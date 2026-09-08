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
}
