<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'GFAddOn', false ) ) {
	class ASFW_GFFormsAddOn extends GFAddOn {

		// phpcs:disable PSR2.Classes.PropertyDeclaration.Underscore -- Gravity Forms add-ons define these framework properties with leading underscores.
		protected $_version                  = ASFW_VERSION;
		protected $_min_gravityforms_version = '2.5';
		protected $_slug                     = 'anti-spam-for-wordpress';
		protected $_full_path                = __FILE__;
		protected $_short_title              = 'Anti Spam';

		private static $_instance = null;
		// phpcs:enable PSR2.Classes.PropertyDeclaration.Underscore

		public static function get_instance() {
			if ( null === self::$_instance ) {
				self::$_instance = new ASFW_GFFormsAddOn();
			}

			return self::$_instance;
		}

		public function get_menu_icon() {
			return 'dashicons-superhero';
		}

		public function pre_init() {
			parent::pre_init();

			if ( $this->is_gravityforms_supported() && class_exists( 'GF_Field' ) ) {
				require_once __DIR__ . '/class-asfw-gfforms-field.php';
				if ( class_exists( 'GF_Fields' ) ) {
					GF_Fields::register( new ASFW_GFForms_Field() );
				}
			}
		}
	}
}
