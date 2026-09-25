<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bound remote list parsing before allocating or replacing the complete list. */
final class ASFW_Bunny_List_Content {

	const MAX_BYTES    = 8 * 1024 * 1024;
	const MAX_SEGMENTS = 400000;
	const MAX_ENTRIES  = 200000;

	/** @return array|WP_Error Normalized unique IPs in their original order. */
	public static function parse( $content, callable $normalize_ip ) {
		if ( ! is_string( $content ) ) {
			return new WP_Error( 'asfw_bunny_invalid_response', __( 'Bunny Shield returned an invalid access list.', 'anti-spam-for-wordpress' ) );
		}
		if ( strlen( $content ) > self::MAX_BYTES ) {
			return self::limit_error();
		}
		$content  = trim( $content );
		$size     = strlen( $content );
		$offset   = 0;
		$segments = 0;
		$entries  = array();
		while ( $offset < $size ) {
			if ( ++$segments > self::MAX_SEGMENTS ) {
				return self::limit_error();
			}
			$length  = strcspn( $content, "\r\n,", $offset );
			$entry   = substr( $content, $offset, $length );
			$offset += $length;
			if ( $offset < $size && "\r" === $content[ $offset ] && $offset + 1 < $size && "\n" === $content[ $offset + 1 ] ) {
				++$offset;
			}
			++$offset;
			if ( '' === $entry ) {
				continue;
			}
			$entry = call_user_func( $normalize_ip, $entry );
			if ( ! is_string( $entry ) || '' === $entry ) {
				return new WP_Error( 'asfw_bunny_invalid_response', __( 'Bunny Shield returned an invalid access list.', 'anti-spam-for-wordpress' ) );
			}
			$entries[ $entry ] = true;
			if ( count( $entries ) > self::MAX_ENTRIES ) {
				return self::limit_error();
			}
		}
		return array_keys( $entries );
	}

	private static function limit_error() {
		return new WP_Error( 'asfw_bunny_list_too_large', __( 'Bunny Shield returned an access list that exceeds safe processing limits.', 'anti-spam-for-wordpress' ) );
	}
}
