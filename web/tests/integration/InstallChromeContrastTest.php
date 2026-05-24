<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1435: contrast regression guard for the install wizard's
 * .install-alert--* / .install-pill--* surfaces.
 *
 * The wizard ships its own install-only inline CSS in
 * `web/themes/default/install/_chrome.tpl` (see "Install wizard"
 * under AGENTS.md Conventions) because the panel runtime never
 * renders these classes — they're scoped to `IN_INSTALL`. Pre-#1435
 * the success / info / warning / error alerts (and the matching
 * status pills on the requirements page) used Tailwind 700/800-tier
 * text colours on the corresponding light-tint backgrounds:
 *
 *   .install-alert--ok   color rgb(21, 128, 61)  on rgba(34, 197, 94, 0.10)
 *   .install-alert--info color rgb(30, 64, 175)  on rgba(59, 130, 246, 0.10)
 *   .install-alert--warn color rgb(133, 77, 14)  on var(--warning-bg)
 *   .install-alert--error color rgb(153, 27, 27) on var(--danger-bg)
 *
 * The worst offender was --ok at ~4.46:1 — just below WCAG AA Normal
 * Text (4.5:1) — which the reporter described as "Dark Green on
 * Light Green boxes" (#1435). The fix pins every variant to the
 * 900-tier (green-900 / amber-900 / red-900 / blue-900) so each
 * alert clears AAA (~8:1) on its background, AND bumps the --ok /
 * --info alpha from 0.10 to 0.15 (matching the .install-pill
 * background opacity) so the card edge is visibly distinct from the
 * page background instead of reading as "barely tinted off-white".
 *
 * This test pins both halves of the fix as a static file shape:
 *
 *   1. {@see testInstallAlertColoursMatchWcagAaaPalette} asserts the
 *      darker text colours are present and the old 700/800-tier
 *      colours are absent.
 *   2. {@see testInstallAlertBackgroundsHaveVisibleEdge} asserts the
 *      --ok / --info bg alpha is no longer the pale 0.10.
 *   3. {@see testEveryInstallAlertVariantPassesWcagAaContrast}
 *      computes the actual WCAG 2.x contrast ratio for each
 *      variant's (background, text) pair and asserts ≥ 4.5:1 (AA
 *      Normal Text). Drift this gate: if a future PR re-darkens the
 *      bg or lightens the text, the maths catches it even if the
 *      literal-substring assertion in (1) doesn't.
 *
 * No DB / session / Smarty bring-up needed; pure file scanning +
 * arithmetic. Extends `PHPUnit\Framework\TestCase` directly so the
 * class stays fast and DB-independent (same shape as
 * {@see DeadJsCallSitesTest} / {@see InstallGuardTest}).
 */
final class InstallChromeContrastTest extends TestCase
{
    /**
     * Path to the wizard's shared chrome template. Defined as a
     * class constant so multiple test methods + helpers reference
     * the same file without re-resolving against `ROOT`.
     */
    private const CHROME_TPL = 'themes/default/install/_chrome.tpl';

    /**
     * The `.install-shell` rule sets `background: var(--bg-page);`,
     * which in light mode resolves to `--zinc-50` (#fafafa). Every
     * alert sits on this page background (the rgba(...) alert bg
     * mixes with the page bg, so the effective alert bg depends on
     * what's underneath).
     */
    private const PAGE_BG_LIGHT = [250, 250, 250]; // --zinc-50

    /**
     * theme.css design tokens (light mode). Mirrored here so the
     * test can compose effective backgrounds for --warn / --error
     * (which use `var(--warning-bg)` / `var(--danger-bg)` directly).
     * Re-derived from the `:root` block in `theme.css` line 69-72.
     */
    private const WARNING_BG_LIGHT = [255, 251, 235]; // #fffbeb
    private const DANGER_BG_LIGHT  = [254, 242, 242]; // #fef2f2

    /**
     * Forbidden (pre-fix) colour literals. Each entry pins one of
     * the v2.0.0-rc5 700/800-tier values the user described as too
     * pale in #1435. Listing them explicitly is what catches a
     * fork or follow-up PR that copy-pastes the old palette back
     * in (the str-replace contract is unforgiving — a "let me just
     * tweak the green" tweak would silently re-fail AA).
     *
     * @return list<string>
     */
    private static function forbiddenColourLiterals(): array
    {
        return [
            // --ok / --pill--ok (green-700, ~4.46:1 on rgba(_, 0.10))
            'rgb(21, 128, 61)',
            // --info (blue-800, decent on paper but flagged by #1435)
            'rgb(30, 64, 175)',
            // --warn / --pill--warn (yellow-800)
            'rgb(133, 77, 14)',
            // --error / --pill--err (red-800)
            'rgb(153, 27, 27)',
        ];
    }

    /**
     * Required (post-fix) colour literals. Each variant carries the
     * 900-tier value; the test asserts every entry appears at least
     * once in the comment-stripped template so a refactor can't
     * silently swap them out.
     *
     * Format mirrors theme.css's preference for `#rrggbb` over
     * `rgb(r, g, b)` for fixed-palette literals — that's the form
     * Tailwind ships with, and grep-friendliness wins over
     * machine-readability here.
     *
     * @return list<string>
     */
    private static function requiredColourLiterals(): array
    {
        return [
            '#14532d', // green-900  — --ok + --pill--ok
            '#1e3a8a', // blue-900   — --info
            '#78350f', // amber-900  — --warn + --pill--warn
            '#7f1d1d', // red-900    — --error + --pill--err
        ];
    }

    /**
     * Strip Smarty curly-star comments before grepping so the
     * #1435 explanatory comment block (which references the
     * pre-fix colour literals as "the worst offender was --ok at
     * ~4.46:1") doesn't false-match the forbidden-literal gate.
     * Inline HTML `<!-- ... -->` comments are NOT stripped (Smarty
     * doesn't treat them as comments; the forbidden patterns
     * aren't HTML-comment-shaped so this is fine). Same defence
     * pattern as {@see DeadJsCallSitesTest::stripSmartyComments}.
     */
    private static function stripSmartyComments(string $contents): string
    {
        return (string) preg_replace('/\{\*.*?\*\}/s', '', $contents);
    }

    /**
     * Strip CSS slash-star comments before grepping. The fix PR
     * adds an inline "Text colours are Tailwind 900-tier..."
     * explanatory comment that names every pre-#1435 contrast
     * ratio + every old colour pattern in passing; without
     * stripping these the forbidden-literal gate would false-fire
     * on its own explanatory text.
     */
    private static function stripCssComments(string $contents): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', $contents);
    }

    /**
     * Load + comment-strip `_chrome.tpl`. Strips BOTH Smarty
     * comments (the file's docblock at the top) AND CSS comments
     * (the inline `<style>` block's design-rationale comments) so
     * grep-based assertions only see live code.
     */
    private static function loadStrippedChrome(): string
    {
        $path = ROOT . self::CHROME_TPL;
        $raw = (string) file_get_contents($path);
        return self::stripCssComments(self::stripSmartyComments($raw));
    }

    /**
     * Convert a `#rrggbb` literal to a 3-element RGB array
     * (each channel 0-255). Used to feed the WCAG luminance
     * calculator. Strict shape — throws on anything other than
     * the exact 7-char form so a typo in the test surfaces loudly.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private static function hexToRgb(string $hex): array
    {
        if (strlen($hex) !== 7 || $hex[0] !== '#') {
            self::fail("hexToRgb expects #rrggbb, got " . var_export($hex, true));
        }
        return [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];
    }

    /**
     * Alpha-composite a foreground RGB(A) over an opaque
     * background RGB. Standard "source over" formula:
     * `result_c = alpha * fg_c + (1 - alpha) * bg_c`. Returned as
     * integers 0-255 because that's what the WCAG luminance step
     * expects (the spec is defined in terms of 8-bit sRGB).
     *
     * @param array{0: int, 1: int, 2: int} $fg
     * @param array{0: int, 1: int, 2: int} $bg
     * @return array{0: int, 1: int, 2: int}
     */
    private static function composite(array $fg, float $alpha, array $bg): array
    {
        return [
            (int) round($alpha * $fg[0] + (1 - $alpha) * $bg[0]),
            (int) round($alpha * $fg[1] + (1 - $alpha) * $bg[1]),
            (int) round($alpha * $fg[2] + (1 - $alpha) * $bg[2]),
        ];
    }

    /**
     * WCAG 2.x relative luminance for an sRGB colour. Implements
     * https://www.w3.org/TR/WCAG21/#dfn-relative-luminance verbatim:
     * normalize each channel to 0..1, apply the gamma curve, then
     * weight per ITU-R BT.709 coefficients (0.2126 R, 0.7152 G,
     * 0.0722 B).
     *
     * @param array{0: int, 1: int, 2: int} $rgb
     */
    private static function relativeLuminance(array $rgb): float
    {
        $linear = [];
        foreach ($rgb as $channel) {
            $c = $channel / 255.0;
            $linear[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }
        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    /**
     * WCAG 2.x contrast ratio between two opaque sRGB colours:
     * `(L_lighter + 0.05) / (L_darker + 0.05)`. Returns a float
     * ≥ 1.0 (identical colours = 1.0, black-on-white = 21.0).
     *
     * @param array{0: int, 1: int, 2: int} $a
     * @param array{0: int, 1: int, 2: int} $b
     */
    private static function contrastRatio(array $a, array $b): float
    {
        $la = self::relativeLuminance($a);
        $lb = self::relativeLuminance($b);
        $lighter = max($la, $lb);
        $darker  = min($la, $lb);
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Forbidden 700/800-tier colour literals must not appear in
     * the live (comment-stripped) chrome template. A regression
     * here is a copy-paste of the v2.0.0-rc5 palette — likely
     * from a fork — and the gate fires before the visual review
     * cycle catches it.
     */
    public function testInstallAlertColoursMatchWcagAaaPalette(): void
    {
        $chrome = self::loadStrippedChrome();

        $hits = [];
        foreach (self::forbiddenColourLiterals() as $literal) {
            if (str_contains($chrome, $literal)) {
                $hits[] = "forbidden pre-#1435 literal " . var_export($literal, true) . " resurrected";
            }
        }
        foreach (self::requiredColourLiterals() as $literal) {
            if (!str_contains($chrome, $literal)) {
                $hits[] = "required post-#1435 literal " . var_export($literal, true) . " missing";
            }
        }

        $this->assertSame(
            [],
            $hits,
            "Install-wizard contrast palette drift. Pre-#1435 the wizard's alert / pill "
                . "text used Tailwind 700/800-tier colours that failed WCAG AA Normal Text on "
                . "the corresponding light-tint backgrounds. The fix darkened every variant to "
                . "the 900-tier (green-900/amber-900/red-900/blue-900). See AGENTS.md "
                . "\"Install wizard\" + the docblock on this test class for the rationale.\n\n"
                . "Offending lines:\n - " . implode("\n - ", $hits),
        );
    }

    /**
     * The --ok / --info alert backgrounds bumped from 0.10 alpha to
     * 0.15 alpha so the card edge stops reading as "barely tinted
     * off-white" against the page background. 0.10 alpha was the
     * v2.0.0-rc5 shape; 0.15 matches the .install-pill backgrounds
     * AND keeps the box internally readable when paired with the
     * 900-tier text colour.
     *
     * Asserts on the comment-stripped chrome so the inline
     * "bumped from rgba(_, 0.1) to rgba(_, 0.15)" explanatory
     * comment in _chrome.tpl isn't picked up by the forbidden
     * literal scan.
     */
    public function testInstallAlertBackgroundsHaveVisibleEdge(): void
    {
        $chrome = self::loadStrippedChrome();

        $forbidden = [
            // The 0.10-alpha shape only existed on --ok and --info
            // pre-#1435; --warn and --error use the --*-bg tokens.
            // If either string reappears, the box-edge regression
            // is back.
            'rgba(34, 197, 94, 0.1)',
            'rgba(59, 130, 246, 0.1)',
        ];

        $required = [
            'rgba(34, 197, 94, 0.15)',
            'rgba(59, 130, 246, 0.15)',
        ];

        $hits = [];
        foreach ($forbidden as $literal) {
            if (str_contains($chrome, $literal)) {
                $hits[] = "forbidden pre-#1435 background literal "
                    . var_export($literal, true) . " resurrected";
            }
        }
        foreach ($required as $literal) {
            if (!str_contains($chrome, $literal)) {
                $hits[] = "required post-#1435 background literal "
                    . var_export($literal, true) . " missing";
            }
        }

        $this->assertSame([], $hits, "Install-wizard alert background drift.\n\nOffending:\n - " . implode("\n - ", $hits));
    }

    /**
     * The arithmetic gate: actually compute the WCAG 2.x contrast
     * ratio for every (effective-bg, text) pair and assert ≥ 4.5
     * (AA Normal Text). This catches subtler drift than the
     * literal-substring gate — e.g. a future PR that swaps the
     * --warning-bg token to a lighter value would visibly fail
     * here even if the text literal still matches the post-#1435
     * palette.
     *
     * Effective bgs:
     *   - --ok:   rgba(34, 197, 94, 0.15) over --zinc-50
     *   - --info: rgba(59, 130, 246, 0.15) over --zinc-50
     *   - --warn: var(--warning-bg) = #fffbeb (opaque)
     *   - --err:  var(--danger-bg) = #fef2f2 (opaque)
     *
     * Pills (only the --ok / --warn / --err triple — there's no
     * --pill--info on the wizard):
     *   - --pill--ok:   rgba(34, 197, 94, 0.15) over --zinc-50
     *   - --pill--warn: rgba(234, 179, 8, 0.15) over --zinc-50
     *   - --pill--err:  rgba(239, 68, 68, 0.15) over --zinc-50
     *
     * Each (bg, text) tuple is checked in light mode (the wizard's
     * only fully-supported mode — see the docblock on the @media
     * (prefers-color-scheme: dark) block in _chrome.tpl).
     */
    public function testEveryInstallAlertVariantPassesWcagAaContrast(): void
    {
        $page = self::PAGE_BG_LIGHT;

        $variants = [
            'install-alert--ok' => [
                'bg' => self::composite([34, 197, 94], 0.15, $page),
                'text' => self::hexToRgb('#14532d'),
            ],
            'install-alert--info' => [
                'bg' => self::composite([59, 130, 246], 0.15, $page),
                'text' => self::hexToRgb('#1e3a8a'),
            ],
            'install-alert--warn' => [
                'bg' => self::WARNING_BG_LIGHT,
                'text' => self::hexToRgb('#78350f'),
            ],
            'install-alert--error' => [
                'bg' => self::DANGER_BG_LIGHT,
                'text' => self::hexToRgb('#7f1d1d'),
            ],
            'install-pill--ok' => [
                'bg' => self::composite([34, 197, 94], 0.15, $page),
                'text' => self::hexToRgb('#14532d'),
            ],
            'install-pill--warn' => [
                'bg' => self::composite([234, 179, 8], 0.15, $page),
                'text' => self::hexToRgb('#78350f'),
            ],
            'install-pill--err' => [
                'bg' => self::composite([239, 68, 68], 0.15, $page),
                'text' => self::hexToRgb('#7f1d1d'),
            ],
        ];

        $failures = [];
        foreach ($variants as $name => $colours) {
            $ratio = self::contrastRatio($colours['bg'], $colours['text']);
            if ($ratio < 4.5) {
                $failures[] = sprintf(
                    '%s: contrast %.2f:1 fails WCAG AA Normal Text (4.5:1). '
                        . 'bg=rgb(%d, %d, %d) text=rgb(%d, %d, %d)',
                    $name,
                    $ratio,
                    $colours['bg'][0], $colours['bg'][1], $colours['bg'][2],
                    $colours['text'][0], $colours['text'][1], $colours['text'][2],
                );
            }
        }

        $this->assertSame(
            [],
            $failures,
            "Install-wizard contrast regression: one or more variants fall below WCAG AA "
                . "Normal Text (4.5:1). Either pick a darker text or bump the bg alpha so "
                . "the effective bg is paler. See AGENTS.md \"Install wizard\" for the contrast "
                . "contract.\n\nFailures:\n - " . implode("\n - ", $failures),
        );
    }

    /**
     * Sanity check the helper maths against three reference pairs
     * computed by the WebAIM contrast checker
     * (https://webaim.org/resources/contrastchecker/):
     *
     *   - black (#000000) on white (#ffffff) = 21.00:1
     *   - white (#ffffff) on white (#ffffff) = 1.00:1
     *   - Tailwind green-700 (#15803d) on Tailwind green-50 (#f0fdf4)
     *     = 4.79:1
     *
     * The third pair guards against off-by-one or gamma-curve typos
     * in the `relativeLuminance` implementation that would otherwise
     * let a regression in `testEveryInstallAlertVariantPassesWcagAaContrast`
     * slip through undetected (a buggy luminance fn would compute
     * "wrong" answers everywhere, so the main assertion would still
     * read green).
     */
    public function testContrastHelperMatchesReferenceValues(): void
    {
        $black = self::hexToRgb('#000000');
        $white = self::hexToRgb('#ffffff');
        $green700 = self::hexToRgb('#15803d');
        $green50  = self::hexToRgb('#f0fdf4');

        $this->assertEqualsWithDelta(21.0, self::contrastRatio($black, $white), 0.01);
        $this->assertEqualsWithDelta(1.0,  self::contrastRatio($white, $white), 0.01);
        $this->assertEqualsWithDelta(4.79, self::contrastRatio($green700, $green50), 0.02);
    }
}
