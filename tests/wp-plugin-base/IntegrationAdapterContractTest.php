<?php
declare(strict_types=1);

if ( ! class_exists( 'ASFW_Integration_Adapter_Contract_Test_Recorder', false ) ) {
	final class ASFW_Integration_Adapter_Contract_Test_Recorder {

		/** @var array<int, string> */
		public static $calls = array();

		public static function reset(): void {
			self::$calls = array();
		}

		public static function record( string $label ): void {
			self::$calls[] = $label;
		}
	}
}

if ( ! function_exists( 'asfw_integration_adapter_contract_test_function_bootstrap' ) ) {
	function asfw_integration_adapter_contract_test_function_bootstrap( string $label = 'function' ): void {
		ASFW_Integration_Adapter_Contract_Test_Recorder::record( $label );
	}
}

if ( ! class_exists( 'ASFW_Integration_Adapter_Contract_Test_Class', false ) ) {
	final class ASFW_Integration_Adapter_Contract_Test_Class {

		public static function bootstrap( string $label = 'class' ): void {
			ASFW_Integration_Adapter_Contract_Test_Recorder::record( $label );
		}
	}
}

final class IntegrationAdapterContractTest extends AsfwPluginTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		ASFW_Integration_Adapter_Contract_Test_Recorder::reset();
	}

	public function test_closure_based_adapter_registers_via_registry(): void
	{
		$registry = new ASFW_Integration_Registry();
		$registry->register(
			new class( 'closure-based', true, 10, function (): void {
				ASFW_Integration_Adapter_Contract_Test_Recorder::record( 'closure' );
			} ) extends ASFW_Integration_Adapter_Base {

				/** @var callable */
				private $callback;

				public function __construct( $adapter_id, $available, $priority, callable $callback ) {
					parent::__construct( $adapter_id, $available, $priority );
					$this->callback = $callback;
				}

				public function register(): void {
					$callback = $this->callback;
					$callback();
				}
			}
		);

		$registry->load();

		$this->assertSame( array( 'closure' ), ASFW_Integration_Adapter_Contract_Test_Recorder::$calls );
	}

	public function test_function_based_adapter_registers_via_registry(): void
	{
		$registry = new ASFW_Integration_Registry();
		$registry->register(
			new class( 'function-based', true, 10 ) extends ASFW_Integration_Adapter_Base {
				public function register(): void {
					asfw_integration_adapter_contract_test_function_bootstrap( 'function' );
				}
			}
		);

		$registry->load();

		$this->assertSame( array( 'function' ), ASFW_Integration_Adapter_Contract_Test_Recorder::$calls );
	}

	public function test_class_based_adapter_registers_via_registry(): void
	{
		$registry = new ASFW_Integration_Registry();
		$registry->register(
			new class( 'class-based', true, 10 ) extends ASFW_Integration_Adapter_Base {
				public function register(): void {
					ASFW_Integration_Adapter_Contract_Test_Class::bootstrap( 'class' );
				}
			}
		);

		$registry->load();

		$this->assertSame( array( 'class' ), ASFW_Integration_Adapter_Contract_Test_Recorder::$calls );
	}

	public function test_registry_keeps_deterministic_order_across_adapter_styles(): void
	{
		$registry = new ASFW_Integration_Registry();

		$registry->register(
			new class( 'late-closure', true, 30, function (): void {
				ASFW_Integration_Adapter_Contract_Test_Recorder::record( 'closure-late' );
			} ) extends ASFW_Integration_Adapter_Base {

				/** @var callable */
				private $callback;

				public function __construct( $adapter_id, $available, $priority, callable $callback ) {
					parent::__construct( $adapter_id, $available, $priority );
					$this->callback = $callback;
				}

				public function register(): void {
					$callback = $this->callback;
					$callback();
				}
			}
		);

		$registry->register(
			new class( 'early-function', true, 10 ) extends ASFW_Integration_Adapter_Base {
				public function register(): void {
					asfw_integration_adapter_contract_test_function_bootstrap( 'function-early' );
				}
			}
		);

		$registry->register(
			new class( 'middle-class', true, 20 ) extends ASFW_Integration_Adapter_Base {
				public function register(): void {
					ASFW_Integration_Adapter_Contract_Test_Class::bootstrap( 'class-middle' );
				}
			}
		);

		$registry->load();

		$this->assertSame(
			array( 'function-early', 'class-middle', 'closure-late' ),
			ASFW_Integration_Adapter_Contract_Test_Recorder::$calls
		);
	}
}
