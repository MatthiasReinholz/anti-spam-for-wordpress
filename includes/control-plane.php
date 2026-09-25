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
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-cli-command.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-control-plane.php';

/** Write initialization state and verify storage, accepting WordPress scalar normalization. */
function asfw_persist_initial_option( $option, $value, $autoload = null ) {
	// update_option() treats a missing option's default false as an unchanged value.
	if ( null === get_option( $option, null ) ) {
		add_option( $option, $value, '', $autoload );
	} else {
		update_option( $option, $value, $autoload );
	}
	$stored = get_option( $option, null );
	if ( null === $stored ) {
		return false;
	}
	if ( is_bool( $value ) ) {
		// Registered checkbox sanitizers store integers; database reads return strings.
		return in_array( $stored, $value ? array( true, 1, '1' ) : array( false, 0, '0', '' ), true );
	}
	return is_scalar( $value ) && is_scalar( $stored )
		? (string) $value === (string) $stored
		: $value === $stored;
}

/** @return bool Whether all missing control-plane defaults were persisted. */
function asfw_seed_control_plane_defaults() {
	$disposable_auto_refresh_option   = class_exists( 'ASFW_Disposable_Email_Module', false )
		? ASFW_Disposable_Email_Module::OPTION_AUTO_REFRESH
		: 'asfw_disposable_email_auto_refresh';
	$content_heuristics_legacy_option = class_exists( 'ASFW_Content_Heuristics_Module', false )
		? ASFW_Content_Heuristics_Module::OPTION_ENABLED
		: 'asfw_content_heuristics_enabled';

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_kill_switch, null ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_kill_switch, false ) ) {
			return false;
		}
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_privacy_legal_basis, null ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_privacy_legal_basis, ASFW_Privacy_Policy_Text::LEGAL_BASIS_REVIEW_REQUIRED ) ) {
			return false;
		}
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_bunny_enabled, null ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_bunny_enabled, false ) ) {
			return false;
		}
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled, null ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled, (bool) get_option( AntiSpamForWordPressPlugin::$option_bunny_enabled, false ) ) ) {
			return false;
		}
	}
	$legacy_bunny_enabled = (bool) get_option( AntiSpamForWordPressPlugin::$option_bunny_enabled, false );
	if ( null === get_option( 'asfw_feature_bunny_shield_mode', null ) ) {
		if ( ! asfw_persist_initial_option( 'asfw_feature_bunny_shield_mode', $legacy_bunny_enabled ? 'block' : 'off' ) ) {
			return false;
		}
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_bunny_api_key, null ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_bunny_api_key, '' ) ) {
			return false;
		}
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key, null ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key, (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_api_key, '' ) ) ) {
			return false;
		}
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_bunny_shield_zone_id, null ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_bunny_shield_zone_id, '' ) ) {
			return false;
		}
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_zone_id, null ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_zone_id, (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_shield_zone_id, '' ) ) ) {
			return false;
		}
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_bunny_access_list_id, null ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_bunny_access_list_id, '' ) ) {
			return false;
		}
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id, null ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id, (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_access_list_id, '' ) ) ) {
			return false;
		}
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_bunny_dry_run, null ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_bunny_dry_run, true ) ) {
			return false;
		}
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run, null ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run, (bool) get_option( AntiSpamForWordPressPlugin::$option_bunny_dry_run, true ) ) ) {
			return false;
		}
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_bunny_fail_open, null ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_bunny_fail_open, true ) ) {
			return false;
		}
	}

	if ( null === get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_fail_open, null ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_fail_open, (bool) get_option( AntiSpamForWordPressPlugin::$option_bunny_fail_open, true ) ) ) {
			return false;
		}
	}

	if ( '' === (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_threshold, '' ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_bunny_threshold, '10' ) ) {
			return false;
		}
	}

	if ( '' === (string) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold, '' ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold, (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_threshold, '10' ) ) ) {
			return false;
		}
	}

	if ( '' === (string) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes, '' ) ) {
		$legacy_dedupe_window = max( 60, intval( (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_dedupe_window, '3600' ), 10 ) );
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes, (string) max( 1, intval( ceil( $legacy_dedupe_window / 60 ) ) ) ) ) {
			return false;
		}
	}

	if ( '' === (string) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_action, '' ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_action, 'block' ) ) {
			return false;
		}
	}

	if ( '' === (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_dedupe_window, '' ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_bunny_dedupe_window, '3600' ) ) {
			return false;
		}
	}

	if ( null === get_option( $disposable_auto_refresh_option, null ) ) {
		if ( ! asfw_persist_initial_option( $disposable_auto_refresh_option, false ) ) {
			return false;
		}
	}
	if ( null === get_option( 'asfw_feature_disposable_email_background_enabled', null ) ) {
		if ( ! asfw_persist_initial_option( 'asfw_feature_disposable_email_background_enabled', (bool) get_option( $disposable_auto_refresh_option, false ) ) ) {
			return false;
		}
	}

	if ( null === get_option( 'asfw_feature_content_heuristics_enabled', null ) ) {
		if ( ! asfw_persist_initial_option( 'asfw_feature_content_heuristics_enabled', (bool) get_option( $content_heuristics_legacy_option, false ) ) ) {
			return false;
		}
	}
	if ( null === get_option( 'asfw_feature_content_heuristics_mode', null ) ) {
		$legacy_content_heuristics_enabled = (bool) get_option( $content_heuristics_legacy_option, false );
		if ( ! asfw_persist_initial_option( 'asfw_feature_content_heuristics_mode', $legacy_content_heuristics_enabled ? 'log' : 'off' ) ) {
			return false;
		}
	}

	if ( '' === (string) get_option( AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '' ) ) {
		if ( ! asfw_persist_initial_option( AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '2500' ) ) {
			return false;
		}
	}

	foreach ( ASFW_Feature_Registry::definitions() as $definition ) {
		if ( ! is_array( $definition ) || empty( $definition['enabled_option'] ) ) {
			continue;
		}

		if ( null === get_option( $definition['enabled_option'], null ) ) {
			$enabled = ! empty( $definition['default_enabled'] );
			if ( ! asfw_persist_initial_option( $definition['enabled_option'], $enabled ) ) {
				return false;
			}
		}

		if ( ! empty( $definition['scope_mode_option'] ) && null === get_option( $definition['scope_mode_option'], null ) ) {
			if ( ! asfw_persist_initial_option( $definition['scope_mode_option'], isset( $definition['default_scope_mode'] ) ? $definition['default_scope_mode'] : 'all' ) ) {
				return false;
			}
		}

		if ( ! empty( $definition['contexts_option'] ) && null === get_option( $definition['contexts_option'], null ) ) {
			if ( ! asfw_persist_initial_option( $definition['contexts_option'], isset( $definition['default_contexts'] ) && is_array( $definition['default_contexts'] ) ? $definition['default_contexts'] : array() ) ) {
				return false;
			}
		}

		if ( ! empty( $definition['mode_option'] ) && null === get_option( $definition['mode_option'], null ) ) {
			$mode = isset( $definition['default_mode'] ) ? $definition['default_mode'] : 'off';
			if ( ! asfw_persist_initial_option( $definition['mode_option'], $mode ) ) {
				return false;
			}
		}

		if ( ! empty( $definition['background_option'] ) && null === get_option( $definition['background_option'], null ) ) {
			$background_enabled = ! empty( $definition['default_background'] );
			if ( ! asfw_persist_initial_option( $definition['background_option'], $background_enabled ) ) {
				return false;
			}
		}
	}

	foreach ( ASFW_Feature_Registry::get_integration_features() as $integration ) {
		if ( ! is_array( $integration ) || empty( $integration['option'] ) ) {
			continue;
		}

		if ( null === get_option( $integration['option'], null ) ) {
			$default = isset( $integration['default'] ) ? (string) $integration['default'] : '';
			if ( ! asfw_persist_initial_option( $integration['option'], $default ) ) {
				return false;
			}
		}
	}
	return true;
}

function asfw_initialize_control_plane() {
	static $initialized = false;

	if ( $initialized ) {
		return;
	}

	$initialized = true;
	ASFW_Control_Plane::init();
}
