<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( asfw_plugin_active( 'elementor' ) ) {
	function asfw_register_form_field( $form_fields_registrar ) {
		require_once __DIR__ . '/elementor/class-elementor-form-antispamwidget-field.php';

		$form_fields_registrar->register( new \Elementor_Form_AntiSpamWidget_Field() );
	}

	$asfw_plugin = AntiSpamForWordPressPlugin::$instance;
	$asfw_mode   = $asfw_plugin->get_integration_elementor();
	if ( 'captcha' === $asfw_mode ) {
		add_action( 'elementor_pro/forms/fields/register', 'asfw_register_form_field' );
	}
}
