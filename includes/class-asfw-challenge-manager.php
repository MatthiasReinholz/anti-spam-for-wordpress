<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ASFW_Challenge_Manager', false ) ) {
	class ASFW_Challenge_Manager {

		private $client_identity_service;
		private $context_helper_service;
		private $options_service;
		private $rate_limiter_service;

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

		public function random_secret() {
			return bin2hex( random_bytes( 32 ) );
		}

		public function get_challenge_transient_key( $challenge_id ) {
			return 'asfw_challenge_' . sanitize_key( $challenge_id );
		}

		public function get_challenge_lock_key( $challenge_id ) {
			return 'asfw_challenge_lock_' . sanitize_key( $challenge_id );
		}

		public function get_math_challenge_id_field_name() {
			return 'asfw_math_challenge';
		}

		public function get_math_challenge_signature_field_name() {
			return 'asfw_math_signature';
		}

		public function get_math_challenge_answer_field_name() {
			return 'asfw_math_answer';
		}

		public function get_submit_delay_token_field_name() {
			return 'asfw_submit_delay_token';
		}

		public function get_submit_delay_signature_field_name() {
			return 'asfw_submit_delay_signature';
		}

		public function get_math_challenge_transient_key( $challenge_id ) {
			return 'asfw_math_' . sanitize_key( (string) $challenge_id );
		}

		public function get_submit_delay_transient_key( $token_id ) {
			return 'asfw_delay_' . sanitize_key( (string) $token_id );
		}

		public function sign_guard_token( $feature, $context, $token_id ) {
			$secret = $this->options_service()->get_secret();
			if ( '' === $secret ) {
				$secret = wp_salt( 'nonce' );
			}

			return hash_hmac(
				'sha256',
				sanitize_key( (string) $feature ) . '|' . $this->context_helper_service()->normalize_context( $context ) . '|' . sanitize_key( (string) $token_id ),
				$secret
			);
		}

		public function acquire_challenge_lock( $challenge_id, $ttl = 30 ) {
			$lock_key   = $this->get_challenge_lock_key( $challenge_id );
			$expires_at = time() + max( 1, intval( $ttl, 10 ) );
			if ( add_option( $lock_key, (string) $expires_at, '', false ) ) {
				return true;
			}

			$existing_expires = intval( (string) get_option( $lock_key, '0' ), 10 );
			if ( $existing_expires >= time() ) {
				return false;
			}

			delete_option( $lock_key );

			return add_option( $lock_key, (string) $expires_at, '', false );
		}

		public function release_challenge_lock( $challenge_id ) {
			delete_option( $this->get_challenge_lock_key( $challenge_id ) );
		}

		public function generate_challenge( $hmac_key = null, $complexity = null, $expires = null, $context = null, $count_against_rate_limit = true ) {
			if ( null === $hmac_key ) {
				$hmac_key = $this->options_service()->get_secret();
			}

			if ( null === $complexity ) {
				$complexity = $this->options_service()->get_complexity();
			}

			if ( null === $expires ) {
				$expires = intval( $this->options_service()->get_expires(), 10 );
			}

			$context = $this->context_helper_service()->normalize_context( $context );
			if ( $count_against_rate_limit ) {
				$rate_limited = $this->rate_limiter_service()->is_rate_limited( 'challenge', $context );
				if ( $rate_limited instanceof WP_Error ) {
					return $rate_limited;
				}
			}

			$challenge_id  = $this->random_secret();
			$salt          = $this->random_secret();
			$transient_ttl = max( 60, $expires > 0 ? $expires : 300 );
			$salt         .= '?' . http_build_query(
				array(
					'challenge_id' => $challenge_id,
					'context'      => $context,
					'expires'      => time() + $transient_ttl,
				)
			);

			if ( ! str_ends_with( $salt, '&' ) ) {
				$salt .= '&';
			}

			switch ( $complexity ) {
				case 'low':
					$min_secret = 25000;
					$max_secret = 50000;
					break;
				case 'medium':
					$min_secret = 100000;
					$max_secret = 200000;
					break;
				case 'high':
					$min_secret = 300000;
					$max_secret = 600000;
					break;
				default:
					$min_secret = 25000;
					$max_secret = 50000;
			}

			$secret_number = random_int( $min_secret, $max_secret );
			$challenge     = hash( 'sha256', $salt . $secret_number );
			$signature     = hash_hmac( 'sha256', $challenge, $hmac_key );
			$issued_at_ms  = (int) round( microtime( true ) * 1000 );
			set_transient(
				$this->get_challenge_transient_key( $challenge_id ),
				array(
					'context'     => $context,
					'fingerprint' => $this->client_identity_service()->get_client_fingerprint(),
					'issued_at'   => $issued_at_ms,
				),
				$transient_ttl
			);
			if ( $count_against_rate_limit ) {
				$this->rate_limiter_service()->increment_rate_limit( 'challenge', $context );
			}

			$challenge_data = array(
				'algorithm' => 'SHA-256',
				'challenge' => $challenge,
				'maxnumber' => $max_secret,
				'salt'      => $salt,
				'signature' => $signature,
			);

			do_action( 'asfw_challenge_issued', $challenge_data, $context, $challenge_id );

			return $challenge_data;
		}

		public function issue_math_challenge( $context ) {
			$left         = random_int( 2, 9 );
			$right        = random_int( 2, 9 );
			$challenge_id = sanitize_key( $this->random_secret() );
			$issued_at_ms = (int) round( microtime( true ) * 1000 );
			$ttl          = 600;

			set_transient(
				$this->get_math_challenge_transient_key( $challenge_id ),
				array(
					'context'     => $this->context_helper_service()->normalize_context( $context ),
					'fingerprint' => $this->client_identity_service()->get_client_fingerprint(),
					'answer'      => (string) ( $left + $right ),
					'issued_at'   => $issued_at_ms,
				),
				$ttl
			);

			return array(
				'challenge_id' => $challenge_id,
				'signature'    => $this->sign_guard_token( 'math_challenge', $context, $challenge_id ),
				'left'         => $left,
				'right'        => $right,
			);
		}

		public function render_math_challenge_fields( $context ) {
			$challenge = $this->issue_math_challenge( $context );
			$field_id  = 'asfw_math_answer_' . substr( $challenge['challenge_id'], 0, 8 );
			$html      = '<p class="asfw-math-challenge">';
			$html     .= '<label for="' . esc_attr( $field_id ) . '">' . esc_html__( 'Security check', 'anti-spam-for-wordpress' ) . ': ' . esc_html( (string) $challenge['left'] ) . ' + ' . esc_html( (string) $challenge['right'] ) . ' = ?</label>';
			$html     .= '<input type="text" class="input asfw-math-answer" inputmode="numeric" autocomplete="off" pattern="[0-9]*" required name="' . esc_attr( $this->get_math_challenge_answer_field_name() ) . '" id="' . esc_attr( $field_id ) . '" value="">';
			$html     .= '<input type="hidden" name="' . esc_attr( $this->get_math_challenge_id_field_name() ) . '" value="' . esc_attr( $challenge['challenge_id'] ) . '">';
			$html     .= '<input type="hidden" name="' . esc_attr( $this->get_math_challenge_signature_field_name() ) . '" value="' . esc_attr( $challenge['signature'] ) . '">';
			$html     .= '</p>';

			return $html;
		}

		public function validate_math_challenge_submission( $context ) {
			$challenge_id = asfw_get_posted_value( $this->get_math_challenge_id_field_name() );
			$signature    = asfw_get_posted_value( $this->get_math_challenge_signature_field_name() );
			$answer       = asfw_get_posted_value( $this->get_math_challenge_answer_field_name() );

			if ( '' === $challenge_id || '' === $signature || '' === $answer ) {
				return new WP_Error( 'asfw_math_missing', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$expected_signature = $this->sign_guard_token( 'math_challenge', $context, $challenge_id );
			if ( ! hash_equals( $expected_signature, $signature ) ) {
				return new WP_Error( 'asfw_math_bad_signature', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$lock_id = 'math_' . sanitize_key( (string) $challenge_id );
			if ( ! $this->acquire_challenge_lock( $lock_id ) ) {
				return new WP_Error( 'asfw_math_replay_locked', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			try {
				$state = get_transient( $this->get_math_challenge_transient_key( $challenge_id ) );
				if ( ! is_array( $state ) ) {
					return new WP_Error( 'asfw_math_unknown', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}

				if ( empty( $state['context'] ) || $this->context_helper_service()->normalize_context( $state['context'] ) !== $this->context_helper_service()->normalize_context( $context ) ) {
					return new WP_Error( 'asfw_math_context_mismatch', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}

				if ( empty( $state['fingerprint'] ) || ! hash_equals( (string) $state['fingerprint'], $this->client_identity_service()->get_client_fingerprint() ) ) {
					return new WP_Error( 'asfw_math_client_mismatch', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}

				if ( ! preg_match( '/^\d+$/', $answer ) ) {
					return new WP_Error( 'asfw_math_invalid', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}

				if ( ! isset( $state['answer'] ) || ! hash_equals( (string) $state['answer'], (string) intval( $answer, 10 ) ) ) {
					return new WP_Error( 'asfw_math_incorrect', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}

				delete_transient( $this->get_math_challenge_transient_key( $challenge_id ) );

				return true;
			} finally {
				$this->release_challenge_lock( $lock_id );
			}
		}

		public function issue_submit_delay_token( $context, $delay_ms ) {
			$token_id     = sanitize_key( $this->random_secret() );
			$issued_at_ms = (int) round( microtime( true ) * 1000 );
			$ttl          = max( 60, (int) ceil( $delay_ms / 1000 ) + 600 );

			set_transient(
				$this->get_submit_delay_transient_key( $token_id ),
				array(
					'context'     => $this->context_helper_service()->normalize_context( $context ),
					'fingerprint' => $this->client_identity_service()->get_client_fingerprint(),
					'issued_at'   => $issued_at_ms,
					'delay_ms'    => intval( $delay_ms, 10 ),
				),
				$ttl
			);

			return array(
				'token_id'     => $token_id,
				'signature'    => $this->sign_guard_token( 'submit_delay', $context, $token_id ),
				'issued_at_ms' => $issued_at_ms,
			);
		}

		public function render_submit_delay_fields( $context, $delay_ms ) {
			$token_url = add_query_arg(
				array(
					'context' => $this->context_helper_service()->normalize_context( $context ),
				),
				get_rest_url( null, '/anti-spam-for-wordpress/v1/submit-delay-token' )
			);
			$html      = '<input type="hidden" name="' . esc_attr( $this->get_submit_delay_token_field_name() ) . '" value="">';
			$html     .= '<input type="hidden" name="' . esc_attr( $this->get_submit_delay_signature_field_name() ) . '" value="">';
			$html     .= '<span class="asfw-submit-delay-status" role="status" aria-live="polite" data-asfw-submit-delay-ms="' . esc_attr( (string) intval( $delay_ms, 10 ) ) . '" data-asfw-submit-delay-mode="' . esc_attr( ASFW_Feature_Registry::active_mode( 'submit_delay' ) ) . '" data-asfw-submit-delay-until="0" data-asfw-submit-delay-token-url="' . esc_url( $token_url ) . '">' . esc_html__( 'Preparing submit...', 'anti-spam-for-wordpress' ) . '</span>';

			return $html;
		}

		public function validate_submit_delay_submission( $context, $delay_ms ) {
			$token_id  = asfw_get_posted_value( $this->get_submit_delay_token_field_name() );
			$signature = asfw_get_posted_value( $this->get_submit_delay_signature_field_name() );
			if ( '' === $token_id || '' === $signature ) {
				return new WP_Error( 'asfw_submit_delay_missing', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$expected_signature = $this->sign_guard_token( 'submit_delay', $context, $token_id );
			if ( ! hash_equals( $expected_signature, $signature ) ) {
				return new WP_Error( 'asfw_submit_delay_bad_signature', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			$lock_id = 'submit_delay_' . sanitize_key( (string) $token_id );
			if ( ! $this->acquire_challenge_lock( $lock_id ) ) {
				return new WP_Error( 'asfw_submit_delay_replay_locked', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
			}

			try {
				$state = get_transient( $this->get_submit_delay_transient_key( $token_id ) );
				if ( ! is_array( $state ) ) {
					return new WP_Error( 'asfw_submit_delay_unknown', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}

				if ( empty( $state['context'] ) || $this->context_helper_service()->normalize_context( $state['context'] ) !== $this->context_helper_service()->normalize_context( $context ) ) {
					return new WP_Error( 'asfw_submit_delay_context_mismatch', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}

				if ( empty( $state['fingerprint'] ) || ! hash_equals( (string) $state['fingerprint'], $this->client_identity_service()->get_client_fingerprint() ) ) {
					return new WP_Error( 'asfw_submit_delay_client_mismatch', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}

				$issued_at_ms = isset( $state['issued_at'] ) ? intval( $state['issued_at'], 10 ) : 0;
				if ( $issued_at_ms <= 0 ) {
					return new WP_Error( 'asfw_submit_delay_missing_issued_at', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}

				$issued_delay_ms = isset( $state['delay_ms'] ) ? intval( $state['delay_ms'], 10 ) : intval( $delay_ms, 10 );
				if ( $issued_delay_ms <= 0 ) {
					return new WP_Error( 'asfw_submit_delay_invalid_delay', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}

				$elapsed_ms = (int) round( microtime( true ) * 1000 ) - $issued_at_ms;
				if ( $elapsed_ms < $issued_delay_ms ) {
					return new WP_Error( 'asfw_submit_delay_too_fast', __( 'Verification failed.', 'anti-spam-for-wordpress' ) );
				}

				delete_transient( $this->get_submit_delay_transient_key( $token_id ) );

				return true;
			} finally {
				$this->release_challenge_lock( $lock_id );
			}
		}
	}
}
