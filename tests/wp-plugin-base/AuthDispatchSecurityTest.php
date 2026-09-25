<?php
declare(strict_types=1);

final class AuthDispatchSecurityTest extends AsfwPluginTestCase
{
    private array $previousServer;
    private $previousPage;

    protected function setUp(): void
    {
        $this->previousServer = $_SERVER;
        $this->previousPage = $GLOBALS['pagenow'] ?? null;
        parent::setUp();
        unset($GLOBALS['pagenow'], $_SERVER['SCRIPT_NAME'], $_SERVER['SCRIPT_FILENAME']);
        update_option('woocommerce_myaccount_page_id', '123');
        update_option('asfw_feature_math_challenge_enabled', 1);
        update_option('asfw_feature_math_challenge_mode', 'block');
        update_option('asfw_feature_math_challenge_scope_mode', 'selected');
        update_option('asfw_feature_math_challenge_contexts', array('wordpress:login', 'wordpress:reset-password'));
        foreach (array('wordpress_login', 'woocommerce_login', 'wordpress_reset_password', 'woocommerce_reset_password') as $integration) {
            $option = 'option_integration_' . $integration;
            update_option(AntiSpamForWordPressPlugin::${$option}, 'captcha');
        }
        $GLOBALS['asfw_test_nonce_actions'] = array('login-nonce' => 'woocommerce-login', 'reset-nonce' => 'lost_password');
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->previousServer;
        if (null === $this->previousPage) {
            unset($GLOBALS['pagenow']);
        } else {
            $GLOBALS['pagenow'] = $this->previousPage;
        }
        unset($GLOBALS['asfw_test_nonce_actions']);
        parent::tearDown();
    }

    public static function nativeRoutes(): array
    {
        return array(
            'spoofed lost-password query' => array('/wp-login.php?lost-password=1', 'pagenow', 'wp-login.php'),
            'spoofed account page query' => array('/site/wp-login.php?page_id=123', 'SCRIPT_NAME', '/site/wp-login.php'),
            'rewritten login URL' => array('/my-account/', 'SCRIPT_FILENAME', '/var/www/wordpress/wp-login.php'),
        );
    }

    /** @dataProvider nativeRoutes */
    public function test_native_login_cannot_select_woo_policy_with_a_valid_guest_nonce(string $uri, string $source, string $entrypoint): void
    {
        $this->setNativeEntrypoint($uri, $source, $entrypoint);
        $_POST['woocommerce-login-nonce'] = 'login-nonce';
        $this->seedPostedWidget('woocommerce:login');

        $this->assertFalse(asfw_is_woocommerce_account_request());
        $result = apply_filters('authenticate', null, 'demo', 'secret');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asfw-error', $result->get_error_code());
    }

    /** @dataProvider nativeRoutes */
    public function test_native_reset_cannot_select_woo_policy_with_a_valid_guest_nonce(string $uri, string $source, string $entrypoint): void
    {
        $this->setNativeEntrypoint($uri, $source, $entrypoint);
        $_POST['woocommerce-lost-password-nonce'] = 'reset-nonce';
        $this->seedPostedWidget('woocommerce:reset-password');

        $this->assertFalse(asfw_is_woocommerce_account_request());
        $errors = apply_filters('lostpassword_post', new WP_Error());
        $this->assertSame('asfw_error_message', $errors->get_error_code());
    }

    public function test_native_login_consumes_its_own_proof_only_once_despite_woo_fields(): void
    {
        $this->setNativeEntrypoint('/my-account/', 'pagenow', 'wp-login.php');
        update_option('asfw_feature_math_challenge_enabled', 0);
        $_POST['woocommerce-login-nonce'] = 'login-nonce';
        $this->seedPostedWidget('wordpress:login');

        $this->assertNotInstanceOf(WP_Error::class, apply_filters('authenticate', null, 'demo', 'secret'));
    }

    public function test_native_reset_consumes_its_own_proof_only_once_despite_woo_fields(): void
    {
        $this->setNativeEntrypoint('/my-account/lost-password/', 'pagenow', 'wp-login.php');
        update_option('asfw_feature_math_challenge_enabled', 0);
        $_POST['woocommerce-lost-password-nonce'] = 'reset-nonce';
        $this->seedPostedWidget('wordpress:reset-password');

        $this->assertSame('', apply_filters('lostpassword_post', new WP_Error())->get_error_code());
    }

    public function test_genuine_woo_login_keeps_its_own_policy(): void
    {
        $this->setNativeEntrypoint('/my-account/', 'SCRIPT_NAME', '/index.php');
        $_POST['woocommerce-login-nonce'] = 'login-nonce';
        $this->seedPostedWidget('woocommerce:login');

        $this->assertTrue(asfw_is_woocommerce_account_request());
        $this->assertNotInstanceOf(WP_Error::class, apply_filters('authenticate', null, 'demo', 'secret'));
    }

    public function test_genuine_woo_reset_uses_the_provider_nonce_action(): void
    {
        $this->setNativeEntrypoint('/?page_id=123&lost-password=1', 'SCRIPT_NAME', '/index.php');
        $_POST['woocommerce-lost-password-nonce'] = 'reset-nonce';
        $this->seedPostedWidget('woocommerce:reset-password');

        $this->assertTrue(asfw_is_woocommerce_account_request());
        $this->assertSame('', apply_filters('lostpassword_post', new WP_Error())->get_error_code());
    }

    private function setNativeEntrypoint(string $uri, string $source, string $entrypoint): void
    {
        $_SERVER['REQUEST_URI'] = $uri;
        if ('pagenow' === $source) {
            $GLOBALS['pagenow'] = $entrypoint;
        } else {
            $_SERVER[$source] = $entrypoint;
        }
    }
}
