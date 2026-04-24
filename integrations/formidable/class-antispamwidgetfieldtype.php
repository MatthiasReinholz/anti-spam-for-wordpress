<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Formidable field class name is kept for integration compatibility.
class AntiSpamWidgetFieldType extends FrmFieldType {

	protected $type = 'anti_spam_widget';

	protected $has_input = true;

	protected function field_settings_for_type() {
		$settings            = parent::field_settings_for_type();
		$settings['default'] = true;

		return $settings;
	}

	protected function extra_field_opts() {
		return array();
	}

	protected function include_form_builder_file() {
		return __DIR__ . '/builder-field.php';
	}

	public function displayed_field_type( $field ) {
		return array(
			$this->type => true,
		);
	}

	public function show_extra_field_choices( $args ) {
		include __DIR__ . '/builder-settings.php';
	}

	protected function html5_input_type() {
		return 'text';
	}

	public function validate( $args ) {
		$plugin = AntiSpamForWordPressPlugin::$instance;
		$mode   = $plugin->get_integration_formidable();
		$errors = array();
		if ( ! empty( $mode ) && 'captcha' === $mode ) {
			if ( asfw_verify_posted_widget( 'formidable' ) === false ) {
				$errors[ 'field' . $args['id'] ] = esc_html__( 'Verification failed.', 'anti-spam-for-wordpress' );
			}
		}

		return $errors;
	}

	public function front_field_input( $args, $shortcode_atts ) {
		$plugin = AntiSpamForWordPressPlugin::$instance;
		$mode   = $plugin->get_integration_formidable();
		if ( ! empty( $mode ) && 'captcha' === $mode ) {
			return wp_kses(
				'<div style="flex-basis:100%">' . $plugin->render_widget( $mode, false, null, 'asfw', 'formidable' ) . '</div>',
				AntiSpamForWordPressPlugin::$html_allowed_tags
			);
		}

		return '';
	}
}
