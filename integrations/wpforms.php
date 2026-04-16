<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( asfw_plugin_active( 'wpforms' ) ) {
	add_action(
		'wpforms_display_submit_before',
		function () {
			$plugin = AntiSpamForWordPressPlugin::$instance;
			$mode   = $plugin->get_integration_wpforms();
			if ( 'captcha' === $mode ) {
				echo wp_kses( asfw_render_widget_markup( $mode, 'wpforms' ), AntiSpamForWordPressPlugin::$html_allowed_tags );
			}
		},
		10,
		1
	);

	add_action(
		'wpforms_process',
		function ( $fields, $entry, $form_data ) {
			$plugin = AntiSpamForWordPressPlugin::$instance;
			$mode   = $plugin->get_integration_wpforms();
			if ( ! empty( $mode ) && 'captcha' === $mode ) {
				if ( asfw_verify_posted_widget( 'wpforms' ) === false ) {
					wpforms()->process->errors[ $form_data['id'] ]['header'] = esc_html__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' );
				}
			}
		},
		10,
		3
	);
}
