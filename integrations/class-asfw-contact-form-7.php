<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Validate before CF7's optional spam checks and any successful-submit actions. */
final class ASFW_Contact_Form_7 {

	const VALIDATION_FIELD = 'asfw_verification';

	public static function register() {
		add_filter( 'wpcf7_form_elements', 'do_shortcode' );
		add_filter( 'wpcf7_form_elements', array( __CLASS__, 'render' ), 100 );
		add_filter( 'wpcf7_validate', array( __CLASS__, 'validate' ), PHP_INT_MAX );
	}

	public static function render( $elements ) {
		$plugin = asfw_plugin_instance();
		$mode   = $plugin->get_integration_contact_form_7();
		if ( $plugin->is_kill_switch_enabled() || ! in_array( $mode, array( 'captcha', 'shortcode' ), true ) ) {
			return $elements;
		}
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

		// CF7 renders the server's verification error through its usual accessible
		// validation UI. The proof widget is a shortcode, not a CF7 form-tag.
		return $elements . '<span class="wpcf7-form-control-wrap" data-name="' . esc_attr( self::VALIDATION_FIELD ) . '"></span>';
	}

	public static function validate( $result ) {
		$plugin = asfw_plugin_instance();
		if ( ! $result->is_valid() || $plugin->is_kill_switch_enabled() ) {
			return $result;
		}
		$mode = $plugin->get_integration_contact_form_7();
		if ( ! in_array( $mode, array( 'captcha', 'shortcode' ), true ) ) {
			return $result;
		}
		$policy = 'captcha' === $mode ? array(
			'context' => 'contact-form-7',
			'name'    => 'asfw',
		) : ASFW_Contact_Form_Policy::current();
		if ( null === $policy || ! asfw_verify_posted_widget( $policy['context'], $policy['name'] ) ) {
			// Passing a name string would silently fail: CF7 only resolves names of
			// its own registered form-tags. An explicit control is its public API.
			$result->invalidate(
				array(
					'type' => 'asfw',
					'name' => self::VALIDATION_FIELD,
				),
				__( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' )
			);
		}
		return $result;
	}
}
