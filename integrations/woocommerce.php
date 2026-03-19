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
            asfw_render_woocommerce_widget($mode, 'asfw_register');
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
            $payload = isset($_POST['asfw_register']) ? trim(sanitize_text_field($_POST['asfw_register'])) : '';
            if ($plugin->verify($payload) === false) {
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
            asfw_render_woocommerce_widget($mode);
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
            $payload = isset($_POST['asfw']) ? trim(sanitize_text_field($_POST['asfw'])) : '';
            if ($plugin->verify($payload) === false) {
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
            asfw_render_woocommerce_widget($mode);
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
            $payload = isset($_POST['asfw']) ? trim(sanitize_text_field($_POST['asfw'])) : '';
            if ($plugin->verify($payload) === false) {
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

function asfw_render_woocommerce_widget($mode, $name = null)
{
    $plugin = AntiSpamForWordPressPlugin::$instance;
    echo wp_kses($plugin->render_widget($mode, true, null, $name), AntiSpamForWordPressPlugin::$html_allowed_tags);
}
