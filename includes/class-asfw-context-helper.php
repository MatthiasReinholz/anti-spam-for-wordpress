<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ASFW_Context_Helper', false ) ) {
	class ASFW_Context_Helper {

		private $options_service;

		private function options_service() {
			if ( ! $this->options_service instanceof ASFW_Options ) {
				$this->options_service = new ASFW_Options();
			}

			return $this->options_service;
		}

		public function normalize_context( $context ) {
			return ASFW_Feature_Registry::normalize_context( $context );
		}

		public function get_widget_context( $mode, $name = null, $context = null ) {
			return apply_filters(
				'asfw_widget_context',
				ASFW_Feature_Registry::build_widget_context( $mode, $name, $context ),
				$mode,
				$name
			);
		}

		public function get_started_field_name( $field_name = 'asfw' ) {
			return sanitize_key( $field_name ) . '_started';
		}

		public function get_honeypot_field_name( $field_name = 'asfw' ) {
			return sanitize_key( $field_name ) . '_website';
		}

		public function get_context_field_name( $field_name = 'asfw' ) {
			return sanitize_key( $field_name ) . '_context';
		}

		public function get_context_signature_field_name( $field_name = 'asfw' ) {
			return sanitize_key( $field_name ) . '_context_sig';
		}

		public function sign_widget_context( $context, $field_name = 'asfw' ) {
			$secret = $this->options_service()->get_secret();
			if ( '' === $secret ) {
				$secret = wp_salt( 'nonce' );
			}

			return hash_hmac(
				'sha256',
				$this->normalize_context( $context ) . '|' . sanitize_key( $field_name ),
				$secret
			);
		}
	}
}
