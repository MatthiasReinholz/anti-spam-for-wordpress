<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function asfw_bootstrap_elementor_integration() {
	if ( ! asfw_plugin_active( 'elementor' ) ) {
		return;
	}

	$plugin = asfw_plugin_instance();
	if ( ! $plugin instanceof AntiSpamForWordPressPlugin ) {
		return;
	}

	$mode   = $plugin->get_integration_elementor();
	if ( 'captcha' !== $mode ) {
		return;
	}

	add_action( 'elementor_pro/forms/fields/register', 'asfw_register_form_field' );
}

function asfw_register_form_field( $form_fields_registrar ) {
	if ( ! class_exists( '\ElementorPro\Modules\Forms\Fields\Field_Base', false ) ) {
		return;
	}

	require_once __DIR__ . '/elementor/class-elementor-form-antispamwidget-field.php';

	if ( ! class_exists( '\Elementor_Form_AntiSpamWidget_Field', false ) ) {
		return;
	}

	$form_fields_registrar->register( new \Elementor_Form_AntiSpamWidget_Field() );
}
