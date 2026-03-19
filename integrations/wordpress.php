<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action(
    'register_form',
    function () {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_wordpress_register();
        if (!empty($mode)) {
            asfw_render_wordpress_widget($mode, 'asfw_register');
        }
    },
    10,
    0
);

add_action(
    'register_post',
    function ($user_login, $user_email, $errors) {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_wordpress_register();
        if (!empty($mode)) {
            $payload = isset($_POST['asfw_register']) ? trim(sanitize_text_field($_POST['asfw_register'])) : '';
            if ($plugin->verify($payload) === false) {
                return $errors->add(
                    'asfw_error_message',
                    '<strong>' . esc_html__('Error', 'anti-spam-for-wordpress') . '</strong> : ' . esc_html__('Could not verify you are not a robot.', 'anti-spam-for-wordpress')
                );
            }
        }

        return $errors;
    },
    10,
    3
);

add_action(
    'login_form',
    function () {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_wordpress_login();
        if (!empty($mode)) {
            asfw_render_wordpress_widget($mode);
        }
    },
    10,
    0
);

add_filter(
    'authenticate',
    function ($user, $username, $password) {
        if ($user instanceof WP_Error) {
            return $user;
        }
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            return $user;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return $user;
        }
        if (asfw_plugin_active('woocommerce') && isset($_POST['woocommerce-login-nonce'])) {
            return $user;
        }

        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_wordpress_login();
        if (!empty($mode)) {
            $payload = isset($_POST['asfw']) ? trim(sanitize_text_field($_POST['asfw'])) : '';
            if ($plugin->verify($payload) === false) {
                return new WP_Error(
                    'asfw-error',
                    '<strong>' . esc_html__('Error', 'anti-spam-for-wordpress') . '</strong> : ' . esc_html__('Could not verify you are not a robot.', 'anti-spam-for-wordpress')
                );
            }
        }

        return $user;
    },
    20,
    3
);

add_action(
    'lostpassword_form',
    function () {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_wordpress_reset_password();
        if (!empty($mode)) {
            asfw_render_wordpress_widget($mode);
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
        if (asfw_plugin_active('woocommerce') && isset($_POST['woocommerce-lost-password-nonce'])) {
            return $errors;
        }

        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_wordpress_reset_password();
        if (!empty($mode)) {
            $payload = isset($_POST['asfw']) ? trim(sanitize_text_field($_POST['asfw'])) : '';
            if ($plugin->verify($payload) === false) {
                $errors->add(
                    'asfw_error_message',
                    '<strong>' . esc_html__('Error', 'anti-spam-for-wordpress') . '</strong> : ' . esc_html__('Could not verify you are not a robot.', 'anti-spam-for-wordpress')
                );
            }
        }

        return $errors;
    },
    10,
    1
);

add_action(
    'comment_form_after_fields',
    function () {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_wordpress_comments();
        if (!empty($mode)) {
            asfw_render_wordpress_widget($mode);
        }
    },
    10,
    0
);

add_action(
    'comment_form_logged_in_after',
    function () {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_wordpress_comments();
        if (!empty($mode)) {
            asfw_render_wordpress_widget($mode);
        }
    },
    10,
    0
);

add_filter(
    'preprocess_comment',
    function ($comment) {
        if ($comment['comment_type'] !== '' && $comment['comment_type'] !== 'comment') {
            return $comment;
        }
        if (is_user_logged_in() && current_user_can('manage_options')) {
            return $comment;
        }

        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = (asfw_plugin_active('wpdiscuz') && $plugin->get_integration_wpdiscuz()) || $plugin->get_integration_wordpress_comments();
        if (!empty($mode)) {
            $payload = isset($_POST['asfw']) ? trim(sanitize_text_field($_POST['asfw'])) : '';
            if ($plugin->verify($payload) === false) {
                wp_die('<strong>' . esc_html__('Error', 'anti-spam-for-wordpress') . '</strong> : ' . esc_html__('Could not verify you are not a robot.', 'anti-spam-for-wordpress'));
            }
        }

        return $comment;
    },
    10,
    1
);

function asfw_render_wordpress_widget($mode, $name = null)
{
    $plugin = AntiSpamForWordPressPlugin::$instance;
    echo wp_kses($plugin->render_widget($mode, true, null, $name), AntiSpamForWordPressPlugin::$html_allowed_tags);
}
