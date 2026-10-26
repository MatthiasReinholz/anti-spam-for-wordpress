<?php
/** Real CF7 submission lifecycle against an isolated temporary WordPress database. */
declare(strict_types=1);

if (getenv('ASFW_CF7_DISPOSABLE') !== 'true') {
    throw new RuntimeException('Use scripts/test-contact-form-coverage.sh with its disposable database.');
}
if ($argc === 1) {
    foreach (['captcha-subscriber-missing', 'shortcode-subscriber-missing', 'captcha-subscriber-valid', 'shortcode-subscriber-valid', 'captcha-skip-spam-missing', 'shortcode-skip-mail-missing', 'prior-invalid', 'captcha-normal-valid', 'shortcode-context-filter', 'kill-switch', 'disabled'] as $case) {
        $process = proc_open([PHP_BINARY, __FILE__, $case], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) { throw new RuntimeException('Could not start isolated CF7 fixture request.'); }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) { throw new RuntimeException($case . ' failed: ' . $error . $output); }
        echo $output;
        if ($error !== '') { fwrite(STDERR, $error); }
    }
    echo "Real Contact Form 7 ASFW lifecycle coverage passed.\n";
    exit;
}

ob_start();
$case = $argv[1];
$core = getenv('WP_TEST_CORE_ROOT');
$provider = getenv('ASFW_CF7_ROOT');
if (!$core || !is_file($core . '/wp-settings.php') || !$provider || !is_file($provider . '/wp-contact-form-7.php')) {
    throw new RuntimeException('Explicit WordPress and Contact Form 7 fixtures are required.');
}
define('ABSPATH', rtrim($core, '/') . '/');
define('DB_NAME', 'asfw_cf7'); define('DB_USER', 'root');
define('DB_PASSWORD', 'disposable-asfw-cf7'); define('DB_HOST', 'asfw-cf7-db');
define('DB_CHARSET', 'utf8mb4'); define('DB_COLLATE', '');
define('WP_INSTALLING', true); define('DISABLE_WP_CRON', true); define('WP_HTTP_BLOCK_EXTERNAL', true);
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
define('WP_CONTENT_DIR', '/tmp/asfw-cf7-content-' . getmypid());
define('WP_CONTENT_URL', 'https://forms.example.invalid/content');
define('WP_PLUGIN_DIR', '/tmp/asfw-cf7-plugins-' . getmypid());
mkdir(WP_PLUGIN_DIR, 0700, true);
symlink($provider, WP_PLUGIN_DIR . '/contact-form-7');
symlink(dirname(__DIR__, 2), WP_PLUGIN_DIR . '/anti-spam-for-wordpress');
$table_prefix = 'cf7_' . bin2hex(random_bytes(4)) . '_';
$_SERVER['HTTP_HOST'] = 'forms.example.invalid';
$_SERVER['SERVER_NAME'] = 'forms.example.invalid';
$_SERVER['REQUEST_URI'] = '/contact/';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '203.0.113.21';
$_SERVER['HTTP_USER_AGENT'] = 'ASFW isolated Contact Form 7 fixture';
$_SERVER['HTTPS'] = 'on';
require ABSPATH . 'wp-settings.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
add_filter('pre_wp_mail', '__return_true', -100);
add_filter('pre_http_request', static fn() => new WP_Error('fixture_network_blocked', 'Network disabled.'), -100);
wp_install('ASFW disposable CF7 fixture', 'fixture-admin', 'admin@example.invalid', false, '', 'disposable-admin-password');
update_option('active_plugins', ['contact-form-7/wp-contact-form-7.php', 'anti-spam-for-wordpress/anti-spam-for-wordpress.php']);
update_option('home', 'https://forms.example.invalid');
update_option('siteurl', 'https://forms.example.invalid');
require $provider . '/wp-contact-form-7.php';
require dirname(__DIR__, 2) . '/anti-spam-for-wordpress.php';
do_action('plugins_loaded');
do_action('init');
asfw_activate(); asfw_init();
update_option('asfw_integration_contact_form_7', str_starts_with($case, 'shortcode') ? 'shortcode' : 'captcha');
update_option('asfw_min_submit_time', '0');
update_option('asfw_complexity', 'low');
update_option('asfw_rate_limit_max_challenges', '100');
update_option('asfw_kill_switch', $case === 'kill-switch' ? '1' : '0');
if ($case === 'disabled') { update_option('asfw_integration_contact_form_7', ''); }
$subscriber = wp_insert_user(['user_login' => 'fixture-customer', 'user_email' => 'customer@example.invalid', 'user_pass' => 'disposable-customer-password', 'role' => 'subscriber']);
if (is_wp_error($subscriber)) { throw new RuntimeException('Could not create the fixture subscriber.'); }
wp_set_current_user($subscriber);

function asfw_cf7_check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
    echo 'PASS ' . $message . "\n";
}
function asfw_cf7_proof(string $context, string $field): array {
    $plugin = asfw_plugin_instance();
    $challenge = $plugin->generate_challenge(null, 'low', 300, $context);
    if (!is_array($challenge)) { throw new RuntimeException('Could not issue fixture proof.'); }
    for ($number = 0; $number <= (int)$challenge['maxnumber']; $number++) {
        if (hash('sha256', $challenge['salt'] . $number) !== $challenge['challenge']) { continue; }
        return [
            $field => base64_encode(wp_json_encode(['algorithm' => $challenge['algorithm'], 'challenge' => $challenge['challenge'], 'number' => $number, 'salt' => $challenge['salt'], 'signature' => $challenge['signature']])),
            $field . '_started' => (string)(time() - 10),
            $field . '_context' => $context,
            $field . '_context_sig' => $plugin->sign_widget_context($context, $field),
            $field . '_website' => '',
        ];
    }
    throw new RuntimeException('Could not solve fixture proof.');
}

$form = WPCF7_ContactForm::get_template(['title' => 'Disposable verification fixture']);
$properties = $form->get_properties();
$properties['form'] = '[text* your-name] [email* your-email] [submit Send]';
if (str_starts_with($case, 'shortcode')) {
    $properties['form'] .= ' [anti_spam_widget mode="captcha" context="contact:fixture" name="contact_guard"]';
}
$properties['additional_settings'] = str_contains($case, 'subscriber') ? 'subscribers_only: true' : '';
if (str_contains($case, 'skip-mail')) { $properties['additional_settings'] .= "\nskip_mail: true"; }
$form->set_properties($properties);
$form->save();
if ($case === 'shortcode-context-filter') { add_filter('asfw_widget_context', static fn($context) => 'filtered:' . $context); }
if (str_contains($case, 'skip-spam')) { add_filter('wpcf7_skip_spam_check', '__return_true'); }
$verified = 0;
add_action('asfw_submission_verified', static function () use (&$verified): void { $verified++; });
$actions = 0;
add_action('wpcf7_before_send_mail', static function () use (&$actions): void { $actions++; });
$_POST = [
    '_wpcf7' => (string)$form->id(), '_wpcf7_unit_tag' => 'wpcf7-f' . $form->id() . '-o1',
    'your-name' => $case === 'prior-invalid' ? '' : 'Fixture visitor', 'your-email' => 'visitor@example.invalid',
];
$validProof = in_array($case, ['captcha-subscriber-valid', 'shortcode-subscriber-valid', 'captcha-normal-valid', 'shortcode-context-filter', 'prior-invalid'], true);
$context = str_starts_with($case, 'shortcode') ? ($case === 'shortcode-context-filter' ? 'filtered:contact:fixture' : 'contact:fixture') : 'contact-form-7';
$field = str_starts_with($case, 'shortcode') ? 'contact_guard' : 'asfw';
if ($validProof) { $_POST += asfw_cf7_proof($context, $field); }
$markup = $form->form_elements();
if (!in_array($case, ['kill-switch', 'disabled'], true)) {
    asfw_cf7_check(str_contains($markup, 'data-name="asfw_verification"'), $case . ': rendered provider validation target');
}
if (str_contains($case, 'subscriber')) {
    asfw_cf7_check(current_user_can('wpcf7_submit', $form->id()) && !current_user_can('manage_options'), $case . ': ordinary subscriber permission');
}
$result = $form->submit();
if ($case === 'prior-invalid') {
    asfw_cf7_check($result['status'] === 'validation_failed' && $actions === 0 && $verified === 0, $case . ': provider validation precedes proof consumption and actions');
    asfw_cf7_check(asfw_verify_posted_widget($context, $field), $case . ': unused proof remains valid');
} elseif ($validProof || in_array($case, ['kill-switch', 'disabled'], true)) {
    asfw_cf7_check($result['status'] === 'mail_sent' && $actions === 1, $case . ': one successful provider action');
    asfw_cf7_check($verified === ($validProof ? 1 : 0), $case . ': proof verified exactly once without bypass signal');
    if ($validProof) { asfw_cf7_check(!asfw_verify_posted_widget($context, $field), $case . ': proof cannot be replayed'); }
} else {
    asfw_cf7_check($result['status'] === 'validation_failed' && $actions === 0 && $verified === 0, $case . ': missing proof rejected before provider actions');
    asfw_cf7_check(isset($result['invalid_fields']['asfw_verification']), $case . ': provider exposes verification error');
}
$output = ob_get_clean();
echo $output;
