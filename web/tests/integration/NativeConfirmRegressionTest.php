<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;
use SplFileInfo;

/**
 * Pins the shared `window.SBPP.confirm` chrome and rejects
 * reintroduction of native `window.confirm` / primary `window.alert`
 * call sites in panel templates and page-tail scripts.
 */
final class NativeConfirmRegressionTest extends TestCase
{
    private static function panelRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testThemeJsExposesConfirmHelper(): void
    {
        $src = (string) file_get_contents(self::panelRoot() . '/themes/default/js/theme.js');
        $this->assertStringContainsString('function confirmDialog(opts)', $src);
        $this->assertMatchesRegularExpression(
            '/confirm\s*:\s*confirmDialog/',
            $src,
        );
        $this->assertStringContainsString("id = 'sbpp-confirm-dialog'", $src);
        $this->assertStringContainsString('data-testid="sbpp-confirm-submit"', $src);
    }

    public function testThemeCssStylesDialogBackdropLikeDrawer(): void
    {
        $src = (string) file_get_contents(self::panelRoot() . '/themes/default/css/theme.css');
        $this->assertMatchesRegularExpression(
            '/dialog::backdrop\s*\{[^}]*backdrop-filter:\s*blur\(2px\)/s',
            $src,
        );
        $this->assertMatchesRegularExpression(
            '/dialog::backdrop\s*\{[^}]*background:\s*rgb\(9 9 11 \/ 0\.4\)/s',
            $src,
        );
    }

    public function testProductionSurfacesDoNotCallNativeConfirm(): void
    {
        $root = self::panelRoot();
        $dirs = [
            $root . '/themes/default',
            $root . '/pages',
            $root . '/scripts',
        ];
        // Toast fallback when SBPP is stripped — last-resort only.
        $allowAlert = [
            'page_admin_settings_settings.tpl',
        ];

        $hits = [];
        foreach ($dirs as $dir) {
            $iterator = new RegexIterator(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)),
                '/\.(?:tpl|js|php)$/i',
                RecursiveRegexIterator::MATCH,
            );
            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                if (!$file->isFile()) {
                    continue;
                }
                $basename = $file->getBasename();
                $stripped = $this->stripComments((string) file_get_contents($file->getPathname()));
                if (str_contains($stripped, 'window.confirm(')) {
                    $hits[] = $basename . ': window.confirm(';
                }
                if (str_contains($stripped, 'window.alert(') && !in_array($basename, $allowAlert, true)) {
                    $hits[] = $basename . ': window.alert(';
                }
            }
        }

        $this->assertSame(
            [],
            $hits,
            "Use SBPP.confirm / showToast instead of native confirm/alert:\n" . implode("\n", $hits),
        );
    }

    private function stripComments(string $src): string
    {
        $out = preg_replace('/\{\*.*?\*\}/s', '', $src) ?? $src;
        $out = preg_replace('#/\*.*?\*/#s', '', $out) ?? $out;
        $out = preg_replace('#^\s*//.*$#m', '', $out) ?? $out;

        return $out;
    }
}
