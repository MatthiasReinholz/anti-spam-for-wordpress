<?php

if (!defined('ABSPATH')) {
    exit;
}

if (asfw_plugin_active('contact-form-7')) {
    add_filter('wpcf7_form_elements', 'do_shortcode');

    add_filter(
        'wpcf7_form_elements',
        function ($elements) {
            $plugin = AntiSpamForWordPressPlugin::$instance;
            $mode = $plugin->get_integration_contact_form_7();
            if ($mode === 'captcha') {
                $input = '<input class="wpcf7-form-control wpcf7-submit ';
                $button = '<button class="wpcf7-form-control wpcf7-submit ';
                $widget = wp_kses(
                    $plugin->render_widget($mode, true, AntiSpamForWordPressPlugin::$language),
                    AntiSpamForWordPressPlugin::$html_allowed_tags
                );
                if (strpos($elements, $input) !== false) {
                    $elements = str_replace($input, $widget . $input, $elements);
                } elseif (strpos($elements, $button) !== false) {
                    $elements = str_replace($button, $widget . $button, $elements);
                } else {
                    $elements .= $widget;
                }
            }

            return $elements;
        },
        100,
        1
    );

    add_filter(
        'wpcf7_spam',
        function ($spam) {
            if ($spam) {
                return $spam;
            }

            $plugin = AntiSpamForWordPressPlugin::$instance;
            $mode = $plugin->get_integration_contact_form_7();
            if (!empty($mode) && ($mode === 'captcha' || $mode === 'shortcode')) {
                $payload = isset($_POST['asfw']) ? trim(sanitize_text_field($_POST['asfw'])) : '';

                return $plugin->verify($payload) === false;
            }

            return $spam;
        },
        9,
        1
    );
}
