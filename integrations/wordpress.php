<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'register_form',
	function () {
		$plugin = asfw_plugin_instance();
		$mode   = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wordpress_register() : '';
		if ( ! empty( $mode ) ) {
			asfw_render_wordpress_widget( $mode, 'wordpress:register', 'asfw_register' );
		}

		echo asfw_render_context_guards( 'wordpress:register' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guard markup is sanitized in helper.
	},
	10,
	0
);

add_action(
	'register_post',
	function ( $user_login, $user_email, $errors ) {
		$guard_result = asfw_validate_context_guards( 'wordpress:register' );
		if ( $guard_result instanceof WP_Error ) {
			return $errors->add(
				'asfw_error_message',
				'<strong>' . esc_html__( 'Error', 'anti-spam-for-wordpress' ) . '</strong> : ' . esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
			);
		}

		$plugin = asfw_plugin_instance();
		$mode   = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wordpress_register() : '';
		if ( ! empty( $mode ) ) {
			if ( asfw_verify_posted_widget( 'wordpress:register', 'asfw_register' ) === false ) {
				return $errors->add(
					'asfw_error_message',
					'<strong>' . esc_html__( 'Error', 'anti-spam-for-wordpress' ) . '</strong> : ' . esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
				);
			}
		}

		return $errors;
	},
	10,
	3
);

add_action(
	'login_form',
	function () {
		$plugin = asfw_plugin_instance();
		$mode   = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wordpress_login() : '';
		if ( ! empty( $mode ) ) {
			asfw_render_wordpress_widget( $mode, 'wordpress:login' );
		}

		echo asfw_render_context_guards( 'wordpress:login' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guard markup is sanitized in helper.
	},
	10,
	0
);

add_filter(
	'authenticate',
	function ( $user, $username, $password ) {
		unset( $username, $password );

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
			if (
				asfw_plugin_active( 'woocommerce' )
				&& function_exists( 'asfw_is_woocommerce_account_request' )
				&& asfw_is_woocommerce_account_request()
				&& isset( $_POST['woocommerce-login-nonce'] )
			) {
				$nonce_valid = function_exists( 'wp_verify_nonce' )
					&& wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['woocommerce-login-nonce'] ) ), 'woocommerce-login' );
				if ( $nonce_valid ) {
					return $user;
				}
			}

		$plugin = asfw_plugin_instance();
		$guard_result = asfw_validate_context_guards( 'wordpress:login' );
		if ( $guard_result instanceof WP_Error ) {
			return new WP_Error(
				'asfw-error',
				'<strong>' . esc_html__( 'Error', 'anti-spam-for-wordpress' ) . '</strong> : ' . esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
			);
		}

		$mode   = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wordpress_login() : '';
		if ( ! empty( $mode ) ) {
			if ( asfw_verify_posted_widget( 'wordpress:login' ) === false ) {
				return new WP_Error(
					'asfw-error',
					'<strong>' . esc_html__( 'Error', 'anti-spam-for-wordpress' ) . '</strong> : ' . esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
				);
			}
		}

		return $user;
	},
	20,
	3
);

add_action(
	'lostpassword_form',
	function () {
		$plugin = asfw_plugin_instance();
		$mode   = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wordpress_reset_password() : '';
		if ( ! empty( $mode ) ) {
			asfw_render_wordpress_widget( $mode, 'wordpress:reset-password' );
		}

		echo asfw_render_context_guards( 'wordpress:reset-password' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guard markup is sanitized in helper.
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
			if (
				asfw_plugin_active( 'woocommerce' )
				&& function_exists( 'asfw_is_woocommerce_account_request' )
				&& asfw_is_woocommerce_account_request()
				&& isset( $_POST['woocommerce-lost-password-nonce'] )
			) {
				$nonce_valid = function_exists( 'wp_verify_nonce' )
					&& wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['woocommerce-lost-password-nonce'] ) ), 'woocommerce-lost-password' );
				if ( $nonce_valid ) {
					return $errors;
				}
			}

		$plugin = asfw_plugin_instance();
		$guard_result = asfw_validate_context_guards( 'wordpress:reset-password' );
		if ( $guard_result instanceof WP_Error ) {
			$errors->add(
				'asfw_error_message',
				'<strong>' . esc_html__( 'Error', 'anti-spam-for-wordpress' ) . '</strong> : ' . esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
			);
			return $errors;
		}

		$mode   = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wordpress_reset_password() : '';
		if ( ! empty( $mode ) ) {
			if ( asfw_verify_posted_widget( 'wordpress:reset-password' ) === false ) {
				$errors->add(
					'asfw_error_message',
					'<strong>' . esc_html__( 'Error', 'anti-spam-for-wordpress' ) . '</strong> : ' . esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
				);
			}
		}

		return $errors;
	},
	10,
	1
);

add_action(
	'comment_form_after_fields',
	function () {
		$plugin = asfw_plugin_instance();
		$mode   = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wordpress_comments() : '';
		if ( ! empty( $mode ) ) {
			asfw_render_wordpress_widget( $mode, 'wordpress:comments' );
		}

		echo asfw_render_context_guards( 'wordpress:comments' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guard markup is sanitized in helper.
	},
	10,
	0
);

add_action(
	'comment_form_logged_in_after',
	function () {
		$plugin = asfw_plugin_instance();
		$mode   = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wordpress_comments() : '';
		if ( ! empty( $mode ) ) {
			asfw_render_wordpress_widget( $mode, 'wordpress:comments' );
		}

		echo asfw_render_context_guards( 'wordpress:comments' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guard markup is sanitized in helper.
	},
	10,
	0
);

add_filter(
	'preprocess_comment',
	function ( $comment ) {
		if ( isset( $comment['comment_type'] ) && '' !== $comment['comment_type'] && 'comment' !== $comment['comment_type'] ) {
			return $comment;
		}
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return $comment;
		}

		$plugin           = asfw_plugin_instance();
		$wpdiscuz_mode    = $plugin instanceof AntiSpamForWordPressPlugin && asfw_plugin_active( 'wpdiscuz' ) ? $plugin->get_integration_wpdiscuz() : '';
		$wordpress_mode   = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wordpress_comments() : '';
		$posted_context   = asfw_get_posted_value( 'asfw_context' );
		$posted_signature = asfw_get_posted_value( 'asfw_context_sig' );
		$wpdiscuz_request = false;
		if ( $plugin instanceof AntiSpamForWordPressPlugin && '' !== $posted_context && '' !== $posted_signature ) {
			$normalized_posted_context = ASFW_Feature_Registry::normalize_context( $posted_context );
			if ( 'wpdiscuz:comments' === $normalized_posted_context ) {
				$expected_signature = $plugin->sign_widget_context( 'wpdiscuz:comments', 'asfw' );
				$wpdiscuz_request   = hash_equals( $expected_signature, $posted_signature );
			}
		}

		$mode             = $wpdiscuz_request ? $wpdiscuz_mode : $wordpress_mode;
		$guard_context    = $wpdiscuz_request ? 'wpdiscuz:comments' : 'wordpress:comments';
		$guard_result     = asfw_validate_context_guards( $guard_context );
		if ( $guard_result instanceof WP_Error ) {
			wp_die( '<strong>' . esc_html__( 'Error', 'anti-spam-for-wordpress' ) . '</strong> : ' . esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' ) );
		}
		if ( ! empty( $mode ) ) {
			$context = $guard_context;
			if ( asfw_verify_posted_widget( $context ) === false ) {
				wp_die( '<strong>' . esc_html__( 'Error', 'anti-spam-for-wordpress' ) . '</strong> : ' . esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' ) );
			}
		}

		return $comment;
	},
	10,
	1
);

function asfw_render_wordpress_widget( $mode, $context, $name = null ) {
	echo wp_kses( asfw_render_widget_markup( $mode, $context, $name ), AntiSpamForWordPressPlugin::$html_allowed_tags );
}
