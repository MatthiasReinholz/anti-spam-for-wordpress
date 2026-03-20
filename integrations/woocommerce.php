<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action(
    'woocommerce_register_form',
    function () {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_woocommerce_register();
        if (!empty($mode)) {
            asfw_render_woocommerce_widget($mode, 'woocommerce:register', 'asfw_register');
        }
    },
    10,
    0
);

add_action(
    'woocommerce_register_post',
    function ($user_login, $user_email, $errors) {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_woocommerce_register();
        if (!empty($mode)) {
            if (asfw_verify_posted_widget('woocommerce:register', 'asfw_register') === false) {
                return $errors->add(
                    'asfw_error_message',
                    esc_html__('Could not verify you are not a robot.', 'anti-spam-for-wordpress')
                );
            }
        }

        return $errors;
    },
    10,
    3
);

add_action(
    'woocommerce_login_form',
    function () {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_woocommerce_login();
        if (!empty($mode)) {
            asfw_render_woocommerce_widget($mode, 'woocommerce:login');
        }
    },
    10,
    0
);

add_filter(
    'authenticate',
    function ($user) {
        if ($user instanceof WP_Error) {
            return $user;
        }
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            return $user;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return $user;
        }
        if (!isset($_POST['woocommerce-login-nonce'])) {
            return $user;
        }

        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_woocommerce_login();
        if (!empty($mode)) {
            if (asfw_verify_posted_widget('woocommerce:login') === false) {
                return new WP_Error(
                    'asfw-error',
                    esc_html__('Could not verify you are not a robot.', 'anti-spam-for-wordpress')
                );
            }
        }

        return $user;
    },
    20,
    1
);

add_action(
    'woocommerce_lostpassword_form',
    function () {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_woocommerce_reset_password();
        if (!empty($mode)) {
            asfw_render_woocommerce_widget($mode, 'woocommerce:reset-password');
        }
    },
    10,
    0
);

add_filter(
    'lostpassword_post',
    function ($errors) {
        if (is_user_logged_in()) {
            return $errors;
        }
        if (!isset($_POST['woocommerce-lost-password-nonce'])) {
            return $errors;
        }

        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_woocommerce_reset_password();
        if (!empty($mode)) {
            if (asfw_verify_posted_widget('woocommerce:reset-password') === false) {
                $errors->add(
                    'asfw_error_message',
                    esc_html__('Could not verify you are not a robot.', 'anti-spam-for-wordpress')
                );
            }
        }

        return $errors;
    },
    10,
    1
);

function asfw_render_woocommerce_widget($mode, $context, $name = null)
{
    echo asfw_render_widget_markup($mode, $context, $name);
}
