<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'wp_enqueue_scripts',
	function () {
			$plugin = AntiSpamForWordPressPlugin::$instance;
			$mode   = $plugin->get_integration_custom();
		if ( 'captcha' === $mode ) {
			wp_enqueue_script(
				'asfw-widget-custom',
				AntiSpamForWordPressPlugin::$custom_script_src,
				array( 'asfw-widget' ),
				asfw_asset_version( 'public/custom.js' ),
				true
			);
			$attrs = wp_json_encode( $plugin->get_widget_attrs( $mode, null, 'asfw', 'custom' ) );
			wp_register_script(
				'asfw-widget-custom-options',
				'',
				array(),
				asfw_asset_version( 'public/custom.js' ),
				false
			);
			wp_enqueue_script( 'asfw-widget-custom-options' );
			wp_add_inline_script(
				'asfw-widget-custom-options',
				"(() => { window.ASFW_WIDGET_ATTRS = $attrs; })();"
			);
		}
	},
	10,
	0
);
