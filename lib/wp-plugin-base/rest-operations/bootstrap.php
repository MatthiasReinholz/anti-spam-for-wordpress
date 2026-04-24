<?php
/**
 * Managed bootstrap for the REST operations pack.
 *
 * @package WPPluginBase
 * @since NEXT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-plugin-base-rest-operations-registry.php';
require_once __DIR__ . '/class-wp-plugin-base-rest-operations-input.php';
require_once __DIR__ . '/class-wp-plugin-base-rest-operations-permissions.php';
require_once __DIR__ . '/class-wp-plugin-base-rest-operations-responses.php';
require_once __DIR__ . '/class-wp-plugin-base-rest-operations-executor.php';
require_once __DIR__ . '/class-wp-plugin-base-rest-operations-rest-adapter.php';
require_once __DIR__ . '/class-wp-plugin-base-rest-operations-abilities-adapter.php';

$asfw_rest_operations_bootstrap = dirname( __DIR__, 3 ) . '/includes/rest-operations/bootstrap.php';

if ( file_exists( $asfw_rest_operations_bootstrap ) ) {
	$asfw_rest_operations = require $asfw_rest_operations_bootstrap;
	if ( is_array( $asfw_rest_operations ) ) {
		WP_Plugin_Base_REST_Operations_Registry::register_many( $asfw_rest_operations );
	}
}

add_action(
	'rest_api_init',
	static function () {
		WP_Plugin_Base_REST_Operations_REST_Adapter::register_all(
			'anti-spam-for-wordpress',
			'anti-spam-for-wordpress/v1',
			WP_Plugin_Base_REST_Operations_Registry::all()
		);
	}
);

if ( (bool) filter_var( 'false', FILTER_VALIDATE_BOOLEAN ) ) {
	add_action(
		'wp_abilities_api_categories_init',
		static function () {
			WP_Plugin_Base_REST_Operations_Abilities_Adapter::register_category(
				'anti-spam-for-wordpress',
				'Anti Spam for WordPress'
			);
		}
	);

	add_action(
		'wp_abilities_api_init',
		static function () {
			WP_Plugin_Base_REST_Operations_Abilities_Adapter::register_operations(
				'anti-spam-for-wordpress',
				'anti-spam-for-wordpress',
				WP_Plugin_Base_REST_Operations_Registry::all()
			);
		}
	);
}
