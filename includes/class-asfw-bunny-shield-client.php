<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-asfw-bunny-list-content.php';

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
			'method'              => strtoupper( (string) $method ),
			'timeout'             => 5,
			'redirection'         => 0,
			'limit_response_size' => 8 * 1024 * 1024 + 1,
			'headers'             => array(
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
		$decoded     = $this->decode_body( $raw_body );

		if ( $status_code < 200 || $status_code >= 300 || ( is_array( $decoded ) && isset( $decoded['error']['success'] ) && false === $decoded['error']['success'] ) ) {
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

		if ( ! is_array( $decoded ) || strlen( $raw_body ) > 8 * 1024 * 1024 ) {
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
		return $this->mutate_entry( $list_id, $ip, true );
	}

	public function remove_entry( string $list_id, string $entry_id ) {
		return $this->mutate_entry( $list_id, $entry_id, false );
	}

	/** Legacy entry helpers use the same lease as the control-plane module. */
	protected function mutate_entry( $list_id, $ip, $adding ) {
		$list_id  = max( 0, intval( $list_id, 10 ) );
		$identity = new ASFW_Client_Identity();
		$ip       = $identity->normalize_ip( $ip );
		if ( $list_id <= 0 || '' === $ip ) {
			return new WP_Error( 'asfw_bunny_invalid_entry', __( 'Invalid Bunny access list entry.', 'anti-spam-for-wordpress' ) );
		}
		$store = new ASFW_Atomic_State_Store( is_multisite() && function_exists( 'get_main_site_id' ) ? get_main_site_id() : null );
		$key   = 'bunny:zone:' . $this->get_shield_zone_id() . ':mutation';
		$lease = $store->acquire_lease( $key, 60 );
		if ( is_wp_error( $lease ) ) {
			return $lease;
		}
		if ( ! is_array( $lease ) ) {
			return new WP_Error( 'asfw_bunny_busy', __( 'Bunny Shield synchronization is busy. Please try again.', 'anti-spam-for-wordpress' ) );
		}
		try {
			$current = $this->get_access_list( $list_id );
			if ( is_wp_error( $current ) ) {
				return $current;
			}
			$payload = isset( $current['body']['data'] ) && is_array( $current['body']['data'] ) ? $current['body']['data'] : $current['body'];
			if ( ! isset( $payload['id'], $payload['content'] ) || $list_id !== $payload['id'] || ! is_string( $payload['content'] )
				|| ( isset( $payload['type'] ) && ! in_array( $payload['type'], array( 0, '0' ), true ) ) ) {
				return new WP_Error( 'asfw_bunny_invalid_response', __( 'Bunny Shield returned an invalid access list.', 'anti-spam-for-wordpress' ) );
			}
			$parsed = ASFW_Bunny_List_Content::parse( $payload['content'], array( $identity, 'normalize_ip' ) );
			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}
			$entries = array();
			foreach ( $parsed as $entry ) {
				if ( $adding || $entry !== $ip ) {
					$entries[ $entry ] = true;
				}
			}
			if ( $adding ) {
				$entries[ $ip ] = true;
			}
			$content = implode( "\n", array_keys( $entries ) );
			$checked = ASFW_Bunny_List_Content::parse( $content, array( $identity, 'normalize_ip' ) );
			if ( is_wp_error( $checked ) ) {
				return $checked;
			}
			$owner = $store->read( $key );
			if ( ! is_array( $owner ) || $owner['expires_at'] <= time() + 6 || ! hash_equals( $lease['raw'], $owner['raw'] ) ) {
				return new WP_Error( 'asfw_bunny_busy', __( 'Bunny Shield synchronization is busy. Please try again.', 'anti-spam-for-wordpress' ) );
			}
			$result = $this->update_access_list( $list_id, $content );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$updated = isset( $result['body']['data'] ) && is_array( $result['body']['data'] ) ? $result['body']['data'] : $result['body'];
			if ( ! isset( $updated['id'], $updated['content'] ) || $list_id !== $updated['id'] || $content !== $updated['content'] ) {
				return new WP_Error( 'asfw_bunny_invalid_response', __( 'Bunny Shield returned an invalid access list.', 'anti-spam-for-wordpress' ) );
			}
			return $result;
		} finally {
			$store->release_lease( $key, $lease );
		}
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
