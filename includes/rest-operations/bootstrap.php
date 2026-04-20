<?php
/**
 * Child-owned REST operations bootstrap.
 *
 * @package anti-spam-for-wordpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wp_plugin_base_attach_operation_source = static function ( $source_file, $operations ) {
	if ( ! is_array( $operations ) ) {
		return array();
	}

	return array_map(
		static function ( $operation ) use ( $source_file ) {
			if ( ! is_array( $operation ) ) {
				return $operation;
			}

			if ( empty( $operation['source_file'] ) ) {
				$operation['source_file'] = $source_file;
			}

			return $operation;
		},
		$operations
	);
};

$settings_operations = $wp_plugin_base_attach_operation_source(
	'includes/rest-operations/settings-operations.php',
	require __DIR__ . '/settings-operations.php'
);
$events_operations = $wp_plugin_base_attach_operation_source(
	'includes/rest-operations/events-operations.php',
	require __DIR__ . '/events-operations.php'
);
$analytics_operations = $wp_plugin_base_attach_operation_source(
	'includes/rest-operations/analytics-operations.php',
	require __DIR__ . '/analytics-operations.php'
);

return array_merge(
	array(),
	is_array( $settings_operations ) ? $settings_operations : array(),
	is_array( $events_operations ) ? $events_operations : array(),
	is_array( $analytics_operations ) ? $analytics_operations : array()
);
