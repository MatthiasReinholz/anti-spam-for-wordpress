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

			public function get_rate_limit_key( $type, $context ) {
				$context_key = (string) ASFW_Feature_Registry::normalize_context( $context );

				return 'asfw_rl_' . sanitize_key( $type ) . '_' . md5( $this->client_identity_service()->get_client_fingerprint() . '|' . $context_key );
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
			$limit = $this->get_rate_limit_limit( $type );
			if ( $limit <= 0 ) {
				return array(
					'count'  => 0,
					'limit'  => 0,
					'window' => $this->get_rate_limit_window_safe(),
				);
			}

			$bucket = get_transient( $this->get_rate_limit_key( $type, $context ) );
			if ( ! is_array( $bucket ) ) {
				$bucket = array(
					'count' => 0,
				);
			}

			return array(
				'count'  => isset( $bucket['count'] ) ? intval( $bucket['count'], 10 ) : 0,
				'limit'  => $limit,
				'window' => $this->get_rate_limit_window_safe(),
			);
		}

		public function is_rate_limited( $type, $context ) {
			$state = $this->get_rate_limit_state( $type, $context );
			if ( $state['limit'] <= 0 ) {
				return false;
			}

			if ( $state['count'] >= $state['limit'] ) {
				do_action( 'asfw_rate_limited', $type, $context, $state );

				return new WP_Error(
					'asfw_rate_limited',
					__( 'Too many verification attempts. Please wait and try again.', 'anti-spam-for-wordpress' ),
					array( 'status' => 429 )
				);
			}

			return false;
		}

		public function increment_rate_limit( $type, $context ) {
			$state = $this->get_rate_limit_state( $type, $context );
			if ( $state['limit'] <= 0 ) {
				return $state;
			}

			++$state['count'];
			set_transient(
				$this->get_rate_limit_key( $type, $context ),
				array( 'count' => $state['count'] ),
				$state['window']
			);

			return $state;
		}

		public function clear_rate_limit( $type, $context ) {
			delete_transient( $this->get_rate_limit_key( $type, $context ) );
		}
	}
}
