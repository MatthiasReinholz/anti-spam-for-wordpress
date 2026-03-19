<?php

if (!defined('ABSPATH')) {
    exit;
}

if (asfw_plugin_active('forminator')) {
    add_action(
        'forminator_render_button_markup',
        function ($html) {
            return asfw_forminator_render_widget($html);
        },
        10,
        2
    );

    add_action(
        'forminator_render_fields_markup',
        function ($html) {
            return asfw_forminator_render_widget($html);
        },
        10,
        2
    );

    add_filter(
        'forminator_cform_form_is_submittable',
        function ($can_show, $id, $form_settings) {
            $plugin = AntiSpamForWordPressPlugin::$instance;
            $mode = $plugin->get_integration_forminator();
            if (!empty($mode) && $mode === 'captcha') {
                $payload = isset($_POST['asfw']) ? trim(sanitize_text_field($_POST['asfw'])) : '';
                if ($plugin->verify($payload) === false) {
                    return array(
                        'can_submit' => false,
                        'error' => __('Could not verify you are not a robot.', 'anti-spam-for-wordpress'),
                    );
                }
            }

            return $can_show;
        },
        10,
        3
    );
}

function asfw_forminator_render_widget($html)
{
    $plugin = AntiSpamForWordPressPlugin::$instance;
    $mode = $plugin->get_integration_forminator();
    if ($mode === 'captcha') {
        $elements = wp_kses($plugin->render_widget($mode, true), AntiSpamForWordPressPlugin::$html_allowed_tags);
        $target = '<div class="forminator-row forminator-row-last"';
        $pos = strpos($html, $target);

        if ($pos !== false) {
            $html = substr_replace($html, $elements, $pos, 0);
        } else {
            $target = '<button class="forminator-button ';
            $pos = strpos($html, $target);
            if ($pos !== false) {
                $html = substr_replace($html, $elements, $pos, 0);
            }
        }
    }

    return $html;
}
