<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ASFW_Settings_Renderer {

	public static function register_settings_section( array $section ) {
		add_settings_section(
			$section['id'],
			$section['title'],
			$section['callback'],
			'asfw_admin'
		);
	}

	public static function register_settings_field( array $field ) {
		add_settings_field(
			$field['id'],
			$field['title'],
			$field['callback'],
			'asfw_admin',
			$field['section'],
			$field['args']
		);
	}
}
