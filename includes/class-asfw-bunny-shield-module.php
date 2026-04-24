<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

	protected function get_signal_key( $ip ) {
		return 'asfw_bunny_counter_' . md5( (string) $ip );
	}

	protected function get_dedupe_key( $ip ) {
		return 'asfw_bunny_banned_' . md5( (string) $ip );
	}

	protected function get_backoff_state() {
		$state = get_transient( self::TRANSIENT_BACKOFF );
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
		$retry_at = isset( $state['retry_at'] ) ? intval( $state['retry_at'], 10 ) : 0;
		$ttl      = max( 1, $retry_at - time() );
		set_transient( self::TRANSIENT_BACKOFF, $state, $ttl );
	}

	protected function reset_backoff_state() {
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
		$state    = $this->get_backoff_state();
		$attempts = isset( $state['attempts'] ) ? max( 0, intval( $state['attempts'], 10 ) ) : 0;
		++$attempts;
		$delay = min( self::MAX_BACKOFF_WINDOW, self::DEFAULT_BACKOFF_WINDOW * pow( 2, min( 6, $attempts - 1 ) ) );

		$this->set_backoff_state(
			array(
				'attempts'  => $attempts,
				'retry_at'  => time() + $delay,
				'delay'     => $delay,
				'updatedAt' => gmdate( 'c' ),
			)
		);
	}

	protected function clear_signal_state( $ip ) {
		delete_transient( $this->get_signal_key( $ip ) );
	}

	protected function get_signal_state( $ip ) {
		$state = get_transient( $this->get_signal_key( $ip ) );
		return is_array( $state ) ? $state : array();
	}

	protected function increment_signal_state( $ip, $reason, $context, $state = array() ) {
		$current = $this->get_signal_state( $ip );
		$count   = isset( $current['count'] ) ? intval( $current['count'], 10 ) : 0;
		++$count;

		$current = array_merge(
			$current,
			array(
				'count'        => $count,
				'last_reason'  => (string) $reason,
				'last_context' => sanitize_key( (string) $context ),
				'last_seen'    => time(),
				'last_state'   => $state,
			)
		);

		set_transient( $this->get_signal_key( $ip ), $current, $this->get_dedupe_window() );

		return $current;
	}

	protected function mark_dedupe( $ip, $reason ) {
		set_transient(
			$this->get_dedupe_key( $ip ),
			array(
				'reason'    => (string) $reason,
				'createdAt' => time(),
			),
			$this->get_dedupe_window()
		);
	}

	protected function is_deduped( $ip ) {
		return is_array( get_transient( $this->get_dedupe_key( $ip ) ) );
	}

	protected function normalize_entries( $content ) {
		$entries = preg_split( '/[\r\n,]+/', trim( (string) $content ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $entries ) ) {
			return array();
		}

		$plugin  = $this->plugin();
		$entries = array_map(
			static function ( $entry ) use ( $plugin ) {
				$entry = trim( (string) $entry );
				if ( '' === $entry ) {
					return '';
				}

				if ( $plugin instanceof AntiSpamForWordPressPlugin ) {
					$entry = $plugin->normalize_ip( $entry );
				}

				return $entry;
			},
			$entries
		);

		$entries = array_values(
			array_filter(
				$entries,
				static function ( $entry ) {
					return '' !== $entry;
				}
			)
		);

		return array_values( array_unique( $entries ) );
	}

	protected function build_content( array $entries ) {
		$entries = array_values( array_unique( array_filter( $entries ) ) );

		return implode( "\n", $entries );
	}

	protected function extract_list_id( array $payload ) {
		foreach ( array( 'id', 'listId', 'configurationId' ) as $key ) {
			if ( isset( $payload[ $key ] ) && intval( $payload[ $key ], 10 ) > 0 ) {
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

	protected function get_existing_list_content( $list_id ) {
		$client   = $this->get_client();
		$response = $client->get_access_list( $list_id );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$payload = $this->extract_custom_list( $response );
		$content = isset( $payload['content'] ) ? (string) $payload['content'] : '';

		return array(
			'list_id'  => $this->extract_list_id( $payload ),
			'name'     => isset( $payload['name'] ) ? (string) $payload['name'] : self::LIST_NAME,
			'content'  => $content,
			'checksum' => isset( $payload['checksum'] ) ? (string) $payload['checksum'] : '',
			'entries'  => $this->normalize_entries( $content ),
			'raw'      => $payload,
			'response' => $response,
		);
	}

	protected function find_list_id_by_name( array $custom_lists ) {
		foreach ( $custom_lists as $list ) {
			if ( ! is_array( $list ) ) {
				continue;
			}

			$name = isset( $list['name'] ) ? (string) $list['name'] : '';
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

			// Fall through if the saved ID is stale or no longer returns a usable payload.
			$this->set_access_list_id( 0 );
		}

		$summary = $client->list_access_lists( $zone_id );
		if ( ! is_wp_error( $summary ) ) {
			$payload = $this->extract_custom_list( $summary );
			if ( isset( $summary['body']['customLists'] ) && is_array( $summary['body']['customLists'] ) ) {
				$found = $this->find_list_id_by_name( $summary['body']['customLists'] );
				if ( $found > 0 ) {
					$this->set_access_list_id( $found );
					$existing = $this->get_existing_list_content( $found );
					if ( ! is_wp_error( $existing ) ) {
						$existing['created'] = false;
					}

					return $existing;
				}
			}
		}

		if ( ! $allow_create ) {
			return array();
		}

		$content  = $this->build_content( $entries );
		$response = $client->create_access_list( self::LIST_NAME, $content, $zone_id, self::LIST_DESCRIPTION );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$payload = $this->extract_custom_list( $response );
		$found   = $this->extract_list_id( $payload );
		if ( $found > 0 ) {
			$this->set_access_list_id( $found );
		}

		return array(
			'list_id'  => $found,
			'name'     => isset( $payload['name'] ) ? (string) $payload['name'] : self::LIST_NAME,
			'content'  => isset( $payload['content'] ) ? (string) $payload['content'] : $content,
			'checksum' => isset( $payload['checksum'] ) ? (string) $payload['checksum'] : '',
			'entries'  => $this->normalize_entries( isset( $payload['content'] ) ? (string) $payload['content'] : $content ),
			'raw'      => $payload,
			'response' => $response,
			'created'  => true,
		);
	}

	protected function apply_remote_update( $list_id, array $entries ) {
		$client  = $this->get_client();
		$content = $this->build_content( $entries );
		$name    = self::LIST_NAME;

		if ( 0 === intval( $list_id, 10 ) ) {
			$response = $client->create_access_list( $name, $content, $this->get_shield_zone_id(), self::LIST_DESCRIPTION );
		} else {
			$response = $client->update_access_list( $list_id, $content, $this->get_shield_zone_id(), $name );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$payload = $this->extract_custom_list( $response );
		$found   = $this->extract_list_id( $payload );
		if ( $found > 0 ) {
			$this->set_access_list_id( $found );
		}

		return array(
			'list_id'  => $found,
			'name'     => isset( $payload['name'] ) ? (string) $payload['name'] : $name,
			'content'  => isset( $payload['content'] ) ? (string) $payload['content'] : $content,
			'checksum' => isset( $payload['checksum'] ) ? (string) $payload['checksum'] : hash( 'sha256', $content ),
			'entries'  => $this->normalize_entries( isset( $payload['content'] ) ? (string) $payload['content'] : $content ),
			'raw'      => $payload,
			'response' => $response,
			'created'  => 0 === intval( $list_id, 10 ),
		);
	}

	protected function maybe_backoff_or_return( $ip, $reason, $context, $state = array() ) {
		if ( $this->is_backoff_active() ) {
			return array(
				'status'  => 'backoff',
				'ip'      => $ip,
				'reason'  => $reason,
				'context' => sanitize_key( (string) $context ),
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
			'context'     => isset( $state['last_context'] ) ? sanitize_key( (string) $state['last_context'] ) : '',
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
		if ( isset( $signal_state['count'] ) && intval( $signal_state['count'], 10 ) < $this->get_threshold() ) {
			return array(
				'status'    => 'counting',
				'ip'        => $normalized_ip,
				'reason'    => $reason,
				'context'   => sanitize_key( (string) $context ),
				'count'     => intval( $signal_state['count'], 10 ),
				'threshold' => $this->get_threshold(),
			);
		}

		if ( 'block' !== $this->get_mode() ) {
			return array(
				'status'    => 'log_only',
				'ip'        => $normalized_ip,
				'reason'    => $reason,
				'context'   => sanitize_key( (string) $context ),
				'count'     => intval( $signal_state['count'], 10 ),
				'threshold' => $this->get_threshold(),
			);
		}

		if ( 'block' !== $this->get_action() ) {
			return array(
				'status'    => 'action_not_supported',
				'ip'        => $normalized_ip,
				'reason'    => $reason,
				'context'   => sanitize_key( (string) $context ),
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
				'context' => sanitize_key( (string) $context ),
				'count'   => intval( $signal_state['count'], 10 ),
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

	public function handle_verify_result( $success, $result, $context, $field_name, $resolved_context = null ) {
		unset( $field_name );

		$event_context = '' !== sanitize_key( (string) $resolved_context ) ? sanitize_key( (string) $resolved_context ) : sanitize_key( (string) $context );

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

		$list_id = $this->get_access_list_id();
		if ( $list_id <= 0 ) {
			$list = $this->get_or_create_access_list( array(), false );
			if ( empty( $list['list_id'] ) ) {
				return array(
					'status' => 'missing_list',
					'ip'     => $normalized_ip,
				);
			}

			$list_id = intval( $list['list_id'], 10 );
		}

		$current = $this->get_existing_list_content( $list_id );
		if ( is_wp_error( $current ) ) {
			return $current;
		}

		$entries = isset( $current['entries'] ) && is_array( $current['entries'] ) ? $current['entries'] : array();
		$updated = array_values(
			array_filter(
				$entries,
				static function ( $entry ) use ( $normalized_ip ) {
					return $normalized_ip !== $entry;
				}
			)
		);

		if ( $updated === $entries ) {
			return array(
				'status'  => 'unchanged',
				'ip'      => $normalized_ip,
				'list_id' => $list_id,
			);
		}

		$result = $this->apply_remote_update( $list_id, $updated );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		delete_transient( $this->get_signal_key( $normalized_ip ) );
		delete_transient( $this->get_dedupe_key( $normalized_ip ) );
		$this->clear_last_failure_state();

		return array(
			'status'  => 'updated',
			'ip'      => $normalized_ip,
			'list_id' => $list_id,
			'result'  => $result,
		);
	}
}
