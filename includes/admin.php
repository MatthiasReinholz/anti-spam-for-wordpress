<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . '../admin/options.php';
require_once plugin_dir_path( __FILE__ ) . 'settings.php';

function asfw_get_admin_ui_url( $tab = 'settings' ) {
	$allowed_tabs = array( 'settings', 'events', 'analytics' );
	$tab          = sanitize_key( (string) $tab );
	if ( ! in_array( $tab, $allowed_tabs, true ) ) {
		$tab = 'settings';
	}

	return add_query_arg(
		array(
			'page' => 'anti-spam-for-wordpress-admin-ui',
			'tab'  => $tab,
		),
		admin_url( 'admin.php' )
	);
}

function asfw_redirect_legacy_admin_page( $tab = 'settings' ) {
	$url = asfw_get_admin_ui_url( $tab );
	if ( '' === $url ) {
		wp_die( esc_html__( 'Unable to resolve admin destination.', 'anti-spam-for-wordpress' ) );
	}

	wp_safe_redirect( $url );
	exit;
}

function asfw_register_legacy_admin_routes() {
	add_submenu_page(
		null,
		__( 'Anti Spam for WordPress', 'anti-spam-for-wordpress' ),
		__( 'Anti Spam for WordPress', 'anti-spam-for-wordpress' ),
		'manage_options',
		'asfw_admin',
		static function () {
			asfw_redirect_legacy_admin_page( 'settings' );
		}
	);
}

function asfw_settings_link( $links ) {
	$url = esc_url( asfw_get_admin_ui_url( 'settings' ) );

	array_unshift(
		$links,
		"<a href='$url'>" . __( 'Settings', 'anti-spam-for-wordpress' ) . '</a>'
	);

	return $links;
}

function asfw_handle_legacy_admin_redirect() {
	if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
	if ( '' === $page ) {
		return;
	}

	$legacy_map = array(
		'asfw_admin'     => 'settings',
		'asfw_events'    => 'events',
		'asfw_analytics' => 'analytics',
	);

	if ( isset( $legacy_map[ $page ] ) ) {
		asfw_redirect_legacy_admin_page( $legacy_map[ $page ] );
	}
}

if ( is_admin() ) {
	add_action( 'admin_init', 'asfw_handle_legacy_admin_redirect' );
	add_action( 'admin_menu', 'asfw_register_legacy_admin_routes' );
	add_filter( 'plugin_action_links_' . plugin_basename( ASFW_FILE ), 'asfw_settings_link' );
}
