<?php
declare(strict_types=1);

final class IntegrationBootstrapTest extends AsfwPluginTestCase
{
	public function test_integration_bootstrap_order_is_deterministic(): void
	{
		$root = dirname( __DIR__, 2 ) . '/';

		$this->assertSame(
			array(
				$root . 'integrations/class-asfw-plugin-coblocks.php',
				$root . 'integrations/contact-form-7.php',
				$root . 'integrations/custom.php',
				$root . 'integrations/elementor.php',
				$root . 'integrations/enfold-theme.php',
				$root . 'integrations/formidable.php',
				$root . 'integrations/forminator.php',
				$root . 'integrations/html-forms.php',
				$root . 'integrations/gravityforms.php',
				$root . 'integrations/wpdiscuz.php',
				$root . 'integrations/wpforms.php',
				$root . 'integrations/woocommerce.php',
				$root . 'integrations/wordpress.php',
			),
			ASFW_Integration_Loader::get_bootstrap_paths( $root )
		);
	}

	public function test_bootstrap_returns_registry_instance(): void
	{
		$registry = ASFW_Integration_Loader::bootstrap( dirname( __DIR__, 2 ) . '/' );

		$this->assertInstanceOf( ASFW_Integration_Registry::class, $registry );
	}

	public function test_formidable_autoloader_is_not_registered(): void
	{
		$this->assertFalse( function_exists( 'asfw_forms_autoloader' ) );
	}

	public function test_registry_loads_registered_file_integrations_in_priority_order(): void
	{
		$temp_files = array();
		$calls      = array();

		try {
			$registry = new ASFW_Integration_Registry();

			foreach (
				array(
					array(
						'id'       => 'late',
						'priority' => 20,
					),
					array(
						'id'       => 'early-b',
						'priority' => 10,
					),
					array(
						'id'       => 'early-a',
						'priority' => 10,
					),
				) as $definition
			) {
				$temp_file   = sys_get_temp_dir() . '/asfw-integration-' . uniqid( '', true ) . '.php';
				$temp_files[] = $temp_file;
				file_put_contents( $temp_file, "<?php\n" );

				$integration_id = $definition['id'];
				$registry->register(
					new ASFW_File_Integration(
						$integration_id,
						$temp_file,
						$definition['priority'],
						static function () use ( &$calls, $integration_id ): void {
							$calls[] = $integration_id;
						}
					)
				);
			}

			$registry->load();

			$this->assertSame( array( 'early-a', 'early-b', 'late' ), $calls );
		} finally {
			foreach ( $temp_files as $temp_file ) {
				if ( file_exists( $temp_file ) ) {
					unlink( $temp_file );
				}
			}
		}
	}

	public function test_registry_load_failure_includes_integration_context(): void
	{
		$missing  = sys_get_temp_dir() . '/asfw-missing-' . uniqid( '', true ) . '.php';
		$registry = new ASFW_Integration_Registry();
		$registry->register( new ASFW_File_Integration( 'missing-test', $missing, 10 ) );

		try {
			$registry->load();
			$this->fail( 'Expected RuntimeException for missing integration bootstrap.' );
		} catch ( RuntimeException $error ) {
			$this->assertStringContainsString( 'missing-test', $error->getMessage() );
			$this->assertStringContainsString( $missing, $error->getMessage() );
			$this->assertStringContainsString( 'ASFW integration bootstrap missing or unreadable', $error->getMessage() );
		}
	}

	public function test_registry_wraps_bootstrap_callback_failures_with_context(): void
	{
		$temp_file = sys_get_temp_dir() . '/asfw-integration-' . uniqid( '', true ) . '.php';
		file_put_contents( $temp_file, "<?php\n" );

		$registry = new ASFW_Integration_Registry();
		$registry->register(
			new ASFW_File_Integration(
				'callback-test',
				$temp_file,
				10,
				static function (): void {
					throw new RuntimeException( 'callback failed' );
				}
			)
		);

		try {
			$registry->load();
			$this->fail( 'Expected RuntimeException for failing integration callback.' );
		} catch ( RuntimeException $error ) {
			$this->assertStringContainsString( 'callback-test', $error->getMessage() );
			$this->assertStringContainsString( $temp_file, $error->getMessage() );
			$this->assertStringContainsString( 'callback failed', $error->getMessage() );
			$this->assertStringContainsString( 'RuntimeException', $error->getMessage() );
		} finally {
			if ( file_exists( $temp_file ) ) {
				unlink( $temp_file );
			}
		}
	}
}
