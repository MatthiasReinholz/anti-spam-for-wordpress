<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_CLI_Command {

	protected $store;
	protected $maintenance;
	protected $disposable_module;
	protected $bunny_module;

	public function __construct( ASFW_Event_Store $store, ASFW_Maintenance $maintenance, ASFW_Disposable_Email_Module $disposable_module, $bunny_module = null ) {
		$this->store             = $store;
		$this->maintenance       = $maintenance;
		$this->disposable_module = $disposable_module;
		$this->bunny_module      = $bunny_module instanceof ASFW_Bunny_Shield_Module ? $bunny_module : null;
	}

	protected function cli_log( $message ) {
		if ( class_exists( 'WP_CLI', false ) ) {
			WP_CLI::log( (string) $message );
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP-CLI output must remain raw so JSON responses stay machine-readable.
		echo (string) $message . PHP_EOL;
	}

	protected function cli_success( $message ) {
		if ( class_exists( 'WP_CLI', false ) ) {
			WP_CLI::success( $message );
			return;
		}

		$this->cli_log( $message );
	}

	protected function cli_warning( $message ) {
		if ( class_exists( 'WP_CLI', false ) ) {
			WP_CLI::warning( $message );
			return;
		}

		$this->cli_log( $message );
	}

	protected function cli_error( $message ) {
		if ( class_exists( 'WP_CLI', false ) ) {
			WP_CLI::error( $message );
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI exceptions must preserve raw messages for scripts and tests.
		throw new RuntimeException( (string) $message );
	}

	protected function yes_requested( array $assoc_args ) {
		return ! empty( $assoc_args['yes'] );
	}

	protected function get_feature_status_rows() {
		$rows = array();

		foreach ( ASFW_Feature_Registry::definitions() as $feature_id => $definition ) {
			if ( ! is_array( $definition ) ) {
				continue;
			}

			$rows[] = array(
				'id'          => $feature_id,
				'label'       => isset( $definition['label'] ) ? (string) $definition['label'] : $feature_id,
				'enabled'     => ASFW_Feature_Registry::is_enabled( $feature_id ),
				'mode'        => ASFW_Feature_Registry::mode( $feature_id ),
				'active_mode' => ASFW_Feature_Registry::active_mode( $feature_id ),
				'scope_mode'  => ASFW_Feature_Registry::scope_mode( $feature_id ),
				'contexts'    => ASFW_Feature_Registry::selected_contexts( $feature_id ),
				'background'  => ASFW_Feature_Registry::background_enabled( $feature_id ),
			);
		}

		return $rows;
	}

	public function status( array $args, array $assoc_args ) {
		unset( $args, $assoc_args );

		$status = array(
			'store'    => array(
				'table'          => $this->store->get_table_name(),
				'events'         => $this->store->count_events(),
				'retention_days' => $this->store->get_retention_days(),
			),
			'features' => $this->get_feature_status_rows(),
		);

		$this->cli_log( wp_json_encode( $status ) );

		return $status;
	}

	public function feature( array $args, array $assoc_args ) {
		unset( $assoc_args );

		$subcommand = isset( $args[0] ) ? $args[0] : 'list';

		switch ( $subcommand ) {
			case 'list':
				$list = $this->get_feature_status_rows();
				$this->cli_log( wp_json_encode( $list ) );
				return $list;

			default:
				$this->cli_error( 'Unknown asfw feature subcommand.' );
		}
	}

	public function events( array $args, array $assoc_args ) {
		$subcommand = isset( $args[0] ) ? $args[0] : 'list';

		switch ( $subcommand ) {
			case 'list':
				$events = $this->store->fetch_events(
					array(
						'limit'  => isset( $assoc_args['limit'] ) ? intval( $assoc_args['limit'], 10 ) : 20,
						'type'   => isset( $assoc_args['type'] ) ? $assoc_args['type'] : '',
						'module' => isset( $assoc_args['module'] ) ? $assoc_args['module'] : '',
						'status' => isset( $assoc_args['status'] ) ? $assoc_args['status'] : '',
					)
				);
					$this->cli_log( wp_json_encode( $events ) );
				return $events;

			case 'prune':
				if ( ! $this->yes_requested( $assoc_args ) ) {
					$this->cli_error( 'Refusing to prune events without --yes.' );
				}

				$days   = isset( $assoc_args['older-than'] ) ? intval( $assoc_args['older-than'], 10 ) : ( isset( $assoc_args['days'] ) ? intval( $assoc_args['days'], 10 ) : $this->store->get_retention_days() );
				$pruned = $this->store->prune_older_than( $days );
				$this->cli_success( sprintf( 'Pruned %d events older than %d days.', $pruned, $days ) );
				return $pruned;

			case 'purge':
				if ( ! $this->yes_requested( $assoc_args ) ) {
					$this->cli_error( 'Refusing to purge events without --yes.' );
				}

				if ( isset( $assoc_args['older-than'] ) ) {
					$days   = intval( $assoc_args['older-than'], 10 );
					$pruned = $this->store->prune_older_than( $days );
					$this->cli_success( sprintf( 'Pruned %d events older than %d days.', $pruned, $days ) );
					return $pruned;
				}

				$deleted = $this->store->purge_all();
				$this->cli_success( sprintf( 'Deleted %d events.', $deleted ) );
				return $deleted;

			default:
				$this->cli_error( 'Unknown asfw events subcommand.' );
		}
	}

	public function disposable( array $args, array $assoc_args ) {
		$subcommand = isset( $args[0] ) ? $args[0] : 'status';

		switch ( $subcommand ) {
			case 'status':
				$status = array(
					'count'        => count( $this->disposable_module->get_domains() ),
					'last_refresh' => $this->disposable_module->get_last_refresh(),
				);
				$this->cli_log( wp_json_encode( $status ) );
				return $status;

			case 'refresh':
				if ( ! $this->yes_requested( $assoc_args ) ) {
					$this->cli_error( 'Refusing to refresh disposable domains without --yes.' );
				}

				$domains = $this->disposable_module->refresh_from_source( true );
				$this->cli_success( sprintf( 'Refreshed %d disposable domains.', count( $domains ) ) );
				return $domains;

			default:
				$this->cli_error( 'Unknown asfw disposable subcommand.' );
		}
	}

	public function disposable_email( array $args, array $assoc_args ) {
		$subcommand = isset( $args[0] ) ? $args[0] : 'status';

		switch ( $subcommand ) {
			case 'status':
				return $this->disposable( array( 'status' ), $assoc_args );

			case 'refresh':
				if ( ! $this->yes_requested( $assoc_args ) ) {
					$this->cli_error( 'Refusing to refresh disposable domains without --yes.' );
				}

				return $this->disposable( array( 'refresh' ), $assoc_args );

			default:
				$this->cli_error( 'Unknown asfw disposable-email subcommand.' );
		}
	}

	public function maintenance( array $args, array $assoc_args ) {
		$subcommand = isset( $args[0] ) ? $args[0] : 'run';

		switch ( $subcommand ) {
			case 'run':
				if ( ! $this->yes_requested( $assoc_args ) ) {
					$this->cli_error( 'Refusing to run maintenance without --yes.' );
				}
				$summary = $this->maintenance->run();
				$this->cli_log( wp_json_encode( $summary ) );
				return $summary;

			default:
				$this->cli_error( 'Unknown asfw maintenance subcommand.' );
		}
	}

	public function bunny( array $args, array $assoc_args ) {
		$subcommand = isset( $args[0] ) ? $args[0] : 'status';

		if ( ! ( $this->bunny_module instanceof ASFW_Bunny_Shield_Module ) ) {
			$this->cli_error( 'Bunny Shield module is not available.' );
		}

		switch ( $subcommand ) {
			case 'status':
				$status = $this->bunny_module->get_status();
				$this->cli_log( wp_json_encode( $status ) );
				return $status;

			case 'revoke':
				$ip = isset( $args[1] ) ? $args[1] : '';
				if ( '' === trim( (string) $ip ) ) {
					$this->cli_error( 'Usage: wp asfw bunny revoke <ip> --yes' );
				}
				if ( ! $this->yes_requested( $assoc_args ) ) {
					$this->cli_error( 'Refusing to revoke access list entries without --yes.' );
				}

				$result = $this->bunny_module->revoke_ip( $ip, true );
				if ( is_wp_error( $result ) ) {
					$this->cli_error( implode( ' ', $result->get_error_messages() ) );
				}

				$this->cli_success( sprintf( 'Processed Bunny Shield revoke for %s.', $ip ) );
				return $result;

			default:
				$this->cli_error( 'Unknown asfw bunny subcommand.' );
		}
	}
}
