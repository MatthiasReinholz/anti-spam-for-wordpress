<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Protect WordPress' native signup dispatcher, not programmatic user validation. */
final class ASFW_Multisite_Signup {

	const CONTEXT = 'wordpress:multisite-register';

	public static function register() {
		add_action( 'signup_extra_fields', array( __CLASS__, 'render_error' ), 10, 1 );
		add_action( 'signup_blogform', array( __CLASS__, 'render_error' ), 10, 1 );
		add_action( 'signup_hidden_fields', array( __CLASS__, 'render' ), 10, 1 );
		add_filter( 'wpmu_validate_user_signup', array( __CLASS__, 'validate_user' ), 100, 1 );
		add_filter( 'wpmu_validate_blog_signup', array( __CLASS__, 'validate_site' ), 100, 1 );
	}

	private static function is_native_signup() {
		if ( ! is_multisite() ) {
			return false;
		}
		if ( 'wp-signup.php' === ( $GLOBALS['pagenow'] ?? '' ) ) {
			return true;
		}
		foreach ( array( 'SCRIPT_NAME', 'SCRIPT_FILENAME' ) as $key ) {
			if ( isset( $_SERVER[ $key ] ) && is_string( $_SERVER[ $key ] ) && 'wp-signup.php' === basename( sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) ) ) ) {
				return true;
			}
		}
		return false;
	}

	public static function render( $stage ) {
		$plugin = asfw_plugin_instance();
		if ( ! $plugin || ! in_array( $stage, array( 'validate-user', 'validate-site', 'create-another-site' ), true ) ) {
			return;
		}
		$mode = $plugin->get_integration_wordpress_register();
		if ( '' !== $mode ) {
			echo asfw_render_widget_markup( $mode, self::CONTEXT, 'asfw_register' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shared renderer applies KSES.
		}
	}

	public static function validate_user( $result ) {
		return self::validate( $result );
	}

	public static function validate_site( $result ) {
		// New-user site signup already validates its user/proof earlier in the same
		// dispatcher. Existing users use a separate site-only form and need a proof.
		return is_user_logged_in() && 'gimmeanotherblog' === asfw_get_posted_value( 'stage' ) ? self::validate( $result ) : $result;
	}

	public static function render_error( $errors ) {
		if ( $errors instanceof WP_Error && '' !== $errors->get_error_message( 'asfw_verification' ) ) {
			echo '<p class="error">' . esc_html( $errors->get_error_message( 'asfw_verification' ) ) . '</p>';
		}
	}

	private static function validate( $result ) {
		$plugin = asfw_plugin_instance();
		if ( ! self::is_native_signup() || ! $plugin || '' === $plugin->get_integration_wordpress_register() || ! isset( $result['errors'] ) || ! $result['errors'] instanceof WP_Error ) {
			return $result;
		}
		if ( ! asfw_verify_posted_widget( self::CONTEXT, 'asfw_register' ) ) {
			$result['errors']->add( 'asfw_verification', __( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' ) );
		}
		return $result;
	}
}
