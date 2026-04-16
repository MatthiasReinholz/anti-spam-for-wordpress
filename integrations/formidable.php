<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function asfw_load_formidable_field() {
	spl_autoload_register( 'asfw_forms_autoloader' );
}
add_action( 'plugins_loaded', 'asfw_load_formidable_field' );

function asfw_forms_autoloader( $class_name ) {
	if ( 1 !== preg_match( '/^AntiSpam.+$/', $class_name ) ) {
		return;
	}

	$filepath = __DIR__ . '/formidable/class-' . strtolower( $class_name ) . '.php';

	if ( file_exists( $filepath ) ) {
		require $filepath;
	}
}

function asfw_get_field_type_class( $class_name, $field_type ) {
	if ( 'anti_spam_widget' === $field_type ) {
		$class_name = 'AntiSpamWidgetFieldType';
	}

	return $class_name;
}
add_filter( 'frm_get_field_type_class', 'asfw_get_field_type_class', 10, 2 );

function asfw_add_new_field( $fields ) {
	$fields['anti_spam_widget'] = array(
		'name' => __( 'Anti Spam Widget', 'anti-spam-for-wordpress' ),
		'icon' => 'frm_icon_font frm_shield_check_icon',
	);

	return $fields;
}
add_filter( 'frm_available_fields', 'asfw_add_new_field' );
