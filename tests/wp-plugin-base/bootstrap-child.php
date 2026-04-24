<?php
declare(strict_types=1);

/**
 * Child-owned PHPUnit bootstrap overlay.
 *
 * This file is loaded from tests/bootstrap.php before the managed plugin load
 * hook runs. In WP test mode it is loaded after includes/functions.php, so
 * tests_add_filter() and similar helpers are available for repo-specific test
 * hooks or optional integration bootstrap code. The managed bootstrap scope
 * exposes $plugin_file and $tests_dir when you need them.
 */

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $value ) {
		return esc_url_raw( $value );
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/support/wordpress/' );
}

require_once dirname( __DIR__ ) . '/support/wp-stubs.php';
require_once __DIR__ . '/AsfwPluginTestCase.php';
