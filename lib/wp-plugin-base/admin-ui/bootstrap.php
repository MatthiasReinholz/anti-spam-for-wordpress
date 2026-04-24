<?php
/**
 * Managed bootstrap for the admin UI pack.
 *
 * @package WPPluginBase
 * @since NEXT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-wp-plugin-base-admin-ui-loader.php';

$asfw_admin_ui_bootstrap = dirname( __DIR__, 3 ) . '/includes/admin-ui/bootstrap.php';
if ( file_exists( $asfw_admin_ui_bootstrap ) ) {
	require_once $asfw_admin_ui_bootstrap;
}
