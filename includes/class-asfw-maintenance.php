<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_Maintenance {

	const HOOK               = 'asfw_daily_maintenance';
	const STATE_CLEANUP_HOOK = 'asfw_security_state_cleanup';
	const OPTION_LAST_RUN    = 'asfw_last_maintenance_run';

	protected $store;
	protected $disposable_module;

	public function __construct( ASFW_Event_Store $store, ?ASFW_Disposable_Email_Module $disposable_module = null ) {
		$this->store             = $store;
		$this->disposable_module = $disposable_module;
	}

	public function set_disposable_module( ASFW_Disposable_Email_Module $disposable_module ) {
		$this->disposable_module = $disposable_module;
	}

	public function register_hooks() {
		add_action( self::HOOK, array( $this, 'run' ), 10, 0 );
		add_action( self::STATE_CLEANUP_HOOK, array( $this, 'cleanup_state' ), 10, 0 );
	}

	public function maybe_schedule() {
		if ( function_exists( 'wp_next_scheduled' ) && function_exists( 'wp_schedule_event' ) ) {
			if ( ! wp_next_scheduled( self::HOOK ) ) {
				wp_schedule_event( time() + 300, 'daily', self::HOOK );
			}
		}
	}

	public function unschedule() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::HOOK );
			wp_clear_scheduled_hook( self::STATE_CLEANUP_HOOK );
		}
	}

	/** Drain expired state in bounded queries, including on busy installations. */
	public function cleanup_state() {
		$cleaned = ( new ASFW_Atomic_State_Store() )->cleanup_expired( 1000 );
		if ( 1000 === $cleaned && ! wp_next_scheduled( self::STATE_CLEANUP_HOOK ) ) {
			wp_schedule_single_event( time() + 10, self::STATE_CLEANUP_HOOK );
		}
		return $cleaned;
	}

	public function run() {
		$schema = $this->store->maybe_upgrade_schema();
		if ( is_wp_error( $schema ) ) {
			return $schema;
		}
		$pruned = $this->store->prune_older_than( $this->store->get_retention_days() );
		if ( is_wp_error( $pruned ) ) {
			return $pruned;
		}
		$cleaned = $this->cleanup_state();
		if ( is_wp_error( $cleaned ) ) {
			return $cleaned;
		}
		$refreshed = array(
			'disposable_domains' => 0,
		);

		if ( $this->disposable_module instanceof ASFW_Disposable_Email_Module ) {
			$refreshed['disposable_domains'] = $this->disposable_module->maybe_refresh();
			$error                           = $this->disposable_module->get_last_refresh_error();
			if ( is_wp_error( $error ) ) {
				return $error;
			}
		}

		$summary = array(
			'pruned'    => $pruned,
			'refreshed' => $refreshed,
		);
		update_option( self::OPTION_LAST_RUN, gmdate( 'Y-m-d H:i:s' ) );

		do_action( 'asfw_maintenance_completed', $summary );

		return $summary;
	}
}
