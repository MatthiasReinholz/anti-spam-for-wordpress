<?php
declare(strict_types=1);

final class AuthIntegrationTest extends AsfwPluginTestCase
{
    public function test_woocommerce_login_uses_wordpress_setting_when_woocommerce_setting_is_disabled(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_login, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_login, '');
        $_POST['woocommerce-login-nonce'] = 'nonce';

        $this->seedPostedWidget('wordpress:login');

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertNotInstanceOf(WP_Error::class, $result);
    }

    public function test_woocommerce_login_rejects_missing_widget_when_wordpress_login_is_the_only_enabled_protection(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_login, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_login, '');
        $_POST['woocommerce-login-nonce'] = 'nonce';

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw-error', $result->get_error_code());
    }

    public function test_woocommerce_login_uses_woocommerce_context_when_enabled(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_login, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_login, 'captcha');
        $_POST['woocommerce-login-nonce'] = 'nonce';

        $this->seedPostedWidget('woocommerce:login');

        $result = apply_filters('authenticate', null, 'demo', 'secret');

        $this->assertNotInstanceOf(WP_Error::class, $result);
    }

    public function test_woocommerce_lost_password_rejects_missing_widget_when_wordpress_reset_is_the_only_enabled_protection(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_reset_password, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_reset_password, '');
        $_POST['woocommerce-lost-password-nonce'] = 'nonce';

        $errors = apply_filters('lostpassword_post', new WP_Error());

        $this->assertInstanceOf(WP_Error::class, $errors);
        $this->assertSame('asfw_error_message', $errors->get_error_code());
    }

    public function test_woocommerce_lost_password_uses_woocommerce_context_when_enabled(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_wordpress_reset_password, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_integration_woocommerce_reset_password, 'captcha');
        $_POST['woocommerce-lost-password-nonce'] = 'nonce';

        $this->seedPostedWidget('woocommerce:reset-password');

        $errors = apply_filters('lostpassword_post', new WP_Error());

        $this->assertSame('', $errors->get_error_code());
    }
}
