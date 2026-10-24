<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Core REST inserts comments directly; preprocess_comment alone cannot guard it. */
final class ASFW_Comment_Requests {

	public static function register() {
		add_filter( 'rest_pre_insert_comment', array( __CLASS__, 'validate' ), PHP_INT_MAX, 2 );
	}

	public static function validate( $comment, $request ) {
		$plugin = asfw_plugin_instance();
		if ( $comment instanceof WP_Error || ! $plugin || $plugin->is_kill_switch_enabled() || ( is_user_logged_in() && current_user_can( 'moderate_comments' ) ) ) {
			return $comment;
		}
		$type = is_array( $comment ) ? ( $comment['comment_type'] ?? 'comment' ) : 'comment';
		if ( ! in_array( $type, array( '', 'comment', 'review' ), true ) ) {
			return $comment;
		}
		$fields = $request->get_json_params();
		if ( ! is_array( $fields ) ) {
			$fields = $request->get_body_params();
		}
		$fields = is_array( $fields ) ? array_filter(
			$fields,
			static function ( $key ) {
				return is_string( $key ) && ( 'asfw' === $key || 0 === strpos( $key, 'asfw_' ) );
			},
			ARRAY_FILTER_USE_KEY
		) : array();
		// Use core's resolved identity/content, never a client-supplied alias or
		// an email that differs from the authenticated comment author's account.
		$fields['email']   = is_array( $comment ) && is_string( $comment['comment_author_email'] ?? null ) ? $comment['comment_author_email'] : '';
		$fields['comment'] = is_array( $comment ) && is_string( $comment['comment_content'] ?? null ) ? $comment['comment_content'] : '';
		$valid             = '' !== $plugin->get_integration_wordpress_comments()
			? asfw_verify_submission( $fields, 'WordPress:comments' )
			: ! ( asfw_validate_context_guard_fields( 'WordPress:comments', $fields ) instanceof WP_Error );
		return $valid ? $comment : new WP_Error( 'asfw_comment_verification', __( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' ), array( 'status' => 422 ) );
	}
}
