<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function asfw_hash_value( $value, $purpose = 'generic' ) {
	$normalized = strtolower( trim( (string) $value ) );
	$secret     = function_exists( 'wp_salt' ) ? wp_salt( 'nonce' ) : 'asfw-salt';

	return hash_hmac( 'sha256', $purpose . '|' . $normalized, $secret );
}

function asfw_sanitize_event_detail_value( $value, $path = array() ) {
	if ( is_array( $value ) ) {
		$sanitized = array();
		foreach ( $value as $key => $child_value ) {
			$sanitized[ $key ] = asfw_sanitize_event_detail_value( $child_value, array_merge( $path, array( (string) $key ) ) );
		}

		return $sanitized;
	}

	if ( is_object( $value ) ) {
		return asfw_sanitize_event_detail_value( (array) $value, $path );
	}

	if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
		return $value;
	}

	$text = trim( (string) $value );
	if ( '' === $text ) {
		return '';
	}

	$joined_path = strtolower( implode( '.', $path ) );
	$sensitive   = array( 'email', 'ip', 'user_agent', 'useragent', 'ua', 'address', 'phone', 'token', 'secret', 'salt', 'signature', 'challenge_id', 'payload', 'fingerprint' );
	foreach ( $sensitive as $needle ) {
		if ( false !== strpos( $joined_path, $needle ) ) {
			return asfw_hash_value( $text, $joined_path );
		}
	}

	if ( preg_match( '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', $text ) ) {
		return asfw_hash_value( $text, 'email' );
	}

	if ( preg_match( '/\b(?:\d{1,3}\.){3}\d{1,3}\b/', $text ) ) {
		return asfw_hash_value( $text, 'ip' );
	}

	if ( strlen( $text ) > 500 ) {
		$text = substr( $text, 0, 500 );
	}

	return $text;
}

function asfw_sanitize_event_details( $details ) {
	return asfw_sanitize_event_detail_value( $details );
}
