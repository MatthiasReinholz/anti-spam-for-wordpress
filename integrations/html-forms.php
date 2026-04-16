<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( asfw_plugin_active( 'html-forms' ) ) {
	add_filter( 'hf_form_html', 'do_shortcode' );

	add_filter(
		'hf_form_html',
		function ( $html ) {
			$plugin = AntiSpamForWordPressPlugin::$instance;
			$mode   = $plugin->get_integration_html_forms();
			if ( 'captcha' === $mode ) {
				return str_replace( '</form>', asfw_render_widget_markup( $mode, 'html-forms', 'asfw', false ) . '</form>', $html );
			}

			return $html;
		}
	);

		add_filter(
			'hf_validate_form',
			function ( $error_code, $form, $data ) {
				unset( $form, $data );

				$plugin = AntiSpamForWordPressPlugin::$instance;
				$mode   = $plugin->get_integration_html_forms();
				if ( ! empty( $mode ) ) {
					if ( 'captcha' === $mode || 'shortcode' === $mode ) {
						if ( false === asfw_verify_posted_widget( 'captcha' === $mode ? 'html-forms' : null ) ) {
							return 'asfw_invalid';
						}
					}
				}

				return $error_code;
			},
			10,
			3
		);

		add_filter(
			'hf_form_message_asfw_invalid',
			function ( $message ) {
				unset( $message );

				return __( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' );
			}
		);
}
