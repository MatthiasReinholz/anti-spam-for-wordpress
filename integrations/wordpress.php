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
			asfw_render_wordpress_widget( $mode, 'WordPress:register', 'asfw_register' );
		}

		echo asfw_render_context_guards( 'WordPress:register' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guard markup is sanitized in helper.
	},
	10,
	0
);

add_action(
	'register_post',
	function ( $user_login, $user_email, $errors ) {
		$guard_result = asfw_validate_context_guards( 'WordPress:register' );
		if ( $guard_result instanceof WP_Error ) {
			return $errors->add(
				'asfw_error_message',
				'<strong>' . esc_html__( 'Error', 'anti-spam-for-wordpress' ) . '</strong> : ' . esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
			);
		}

		$plugin = asfw_plugin_instance();
		$mode   = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wordpress_register() : '';
		if ( ! empty( $mode ) ) {
			if ( asfw_verify_posted_widget( 'WordPress:register', 'asfw_register' ) === false ) {
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
			asfw_render_wordpress_widget( $mode, 'WordPress:login' );
		}

		echo asfw_render_context_guards( 'WordPress:login' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guard markup is sanitized in helper.
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

		$plugin       = asfw_plugin_instance();
		$guard_result = asfw_validate_context_guards( 'WordPress:login' );
		if ( $guard_result instanceof WP_Error ) {
			return new WP_Error(
				'asfw-error',
				'<strong>' . esc_html__( 'Error', 'anti-spam-for-wordpress' ) . '</strong> : ' . esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
			);
		}

		$mode = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wordpress_login() : '';
		if ( ! empty( $mode ) ) {
			if ( asfw_verify_posted_widget( 'WordPress:login' ) === false ) {
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
			asfw_render_wordpress_widget( $mode, 'WordPress:reset-password' );
		}

		echo asfw_render_context_guards( 'WordPress:reset-password' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guard markup is sanitized in helper.
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

		$plugin       = asfw_plugin_instance();
		$guard_result = asfw_validate_context_guards( 'WordPress:reset-password' );
		if ( $guard_result instanceof WP_Error ) {
			$errors->add(
				'asfw_error_message',
				'<strong>' . esc_html__( 'Error', 'anti-spam-for-wordpress' ) . '</strong> : ' . esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
			);
			return $errors;
		}

		$mode = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wordpress_reset_password() : '';
		if ( ! empty( $mode ) ) {
			if ( asfw_verify_posted_widget( 'WordPress:reset-password' ) === false ) {
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
			asfw_render_wordpress_widget( $mode, 'WordPress:comments' );
		}

		echo asfw_render_context_guards( 'WordPress:comments' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guard markup is sanitized in helper.
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
			asfw_render_wordpress_widget( $mode, 'WordPress:comments' );
		}

		echo asfw_render_context_guards( 'WordPress:comments' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guard markup is sanitized in helper.
	},
	10,
	0
);

/**
 * Mark a comment submitted through WordPress' native comment form handler.
 *
 * The preprocess_comment filter is also used by wp_new_comment(), importers,
 * REST clients, and other programmatic integrations. Limiting the browser
 * challenge to submissions that passed through wp_handle_comment_submission()
 * avoids breaking those trusted creation paths.
 */
function asfw_mark_native_comment_submission( $comment_post_id ) {
	if ( ! isset( $GLOBALS['asfw_native_comment_submission_posts'] ) || ! is_array( $GLOBALS['asfw_native_comment_submission_posts'] ) ) {
		$GLOBALS['asfw_native_comment_submission_posts'] = array();
	}

	$comment_post_id = (int) $comment_post_id;
	$GLOBALS['asfw_native_comment_submission_posts'][ $comment_post_id ] = isset( $GLOBALS['asfw_native_comment_submission_posts'][ $comment_post_id ] )
		? (int) $GLOBALS['asfw_native_comment_submission_posts'][ $comment_post_id ] + 1
		: 1;
}

/**
 * Consume one native comment submission marker.
 *
 * @param int $comment_post_id Comment post ID passed to preprocess_comment.
 * @return bool Whether the current comment came from the native form handler.
 */
function asfw_consume_native_comment_submission_marker( $comment_post_id ) {
	$pending_posts   = isset( $GLOBALS['asfw_native_comment_submission_posts'] ) && is_array( $GLOBALS['asfw_native_comment_submission_posts'] )
		? $GLOBALS['asfw_native_comment_submission_posts']
		: array();
	$comment_post_id = (int) $comment_post_id;
	$pending_count   = isset( $pending_posts[ $comment_post_id ] ) ? (int) $pending_posts[ $comment_post_id ] : 0;
	if ( $pending_count < 1 ) {
		return false;
	}

	if ( 1 === $pending_count ) {
		unset( $pending_posts[ $comment_post_id ] );
	} else {
		$pending_posts[ $comment_post_id ] = $pending_count - 1;
	}
	$GLOBALS['asfw_native_comment_submission_posts'] = $pending_posts;
	return true;
}

/**
 * Resolve a signed comment-form context against the currently enabled policy.
 *
 * Context signatures identify the renderer that produced a submission; they
 * are not authorization tokens. Rechecking the active policy prevents stale
 * form markup from selecting a disabled integration policy.
 *
 * @param AntiSpamForWordPressPlugin $plugin Plugin instance.
 * @return string Normalized context, or an empty string when unrecognized.
 */
function asfw_resolve_signed_comment_context( $plugin ) {
	$posted_context   = asfw_get_posted_value( 'asfw_context' );
	$posted_signature = asfw_get_posted_value( 'asfw_context_sig' );
	if ( '' === $posted_context || '' === $posted_signature ) {
		return '';
	}

	$context = ASFW_Feature_Registry::normalize_context( $posted_context );
	if ( ! in_array( $context, array( 'wordpress:comments', 'wpdiscuz:comments' ), true ) ) {
		return '';
	}
	if ( ! hash_equals( $plugin->sign_widget_context( $context, 'asfw' ), $posted_signature ) ) {
		return '';
	}

	if ( 'wpdiscuz:comments' === $context ) {
		$wpdiscuz_policy_enabled = asfw_plugin_active( 'wpdiscuz' )
			&& (
				'' !== $plugin->get_integration_wpdiscuz()
				|| ASFW_Feature_Registry::is_enabled( 'math_challenge', 'wpdiscuz:comments' )
				|| ASFW_Feature_Registry::is_enabled( 'submit_delay', 'wpdiscuz:comments' )
			);
		return $wpdiscuz_policy_enabled ? $context : 'wordpress:comments'; // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- Context identifiers are normalized lowercase values.
	}

	return $context;
}

add_action( 'pre_comment_on_post', 'asfw_mark_native_comment_submission', 10, 1 );

add_filter(
	'preprocess_comment',
	function ( $comment ) {
		$comment_post_id = isset( $comment['comment_post_ID'] ) ? (int) $comment['comment_post_ID'] : 0;
		$native_request  = asfw_consume_native_comment_submission_marker( $comment_post_id );
		if ( isset( $comment['comment_type'] ) && '' !== $comment['comment_type'] && 'comment' !== $comment['comment_type'] ) {
			return $comment;
		}
		if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return $comment;
		}

		$plugin         = asfw_plugin_instance();
		$signed_context = $plugin instanceof AntiSpamForWordPressPlugin ? asfw_resolve_signed_comment_context( $plugin ) : '';

		if ( ! $native_request && '' === $signed_context ) {
			$remote_request = ( defined( 'REST_REQUEST' ) && REST_REQUEST )
				|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
				|| wp_doing_ajax();
			if ( $remote_request && ! is_user_logged_in() ) {
				$native_request = true;
			} else {
				return $comment;
			}
		}
		$guard_context = 'wpdiscuz:comments' === $signed_context ? 'wpdiscuz:comments' : 'WordPress:comments';
		$mode          = $plugin instanceof AntiSpamForWordPressPlugin && 'wpdiscuz:comments' === $guard_context
			? $plugin->get_integration_wpdiscuz()
			: ( $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_wordpress_comments() : '' );
		$guard_result  = asfw_validate_context_guards( $guard_context );
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
