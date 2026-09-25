<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Plugin Name: Anti Spam for WordPress
 * Description: Self-hosted spam protection for WordPress forms using a proof-of-work widget.
 * Author: Matthias Reinholz
 * Author URI: https://matthiasreinholz.com
 * Version: 0.10.1
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Tested up to: 7.1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: anti-spam-for-wordpress
 * Domain Path: /languages
 */

define( 'ASFW_FILE', __FILE__ );
define( 'ASFW_VERSION', '0.10.1' );
define( 'ASFW_WEBSITE', 'https://matthiasreinholz.com' );
define( 'ASFW_WIDGET_VERSION', '1.0.0' );
define( 'ASFW_DB_VERSION', 1 );

// Do not initialize against an incomplete or non-WordPress bootstrap.
if ( ! function_exists( 'add_action' ) || ! function_exists( 'register_activation_hook' ) ) {
	return;
}

// Load the plugin API when WordPress has made it available.
$asfw_plugin_api_file = ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! function_exists( 'is_plugin_active' ) && is_readable( $asfw_plugin_api_file ) ) {
	require_once $asfw_plugin_api_file;
}
unset( $asfw_plugin_api_file );

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/class-asfw-settings-schema.php';
require_once __DIR__ . '/includes/class-asfw-settings-definitions.php';
require_once __DIR__ . '/includes/class-asfw-settings-renderer.php';
require_once __DIR__ . '/includes/class-asfw-settings-registrar.php';
require_once __DIR__ . '/includes/class-antispamforwordpressplugin.php';
require_once __DIR__ . '/includes/class-asfw-privacy-policy-text.php';
require_once __DIR__ . '/includes/class-asfw-options.php';
require_once __DIR__ . '/includes/class-asfw-context-helper.php';
require_once __DIR__ . '/includes/class-asfw-client-identity.php';
require_once __DIR__ . '/includes/class-asfw-atomic-state-store.php';
require_once __DIR__ . '/includes/class-asfw-rate-limiter.php';
require_once __DIR__ . '/includes/class-asfw-challenge-manager.php';
require_once __DIR__ . '/includes/class-asfw-verifier.php';
require_once __DIR__ . '/includes/class-asfw-widget-renderer.php';
require_once __DIR__ . '/includes/interface-asfw-integration-adapter.php';
require_once __DIR__ . '/includes/class-asfw-integration-adapter-base.php';
require_once __DIR__ . '/includes/class-asfw-integration-registry.php';
require_once __DIR__ . '/includes/rest.php';
require_once __DIR__ . '/includes/control-plane.php';
require_once __DIR__ . '/includes/class-asfw-schema.php';
require_once __DIR__ . '/lib/wp-plugin-base/rest-operations/bootstrap.php';
require_once __DIR__ . '/lib/wp-plugin-base/admin-ui/bootstrap.php';
require_once __DIR__ . '/public/widget.php';
require_once __DIR__ . '/includes/lifecycle.php';

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Static property names are part of the legacy plugin API.
AntiSpamForWordPressPlugin::$widget_script_src = plugin_dir_url( __FILE__ ) . 'public/asfw-widget.js';
AntiSpamForWordPressPlugin::$widget_style_src  = plugin_dir_url( __FILE__ ) . 'public/asfw-widget.css';
AntiSpamForWordPressPlugin::$wp_script_src     = plugin_dir_url( __FILE__ ) . 'public/script.js';
AntiSpamForWordPressPlugin::$custom_script_src = plugin_dir_url( __FILE__ ) . 'public/custom.js';
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

register_activation_hook( __FILE__, 'asfw_activate' );
register_deactivation_hook( __FILE__, 'asfw_deactivate' );

add_action( 'init', 'asfw_init' );
add_action(
	'admin_init',
	static function () {
		asfw_maybe_migrate_legacy_settings();
	}
);

ASFW_Integration_Loader::bootstrap( __DIR__ );
asfw_initialize_control_plane();

add_shortcode(
	'anti_spam_widget',
	function ( $attrs ) {
		$plugin     = asfw_plugin_instance();
		$defaults   = array(
			'appearance' => null,
			'context'    => null,
			'language'   => null,
			'layout'     => null,
			'mode'       => $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_custom() : '',
			'name'       => 'asfw',
		);
		$attributes = shortcode_atts( $defaults, $attrs );
		if ( ! in_array( $attributes['mode'], array( 'captcha', 'shortcode' ), true ) ) {
			return '';
		}

		return asfw_render_widget_markup(
			$attributes['mode'],
			$attributes['context'],
			$attributes['name'],
			true,
			$attributes['language'],
			array(
				'appearance' => $attributes['appearance'],
				'layout'     => $attributes['layout'],
			)
		);
	}
);

function asfw_init() {
	load_plugin_textdomain( 'anti-spam-for-wordpress', false, dirname( plugin_basename( ASFW_FILE ) ) . '/languages' );
	asfw_maybe_initialize_site();
	asfw_initialize_control_plane();
}

function asfw_normalize_migrated_mode( $value ) {
	if ( 'captcha_spamfilter' === $value || 'spamfilter' === $value ) {
		return 'captcha';
	}

	return $value;
}

/** @return bool Whether migration is complete or no migration is needed. */
function asfw_maybe_migrate_legacy_settings( $force = false ) {
	$migration_option = 'asfw_migration_completed';
	if ( get_option( $migration_option ) ) {
		return true;
	}

	$legacy_secret = get_option( 'altcha_secret', null );
	if ( null === $legacy_secret ) {
		if ( $force ) {
			return asfw_persist_initial_option( $migration_option, ASFW_VERSION );
		}

		return true;
	}

	$option_map = array(
		'altcha_secret'                                 => AntiSpamForWordPressPlugin::$option_secret,
		'altcha_complexity'                             => AntiSpamForWordPressPlugin::$option_complexity,
		'altcha_expires'                                => AntiSpamForWordPressPlugin::$option_expires,
		'altcha_auto'                                   => AntiSpamForWordPressPlugin::$option_auto,
		'altcha_floating'                               => AntiSpamForWordPressPlugin::$option_floating,
		'altcha_delay'                                  => AntiSpamForWordPressPlugin::$option_delay,
		'altcha_hidefooter'                             => AntiSpamForWordPressPlugin::$option_hidefooter,
		'altcha_hidelogo'                               => AntiSpamForWordPressPlugin::$option_hidelogo,
		'altcha_footer_text'                            => AntiSpamForWordPressPlugin::$option_footer_text,
		'altcha_integration_coblocks'                   => AntiSpamForWordPressPlugin::$option_integration_coblocks,
		'altcha_integration_contact_form_7'             => AntiSpamForWordPressPlugin::$option_integration_contact_form_7,
		'altcha_integration_custom'                     => AntiSpamForWordPressPlugin::$option_integration_custom,
		'altcha_integration_elementor'                  => AntiSpamForWordPressPlugin::$option_integration_elementor,
		'altcha_integration_enfold_theme'               => AntiSpamForWordPressPlugin::$option_integration_enfold_theme,
		'altcha_integration_formidable'                 => AntiSpamForWordPressPlugin::$option_integration_formidable,
		'altcha_integration_forminator'                 => AntiSpamForWordPressPlugin::$option_integration_forminator,
		'altcha_integration_gravityforms'               => AntiSpamForWordPressPlugin::$option_integration_gravityforms,
		'altcha_integration_html_forms'                 => AntiSpamForWordPressPlugin::$option_integration_html_forms,
		'altcha_integration_woocommerce_login'          => AntiSpamForWordPressPlugin::$option_integration_woocommerce_login,
		'altcha_integration_woocommerce_register'       => AntiSpamForWordPressPlugin::$option_integration_woocommerce_register,
		'altcha_integration_woocommerce_reset_password' => AntiSpamForWordPressPlugin::$option_integration_woocommerce_reset_password,
		'altcha_integration_wordpress_comments'         => AntiSpamForWordPressPlugin::$option_integration_wordpress_comments,
		'altcha_integration_wordpress_login'            => AntiSpamForWordPressPlugin::$option_integration_wordpress_login,
		'altcha_integration_wordpress_register'         => AntiSpamForWordPressPlugin::$option_integration_wordpress_register,
		'altcha_integration_wordpress_reset_password'   => AntiSpamForWordPressPlugin::$option_integration_wordpress_reset_password,
		'altcha_integration_wpdiscuz'                   => AntiSpamForWordPressPlugin::$option_integration_wpdiscuz,
		'altcha_integration_wpforms'                    => AntiSpamForWordPressPlugin::$option_integration_wpforms,
	);

	$integration_targets = array_flip(
		array(
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
		)
	);

	foreach ( $option_map as $legacy_option => $new_option ) {
		$legacy_value = get_option( $legacy_option, null );
		if ( null === $legacy_value || null !== get_option( $new_option, null ) ) {
			continue;
		}

		if ( isset( $integration_targets[ $new_option ] ) ) {
			$legacy_value = asfw_normalize_migrated_mode( $legacy_value );
		}

		if ( ! asfw_persist_initial_option( $new_option, $legacy_value ) ) {
			return false;
		}
	}

	return asfw_persist_initial_option( $migration_option, ASFW_VERSION );
}
