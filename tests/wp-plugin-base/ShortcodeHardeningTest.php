<?php
declare(strict_types=1);

final class ShortcodeHardeningTest extends AsfwPluginTestCase
{
    public function test_html_forms_shortcode_mode_skips_verification_when_widget_markup_is_missing(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_html_forms, 'shortcode');

        $result = apply_filters('hf_validate_form', '', '<form method="post"></form>', array());

        $this->assertSame('', $result);
    }

    public function test_shortcode_returns_empty_when_no_mode_is_available(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_custom, '');

        $shortcode = $GLOBALS['asfw_test_shortcodes']['anti_spam_widget'];

        $this->assertSame('', $shortcode(array()));
    }

    public function test_shortcode_renders_when_mode_is_explicit(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_custom, '');

        $shortcode = $GLOBALS['asfw_test_shortcodes']['anti_spam_widget'];
        $markup = $shortcode(array('mode' => 'captcha', 'context' => 'custom'));

        $this->assertStringContainsString('<asfw-widget', $markup);
        $this->assertStringContainsString('custom', $markup);
    }

    public function test_shortcode_returns_empty_when_plugin_instance_is_missing(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_custom, 'captcha');

        $shortcode = $GLOBALS['asfw_test_shortcodes']['anti_spam_widget'];
        $plugin = AntiSpamForWordPressPlugin::$instance;
        AntiSpamForWordPressPlugin::$instance = null;

        try {
            $this->assertSame('', $shortcode(array('mode' => 'captcha', 'context' => 'custom')));
        } finally {
            AntiSpamForWordPressPlugin::$instance = $plugin;
        }
    }
}
