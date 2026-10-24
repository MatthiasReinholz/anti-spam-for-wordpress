<?php
/** Real provider dispatch and database effects; never bootstrap a store database. */
declare(strict_types=1);

if (getenv('ASFW_FORM_COVERAGE_DISPOSABLE') !== 'true') {
    throw new RuntimeException('Use scripts/test-form-coverage.sh with its disposable database.');
}
if ($argc === 1) {
    foreach (['classic-missing', 'classic-valid', 'classic-preflight', 'store-missing', 'store-wrong-context', 'store-valid-replay', 'store-invalid-nonce', 'store-schema'] as $case) {
        $process = proc_open([PHP_BINARY, __FILE__, $case], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Could not start isolated form fixture request.'); }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException($case . ' failed: ' . $error . $output); }
        echo $output;
        if ($error !== '') { fwrite(STDERR, $error); }
    }
    echo 'Real WooCommerce ASFW ' . getenv('ASFW_FORM_STORAGE') . " form coverage passed.\n";
    exit;
}

ob_start(); // Real checkout emits headers and JSON; keep assertions out of its response.
$case = $argv[1];
$core = getenv('WP_TEST_CORE_ROOT');
$woo = getenv('ASFW_WOOCOMMERCE_ROOT');
if (!$core || !is_file($core . '/wp-settings.php') || !$woo || !is_file($woo . '/woocommerce.php')) {
    throw new RuntimeException('The explicit WordPress and WooCommerce fixtures are required.');
}
define('ABSPATH', rtrim($core, '/') . '/');
define('DB_NAME', 'asfw_forms'); define('DB_USER', 'root');
define('DB_PASSWORD', 'disposable-asfw-forms'); define('DB_HOST', 'asfw-form-db');
define('DB_CHARSET', 'utf8mb4'); define('DB_COLLATE', '');
define('WP_INSTALLING', true); define('DISABLE_WP_CRON', true); define('WP_HTTP_BLOCK_EXTERNAL', true);
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
define('DOING_AJAX', true);
define('WP_CONTENT_DIR', '/tmp/asfw-form-content-' . getmypid());
define('WP_CONTENT_URL', 'https://forms.example.invalid/content');
define('WP_PLUGIN_DIR', '/tmp/asfw-form-plugins-' . getmypid());
mkdir(WP_PLUGIN_DIR, 0700, true);
symlink($woo, WP_PLUGIN_DIR . '/woocommerce');
symlink(dirname(__DIR__, 2), WP_PLUGIN_DIR . '/anti-spam-for-wordpress');
$table_prefix = 'forms_' . bin2hex(random_bytes(4)) . '_';
$_SERVER['HTTP_HOST'] = 'forms.example.invalid';
$_SERVER['SERVER_NAME'] = 'forms.example.invalid';
$_SERVER['REQUEST_URI'] = '/checkout/';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '203.0.113.19';
$_SERVER['HTTP_USER_AGENT'] = 'ASFW isolated provider fixture';
$_SERVER['HTTPS'] = 'on';
require ABSPATH . 'wp-settings.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
add_filter('pre_wp_mail', '__return_true', -100);
add_filter('pre_http_request', static fn() => new WP_Error('fixture_network_blocked', 'Network disabled.'), -100);
wp_install('ASFW disposable form fixture', 'fixture-admin', 'admin@example.invalid', false, '', 'disposable-admin-password');
update_option('active_plugins', ['woocommerce/woocommerce.php', 'anti-spam-for-wordpress/anti-spam-for-wordpress.php']);
update_option('home', 'https://forms.example.invalid');
update_option('siteurl', 'https://forms.example.invalid');
update_option('woocommerce_custom_orders_table_enabled', getenv('ASFW_FORM_STORAGE') === 'HPOS' ? 'yes' : 'no');
update_option('woocommerce_enable_guest_checkout', 'yes');
update_option('woocommerce_enable_signup_and_login_from_checkout', 'yes');
update_option('woocommerce_registration_generate_username', 'yes');
update_option('woocommerce_registration_generate_password', 'yes');
update_option('woocommerce_default_country', 'DE');
update_option('woocommerce_currency', 'EUR');
update_option('woocommerce_bacs_settings', ['enabled' => 'yes']);
define('WC_TAX_ROUNDING_MODE', 'auto');
require $woo . '/woocommerce.php';
WC_Install::create_tables();
WC()->init();
WC()->frontend_includes();
WC()->include_template_functions();
WC_Post_Types::register_post_types();
WC_Post_Types::register_post_status();
WC_Post_Types::register_taxonomies();
WC_Install::create_tables();
WC()->initialize_session();
WC()->initialize_cart();
WC()->session->set_customer_session_cookie(true);
do_action('wp_loaded');
add_filter('woocommerce_logger_log_message', static function ($message, $level) { if ($level === 'error') { fwrite(STDERR, (string)$message . "\n"); } return $message; }, 10, 2);
foreach (WC()->mailer()->get_emails() as $email) { add_filter('woocommerce_email_enabled_' . $email->id, '__return_false', PHP_INT_MAX); }
require dirname(__DIR__, 2) . '/anti-spam-for-wordpress.php';
asfw_activate(); asfw_init();
update_option('asfw_integration_woocommerce_checkout', 'captcha');
update_option('asfw_min_submit_time', '0');
update_option('asfw_complexity', 'low');
update_option('asfw_rate_limit_max_challenges', '100');
update_option('asfw_kill_switch', '0');
$product = new WC_Product_Simple();
$product->set_name('Disposable verification fixture');
$product->set_regular_price('10');
$product->set_virtual(true);
$product->save();
WC()->cart->add_to_cart($product->get_id(), 1);
WC()->cart->calculate_totals();

function asfw_forms_check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
    echo 'PASS ' . $message . "\n";
}
function asfw_forms_proof(string $context = 'woocommerce:checkout'): array {
    $plugin = asfw_plugin_instance();
    $challenge = $plugin->generate_challenge(null, 'low', 300, $context);
    if (!is_array($challenge)) { throw new RuntimeException('Could not issue fixture proof.'); }
    for ($number = 0; $number <= (int)$challenge['maxnumber']; $number++) {
        if (hash('sha256', $challenge['salt'] . $number) !== $challenge['challenge']) { continue; }
        return [
            'asfw_checkout' => base64_encode(wp_json_encode(['algorithm' => $challenge['algorithm'], 'challenge' => $challenge['challenge'], 'number' => $number, 'salt' => $challenge['salt'], 'signature' => $challenge['signature']])),
            'asfw_checkout_started' => (string)(time() - 10),
            'asfw_checkout_context' => $context,
            'asfw_checkout_context_sig' => $plugin->sign_widget_context($context, 'asfw_checkout'),
            'asfw_checkout_website' => '',
        ];
    }
    throw new RuntimeException('Could not solve fixture proof.');
}
function asfw_forms_counts(): array {
    global $wpdb;
    return ['users' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}"), 'orders' => count(wc_get_orders(['limit' => -1, 'return' => 'ids', 'status' => array_merge(array_keys(wc_get_order_statuses()), ['wc-checkout-draft'])]))];
}
function asfw_forms_fields(): array {
    return ['billing_first_name' => 'Fixture', 'billing_last_name' => 'Customer', 'billing_country' => 'DE', 'billing_address_1' => 'Example Street 1', 'billing_city' => 'Berlin', 'billing_postcode' => '10115', 'billing_email' => 'customer@example.invalid', 'billing_phone' => '+49305550000', 'payment_method' => 'bacs', 'terms' => '1', 'createaccount' => '1', 'woocommerce-process-checkout-nonce' => wp_create_nonce('woocommerce-process_checkout')];
}
final class AsfwFormsResponse extends Error {}
add_filter('wp_die_ajax_handler', static fn() => static function () { throw new AsfwFormsResponse('Captured fixture response'); });
function asfw_forms_classic(): array {
    $_REQUEST = $_POST;
    ob_start();
    try { WC()->checkout()->process_checkout(); }
    catch (AsfwFormsResponse $done) {}
    $body = (string)ob_get_clean();
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) { throw new RuntimeException('Checkout did not return JSON: ' . $body); }
    return $decoded;
}
function asfw_forms_store(array $proof, string $nonce = ''): WP_REST_Response {
    $request = new WP_REST_Request('POST', '/wc/store/v1/checkout');
    $request->set_header('Content-Type', 'application/json');
    $request->set_header('Nonce', $nonce ?: wp_create_nonce('wc_store_api'));
    $request->set_body(wp_json_encode([
        'billing_address' => ['first_name' => 'Fixture', 'last_name' => 'Customer', 'email' => 'store@example.invalid', 'phone' => '+49305550000', 'country' => 'DE', 'address_1' => 'Example Street 1', 'city' => 'Berlin', 'postcode' => '10115'],
        'payment_method' => 'bacs', 'create_account' => true,
        'extensions' => [ASFW_WooCommerce_Checkout::NAMESPACE => $proof],
    ]));
    return rest_get_server()->dispatch($request);
}

$before = asfw_forms_counts();
if (str_starts_with($case, 'classic-')) {
    $_POST = asfw_forms_fields();
    if ($case !== 'classic-missing') { $_POST += asfw_forms_proof(); }
    if ($case === 'classic-preflight') {
        $gateway = getenv('ASFW_REVOLUT_ROOT');
        if ($gateway && is_file($gateway . '/includes/class-wc-revolut-validate-checkout.php')) {
            require $gateway . '/includes/class-wc-revolut-validate-checkout.php';
            (new WC_Revolut_Validate_Checkout())->validate_checkout_fields();
            asfw_forms_check(wc_notice_count('error') === 0, 'actual Revolut preflight preserves proof');
        } else {
            $get = new ReflectionMethod(WC_Checkout::class, 'get_posted_data'); $get->setAccessible(true);
            $validate = new ReflectionMethod(WC_Checkout::class, 'validate_checkout'); $validate->setAccessible(true);
            $data = $get->invoke(WC()->checkout()); $errors = new WP_Error();
            $validate->invokeArgs(WC()->checkout(), [&$data, &$errors]);
            asfw_forms_check(!$errors->has_errors(), 'actual Woo validation preflight preserves proof');
        }
        asfw_forms_check(asfw_forms_counts() === $before, 'preflight creates no account or order');
    }
    $response = asfw_forms_classic();
    $after = asfw_forms_counts();
    if ($case === 'classic-missing') {
        asfw_forms_check(($response['result'] ?? '') === 'failure' && str_contains($response['messages'] ?? '', 'spam verification'), 'actual classic checkout rejects missing proof: ' . wp_json_encode($response));
        asfw_forms_check($after === $before, 'rejected classic checkout creates no user or order');
    } else {
        asfw_forms_check(($response['result'] ?? '') === 'success', 'actual classic checkout accepts verified proof');
        asfw_forms_check($after['users'] === $before['users'] + 1 && $after['orders'] === $before['orders'] + 1, 'verified classic checkout creates exactly one customer and order');
    }
} else {
    Automattic\WooCommerce\Blocks\Package::init();
    do_action('woocommerce_blocks_loaded');
    ASFW_WooCommerce_Checkout::register_schema();
    do_action('rest_api_init', rest_get_server());
    if ($case === 'store-schema') {
        Automattic\WooCommerce\Blocks\Package::container()->get(Automattic\WooCommerce\Blocks\AssetsController::class)->register_assets();
        asfw_forms_check(ASFW_WooCommerce_Checkout::supports_blocks(), 'tested WooCommerce registers every required Blocks adapter capability');
        $routes = rest_get_server()->get_routes();
        $registered = false;
        foreach ($routes['/wc/store/v1/checkout'] ?? [] as $endpoint) {
            $fields = $endpoint['args']['extensions']['properties'][ASFW_WooCommerce_Checkout::NAMESPACE]['properties'] ?? [];
            if (isset($endpoint['methods']['POST']) && array_keys($fields) === ASFW_WooCommerce_Checkout::field_names()) { $registered = true; }
        }
        asfw_forms_check($registered, 'real Store API schema accepts the registered proof namespace');
    } else {
        $proof = $case === 'store-missing' ? [] : asfw_forms_proof($case === 'store-wrong-context' ? 'woocommerce:register' : 'woocommerce:checkout');
        $response = asfw_forms_store($proof, $case === 'store-invalid-nonce' ? 'invalid-nonce' : '');
        $data = $response->get_data(); $after = asfw_forms_counts();
        if ($case === 'store-valid-replay') {
            asfw_forms_check($response->get_status() === 200, 'real Store API dispatch accepts JSON proof: ' . wp_json_encode($data));
            asfw_forms_check($after['users'] === $before['users'] + 1 && $after['orders'] === $before['orders'] + 1, 'verified Store API creates exactly one customer and order');
            wp_set_current_user(0); WC()->customer = new WC_Customer(0, true);
            $replay = asfw_forms_store($proof);
            asfw_forms_check($replay->get_status() === 422 && ($replay->get_data()['code'] ?? '') === 'asfw_checkout_verification', 'real Store API rejects replay before provider mutation');
            asfw_forms_check(asfw_forms_counts() === $after, 'replay creates no additional user or order');
        } elseif ($case === 'store-invalid-nonce') {
            asfw_forms_check($response->get_status() === 403 && ($data['code'] ?? '') === 'woocommerce_rest_invalid_nonce', 'Store API still enforces its callback-internal nonce after proof');
            asfw_forms_check($after === $before, 'invalid Store API nonce creates no user or order');
        } else {
            asfw_forms_check($response->get_status() === 422 && ($data['code'] ?? '') === 'asfw_checkout_verification', 'real Store API rejects missing or wrong-context proof: ' . wp_json_encode(['status' => $response->get_status(), 'data' => $data]));
            asfw_forms_check($after === $before, 'rejected Store API request creates no user or order');
        }
    }
}
$output = (string)ob_get_clean();
echo $output;
