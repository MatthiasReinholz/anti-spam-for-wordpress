<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_File_Integration extends ASFW_Integration_Adapter_Base implements ASFW_Integration {

	private $bootstrap_callback;

	public function __construct( $id, $bootstrap_path, $priority = 10, $bootstrap_callback = null ) {
		parent::__construct( $id, true, $priority, $bootstrap_path );
		$this->bootstrap_callback = $bootstrap_callback;
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

	public function bootstrap_path(): string {
		return parent::bootstrap_path();
	}

	public function register(): void {
		$bootstrap_path = $this->bootstrap_path();
		if ( ! is_readable( $bootstrap_path ) ) {
			throw new RuntimeException(
				sprintf(
					'ASFW integration bootstrap missing or unreadable for "%s" at %s',
					$this->id(),
					$bootstrap_path
				)
			);
		}

		require_once $bootstrap_path;

		if ( null !== $this->bootstrap_callback ) {
			if ( ! is_callable( $this->bootstrap_callback ) ) {
				throw new RuntimeException(
					sprintf(
						'ASFW integration bootstrap callback for "%s" at %s is not callable',
						$this->id(),
						$bootstrap_path
					)
				);
			}

			call_user_func( $this->bootstrap_callback );
		}
	}

	public function load() {
		$this->register();
	}
}
