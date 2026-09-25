<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bind WooCommerce's verified login dispatch to exactly one authentication call.
 *
 * The provider renders login forms on checkout and arbitrary shortcode pages.
 * A URL or public nonce alone cannot establish which handler is authenticating.
 */
final class ASFW_WooCommerce_Login_Dispatch {

	/** @var string|null Username passed by the provider immediately before wp_signon(). */
	private static $pending_username = null;

	/** @var bool[] Active authentication calls, including nested calls. */
	private static $active_calls = array();

	public static function mark( $credentials ) {
		self::$pending_username = null;
		$nonce                  = asfw_get_posted_value( 'woocommerce-login-nonce' );
		if ( '' === $nonce ) {
			$nonce = asfw_get_posted_value( '_wpnonce' );
		}
		if ( asfw_plugin_active( 'woocommerce' ) && ! asfw_is_native_wordpress_auth_request()
			&& is_array( $credentials ) && isset( $credentials['user_login'] ) && is_string( $credentials['user_login'] )
			&& wp_verify_nonce( $nonce, 'woocommerce-login' ) ) {
			self::$pending_username = sanitize_user( $credentials['user_login'] );
		}
		return $credentials;
	}

	public static function begin( $user, $username ) {
		self::$active_calls[]   = null !== self::$pending_username
			&& trim( (string) $username ) === self::$pending_username
			&& ! asfw_is_native_wordpress_auth_request();
		self::$pending_username = null;
		return $user;
	}

	public static function is_active() {
		return ! empty( self::$active_calls ) && true === end( self::$active_calls );
	}

	public static function finish( $user ) {
		array_pop( self::$active_calls );
		return $user;
	}
}
