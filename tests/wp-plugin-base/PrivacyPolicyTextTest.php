<?php
declare(strict_types=1);

final class WpPluginBasePrivacyPolicyTextTest extends AsfwPluginTestCase
{
	public function test_default_text_is_empty_until_legal_basis_is_selected(): void
	{
		$payload = ASFW_Privacy_Policy_Text::payload();

		$this->assertIsArray($payload);
		$this->assertArrayHasKey('text', $payload);
		$this->assertArrayHasKey('summary', $payload);
		$this->assertArrayHasKey('relevant_options', $payload);
		$this->assertArrayHasKey('flags', $payload);
		$this->assertSame('', $payload['text']);
		$this->assertStringNotContainsString('[Review required:', $payload['text']);
		$this->assertStringContainsString('local/self-hosted anti-spam processing only', $payload['summary']);
		$this->assertContains(AntiSpamForWordPressPlugin::$option_privacy_legal_basis, $payload['relevant_options']);
	}

	public function test_legal_basis_variants_are_inserted(): void
	{
		update_option(AntiSpamForWordPressPlugin::$option_privacy_legal_basis, ASFW_Privacy_Policy_Text::LEGAL_BASIS_CONSENT);
		$this->assertStringContainsString('Article 6(1)(a) GDPR', ASFW_Privacy_Policy_Text::payload()['text']);

		update_option(AntiSpamForWordPressPlugin::$option_privacy_legal_basis, ASFW_Privacy_Policy_Text::LEGAL_BASIS_LEGITIMATE);
		$this->assertStringContainsString('Article 6(1)(f) GDPR', ASFW_Privacy_Policy_Text::payload()['text']);

		$this->assertSame(
			ASFW_Privacy_Policy_Text::LEGAL_BASIS_REVIEW_REQUIRED,
			ASFW_Privacy_Policy_Text::sanitize_legal_basis('not-valid')
		);
	}

	public function test_ip_only_and_ip_user_agent_binding_are_reflected(): void
	{
		update_option(AntiSpamForWordPressPlugin::$option_privacy_legal_basis, ASFW_Privacy_Policy_Text::LEGAL_BASIS_CONSENT);
		update_option(AntiSpamForWordPressPlugin::$option_visitor_binding, 'ip');
		$this->assertStringContainsString('visitor IP address to create', ASFW_Privacy_Policy_Text::payload()['text']);

		update_option(AntiSpamForWordPressPlugin::$option_visitor_binding, 'ip_ua');
		$payload = ASFW_Privacy_Policy_Text::payload();
		$this->assertTrue($payload['flags']['ip_user_agent_binding']);
		$this->assertStringContainsString('visitor IP address and user agent', $payload['text']);
	}

	public function test_event_logging_retention_is_reflected(): void
	{
		update_option(AntiSpamForWordPressPlugin::$option_privacy_legal_basis, ASFW_Privacy_Policy_Text::LEGAL_BASIS_CONSENT);
		update_option('asfw_feature_event_logging_enabled', 1);
		update_option('asfw_feature_event_logging_mode', 'log');
		update_option('asfw_event_logging_retention_days', '90');

		$text = ASFW_Privacy_Policy_Text::payload()['text'];

		$this->assertStringContainsString('approximately 90 days', $text);
		$this->assertStringContainsString('hashed IP addresses', $text);
		$this->assertStringContainsString('may still qualify as personal data', $text);
	}

	public function test_optional_local_features_are_reflected(): void
	{
		update_option(AntiSpamForWordPressPlugin::$option_privacy_legal_basis, ASFW_Privacy_Policy_Text::LEGAL_BASIS_CONSENT);
		update_option('asfw_feature_disposable_email_enabled', 1);
		update_option('asfw_feature_disposable_email_mode', 'block');
		update_option('asfw_feature_disposable_email_background_enabled', 1);
		update_option('asfw_feature_content_heuristics_enabled', 1);
		update_option('asfw_feature_content_heuristics_mode', 'log');
		update_option('asfw_feature_math_challenge_enabled', 1);
		update_option('asfw_feature_math_challenge_mode', 'block');
		update_option('asfw_feature_submit_delay_enabled', 1);
		update_option('asfw_feature_submit_delay_mode', 'block');
		update_option(AntiSpamForWordPressPlugin::$option_feature_submit_delay_ms, '5000');

		$text = ASFW_Privacy_Policy_Text::payload()['text'];

		$this->assertStringContainsString('disposable-domain list', $text);
		$this->assertStringContainsString('refreshed from the configured remote source', $text);
		$this->assertStringContainsString('content heuristics', $text);
		$this->assertStringContainsString('arithmetic question', $text);
		$this->assertStringContainsString('approximately 5 seconds', $text);
	}

	public function test_bunny_external_sync_only_when_runtime_can_send_ips(): void
	{
		update_option(AntiSpamForWordPressPlugin::$option_privacy_legal_basis, ASFW_Privacy_Policy_Text::LEGAL_BASIS_CONSENT);
		update_option('asfw_feature_bunny_shield_enabled', 1);
		update_option('asfw_feature_bunny_shield_mode', 'block');
		update_option('asfw_feature_bunny_shield_background_enabled', 1);
		update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_action, 'block');
		update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run, 1);

		$dryRunPayload = ASFW_Privacy_Policy_Text::payload();
		$this->assertFalse($dryRunPayload['flags']['bunny_external_sync']);
		$this->assertStringContainsString('Bunny Shield remote synchronization is not active', $dryRunPayload['text']);

		update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_dry_run, 0);
		update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_threshold, '3');
		update_option(AntiSpamForWordPressPlugin::$option_feature_bunny_shield_ttl_minutes, '120');

		$activePayload = ASFW_Privacy_Policy_Text::payload();
		$this->assertTrue($activePayload['flags']['bunny_external_sync']);
		$this->assertStringContainsString('may be transmitted to Bunny Shield', $activePayload['text']);
		$this->assertStringContainsString('approximately 3 local abuse signals', $activePayload['text']);
		$this->assertStringContainsString('approximately 120 minutes', $activePayload['text']);
	}

	public function test_wordpress_privacy_policy_guide_content_is_registered(): void
	{
		update_option(AntiSpamForWordPressPlugin::$option_privacy_legal_basis, ASFW_Privacy_Policy_Text::LEGAL_BASIS_CONSENT);

		asfw_register_privacy_policy_content();

		$this->assertCount(1, $GLOBALS['asfw_test_privacy_policy_content']);
		$this->assertSame('Anti Spam for WordPress', $GLOBALS['asfw_test_privacy_policy_content'][0]['plugin_name']);
		$this->assertStringContainsString('<h2>Use of Anti Spam for WordPress</h2>', $GLOBALS['asfw_test_privacy_policy_content'][0]['policy_text']);
	}

	public function test_wordpress_privacy_policy_guide_content_is_not_registered_without_legal_basis(): void
	{
		asfw_register_privacy_policy_content();

		$this->assertSame(array(), $GLOBALS['asfw_test_privacy_policy_content']);
	}
}
