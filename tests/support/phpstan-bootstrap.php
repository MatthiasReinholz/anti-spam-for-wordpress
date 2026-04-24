<?php

namespace {
	if ( ! class_exists( 'WP_CLI', false ) ) {
		class WP_CLI {
			public static function log( $message ) {}

			public static function success( $message ) {}

			public static function warning( $message ) {}

			public static function error( $message ) {}

			public static function add_command( $name, $callable ) {}
		}
	}

	if ( ! class_exists( 'CoBlocks_Form', false ) ) {
		class CoBlocks_Form {
			public const GCAPTCHA_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
		}
	}

	if ( ! class_exists( 'FrmFieldType', false ) ) {
		class FrmFieldType {
			protected $type      = '';
			protected $has_input = false;

			protected function field_settings_for_type() {
				return array();
			}

			protected function extra_field_opts() {
				return array();
			}

			protected function include_form_builder_file() {
				return '';
			}

			public function displayed_field_type( $field ) {
				return array();
			}

			public function show_extra_field_choices( $args ) {}

			protected function html5_input_type() {
				return 'text';
			}

			public function validate( $args ) {
				return array();
			}

			public function front_field_input( $args, $shortcode_atts ) {
				return '';
			}
		}
	}

	if ( ! class_exists( 'GFAddOn', false ) ) {
		class GFAddOn {
			public static function register( $class_name ) {}

			public function pre_init() {}

			public function is_gravityforms_supported() {
				return true;
			}
		}
	}

	if ( ! class_exists( 'GFForms', false ) ) {
		class GFForms {
			public static function include_addon_framework() {}
		}
	}

	if ( ! class_exists( 'GF_Field', false ) ) {
		class GF_Field {
			public $pageNumber         = 1;
			public $failed_validation  = false;
			public $validation_message = '';

			public function is_form_editor() {
				return false;
			}
		}
	}

	if ( ! class_exists( 'GFAPI', false ) ) {
		class GFAPI {
			public static function get_fields_by_type( $form, array $types ) {
				return array();
			}
		}
	}

	if ( ! class_exists( 'GFFormDisplay', false ) ) {
		class GFFormDisplay {
			public static function is_last_page( $form ) {
				return true;
			}
		}
	}

	if ( ! class_exists( 'GF_Fields', false ) ) {
		class GF_Fields {
			public static function register( $field ) {}
		}
	}

	if ( ! function_exists( 'wpforms' ) ) {
		function wpforms() {
			static $wpforms = null;

			if ( null === $wpforms ) {
				$wpforms = (object) array(
					'process' => (object) array(
						'errors' => array(),
					),
				);
			}

			return $wpforms;
		}
	}
}

namespace ElementorPro {
	if ( ! class_exists( Plugin::class, false ) ) {
		class Controls_Manager_Stub {
			/**
			 * @return array|\WP_Error
			 */
			public function get_control_from_stack( string $unique_name, string $control_name ) {
				return array(
					'fields' => array(),
				);
			}
		}

		class Elementor_Instance_Stub {
			/** @var Controls_Manager_Stub */
			public $controls_manager;

			public function __construct() {
				$this->controls_manager = new Controls_Manager_Stub();
			}
		}

		class Plugin {
			public static function elementor(): Elementor_Instance_Stub {
				return new Elementor_Instance_Stub();
			}
		}
	}
}

namespace ElementorPro\Modules\Forms\Fields {
	if ( ! class_exists( Field_Base::class, false ) ) {
		abstract class Field_Base {
			abstract public function get_type();

			abstract public function get_name();
		}
	}
}
