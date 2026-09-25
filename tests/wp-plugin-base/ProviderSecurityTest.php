<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/support/phpstan-bootstrap.php';
$GLOBALS['asfw_active_plugins'][] = 'coblocks/class-coblocks.php';
require dirname(__DIR__, 2) . '/integrations/class-asfw-plugin-coblocks.php';

final class ProviderSecurityTest extends AsfwPluginTestCase
{
    public function test_disabled_coblocks_preserves_provider_token_and_response(): void
    {
        $_POST['g-recaptcha-token'] = 'native-token';
        update_option('asfw_integration_coblocks', '');
        ASFW_Plugin_Coblocks::before_form_submit(array(), array());
        $this->assertSame('native-token', $_POST['g-recaptcha-token']);
        $this->assertSame('native-key', apply_filters('pre_option_coblocks_google_recaptcha_site_key', 'native-key'));
        $response = array('native' => true);
        $this->assertSame($response, ASFW_Plugin_Coblocks::verify($response, array(), CoBlocks_Form::GCAPTCHA_VERIFY_URL));
        update_option('asfw_integration_coblocks', 'captcha');
        update_option('asfw_kill_switch', true);
        ASFW_Plugin_Coblocks::before_form_submit(array(), array());
        $this->assertSame('native-token', $_POST['g-recaptcha-token']);
    }

    public function test_enabled_coblocks_rejects_missing_proof_and_cleans_temporary_filters(): void
    {
        update_option('asfw_integration_coblocks', 'captcha');
        $_POST['g-recaptcha-token'] = 'native-token';
        ASFW_Plugin_Coblocks::before_form_submit(array(), array());
        $dummy = $_POST['g-recaptcha-token'];
        $this->assertTrue(apply_filters('pre_option_coblocks_google_recaptcha_secret_key', false));
        $this->assertSame('untouched', ASFW_Plugin_Coblocks::verify('untouched', array('body' => array('response' => $dummy)), 'https://unrelated.example/'));
        $response = ASFW_Plugin_Coblocks::verify(false, array('body' => array('response' => $dummy)), CoBlocks_Form::GCAPTCHA_VERIFY_URL);
        $this->assertFalse(json_decode($response['body'], true)['success']);
        $this->assertSame('native-token', $_POST['g-recaptcha-token']);
        $this->assertSame('native-key', apply_filters('pre_option_coblocks_google_recaptcha_secret_key', 'native-key'));
    }

    public function test_coblocks_accepts_proof_once_and_is_null_safe(): void
    {
        update_option('asfw_integration_coblocks', 'captcha');
        $this->seedPostedWidget('coblocks');
        ASFW_Plugin_Coblocks::before_form_submit(array(), array());
        $args = array('body' => array('response' => $_POST['g-recaptcha-token']));
        $result = ASFW_Plugin_Coblocks::verify(false, $args, CoBlocks_Form::GCAPTCHA_VERIFY_URL);
        $this->assertTrue(json_decode($result['body'], true)['success']);
        ASFW_Plugin_Coblocks::before_form_submit(array(), array());
        $result = ASFW_Plugin_Coblocks::verify(false, $args, CoBlocks_Form::GCAPTCHA_VERIFY_URL);
        $this->assertFalse(json_decode($result['body'], true)['success']);
        $plugin = AntiSpamForWordPressPlugin::$instance;
        AntiSpamForWordPressPlugin::$instance = null;
        try { ASFW_Plugin_Coblocks::before_form_submit(array(), array()); }
        finally { AntiSpamForWordPressPlugin::$instance = $plugin; }
    }

    /** @dataProvider wpdiscuzRoutes */
    public function test_wpdiscuz_dispatch_cannot_downgrade_policy(string $route, bool $loggedIn, string $commentType): void
    {
        update_option('asfw_integration_wordpress_comments', '');
        update_option('asfw_integration_wpdiscuz', 'captcha');
        $GLOBALS['asfw_test_user_logged_in'] = $loggedIn;
        $_POST['asfw_context'] = 'wordpress:comments';
        $_POST['asfw_context_sig'] = $this->plugin()->sign_widget_context('wordpress:comments', 'asfw');
        $callback = static function () use ($commentType) {
            do_action('pre_comment_on_post', 42);
            apply_filters('preprocess_comment', array('comment_post_ID' => 42, 'comment_type' => $commentType));
        };
        add_action($route, $callback);
        try {
            $this->expectException(RuntimeException::class);
            do_action($route);
        } finally { remove_filter($route, $callback); }
    }

    public static function wpdiscuzRoutes(): array
    {
        $cases = array();
        foreach (array('wp_ajax_', 'wp_ajax_nopriv_', 'wpdiscuz_', 'wpdiscuz_nopriv_') as $prefix) {
            foreach (array('wpdAddComment', 'wpdAddInlineComment') as $action) {
                $cases[] = array($prefix . $action, false, 'comment');
                $cases[] = array($prefix . $action, true, 'review');
            }
        }
        return $cases;
    }

    public function test_unsigned_wpdiscuz_dispatch_requires_proof_and_inline_renderer_matches_policy(): void
    {
        update_option('asfw_integration_wordpress_comments', '');
        update_option('asfw_integration_wpdiscuz', 'captcha');
        $markup = apply_filters('wpdiscuz_after_feedback_form_fields', '<input name="message">');
        $this->assertStringContainsString('wpdiscuz:comments', $markup);
        $this->assertStringContainsString('asfw-widget', $markup);
        $callback = static function () { apply_filters('preprocess_comment', array('comment_post_ID' => 42)); };
        add_action('wpdiscuz_nopriv_wpdAddComment', $callback);
        try {
            $this->expectException(RuntimeException::class);
            do_action('wpdiscuz_nopriv_wpdAddComment');
        } finally { remove_filter('wpdiscuz_nopriv_wpdAddComment', $callback); }
    }

    /** @dataProvider nativeRoutes */
    public function test_native_route_cannot_use_a_weaker_signed_provider_context(bool $ajax): void
    {
        update_option('active_plugins', array('wpdiscuz/class.WpdiscuzCore.php'));
        update_option('asfw_integration_wordpress_comments', 'captcha');
        update_option('asfw_integration_wpdiscuz', '');
        update_option('asfw_feature_math_challenge_mode', 'log');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wpdiscuz:comments'));
        $_POST['asfw_context'] = 'wpdiscuz:comments';
        $_POST['asfw_context_sig'] = $this->plugin()->sign_widget_context('wpdiscuz:comments', 'asfw');
        if ($ajax) { $GLOBALS['asfw_test_doing_ajax'] = true; }
        else { do_action('pre_comment_on_post', 42); }
        $this->expectException(RuntimeException::class);
        apply_filters('preprocess_comment', array('comment_post_ID' => 42, 'comment_type' => 'comment'));
    }

    public static function nativeRoutes(): array
    {
        return array(array(false), array(true));
    }

    public function test_extension_registration_hook_and_duplicate_rejection(): void
    {
        $adapter = new class('extension', true, 150) extends ASFW_Integration_Adapter_Base {
            public function register(): void { $GLOBALS['asfw_test_extension_loaded'] = true; }
        };
        $callback = static function ($registry) use ($adapter) { $registry->register($adapter); };
        add_action('asfw_register_integrations', $callback);
        try {
            $registry = ASFW_Integration_Loader::bootstrap();
            $this->assertTrue($GLOBALS['asfw_test_extension_loaded']);
            $this->assertArrayHasKey('extension', $registry->all());
            $this->expectException(InvalidArgumentException::class);
            $registry->register($adapter);
        } finally { remove_filter('asfw_register_integrations', $callback); }
    }
}
