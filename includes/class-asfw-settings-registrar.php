<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ASFW_Settings_Registrar {

	public static function register_setting_option( $option, $sanitize_callback = null ) {
		$args = array();
		if ( null !== $sanitize_callback ) {
			$args['sanitize_callback'] = $sanitize_callback;
		}

		register_setting( 'asfw_options', $option, $args );
	}

	public static function register_external_settings() {
		$definitions = apply_filters( 'asfw_settings_external_registered_settings', array() );
		if ( ! is_array( $definitions ) ) {
			return;
		}

		foreach ( $definitions as $definition ) {
			if ( ! is_array( $definition ) || empty( $definition['option'] ) ) {
				continue;
			}

			$option            = (string) $definition['option'];
			$sanitize_callback = isset( $definition['sanitize_callback'] ) && is_callable( $definition['sanitize_callback'] )
				? $definition['sanitize_callback']
				: null;

			self::register_setting_option( $option, $sanitize_callback );
		}
	}

	public static function init() {
		$fields_by_section = ASFW_Settings_Definitions::get_fields_by_section();

		foreach ( ASFW_Settings_Definitions::get_registered_settings() as $setting ) {
			self::register_setting_option( $setting['option'], $setting['sanitize_callback'] );
		}

		self::register_external_settings();

		foreach ( ASFW_Settings_Definitions::get_sections() as $section ) {
			ASFW_Settings_Renderer::register_settings_section( $section );
		}

		$section_order = array(
			'asfw_control_plane_settings_section',
			'asfw_general_settings_section',
			'asfw_security_settings_section',
			'asfw_bunny_settings_section',
			'asfw_widget_settings_section',
			'asfw_integrations_settings_section',
		);

		foreach ( $section_order as $section_id ) {
			if ( empty( $fields_by_section[ $section_id ] ) ) {
				continue;
			}

			foreach ( $fields_by_section[ $section_id ] as $field ) {
				ASFW_Settings_Renderer::register_settings_field( $field );
			}

			if ( 'asfw_integrations_settings_section' === $section_id ) {
				// Legacy render hook. For schema-safe setting registration use
				// the `asfw_settings_schema_fields` and
				// `asfw_settings_external_registered_settings` filters.
				do_action( 'asfw_settings_integrations' );
			}
		}

		if ( ! empty( $fields_by_section['asfw_wordpress_settings_section'] ) ) {
			foreach ( $fields_by_section['asfw_wordpress_settings_section'] as $field ) {
				ASFW_Settings_Renderer::register_settings_field( $field );
			}
		}
	}

	public static function option_updated( $option, $old_value, $value ) {
		static $syncing_legacy_options = false;

		if ( 0 !== strpos( (string) $option, 'asfw_' ) ) {
			return;
		}

		if ( $old_value === $value ) {
			return;
		}

		if ( $syncing_legacy_options ) {
			return;
		}

		if ( ! $syncing_legacy_options ) {
			$syncing_legacy_options = true;
			try {
				self::sync_legacy_feature_options( (string) $option );
			} finally {
				$syncing_legacy_options = false;
			}
		}

		$user_id = function_exists( 'get_current_user_id' ) ? intval( get_current_user_id(), 10 ) : 0;
		do_action(
			'asfw_settings_changed',
			array(
				(string) $option => array(
					'old' => $old_value,
					'new' => $value,
				),
			),
			$user_id
		);
	}

	public static function sync_legacy_feature_options( $updated_option ) {
		switch ( $updated_option ) {
			case AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled:
				update_option(
					AntiSpamForWordPressPlugin::$option_bunny_enabled,
					(bool) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled, false ) ? 1 : 0
				);
				break;

			case 'asfw_feature_bunny_shield_mode':
				update_option( AntiSpamForWordPressPlugin::$option_bunny_enabled, ASFW_Feature_Registry::is_enabled( 'bunny_shield' ) ? 1 : 0 );
				break;

			case AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key:
				update_option( AntiSpamForWordPressPlugin::$option_bunny_api_key, (string) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key, '' ) );
				break;

			case AntiSpamForWordPressPlugin::$option_feature_bunny_shield_zone_id:
				update_option( AntiSpamForWordPressPlugin::$option_bunny_shield_zone_id, (string) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_zone_id, '' ) );
				break;

			case AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id:
				update_option( AntiSpamForWordPressPlugin::$option_bunny_access_list_id, (string) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id, '' ) );
				break;

			case AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run:
				update_option( AntiSpamForWordPressPlugin::$option_bunny_dry_run, (bool) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run, true ) );
				break;

			case AntiSpamForWordPressPlugin::$option_feature_bunny_shield_fail_open:
				update_option( AntiSpamForWordPressPlugin::$option_bunny_fail_open, (bool) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_fail_open, true ) );
				break;

			case AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold:
				update_option( AntiSpamForWordPressPlugin::$option_bunny_threshold, (string) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold, '10' ) );
				break;

			case AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes:
				update_option(
					AntiSpamForWordPressPlugin::$option_bunny_dedupe_window,
					(string) ( max( 1, intval( get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes, '60' ), 10 ) ) * 60 )
				);
				break;

			case 'asfw_feature_content_heuristics_enabled':
			case 'asfw_feature_content_heuristics_mode':
				update_option( ASFW_Content_Heuristics_Module::OPTION_ENABLED, ASFW_Feature_Registry::is_enabled( 'content_heuristics' ) ? 1 : 0 );
				break;

			case 'asfw_feature_disposable_email_background_enabled':
				update_option( ASFW_Disposable_Email_Module::OPTION_AUTO_REFRESH, ASFW_Feature_Registry::background_enabled( 'disposable_email' ) ? 1 : 0 );
				break;
		}
	}
}
