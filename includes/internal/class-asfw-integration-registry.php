<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_Integration_Registry {

	/**
	 * @var array<string, ASFW_Integration_Adapter>
	 */
	private $integrations = array();

	public function register( ASFW_Integration_Adapter $integration ) {
		$this->integrations[ $this->integration_id( $integration ) ] = $integration;

		return $this;
	}

	/**
	 * @return array<string, ASFW_Integration_Adapter>
	 */
	public function all() {
		$integrations = $this->integrations;

		uasort(
			$integrations,
			function ( ASFW_Integration_Adapter $left, ASFW_Integration_Adapter $right ) {
				if ( $this->integration_priority( $left ) === $this->integration_priority( $right ) ) {
					return strcmp( $this->integration_id( $left ), $this->integration_id( $right ) );
				}

				return $this->integration_priority( $left ) <=> $this->integration_priority( $right );
			}
		);

		return $integrations;
	}

	public function load() {
		foreach ( $this->all() as $integration ) {
			try {
				if ( method_exists( $integration, 'is_available' ) && ! $integration->is_available() ) {
					continue;
				}

				$integration->register();
			} catch ( Throwable $error ) {
				$message = sprintf(
					'Failed loading ASFW integration "%s" from %s: %s: %s',
					$this->integration_id( $integration ),
					$this->integration_bootstrap_path( $integration ),
					get_class( $error ),
					$error->getMessage()
				);

				throw new RuntimeException(
					esc_html( $message ),
					0,
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Previous exception object is chained, not output.
					$error
				);
			}
		}

		return $this;
	}

	public function get_bootstrap_paths() {
		$paths = array();

		foreach ( $this->all() as $integration ) {
			$bootstrap_path = $this->integration_bootstrap_path( $integration );
			if ( '' !== $bootstrap_path ) {
				$paths[] = $bootstrap_path;
			}
		}

		return $paths;
	}

	private function integration_id( ASFW_Integration_Adapter $integration ) {
		return $integration->id();
	}

	private function integration_priority( ASFW_Integration_Adapter $integration ) {
		if ( method_exists( $integration, 'priority' ) ) {
			return (int) $integration->priority();
		}

		if ( method_exists( $integration, 'get_priority' ) ) {
			return (int) $integration->get_priority();
		}

		return 10;
	}

	private function integration_bootstrap_path( ASFW_Integration_Adapter $integration ) {
		if ( method_exists( $integration, 'bootstrap_path' ) ) {
			return (string) $integration->bootstrap_path();
		}

		if ( method_exists( $integration, 'get_bootstrap_path' ) ) {
			return (string) $integration->get_bootstrap_path();
		}

		return '';
	}
}
