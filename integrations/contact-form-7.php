<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-asfw-contact-form-policy.php';

if ( asfw_plugin_active( 'contact-form-7' ) ) {
	add_filter( 'wpcf7_form_elements', 'do_shortcode' );

	add_filter(
		'wpcf7_form_elements',
		function ( $elements ) {
			$plugin = AntiSpamForWordPressPlugin::$instance;
			$mode   = $plugin->get_integration_contact_form_7();
			if ( 'captcha' === $mode ) {
				$input  = '<input class="wpcf7-form-control wpcf7-submit ';
				$button = '<button class="wpcf7-form-control wpcf7-submit ';
				$widget = asfw_render_widget_markup( $mode, 'contact-form-7', 'asfw', true, AntiSpamForWordPressPlugin::$language );
				if ( strpos( $elements, $input ) !== false ) {
					$elements = str_replace( $input, $widget . $input, $elements );
				} elseif ( strpos( $elements, $button ) !== false ) {
					$elements = str_replace( $button, $widget . $button, $elements );
				} else {
					$elements .= $widget;
				}
			}

			return $elements;
		},
		100,
		1
	);

	add_filter(
		'wpcf7_spam',
		function ( $spam ) {
			if ( $spam ) {
				return $spam;
			}

			$plugin = AntiSpamForWordPressPlugin::$instance;
			if ( $plugin->is_kill_switch_enabled() ) {
				return $spam;
			}
			$mode = $plugin->get_integration_contact_form_7();
			if ( ! empty( $mode ) && ( 'captcha' === $mode || 'shortcode' === $mode ) ) {
				if ( 'captcha' === $mode ) {
					return ! asfw_verify_posted_widget( 'contact-form-7' );
				}
				$policy = ASFW_Contact_Form_Policy::current();
				return null === $policy || ! asfw_verify_posted_widget( $policy['context'], $policy['name'] );
			}

			return $spam;
		},
		9,
		1
	);
}
