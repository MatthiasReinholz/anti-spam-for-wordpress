<?php
declare(strict_types=1);

final class BunnyContractTest extends AsfwPluginTestCase
{
    public function test_bunny_action_field_keeps_ui_conservative_while_contract_accepts_challenge(): void
    {
        $fields = ASFW_Settings_Schema::get_fields_by_section();
        $bunny_fields = array_values(array_filter($fields['asfw_bunny_settings_section'], static function (array $field): bool {
            return isset($field['option']) && AntiSpamForWordPressPlugin::$option_feature_bunny_shield_action === $field['option'];
        }));

        $this->assertCount(1, $bunny_fields);
        $this->assertSame(array('block'), array_keys($bunny_fields[0]['args']['options']));

        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_action, 'challenge');
        $this->assertSame('challenge', $this->plugin()->get_bunny_action());
    }

    public function test_bunny_challenge_action_remains_runtime_inert(): void
    {
        $this->enableBunnyFeature(
            array(
                'dry_run' => false,
                'threshold' => '1',
            )
        );
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_action, 'challenge');
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';

        do_action('asfw_verify_result', false, new WP_Error('asfw_test', 'failure'), 'contact-form-7', 'asfw');

        $this->assertCount(0, $GLOBALS['asfw_test_http_requests']);
        $this->assertSame('challenge', $this->plugin()->get_bunny_action());
    }

    public function test_schema_wrapper_exists_for_contract_alignment(): void
    {
        $this->assertTrue(class_exists('ASFW_Schema'));
        $this->assertTrue(is_subclass_of('ASFW_Schema', 'ASFW_Event_Store'));
    }

    private function enableBunnyFeature(array $overrides = array()): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled, 1);
        update_option('asfw_feature_bunny_shield_mode', $overrides['mode'] ?? 'block');
        update_option('asfw_feature_bunny_shield_background_enabled', $overrides['background_enabled'] ?? 1);
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key, $overrides['api_key'] ?? 'test-api-key');
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_zone_id, $overrides['zone_id'] ?? '42');
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id, $overrides['access_list_id'] ?? '77');
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run, $overrides['dry_run'] ?? true);
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_fail_open, $overrides['fail_open'] ?? true);
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold, $overrides['threshold'] ?? '10');
        update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes, $overrides['ttl_minutes'] ?? '60');
    }
}
