<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ASFW_Verifier', false ) ) {
	class ASFW_Verifier {

		private $challenge_manager_service;
		private $client_identity_service;
		private $context_helper_service;
		private $options_service;
		private $rate_limiter_service;

		private function challenge_manager_service() {
			if ( ! $this->challenge_manager_service instanceof ASFW_Challenge_Manager ) {
				$this->challenge_manager_service = new ASFW_Challenge_Manager();
			}

			return $this->challenge_manager_service;
		}

		private function client_identity_service() {
			if ( ! $this->client_identity_service instanceof ASFW_Client_Identity ) {
				$this->client_identity_service = new ASFW_Client_Identity();
			}

			return $this->client_identity_service;
		}

		private function context_helper_service() {
			if ( ! $this->context_helper_service instanceof ASFW_Context_Helper ) {
				$this->context_helper_service = new ASFW_Context_Helper();
			}

			return $this->context_helper_service;
		}

		private function options_service() {
			if ( ! $this->options_service instanceof ASFW_Options ) {
				$this->options_service = new ASFW_Options();
			}

			return $this->options_service;
		}

		private function rate_limiter_service() {
			if ( ! $this->rate_limiter_service instanceof ASFW_Rate_Limiter ) {
				$this->rate_limiter_service = new ASFW_Rate_Limiter();
			}

			return $this->rate_limiter_service;
		}

		public function decode_payload( $payload ) {
			$payload = trim( (string) $payload );
			if ( '' === $payload ) {
				return new WP_Error( 'asfw_empty_payload', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			if ( strlen( $payload ) > 8192 ) {
				return new WP_Error( 'asfw_payload_too_large', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Proof-of-work payloads are base64-encoded JSON by design.
			$decoded = base64_decode( $payload, true );
			if ( false === $decoded ) {
				return new WP_Error( 'asfw_invalid_base64', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			if ( strlen( $decoded ) > 8192 ) {
				return new WP_Error( 'asfw_payload_too_large', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$data = json_decode( $decoded, true );
			if ( ! is_array( $data ) || json_last_error() !== JSON_ERROR_NONE ) {
				return new WP_Error( 'asfw_invalid_json', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$required = array( 'algorithm', 'challenge', 'number', 'salt', 'signature' );
			foreach ( $required as $field ) {
				if ( ! array_key_exists( $field, $data ) ) {
					return new WP_Error( 'asfw_missing_field', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}
			}

			if (
				! is_string( $data['algorithm'] ) ||
				! is_string( $data['challenge'] ) ||
				! is_string( $data['salt'] ) ||
				! is_string( $data['signature'] ) ||
				( ! is_int( $data['number'] ) && ! is_string( $data['number'] ) )
			) {
				return new WP_Error( 'asfw_invalid_field_type', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			if ( ! preg_match( '/^\d+$/', (string) $data['number'] ) ) {
				return new WP_Error( 'asfw_invalid_number', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$number = intval( $data['number'], 10 );
			if ( $number < 0 || $number > 1000000 ) {
				return new WP_Error( 'asfw_invalid_number', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$data['number'] = (string) $number;

			return $data;
		}

		public function validate_submission_guards( $context, $field_name = 'asfw' ) {
			$rate_limited = $this->rate_limiter_service()->is_rate_limited( 'failure', $context );
			if ( $rate_limited instanceof WP_Error ) {
				return $rate_limited;
			}

			if ( $this->options_service()->get_honeypot() ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Honeypot presence is part of the anti-spam verification flow, not a privileged state change.
				if ( ! array_key_exists( $this->context_helper_service()->get_honeypot_field_name( $field_name ), $_POST ) ) {
					$this->rate_limiter_service()->increment_rate_limit( 'failure', $context );

					return new WP_Error( 'asfw_missing_honeypot', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}

				$honeypot = asfw_get_posted_value( $this->context_helper_service()->get_honeypot_field_name( $field_name ) );
				if ( '' !== $honeypot ) {
					$this->rate_limiter_service()->increment_rate_limit( 'failure', $context );

					return new WP_Error( 'asfw_honeypot', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}
			}

			return true;
		}

			public function resolve_expected_context( $expected_context, $field_name = 'asfw' ) {
				$normalized_expected = '';
				if ( null !== $expected_context && '' !== $expected_context ) {
					$normalized_expected = $this->context_helper_service()->normalize_context( $expected_context );
				}

				$posted_context   = asfw_get_posted_value( $this->context_helper_service()->get_context_field_name( $field_name ) );
				$posted_signature = asfw_get_posted_value( $this->context_helper_service()->get_context_signature_field_name( $field_name ) );
				if ( '' !== $posted_context || '' !== $posted_signature ) {
					if ( '' === $posted_context || '' === $posted_signature ) {
						return new WP_Error( 'asfw_missing_context', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
					}

					$normalized_context = $this->context_helper_service()->normalize_context( $posted_context );
					$expected_signature = $this->context_helper_service()->sign_widget_context( $normalized_context, $field_name );
					if ( ! hash_equals( $expected_signature, $posted_signature ) ) {
						return new WP_Error( 'asfw_invalid_context_signature', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
					}

					if ( '' !== $normalized_expected && $normalized_context !== $normalized_expected ) {
						return new WP_Error( 'asfw_context_mismatch', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
					}

					return $normalized_context;
				}

				if ( '' !== $normalized_expected ) {
					return $normalized_expected;
				}

				return new WP_Error( 'asfw_missing_context', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

		public function validate_solution( $payload, $hmac_key = null, $expected_context = null ) {
			if ( null === $hmac_key ) {
				$hmac_key = $this->options_service()->get_secret();
			}

			if ( empty( $hmac_key ) ) {
				return new WP_Error( 'asfw_missing_secret', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$data = $this->decode_payload( $payload );
			if ( $data instanceof WP_Error ) {
				return $data;
			}

			if ( 'SHA-256' !== $data['algorithm'] ) {
				return new WP_Error( 'asfw_invalid_algorithm', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$salt_url = wp_parse_url( $data['salt'] );
			if ( ! is_array( $salt_url ) ) {
				return new WP_Error( 'asfw_invalid_salt', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$salt_params = array();
			if ( ! empty( $salt_url['query'] ) ) {
				parse_str( $salt_url['query'], $salt_params );
			}

			$context      = isset( $salt_params['context'] ) ? $this->context_helper_service()->normalize_context( $salt_params['context'] ) : '';
			$challenge_id = isset( $salt_params['challenge_id'] ) ? sanitize_key( $salt_params['challenge_id'] ) : '';
			if ( '' === $context || '' === $challenge_id ) {
				return new WP_Error( 'asfw_missing_challenge_state', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			if ( null !== $expected_context && '' !== $expected_context && $this->context_helper_service()->normalize_context( $expected_context ) !== $context ) {
				return new WP_Error( 'asfw_context_mismatch', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			if ( ! empty( $salt_params['expires'] ) ) {
				$expires = intval( $salt_params['expires'], 10 );
				if ( $expires > 0 && $expires < time() ) {
					delete_transient( $this->challenge_manager_service()->get_challenge_transient_key( $challenge_id ) );

					return new WP_Error( 'asfw_expired', __( 'Verification expired.', 'anti-spam-for-wordpress' ) );
				}
			}

			$challenge_state = get_transient( $this->challenge_manager_service()->get_challenge_transient_key( $challenge_id ) );
			if ( ! is_array( $challenge_state ) ) {
				return new WP_Error( 'asfw_unknown_challenge', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			if ( ! empty( $challenge_state['context'] ) && $this->context_helper_service()->normalize_context( $challenge_state['context'] ) !== $context ) {
				return new WP_Error( 'asfw_transient_context_mismatch', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			if (
				! empty( $challenge_state['fingerprint'] ) &&
				! hash_equals( (string) $challenge_state['fingerprint'], $this->client_identity_service()->get_client_fingerprint() )
			) {
				return new WP_Error( 'asfw_client_mismatch', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$min_submit_time = $this->options_service()->get_min_submit_time();
			if ( $min_submit_time > 0 && ! empty( $challenge_state['issued_at'] ) ) {
				$issued_at = intval( $challenge_state['issued_at'], 10 );
				if ( $issued_at > 0 ) {
					$elapsed_seconds = 0.0;
					if ( $issued_at > 1000000000000 ) {
						$elapsed_seconds = ( ( microtime( true ) * 1000 ) - $issued_at ) / 1000;
					} else {
						$elapsed_seconds = time() - $issued_at;
					}
				}
				if ( $issued_at > 0 && $elapsed_seconds < $min_submit_time ) {
					return new WP_Error( 'asfw_submitted_too_fast', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}
			}

			if ( ! $this->challenge_manager_service()->acquire_challenge_lock( $challenge_id ) ) {
				return new WP_Error( 'asfw_replay_locked', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$calculated_challenge = hash( 'sha256', $data['salt'] . $data['number'] );
			if ( ! hash_equals( $calculated_challenge, $data['challenge'] ) ) {
				$this->challenge_manager_service()->release_challenge_lock( $challenge_id );

				return new WP_Error( 'asfw_invalid_challenge', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$calculated_signature = hash_hmac( 'sha256', $data['challenge'], $hmac_key );
			if ( ! hash_equals( $calculated_signature, $data['signature'] ) ) {
				$this->challenge_manager_service()->release_challenge_lock( $challenge_id );

				return new WP_Error( 'asfw_invalid_signature', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			delete_transient( $this->challenge_manager_service()->get_challenge_transient_key( $challenge_id ) );
			$this->challenge_manager_service()->release_challenge_lock( $challenge_id );

			return true;
		}

		public function validate_request( $payload, $hmac_key = null, $context = null, $field_name = 'asfw', &$resolved_context = null ) {
			$resolved_context = null;
			if ( $this->options_service()->is_kill_switch_enabled() ) {
				return true;
			}

			$context = $this->resolve_expected_context( $context, $field_name );
			if ( $context instanceof WP_Error ) {
				$fallback_context = $this->context_helper_service()->normalize_context( 'form:' . sanitize_key( (string) $field_name ) );
				if ( '' === $fallback_context ) {
					$fallback_context = 'generic';
				}
				$this->rate_limiter_service()->increment_rate_limit( 'failure', $fallback_context );

				return $context;
			}

			$resolved_context = $context;

			$guard_result = $this->validate_submission_guards( $context, $field_name );
			if ( $guard_result instanceof WP_Error ) {
				return $guard_result;
			}

			$result = $this->validate_solution( $payload, $hmac_key, $context );
			if ( $result instanceof WP_Error ) {
				$this->rate_limiter_service()->increment_rate_limit( 'failure', $context );

				return $result;
			}

			$this->rate_limiter_service()->clear_rate_limit( 'failure', $context );

			return true;
		}

		public function verify( $payload, $hmac_key = null, $context = null, $field_name = 'asfw' ) {
			$resolved_context = null;
			$result           = $this->validate_request( $payload, $hmac_key, $context, $field_name, $resolved_context );
			$success = ! ( $result instanceof WP_Error );

			if ( $success && class_exists( 'ASFW_Control_Plane', false ) ) {
				$disposable_module = ASFW_Control_Plane::disposable_module();
				if ( $disposable_module instanceof ASFW_Disposable_Email_Module ) {
					$analysis_context = '' !== trim( (string) $resolved_context ) ? $resolved_context : $context;
					$analysis         = $disposable_module->analyze_submission( $analysis_context, $_POST );
					if ( ! empty( $analysis['hit'] ) && ! empty( $analysis['blocked'] ) ) {
						$rate_limit_context = $this->context_helper_service()->normalize_context( $analysis_context );
						if ( '' === $rate_limit_context ) {
							$rate_limit_context = 'generic';
						}
						$this->rate_limiter_service()->increment_rate_limit( 'failure', $rate_limit_context );
						$result  = new WP_Error( 'asfw_disposable_email_blocked', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
						$success = false;
					}
				}
			}

			do_action( 'asfw_verify_result', $success, $result, $context, $field_name, $resolved_context );

			return $success;
		}

		public function verify_solution( $payload, $hmac_key = null, $expected_context = null ) {
			return ! ( $this->validate_solution( $payload, $hmac_key, $expected_context ) instanceof WP_Error );
		}
	}
}
