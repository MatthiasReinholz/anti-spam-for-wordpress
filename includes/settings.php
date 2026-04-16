<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function asfw_sanitize_checkbox_option( $value ) {
	return empty( $value ) ? 0 : 1;
}

function asfw_sanitize_enum_option( $value, array $allowed, $default_value = '' ) {
	$value = trim( (string) $value );

	return in_array( $value, $allowed, true ) ? $value : $default_value;
}

function asfw_sanitize_numeric_string_option( $value, array $allowed, $default_value ) {
	$value = trim( (string) $value );

	return in_array( $value, $allowed, true ) ? $value : $default_value;
}

function asfw_sanitize_trusted_proxies_option( $value ) {
	$entries = preg_split( '/[\r\n,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! is_array( $entries ) ) {
		return '';
	}

	$plugin  = asfw_plugin_instance();
	$entries = array_values(
		array_filter(
			array_map(
				function ( $entry ) use ( $plugin ) {
					$entry = trim( (string) $entry );
					if ( '' === $entry ) {
						return '';
					}

					if ( false === strpos( $entry, '/' ) ) {
						if ( $plugin instanceof AntiSpamForWordPressPlugin ) {
							return $plugin->normalize_ip( $entry );
						}

						return filter_var( $entry, FILTER_VALIDATE_IP ) ? $entry : '';
					}

					list($subnet, $prefix) = array_pad( explode( '/', $entry, 2 ), 2, '' );
					if ( $plugin instanceof AntiSpamForWordPressPlugin ) {
						$subnet = $plugin->normalize_ip( $subnet );
					} else {
						$subnet = filter_var( $subnet, FILTER_VALIDATE_IP ) ? $subnet : '';
					}
					if ( '' === $subnet || ! preg_match( '/^\d+$/', $prefix ) ) {
						return '';
					}

					return $subnet . '/' . $prefix;
				},
				$entries
			)
		)
	);

	return implode( ', ', array_unique( $entries ) );
}

function asfw_sanitize_secret_option( $value ) {
	$value = trim( wp_strip_all_tags( (string) $value ) );
	if ( '' === $value ) {
		$current = trim( (string) get_option( AntiSpamForWordPressPlugin::$option_secret, '' ) );
		$plugin  = asfw_plugin_instance();

		if ( '' !== $current ) {
			return $current;
		}

		if ( $plugin instanceof AntiSpamForWordPressPlugin ) {
			return $plugin->random_secret();
		}

		return bin2hex( random_bytes( 32 ) );
	}

	return $value;
}

function asfw_sanitize_footer_text_option( $value ) {
	return trim( wp_strip_all_tags( (string) $value ) );
}

function asfw_sanitize_privacy_target_option( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value || 'custom' === $value ) {
		return $value;
	}

	if ( ctype_digit( $value ) ) {
		return (string) absint( $value );
	}

	return '';
}

function asfw_sanitize_privacy_url_option( $value ) {
	return esc_url_raw( trim( (string) $value ) );
}

function asfw_sanitize_feature_contexts_option( $value ) {
	if ( is_array( $value ) ) {
		$contexts = $value;
	} else {
		$contexts = preg_split( '/[\r\n,]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
	}

	if ( ! is_array( $contexts ) ) {
		return array();
	}

	return array_values(
		array_unique(
			array_filter(
				array_map(
					array( 'ASFW_Feature_Registry', 'sanitize_selected_context' ),
					$contexts
				)
			)
		)
	);
}

function asfw_sanitize_retention_days_option( $value ) {
	return asfw_sanitize_numeric_string_option(
		$value,
		array( '7', '14', '30', '60', '90', '180', '365' ),
		'30'
	);
}

function asfw_register_setting_option( $option, $sanitize_callback = null ) {
	ASFW_Settings_Registrar::register_setting_option( $option, $sanitize_callback );
}

function asfw_register_settings_field_from_schema( array $field ) {
	ASFW_Settings_Renderer::register_settings_field( $field );
}

function asfw_register_external_settings() {
	ASFW_Settings_Registrar::register_external_settings();
}

function asfw_settings_init() {
	ASFW_Settings_Registrar::init();
}

function asfw_settings_option_updated( $option, $old_value, $value ) {
	ASFW_Settings_Registrar::option_updated( $option, $old_value, $value );
}

function asfw_sync_legacy_feature_options( $updated_option ) {
	ASFW_Settings_Registrar::sync_legacy_feature_options( $updated_option );
}

if ( is_admin() ) {
	add_action( 'admin_init', 'asfw_settings_init' );
	add_action( 'updated_option', 'asfw_settings_option_updated', 10, 3 );
}
