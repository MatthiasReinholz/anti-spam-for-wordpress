<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'privacy.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-event-store.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-event-logger.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-disposable-email-module.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-maintenance.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-content-heuristics-module.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-bunny-shield-client.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-bunny-shield-module.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-admin-pages.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-cli.php';
require_once plugin_dir_path( __FILE__ ) . 'class-asfw-control-plane.php';

function asfw_initialize_control_plane() {
	static $initialized = false;

	if ( $initialized ) {
		return;
	}

	$initialized = true;
	ASFW_Control_Plane::init();
}
