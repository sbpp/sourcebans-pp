<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Issue #1448: pin the "every `class="btn--*"` modifier carries the
 * base `btn` token" structural contract across every panel-chrome
 * surface that emits HTML — Smarty templates, page handlers, view
 * DTOs, AND the runtime HTML emitter at
 * `web/themes/default/js/theme.js` (the load-bearing site that
 * actually paints the live drawer / toast / Notes-pane chrome).
 *
 * Background
 * ----------
 *
 * The panel's button rule (`web/themes/default/css/theme.css`):
 *
 *   .btn {
 *     --btn-bg: var(--zinc-900);
 *     --btn-color: white;
 *     --btn-border: transparent;
 *     --btn-bg-hover: var(--zinc-800);
 *     display: inline-flex; align-items: center; justify-content: center;
 *     ...;
 *     background: var(--btn-bg); color: var(--btn-color);
 *     border: 1px solid var(--btn-border);
 *   }
 *
 * is the load-bearing site. It declares the `--btn-*` custom-property
 * defaults AND applies them as `background` / `color` / `border` /
 * `display: inline-flex` / `padding` / `height`.
 *
 * The modifier rules — `.btn--ghost`, `.btn--primary`, `.btn--secondary`,
 * `.btn--danger` — are colour-variable overrides only:
 *
 *   .btn--ghost {
 *     --btn-bg: transparent;
 *     --btn-color: var(--text-muted);
 *     --btn-border: transparent;
 *     --btn-bg-hover: var(--bg-muted);
 *   }
 *
 * The sizing modifiers — `.btn--sm`, `.btn--icon`, `.btn--xs` — are
 * variable overrides PLUS a thin layer of geometry on top
 * (`width` / `height` / `padding` / `font-size`). They still don't
 * carry the load-bearing `background` / `color` / `border` /
 * `display: inline-flex` declarations — those live exclusively on
 * `.btn`. Without the base in the class chain, the `--btn-*`
 * variables are SET but never READ, and the `<button>` falls back
 * to the user-agent default chrome (typically a grey 1px-border
 * pill).
 *
 * The bug presents most visibly on the mobile viewport's burger
 * menu (the user-reported regression in #1448 — "Mobile view -
 * Burger menu background is grey"), but it's structurally identical
 * on the drawer-close `X` and the palette-close `Esc` button. The
 * fix at #1448 prepends `btn ` to the offending sites; this test
 * pins the contract so a future copy-paste doesn't re-open the bug
 * class.
 *
 * Implementation
 * --------------
 *
 * Parser-style sweep, not literal-substring grep:
 *
 *   1. Walk every `*.tpl`, `*.php`, and `*.js` under the documented
 *      scan roots.
 *   2. Strip Smarty `{* … *}` comments (default delimiters) AND
 *      `-{* … *}-` (the non-default delimiter shape used by
 *      `page_login.tpl` / `page_blockit.tpl` / `page_kickit.tpl` /
 *      `page_admin_servers_rcon.tpl`); strip PHP and JS line
 *      comments. The strip pass is what stops this test's own
 *      explanatory comments — and the AGENTS.md-style reminders
 *      next to the three fixed sites — from false-matching the
 *      gate. Without the strip, the literal substring
 *      `btn--ghost btn--icon` quoted inside a `{* ... *}` comment
 *      block would fire the gate every run.
 *   3. Extract every `class="…"` / `class='…'` attribute body. The
 *      regex uses a negative lookbehind to skip name-prefixed
 *      attributes like `data-class=`.
 *   4. Split on whitespace, classify tokens as `btn` (the base) /
 *      `btn--<x>` (a modifier) / other.
 *   5. If any token is a `btn--<x>` modifier and no `btn` token is
 *      present, the file fails the gate.
 *
 * Smarty-conditional class chains (`class="btn{if $foo} btn--primary{/if}"`)
 * are skipped — the gate doesn't expand templates, so it can't
 * validate which branch a given token came from. False negatives
 * are bounded to chains with NO base in any branch (e.g.
 * `class="{if $x}btn--primary{else}btn--ghost{/if}"`); none present
 * in the codebase today, and would surface as a visible UA-default
 * render whenever the conditional path runs. A fuller fix would
 * build a per-branch tokenizer; deferred until a real bypass
 * appears.
 *
 * Pure file scanning. No DB / session / Smarty bring-up. Extends
 * `PHPUnit\Framework\TestCase` directly so test discovery + CI
 * scheduling stay cheap (mirrors `DeadJsCallSitesTest`).
 */
final class ButtonClassChainTest extends TestCase
{
    /**
     * Roots scanned by the gate. Each entry is `[path, recursive]`
     * — recursive directories use the iterator, single files map
     * straight onto `collectFiles()`.
     *
     * Coverage rationale:
     *
     * - `themes/`           — every Smarty template plus the rare
     *                         PHP view-helper. Includes the install
     *                         wizard chrome under
     *                         `themes/default/install/`.
     * - `themes/default/js/` — the runtime HTML emitter. The drawer
     *                         close-X, the toast close button, the
     *                         Notes-pane delete button, and the
     *                         row-level copy buttons all live here
     *                         and ship `<button class="btn btn--ghost
     *                         btn--icon">` strings. Without this
     *                         scan root the gate would only protect
     *                         server-rendered chrome and miss the
     *                         most actively-edited surface.
     * - `pages/`            — page handlers occasionally render
     *                         inline HTML.
     * - `includes/View/`    — view DTOs are pure value objects;
     *                         scan is preventive.
     * - `install/`          — wizard chrome (`recovery.php`,
     *                         `already-installed.php`, etc.).
     *                         Wizard surfaces today don't use the
     *                         panel `.btn` chain (they ship their
     *                         own self-contained inline CSS) but a
     *                         future maintainer reaching for
     *                         `<button class="btn--primary">` here
     *                         would silently regress without this.
     * - `updater/`          — `web/updater/index.php` lives outside
     *                         the themes tree.
     * - `api/handlers/`     — JSON-emitting handlers; preventive
     *                         coverage for any future error-page
     *                         render that leaks inline HTML.
     * - `scripts/`          — public JS at the panel root (sb.js,
     *                         banlist.js, comment-actions.js,
     *                         server-tile-hydrate.js, etc.). Same
     *                         emit-HTML rationale as the theme JS.
     *
     * @return list<string>
     */
    private static function scanRoots(): array
    {
        return [
            ROOT . 'themes',
            ROOT . 'pages',
            ROOT . 'includes/View',
            ROOT . 'install',
            ROOT . 'updater',
            ROOT . 'api/handlers',
            ROOT . 'scripts',
        ];
    }

    /**
     * Strip comments before scanning so explanatory blocks that
     * quote literal `class="btn--*"` strings (this test's own
     * docblock + the AGENTS.md-style reminders next to the three
     * #1448 fixes are the canonical references) don't false-match
     * the gate.
     *
     * Per file extension:
     *
     * - `*.php` — `php_strip_whitespace()`. Native PHP tokenizer;
     *             handles every comment shape PHP supports.
     * - `*.tpl` — Smarty `{* … *}` (default delimiters) plus
     *             `-{* … *}-` (the non-default delimiter shape used
     *             by `page_login.tpl` / `page_blockit.tpl` /
     *             `page_kickit.tpl` / `page_admin_servers_rcon.tpl`,
     *             documented under "Templates + View DTOs" in
     *             AGENTS.md). Then C-style block comments and `//`
     *             line comments inside `<script>` blocks the
     *             template embeds.
     * - `*.js`  — C-style block comments and `//` line comments.
     *             A regex pass — fine for the way `theme.js` is
     *             written (no embedded templated code, no
     *             single-line `//` inside string literals at
     *             column 0). A future complex JS file with
     *             string-literal-inside-comment edge cases would
     *             want the JS tokenizer; defer until needed.
     */
    private static function stripCommentsForScan(string $path, string $contents): string
    {
        if (str_ends_with($path, '.php')) {
            return php_strip_whitespace($path);
        }
        if (str_ends_with($path, '.tpl')) {
            $contents = (string) preg_replace('/\{\*.*?\*\}/s', '', $contents);
            $contents = (string) preg_replace('/-\{\*.*?\*\}-/s', '', $contents);
            $contents = (string) preg_replace('!/\*.*?\*/!s', '', $contents);
            $contents = (string) preg_replace('!(^|[^:])//[^\n]*!', '$1', $contents);
            return $contents;
        }
        if (str_ends_with($path, '.js')) {
            $contents = (string) preg_replace('!/\*.*?\*/!s', '', $contents);
            $contents = (string) preg_replace('!(^|[^:])//[^\n]*!', '$1', $contents);
            return $contents;
        }
        return $contents;
    }

    /**
     * Walk a directory tree and return every `*.tpl` / `*.php` /
     * `*.js` file path under it, sorted for deterministic output.
     *
     * @return list<string>
     */
    private static function collectFiles(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
        $files = [];
        foreach ($iter as $entry) {
            if (!$entry->isFile()) {
                continue;
            }
            $path = $entry->getPathname();
            if (
                !str_ends_with($path, '.tpl')
                && !str_ends_with($path, '.php')
                && !str_ends_with($path, '.js')
            ) {
                continue;
            }
            $files[] = $path;
        }
        sort($files);
        return $files;
    }

    /**
     * Extract every `class="..."` / `class='...'` attribute body.
     * The lookbehind `(?<![-_\w])` guards against name-prefixed
     * attributes like `data-class=`, `aria-class=`, or
     * `someClass=` JS-style — only an unprefixed `class` keyword
     * matches.
     *
     * The attribute matcher is intentionally simple — quoted-string
     * boundaries on the value, no embedded escaping. HTML + Smarty
     * + the JS string literals in `theme.js` don't carry embedded
     * same-quote characters inside an attribute value, so the
     * simple match is correct.
     *
     * Returns the raw bodies (NOT split into tokens) so the caller
     * can run conditional-detection logic before splitting.
     *
     * @return list<string>
     */
    private static function extractClassAttributes(string $contents): array
    {
        $matches = [];
        if (preg_match_all('/(?<![-_\w])class\s*=\s*"([^"]*)"/', $contents, $double)) {
            foreach ($double[1] as $body) {
                $matches[] = $body;
            }
        }
        if (preg_match_all("/(?<![-_\\w])class\\s*=\\s*'([^']*)'/", $contents, $single)) {
            foreach ($single[1] as $body) {
                $matches[] = $body;
            }
        }
        return $matches;
    }

    /**
     * Single-method pin: every `class="..."` attribute carrying a
     * `btn--*` modifier token MUST also carry the base `btn` token.
     * Smarty-conditional class chains (the `{if $x} btn--primary{/if}`
     * shape) are skipped — the gate's output then surfaces only the
     * structural-bug shape this test exists to catch.
     *
     * Captures a sanity-check counter alongside the hits so the
     * test fails loudly if the scan ever ends up touching zero
     * files (the failure shape if `ROOT` resolves wrong, or if a
     * future refactor of `scanRoots()` lands an unreadable path).
     * Thresholds are deliberately loose — the point is "scope is
     * non-empty," not "this exact number." The current codebase
     * touches ~750 files / extracts ~3500 class attributes;
     * `> 100` files and `> 200` attribute bodies leaves headroom
     * for half the panel templates being stripped without a
     * false-fail.
     *
     * The literal expected hit count is `0`. Failure messages name
     * the file path + the offending class chain so a regression
     * triages directly to the call site.
     */
    public function testEveryBtnModifierCarriesTheBaseBtnToken(): void
    {
        $hits = [];
        $filesScanned = 0;
        $classAttrsExtracted = 0;

        foreach (self::scanRoots() as $root) {
            foreach (self::collectFiles($root) as $path) {
                $filesScanned++;
                $raw = (string) file_get_contents($path);
                $stripped = self::stripCommentsForScan($path, $raw);
                foreach (self::extractClassAttributes($stripped) as $body) {
                    $classAttrsExtracted++;
                    if (str_contains($body, '{')) {
                        continue;
                    }
                    $tokens = preg_split('/\s+/', trim($body)) ?: [];
                    $hasBase = false;
                    $modifiers = [];
                    foreach ($tokens as $tok) {
                        if ($tok === 'btn') {
                            $hasBase = true;
                            continue;
                        }
                        if (str_starts_with($tok, 'btn--')) {
                            $modifiers[] = $tok;
                        }
                    }
                    if ($modifiers !== [] && !$hasBase) {
                        $rel = str_starts_with($path, ROOT) ? substr($path, strlen(ROOT)) : $path;
                        $hits[] = sprintf(
                            '%s: class="%s" carries %s but not the base `btn`',
                            $rel,
                            $body,
                            implode(' / ', $modifiers),
                        );
                    }
                }
            }
        }

        $this->assertGreaterThan(
            100,
            $filesScanned,
            "Sanity check: scan touched only {$filesScanned} files, expected > 100. "
                . 'A `ROOT` typo or an unreadable scan root will produce a false-pass; this assertion '
                . 'fails loudly so the gate cannot silently de-cover.',
        );
        $this->assertGreaterThan(
            200,
            $classAttrsExtracted,
            "Sanity check: scan extracted only {$classAttrsExtracted} `class=\"…\"` attribute bodies, "
                . 'expected > 200. A regex regression in `extractClassAttributes()` would surface here.',
        );

        $this->assertSame(
            0,
            count($hits),
            "One or more `class=\"btn--*\"` attributes are missing the base `btn` token. Without `.btn`, the modifier rules set CSS custom properties (`--btn-bg`, `--btn-color`, …) and (for sizing modifiers) layer geometry on top — but nothing reads the `--btn-*` variables and nothing carries the load-bearing `background` / `color` / `border` / `display: inline-flex` declarations except `.btn` itself. Buttons render with the user-agent default chrome instead of the panel's button styling. See #1448 for the canonical reproduction (mobile burger menu) and AGENTS.md \"Anti-patterns\" for the rule.\n\nOffending sites:\n - "
                . implode("\n - ", $hits),
        );
    }
}
