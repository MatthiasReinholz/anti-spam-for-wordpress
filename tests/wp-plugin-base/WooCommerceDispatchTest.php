<?php
declare(strict_types=1);

final class WooCommerceDispatchTest extends AsfwPluginTestCase
{
    private array $previousServer;
    private $previousPage;

    protected function setUp(): void
    {
        $this->previousServer = $_SERVER;
        $this->previousPage = $GLOBALS['pagenow'] ?? null;
        parent::setUp();
        unset($GLOBALS['pagenow'], $_SERVER['SCRIPT_FILENAME']);
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = '/checkout/';
        $GLOBALS['asfw_test_nonce_actions'] = array('woo-login' => 'woocommerce-login');
        $_POST['woocommerce-login-nonce'] = 'woo-login';
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_login, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_login, 'captcha');
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->previousServer;
        if (null === $this->previousPage) { unset($GLOBALS['pagenow']); }
        else { $GLOBALS['pagenow'] = $this->previousPage; }
        parent::tearDown();
    }

    /** @dataProvider loginForms */
    public function test_provider_login_accepts_its_rendered_proof_once_on_every_form_route(string $route, bool $fallback): void
    {
        $_SERVER['REQUEST_URI'] = $route;
        if ($fallback) { update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_login, ''); }
        $this->seedPostedWidget($fallback ? 'wordpress:login' : 'woocommerce:login');
        $this->markProviderLogin();
        $this->assertNotInstanceOf(WP_Error::class, apply_filters('authenticate', null, 'demo', 'secret'));
        $this->assertFalse(ASFW_WooCommerce_Login_Dispatch::is_active());
        $this->assertInstanceOf(WP_Error::class, apply_filters('authenticate', null, 'demo', 'secret'));
    }

    public static function loginForms(): array
    {
        return array(array('/checkout/', false), array('/checkout/', true), array('/members/', false), array('/members/', true));
    }

    public function test_provider_username_matches_core_normalization_and_email_authentication(): void
    {
        foreach (array('<b>demo</b>%20', 'demo@example.com', 'demo   user') as $username) {
            $this->seedPostedWidget('woocommerce:login');
            apply_filters('woocommerce_login_credentials', array('user_login' => $username, 'user_password' => 'secret'));
            $this->assertNotInstanceOf(WP_Error::class, apply_filters('authenticate', null, sanitize_user($username), 'secret'));
        }
    }

    public function test_anonymous_remote_product_reviews_cannot_avoid_comment_proof(): void
    {
        $GLOBALS['asfw_test_doing_ajax'] = true;
        $this->expectException(RuntimeException::class);
        apply_filters('preprocess_comment', array('comment_post_ID' => 42, 'comment_type' => 'review'));
    }

    public function test_nonce_and_account_url_without_provider_dispatch_cannot_select_woo_policy(): void
    {
        $_SERVER['REQUEST_URI'] = '/my-account/';
        $this->seedPostedWidget('woocommerce:login');
        $this->assertInstanceOf(WP_Error::class, apply_filters('authenticate', null, 'demo', 'secret'));
    }

    public function test_provider_marker_cannot_override_native_login_entrypoint(): void
    {
        $this->seedPostedWidget('woocommerce:login');
        $this->markProviderLogin();
        $_SERVER['SCRIPT_NAME'] = '/wp-login.php';
        $this->assertInstanceOf(WP_Error::class, apply_filters('authenticate', null, 'demo', 'secret'));
    }

    public function test_invalid_nonce_does_not_establish_provider_dispatch(): void
    {
        $_POST['woocommerce-login-nonce'] = 'invalid';
        $this->seedPostedWidget('woocommerce:login');
        $this->markProviderLogin();
        $this->assertInstanceOf(WP_Error::class, apply_filters('authenticate', null, 'demo', 'secret'));
    }

    public function test_legacy_provider_nonce_field_remains_supported(): void
    {
        unset($_POST['woocommerce-login-nonce']);
        $_POST['_wpnonce'] = 'woo-login';
        $this->seedPostedWidget('woocommerce:login');
        $this->markProviderLogin();
        $this->assertNotInstanceOf(WP_Error::class, apply_filters('authenticate', null, 'demo', 'secret'));
    }

    public function test_provider_marker_is_consumed_even_when_username_does_not_match(): void
    {
        $this->seedPostedWidget('woocommerce:login');
        $this->markProviderLogin();
        $this->assertInstanceOf(WP_Error::class, apply_filters('authenticate', null, 'another-user', 'secret'));
        $this->assertInstanceOf(WP_Error::class, apply_filters('authenticate', null, 'demo', 'secret'));
    }

    public function test_authentication_error_clears_provider_dispatch(): void
    {
        $this->markProviderLogin();
        $error = new WP_Error('invalid_credentials');
        $this->assertSame($error, apply_filters('authenticate', $error, 'demo', 'secret'));
        $this->assertFalse(ASFW_WooCommerce_Login_Dispatch::is_active());
        $this->seedPostedWidget('woocommerce:login');
        $this->assertInstanceOf(WP_Error::class, apply_filters('authenticate', null, 'demo', 'secret'));
    }

    public function test_nested_authentication_does_not_inherit_or_erase_outer_dispatch(): void
    {
        $this->seedPostedWidget('woocommerce:login');
        $this->markProviderLogin();
        $nested = false;
        $callback = function ($user) use (&$nested) {
            if (!$nested) {
                $nested = true;
                $this->assertInstanceOf(WP_Error::class, apply_filters('authenticate', null, 'other', 'secret'));
                $this->assertTrue(ASFW_WooCommerce_Login_Dispatch::is_active());
            }
            return $user;
        };
        add_filter('authenticate', $callback, 10);
        try { $this->assertNotInstanceOf(WP_Error::class, apply_filters('authenticate', null, 'demo', 'secret')); }
        finally { remove_filter('authenticate', $callback, 10); }
    }

    public function test_registration_requires_proof_at_the_verified_account_form_boundary(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_register, 'captcha');
        $errors = apply_filters('woocommerce_process_registration_errors', new WP_Error(), 'demo', 'secret', 'demo@example.com');
        $this->assertSame('asfw_error_message', $errors->get_error_code());
        $this->seedPostedWidget('woocommerce:register', 'asfw_register');
        $errors = apply_filters('woocommerce_process_registration_errors', new WP_Error(), 'demo', 'secret', 'demo@example.com');
        $this->assertSame('', $errors->get_error_code());
        do_action('woocommerce_register_post', 'demo', 'demo@example.com', $errors);
        $this->assertSame('', $errors->get_error_code());
        $this->assertSame('asfw_error_message', apply_filters('woocommerce_process_registration_errors', new WP_Error())->get_error_code());
    }

    public function test_checkout_and_programmatic_customer_creation_do_not_require_unrendered_widget(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_register, 'captcha');
        $errors = new WP_Error();
        do_action('woocommerce_register_post', 'demo', 'demo@example.com', $errors);
        $this->assertSame('', $errors->get_error_code());
    }

    public function test_native_product_reviews_require_proof(): void
    {
        do_action('pre_comment_on_post', 42);
        $this->expectException(RuntimeException::class);
        apply_filters('preprocess_comment', array('comment_post_ID' => 42, 'comment_type' => 'review'));
    }

    public function test_native_product_reviews_accept_the_rendered_comment_proof(): void
    {
        $this->seedPostedWidget('wordpress:comments');
        do_action('pre_comment_on_post', 42);
        $review = array('comment_post_ID' => 42, 'comment_type' => 'review');
        $this->assertSame($review, apply_filters('preprocess_comment', $review));
    }

    public function test_review_imports_and_pingbacks_preserve_programmatic_behavior(): void
    {
        $review = array('comment_post_ID' => 42, 'comment_type' => 'review');
        $this->assertSame($review, apply_filters('preprocess_comment', $review));
        do_action('pre_comment_on_post', 42);
        $pingback = array('comment_post_ID' => 42, 'comment_type' => 'pingback');
        $this->assertSame($pingback, apply_filters('preprocess_comment', $pingback));
    }

    private function markProviderLogin(): void
    {
        apply_filters('woocommerce_login_credentials', array('user_login' => 'demo', 'user_password' => 'secret'));
    }
}
