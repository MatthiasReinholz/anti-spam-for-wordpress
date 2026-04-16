<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ASFW_Integration_Loader {

	public static function bootstrap( $base_dir = null ) {
		$registry = self::build_registry( $base_dir );

		$registry->load();

		return $registry;
	}

	public static function get_bootstrap_paths( $base_dir = null ) {
		return self::build_registry( $base_dir )->get_bootstrap_paths();
	}

	private static function build_registry( $base_dir = null ) {
		$registry = new ASFW_Integration_Registry();

		foreach ( self::get_adapters( $base_dir ) as $adapter ) {
			$registry->register( $adapter );
		}

		return $registry;
	}

	private static function get_adapters( $base_dir = null ) {
		$base_dir = null === $base_dir ? plugin_dir_path( ASFW_FILE ) : rtrim( (string) $base_dir, "/\\" ) . '/';

		return array(
			new ASFW_File_Integration( 'coblocks', $base_dir . 'integrations/class-asfw-plugin-coblocks.php', 10 ),
			new ASFW_File_Integration( 'contact-form-7', $base_dir . 'integrations/contact-form-7.php', 20 ),
			new ASFW_File_Integration( 'custom', $base_dir . 'integrations/custom.php', 30 ),
			new ASFW_File_Integration( 'elementor', $base_dir . 'integrations/elementor.php', 40, 'asfw_bootstrap_elementor_integration' ),
			new ASFW_File_Integration( 'enfold-theme', $base_dir . 'integrations/enfold-theme.php', 50 ),
			new ASFW_File_Integration( 'formidable', $base_dir . 'integrations/formidable.php', 60 ),
			new ASFW_File_Integration( 'forminator', $base_dir . 'integrations/forminator.php', 70 ),
			new ASFW_File_Integration( 'html-forms', $base_dir . 'integrations/html-forms.php', 80 ),
			new ASFW_File_Integration( 'gravityforms', $base_dir . 'integrations/gravityforms.php', 90, 'asfw_bootstrap_gravityforms_integration' ),
			new ASFW_File_Integration( 'wpdiscuz', $base_dir . 'integrations/wpdiscuz.php', 100 ),
			new ASFW_File_Integration( 'wpforms', $base_dir . 'integrations/wpforms.php', 110 ),
			new ASFW_File_Integration( 'woocommerce', $base_dir . 'integrations/woocommerce.php', 120 ),
			new ASFW_File_Integration( 'wordpress', $base_dir . 'integrations/wordpress.php', 130 ),
		);
	}
}
