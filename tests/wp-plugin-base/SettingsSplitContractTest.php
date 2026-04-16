<?php
declare(strict_types=1);

final class SettingsSplitContractTest extends AsfwPluginTestCase
{
	public function test_split_contract_classes_exist_and_match_schema_outputs(): void
	{
		$this->assertTrue(class_exists('ASFW_Settings_Definitions', false));
		$this->assertTrue(class_exists('ASFW_Settings_Registrar', false));
		$this->assertTrue(class_exists('ASFW_Settings_Renderer', false));
		$this->assertTrue(class_exists('ASFW_Settings_Schema', false));

		$this->assertSame(
			$this->normalizeForComparison(ASFW_Settings_Schema::get_sections()),
			$this->normalizeForComparison(ASFW_Settings_Definitions::get_sections())
		);

		$this->assertSame(
			$this->normalizeForComparison(ASFW_Settings_Schema::get_fields_by_section()),
			$this->normalizeForComparison(ASFW_Settings_Definitions::get_fields_by_section())
		);

		$this->assertSame(
			$this->normalizeForComparison(ASFW_Settings_Schema::get_registered_settings()),
			$this->normalizeForComparison(ASFW_Settings_Definitions::get_registered_settings())
		);
	}

	private function normalizeForComparison($value)
	{
		if (is_array($value)) {
			$normalized = array();
			foreach ($value as $key => $item) {
				$normalized[$key] = $this->normalizeForComparison($item);
			}

			return $normalized;
		}

		if ($value instanceof Closure) {
			return 'closure';
		}

		if (is_object($value)) {
			return get_class($value);
		}

		return $value;
	}
}
