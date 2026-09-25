<?php
declare(strict_types=1);

final class SettingsExtensionTest extends AsfwPluginTestCase
{
    private array $extensionFilters = array();

    protected function tearDown(): void
    {
        foreach ($this->extensionFilters as [$hook, $callback]) {
            remove_filter($hook, $callback, 10);
        }
        parent::tearDown();
    }

    public function test_custom_section_fields_follow_filtered_order_in_both_settings_interfaces(): void
    {
        $this->filter('asfw_settings_schema_sections', static function (array $sections): array {
            array_splice($sections, 1, 0, array(array(
                'id' => 'asfw_extension_section',
                'title' => 'Site protection',
                'callback' => '',
            )));
            return $sections;
        });
        $this->filter('asfw_settings_schema_fields', static function (array $fields): array {
            $fields['asfw_extension_section'] = array(array(
                'id' => 'asfw_extension_field',
                'section' => 'asfw_extension_section',
                'title' => 'Site setting',
                'option' => 'asfw_extension_option',
                'sanitize_callback' => 'sanitize_text_field',
                'callback' => 'asfw_settings_field_callback',
                'args' => array('name' => 'asfw_extension_option', 'type' => 'text'),
            ));
            return $fields;
        });

        asfw_settings_init();

        $this->assertSame('asfw_extension_section', $GLOBALS['asfw_test_settings_sections'][1]['id']);
        $fieldSections = array_values(array_unique(array_column($GLOBALS['asfw_test_settings_fields'], 'section')));
        $this->assertSame('asfw_extension_section', $fieldSections[1]);
        $this->assertContains('asfw_extension_option', array_column($GLOBALS['asfw_test_registered_settings'], 'option_name'));

        $payload = $this->settingsPayload();
        $this->assertSame('asfw_extension_section', $payload['sections'][1]['id']);
        $this->assertSame('asfw_extension_option', $payload['sections'][1]['fields'][0]['option']);
    }

    public function test_feature_with_custom_section_metadata_retains_its_title_and_fields(): void
    {
        $this->addFeature('extension_one');
        $this->filter('asfw_settings_schema_sections', static function (array $sections): array {
            $sections[] = array('id' => 'asfw_extension_section', 'title' => 'Explicit section title', 'callback' => '');
            return $sections;
        });

        asfw_settings_init();

        $sections = array_column($GLOBALS['asfw_test_settings_sections'], null, 'id');
        $this->assertSame('Explicit section title', $sections['asfw_extension_section']['title']);
        $this->assertCount(7, $sections);
        $this->assertContains('asfw_settings_extension_one_enabled_field', array_column($GLOBALS['asfw_test_settings_fields'], 'id'));
        $payload = $this->settingsPayload();
        $extension = array_column($payload['sections'], null, 'id')['asfw_extension_section'];
        $this->assertCount(4, $extension['fields']);
        $this->assertSame('asfw_extension_one_enabled', $extension['fields'][0]['option']);
    }

    public function test_features_without_section_metadata_remain_visible_in_one_fallback_section(): void
    {
        $this->addFeature('extension_one');
        $this->addFeature('extension_two');

        asfw_settings_init();

        $sections = $GLOBALS['asfw_test_settings_sections'];
        $this->assertCount(7, $sections);
        $this->assertSame('asfw_extension_section', $sections[6]['id']);
        $this->assertSame('Extension extension_one', $sections[6]['title']);
        $this->assertSame('', $sections[6]['callback']);
        $extension = array_column($this->settingsPayload()['sections'], null, 'id')['asfw_extension_section'];
        $this->assertCount(8, $extension['fields']);
        $this->assertContains('asfw_extension_two_enabled', array_column($extension['fields'], 'option'));
    }

    public function test_default_order_and_legacy_hook_timing_are_preserved(): void
    {
        $atLegacyHook = null;
        $this->filter('asfw_settings_integrations', static function () use (&$atLegacyHook): void {
            $atLegacyHook = array_values(array_unique(array_column($GLOBALS['asfw_test_settings_fields'], 'section')));
        });

        asfw_settings_init();

        $this->assertSame(array('asfw_integrations_settings_section'), $atLegacyHook);
        $this->assertSame(array(
            'asfw_integrations_settings_section',
            'asfw_general_settings_section',
            'asfw_security_settings_section',
            'asfw_widget_settings_section',
            'asfw_control_plane_settings_section',
            'asfw_bunny_settings_section',
        ), array_column($GLOBALS['asfw_test_settings_sections'], 'id'));
    }

    public function test_fallback_does_not_restore_a_deliberately_removed_builtin_section(): void
    {
        $this->filter('asfw_settings_schema_sections', static function (array $sections): array {
            return array_values(array_filter($sections, static fn(array $section): bool => 'asfw_security_settings_section' !== $section['id']));
        });

        asfw_settings_init();

        $this->assertNotContains('asfw_security_settings_section', array_column($GLOBALS['asfw_test_settings_sections'], 'id'));
        $this->assertNotContains('asfw_security_settings_section', array_column($GLOBALS['asfw_test_settings_fields'], 'section'));
    }

    private function addFeature(string $id): void
    {
        $this->filter('asfw_feature_registry_definitions', static function (array $features) use ($id): array {
            $feature = $features['math_challenge'];
            $feature['id'] = $id;
            $feature['label'] = 'Extension ' . $id;
            $feature['section'] = 'asfw_extension_section';
            foreach (array('enabled', 'scope_mode', 'contexts', 'mode') as $option) {
                $feature[$option . '_option'] = 'asfw_' . $id . '_' . $option;
            }
            $features[$id] = $feature;
            return $features;
        });
    }

    private function settingsPayload(): array
    {
        require_once dirname(__DIR__, 2) . '/includes/rest-operations/settings-operations.php';
        return asfw_rest_build_settings_payload();
    }

    private function filter(string $hook, callable $callback): void
    {
        add_filter($hook, $callback, 10);
        $this->extensionFilters[] = array($hook, $callback);
    }
}
