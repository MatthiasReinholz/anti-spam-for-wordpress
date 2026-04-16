<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ASFW_Context_Catalog {

	public static function get_contexts() {
		return ASFW_Feature_Registry::get_context_catalog();
	}

	public static function get_context( $context ) {
		return ASFW_Feature_Registry::get_context_catalog_entry( $context );
	}

	public static function normalize( $context ) {
		return ASFW_Feature_Registry::normalize_context( $context );
	}

	public static function build_widget_context( $mode, $name = null, $context = null ) {
		return ASFW_Feature_Registry::build_widget_context( $mode, $name, $context );
	}
}
