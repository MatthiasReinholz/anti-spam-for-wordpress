<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_GFForms_Field extends GF_Field {

	public $type = 'anti_spam_widget';

	public function get_form_editor_field_title() {
		return __( 'Anti Spam Widget', 'anti-spam-for-wordpress' );
	}

	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title(),
		);
	}

	public function get_form_editor_field_settings() {
		return array(
			'label_setting',
			'description_setting',
			'label_placement_setting',
			'error_message_setting',
		);
	}

	public function get_form_editor_field_icon() {
		return 'dashicons-superhero';
	}

	public function is_conditional_logic_supported() {
		return true;
	}

	public function get_field_input( $form, $value = '', $entry = null ) {
		$plugin = asfw_plugin_instance();
		$mode   = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_gravityforms() : '';
		if ( empty( $mode ) ) {
			return '';
		}

		if ( $this->is_form_editor() ) {
			$widget_html  = '<div style="display:flex;gap:1rem;border:1px solid lightgray;max-width:260px;padding:1em;border-radius:4px;font-size:80%">';
			$widget_html .= '<div><span class="dashicons-before dashicons-superhero"></span></div>';
			$widget_html .= '<div><span>' . esc_html__( 'Anti Spam Widget placeholder', 'anti-spam-for-wordpress' ) . '</span></div>';
			$widget_html .= '</div>';
		} else {
			$widget_html = asfw_render_widget_markup( $mode, 'gravityforms', 'asfw', false );
		}

		return sprintf( "<div class='ginput_container ginput_container_%s gfield--type-html'>%s</div>", $this->type, $widget_html );
	}

	private function is_on_last_page( $form ) {
		$pages = GFAPI::get_fields_by_type( $form, array( 'page' ) );

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Gravity Forms defines this property name.
		return count( $pages ) + 1 === (int) $this->pageNumber;
	}

	public function validate( $value, $form ) {
		if ( GFFormDisplay::is_last_page( $form ) && ! $this->is_on_last_page( $form ) ) {
			return;
		}

		$plugin = asfw_plugin_instance();
		$mode   = $plugin instanceof AntiSpamForWordPressPlugin ? $plugin->get_integration_gravityforms() : '';
		if ( ! empty( $mode ) && 'captcha' === $mode ) {
			if ( asfw_verify_posted_widget( 'gravityforms' ) === false ) {
				$this->failed_validation  = true;
				$this->validation_message = __( 'Could not verify you are not a robot.', 'anti-spam-for-wordpress' );
			}
		}
	}
}
