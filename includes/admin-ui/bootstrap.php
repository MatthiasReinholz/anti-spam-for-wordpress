<?php
/**
 * Child-owned admin UI bootstrap.
 *
 * @package anti-spam-for-wordpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

WP_Plugin_Base_Admin_UI_Loader::register_page(
	array(
		'page_title'             => 'Anti Spam for WordPress',
		'menu_title'             => 'Anti Spam for WordPress',
		'capability'             => 'manage_options',
		'menu_slug'              => 'anti-spam-for-wordpress-admin-ui',
		'root_id'                => 'anti-spam-for-wordpress-admin-ui-root',
		'plugin_slug'            => 'anti-spam-for-wordpress',
		'text_domain'            => 'anti-spam-for-wordpress',
		'script_handle'          => 'anti-spam-for-wordpress-admin-ui',
		'style_handle'           => 'anti-spam-for-wordpress-admin-ui',
		'rest_namespace'         => 'anti-spam-for-wordpress/v1',
		'plugin_name'            => 'Anti Spam for WordPress',
		'experimental_dataviews' => 'true' === 'false',
	)
);
