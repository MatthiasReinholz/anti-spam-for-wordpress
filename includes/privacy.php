<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function asfw_hash_value( $value, $purpose = 'generic' ) {
	$normalized = strtolower( trim( (string) $value ) );
	$secret     = function_exists( 'wp_salt' ) ? wp_salt( 'nonce' ) : 'asfw-salt';

	return hash_hmac( 'sha256', $purpose . '|' . $normalized, $secret );
}

/** Bound work as well as output for extension-provided, potentially recursive details. */
function asfw_sanitize_event_detail_value( $value, $path = array(), &$budget = null ) {
	if ( null === $budget ) {
		$budget = 200;
	}
	if ( --$budget < 0 || count( $path ) > 8 ) {
		return '[truncated]';
	}
	if ( is_array( $value ) || is_object( $value ) ) {
		$sanitized = array();
		foreach ( (array) $value as $key => $child_value ) {
			if ( $budget <= 0 ) {
				$sanitized['_truncated'] = true;
				break;
			}
			if ( strlen( (string) $key ) > 8192 ) {
				$sanitized['_truncated'] = true;
				--$budget;
				continue;
			}
			// Identity can occur in extension-supplied keys as well as values.
			$safe_key               = asfw_private_detail_text( (string) $key, array( 'key' ) );
			$sanitized[ $safe_key ] = asfw_sanitize_event_detail_value( $child_value, array_merge( $path, array( (string) $key ) ), $budget );
		}
		return $sanitized;
	}
	if ( ( is_int( $value ) || is_float( $value ) ) && asfw_event_detail_path_is_sensitive( $path ) ) {
		return asfw_private_detail_text( (string) $value, $path );
	}
	if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
		return $value;
	}
	return asfw_private_detail_text( (string) $value, $path );
}

function asfw_event_detail_path_is_sensitive( array $path ) {
	$joined_path = strtolower( implode( '.', $path ) );
	foreach ( array( 'email', 'ip', 'user_agent', 'useragent', 'ua', 'address', 'phone', 'token', 'secret', 'salt', 'signature', 'challenge_id', 'payload', 'fingerprint' ) as $needle ) {
		if ( false !== strpos( $joined_path, $needle ) ) {
			return true;
		}
	}
	foreach ( $path as $segment ) {
		$normalized_key = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', (string) $segment ) );
		// Include common form fields and HTTP headers without treating counters as credentials.
		if ( in_array( $normalized_key, array( 'pwd', 'pass', 'userpass', 'credentials', 'passwords' ), true )
			|| preg_match( '/(?:password|passwd|authorization|cookie|apikey|accesskey|privatekey)$/', $normalized_key ) ) {
			return true;
		}
	}
	return false;
}

function asfw_private_detail_text( $text, array $path ) {
	// Do not scan or retain arbitrarily large extension-provided strings.
	if ( strlen( $text ) > 8192 ) {
		return '[truncated]';
	}
	$text = trim( $text );
	if ( '' === $text ) {
		return '';
	}
	if ( asfw_event_detail_path_is_sensitive( $path ) ) {
		return asfw_hash_value( $text, strtolower( implode( '.', $path ) ) );
	}
	if ( preg_match( '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $text ) ) {
		return asfw_hash_value( $text, 'email' );
	}
	if ( preg_match( '/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $text ) ) {
		return asfw_hash_value( $text, 'ip' );
	}
	preg_match_all( '/[0-9a-f]*:[0-9a-f:.]+/i', $text, $candidates );
	foreach ( $candidates[0] as $candidate ) {
		if ( filter_var( rtrim( $candidate, '.' ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return asfw_hash_value( $text, 'ip' );
		}
	}
	// wp_check_invalid_utf8 avoids cutting a multi-byte character in half.
	return wp_check_invalid_utf8( substr( $text, 0, 500 ), true );
}

function asfw_sanitize_event_details( $details ) {
	if ( is_string( $details ) ) {
		if ( strlen( $details ) > 65535 ) {
			return array( '_truncated' => true );
		}
		$decoded = json_decode( $details, true, 16 );
		$trimmed = ltrim( $details );
		if ( JSON_ERROR_NONE !== json_last_error() && ( JSON_ERROR_DEPTH === json_last_error() || str_starts_with( $trimmed, '{' ) || str_starts_with( $trimmed, '[' ) ) ) {
			// Failed structured input must not fall back to unsanitized serialized secrets.
			return array( '_truncated' => true );
		}
		$details = is_array( $decoded ) ? $decoded : array( 'message' => $details );
	}
	return asfw_sanitize_event_detail_value( $details );
}

function asfw_register_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) || ! class_exists( 'ASFW_Privacy_Policy_Text', false ) ) {
		return;
	}

	$payload = ASFW_Privacy_Policy_Text::payload();
	$text    = isset( $payload['text'] ) ? trim( (string) $payload['text'] ) : '';
	if ( '' === $text ) {
		return;
	}

	$paragraphs = preg_split( "/\n\s*\n/", $text );
	if ( ! is_array( $paragraphs ) || empty( $paragraphs ) ) {
		return;
	}

	$title = array_shift( $paragraphs );
	$html  = '<h2>' . esc_html( $title ) . '</h2>';
	foreach ( $paragraphs as $paragraph ) {
		$paragraph = trim( (string) $paragraph );
		if ( '' === $paragraph ) {
			continue;
		}
		$html .= '<p>' . esc_html( $paragraph ) . '</p>';
	}

	wp_add_privacy_policy_content( __( 'Anti Spam for WordPress', 'anti-spam-for-wordpress' ), $html );
}

if ( is_admin() ) {
	add_action( 'admin_init', 'asfw_register_privacy_policy_content' );
}
