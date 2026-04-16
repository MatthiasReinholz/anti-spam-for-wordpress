<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface ASFW_Integration {

	public function get_id();

	public function get_priority();

	public function get_bootstrap_path();

	public function load();
}
