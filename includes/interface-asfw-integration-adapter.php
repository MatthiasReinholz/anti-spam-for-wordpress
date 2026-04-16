<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( 'ASFW_Integration_Adapter', false ) ) {
	interface ASFW_Integration_Adapter {

		public function id(): string;

		public function is_available(): bool;

		public function register(): void;
	}
}
