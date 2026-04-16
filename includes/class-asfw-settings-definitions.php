<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ASFW_Settings_Definitions {

	public static function get_sections() {
		return ASFW_Settings_Schema::get_sections();
	}

	public static function get_fields_by_section() {
		return ASFW_Settings_Schema::get_fields_by_section();
	}

	public static function get_registered_settings() {
		return ASFW_Settings_Schema::get_registered_settings();
	}
}
