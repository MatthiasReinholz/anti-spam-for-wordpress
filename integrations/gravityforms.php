<?php

if (!defined('ABSPATH')) {
    exit;
}

if (asfw_plugin_active('gravityforms')) {
    add_action(
        'gform_loaded',
        function () {
            $plugin = AntiSpamForWordPressPlugin::$instance;
            $mode = $plugin->get_integration_gravityforms();
            if ($mode === 'captcha') {
                require_once 'gravityforms/addon.php';
                GFAddOn::register('ASFW_GFFormsAddOn');
            }
        },
        5
    );
}
