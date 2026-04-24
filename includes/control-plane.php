<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'privacy.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-event-store.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-event-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-disposable-email-module.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-maintenance.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-content-heuristics-module.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-bunny-shield-client.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-bunny-shield-module.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-admin-pages.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-cli-command.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-control-plane.php';

function asfw_seed_control_plane_defaults() {
	$disposable_auto_refresh_option   = class_exists( 'ASFW_Disposable_Email_Module', false )
		? ASFW_Disposable_Email_Module::OPTION_AUTO_REFRESH
		: 'asfw_disposable_email_auto_refresh';
	$content_heuristics_legacy_option = class_exists( 'ASFW_Content_Heuristics_Module', false )
		? ASFW_Content_Heuristics_Module::OPTION_ENABLED
		: 'asfw_content_heuristics_enabled';

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_kill_switch, null ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_kill_switch, false );
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_bunny_enabled, null ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_bunny_enabled, false );
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled, null ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled, (bool) get_option( AntiSpamForWordPressPlugin::$option_bunny_enabled, false ) );
	}
	$legacy_bunny_enabled = (bool) get_option( AntiSpamForWordPressPlugin::$option_bunny_enabled, false );
	if ( null === get_option( 'asfw_feature_bunny_shield_mode', null ) ) {
		update_option( 'asfw_feature_bunny_shield_mode', $legacy_bunny_enabled ? 'block' : 'off' );
	} elseif ( $legacy_bunny_enabled && 'off' === (string) get_option( 'asfw_feature_bunny_shield_mode', 'off' ) ) {
		update_option( 'asfw_feature_bunny_shield_mode', 'block' );
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_bunny_api_key, null ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_bunny_api_key, '' );
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key, null ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key, (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_api_key, '' ) );
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_bunny_shield_zone_id, null ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_bunny_shield_zone_id, '' );
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_zone_id, null ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_zone_id, (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_shield_zone_id, '' ) );
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_bunny_access_list_id, null ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_bunny_access_list_id, '' );
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id, null ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id, (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_access_list_id, '' ) );
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_bunny_dry_run, null ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_bunny_dry_run, true );
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run, null ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run, (bool) get_option( AntiSpamForWordPressPlugin::$option_bunny_dry_run, true ) );
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_bunny_fail_open, null ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_bunny_fail_open, true );
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_fail_open, null ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_fail_open, (bool) get_option( AntiSpamForWordPressPlugin::$option_bunny_fail_open, true ) );
	}

	if ( '' === (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_threshold, '' ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_bunny_threshold, '10' );
	}

	if ( '' === (string) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold, '' ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold, (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_threshold, '10' ) );
	}

	if ( '' === (string) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes, '' ) ) {
		$legacy_dedupe_window = max( 60, intval( (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_dedupe_window, '3600' ), 10 ) );
		update_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes, (string) max( 1, intval( ceil( $legacy_dedupe_window / 60 ) ) ) );
	}

	if ( '' === (string) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_action, '' ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_action, 'block' );
	}

	if ( '' === (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_dedupe_window, '' ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_bunny_dedupe_window, '3600' );
	}

	if ( null === get_option( $disposable_auto_refresh_option, null ) ) {
		update_option( $disposable_auto_refresh_option, false );
	}
	if ( null === get_option( 'asfw_feature_disposable_email_background_enabled', null ) ) {
		update_option( 'asfw_feature_disposable_email_background_enabled', (bool) get_option( $disposable_auto_refresh_option, false ) );
	}

	if ( null === get_option( 'asfw_feature_content_heuristics_enabled', null ) ) {
		update_option( 'asfw_feature_content_heuristics_enabled', (bool) get_option( $content_heuristics_legacy_option, false ) );
	}
	if ( null === get_option( 'asfw_feature_content_heuristics_mode', null ) ) {
		$legacy_content_heuristics_enabled = (bool) get_option( $content_heuristics_legacy_option, false );
		update_option( 'asfw_feature_content_heuristics_mode', $legacy_content_heuristics_enabled ? 'log' : 'off' );
	}

	if ( '' === (string) get_option( AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '' ) ) {
		update_option( AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '2500' );
	}

	foreach ( ASFW_Feature_Registry::definitions() as $definition ) {
		if ( ! is_array( $definition ) || empty( $definition['enabled_option'] ) ) {
			continue;
		}

		if ( null === get_option( $definition['enabled_option'], null ) ) {
			$enabled = ! empty( $definition['default_enabled'] );
			update_option( $definition['enabled_option'], $enabled );
		}

		if ( ! empty( $definition['scope_mode_option'] ) && null === get_option( $definition['scope_mode_option'], null ) ) {
			update_option( $definition['scope_mode_option'], isset( $definition['default_scope_mode'] ) ? $definition['default_scope_mode'] : 'all' );
		}

		if ( ! empty( $definition['contexts_option'] ) && null === get_option( $definition['contexts_option'], null ) ) {
			update_option( $definition['contexts_option'], isset( $definition['default_contexts'] ) && is_array( $definition['default_contexts'] ) ? $definition['default_contexts'] : array() );
		}

		if ( ! empty( $definition['mode_option'] ) && null === get_option( $definition['mode_option'], null ) ) {
			$mode = isset( $definition['default_mode'] ) ? $definition['default_mode'] : 'off';
			update_option( $definition['mode_option'], $mode );
		}

		if ( ! empty( $definition['background_option'] ) && null === get_option( $definition['background_option'], null ) ) {
			$background_enabled = ! empty( $definition['default_background'] );
			update_option( $definition['background_option'], $background_enabled );
		}
	}
}

function asfw_initialize_control_plane() {
	static $initialized = false;

	if ( $initialized ) {
		return;
	}

	$initialized = true;
	ASFW_Control_Plane::init();
}
