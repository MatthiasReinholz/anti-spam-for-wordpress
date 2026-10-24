<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolve the required proof from saved form configuration, never posted context. */
final class ASFW_Contact_Form_Policy {

	public static function current() {
		$submission = class_exists( 'WPCF7_Submission' ) ? WPCF7_Submission::get_instance() : null;
		$form       = $submission ? $submission->get_contact_form() : null;
		return $form ? self::from_template( (string) $form->prop( 'form' ) ) : null;
	}

	public static function from_template( $template ) {
		$plugin = asfw_plugin_instance();
		if ( ! $plugin ) {
			return null;
		}
		preg_match_all( '/' . get_shortcode_regex( array( 'anti_spam_widget' ) ) . '/s', $template, $matches, PREG_SET_ORDER );
		$widgets = array();
		foreach ( $matches as $match ) {
			if ( '[' === $match[1] && ']' === $match[6] ) {
				continue;
			}
			$attrs = shortcode_atts(
				array(
					'mode'    => $plugin->get_integration_custom(),
					'name'    => 'asfw',
					'context' => null,
				),
				shortcode_parse_atts( $match[3] )
			);
			if ( ! in_array( $attrs['mode'], array( 'captcha', 'shortcode' ), true ) || ! is_string( $attrs['name'] ) || ( null !== $attrs['context'] && ! is_string( $attrs['context'] ) ) ) {
				return null;
			}
			$name = '' !== $attrs['name'] ? sanitize_key( $attrs['name'] ) : 'asfw';
			if ( '' === $name ) {
				return null;
			}
			$widgets[] = array(
				'name'    => $name,
				'context' => $plugin->get_widget_context( $attrs['mode'], $name, $attrs['context'] ),
			);
		}
		return 1 === count( $widgets ) ? $widgets[0] : null;
	}
}
