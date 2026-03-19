<?php

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Plugin Name: Anti Spam for WordPress
 * Description: Self-hosted spam protection for WordPress forms using a proof-of-work widget. This plugin is a fork of the ALTCHA WordPress plugin v1.
 * Author: Matthias Reinholz
 * Author URI: https://matthiasreinholz.com
 * Version: 0.0.1
 * Stable tag: 0.0.1
 * Requires at least: 5.0
 * Requires PHP: 7.3
 * Tested up to: 6.8
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

define('ASFW_FILE', __FILE__);
define('ASFW_VERSION', '0.0.1');
define('ASFW_WEBSITE', 'https://matthiasreinholz.com');
define('ASFW_WIDGET_VERSION', '2.2.2');

// Required for is_plugin_active.
require_once ABSPATH . 'wp-admin/includes/plugin.php';

require plugin_dir_path(__FILE__) . 'includes/helpers.php';
require plugin_dir_path(__FILE__) . 'includes/core.php';
require plugin_dir_path(__FILE__) . 'public/widget.php';

require plugin_dir_path(__FILE__) . 'integrations/coblocks.php';
require plugin_dir_path(__FILE__) . 'integrations/contact-form-7.php';
require plugin_dir_path(__FILE__) . 'integrations/custom.php';
require plugin_dir_path(__FILE__) . 'integrations/elementor.php';
require plugin_dir_path(__FILE__) . 'integrations/enfold-theme.php';
require plugin_dir_path(__FILE__) . 'integrations/formidable.php';
require plugin_dir_path(__FILE__) . 'integrations/forminator.php';
require plugin_dir_path(__FILE__) . 'integrations/html-forms.php';
require plugin_dir_path(__FILE__) . 'integrations/gravityforms.php';
require plugin_dir_path(__FILE__) . 'integrations/wpdiscuz.php';
require plugin_dir_path(__FILE__) . 'integrations/wpforms.php';
require plugin_dir_path(__FILE__) . 'integrations/wpmembers.php';
require plugin_dir_path(__FILE__) . 'integrations/woocommerce.php';
require plugin_dir_path(__FILE__) . 'integrations/wordpress.php';

AntiSpamForWordPressPlugin::$widget_script_src = plugin_dir_url(__FILE__) . 'public/altcha.min.js';
AntiSpamForWordPressPlugin::$widget_style_src = plugin_dir_url(__FILE__) . 'public/altcha.css';
AntiSpamForWordPressPlugin::$wp_script_src = plugin_dir_url(__FILE__) . 'public/script.js';
AntiSpamForWordPressPlugin::$admin_script_src = plugin_dir_url(__FILE__) . 'public/admin.js';
AntiSpamForWordPressPlugin::$admin_css_src = plugin_dir_url(__FILE__) . 'public/admin.css';
AntiSpamForWordPressPlugin::$custom_script_src = plugin_dir_url(__FILE__) . 'public/custom.js';

register_activation_hook(__FILE__, 'asfw_activate');
register_deactivation_hook(__FILE__, 'asfw_deactivate');

add_action('init', 'asfw_init');
add_action('admin_init', 'asfw_maybe_migrate_legacy_settings');

add_shortcode(
    'anti_spam_widget',
    function ($attrs) {
        $plugin = AntiSpamForWordPressPlugin::$instance;
        $defaults = array(
            'language' => null,
            'mode' => $plugin->get_integration_custom(),
        );
        $attributes = shortcode_atts($defaults, $attrs);

        return wp_kses(
            $plugin->render_widget($attributes['mode'], true, $attributes['language']),
            AntiSpamForWordPressPlugin::$html_allowed_tags
        );
    }
);

function asfw_init()
{
    load_plugin_textdomain(
        'anti-spam-for-wordpress',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}

function asfw_activate()
{
    asfw_maybe_migrate_legacy_settings(true);

    if (get_option(AntiSpamForWordPressPlugin::$option_secret, '') === '') {
        update_option(AntiSpamForWordPressPlugin::$option_secret, AntiSpamForWordPressPlugin::$instance->random_secret());
    }

    if (get_option(AntiSpamForWordPressPlugin::$option_complexity, '') === '') {
        update_option(AntiSpamForWordPressPlugin::$option_complexity, 'medium');
    }

    if (get_option(AntiSpamForWordPressPlugin::$option_expires, '') === '') {
        update_option(AntiSpamForWordPressPlugin::$option_expires, '3600');
    }

    if (get_option(AntiSpamForWordPressPlugin::$option_hidefooter, null) === null) {
        update_option(AntiSpamForWordPressPlugin::$option_hidefooter, true);
    }

    if (get_option(AntiSpamForWordPressPlugin::$option_hidelogo, null) === null) {
        update_option(AntiSpamForWordPressPlugin::$option_hidelogo, false);
    }

    if (get_option(AntiSpamForWordPressPlugin::$option_integration_custom, '') === '') {
        update_option(AntiSpamForWordPressPlugin::$option_integration_custom, 'captcha');
    }
}

function asfw_deactivate()
{
}

function asfw_normalize_migrated_mode($value)
{
    if ($value === 'captcha_spamfilter' || $value === 'spamfilter') {
        return 'captcha';
    }

    return $value;
}

function asfw_maybe_migrate_legacy_settings($force = false)
{
    $migration_option = 'asfw_migration_completed';
    if (!$force && get_option($migration_option)) {
        return;
    }

    $legacy_secret = get_option('altcha_secret', null);
    if ($legacy_secret === null) {
        if ($force) {
            update_option($migration_option, ASFW_VERSION);
        }

        return;
    }

    $option_map = array(
        'altcha_secret' => AntiSpamForWordPressPlugin::$option_secret,
        'altcha_complexity' => AntiSpamForWordPressPlugin::$option_complexity,
        'altcha_expires' => AntiSpamForWordPressPlugin::$option_expires,
        'altcha_auto' => AntiSpamForWordPressPlugin::$option_auto,
        'altcha_floating' => AntiSpamForWordPressPlugin::$option_floating,
        'altcha_delay' => AntiSpamForWordPressPlugin::$option_delay,
        'altcha_hidefooter' => AntiSpamForWordPressPlugin::$option_hidefooter,
        'altcha_hidelogo' => AntiSpamForWordPressPlugin::$option_hidelogo,
        'altcha_integration_coblocks' => AntiSpamForWordPressPlugin::$option_integration_coblocks,
        'altcha_integration_contact_form_7' => AntiSpamForWordPressPlugin::$option_integration_contact_form_7,
        'altcha_integration_custom' => AntiSpamForWordPressPlugin::$option_integration_custom,
        'altcha_integration_elementor' => AntiSpamForWordPressPlugin::$option_integration_elementor,
        'altcha_integration_enfold_theme' => AntiSpamForWordPressPlugin::$option_integration_enfold_theme,
        'altcha_integration_formidable' => AntiSpamForWordPressPlugin::$option_integration_formidable,
        'altcha_integration_forminator' => AntiSpamForWordPressPlugin::$option_integration_forminator,
        'altcha_integration_gravityforms' => AntiSpamForWordPressPlugin::$option_integration_gravityforms,
        'altcha_integration_html_forms' => AntiSpamForWordPressPlugin::$option_integration_html_forms,
        'altcha_integration_woocommerce_login' => AntiSpamForWordPressPlugin::$option_integration_woocommerce_login,
        'altcha_integration_woocommerce_register' => AntiSpamForWordPressPlugin::$option_integration_woocommerce_register,
        'altcha_integration_woocommerce_reset_password' => AntiSpamForWordPressPlugin::$option_integration_woocommerce_reset_password,
        'altcha_integration_wordpress_comments' => AntiSpamForWordPressPlugin::$option_integration_wordpress_comments,
        'altcha_integration_wordpress_login' => AntiSpamForWordPressPlugin::$option_integration_wordpress_login,
        'altcha_integration_wordpress_register' => AntiSpamForWordPressPlugin::$option_integration_wordpress_register,
        'altcha_integration_wordpress_reset_password' => AntiSpamForWordPressPlugin::$option_integration_wordpress_reset_password,
        'altcha_integration_wpdiscuz' => AntiSpamForWordPressPlugin::$option_integration_wpdiscuz,
        'altcha_integration_wpforms' => AntiSpamForWordPressPlugin::$option_integration_wpforms,
    );

    $integration_targets = array_flip(array(
        AntiSpamForWordPressPlugin::$option_integration_coblocks,
        AntiSpamForWordPressPlugin::$option_integration_contact_form_7,
        AntiSpamForWordPressPlugin::$option_integration_custom,
        AntiSpamForWordPressPlugin::$option_integration_elementor,
        AntiSpamForWordPressPlugin::$option_integration_enfold_theme,
        AntiSpamForWordPressPlugin::$option_integration_formidable,
        AntiSpamForWordPressPlugin::$option_integration_forminator,
        AntiSpamForWordPressPlugin::$option_integration_gravityforms,
        AntiSpamForWordPressPlugin::$option_integration_html_forms,
        AntiSpamForWordPressPlugin::$option_integration_woocommerce_login,
        AntiSpamForWordPressPlugin::$option_integration_woocommerce_register,
        AntiSpamForWordPressPlugin::$option_integration_woocommerce_reset_password,
        AntiSpamForWordPressPlugin::$option_integration_wordpress_comments,
        AntiSpamForWordPressPlugin::$option_integration_wordpress_login,
        AntiSpamForWordPressPlugin::$option_integration_wordpress_register,
        AntiSpamForWordPressPlugin::$option_integration_wordpress_reset_password,
        AntiSpamForWordPressPlugin::$option_integration_wpdiscuz,
        AntiSpamForWordPressPlugin::$option_integration_wpforms,
    ));

    foreach ($option_map as $legacy_option => $new_option) {
        $legacy_value = get_option($legacy_option, null);
        if ($legacy_value === null) {
            continue;
        }

        if (isset($integration_targets[$new_option])) {
            $legacy_value = asfw_normalize_migrated_mode($legacy_value);
        }

        update_option($new_option, $legacy_value);
    }

    update_option($migration_option, ASFW_VERSION);
}
