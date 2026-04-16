<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function asfw_is_woocommerce_account_request() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	if ( '' === $request_uri ) {
		return false;
	}

	$path = strtolower( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) );
	if ( '' === $path ) {
		$path = '/';
	}
	$query      = (string) wp_parse_url( $request_uri, PHP_URL_QUERY );
	$query_vars = array();
	if ( '' !== $query ) {
		parse_str( $query, $query_vars );
		if ( ! is_array( $query_vars ) ) {
			$query_vars = array();
		}
	}

	$path = '/' . trim( $path, '/' ) . '/';

	if ( 1 === preg_match( '#(?:^|/)my-account(?:/|$)#', $path ) || 1 === preg_match( '#(?:^|/)lost-password(?:/|$)#', $path ) ) {
		return true;
	}

	$account_page_id = intval( get_option( 'woocommerce_myaccount_page_id', '0' ), 10 );
	if ( $account_page_id > 0 ) {
		if ( isset( $query_vars['page_id'] ) && intval( $query_vars['page_id'], 10 ) === $account_page_id ) {
			return true;
		}

		$account_permalink = (string) get_permalink( $account_page_id );
		$account_path      = strtolower( (string) wp_parse_url( $account_permalink, PHP_URL_PATH ) );
		$account_path      = '/' . trim( $account_path, '/' ) . '/';
		if ( '/' !== $account_path && 0 === strpos( $path, $account_path ) ) {
			return true;
		}
	}

	$lost_password_endpoint = sanitize_key( (string) get_option( 'woocommerce_myaccount_lost_password_endpoint', 'lost-password' ) );
	if ( '' === $lost_password_endpoint ) {
		$lost_password_endpoint = 'lost-password';
	}
	if ( isset( $query_vars[ $lost_password_endpoint ] ) ) {
		return true;
	}

	return 1 === preg_match( '#(?:^|/)' . preg_quote( $lost_password_endpoint, '#' ) . '(?:/|$)#', $path );
}

function asfw_get_woocommerce_login_protection() {
	$plugin = AntiSpamForWordPressPlugin::$instance;
	$mode   = $plugin->get_integration_woocommerce_login();
	if ( ! empty( $mode ) ) {
		return array( $mode, 'woocommerce:login' );
	}

	$mode = $plugin->get_integration_wordpress_login();

	return array( $mode, 'wordpress:login' );
}

function asfw_get_woocommerce_reset_password_protection() {
	$plugin = AntiSpamForWordPressPlugin::$instance;
	$mode   = $plugin->get_integration_woocommerce_reset_password();
	if ( ! empty( $mode ) ) {
		return array( $mode, 'woocommerce:reset-password' );
	}

	$mode = $plugin->get_integration_wordpress_reset_password();

	return array( $mode, 'wordpress:reset-password' );
}

add_action(
	'woocommerce_register_form',
	function () {
		$plugin = AntiSpamForWordPressPlugin::$instance;
		$mode   = $plugin->get_integration_woocommerce_register();
		if ( ! empty( $mode ) ) {
			asfw_render_woocommerce_widget( $mode, 'woocommerce:register', 'asfw_register' );
		}
	},
	10,
	0
);

add_action(
	'woocommerce_register_post',
	function ( $user_login, $user_email, $errors ) {
		$plugin = AntiSpamForWordPressPlugin::$instance;
		$mode   = $plugin->get_integration_woocommerce_register();
		if ( ! empty( $mode ) ) {
			if ( asfw_verify_posted_widget( 'woocommerce:register', 'asfw_register' ) === false ) {
				return $errors->add(
					'asfw_error_message',
					esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
				);
			}
		}

		return $errors;
	},
	10,
	3
);

add_action(
	'woocommerce_login_form',
	function () {
		list($mode, $context) = asfw_get_woocommerce_login_protection();
		if ( ! empty( $mode ) ) {
			asfw_render_woocommerce_widget( $mode, $context );
		}

		echo asfw_render_context_guards( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guard markup is sanitized in helper.
	},
	10,
	0
);

add_filter(
	'authenticate',
	function ( $user ) {
		if ( $user instanceof WP_Error ) {
			return $user;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return $user;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $user;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Request shape detection is required to scope guards to WooCommerce account routes.
		if ( ! isset( $_POST['woocommerce-login-nonce'] ) && ! asfw_is_woocommerce_account_request() ) {
			return $user;
		}
		list($mode, $context) = asfw_get_woocommerce_login_protection();
		$guard_result = asfw_validate_context_guards( $context );
		if ( $guard_result instanceof WP_Error ) {
			return new WP_Error(
				'asfw-error',
				esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
			);
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This nonce field is read only to detect the WooCommerce login flow.
		if ( ! isset( $_POST['woocommerce-login-nonce'] ) ) {
			return $user;
		}

		if ( ! empty( $mode ) ) {
			if ( asfw_verify_posted_widget( $context ) === false ) {
				return new WP_Error(
					'asfw-error',
					esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
				);
			}
		}

		return $user;
	},
	20,
	1
);

add_action(
	'woocommerce_lostpassword_form',
	function () {
		list($mode, $context) = asfw_get_woocommerce_reset_password_protection();
		if ( ! empty( $mode ) ) {
			asfw_render_woocommerce_widget( $mode, $context );
		}

		echo asfw_render_context_guards( $context ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guard markup is sanitized in helper.
	},
	10,
	0
);

add_filter(
	'lostpassword_post',
	function ( $errors ) {
		if ( is_user_logged_in() ) {
			return $errors;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Request shape detection is required to scope guards to WooCommerce account routes.
		if ( ! isset( $_POST['woocommerce-lost-password-nonce'] ) && ! asfw_is_woocommerce_account_request() ) {
			return $errors;
		}
		list($mode, $context) = asfw_get_woocommerce_reset_password_protection();
		$guard_result = asfw_validate_context_guards( $context );
		if ( $guard_result instanceof WP_Error ) {
			$errors->add(
				'asfw_error_message',
				esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
			);
			return $errors;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This nonce field is read only to detect the WooCommerce lost-password flow.
		if ( ! isset( $_POST['woocommerce-lost-password-nonce'] ) ) {
			return $errors;
		}

		if ( ! empty( $mode ) ) {
			if ( asfw_verify_posted_widget( $context ) === false ) {
				$errors->add(
					'asfw_error_message',
					esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
				);
			}
		}

		return $errors;
	},
	10,
	1
);

function asfw_render_woocommerce_widget( $mode, $context, $name = null ) {
	echo wp_kses( asfw_render_widget_markup( $mode, $context, $name ), AntiSpamForWordPressPlugin::$html_allowed_tags );
}
