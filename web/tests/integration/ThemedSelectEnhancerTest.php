<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Static gate for the themed single-select enhancer (.ssel) and its
 * shared chrome with .msel in theme.js / theme.css.
 */
final class ThemedSelectEnhancerTest extends TestCase
{
    private static function themeJs(): string
    {
        $path = dirname(__DIR__, 2) . '/themes/default/js/theme.js';
        self::assertFileExists($path);
        $src = file_get_contents($path);
        self::assertNotFalse($src);
        return $src;
    }

    private static function themeCss(): string
    {
        $path = dirname(__DIR__, 2) . '/themes/default/css/theme.css';
        self::assertFileExists($path);
        $src = file_get_contents($path);
        self::assertNotFalse($src);
        return $src;
    }

    public function testThemeJsDefinesSingleSelectEnhancer(): void
    {
        $js = self::themeJs();
        self::assertStringContainsString('function enhanceSelect(select)', $js);
        self::assertStringContainsString("wrap.className = 'ssel'", $js);
        self::assertStringContainsString("data-lucide=\"chevron-down\" class=\"ssel__chevron\"", $js);
        self::assertStringContainsString('data-native-select', $js);
        self::assertStringContainsString("querySelectorAll('select.select')", $js);
        self::assertStringContainsString('function positionSelectPanel(', $js);
        self::assertStringContainsString("data-placement", $js);
    }

    public function testThemeJsSkipsMultiselectAndNativeOptOut(): void
    {
        $js = self::themeJs();
        $start = strpos($js, 'function enhanceSelect(select)');
        self::assertNotFalse($start);
        $chunk = substr($js, $start, 800);
        self::assertStringContainsString("select.hasAttribute('data-multiselect')", $chunk);
        self::assertStringContainsString("select.hasAttribute('data-native-select')", $chunk);
        self::assertStringContainsString('select.size > 1', $chunk);
        self::assertStringContainsString('select.multiple', $chunk);
    }

    public function testThemeCssSharesSelectChromeBetweenMselAndSsel(): void
    {
        $css = self::themeCss();
        self::assertStringContainsString('.msel, .ssel {', $css);
        self::assertStringContainsString('.msel__trigger, .ssel__trigger {', $css);
        self::assertStringContainsString('.msel__chevron, .ssel__chevron {', $css);
        self::assertStringContainsString('.msel__panel, .ssel__panel {', $css);
        self::assertStringContainsString('.ssel__option[aria-selected="true"]', $css);
        self::assertStringContainsString('.ssel__group', $css);
        self::assertStringContainsString('.msel[data-placement="top"] .msel__panel', $css);
        self::assertStringContainsString('.ssel[data-placement="top"] .ssel__panel', $css);
        self::assertStringContainsString('bottom: calc(100% + 0.25rem)', $css);
    }

    public function testThemeCssDoesNotForceMinWidthOnSingleSelect(): void
    {
        $css = self::themeCss();
        $sharedStart = strpos($css, '.msel, .ssel {');
        self::assertNotFalse($sharedStart);
        $sharedChunk = substr($css, $sharedStart, 220);
        self::assertStringNotContainsString('min-width: 14rem', $sharedChunk);
        self::assertStringContainsString('min-width: 0', $sharedChunk);

        // Multi-select alone keeps the 14rem floor (after the shared block).
        $afterShared = substr($css, $sharedStart + strlen('.msel, .ssel {'));
        self::assertMatchesRegularExpression(
            '/\.msel\s*\{\s*min-width:\s*14rem\s*;/s',
            $afterShared,
        );
    }
}
