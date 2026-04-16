<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'ASFW_Integration_Adapter_Base', false ) ) {
	class ASFW_Integration_Adapter_Base implements ASFW_Integration_Adapter {

		protected $adapter_id = '';

		protected $available = true;

		protected $priority = 10;

		protected $bootstrap_path = '';

		public function __construct( $adapter_id = '', $available = true, $priority = 10, $bootstrap_path = '' ) {
			$this->adapter_id = sanitize_key( (string) $adapter_id );
			$this->available  = (bool) $available;
			$this->priority   = (int) $priority;
			$this->bootstrap_path = (string) $bootstrap_path;
		}

		public function id(): string {
			return $this->adapter_id;
		}

		public function is_available(): bool {
			return $this->available;
		}

		public function priority(): int {
			return $this->priority;
		}

		public function bootstrap_path(): string {
			return $this->bootstrap_path;
		}

		public function get_id() {
			return $this->id();
		}

		public function get_priority() {
			return $this->priority();
		}

		public function get_bootstrap_path() {
			return $this->bootstrap_path();
		}

		public function load() {
			$this->register();
		}

		public function register(): void {}

		protected function render_widget( string $mode, string $context, string $field_name = 'asfw' ): string {
			return asfw_render_widget_markup( $mode, $context, $field_name, true );
		}

		protected function verify_widget( string $context, string $field_name = 'asfw' ): bool {
			return asfw_verify_posted_widget( $context, $field_name );
		}

		protected function reject_message(): string {
			return __( 'Verification failed.', 'anti-spam-for-wordpress' );
		}
	}
}
