<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'asfw_insert_before_key' ) ) {
	function asfw_insert_before_key( $items, $key, $new_key, $new_value ) {
		$new_array = array();

		foreach ( $items as $k => $v ) {
			if ( $k === $key ) {
				$new_array[ $new_key ] = $new_value;
			}
			$new_array[ $k ] = $v;
		}

		return $new_array;
	}
}

if ( ! function_exists( 'asfw_enfold_theme_add_captcha_field' ) ) {
	function asfw_enfold_theme_add_captcha_field( $elements ) {
		$plugin = AntiSpamForWordPressPlugin::$instance;
		$mode   = $plugin->get_integration_enfold_theme();
		if ( 'captcha' !== $mode ) {
			return $elements;
		}

		$captcha = array(
			'id'      => 'captcha',
			'type'    => 'html',
			'content' => asfw_render_widget_markup( $mode, 'enfold-theme' ),
		);

		return asfw_insert_before_key( $elements, 'av-button', 'captcha', $captcha );
	}
}

add_filter( 'ava_mailchimp_contact_form_elements', 'asfw_enfold_theme_add_captcha_field' );
add_filter( 'avia_contact_form_elements', 'asfw_enfold_theme_add_captcha_field' );

add_filter(
	'avf_form_send',
	function ( $proceed, $new_post, $form_params, $that ) {
		unset( $new_post, $form_params );

		$plugin = AntiSpamForWordPressPlugin::$instance;
		$mode   = $plugin->get_integration_enfold_theme();
		if ( ! empty( $mode ) ) {
			if ( asfw_verify_posted_widget( 'enfold-theme' ) === false ) {
				if ( is_object( $that ) && property_exists( $that, 'submit_error' ) ) {
					$that->submit_error = __( 'Verification failed. Try again later.', 'anti-spam-for-wordpress' );
				}

				return null;
			}
		}

		return $proceed;
	},
	10,
	4
);

add_filter(
	'avf_mailchimp_subscriber_data',
	function ( $data, $that ) {
		unset( $that );

		$plugin = AntiSpamForWordPressPlugin::$instance;
		$mode   = $plugin->get_integration_enfold_theme();
		if ( ! empty( $mode ) ) {
			if ( asfw_verify_posted_widget( 'enfold-theme' ) === false ) {
				$data['email_address'] = 'captcha failed';
				$data['status']        = 'THIS STATUS DOES NOT EXIST';
			}
		}

		return $data;
	},
	10,
	2
);
