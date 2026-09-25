<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package anti-spam-for-wordpress
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-asfw-option-inventory.php';

global $wpdb;

if ( ! function_exists( 'asfw_uninstall_escape_identifier' ) ) {
	function asfw_uninstall_escape_identifier( $identifier ) {
		$identifier = preg_replace( '/[^A-Za-z0-9_]/', '', (string) $identifier );

		if ( '' === $identifier ) {
			return '';
		}

		return '`' . $identifier . '`';
	}
}

if ( ! function_exists( 'asfw_uninstall_option_names' ) ) {
	function asfw_uninstall_option_names() {
		return ASFW_Option_Inventory::names();
	}
}

if ( ! function_exists( 'asfw_uninstall_site' ) ) {
	function asfw_uninstall_site() {
		global $wpdb;

		foreach ( asfw_uninstall_option_names() as $option_name ) {
			delete_option( $option_name );
		}

		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( 'asfw_daily_maintenance' );
			wp_clear_scheduled_hook( 'asfw_security_state_cleanup' );
			wp_unschedule_hook( 'asfw_initialize_network_batch' );
		}

		if ( ! is_object( $wpdb ) || ! isset( $wpdb->prefix ) || ! method_exists( $wpdb, 'query' ) ) {
			return;
		}

		$events_table = asfw_uninstall_escape_identifier( $wpdb->prefix . 'asfw_events' );
		if ( '' === $events_table ) {
			return;
		}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall must drop the plugin-owned custom table; identifier is normalized above.
			$wpdb->query( "DROP TABLE IF EXISTS {$events_table}" );

		if ( isset( $wpdb->options ) && method_exists( $wpdb, 'esc_like' ) && method_exists( $wpdb, 'prepare' ) ) {
			$option_table = asfw_uninstall_escape_identifier( $wpdb->options );
			if ( '' === $option_table ) {
				return;
			}
			$patterns = array(
				$wpdb->esc_like( '_transient_asfw_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_asfw_' ) . '%',
				$wpdb->esc_like( 'asfw_rl_' ) . '%',
				$wpdb->esc_like( 'asfw_state_' ) . '%',
				$wpdb->esc_like( 'asfw_challenge_lock_' ) . '%',
			);

			foreach ( $patterns as $pattern ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Option table identifier is normalized and LIKE values are prepared.
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$option_table} WHERE option_name LIKE %s", $pattern ) );
			}
		}
	}
}

if (
	function_exists( 'is_multisite' ) &&
	is_multisite() &&
	function_exists( 'get_sites' ) &&
	function_exists( 'switch_to_blog' ) &&
	function_exists( 'restore_current_blog' )
) {
	$asfw_offset = 0;
	do {
		$asfw_site_ids = get_sites(
			array(
				'fields'  => 'ids',
				'number'  => 100,
				'offset'  => $asfw_offset,
				'orderby' => 'id',
				'order'   => 'ASC',
			)
		);
		foreach ( $asfw_site_ids as $asfw_site_id ) {
			switch_to_blog( (int) $asfw_site_id );
			try {
				asfw_uninstall_site();
			} finally {
				restore_current_blog();
			}
		}
		$asfw_count   = count( $asfw_site_ids );
		$asfw_offset += $asfw_count;
	} while ( 100 === $asfw_count );
} else {
	asfw_uninstall_site();
}
