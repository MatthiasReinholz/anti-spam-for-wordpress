<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ASFW_Rate_Limiter', false ) ) {
	class ASFW_Rate_Limiter {

		private $client_identity_service;
		private $options_service;

		private function client_identity_service() {
			if ( ! $this->client_identity_service instanceof ASFW_Client_Identity ) {
				$this->client_identity_service = new ASFW_Client_Identity();
			}

			return $this->client_identity_service;
		}

		private function options_service() {
			if ( ! $this->options_service instanceof ASFW_Options ) {
				$this->options_service = new ASFW_Options();
			}

			return $this->options_service;
		}

		/** Quotas are per client IP, independent of attacker-controlled context and user agent. */
		public function get_rate_limit_key( $type, $context ) {
			unset( $context ); // Retain the public signature; caller-provided contexts cannot partition quotas.
			$secret = $this->options_service()->get_secret();
			if ( '' === $secret ) {
				$secret = wp_salt( 'nonce' );
			}
			$ip       = $this->client_identity_service()->get_client_ip_address();
			$packed   = inet_pton( $ip );
			$identity = false === $packed ? 'unknown' : bin2hex( $packed );
			return 'asfw_rl_' . sanitize_key( $type ) . '_' . hash_hmac( 'sha256', $identity, $secret );
		}

		public function get_rate_limit_limit( $type ) {
			if ( 'challenge' === $type ) {
				return max( 0, $this->options_service()->get_rate_limit_max_challenges() );
			}

			return max( 0, $this->options_service()->get_rate_limit_max_failures() );
		}

		public function get_rate_limit_window_safe() {
			return max( 60, $this->options_service()->get_rate_limit_window() );
		}

		public function get_rate_limit_state( $type, $context ) {
			$state = array(
				'count'  => 0,
				'limit'  => $this->get_rate_limit_limit( $type ),
				'window' => $this->get_rate_limit_window_safe(),
			);
			if ( $state['limit'] <= 0 ) {
				return $state;
			}
			$store    = new ASFW_Atomic_State_Store();
			$snapshot = $store->read( $this->get_rate_limit_key( $type, $context ) );
			if ( $snapshot instanceof WP_Error ) {
				return $snapshot;
			}
			$state['count'] = is_array( $snapshot ) ? (int) $snapshot['value']['count'] : 0;
			return $state;
		}

		private function limited( $type, $context, array $state ) {
			do_action( 'asfw_rate_limited', $type, $context, $state );
			return new WP_Error( 'asfw_rate_limited', __( 'Too many verification attempts. Please wait and try again.', 'anti-spam-for-wordpress' ), array( 'status' => 429 ) );
		}

		public function is_rate_limited( $type, $context ) {
			$state = $this->get_rate_limit_state( $type, $context );
			if ( $state instanceof WP_Error ) {
				return $state;
			}
			return $state['limit'] > 0 && $state['count'] >= $state['limit'] ? $this->limited( $type, $context, $state ) : false;
		}

		/** Reserve before allocating state. A separate check followed by increment is unsafe. */
		public function reserve( $type, $context ) {
			return $this->mutate( $type, $context, true );
		}

		public function increment_rate_limit( $type, $context ) {
			return $this->mutate( $type, $context, false );
		}

		private function mutate( $type, $context, $enforce_limit ) {
			$state = array(
				'count'  => 0,
				'limit'  => $this->get_rate_limit_limit( $type ),
				'window' => $this->get_rate_limit_window_safe(),
			);
			if ( $state['limit'] <= 0 ) {
				return $state;
			}
			$store = new ASFW_Atomic_State_Store();
			$key   = $this->get_rate_limit_key( $type, $context );
			for ( $attempt = 0; $attempt < 8; ++$attempt ) {
				$snapshot = $store->read( $key );
				if ( $snapshot instanceof WP_Error ) {
					return $snapshot;
				}
				$state['count'] = is_array( $snapshot ) ? (int) $snapshot['value']['count'] : 0;
				if ( $enforce_limit && $state['count'] >= $state['limit'] ) {
					return $this->limited( $type, $context, $state );
				}
				++$state['count'];
				$value   = array( 'count' => $state['count'] );
				$updated = null === $snapshot ? $store->create( $key, $value, $state['window'] ) : $store->replace( $key, $snapshot, $value );
				if ( $updated instanceof WP_Error ) {
					return $updated;
				}
				if ( $updated ) {
					return $state;
				}
			}
			return new WP_Error( 'asfw_rate_limit_busy', __( 'Verification is busy. Please try again.', 'anti-spam-for-wordpress' ), array( 'status' => 503 ) );
		}

		public function clear_rate_limit( $type, $context ) {
			$store    = new ASFW_Atomic_State_Store();
			$key      = $this->get_rate_limit_key( $type, $context );
			$snapshot = $store->read( $key );
			if ( is_array( $snapshot ) ) {
				return $store->delete( $key, $snapshot );
			}
			return $snapshot instanceof WP_Error ? $snapshot : true;
		}
	}
}
