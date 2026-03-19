<?php

if (!defined('ABSPATH')) {
    exit;
}

function asfw_plugin_active($name)
{
    switch ($name) {
        case 'coblocks':
            return is_plugin_active('coblocks/class-coblocks.php');
        case 'elementor':
            return is_plugin_active('elementor/elementor.php');
        case 'formidable':
            return is_plugin_active('formidable/formidable.php');
        case 'forminator':
            return is_plugin_active('forminator/forminator.php');
        case 'gravityforms':
            return is_plugin_active('gravityforms/gravityforms.php');
        case 'html-forms':
            return is_plugin_active('html-forms/html-forms.php');
        case 'contact-form-7':
            return is_plugin_active('contact-form-7/wp-contact-form-7.php');
        case 'woocommerce':
            return is_plugin_active('woocommerce/woocommerce.php');
        case 'wpdiscuz':
            return is_plugin_active('wpdiscuz/class.WpdiscuzCore.php');
        case 'wpmembers':
            return is_plugin_active('wp-members/wp-members.php');
        case 'wpforms':
            return is_plugin_active('wpforms/wpforms.php') || is_plugin_active('wpforms-lite/wpforms.php');
        default:
            return apply_filters('asfw_plugin_active', false, $name);
    }
}

function asfw_enqueue_styles()
{
    wp_enqueue_style(
        'asfw-widget-styles',
        AntiSpamForWordPressPlugin::$widget_style_src,
        array(),
        ASFW_VERSION,
        'all'
    );
}

function asfw_enqueue_scripts()
{
    wp_enqueue_script(
        'asfw-widget',
        AntiSpamForWordPressPlugin::$widget_script_src,
        array(),
        ASFW_VERSION,
        true
    );
    wp_enqueue_script(
        'asfw-widget-wp',
        AntiSpamForWordPressPlugin::$wp_script_src,
        array('asfw-widget'),
        ASFW_VERSION,
        true
    );
}
