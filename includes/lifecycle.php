<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-asfw-option-inventory.php';

/** Initialize only missing state; reactivation must never replace administrator choices. */
function asfw_initialize_site() {
	$store = new ASFW_Atomic_State_Store();
	$lease = $store->acquire_lease( 'site-initialization', 30 );
	if ( ! is_array( $lease ) ) {
		return false;
	}
	try {
		asfw_maybe_migrate_legacy_settings( true );
		// Discard cached misses before provisioning a secret under the exclusive lease.
		wp_cache_delete( 'asfw_secret', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		if ( '' === trim( (string) get_option( 'asfw_secret', '' ) ) ) {
			$secret = bin2hex( random_bytes( 32 ) );
			if ( null === get_option( 'asfw_secret', null ) ) {
				add_option( 'asfw_secret', $secret, '', false );
			} else {
				update_option( 'asfw_secret', $secret, false );
			}
		}
		$defaults = array(
			'asfw_complexity'                => 'medium',
			'asfw_expires'                   => '300',
			'asfw_hidefooter'                => true,
			'asfw_hidelogo'                  => false,
			'asfw_widget_appearance'         => 'light',
			'asfw_widget_layout'             => 'compact',
			'asfw_privacy_new_tab'           => false,
			'asfw_privacy_legal_basis'       => ASFW_Privacy_Policy_Text::LEGAL_BASIS_REVIEW_REQUIRED,
			'asfw_integration_custom'        => 'captcha',
			'asfw_lazy'                      => true,
			'asfw_rate_limit_max_challenges' => '30',
			'asfw_rate_limit_max_failures'   => '10',
			'asfw_rate_limit_window'         => '600',
			'asfw_honeypot'                  => true,
			'asfw_min_submit_time'           => '3',
			'asfw_visitor_binding'           => 'ip',
			'asfw_trusted_proxies'           => '',
			'asfw_trusted_proxy_header'      => 'HTTP_X_FORWARDED_FOR',
		);
		foreach ( $defaults as $name => $value ) {
			if ( null === get_option( $name, null ) ) {
				add_option( $name, $value, '', false );
			}
		}
		asfw_seed_control_plane_defaults();
		asfw_initialize_control_plane();
		$services  = ASFW_Control_Plane::instance();
		$installed = $services['store']->install();
		$services['maintenance']->maybe_schedule();
		if ( is_wp_error( $installed ) || '' === (string) get_option( 'asfw_secret', '' ) ) {
			return false;
		}
		update_option( 'asfw_site_initialized', '1', false );
		return true;
	} finally {
		$store->release_lease( 'site-initialization', $lease );
	}
}

/** Lazy repair also covers sites not yet reached by a network initialization batch. */
function asfw_maybe_initialize_site() {
	if ( '1' !== get_option( 'asfw_site_initialized', '' ) || '' === (string) get_option( 'asfw_secret', '' ) ) {
		asfw_initialize_site();
	}
}

/** Visit one bounded page, restoring the calling site's context even on failure. */
function asfw_visit_network_sites( $network_id, $offset, $callback ) {
	$ids = get_sites(
		array(
			'network_id' => (int) $network_id,
			'fields'     => 'ids',
			'number'     => 100,
			'offset'     => (int) $offset,
			'orderby'    => 'id',
			'order'      => 'ASC',
		)
	);
	foreach ( $ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		try {
			call_user_func( $callback );
		} finally {
			restore_current_blog();
		}
	}
	return count( $ids );
}

function asfw_initialize_network_batch( $network_id, $offset = 0 ) {
	$count = asfw_visit_network_sites( $network_id, $offset, 'asfw_initialize_site' );
	if ( 100 === $count ) {
		$args = array( (int) $network_id, (int) $offset + $count );
		if ( ! wp_next_scheduled( 'asfw_initialize_network_batch', $args ) ) {
			wp_schedule_single_event( time() + 10, 'asfw_initialize_network_batch', $args );
		}
	}
}

function asfw_activate( $network_wide = false ) {
	if ( $network_wide && is_multisite() ) {
		asfw_initialize_network_batch( get_current_network_id() );
	} else {
		asfw_initialize_site();
	}
}

function asfw_deactivate_site() {
	wp_clear_scheduled_hook( 'asfw_daily_maintenance' );
	wp_clear_scheduled_hook( 'asfw_security_state_cleanup' );
	wp_unschedule_hook( 'asfw_initialize_network_batch' );
}

function asfw_deactivate( $network_wide = false ) {
	if ( $network_wide && is_multisite() ) {
		$offset = 0;
		do {
			$count   = asfw_visit_network_sites( get_current_network_id(), $offset, 'asfw_deactivate_site' );
			$offset += $count;
		} while ( 100 === $count );
	} else {
		asfw_deactivate_site();
	}
}

function asfw_initialize_new_site( $site ) {
	$active = get_network_option( (int) $site->network_id, 'active_sitewide_plugins', array() );
	if ( ! isset( $active[ plugin_basename( ASFW_FILE ) ] ) ) {
		return;
	}
	switch_to_blog( (int) $site->blog_id );
	try {
		asfw_initialize_site();
	} finally {
		restore_current_blog();
	}
}

add_action( 'asfw_initialize_network_batch', 'asfw_initialize_network_batch', 10, 2 );
add_action( 'wp_initialize_site', 'asfw_initialize_new_site', 100, 1 );
