<?php

if (!defined('ABSPATH')) {
    exit;
}

if (asfw_plugin_active('elementor')) {
    function asfw_register_form_field($form_fields_registrar)
    {
        require_once __DIR__ . '/elementor/field.php';

        $form_fields_registrar->register(new \Elementor_Form_AntiSpamWidget_Field());
    }

    $plugin = AntiSpamForWordPressPlugin::$instance;
    $mode = $plugin->get_integration_elementor();
    if ($mode === 'captcha') {
        add_action('elementor_pro/forms/fields/register', 'asfw_register_form_field');
    }
}
