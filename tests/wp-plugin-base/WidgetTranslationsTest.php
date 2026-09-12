<?php

declare(strict_types=1);

final class WidgetTranslationsTest extends AsfwPluginTestCase
{
    public function testWidgetUsesCurrentLocaleAndPreservesCustomFooter(): void
    {
        $translate = static function ($translated, $text, $domain) {
            return 'anti-spam-for-wordpress' === $domain ? get_locale() . ': ' . $text : $translated;
        };
        add_filter('gettext', $translate, 10, 3);
        try {
            switch_to_locale('de_DE');
            $strings = json_decode($this->plugin()->get_widget_attrs('captcha')['strings'], true);
            self::assertSame("de_DE: I'm not a robot", $strings['label']);
            self::assertSame('de_DE: Protected by Anti Spam for WordPress', $strings['footer']);
            update_option(AntiSpamForWordPressPlugin::$option_footer_text, 'Protected by Anti Spam for WordPress');
            self::assertSame($strings['footer'], $this->plugin()->get_translations()['footer']);
            update_option(AntiSpamForWordPressPlugin::$option_footer_text, 'Our custom footer');
            self::assertSame('Our custom footer', $this->plugin()->get_translations()['footer']);
            self::assertSame('de_DE', get_locale());
        } finally {
            remove_filter('gettext', $translate, 10);
            restore_previous_locale();
        }
    }

    public function testOverrideRestoresNestedLocaleWithoutLeakingStackEntries(): void
    {
        switch_to_locale('de_DE');
        $seen = null;
        $capture = static function ($strings) use (&$seen) {
            $seen = get_locale();
            return $strings;
        };
        add_filter('asfw_translations', $capture);
        try {
            $this->plugin()->get_translations('fr_FR');
            self::assertSame('fr_FR', $seen);
            self::assertSame('de_DE', get_locale());
            $this->plugin()->get_translations('de_DE');
            $this->plugin()->get_translations('unsupported');
            self::assertSame('de_DE', get_locale());
            self::assertSame('en_US', restore_previous_locale());
            self::assertFalse(restore_previous_locale());
        } finally {
            remove_filter('asfw_translations', $capture);
        }
    }

    public function testThrowingTranslationFilterStillRestoresLocale(): void
    {
        $throw = static function () {
            throw new RuntimeException('Translation filter failed');
        };
        add_filter('asfw_translations', $throw);
        try {
            $this->plugin()->get_translations('fr_FR');
            self::fail('Expected the filter exception.');
        } catch (RuntimeException $error) {
            self::assertSame('Translation filter failed', $error->getMessage());
            self::assertSame('en_US', get_locale());
            self::assertFalse(restore_previous_locale());
        } finally {
            remove_filter('asfw_translations', $throw);
        }
    }
}
