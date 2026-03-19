<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action(
    'wp_enqueue_scripts',
    function () {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $mode = $plugin->get_integration_custom();
        if ($mode === 'captcha') {
            wp_enqueue_script(
                'asfw-widget-custom',
                AntiSpamForWordPressPlugin::$custom_script_src,
                array('asfw-widget'),
                ASFW_VERSION,
                true
            );
            $attrs = wp_json_encode($plugin->get_widget_attrs($mode));
            wp_register_script(
                'asfw-widget-custom-options',
                '',
                array(),
                ASFW_VERSION,
                false
            );
            wp_enqueue_script('asfw-widget-custom-options');
            wp_add_inline_script(
                'asfw-widget-custom-options',
                "(() => { window.ASFW_WIDGET_ATTRS = $attrs; })();"
            );
        }
    },
    10,
    0
);
