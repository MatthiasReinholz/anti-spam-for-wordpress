<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( asfw_plugin_active( 'html-forms' ) ) {
	add_filter( 'hf_form_html', 'do_shortcode' );

	add_filter(
		'hf_form_html',
		function ( $html ) {
			$plugin = asfw_plugin_instance();
			$mode   = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_html_forms() : '';
			if ( 'captcha' === $mode ) {
				return str_replace( '</form>', asfw_render_widget_markup( $mode, 'html-forms', 'asfw', false ) . '</form>', $html );
			}

			return $html;
		}
	);

			add_filter(
				'hf_validate_form',
				function ( $error_code, $form, $data ) {
					unset( $data );

					$plugin = asfw_plugin_instance();
					$mode   = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_html_forms() : '';
					if ( ! empty( $mode ) ) {
						if ( 'captcha' === $mode || 'shortcode' === $mode ) {
							if ( 'shortcode' === $mode ) {
								$form_markup = (string) $form;
								if (
									false === strpos( $form_markup, '[anti_spam_widget' )
									&& false === strpos( $form_markup, '<asfw-widget' )
								) {
									return $error_code;
								}
							}

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
