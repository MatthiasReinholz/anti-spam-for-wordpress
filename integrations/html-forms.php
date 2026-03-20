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
                return str_replace('</form>', asfw_render_widget_markup($mode, 'html-forms', 'asfw', false) . '</form>', $html);
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
                $widget_tag = '<' . $plugin->get_widget_tag_name() . ' ';
                if ($mode === 'shortcode' && strpos($form, $widget_tag) === false) {
                    return $error_code;
                }
                if ($mode === 'captcha' || $mode === 'shortcode') {
                    if (asfw_verify_posted_widget($mode === 'captcha' ? 'html-forms' : null) === false) {
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
