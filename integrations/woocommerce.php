<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- This nonce field is read only to detect the WooCommerce login flow.
		if ( ! isset( $_POST['woocommerce-login-nonce'] ) ) {
			return $user;
		}

		list($mode, $context) = asfw_get_woocommerce_login_protection();
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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This nonce field is read only to detect the WooCommerce lost-password flow.
		if ( ! isset( $_POST['woocommerce-lost-password-nonce'] ) ) {
			return $errors;
		}

		list($mode, $context) = asfw_get_woocommerce_reset_password_protection();
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
