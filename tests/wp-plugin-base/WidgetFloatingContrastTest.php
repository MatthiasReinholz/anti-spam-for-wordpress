<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WidgetFloatingContrastTest extends TestCase
{
    public function testEmptyFloatingStateDoesNotActivateElevatedSurface(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/public/asfw-widget.js');
        $styles = file_get_contents(dirname(__DIR__, 2) . '/public/asfw-widget.css');

        self::assertIsString($script);
        self::assertIsString($styles);
        self::assertStringContainsString(
            "asfwSetOptionalDataAttribute(this._shell, 'floating', this.getAttribute('floating') || '');",
            $script
        );
        self::assertStringContainsString(
            'element.removeAttribute(`data-${name}`);',
            $script
        );
        self::assertStringContainsString(
            '.asfw-widget-shell[data-floating]:not([data-floating=""])',
            $styles
        );
    }

    public function testWidgetUsesCompleteExplicitLightAndDarkPalettes(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/public/asfw-widget.js');
        $styles = file_get_contents(dirname(__DIR__, 2) . '/public/asfw-widget.css');

        self::assertIsString($script);
        self::assertIsString($styles);
        self::assertStringContainsString("this._shell.dataset.appearance = this.getAppearance();", $script);
        self::assertStringContainsString("return this.getAttribute('appearance') === 'dark' ? 'dark' : 'light';", $script);
        self::assertStringContainsString('--asfw-surface: #ffffff;', $styles);
        self::assertStringContainsString('--asfw-text: #0f172a;', $styles);
        self::assertStringContainsString('.asfw-widget-shell[data-appearance="dark"]', $styles);
        self::assertStringContainsString('--asfw-surface: #111827;', $styles);
        self::assertStringContainsString('--asfw-text: #f8fafc;', $styles);
        self::assertStringNotContainsString('--asfw-text: currentColor;', $styles);
        self::assertStringNotContainsString('--asfw-surface: transparent;', $styles);
    }

    public function testExtendedLayoutPlacesFullWidthIntroBeforeControl(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/public/asfw-widget.js');
        $styles = file_get_contents(dirname(__DIR__, 2) . '/public/asfw-widget.css');

        self::assertIsString($script);
        self::assertIsString($styles);
        self::assertMatchesRegularExpression('/<div class="asfw-widget">\\s*<div class="asfw-intro" hidden><\\/div>\\s*<div class="asfw-main">/', $script);
        self::assertStringContainsString("this._intro.hidden = this.getLayout() !== 'extended';", $script);
        self::assertStringContainsString("return this.getAttribute('layout') === 'extended' ? 'extended' : 'compact';", $script);
        self::assertStringContainsString('.asfw-intro {', $styles);
        self::assertStringContainsString('box-sizing: border-box;', $styles);
        self::assertStringContainsString('width: 100%;', $styles);
    }
}
