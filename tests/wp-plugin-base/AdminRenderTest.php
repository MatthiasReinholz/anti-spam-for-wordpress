<?php
declare(strict_types=1);

final class WpPluginBaseAdminRenderTest extends AsfwPluginTestCase
{
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
