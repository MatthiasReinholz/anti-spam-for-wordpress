<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/integrations/class-asfw-woocommerce-checkout.php';

if (!function_exists('wp_script_is')) {
    function wp_script_is($handle, $state = 'enqueued') { return !in_array($handle, $GLOBALS['asfw_checkout_missing_scripts'] ?? [], true); }
}
if (!function_exists('woocommerce_store_api_register_endpoint_data')) {
    function woocommerce_store_api_register_endpoint_data($args) {}
}

/** The repository's REST stub intentionally models only the common request APIs. */
final class AsfwCheckoutRequest extends WP_REST_Request
{
    private string $checkoutMethod;
    private string $checkoutRoute;

    public function __construct(array $params = [], string $method = 'POST', string $route = '/wc/store/v1/checkout')
    {
        parent::__construct($params);
        $this->checkoutMethod = $method;
        $this->checkoutRoute = $route;
    }

    public function get_method() { return $this->checkoutMethod; }
    public function get_route() { return $this->checkoutRoute; }
}

final class WooCommerceCheckoutTest extends AsfwPluginTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['asfw_checkout_missing_scripts'] = [];
        update_option('asfw_integration_woocommerce_checkout', 'captcha');
    }

    private function requestWithProof(string $context = 'woocommerce:checkout'): AsfwCheckoutRequest
    {
        $this->seedPostedWidget($context, 'asfw_checkout');
        $fields = $_POST;
        $_POST = ['unrelated' => 'preserved'];
        return new AsfwCheckoutRequest([
            'extensions' => ['anti-spam-for-wordpress' => $fields],
            'billing_address' => ['email' => 'customer@example.com'],
        ]);
    }

    public function test_classic_checkout_rejects_missing_proof_and_accepts_valid_proof_once(): void
    {
        ASFW_WooCommerce_Checkout::begin_classic();
        $errors = new WP_Error();
        ASFW_WooCommerce_Checkout::validate_classic([], $errors);
        self::assertSame('asfw_checkout_verification', $errors->get_error_code());

        $this->seedPostedWidget('woocommerce:checkout', 'asfw_checkout');
        ASFW_WooCommerce_Checkout::begin_classic();
        $errors = new WP_Error();
        ASFW_WooCommerce_Checkout::validate_classic([], $errors);
        self::assertSame('', $errors->get_error_code());
        ASFW_WooCommerce_Checkout::validate_classic([], $errors);
        self::assertSame('', $errors->get_error_code(), 'Repeated validation in one native dispatch must not consume proof twice.');
        ASFW_WooCommerce_Checkout::begin_classic();
        ASFW_WooCommerce_Checkout::validate_classic([], $errors);
        self::assertSame('asfw_checkout_verification', $errors->get_error_code());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_gateway_preflight_does_not_consume_checkout_proof(): void
    {
        $this->seedPostedWidget('woocommerce:checkout', 'asfw_checkout');
        $preflight = new WP_Error();
        ASFW_WooCommerce_Checkout::validate_classic([], $preflight);
        self::assertSame('', $preflight->get_error_code());
        ASFW_WooCommerce_Checkout::begin_classic();
        $processing = new WP_Error();
        ASFW_WooCommerce_Checkout::validate_classic([], $processing);
        self::assertSame('', $processing->get_error_code());
    }

    public function test_unrelated_classic_validation_error_does_not_consume_proof(): void
    {
        $this->seedPostedWidget('woocommerce:checkout', 'asfw_checkout');
        ASFW_WooCommerce_Checkout::begin_classic();
        $existing = new WP_Error('billing_invalid', 'Invalid address');
        ASFW_WooCommerce_Checkout::validate_classic([], $existing);
        self::assertSame(['Invalid address'], $existing->get_error_messages());
        $retry = new WP_Error();
        ASFW_WooCommerce_Checkout::validate_classic([], $retry);
        self::assertSame('', $retry->get_error_code());
    }

    public function test_store_api_reads_json_proof_without_mutating_post_and_rejects_replay(): void
    {
        $request = $this->requestWithProof();
        self::assertNull(ASFW_WooCommerce_Checkout::validate_store_api(null, $request));
        self::assertSame(['unrelated' => 'preserved'], $_POST);
        $replay = ASFW_WooCommerce_Checkout::validate_store_api(null, $request);
        self::assertInstanceOf(WP_Error::class, $replay);
        self::assertSame('asfw_checkout_verification', $replay->get_error_code());
    }

    /** @dataProvider rejectedExtensionValues */
    public function test_store_api_rejects_missing_or_malformed_proof_extensions($extension): void
    {
        $request = new AsfwCheckoutRequest(['extensions' => $extension]);
        self::assertInstanceOf(WP_Error::class, ASFW_WooCommerce_Checkout::validate_store_api(null, $request));
    }

    public static function rejectedExtensionValues(): array
    {
        return [[null], ['invalid'], [['anti-spam-for-wordpress' => null]], [['anti-spam-for-wordpress' => (object) ['asfw_checkout' => 'invalid']]], [['anti-spam-for-wordpress' => ['asfw_checkout' => ['invalid']]]]];
    }

    public function test_store_api_rejects_proof_from_another_form_context(): void
    {
        $request = $this->requestWithProof('woocommerce:register');
        self::assertInstanceOf(WP_Error::class, ASFW_WooCommerce_Checkout::validate_store_api(null, $request));
    }

    public function test_existing_rest_error_does_not_consume_valid_proof(): void
    {
        $request = $this->requestWithProof();
        $existing = new WP_Error('other_extension', 'Unavailable');
        self::assertSame($existing, ASFW_WooCommerce_Checkout::validate_store_api($existing, $request));
        self::assertNull(ASFW_WooCommerce_Checkout::validate_store_api(null, $request));
    }

    /** @dataProvider unprotectedRoutes */
    public function test_store_api_only_guards_checkout_post(string $method, string $route): void
    {
        $request = new AsfwCheckoutRequest([], $method, $route);
        self::assertNull(ASFW_WooCommerce_Checkout::validate_store_api(null, $request));
    }

    public static function unprotectedRoutes(): array
    {
        return [['GET', '/wc/store/v1/checkout'], ['PATCH', '/wc/store/v1/checkout'], ['POST', '/wc/store/v1/cart/update-customer'], ['POST', '/wc/v3/customers'], ['POST', '/wc/store/v1/checkout/123'], ['POST', '/WC/StOrE/V1/ChEcKoUt/123'], ['POST', '/custom/wc/store/v1/checkout']];
    }

    /** @dataProvider mixedCaseCheckoutRoutes */
    public function test_case_insensitive_checkout_routes_require_proof(string $route): void
    {
        $result = ASFW_WooCommerce_Checkout::validate_store_api(null, new AsfwCheckoutRequest([], 'POST', $route));
        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('asfw_checkout_verification', $result->get_error_code());
    }

    public static function mixedCaseCheckoutRoutes(): array
    {
        return [['/wc/store/v1/CHECKOUT'], ['/WC/StOrE/V1/ChEcKoUt/'], ['/wc/store/ChEcKoUt']];
    }

    public function test_unversioned_store_api_alias_is_also_guarded(): void
    {
        $request = new AsfwCheckoutRequest([], 'POST', '/wc/store/checkout');
        self::assertInstanceOf(WP_Error::class, ASFW_WooCommerce_Checkout::validate_store_api(null, $request));
    }

    public function test_authenticated_checkout_and_disabled_setting_bypass_both_renderers(): void
    {
        $GLOBALS['asfw_test_user_logged_in'] = true;
        self::assertFalse(ASFW_WooCommerce_Checkout::requires_protection());
        self::assertNull(ASFW_WooCommerce_Checkout::validate_store_api(null, new AsfwCheckoutRequest()));
        $GLOBALS['asfw_test_user_logged_in'] = false;
        update_option('asfw_integration_woocommerce_checkout', '');
        self::assertFalse(ASFW_WooCommerce_Checkout::requires_protection());
        $errors = new WP_Error();
        ASFW_WooCommerce_Checkout::validate_classic([], $errors);
        self::assertSame('', $errors->get_error_code());
    }

    public function test_native_emergency_kill_switch_bypasses_both_checkout_paths(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_kill_switch, 1);
        self::assertFalse(ASFW_WooCommerce_Checkout::requires_protection());
        self::assertNull(ASFW_WooCommerce_Checkout::validate_store_api(null, new AsfwCheckoutRequest()));
        $errors = new WP_Error();
        ASFW_WooCommerce_Checkout::validate_classic([], $errors);
        self::assertSame('', $errors->get_error_code());
    }

    public function test_classic_rendering_uses_dedicated_context_and_does_not_load_blocks(): void
    {
        ob_start();
        ASFW_WooCommerce_Checkout::render_classic();
        $markup = (string) ob_get_clean();
        self::assertStringContainsString('woocommerce:checkout', $markup);
        self::assertStringContainsString('asfw_checkout', $markup);
        self::assertArrayHasKey('asfw-checkout', $GLOBALS['asfw_test_enqueued_scripts']);
        self::assertNotContains('wc-blocks-checkout', $GLOBALS['asfw_test_enqueued_scripts']['asfw-checkout']['deps']);
    }

    public function test_blocks_rendering_registers_supported_data_and_slot_dependencies(): void
    {
        self::assertSame('<div>Checkout</div>', ASFW_WooCommerce_Checkout::render_blocks('<div>Checkout</div>'));
        $script = $GLOBALS['asfw_test_enqueued_scripts']['asfw-checkout-blocks'];
        self::assertContains('wc-blocks-checkout', $script['deps']);
        self::assertContains('wc-blocks-data-store', $script['deps']);
        $config = $GLOBALS['asfw_test_localized_scripts']['asfw-checkout-blocks']['ASFW_CHECKOUT'];
        self::assertSame('anti-spam-for-wordpress', $config['namespace']);
        self::assertStringContainsString('asfw_checkout_context_sig', $config['markup']);
    }

    public function test_extension_schema_exposes_request_fields_without_reflecting_proof(): void
    {
        $schema = ASFW_WooCommerce_Checkout::schema();
        self::assertSame(ASFW_WooCommerce_Checkout::field_names(), array_keys($schema));
        foreach ($schema as $property) {
            self::assertSame(['edit'], $property['context']);
            self::assertFalse($property['readonly']);
            self::assertSame('string', $property['type']);
        }
    }

    public function test_missing_blocks_capability_is_visible_and_does_not_bypass_server_protection(): void
    {
        $GLOBALS['asfw_checkout_missing_scripts'] = ['wc-blocks-checkout-events'];
        self::assertFalse(ASFW_WooCommerce_Checkout::supports_blocks());
        $content = ASFW_WooCommerce_Checkout::render_blocks('<div>Checkout</div>');
        self::assertStringContainsString('role="alert"', $content);
        self::assertStringContainsString('Checkout verification could not load. Please contact the store.', $content);
        self::assertArrayNotHasKey('asfw-checkout-blocks', $GLOBALS['asfw_test_enqueued_scripts']);
        self::assertInstanceOf(WP_Error::class, ASFW_WooCommerce_Checkout::validate_store_api(null, new AsfwCheckoutRequest()));
    }
}
