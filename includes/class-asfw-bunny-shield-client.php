<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_Bunny_Shield_Client {

	const BASE_URL = 'https://api.bunny.net';

	protected $api_key;

	protected $shield_zone_id;

	protected $user_agent;

	public function __construct( $api_key = '', $shield_zone_id = 0, $user_agent = null ) {
		$this->api_key        = trim( (string) $api_key );
		$this->shield_zone_id = max( 0, intval( $shield_zone_id, 10 ) );
		$this->user_agent     = null === $user_agent ? $this->default_user_agent() : trim( (string) $user_agent );
	}

	public function is_configured() {
		return '' !== $this->api_key && $this->shield_zone_id > 0;
	}

	public function get_user_agent() {
		return $this->user_agent;
	}

	public function get_api_key() {
		return $this->api_key;
	}

	public function get_shield_zone_id() {
		return $this->shield_zone_id;
	}

	protected function default_user_agent() {
		$version = defined( 'ASFW_VERSION' ) ? ASFW_VERSION : ( class_exists( 'AntiSpamForWordPressPlugin', false ) ? AntiSpamForWordPressPlugin::$version : '0.0.0' );

		return 'Anti Spam for WordPress/' . $version . '; Bunny Shield';
	}

	protected function build_url( $path, array $query = array() ) {
		$url = rtrim( self::BASE_URL, '/' ) . '/' . ltrim( (string) $path, '/' );
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		return $url;
	}

	protected function decode_body( $body ) {
		$body = trim( (string) $body );
		if ( '' === $body ) {
			return null;
		}

		$decoded = json_decode( $body, true );
		if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
			return $decoded;
		}

		return null;
	}

	protected function request( $method, $path, array $body = array(), $shield_zone_id = null ) {
		$api_key = $this->get_api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'asfw_bunny_missing_api_key', __( 'Bunny API key is missing.', 'anti-spam-for-wordpress' ) );
		}

		$zone_id = null === $shield_zone_id ? $this->get_shield_zone_id() : max( 0, intval( $shield_zone_id, 10 ) );
		if ( $zone_id <= 0 ) {
			return new WP_Error( 'asfw_bunny_missing_zone', __( 'Bunny Shield zone ID is missing.', 'anti-spam-for-wordpress' ) );
		}

		$args = array(
			'method'      => strtoupper( (string) $method ),
			'timeout'     => 5,
			'redirection' => 0,
			'headers'     => array(
				'AccessKey'    => $api_key,
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
				'User-Agent'   => $this->get_user_agent(),
			),
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $this->build_url( $path ), $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = function_exists( 'wp_remote_retrieve_response_code' ) ? intval( wp_remote_retrieve_response_code( $response ), 10 ) : 0;
		$raw_body    = function_exists( 'wp_remote_retrieve_body' ) ? (string) wp_remote_retrieve_body( $response ) : '';
			$decoded = $this->decode_body( $raw_body );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$message = __( 'Bunny Shield request failed.', 'anti-spam-for-wordpress' );
			if ( is_array( $decoded ) && isset( $decoded['error']['message'] ) && is_string( $decoded['error']['message'] ) ) {
				$message = $decoded['error']['message'];
			}

			return new WP_Error(
				'asfw_bunny_http_error',
				$message,
				array(
					'status' => $status_code,
					'body'   => is_array( $decoded ) ? $decoded : $raw_body,
					'path'   => $path,
				)
			);
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'asfw_bunny_invalid_response',
				__( 'Bunny Shield returned an invalid response body.', 'anti-spam-for-wordpress' ),
				array(
					'status' => $status_code,
					'body'   => $raw_body,
					'path'   => $path,
				)
			);
		}

		return array(
			'status' => $status_code,
			'body'   => $decoded,
			'raw'    => $raw_body,
		);
	}

	public function list_access_lists( $shield_zone_id = null ) {
		$zone_id = null === $shield_zone_id ? $this->get_shield_zone_id() : max( 0, intval( $shield_zone_id, 10 ) );

		return $this->request( 'GET', '/shield/shield-zone/' . $zone_id . '/access-lists', array(), $zone_id );
	}

	public function ping() {
		$zone_id = $this->get_shield_zone_id();
		if ( $zone_id <= 0 ) {
			return new WP_Error( 'asfw_bunny_missing_zone', __( 'Bunny Shield zone ID is missing.', 'anti-spam-for-wordpress' ) );
		}

		return $this->list_access_lists( $zone_id );
	}

	public function list_zones() {
		return $this->request( 'GET', '/shield/shield-zone/' . $this->get_shield_zone_id(), array(), $this->get_shield_zone_id() );
	}

	public function add_entry( string $list_id, string $ip, string $action, int $ttl_minutes, string $description ) {
		unset( $action, $ttl_minutes, $description );
		$list_id = max( 0, intval( $list_id, 10 ) );
		if ( $list_id <= 0 ) {
			return new WP_Error( 'asfw_bunny_missing_list_id', __( 'Bunny access list ID is missing.', 'anti-spam-for-wordpress' ) );
		}

		$current = $this->get_access_list( $list_id );
		if ( is_wp_error( $current ) ) {
			return $current;
		}

		$payload = isset( $current['body']['data'] ) && is_array( $current['body']['data'] ) ? $current['body']['data'] : ( isset( $current['body'] ) && is_array( $current['body'] ) ? $current['body'] : array() );
		$content = isset( $payload['content'] ) ? (string) $payload['content'] : '';
		$entries = preg_split( '/[\r\n,]+/', trim( $content ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $entries ) ) {
			$entries = array();
		}
		$entries[] = trim( $ip );
		$entries   = array_values( array_unique( array_filter( $entries ) ) );

		return $this->update_access_list( $list_id, implode( "\n", $entries ), null, isset( $payload['name'] ) ? (string) $payload['name'] : '' );
	}

	public function remove_entry( string $list_id, string $entry_id ) {
		$list_id = max( 0, intval( $list_id, 10 ) );
		$ip      = trim( (string) $entry_id );
		if ( $list_id <= 0 || '' === $ip ) {
			return new WP_Error( 'asfw_bunny_invalid_remove_args', __( 'Invalid Bunny revoke request.', 'anti-spam-for-wordpress' ) );
		}

		$current = $this->get_access_list( $list_id );
		if ( is_wp_error( $current ) ) {
			return $current;
		}

		$payload = isset( $current['body']['data'] ) && is_array( $current['body']['data'] ) ? $current['body']['data'] : ( isset( $current['body'] ) && is_array( $current['body'] ) ? $current['body'] : array() );
		$content = isset( $payload['content'] ) ? (string) $payload['content'] : '';
		$entries = preg_split( '/[\r\n,]+/', trim( $content ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $entries ) ) {
			$entries = array();
		}
		$entries = array_values(
			array_filter(
				$entries,
				static function ( $entry ) use ( $ip ) {
					return trim( (string) $entry ) !== $ip;
				}
			)
		);

		return $this->update_access_list( $list_id, implode( "\n", $entries ), null, isset( $payload['name'] ) ? (string) $payload['name'] : '' );
	}

	public function get_access_list( $list_id, $shield_zone_id = null ) {
		$list_id = max( 0, intval( $list_id, 10 ) );
		$zone_id = null === $shield_zone_id ? $this->get_shield_zone_id() : max( 0, intval( $shield_zone_id, 10 ) );

		return $this->request( 'GET', '/shield/shield-zone/' . $zone_id . '/access-lists/' . $list_id, array(), $zone_id );
	}

	public function create_access_list( $name, $content, $shield_zone_id = null, $description = '' ) {
		$zone_id = null === $shield_zone_id ? $this->get_shield_zone_id() : max( 0, intval( $shield_zone_id, 10 ) );
		$content = (string) $content;

		return $this->request(
			'POST',
			'/shield/shield-zone/' . $zone_id . '/access-lists',
			array(
				'name'        => (string) $name,
				'description' => (string) $description,
				'type'        => 0,
				'content'     => $content,
				'checksum'    => hash( 'sha256', $content ),
			),
			$zone_id
		);
	}

	public function update_access_list( $list_id, $content, $shield_zone_id = null, $name = null ) {
		$list_id = max( 0, intval( $list_id, 10 ) );
		$zone_id = null === $shield_zone_id ? $this->get_shield_zone_id() : max( 0, intval( $shield_zone_id, 10 ) );
		$content = (string) $content;

		$body = array(
			'content'  => $content,
			'checksum' => hash( 'sha256', $content ),
		);

		if ( null !== $name && '' !== (string) $name ) {
			$body['name'] = (string) $name;
		}

		return $this->request(
			'PATCH',
			'/shield/shield-zone/' . $zone_id . '/access-lists/' . $list_id,
			$body,
			$zone_id
		);
	}

	public function delete_access_list( $list_id, $shield_zone_id = null ) {
		$list_id = max( 0, intval( $list_id, 10 ) );
		$zone_id = null === $shield_zone_id ? $this->get_shield_zone_id() : max( 0, intval( $shield_zone_id, 10 ) );

		return $this->request( 'DELETE', '/shield/shield-zone/' . $zone_id . '/access-lists/' . $list_id, array(), $zone_id );
	}
}
