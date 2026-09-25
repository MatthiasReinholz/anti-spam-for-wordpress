<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-asfw-bunny-list-content.php';

class ASFW_Bunny_Shield_Module {

	const LIST_NAME = 'Anti Spam for WordPress';

	const LIST_DESCRIPTION = 'Automatically managed by Anti Spam for WordPress.';

	const LIST_TYPE = 0;

	const DEFAULT_THRESHOLD = 5;

	const DEFAULT_DEDUPE_WINDOW = 3600;

	const DEFAULT_BACKOFF_WINDOW = 60;

	const MAX_BACKOFF_WINDOW = 3600;

	const TRANSIENT_BACKOFF = 'asfw_bunny_backoff_until';

	const TRANSIENT_LAST_FAILURE = 'asfw_bunny_last_failure';

	protected $plugin;

	public function __construct( ?AntiSpamForWordPressPlugin $plugin = null ) {
		$this->plugin = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin : AntiSpamForWordPressPlugin::$instance;
	}

	public function register_hooks() {
		add_action( 'asfw_verify_result', array( $this, 'handle_verify_result' ), 20, 5 );
		add_action( 'asfw_rate_limited', array( $this, 'handle_rate_limited' ), 20, 3 );
	}

	protected function plugin() {
		if ( $this->plugin instanceof AntiSpamForWordPressPlugin ) {
			return $this->plugin;
		}

		return AntiSpamForWordPressPlugin::$instance;
	}

	protected function get_api_key() {
		$plugin = $this->plugin();
		if ( $plugin instanceof AntiSpamForWordPressPlugin ) {
			return $plugin->get_bunny_api_key();
		}

		return '';
	}

	protected function get_shield_zone_id() {
		$plugin = $this->plugin();
		if ( $plugin instanceof AntiSpamForWordPressPlugin ) {
			return $plugin->get_bunny_shield_zone_id();
		}

		return 0;
	}

	protected function get_access_list_id() {
		$plugin = $this->plugin();
		if ( $plugin instanceof AntiSpamForWordPressPlugin ) {
			return $plugin->get_bunny_access_list_id();
		}

		return 0;
	}

	protected function set_access_list_id( $list_id ) {
		$value = (string) max( 0, intval( $list_id, 10 ) );
		update_option( AntiSpamForWordPressPlugin::$option_bunny_access_list_id, $value );
		update_option( AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id, $value );
	}

	protected function is_enabled( $context = null ) {
		$plugin = $this->plugin();
		return $plugin instanceof AntiSpamForWordPressPlugin ? ASFW_Feature_Registry::is_enabled( 'bunny_shield', is_string( $context ) ? $context : null ) : false;
	}

	protected function get_mode() {
		$plugin = $this->plugin();
		return $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_bunny_mode() : 'off';
	}

	protected function get_action() {
		$plugin = $this->plugin();
		return $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_bunny_action() : 'block';
	}

	protected function background_enabled() {
		$plugin = $this->plugin();
		return $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->is_bunny_background_enabled() : false;
	}

	protected function is_dry_run() {
		$plugin = $this->plugin();
		return $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_bunny_dry_run() : true;
	}

	protected function is_fail_open() {
		$plugin = $this->plugin();
		return $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_bunny_fail_open() : true;
	}

	protected function get_threshold() {
		$plugin = $this->plugin();
		return $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_bunny_threshold() : self::DEFAULT_THRESHOLD;
	}

	protected function get_dedupe_window() {
		$plugin = $this->plugin();
		return $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_bunny_dedupe_window() : self::DEFAULT_DEDUPE_WINDOW;
	}

	protected function get_client() {
		return new ASFW_Bunny_Shield_Client( $this->get_api_key(), $this->get_shield_zone_id() );
	}

	protected function get_client_ip() {
		$plugin = $this->plugin();
		if ( ! $plugin instanceof AntiSpamForWordPressPlugin ) {
			return '';
		}

		$ip = $plugin->get_client_ip_address();
		if ( ! is_string( $ip ) ) {
			return '';
		}

		return trim( $ip );
	}

	protected function is_public_ip( $ip ) {
		return '' !== $ip && false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	protected function normalize_public_ip( $ip ) {
		$plugin = $this->plugin();
		if ( ! $plugin instanceof AntiSpamForWordPressPlugin ) {
			return '';
		}

		$normalized = $plugin->normalize_ip( $ip );
		if ( ! $this->is_public_ip( $normalized ) ) {
			return '';
		}

		return $normalized;
	}

	/** Serialize shared remote resources across sites in the same network. */
	protected function state_store() {
		return new ASFW_Atomic_State_Store( is_multisite() && function_exists( 'get_main_site_id' ) ? get_main_site_id() : null );
	}

	protected function state_key( $key ) {
		return 'bunny:zone:' . $this->get_shield_zone_id() . ':' . $key;
	}

	protected function update_state( $key, callable $update, $ttl ) {
		$store = $this->state_store();
		$key   = $this->state_key( $key );
		for ( $attempt = 0; $attempt < 5; ++$attempt ) {
			$current = $store->read( $key );
			if ( is_wp_error( $current ) ) {
				return $current;
			}
			$value   = $update( is_array( $current ) ? $current['value'] : array() );
			$written = null === $current ? $store->create( $key, $value, $ttl ) : $store->replace( $key, $current, $value, $ttl );
			if ( is_wp_error( $written ) ) {
				return $written;
			}
			if ( $written ) {
				return $value;
			}
		}
		return new WP_Error( 'asfw_bunny_busy', __( 'Bunny Shield synchronization is busy. Please try again.', 'anti-spam-for-wordpress' ) );
	}

	protected function read_state( $key ) {
		$current = $this->state_store()->read( $this->state_key( $key ) );
		return is_array( $current ) ? $current['value'] : $current;
	}

	protected function delete_state( $key ) {
		$store   = $this->state_store();
		$key     = $this->state_key( $key );
		$current = $store->read( $key );
		return is_array( $current ) ? $store->delete( $key, $current ) : $current;
	}

	private $remote_lease;

	protected function with_remote_lock( callable $operation ) {
		$store = $this->state_store();
		$key   = $this->state_key( 'mutation' );
		$lease = $store->acquire_lease( $key, 60 );
		if ( is_wp_error( $lease ) ) {
			return $lease;
		}
		if ( ! is_array( $lease ) ) {
			return new WP_Error( 'asfw_bunny_busy', __( 'Bunny Shield synchronization is busy. Please try again.', 'anti-spam-for-wordpress' ) );
		}
		$this->remote_lease = $lease;
		try {
			return $operation();
		} finally {
			$this->remote_lease = null;
			$store->release_lease( $key, $lease );
		}
	}

	protected function owns_remote_lease() {
		$current = $this->state_store()->read( $this->state_key( 'mutation' ) );
		return is_array( $this->remote_lease ) && is_array( $current )
			&& $current['expires_at'] > time() + 6
			&& hash_equals( $current['raw'], $this->remote_lease['raw'] );
	}

	protected function invalid_response() {
		return new WP_Error( 'asfw_bunny_invalid_response', __( 'Bunny Shield returned an invalid access list.', 'anti-spam-for-wordpress' ) );
	}

	protected function get_signal_key( $ip ) {
		return 'asfw_bunny_counter_' . md5( (string) $ip );
	}

	protected function get_dedupe_key( $ip, $dry_run = null ) {
		$dry_run = null === $dry_run ? $this->is_dry_run() : (bool) $dry_run;
		return ( $dry_run ? 'asfw_bunny_dry_run_' : 'asfw_bunny_banned_' ) . md5( (string) $ip );
	}

	protected function get_backoff_state() {
		$state = $this->read_state( 'backoff' );
		if ( is_wp_error( $state ) ) {
			return array( 'retry_at' => time() + self::DEFAULT_BACKOFF_WINDOW );
		}
		if ( null === $state ) {
			$state = get_transient( self::TRANSIENT_BACKOFF );
		}
		if ( is_array( $state ) ) {
			return $state;
		}

		$retry_at = intval( (string) $state, 10 );
		if ( $retry_at > time() ) {
			return array( 'retry_at' => $retry_at );
		}

		return array();
	}

	protected function is_backoff_active() {
		$state = $this->get_backoff_state();
		if ( empty( $state['retry_at'] ) ) {
			return false;
		}

		return intval( $state['retry_at'], 10 ) > time();
	}

	protected function set_backoff_state( array $state ) {
		$this->update_state(
			'backoff',
			static function () use ( $state ) {
				return $state;
			},
			DAY_IN_SECONDS
		);
		// Compatibility mirror; atomic state remains authoritative for coordination.
		set_transient( self::TRANSIENT_BACKOFF, $state, DAY_IN_SECONDS );
	}

	protected function reset_backoff_state() {
		$this->delete_state( 'backoff' );
		delete_transient( self::TRANSIENT_BACKOFF );
	}

	protected function get_last_failure_state() {
		$state = get_transient( self::TRANSIENT_LAST_FAILURE );

		return is_array( $state ) ? $state : array();
	}

	protected function set_last_failure_state( array $state ) {
		set_transient( self::TRANSIENT_LAST_FAILURE, $state, DAY_IN_SECONDS );
	}

	protected function clear_last_failure_state() {
		delete_transient( self::TRANSIENT_LAST_FAILURE );
	}

	protected function bump_backoff_state() {
		$legacy = $this->get_backoff_state();
		$result = $this->update_state(
			'backoff',
			static function ( array $state ) use ( $legacy ) {
				$state    = empty( $state ) ? $legacy : $state;
				$attempts = isset( $state['attempts'] ) ? max( 0, intval( $state['attempts'], 10 ) ) + 1 : 1;
				$delay    = min( self::MAX_BACKOFF_WINDOW, self::DEFAULT_BACKOFF_WINDOW * pow( 2, min( 6, $attempts - 1 ) ) );
				return array(
					'attempts'  => $attempts,
					'retry_at'  => time() + $delay,
					'delay'     => $delay,
					'updatedAt' => gmdate( 'c' ),
				);
			},
			DAY_IN_SECONDS
		);
		if ( is_array( $result ) ) {
			set_transient( self::TRANSIENT_BACKOFF, $result, DAY_IN_SECONDS );
		}
	}

	protected function clear_signal_state( $ip ) {
		$this->delete_state( $this->get_signal_key( $ip ) );
		delete_transient( $this->get_signal_key( $ip ) );
	}

	protected function get_signal_state( $ip ) {
		$state = $this->read_state( $this->get_signal_key( $ip ) );
		return is_array( $state ) ? $state : array();
	}

	protected function increment_signal_state( $ip, $reason, $context, $state = array() ) {
		return $this->update_state(
			$this->get_signal_key( $ip ),
			static function ( array $current ) use ( $reason, $context, $state ) {
				return array(
					'count'        => isset( $current['count'] ) ? (int) $current['count'] + 1 : 1,
					'last_reason'  => (string) $reason,
					'last_context' => ASFW_Feature_Registry::normalize_context( $context ),
					'last_seen'    => time(),
					'last_state'   => $state,
				);
			},
			$this->get_dedupe_window()
		);
	}

	protected function mark_dedupe( $ip, $reason ) {
		return $this->update_state(
			$this->get_dedupe_key( $ip ),
			static function () use ( $reason ) {
				return array(
					'reason'    => (string) $reason,
					'createdAt' => time(),
				); },
			$this->get_dedupe_window()
		);
	}

	protected function is_deduped( $ip ) {
		return is_array( $this->read_state( $this->get_dedupe_key( $ip ) ) );
	}

	/** @return array|WP_Error Reject the whole list when it is invalid or excessive. */
	protected function normalize_entries( $content ) {
		return ASFW_Bunny_List_Content::parse( $content, array( $this->plugin(), 'normalize_ip' ) );
	}

	protected function build_content( array $entries ) {
		$entries = array_values( array_unique( array_filter( $entries ) ) );

		return implode( "\n", $entries );
	}

	protected function extract_list_id( array $payload ) {
		foreach ( array( 'id', 'listId', 'configurationId' ) as $key ) {
			if ( isset( $payload[ $key ] ) && ( is_int( $payload[ $key ] ) || ( is_string( $payload[ $key ] ) && ctype_digit( $payload[ $key ] ) ) ) && intval( $payload[ $key ], 10 ) > 0 ) {
				return intval( $payload[ $key ], 10 );
			}
		}

		return 0;
	}

	protected function extract_custom_list( $response ) {
		if ( ! is_array( $response ) ) {
			return array();
		}

		if ( isset( $response['body']['data'] ) && is_array( $response['body']['data'] ) ) {
			return $response['body']['data'];
		}

		if ( isset( $response['body'] ) && is_array( $response['body'] ) ) {
			return $response['body'];
		}

		return array();
	}

	protected function is_missing_access_list_error( WP_Error $error ) {
		$data = $error->get_error_data();

		return is_array( $data ) && isset( $data['status'] ) && 404 === intval( $data['status'], 10 );
	}

	protected function validate_list_response( $response, $expected_id = 0, $expected_content = null ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$payload = $this->extract_custom_list( $response );
		$id      = $this->extract_list_id( $payload );
		if ( $id <= 0 || ( $expected_id > 0 && $expected_id !== $id )
			|| ! isset( $payload['content'] ) || ! is_string( $payload['content'] )
			|| ( isset( $payload['type'] ) && ! in_array( $payload['type'], array( self::LIST_TYPE, (string) self::LIST_TYPE ), true ) ) ) {
			return $this->invalid_response();
		}
		$content = $payload['content'];
		if ( null !== $expected_content && $content !== $expected_content ) {
			return $this->invalid_response();
		}
		if ( ! empty( $payload['checksum'] ) && ( ! is_string( $payload['checksum'] ) || ! hash_equals( hash( 'sha256', $content ), strtolower( $payload['checksum'] ) ) ) ) {
			return $this->invalid_response();
		}
		$entries = $this->normalize_entries( $content );
		if ( is_wp_error( $entries ) ) {
			return $entries;
		}
		return array(
			'list_id'  => $id,
			'name'     => isset( $payload['name'] ) && is_string( $payload['name'] ) ? $payload['name'] : self::LIST_NAME,
			'content'  => $content,
			'checksum' => hash( 'sha256', $content ),
			'entries'  => $entries,
			'raw'      => $payload,
			'response' => $response,
		);
	}

	protected function get_existing_list_content( $list_id ) {
		return $this->validate_list_response( $this->get_client()->get_access_list( $list_id ), (int) $list_id );
	}

	protected function find_list_id_by_name( array $custom_lists ) {
		foreach ( $custom_lists as $list ) {
			if ( ! is_array( $list ) ) {
				continue;
			}

			$name = isset( $list['name'] ) && is_string( $list['name'] ) ? $list['name'] : '';
			if ( '' !== $name && 0 === strcasecmp( $name, self::LIST_NAME ) ) {
				return $this->extract_list_id( $list );
			}
		}

		return 0;
	}

	protected function get_or_create_access_list( array $initial_entries = array(), $allow_create = true ) {
		$client  = $this->get_client();
		$list_id = $this->get_access_list_id();
		$zone_id = $this->get_shield_zone_id();
		$entries = array_values( array_unique( array_filter( $initial_entries ) ) );

		if ( $list_id > 0 ) {
			$current = $this->get_existing_list_content( $list_id );
			if ( ! is_wp_error( $current ) && ! empty( $current['list_id'] ) ) {
				$current['created'] = false;
				return $current;
			}

			if ( is_wp_error( $current ) && ! $this->is_missing_access_list_error( $current ) ) {
				return $current;
			}

			// Only a confirmed missing list permits rediscovery and creation.
			$this->set_access_list_id( 0 );
		}

		$summary = $client->list_access_lists( $zone_id );
		if ( is_wp_error( $summary ) ) {
			return $summary;
		}
		if ( ! isset( $summary['body']['customLists'] ) || ! is_array( $summary['body']['customLists'] ) ) {
			return $this->invalid_response();
		}
		$found = $this->find_list_id_by_name( $summary['body']['customLists'] );
		if ( $found > 0 ) {
			$existing = $this->get_existing_list_content( $found );
			if ( ! is_wp_error( $existing ) ) {
				$this->set_access_list_id( $found );
				$existing['created'] = false;
			}
			return $existing;
		}

		if ( ! $allow_create ) {
			return array();
		}

		return $this->apply_remote_update( 0, $entries );
	}

	protected function apply_remote_update( $list_id, array $entries ) {
		$content = $this->build_content( $entries );
		$checked = $this->normalize_entries( $content );
		if ( is_wp_error( $checked ) ) {
			return $checked;
		}
		if ( ! $this->owns_remote_lease() ) {
			return new WP_Error( 'asfw_bunny_busy', __( 'Bunny Shield synchronization is busy. Please try again.', 'anti-spam-for-wordpress' ) );
		}
		$client   = $this->get_client();
		$created  = 0 === intval( $list_id, 10 );
		$response = $created
			? $client->create_access_list( self::LIST_NAME, $content, $this->get_shield_zone_id(), self::LIST_DESCRIPTION )
			: $client->update_access_list( $list_id, $content, $this->get_shield_zone_id(), self::LIST_NAME );
		$result   = $this->validate_list_response( $response, (int) $list_id, $content );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->set_access_list_id( $result['list_id'] );
		$result['created'] = $created;
		return $result;
	}

	protected function maybe_backoff_or_return( $ip, $reason, $context, $state = array() ) {
		if ( $this->is_backoff_active() ) {
			return array(
				'status'  => 'backoff',
				'ip'      => $ip,
				'reason'  => $reason,
				'context' => ASFW_Feature_Registry::normalize_context( $context ),
				'state'   => $state,
			);
		}

		return null;
	}

	protected function record_successful_sync( $ip, $reason, array $state, array $result ) {
		$this->clear_signal_state( $ip );
		$this->mark_dedupe( $ip, $reason );
		$this->reset_backoff_state();
		$this->clear_last_failure_state();

		do_action( 'asfw_bunny_synced', $ip, $reason, $state, $result );

		return $result;
	}

	protected function record_failed_sync( $ip, $reason, array $state, WP_Error $error ) {
		$this->bump_backoff_state();
		$failure = array(
			'status'      => $this->is_fail_open() ? 'failed_open' : 'failed_closed',
			'ip'          => (string) $ip,
			'reason'      => (string) $reason,
			'context'     => isset( $state['last_context'] ) ? ASFW_Feature_Registry::normalize_context( $state['last_context'] ) : '',
			'count'       => isset( $state['count'] ) ? intval( $state['count'], 10 ) : 0,
			'error'       => array(
				'code'     => $error->get_error_code(),
				'messages' => $error->get_error_messages(),
			),
			'backoff'     => $this->get_backoff_state(),
			'recorded_at' => gmdate( 'c' ),
		);
		$this->set_last_failure_state( $failure );

		do_action( 'asfw_bunny_sync_failed', $ip, $reason, $state, $error, $failure );
		do_action(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Both hook names use the plugin prefix and are selected by failure mode.
			$this->is_fail_open() ? 'asfw_bunny_sync_failed_open' : 'asfw_bunny_sync_failed_closed',
			$failure,
			$error,
			$state
		);

		return $failure;
	}

	protected function maybe_block_ip( $ip, $reason, $context, $state = array() ) {
		if ( ! $this->is_enabled( $context ) ) {
			return array(
				'status' => 'disabled',
				'ip'     => $ip,
			);
		}

		$normalized_ip = $this->normalize_public_ip( $ip );
		if ( '' === $normalized_ip ) {
			return array(
				'status' => 'skipped',
				'ip'     => $ip,
				'reason' => 'private_or_reserved',
			);
		}

		if ( $this->is_deduped( $normalized_ip ) ) {
			return array(
				'status' => 'deduped',
				'ip'     => $normalized_ip,
				'reason' => $reason,
			);
		}

		$signal_state = $this->increment_signal_state( $normalized_ip, $reason, $context, $state );
		if ( is_wp_error( $signal_state ) ) {
			return $signal_state;
		}
		if ( isset( $signal_state['count'] ) && intval( $signal_state['count'], 10 ) < $this->get_threshold() ) {
			return array(
				'status'    => 'counting',
				'ip'        => $normalized_ip,
				'reason'    => $reason,
				'context'   => ASFW_Feature_Registry::normalize_context( $context ),
				'count'     => intval( $signal_state['count'], 10 ),
				'threshold' => $this->get_threshold(),
			);
		}

		if ( 'block' !== $this->get_mode() ) {
			return array(
				'status'    => 'log_only',
				'ip'        => $normalized_ip,
				'reason'    => $reason,
				'context'   => ASFW_Feature_Registry::normalize_context( $context ),
				'count'     => intval( $signal_state['count'], 10 ),
				'threshold' => $this->get_threshold(),
			);
		}

		if ( 'block' !== $this->get_action() ) {
			return array(
				'status'    => 'action_not_supported',
				'ip'        => $normalized_ip,
				'reason'    => $reason,
				'context'   => ASFW_Feature_Registry::normalize_context( $context ),
				'count'     => intval( $signal_state['count'], 10 ),
				'threshold' => $this->get_threshold(),
				'action'    => $this->get_action(),
			);
		}

		if ( $this->is_dry_run() ) {
			$this->mark_dedupe( $normalized_ip, $reason );
			do_action( 'asfw_bunny_dry_run', $normalized_ip, $reason, $signal_state );

			return array(
				'status'  => 'dry_run',
				'ip'      => $normalized_ip,
				'reason'  => $reason,
				'context' => ASFW_Feature_Registry::normalize_context( $context ),
				'count'   => intval( $signal_state['count'], 10 ),
			);
		}

		return $this->with_remote_lock(
			function () use ( $normalized_ip, $reason, $context, $signal_state ) {
				if ( $this->is_deduped( $normalized_ip ) ) {
					return array(
						'status' => 'deduped',
						'ip'     => $normalized_ip,
					);
				}
				$backoff_state = $this->maybe_backoff_or_return( $normalized_ip, $reason, $context, $signal_state );
				if ( is_array( $backoff_state ) ) {
					return $backoff_state;
				}

				$list = $this->get_or_create_access_list( array( $normalized_ip ), true );
				if ( is_wp_error( $list ) ) {
					return $this->record_failed_sync( $normalized_ip, $reason, $signal_state, $list );
				}

				$list_id = isset( $list['list_id'] ) ? intval( $list['list_id'], 10 ) : 0;
				$entries = isset( $list['entries'] ) && is_array( $list['entries'] ) ? $list['entries'] : array();
				if ( empty( $list['created'] ) && ! in_array( $normalized_ip, $entries, true ) ) {
					$entries[] = $normalized_ip;
					$result    = $this->apply_remote_update( $list_id, $entries );
				} else {
					$result = $list;
				}
				if ( is_wp_error( $result ) ) {
					return $this->record_failed_sync( $normalized_ip, $reason, $signal_state, $result );
				}

				return $this->record_successful_sync( $normalized_ip, $reason, $signal_state, $result );
			}
		);
	}

	public function handle_verify_result( $success, $result, $context, $field_name, $resolved_context = null ) {
		unset( $field_name );

		$event_context = ASFW_Feature_Registry::normalize_context( '' !== trim( (string) $resolved_context ) ? $resolved_context : $context );

		if ( ! $this->background_enabled() || ! $this->is_enabled( $event_context ) ) {
			return;
		}

		$ip = $this->get_client_ip();
		if ( '' === $ip ) {
			return;
		}

		if ( $success ) {
			$this->clear_signal_state( $ip );

			return;
		}

		$this->maybe_block_ip(
			$ip,
			'verification_failed',
			$event_context,
			array(
				'success' => false,
				'result'  => $result instanceof WP_Error ? $result->get_error_code() : '',
			)
		);
	}

	public function handle_rate_limited( $type, $context, array $state ) {
		if ( ! $this->background_enabled() || ! $this->is_enabled( $context ) ) {
			return;
		}

		$ip = $this->get_client_ip();
		if ( '' === $ip ) {
			return;
		}

		$this->maybe_block_ip(
			$ip,
			'rate_limited:' . sanitize_key( (string) $type ),
			$context,
			$state
		);
	}

	public function get_status() {
		$client = $this->get_client();
		$status = array(
			'enabled'            => $this->is_enabled(),
			'mode'               => $this->get_mode(),
			'action'             => $this->get_action(),
			'background_enabled' => $this->background_enabled(),
			'scope_mode'         => ASFW_Feature_Registry::scope_mode( 'bunny_shield' ),
			'contexts'           => ASFW_Feature_Registry::selected_contexts( 'bunny_shield' ),
			'configured'         => $client->is_configured(),
			'dry_run'            => $this->is_dry_run(),
			'fail_open'          => $this->is_fail_open(),
			'threshold'          => $this->get_threshold(),
			'dedupe_window'      => $this->get_dedupe_window(),
			'shield_zone_id'     => $this->get_shield_zone_id(),
			'access_list_id'     => $this->get_access_list_id(),
			'api_key_set'        => '' !== $this->get_api_key(),
			'backoff'            => $this->get_backoff_state(),
			'last_failure'       => $this->get_last_failure_state(),
			'list'               => array(),
		);

		$summary = $client->list_access_lists();
		if ( ! is_wp_error( $summary ) && isset( $summary['body'] ) && is_array( $summary['body'] ) ) {
			$status['list'] = array(
				'custom_lists'       => isset( $summary['body']['customLists'] ) ? $summary['body']['customLists'] : array(),
				'custom_entry_count' => isset( $summary['body']['customEntryCount'] ) ? intval( $summary['body']['customEntryCount'], 10 ) : 0,
				'custom_list_count'  => isset( $summary['body']['customListCount'] ) ? intval( $summary['body']['customListCount'], 10 ) : 0,
			);
		} elseif ( is_wp_error( $summary ) ) {
			$status['list_error'] = array(
				'code'     => $summary->get_error_code(),
				'messages' => $summary->get_error_messages(),
			);
		}

		return $status;
	}

	public function revoke_ip( $ip, $force_remote = false ) {
		$normalized_ip = $this->normalize_public_ip( $ip );
		if ( '' === $normalized_ip ) {
			return new WP_Error( 'asfw_bunny_invalid_ip', __( 'The IP address is private, reserved, or invalid.', 'anti-spam-for-wordpress' ) );
		}

		if ( ! $force_remote && $this->is_dry_run() ) {
			return array(
				'status' => 'dry_run',
				'ip'     => $normalized_ip,
			);
		}

		return $this->with_remote_lock(
			function () use ( $normalized_ip ) {
				// Resolve cached IDs through the same confirmed-404 recovery as sync.
				$current = $this->get_or_create_access_list( array(), false );
				if ( is_wp_error( $current ) ) {
					return $current;
				}
				$list_id = isset( $current['list_id'] ) ? intval( $current['list_id'], 10 ) : 0;
				$entries = isset( $current['entries'] ) && is_array( $current['entries'] ) ? $current['entries'] : array();
				$updated = array_values(
					array_filter(
						$entries,
						static function ( $entry ) use ( $normalized_ip ) {
							return $normalized_ip !== $entry;
						}
					)
				);

				$response = array(
					'status' => $list_id > 0 ? 'unchanged' : 'missing_list',
					'ip'     => $normalized_ip,
				);
				if ( $list_id > 0 ) {
					$response['list_id'] = $list_id;
				}
				if ( $updated !== $entries ) {
					$result = $this->apply_remote_update( $list_id, $updated );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
					$response['status'] = 'updated';
					$response['result'] = $result;
				}

				// Confirmed absence is a successful idempotent revoke too.
				if ( ! $this->owns_remote_lease() ) {
					return new WP_Error( 'asfw_bunny_busy', __( 'Bunny Shield synchronization is busy. Please try again.', 'anti-spam-for-wordpress' ) );
				}
				foreach ( array( $this->get_signal_key( $normalized_ip ), $this->get_dedupe_key( $normalized_ip, false ), $this->get_dedupe_key( $normalized_ip, true ) ) as $key ) {
					$cleared = $this->delete_state( $key );
					if ( is_wp_error( $cleared ) || false === $cleared ) {
						return new WP_Error( 'asfw_bunny_revoke_state_failed', __( 'The remote entry is absent, but local synchronization state could not be cleared. Please retry revoking it.', 'anti-spam-for-wordpress' ) );
					}
				}
				delete_transient( $this->get_signal_key( $normalized_ip ) );
				$this->clear_last_failure_state();

				return $response;
			}
		);
	}
}
