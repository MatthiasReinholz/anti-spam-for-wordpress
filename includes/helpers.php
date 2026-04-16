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
			return is_plugin_active( 'wpdiscuz/class.WpdiscuzCore.php' );
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
			'defaultFieldName' => 'asfw',
			'honeypotEnabled'  => $plugin ? (bool) $plugin->get_honeypot() : false,
			'lazy'             => $plugin ? (bool) $plugin->get_lazy() : false,
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

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The payload must be read verbatim before cryptographic validation.
	return trim( (string) wp_unslash( $_POST[ $key ] ) );
}

function asfw_render_widget_markup( $mode, $context = null, $name = null, $wrap = true, $language = null ) {
	$plugin = AntiSpamForWordPressPlugin::$instance;

	return wp_kses(
		$plugin->render_widget( $mode, $wrap, $language, $name, $context ),
		AntiSpamForWordPressPlugin::$html_allowed_tags
	);
}

function asfw_verify_posted_widget( $context = null, $field_name = 'asfw' ) {
	$plugin  = AntiSpamForWordPressPlugin::$instance;
	$payload = asfw_get_posted_payload( $field_name );

	return $plugin->verify( $payload, null, $context, $field_name );
}
