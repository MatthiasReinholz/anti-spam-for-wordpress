<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function asfw_bootstrap_gravityforms_integration() {
	if ( ! asfw_plugin_active( 'gravityforms' ) ) {
		return;
	}

	$plugin = asfw_plugin_instance();
	if ( ! $plugin instanceof AntiSpamForWordPressPlugin ) {
		return;
	}

	$mode = $plugin->get_integration_gravityforms();
	if ( 'captcha' !== $mode ) {
		return;
	}

	$bootstrap_addon = static function () {
		if ( ! class_exists( 'GFForms', false ) ) {
			return;
		}

		GFForms::include_addon_framework();
		require_once __DIR__ . '/gravityforms/class-asfw-gfformsaddon.php';
		GFAddOn::register( 'ASFW_GFFormsAddOn' );
	};

	if ( did_action( 'gform_loaded' ) ) {
		$bootstrap_addon();
		return;
	}

	add_action( 'gform_loaded', $bootstrap_addon, 5 );
}
