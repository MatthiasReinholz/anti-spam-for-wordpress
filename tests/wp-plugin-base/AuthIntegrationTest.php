<?php
declare(strict_types=1);

final class AuthIntegrationTest extends AsfwPluginTestCase
{
    public function test_wordpress_login_guard_blocks_when_math_challenge_is_missing(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:login'));

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw-error', $result->get_error_code());
    }

    public function test_wordpress_register_guard_blocks_when_math_challenge_is_missing(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:register'));

        $errors = new WP_Error();
        do_action('register_post', 'demo', 'demo@example.com', $errors);

        $this->assertInstanceOf(WP_Error::class, $errors);
        $this->assertSame('asfw_error_message', $errors->get_error_code());
    }

    public function test_wordpress_reset_guard_blocks_when_math_challenge_is_missing(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:reset-password'));

        $errors = apply_filters('lostpassword_post', new WP_Error());

        $this->assertInstanceOf(WP_Error::class, $errors);
        $this->assertSame('asfw_error_message', $errors->get_error_code());
    }

    public function test_wordpress_comments_guard_blocks_when_math_challenge_is_missing(): void
    {
        $GLOBALS['asfw_active_plugins'] = array('html-forms/html-forms.php');
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:comments'));

        $this->expectException(RuntimeException::class);
        apply_filters(
            'preprocess_comment',
            array(
                'comment_type' => 'comment',
                'comment_content' => 'Hello',
            )
        );
    }

    public function test_wpdiscuz_comments_guard_blocks_when_math_challenge_is_missing(): void
    {
        $GLOBALS['asfw_active_plugins'] = array('wpdiscuz/class.WpdiscuzCore.php');
        update_option(AntiSpamForWordPressPlugin::$option_integration_wpdiscuz, 'captcha');
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wpdiscuz:comments'));
        $_POST['asfw_context'] = 'wpdiscuz:comments';
        $_POST['asfw_context_sig'] = $this->plugin()->sign_widget_context('wpdiscuz:comments', 'asfw');

        $this->expectException(RuntimeException::class);
        apply_filters(
            'preprocess_comment',
            array(
                'comment_type' => 'comment',
                'comment_content' => 'Hello',
            )
        );
    }

    public function test_wordpress_comments_guard_uses_wordpress_context_when_wpdiscuz_integration_is_disabled(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_wpdiscuz, '');
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:comments'));

        $this->expectException(RuntimeException::class);
        apply_filters(
            'preprocess_comment',
            array(
                'comment_type' => 'comment',
                'comment_content' => 'Hello',
            )
        );
    }

    public function test_wpdiscuz_renderer_falls_back_to_wordpress_context_when_wpdiscuz_integration_is_disabled(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_wpdiscuz, '');
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_comments, 'captcha');

        ob_start();
        do_action('wpdiscuz_button_actions');
        $markup = (string) ob_get_clean();

        $this->assertStringContainsString('wordpress:comments', $markup);
    }

    public function test_woocommerce_login_guard_blocks_when_delay_token_is_missing(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_login, 'captcha');
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'selected');
        update_option('asfw_feature_submit_delay_contexts', array('woocommerce:login'));
        $_POST['woocommerce-login-nonce'] = 'nonce';
        $_SERVER['REQUEST_URI'] = '/my-account/';

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw-error', $result->get_error_code());
    }

    public function test_woocommerce_login_guard_uses_wordpress_fallback_context_when_woo_integration_is_disabled(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_login, '');
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_login, 'captcha');
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:login'));
        $_POST['woocommerce-login-nonce'] = 'nonce';
        $_SERVER['REQUEST_URI'] = '/my-account/';

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw-error', $result->get_error_code());
    }

    public function test_woocommerce_login_guard_detects_custom_account_page_routes(): void
    {
        update_option('woocommerce_myaccount_page_id', 123);
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:login'));
        unset($_POST['woocommerce-login-nonce']);
        $_SERVER['REQUEST_URI'] = '/privacy-policy/';

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw-error', $result->get_error_code());
    }

    public function test_woocommerce_login_guard_detects_plain_permalink_account_page_routes(): void
    {
        update_option('woocommerce_myaccount_page_id', 123);
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:login'));
        unset($_POST['woocommerce-login-nonce']);
        $_SERVER['REQUEST_URI'] = '/?page_id=123';

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw-error', $result->get_error_code());
    }

    public function test_woocommerce_login_uses_wordpress_setting_when_woocommerce_setting_is_disabled(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_login, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_login, '');
        $_POST['woocommerce-login-nonce'] = 'nonce';
        $_SERVER['REQUEST_URI'] = '/my-account/';

        $this->seedPostedWidget('wordpress:login');

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertNotInstanceOf(WP_Error::class, $result);
    }

    public function test_woocommerce_login_form_renders_guard_for_wordpress_fallback_context(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_login, '');
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'selected');
        update_option('asfw_feature_submit_delay_contexts', array('wordpress:login'));

        ob_start();
        do_action('woocommerce_login_form');
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('asfw_submit_delay_token', $html);
    }

    public function test_woocommerce_login_rejects_missing_widget_when_wordpress_login_is_the_only_enabled_protection(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_login, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_login, '');
        $_POST['woocommerce-login-nonce'] = 'nonce';
        $_SERVER['REQUEST_URI'] = '/my-account/';

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw-error', $result->get_error_code());
    }

    public function test_woocommerce_login_uses_woocommerce_context_when_enabled(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_login, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_login, 'captcha');
        $_POST['woocommerce-login-nonce'] = 'nonce';
        $_SERVER['REQUEST_URI'] = '/my-account/';

        $this->seedPostedWidget('woocommerce:login');

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertNotInstanceOf(WP_Error::class, $result);
    }

    public function test_woocommerce_login_nonce_does_not_bypass_verification_when_nonce_is_invalid(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_login, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_login, 'captcha');
        $_POST['woocommerce-login-nonce'] = '';
        $_SERVER['REQUEST_URI'] = '/my-account/';

        $this->seedPostedWidget('wordpress:login');

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw-error', $result->get_error_code());
    }

    public function test_wordpress_login_does_not_skip_verification_on_woo_nonce_when_woocommerce_is_inactive(): void
    {
        $GLOBALS['asfw_active_plugins'] = array('html-forms/html-forms.php');
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_login, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_login, '');
        $_POST['woocommerce-login-nonce'] = 'nonce';

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw-error', $result->get_error_code());
    }

    public function test_woocommerce_lost_password_rejects_missing_widget_when_wordpress_reset_is_the_only_enabled_protection(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_reset_password, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_reset_password, '');
        $_POST['woocommerce-lost-password-nonce'] = 'nonce';
        $_SERVER['REQUEST_URI'] = '/my-account/lost-password/';

        $errors = apply_filters('lostpassword_post', new WP_Error());

        $this->assertInstanceOf(WP_Error::class, $errors);
        $this->assertSame('asfw_error_message', $errors->get_error_code());
    }

    public function test_woocommerce_lost_password_uses_woocommerce_context_when_enabled(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_reset_password, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_reset_password, 'captcha');
        $_POST['woocommerce-lost-password-nonce'] = 'nonce';
        $_SERVER['REQUEST_URI'] = '/my-account/lost-password/';

        $this->seedPostedWidget('woocommerce:reset-password');

        $errors = apply_filters('lostpassword_post', new WP_Error());

        $this->assertSame('', $errors->get_error_code());
    }

    public function test_woocommerce_lost_password_nonce_does_not_bypass_verification_when_nonce_is_invalid(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_reset_password, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_reset_password, 'captcha');
        $_POST['woocommerce-lost-password-nonce'] = '';
        $_SERVER['REQUEST_URI'] = '/my-account/lost-password/';

        $this->seedPostedWidget('wordpress:reset-password');

        $errors = apply_filters('lostpassword_post', new WP_Error());

        $this->assertInstanceOf(WP_Error::class, $errors);
        $this->assertSame('asfw_error_message', $errors->get_error_code());
    }

    public function test_woocommerce_lost_password_form_renders_guard_for_wordpress_fallback_context(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_reset_password, '');
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'selected');
        update_option('asfw_feature_submit_delay_contexts', array('wordpress:reset-password'));

        ob_start();
        do_action('woocommerce_lostpassword_form');
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('asfw_submit_delay_token', $html);
    }

    public function test_woocommerce_lost_password_guard_detects_plain_permalink_endpoint_query(): void
    {
        update_option('woocommerce_myaccount_page_id', 123);
        update_option('woocommerce_myaccount_lost_password_endpoint', 'lost-password');
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:reset-password'));
        unset($_POST['woocommerce-lost-password-nonce']);
        $_SERVER['REQUEST_URI'] = '/?page_id=123&lost-password=1';

        $errors = apply_filters('lostpassword_post', new WP_Error());

        $this->assertInstanceOf(WP_Error::class, $errors);
        $this->assertSame('asfw_error_message', $errors->get_error_code());
    }

    public function test_woocommerce_lost_password_guard_blocks_when_guard_enabled_and_woo_nonce_is_missing(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('woocommerce:reset-password'));
        $_SERVER['REQUEST_URI'] = '/my-account/lost-password/';
        unset($_POST['woocommerce-lost-password-nonce']);

        $errors = apply_filters('lostpassword_post', new WP_Error());

        $this->assertInstanceOf(WP_Error::class, $errors);
        $this->assertSame('asfw_error_message', $errors->get_error_code());
    }

    public function test_wordpress_lost_password_does_not_skip_verification_on_woo_nonce_when_woocommerce_is_inactive(): void
    {
        $GLOBALS['asfw_active_plugins'] = array('html-forms/html-forms.php');
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_reset_password, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_reset_password, '');
        $_POST['woocommerce-lost-password-nonce'] = 'nonce';

        $errors = apply_filters('lostpassword_post', new WP_Error());

        $this->assertInstanceOf(WP_Error::class, $errors);
        $this->assertSame('asfw_error_message', $errors->get_error_code());
    }

    public function test_wordpress_login_guard_is_not_bypassed_by_woo_nonce_outside_woo_route(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:login'));
        $_POST['woocommerce-login-nonce'] = 'nonce';
        $_SERVER['REQUEST_URI'] = '/wp-login.php';

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw-error', $result->get_error_code());
    }

    public function test_wordpress_reset_guard_is_not_bypassed_by_woo_nonce_outside_woo_route(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:reset-password'));
        $_POST['woocommerce-lost-password-nonce'] = 'nonce';
        $_SERVER['REQUEST_URI'] = '/wp-login.php?action=lostpassword';

        $errors = apply_filters('lostpassword_post', new WP_Error());

        $this->assertInstanceOf(WP_Error::class, $errors);
        $this->assertSame('asfw_error_message', $errors->get_error_code());
    }

    public function test_wordpress_login_guard_is_not_bypassed_by_woo_nonce_in_query_string(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:login'));
        $_POST['woocommerce-login-nonce'] = 'nonce';
        $_SERVER['REQUEST_URI'] = '/wp-login.php?redirect_to=%2Fmy-account%2F';

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw-error', $result->get_error_code());
    }

    public function test_wordpress_reset_guard_is_not_bypassed_by_woo_nonce_in_query_string(): void
    {
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:reset-password'));
        $_POST['woocommerce-lost-password-nonce'] = 'nonce';
        $_SERVER['REQUEST_URI'] = '/wp-login.php?action=lostpassword&redirect_to=%2Fmy-account%2F';

        $errors = apply_filters('lostpassword_post', new WP_Error());

        $this->assertInstanceOf(WP_Error::class, $errors);
        $this->assertSame('asfw_error_message', $errors->get_error_code());
    }

    public function test_woocommerce_lost_password_form_renders_context_guards(): void
    {
        update_option('asfw_feature_submit_delay_enabled', 1);
        update_option('asfw_feature_submit_delay_mode', 'block');
        update_option('asfw_feature_submit_delay_scope_mode', 'selected');
        update_option('asfw_feature_submit_delay_contexts', array('woocommerce:reset-password'));

        ob_start();
        do_action('woocommerce_lostpassword_form');
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('asfw_submit_delay_token', $html);
    }
}
