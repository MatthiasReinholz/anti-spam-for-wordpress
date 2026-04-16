<?php
declare(strict_types=1);

final class WpPluginBaseAdminRenderTest extends AsfwPluginTestCase
{
	public function test_admin_page_uses_asfw_classes_and_no_inline_styles(): void
	{
		ob_start();
		asfw_options_page_html();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString('class="asfw-head"', $html);
		$this->assertStringContainsString('class="asfw-logo"', $html);
		$this->assertStringContainsString('class="asfw-page-meta"', $html);
		$this->assertStringContainsString('class="asfw-summary-panel"', $html);
		$this->assertStringContainsString('Control plane summary', $html);
		$this->assertStringContainsString('Kill switch:', $html);
		$this->assertStringNotContainsString('altcha-', $html);
		$this->assertStringNotContainsString('display:flex', $html);
		$this->assertStringNotContainsString('flex-grow: 1', $html);
		$this->assertStringNotContainsString('opacity: 0.8', $html);
	}

	public function test_context_catalog_section_renders_context_table(): void
	{
		ob_start();
		asfw_context_catalog_section_callback();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString('form:captcha', $html);
		$this->assertStringContainsString('wordpress:login', $html);
		$this->assertStringContainsString('asfw-context-table', $html);
	}

	public function test_control_plane_and_bunny_section_copy_mentions_mode_driven_contract(): void
	{
		ob_start();
		asfw_control_plane_section_callback();
		$control_plane_html = (string) ob_get_clean();

		ob_start();
		asfw_bunny_section_callback();
		$bunny_html = (string) ob_get_clean();

		$this->assertStringContainsString('runtime mode', $control_plane_html);
		$this->assertStringContainsString('log mode', $bunny_html);
		$this->assertStringContainsString('block mode', $bunny_html);
	}

	public function test_textarea_settings_callback_renders_schema_managed_context_lists(): void
	{
		update_option('asfw_feature_event_logging_contexts', array('contact-form-7', 'wordpress:login'));

		ob_start();
		asfw_settings_textarea_callback(
			array(
				'name' => 'asfw_feature_event_logging_contexts',
				'hint' => 'Enter one normalized context per line.',
			)
		);
		$html = (string) ob_get_clean();

		$this->assertStringContainsString('<textarea', $html);
		$this->assertStringContainsString('contact-form-7', $html);
		$this->assertStringContainsString('wordpress:login', $html);
	}

	public function test_kill_switch_bypasses_rendering_and_validation(): void
	{
		update_option(AntiSpamForWordPressPlugin::$option_kill_switch, 1);

		$this->assertSame('', asfw_render_widget_markup('captcha', 'contact-form-7'));
		$this->assertSame(array(), $this->plugin()->get_integrations());
		$this->assertFalse($this->plugin()->has_active_integrations());
		$this->assertTrue($this->plugin()->verify('anything', null, 'contact-form-7'));
	}
}
