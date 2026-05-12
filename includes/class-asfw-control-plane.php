<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_Control_Plane {

	protected static $initialized = false;
	protected static $store;
	protected static $logger;
	protected static $maintenance;
	protected static $disposable_module;
	protected static $content_module;
	protected static $bunny_module;
	protected static $cli;

	public static function init() {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		self::$store             = new ASFW_Event_Store();
		self::$disposable_module = new ASFW_Disposable_Email_Module( self::$store );
		self::$content_module    = new ASFW_Content_Heuristics_Module( self::$store, self::$disposable_module );
		self::$bunny_module      = new ASFW_Bunny_Shield_Module();
		self::$maintenance       = new ASFW_Maintenance( self::$store, self::$disposable_module );
		self::$logger            = new ASFW_Event_Logger( self::$store );
		self::$cli               = new ASFW_CLI_Command( self::$store, self::$maintenance, self::$disposable_module, self::$bunny_module );

		self::$logger->register_hooks();
		self::$disposable_module->register_hooks();
		self::$content_module->register_hooks();
		self::$bunny_module->register_hooks();
		self::$maintenance->register_hooks();
		self::$maintenance->maybe_schedule();

		if ( class_exists( 'WP_CLI', false ) ) {
			WP_CLI::add_command( 'asfw', self::$cli );
		}

		do_action( 'asfw_control_plane_ready', self::instance() );
	}

	public static function instance() {
		return array(
			'store'             => self::$store,
			'logger'            => self::$logger,
			'maintenance'       => self::$maintenance,
			'disposable_module' => self::$disposable_module,
			'content_module'    => self::$content_module,
			'bunny_module'      => self::$bunny_module,
			'cli'               => self::$cli,
		);
	}

	public static function store() {
		return self::$store;
	}

	public static function logger() {
		return self::$logger;
	}

	public static function maintenance() {
		return self::$maintenance;
	}

	public static function disposable_module() {
		return self::$disposable_module;
	}

	public static function content_module() {
		return self::$content_module;
	}

	public static function bunny_module() {
		return self::$bunny_module;
	}

	public static function cli() {
		return self::$cli;
	}
}
