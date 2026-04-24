<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_Maintenance {

	const HOOK            = 'asfw_daily_maintenance';
	const OPTION_LAST_RUN = 'asfw_last_maintenance_run';

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
		}
	}

	public function run() {
		$this->store->maybe_upgrade_schema();
		$pruned    = $this->store->prune_older_than( $this->store->get_retention_days() );
		$refreshed = array(
			'disposable_domains' => 0,
		);

		if ( $this->disposable_module instanceof ASFW_Disposable_Email_Module ) {
			$refreshed['disposable_domains'] = $this->disposable_module->maybe_refresh();
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
