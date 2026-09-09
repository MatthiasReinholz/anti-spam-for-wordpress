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

    public function test_shortcode_presentation_overrides_are_strict_and_independent(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_custom, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_widget_appearance, 'light');
        update_option(AntiSpamForWordPressPlugin::$option_widget_layout, 'compact');

        $shortcode = $GLOBALS['asfw_test_shortcodes']['anti_spam_widget'];
        $darkExtended = $shortcode(array('appearance' => 'dark', 'layout' => 'extended'));
        $this->assertStringContainsString('appearance="dark"', $darkExtended);
        $this->assertStringContainsString('layout="extended"', $darkExtended);

        $bright = $shortcode(array('appearance' => 'bright'));
        $this->assertStringContainsString('appearance="light"', $bright);
        $this->assertStringContainsString('layout="compact"', $bright);
    }

    public function test_invalid_shortcode_presentation_overrides_inherit_site_settings(): void
    {
        update_option(AntiSpamForWordPressPlugin::$option_integration_custom, 'captcha');
        update_option(AntiSpamForWordPressPlugin::$option_widget_appearance, 'dark');
        update_option(AntiSpamForWordPressPlugin::$option_widget_layout, 'extended');

        $shortcode = $GLOBALS['asfw_test_shortcodes']['anti_spam_widget'];
        $markup = $shortcode(array('appearance' => array('light'), 'layout' => 'wide'));

        $this->assertStringContainsString('appearance="dark"', $markup);
        $this->assertStringContainsString('layout="extended"', $markup);
        $this->assertStringNotContainsString('wide', $markup);
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
