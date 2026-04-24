<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_Event_Logger {

	protected $store;

	public function __construct( ASFW_Event_Store $store ) {
		$this->store = $store;
	}

	public function register_hooks() {
		add_action( 'asfw_challenge_issued', array( $this, 'log_challenge_issued' ), 10, 3 );
		add_action( 'asfw_verify_result', array( $this, 'log_verify_result' ), 10, 5 );
		add_action( 'asfw_rate_limited', array( $this, 'log_rate_limited' ), 10, 3 );
		add_action( 'asfw_settings_changed', array( $this, 'log_settings_changed' ), 10, 2 );
		add_action( 'asfw_guard_result', array( $this, 'log_guard_result' ), 10, 5 );
		add_action( 'asfw_content_heuristics_flagged', array( $this, 'log_disposable_email_hit' ), 10, 3 );
		add_action( 'asfw_bunny_synced', array( $this, 'log_bunny_synced' ), 10, 4 );
		add_action( 'asfw_bunny_sync_failed', array( $this, 'log_bunny_sync_failed' ), 10, 5 );
		add_action( 'asfw_bunny_dry_run', array( $this, 'log_bunny_dry_run' ), 10, 3 );
	}

	protected function should_log( $context = null ) {
		if ( ASFW_Feature_Registry::kill_switch_active() ) {
			return false;
		}

		return ASFW_Feature_Registry::is_enabled( 'event_logging', is_string( $context ) ? $context : null );
	}

	protected function get_actor_hash() {
		$plugin = AntiSpamForWordPressPlugin::$instance;
		if ( $plugin instanceof AntiSpamForWordPressPlugin ) {
			return $plugin->get_client_fingerprint();
		}

		return $this->store->hash_value( 'unknown', 'actor' );
	}

	protected function normalize_context( $context ) {
		return sanitize_key( (string) $context );
	}

	protected function maybe_hash_user( $user_id ) {
		$user_id = intval( $user_id, 10 );

		return $user_id > 0 ? $this->store->hash_value( (string) $user_id, 'user' ) : '';
	}

	protected function extract_disabled_features( array $settings_changes ) {
		$disabled_features = array();

		foreach ( $settings_changes as $option => $delta ) {
			$option = sanitize_key( (string) $option );
			if ( '' === $option || ! is_array( $delta ) ) {
				continue;
			}

			if ( preg_match( '/^asfw_feature_([a-z0-9_]+)_enabled$/', $option, $matches ) && empty( $delta['new'] ) ) {
				$disabled_features[] = array(
					'feature' => $matches[1],
					'option'  => $option,
					'reason'  => 'disabled',
				);
				continue;
			}

			if ( preg_match( '/^asfw_feature_([a-z0-9_]+)_mode$/', $option, $matches ) && 'off' === strtolower( trim( (string) $delta['new'] ) ) ) {
				$disabled_features[] = array(
					'feature' => $matches[1],
					'option'  => $option,
					'reason'  => 'mode_off',
				);
			}
		}

		return $disabled_features;
	}

	protected function get_posted_field_value( $field_name ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Event logging reads submitted fields after verification to store privacy-preserving hashes only.
		if ( '' === $field_name || ! isset( $_POST[ $field_name ] ) || is_array( $_POST[ $field_name ] ) || is_object( $_POST[ $field_name ] ) ) {
			return '';
		}

		return trim( strtolower( (string) sanitize_text_field( wp_unslash( $_POST[ $field_name ] ) ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	public function log_challenge_issued( array $challenge_data, $context, $challenge_id ) {
		if ( ! $this->should_log( $context ) ) {
			return;
		}

		$this->store->record_event(
			'challenge_issued',
			array(
				'decision' => 'issued',
				'context'  => sanitize_key( (string) $context ),
				'feature'  => 'core',
				'ip_hash'  => $this->get_actor_hash(),
				'details'  => array(
					'algorithm'    => isset( $challenge_data['algorithm'] ) ? $challenge_data['algorithm'] : 'SHA-256',
					'maxnumber'    => isset( $challenge_data['maxnumber'] ) ? intval( $challenge_data['maxnumber'], 10 ) : 0,
					'challenge_id' => $challenge_id,
				),
			)
		);
	}

	public function log_verify_result( $success, $result, $context, $field_name, $resolved_context = null ) {
		$event_context = '' !== $this->normalize_context( $resolved_context ) ? $this->normalize_context( $resolved_context ) : $this->normalize_context( $context );
		if ( ! $this->should_log( $event_context ) ) {
			return;
		}

		$event_type = $success ? 'verify_passed' : 'verify_failed';
		$status     = $success ? 'success' : 'failed';
		$details    = array(
			'field_name' => sanitize_key( (string) $field_name ),
			'success'    => (bool) $success,
		);
		if ( $result instanceof WP_Error ) {
			$details['error_code']     = $result->get_error_code();
			$details['error_messages'] = $result->get_error_messages();
		}

		$this->store->record_event(
			$event_type,
			array(
				'decision' => $status,
				'context'  => $event_context,
				'feature'  => 'core',
				'ip_hash'  => $this->get_actor_hash(),
				'details'  => $details,
			)
		);
	}

	public function log_rate_limited( $type, $context, array $state ) {
		if ( ! $this->should_log( $context ) ) {
			return;
		}

		$this->store->record_event(
			'rate_limited',
			array(
				'decision' => 'blocked',
				'context'  => sanitize_key( (string) $context ),
				'feature'  => 'core',
				'ip_hash'  => $this->get_actor_hash(),
				'details'  => array(
					'rate_limit_type' => sanitize_key( (string) $type ),
					'count'           => isset( $state['count'] ) ? intval( $state['count'], 10 ) : 0,
					'limit'           => isset( $state['limit'] ) ? intval( $state['limit'], 10 ) : 0,
					'window'          => isset( $state['window'] ) ? intval( $state['window'], 10 ) : 0,
				),
			)
		);
	}

	public function log_settings_changed( array $settings_changes, $user_id ) {
		if ( ! $this->should_log() ) {
			return;
		}

		$options = array_values(
			array_filter(
				array_map(
					'sanitize_key',
					array_keys( $settings_changes )
				)
			)
		);
		if ( empty( $options ) ) {
			return;
		}

		$user_hash = $this->maybe_hash_user( $user_id );
		$this->store->record_event(
			'settings_changed',
			array(
				'decision' => 'updated',
				'context'  => 'settings',
				'feature'  => 'core',
				'ip_hash'  => '' !== $user_hash ? $user_hash : $this->get_actor_hash(),
				'details'  => array(
					'options'      => $options,
					'count'        => count( $options ),
					'subject_hash' => $user_hash,
				),
			)
		);

		foreach ( $this->extract_disabled_features( $settings_changes ) as $feature_change ) {
			$this->store->record_event(
				'feature_runtime_disabled',
				array(
					'decision' => 'disabled',
					'context'  => 'settings',
					'feature'  => isset( $feature_change['feature'] ) ? sanitize_key( (string) $feature_change['feature'] ) : 'core',
					'ip_hash'  => '' !== $user_hash ? $user_hash : $this->get_actor_hash(),
					'details'  => $feature_change + array( 'subject_hash' => $user_hash ),
				)
			);
		}
	}

	public function log_guard_result( $feature, $context, $success, $mode, $error_code = '' ) {
		$event_context = $this->normalize_context( $context );
		if ( ! $this->should_log( $event_context ) ) {
			return;
		}

		$feature_id = sanitize_key( str_replace( '_', '-', (string) $feature ) );
		$decision   = ! empty( $success ) ? 'passed' : 'failed';

		$this->store->record_event(
			'guard_check',
			array(
				'decision' => $decision,
				'context'  => $event_context,
				'feature'  => '' !== $feature_id ? $feature_id : 'core',
				'ip_hash'  => $this->get_actor_hash(),
				'details'  => array(
					'mode'       => sanitize_key( (string) $mode ),
					'success'    => (bool) $success,
					'error_code' => sanitize_key( (string) $error_code ),
				),
			)
		);
	}

	public function log_disposable_email_hit( array $analysis, $context, $field_name ) {
		$event_context = $this->normalize_context( $context );
		if ( ! $this->should_log( $event_context ) ) {
			return;
		}

		$matched_fields = array();
		foreach ( isset( $analysis['reasons'] ) && is_array( $analysis['reasons'] ) ? $analysis['reasons'] : array() as $reason ) {
			if ( 0 !== strpos( (string) $reason, 'disposable_email:' ) ) {
				continue;
			}

			$matched_fields[] = sanitize_key( substr( (string) $reason, strlen( 'disposable_email:' ) ) );
		}

		$matched_fields = array_values( array_unique( array_filter( $matched_fields ) ) );
		if ( empty( $matched_fields ) ) {
			return;
		}

		$email_hash = '';
		foreach ( $matched_fields as $matched_field ) {
			$email_value = $this->get_posted_field_value( $matched_field );
			if ( '' === $email_value ) {
				continue;
			}

			$email_hash = $this->store->hash_value( $email_value, 'email' );
			break;
		}

		$this->store->record_event(
			'disposable_email_hit',
			array(
				'decision'   => 'matched',
				'context'    => $event_context,
				'feature'    => 'content-heuristics',
				'ip_hash'    => $this->get_actor_hash(),
				'email_hash' => $email_hash,
				'details'    => array(
					'field_name'     => sanitize_key( (string) $field_name ),
					'matched_fields' => $matched_fields,
					'matched_count'  => count( $matched_fields ),
				),
			)
		);
	}

	protected function bunny_context_from_state( array $state ) {
		$candidate = '';
		if ( isset( $state['last_context'] ) && '' !== trim( (string) $state['last_context'] ) ) {
			$candidate = (string) $state['last_context'];
		} elseif ( isset( $state['context'] ) && '' !== trim( (string) $state['context'] ) ) {
			$candidate = (string) $state['context'];
		}

		return $this->normalize_context( $candidate );
	}

	public function log_bunny_synced( $ip, $reason, array $state, array $result ) {
		$context = $this->bunny_context_from_state( $state );
		if ( ! $this->should_log( $context ) ) {
			return;
		}

		$this->store->record_event(
			'bunny_sync_success',
			array(
				'decision' => 'synced',
				'context'  => $context,
				'feature'  => 'bunny-shield',
				'ip_hash'  => '' !== (string) $ip ? $this->store->hash_value( (string) $ip, 'ip' ) : '',
				'details'  => array(
					'reason'    => sanitize_key( (string) $reason ),
					'count'     => isset( $state['count'] ) ? intval( $state['count'], 10 ) : 0,
					'list_id'   => isset( $result['list_id'] ) ? intval( $result['list_id'], 10 ) : 0,
					'is_create' => ! empty( $result['created'] ),
				),
			)
		);
	}

	public function log_bunny_sync_failed( $ip, $reason, array $state, WP_Error $error, array $failure ) {
		$context = $this->bunny_context_from_state( $state );
		if ( ! $this->should_log( $context ) ) {
			return;
		}

		$this->store->record_event(
			'bunny_sync_failed',
			array(
				'decision' => sanitize_key( isset( $failure['status'] ) ? (string) $failure['status'] : 'failed' ),
				'context'  => $context,
				'feature'  => 'bunny-shield',
				'ip_hash'  => '' !== (string) $ip ? $this->store->hash_value( (string) $ip, 'ip' ) : '',
				'details'  => array(
					'reason'      => sanitize_key( (string) $reason ),
					'count'       => isset( $state['count'] ) ? intval( $state['count'], 10 ) : 0,
					'error_code'  => $error->get_error_code(),
					'error_texts' => $error->get_error_messages(),
					'backoff'     => isset( $failure['backoff'] ) ? $failure['backoff'] : array(),
				),
			)
		);
	}

	public function log_bunny_dry_run( $ip, $reason, array $state ) {
		$context = $this->bunny_context_from_state( $state );
		if ( ! $this->should_log( $context ) ) {
			return;
		}

		$this->store->record_event(
			'bunny_dry_run',
			array(
				'decision' => 'dry_run',
				'context'  => $context,
				'feature'  => 'bunny-shield',
				'ip_hash'  => '' !== (string) $ip ? $this->store->hash_value( (string) $ip, 'ip' ) : '',
				'details'  => array(
					'reason'    => sanitize_key( (string) $reason ),
					'count'     => isset( $state['count'] ) ? intval( $state['count'], 10 ) : 0,
					'threshold' => isset( $state['threshold'] ) ? intval( $state['threshold'], 10 ) : 0,
				),
			)
		);
	}
}
