<?php
declare(strict_types=1);

final class WpPluginBaseSettingsRegistryTest extends AsfwPluginTestCase
{
	public function test_settings_init_registers_schema_driven_sections_fields_and_settings(): void
	{
		asfw_settings_init();

		$this->assertSectionRegistered('asfw_control_plane_settings_section');
		$this->assertSectionRegistered('asfw_context_catalog_section');
			$this->assertFieldRegistered('asfw_settings_kill_switch_field');
			$this->assertFieldRegistered('asfw_settings_event_logging_retention_days_field');
		$this->assertFieldRegistered('asfw_settings_event_logging_mode_field');
		$this->assertFieldRegistered('asfw_settings_content_heuristics_scope_mode_field');
		$this->assertFieldRegistered('asfw_settings_bunny_shield_background_field');
		$this->assertFieldRegistered('asfw_settings_math_challenge_mode_field');
		$this->assertFieldRegistered('asfw_settings_submit_delay_mode_field');
		$this->assertFieldRegistered('asfw_settings_feature_submit_delay_ms_field');
		$this->assertFieldRegistered('asfw_settings_contact_form_7_integration_field');
		$this->assertFieldRegistered('asfw_settings_wordpress_login_integration_field');

		$kill_switch = $this->findRegisteredSetting(AntiSpamForWordPressPlugin::$option_kill_switch);
		$this->assertSame('asfw_options', $kill_switch['option_group']);
		$this->assertTrue(is_callable($kill_switch['args']['sanitize_callback']));

		$event_logging_mode = $this->findRegisteredSetting('asfw_feature_event_logging_mode');
		$this->assertTrue(is_callable($event_logging_mode['args']['sanitize_callback']));

		$ip_feeds_background = $this->findRegisteredSetting('asfw_feature_ip_feeds_background_enabled');
		$this->assertTrue(is_callable($ip_feeds_background['args']['sanitize_callback']));

		$submit_delay_ms = $this->findRegisteredSetting(AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms);
		$this->assertTrue(is_callable($submit_delay_ms['args']['sanitize_callback']));

		$contact_form_7 = $this->findField('asfw_settings_contact_form_7_integration_field');
		$this->assertTrue((bool) $contact_form_7['args']['disabled']);
		$this->assertArrayHasKey('shortcode', $contact_form_7['args']['options']);

		$bunny_action = $this->findField('asfw_settings_bunny_action_field');
		$this->assertSame(array('block' => 'Block'), $bunny_action['args']['options']);
	}

	public function test_context_catalog_contains_core_and_integration_contexts(): void
	{
		$catalog = ASFW_Context_Catalog::get_contexts();

		$this->assertArrayHasKey('form:captcha', $catalog);
		$this->assertArrayHasKey('wordpress:login', $catalog);
		$this->assertArrayHasKey('woocommerce:reset-password', $catalog);
		$this->assertSame('wordpress', $catalog['wordpress:login']['group']);
		$this->assertSame('form:captcha:my-widget', ASFW_Context_Catalog::build_widget_context('captcha', 'My Widget'));
	}

	public function test_feature_context_sanitizer_drops_invalid_entries_without_promoting_them_to_generic(): void
	{
		update_option('asfw_feature_submit_delay_contexts', array('wordpress:login', '!!!'));

		$this->assertSame(
			array('wordpress:login'),
			ASFW_Feature_Registry::selected_contexts('submit_delay')
		);
		$this->assertSame(
			array('wordpress:login'),
			asfw_sanitize_feature_contexts_option('wordpress:login, !!!')
		);
	}

	public function test_trusted_proxy_sanitizer_normalizes_and_deduplicates_entries(): void
	{
		$sanitized = asfw_sanitize_trusted_proxies_option("10.0.0.1, bad, 192.0.2.0/24\n10.0.0.1");

		$this->assertSame('10.0.0.1, 192.0.2.0/24', $sanitized);
	}

	public function test_seed_defaults_migrates_legacy_bunny_values_into_feature_options(): void
	{
		delete_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled);
		delete_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key);
		delete_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_zone_id);
		delete_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id);
		delete_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold);
		delete_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes);
		update_option(AntiSpamForWordPressPlugin::$option_bunny_enabled, 1);
		update_option(AntiSpamForWordPressPlugin::$option_bunny_api_key, 'legacy-key');
		update_option(AntiSpamForWordPressPlugin::$option_bunny_shield_zone_id, 'legacy-zone');
		update_option(AntiSpamForWordPressPlugin::$option_bunny_access_list_id, 'legacy-list');
		update_option(AntiSpamForWordPressPlugin::$option_bunny_threshold, '9');
		update_option(AntiSpamForWordPressPlugin::$option_bunny_dedupe_window, '3599');

		asfw_seed_control_plane_defaults();

		$this->assertTrue((bool) get_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled));
		$this->assertSame('block', (string) get_option('asfw_feature_bunny_shield_mode', ''));
		$this->assertSame('legacy-key', (string) get_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_api_key));
		$this->assertSame('legacy-zone', (string) get_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_zone_id));
		$this->assertSame('legacy-list', (string) get_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_access_list_id));
		$this->assertSame('9', (string) get_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold));
		$this->assertSame('60', (string) get_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes));
	}

	public function test_options_privacy_url_uses_custom_url_when_custom_target_is_selected(): void
	{
		update_option(AntiSpamForWordPressPlugin::$option_privacy_page, 'custom');
		update_option(AntiSpamForWordPressPlugin::$option_privacy_url, 'https://example.test/privacy-custom');

		$options = new ASFW_Options();
		$this->assertSame('https://example.test/privacy-custom', $options->get_privacy_url());
	}

	public function test_secret_sanitizer_generates_fallback_secret_when_plugin_instance_is_missing(): void
	{
		$plugin = AntiSpamForWordPressPlugin::$instance;
		AntiSpamForWordPressPlugin::$instance = null;

		try {
			delete_option(AntiSpamForWordPressPlugin::$option_secret);
			$secret = asfw_sanitize_secret_option('');
		} finally {
			AntiSpamForWordPressPlugin::$instance = $plugin;
		}

		$this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $secret);
	}

	public function test_seed_defaults_migrates_content_heuristics_legacy_option_into_feature_flags(): void
	{
		update_option('asfw_content_heuristics_enabled', 1);
		delete_option('asfw_feature_content_heuristics_enabled');
		delete_option('asfw_feature_content_heuristics_mode');

		asfw_seed_control_plane_defaults();

		$this->assertTrue((bool) get_option('asfw_feature_content_heuristics_enabled', false));
		$this->assertSame('log', (string) get_option('asfw_feature_content_heuristics_mode', ''));
		$this->assertTrue(ASFW_Feature_Registry::is_enabled('content_heuristics'));
	}

	public function test_seed_defaults_migrates_disposable_auto_refresh_into_feature_background_flag(): void
	{
		update_option(ASFW_Disposable_Email_Module::OPTION_AUTO_REFRESH, 1);
		delete_option('asfw_feature_disposable_email_background_enabled');

		asfw_seed_control_plane_defaults();

		$this->assertTrue((bool) get_option('asfw_feature_disposable_email_background_enabled', false));
	}

	public function test_settings_option_updated_emits_settings_changed_action(): void
	{
		$captured = null;
		$listener = static function (array $changes, int $user_id) use (&$captured): void {
			$captured = array(
				'changes' => $changes,
				'user_id' => $user_id,
			);
		};

		add_action('asfw_settings_changed', $listener, 10, 2);
		try {
			asfw_settings_option_updated('asfw_feature_event_logging_enabled', '0', '1');
		} finally {
			remove_action('asfw_settings_changed', $listener, 10);
		}

		$this->assertIsArray($captured);
		$this->assertSame(
			array(
				'asfw_feature_event_logging_enabled' => array(
					'old' => '0',
					'new' => '1',
				),
			),
			$captured['changes']
		);
		$this->assertSame(0, $captured['user_id']);
	}

	public function test_settings_option_updated_emits_single_event_when_legacy_mirror_updates_are_triggered(): void
	{
		$events = array();
		$listener = static function (array $changes, int $user_id) use (&$events): void {
			$events[] = array(
				'changes' => $changes,
				'user_id' => $user_id,
			);
		};

		add_action('asfw_settings_changed', $listener, 10, 2);
		try {
			asfw_settings_option_updated(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled, 0, 1);
		} finally {
			remove_action('asfw_settings_changed', $listener, 10);
		}

		$this->assertCount(1, $events);
		$this->assertArrayHasKey(
			AntiSpamForWordPressPlugin::$option_feature_bunny_shield_enabled,
			$events[0]['changes']
		);
	}

	private function assertSectionRegistered(string $section_id): void
	{
		foreach ($GLOBALS['asfw_test_settings_sections'] as $section) {
			if ($section['id'] === $section_id) {
				$this->assertSame('asfw_admin', $section['page']);

				return;
			}
		}

		$this->fail('Missing settings section: ' . $section_id);
	}

	private function assertFieldRegistered(string $field_id): void
	{
		foreach ($GLOBALS['asfw_test_settings_fields'] as $field) {
			if ($field['id'] === $field_id) {
				$this->assertSame('asfw_admin', $field['page']);

				return;
			}
		}

		$this->fail('Missing settings field: ' . $field_id);
	}

	private function findRegisteredSetting(string $option_name): array
	{
		foreach ($GLOBALS['asfw_test_registered_settings'] as $setting) {
			if ($setting['option_name'] === $option_name) {
				return $setting;
			}
		}

		$this->fail('Missing registered setting: ' . $option_name);

		return array();
	}

	private function findField(string $field_id): array
	{
		foreach ($GLOBALS['asfw_test_settings_fields'] as $field) {
			if ($field['id'] === $field_id) {
				return $field;
			}
		}

		$this->fail('Missing settings field: ' . $field_id);

		return array();
	}
}
