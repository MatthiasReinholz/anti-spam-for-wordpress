<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function asfw_plugin_active( $name ) {
	switch ( $name ) {
		case 'coblocks':
			return is_plugin_active( 'coblocks/class-coblocks.php' );
		case 'elementor':
			return is_plugin_active( 'elementor/elementor.php' );
		case 'formidable':
			return is_plugin_active( 'formidable/formidable.php' );
		case 'forminator':
			return is_plugin_active( 'forminator/forminator.php' );
		case 'gravityforms':
			return is_plugin_active( 'gravityforms/gravityforms.php' );
		case 'html-forms':
			return is_plugin_active( 'html-forms/html-forms.php' );
		case 'contact-form-7':
			return is_plugin_active( 'contact-form-7/wp-contact-form-7.php' );
		case 'woocommerce':
			return is_plugin_active( 'woocommerce/woocommerce.php' );
		case 'wpdiscuz':
			return is_plugin_active( 'wpdiscuz/class.WpdiscuzCore.php' ) || is_plugin_active( 'wpdiscuz/wpdiscuz.php' );
		case 'wpforms':
			return is_plugin_active( 'wpforms/wpforms.php' ) || is_plugin_active( 'wpforms-lite/wpforms.php' );
		default:
			return apply_filters( 'asfw_plugin_active', false, $name );
	}
}

function asfw_asset_version( $relative_path ) {
	$path = plugin_dir_path( ASFW_FILE ) . ltrim( $relative_path, '/' );
	if ( file_exists( $path ) ) {
		$mtime = filemtime( $path );
		if ( false !== $mtime ) {
			return (string) $mtime;
		}
	}

	return ASFW_VERSION;
}

function asfw_plugin_instance() {
	if ( ! class_exists( 'AntiSpamForWordPressPlugin', false ) ) {
		return null;
	}

	$plugin = AntiSpamForWordPressPlugin::$instance;

	return $plugin instanceof AntiSpamForWordPressPlugin ? $plugin : null;
}

function asfw_enqueue_styles() {
	wp_enqueue_style(
		'asfw-widget-styles',
		AntiSpamForWordPressPlugin::$widget_style_src,
		array(),
		asfw_asset_version( 'public/asfw-widget.css' ),
		'all'
	);
}

function asfw_enqueue_scripts() {
	wp_enqueue_script(
		'asfw-widget',
		AntiSpamForWordPressPlugin::$widget_script_src,
		array(),
		asfw_asset_version( 'public/asfw-widget.js' ),
		true
	);
	wp_enqueue_script(
		'asfw-widget-wp',
		AntiSpamForWordPressPlugin::$wp_script_src,
		array( 'asfw-widget' ),
		asfw_asset_version( 'public/script.js' ),
		true
	);

	$plugin = AntiSpamForWordPressPlugin::$instance;
	wp_localize_script(
		'asfw-widget-wp',
		'ASFW_RUNTIME',
		array(
			'defaultFieldName'   => 'asfw',
			'honeypotEnabled'    => $plugin ? (bool) $plugin->get_honeypot() : false,
			'lazy'               => $plugin ? (bool) $plugin->get_lazy() : false,
			/* translators: %s: number of seconds remaining before the form can be submitted. */
			'submitDelayMessage' => __( 'Please wait %ss...', 'anti-spam-for-wordpress' ),
		)
	);
}

function asfw_get_posted_value( $key ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Integrations call this helper only while performing their own anti-spam verification checks.
	if ( ! isset( $_POST[ $key ] ) ) {
		return '';
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Raw request inspection is required for anti-spam verification.
	return trim( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
}

/**
 * Retrieve a raw proof-of-work payload from $_POST.
 *
 * Unlike asfw_get_posted_value(), this function intentionally skips
 * sanitize_text_field() because the payload is a base64-encoded JSON
 * string that must be preserved verbatim for cryptographic verification.
 * The returned value MUST be passed through decode_payload() before use.
 *
 * @param string $key The $_POST key to retrieve.
 * @return string The raw payload string, or empty string if not set.
 */
function asfw_get_posted_payload( $key ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Integrations call this helper only while performing their own anti-spam verification checks.
	if ( ! isset( $_POST[ $key ] ) ) {
		return '';
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The payload must be read verbatim before cryptographic validation.
	return trim( (string) wp_unslash( $_POST[ $key ] ) );
}

function asfw_render_widget_markup( $mode, $context = null, $name = null, $wrap = true, $language = null ) {
	$plugin = asfw_plugin_instance();
	if ( ! $plugin instanceof AntiSpamForWordPressPlugin ) {
		return '';
	}

	return wp_kses(
		$plugin->render_widget( $mode, $wrap, $language, $name, $context ),
		AntiSpamForWordPressPlugin::$html_allowed_tags
	);
}

function asfw_verify_posted_widget( $context = null, $field_name = 'asfw' ) {
	$plugin  = asfw_plugin_instance();
	$payload = asfw_get_posted_payload( $field_name );
	if ( ! $plugin instanceof AntiSpamForWordPressPlugin ) {
		return false;
	}

	return $plugin->verify( $payload, null, $context, $field_name );
}

function asfw_supported_context_guards() {
	return array(
		'wordpress:login',
		'wordpress:register',
		'wordpress:reset-password',
		'wordpress:comments',
		'wpdiscuz:comments',
		'woocommerce:login',
		'woocommerce:reset-password',
	);
}

function asfw_is_context_guard_supported( $context ) {
	return in_array( ASFW_Feature_Registry::normalize_context( $context ), asfw_supported_context_guards(), true );
}

function asfw_emit_context_guard_result( $feature, $context, $result ) {
	$mode       = ASFW_Feature_Registry::active_mode( $feature );
	$success    = ! ( $result instanceof WP_Error );
	$error_code = $result instanceof WP_Error ? $result->get_error_code() : '';
	do_action( 'asfw_guard_result', $feature, ASFW_Feature_Registry::normalize_context( $context ), $success, $mode, $error_code );

	if ( ! $success && 'block' === $mode ) {
		return $result;
	}

	return true;
}

function asfw_render_context_guards( $context ) {
	$plugin  = asfw_plugin_instance();
	$context = ASFW_Feature_Registry::normalize_context( $context );
	if ( ! $plugin instanceof AntiSpamForWordPressPlugin || ! asfw_is_context_guard_supported( $context ) ) {
		return '';
	}

	$html = '';
	if ( ASFW_Feature_Registry::is_enabled( 'math_challenge', $context ) ) {
		$html .= $plugin->render_math_challenge_fields( $context );
	}

	if ( ASFW_Feature_Registry::is_enabled( 'submit_delay', $context ) ) {
		$html .= $plugin->render_submit_delay_fields( $context, $plugin->get_feature_submit_delay_ms() );
	}

	if ( '' !== $html ) {
		asfw_enqueue_scripts();
		asfw_enqueue_styles();
	}

	return wp_kses( $html, AntiSpamForWordPressPlugin::$html_allowed_tags );
}

function asfw_validate_context_guards( $context ) {
	$plugin  = asfw_plugin_instance();
	$context = ASFW_Feature_Registry::normalize_context( $context );
	if ( ! $plugin instanceof AntiSpamForWordPressPlugin || ! asfw_is_context_guard_supported( $context ) ) {
		return true;
	}

	if ( ASFW_Feature_Registry::is_enabled( 'submit_delay', $context ) ) {
		if ( 'block' === ASFW_Feature_Registry::active_mode( 'submit_delay' ) ) {
			$rate_limited = $plugin->is_rate_limited( 'failure', $context );
			if ( $rate_limited instanceof WP_Error ) {
				return asfw_emit_context_guard_result( 'submit_delay', $context, $rate_limited );
			}
		}

		$result = asfw_emit_context_guard_result(
			'submit_delay',
			$context,
			$plugin->validate_submit_delay_submission( $context, $plugin->get_feature_submit_delay_ms() )
		);
		if ( $result instanceof WP_Error ) {
			$plugin->increment_rate_limit( 'failure', $context );
			return $result;
		}
	}

	if ( ASFW_Feature_Registry::is_enabled( 'math_challenge', $context ) ) {
		if ( 'block' === ASFW_Feature_Registry::active_mode( 'math_challenge' ) ) {
			$rate_limited = $plugin->is_rate_limited( 'failure', $context );
			if ( $rate_limited instanceof WP_Error ) {
				return asfw_emit_context_guard_result( 'math_challenge', $context, $rate_limited );
			}
		}

		$result = asfw_emit_context_guard_result( 'math_challenge', $context, $plugin->validate_math_challenge_submission( $context ) );
		if ( $result instanceof WP_Error ) {
			$plugin->increment_rate_limit( 'failure', $context );
			return $result;
		}
	}

	return true;
}

function asfw_sanitize_slug_option( $value ) {
	$value = strtolower( trim( (string) $value ) );
	$value = preg_replace( '/[^a-z0-9._-]+/', '-', $value );
	$value = trim( $value, '-' );

	return '' === $value ? '' : $value;
}

function asfw_sanitize_bunny_api_key_option( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		$current = trim( (string) get_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key, '' ) );
		if ( '' === $current ) {
			$current = trim( (string) get_option( AntiSpamForWordPressPlugin::$option_bunny_api_key, '' ) );
		}

		return $current;
	}

	return sanitize_text_field( $value );
}

function asfw_sanitize_bunny_integer_option( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}

	return (string) absint( $value );
}
