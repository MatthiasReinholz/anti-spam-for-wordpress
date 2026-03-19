<?php

if (!defined('ABSPATH')) {
    exit;
}

if (asfw_plugin_active('html-forms')) {
    add_filter('hf_form_html', 'do_shortcode');

    add_filter(
        'hf_form_html',
        function ($html) {
            $plugin = AntiSpamForWordPressPlugin::$instance;
            $mode = $plugin->get_integration_html_forms();
            if ($mode === 'captcha') {
                return str_replace('</form>', wp_kses($plugin->render_widget($mode), AntiSpamForWordPressPlugin::$html_allowed_tags) . '</form>', $html);
            }

            return $html;
        }
    );

    add_filter(
        'hf_validate_form',
        function ($error_code, $form, $data) {
            $plugin = AntiSpamForWordPressPlugin::$instance;
            $mode = $plugin->get_integration_html_forms();
            if (!empty($mode)) {
                if ($mode === 'shortcode' && strpos($form, '<altcha-widget ') === false) {
                    return $error_code;
                }
                if ($mode === 'captcha' || $mode === 'shortcode') {
                    $payload = isset($_POST['asfw']) ? trim(sanitize_text_field($_POST['asfw'])) : '';
                    if ($plugin->verify($payload) === false) {
                        return 'asfw_invalid';
                    }
                }
            }

            return $error_code;
        },
        10,
        3
    );

    add_filter(
        'hf_form_message_asfw_invalid',
        function ($message) {
            return __('Could not verify you are not a robot.', 'anti-spam-for-wordpress');
        }
    );
}
