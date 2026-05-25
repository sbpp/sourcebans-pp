<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1438: pin the anti-FOUC bootloader's presence + normalized
 * equivalence across the FIVE template surfaces that render their own
 * `<head>`.
 *
 * Background
 * ----------
 *
 * The light/dark theme is keyed off the `dark` class on `<html>`, with
 * `:root` declaring the light tokens and `html.dark` overriding to the
 * dark tokens (see `web/themes/default/css/theme.css`). The bootloader
 * is a tiny synchronous inline `<script>` in `<head>` that reads
 * `localStorage['sbpp-theme']` and adds `class="dark"` to `<html>`
 * BEFORE the parser reaches `<body>`, so the very first paint lands in
 * the operator's persisted theme — no white flash + content flicker
 * (the FOUC the bootloader's name calls out).
 *
 * `web/themes/default/core/header.tpl` carries the bootloader for
 * the panel chrome (every `index.php?p=…` render). Pre-#1438 the four
 * other chromeless surfaces (`page_kickit.tpl`, `page_blockit.tpl`,
 * `page_uploadfile.tpl`, `updater.tpl`) shipped their own `<head>`
 * without the bootloader, so a dark-mode operator reaching any of
 * them painted the page in light mode regardless of preference.
 *
 * The kickit reproduction is the user-reported #1438 path: the public
 * Servers page's right-click context menu's "Kick player" item
 * (`web/scripts/server-context-menu.js`) builds the href directly to
 * `pages/admin.kickit.php?check=…`, NOT as an iframe — that's a
 * top-level navigation to the chromeless iframe template. Dark-mode
 * operator clicks Kick → full-page stark-white kickit grid → exactly
 * the bug report. The blockit iframe is still `display:none` in
 * `page_admin_comms_add.tpl` so the dark-mode bug doesn't surface
 * there today, but the parity fix in `page_blockit.tpl` future-proofs
 * against the moment somebody makes it visible.
 *
 * The audit-extended surfaces (#1438 follow-up review): `page_uploadfile.tpl`
 * (popup window opened via `window.open(...)` from admin upload pages —
 * mod icon / map image / demo file uploads; the popup ships its own
 * chromeless `<head>` and a dark-mode admin's parent page paints a
 * stark-white popup over the dark-mode parent) and `updater.tpl`
 * (the upgrade runner's standalone wizard hit by logged-in admins
 * on every panel upgrade — dark-mode admin runs an upgrade → stark-
 * white "Updater" page) ride the same bug class as kickit and got
 * swept in the same fix.
 *
 * The contract
 * ------------
 *
 * Two complementary gates:
 *
 * - `testEveryTemplateShipsTheBootloader` catches the "removed a
 *   load-bearing branch" class of drift (renamed THEME_KEY, dropped
 *   matchMedia branch, swapped `classList.add` for `classList.toggle`,
 *   etc.) by checking each required substring is present.
 * - `testBootloaderBodiesAreEquivalentAfterNormalization` catches
 *   the subtler "looks similar, differs in one branch" drift the
 *   fragment grep can't see (e.g. one copy drops the `window.matchMedia &&`
 *   null check while the others keep it — both contain
 *   `matchMedia('(prefers-color-scheme: dark)').matches` so the
 *   fragment test passes, but the behaviour on really old browsers
 *   diverges). The normalized-equivalence test extracts the first
 *   `<script>...</script>` block from each template's `<head>`,
 *   collapses runs of whitespace, and asserts all five normalized
 *   bodies are equal.
 *
 * The bootloader is intentionally MORE defensive than `theme.js`'s
 * `applyTheme(currentTheme())`: it ships an explicit
 * `window.matchMedia &&` null check, wraps the entire body in
 * `try/catch` (not just the localStorage write), and uses
 * `classList.add` instead of `classList.toggle` (the bootloader
 * never needs to remove the class — `:root` defaults to light). The
 * extra defensiveness exists because theme.js failing is recoverable
 * (the next render gets the right theme) but the bootloader failing
 * IS the FOUC bug. So the bootloader is *semantically* equivalent to
 * theme.js's resolution, not byte-identical. Documented in AGENTS.md
 * Conventions "Anti-FOUC theme bootloader".
 *
 * Why static-grep this and not the runtime
 * ----------------------------------------
 *
 * The runtime gates exist — `web/tests/e2e/specs/flows/theme-fouc.spec.ts`
 * (chrome) plus `web/tests/e2e/specs/flows/iframe-anti-fouc.spec.ts`
 * (kickit + blockit) — both stall the appropriate scripts and
 * assert `<html class="dark">` appears at the right moment. But e2e
 * gates only catch the regression once a real browser navigates
 * there, and `page_uploadfile.tpl` (popup window opener handshake)
 * + `updater.tpl` (one-shot upgrade page that needs pending
 * migrations to render) are awkward to drive from Playwright.
 * Static grep here catches:
 *
 *   - A future "simplification" that removes the bootloader from one
 *     template "because theme.js does it anyway" (the exact pre-#1367
 *     reasoning the panel-chrome bootloader exists to defeat).
 *   - A drift edit that updates the `'sbpp-theme'` key in theme.js +
 *     `core/header.tpl` but forgets the iframe / upload / updater
 *     templates.
 *   - A reviewer who removes any bootloader copy "for consistency
 *     with the install wizard" without realising the install wizard's
 *     exemption is documented separately (no `theme.js` / no toggle
 *     / no logged-in user).
 *   - A new chromeless `<head>` surface added without the bootloader
 *     (the test enumerates the known sites; adding a sixth requires
 *     a paired test edit, which forces the conversation about whether
 *     the new surface should ship the bootloader too).
 *
 * Pure file scanning — extends `PHPUnit\Framework\TestCase` directly
 * (no DB / session / Smarty bring-up). Sister of
 * `DeadJsCallSitesTest`; same per-file forbidden-substring shape,
 * inverted into per-required-fragment assertions.
 */
final class IframeChromeAntiFoucBootloaderTest extends TestCase
{
    /**
     * The four load-bearing fragments of the bootloader. We check each
     * separately so a regression message names the specific drift
     * (e.g. "lost the matchMedia branch" vs. "renamed THEME_KEY") rather
     * than a wholesale "your bootloader is wrong" diff.
     *
     * Fragments are intentionally tight — they fail loudly on a
     * cosmetic edit (whitespace, single vs. double quote) so the
     * semantic-equivalence contract with `theme.js` / `core/header.tpl`
     * is actually enforced. If a maintainer needs to tweak whitespace,
     * the fix is to update this test in the same PR, not to loosen
     * the literal.
     *
     * @return list<array{0: string, 1: string}>  Pairs of [substring, why].
     */
    private static function requiredFragments(): array
    {
        return [
            [
                "localStorage.getItem('sbpp-theme')",
                "must read the same THEME_KEY ('sbpp-theme') as `theme.js` — drift here "
                . "silently desyncs first paint from theme.js's boot-time `applyTheme(currentTheme())`",
            ],
            [
                "|| 'system'",
                "must fall back to the same default ('system') as `theme.js` — a different "
                . "default makes a first-time visitor see light/dark inconsistently across templates",
            ],
            [
                "matchMedia('(prefers-color-scheme: dark)').matches",
                "must consult the OS preference for the 'system' arm — without the matchMedia "
                . "branch, system-mode operators on dark OS always paint light first",
            ],
            [
                "document.documentElement.classList.add('dark')",
                "must add (NOT replace) the `dark` class on `<html>` — :root defaults to light "
                . "so removing isn't needed; using `.className =` or `.classList.toggle` would "
                . "stomp other classes the chrome (or a fork) may have added",
            ],
        ];
    }

    /**
     * The five template surfaces that ship a self-contained `<head>`
     * and therefore need their own bootloader copy. The install
     * wizard's templates under `install/` are deliberately NOT in
     * this list — see AGENTS.md "Install wizard" for the documented
     * exemption (no logged-in user, no `theme.js`, no toggle, no
     * persisted theme). The negative is enforced by
     * `testInstallWizardTemplatesDoNotCarryBootloader`.
     *
     * @return list<string>
     */
    private static function templatesRequiringBootloader(): array
    {
        return [
            'core/header.tpl',
            'page_kickit.tpl',
            'page_blockit.tpl',
            'page_uploadfile.tpl',
            'updater.tpl',
        ];
    }

    private static function loadTemplate(string $relativePath): string
    {
        $path = ROOT . 'themes/default/' . $relativePath;
        // Don't suppress with `@` — let PHPUnit surface the warning so a
        // genuinely missing file gets diagnostic info beyond our hand-built
        // failure message. The fallback below still fires if `file_get_contents`
        // returns false rather than warning (older PHP / userland streams).
        $contents = file_get_contents($path);
        if ($contents === false) {
            self::fail(
                "Could not read $path — file moved or test bootstrap broke? "
                . "If you renamed the template, update IframeChromeAntiFoucBootloaderTest::templatesRequiringBootloader() in the same PR."
            );
        }
        return $contents;
    }

    /**
     * Strip Smarty `{* ... *}` / `-{* ... *}-` comment blocks (greedy,
     * multiline) so my own explanatory comments at the top of each
     * template (which legitimately discuss the bootloader by name)
     * don't false-match the required-fragment grep. Matches both
     * delimiter pairs because `core/header.tpl` uses the default
     * `{` `}` and the iframe templates use `-{` `}-`.
     */
    private static function stripSmartyComments(string $contents): string
    {
        // {* ... *} for default-delimiter templates.
        $contents = (string) preg_replace('/\{\*.*?\*\}/s', '', $contents);
        // -{* ... *}- for the iframe templates with custom delimiters.
        return (string) preg_replace('/-\{\*.*?\*\}-/s', '', $contents);
    }

    /**
     * Extract the FIRST `<script>...</script>` block inside `<head>`
     * (the bootloader) from a template and normalize whitespace so
     * cosmetic differences (spaces vs tabs, indentation depth, line
     * endings) don't trip the equivalence check. The bootloader is
     * always the first inline script — it sits between `<title>` and
     * `<link rel="stylesheet">` per the placement contract.
     *
     * Returns the normalized body (the script's text content with
     * leading/trailing whitespace stripped and internal whitespace
     * collapsed to single spaces). Returns null when no inline script
     * is found, so the caller can name the missing-bootloader case
     * separately from the drift case.
     */
    private static function extractNormalizedBootloaderBody(string $contents): ?string
    {
        $contents = self::stripSmartyComments($contents);
        // Match the first <script>...</script> in the document. The
        // bootloader is the only inline script in <head> across all
        // five templates (the iframe templates have additional
        // <script src="..."> tags below the stylesheet link, but
        // those are SRC scripts and don't match `<script>` without
        // attributes). `<script>` with NO attributes is the canonical
        // shape — if a future template tweak adds attributes (e.g.
        // `<script nonce="...">`) the regex misses it and the test
        // fires "bootloader missing" instead of silently passing.
        if (!preg_match('#<script>(.*?)</script>#s', $contents, $m)) {
            return null;
        }
        // Collapse all whitespace runs to single spaces, trim ends.
        return trim((string) preg_replace('/\s+/', ' ', $m[1]));
    }

    public function testEveryTemplateShipsTheBootloader(): void
    {
        $misses = [];

        foreach (self::templatesRequiringBootloader() as $relativePath) {
            $contents = self::stripSmartyComments(self::loadTemplate($relativePath));

            foreach (self::requiredFragments() as [$fragment, $why]) {
                if (!str_contains($contents, $fragment)) {
                    $misses[] = "$relativePath is missing fragment " . var_export($fragment, true) . " — $why";
                }
            }
        }

        $this->assertSame(
            [],
            $misses,
            "Anti-FOUC bootloader drift detected (#1438 / #1367). The bootloader's logic must "
                . "stay semantically equivalent across `core/header.tpl`, `page_kickit.tpl`, "
                . "`page_blockit.tpl`, `page_uploadfile.tpl`, and `updater.tpl` (and semantically "
                . "equivalent to `theme.js`'s `applyTheme(currentTheme())` minus the localStorage "
                . "write — see AGENTS.md Conventions \"Anti-FOUC theme bootloader\"). The "
                . "chromeless surfaces are reachable as TOP-LEVEL navigations from the public "
                . "Servers page's right-click context menu (kickit), as `<iframe>`s from the "
                . "post-Ban / post-Block success dialogs (kickit / blockit), as popup windows "
                . "from admin upload pages (uploadfile), and as standalone upgrade pages "
                . "(updater); without the bootloader, dark-mode operators see a stark-white "
                . "page or iframe-content blast — the user-reported #1438 symptom. Drift "
                . "findings:\n  - "
                . implode("\n  - ", $misses),
        );
    }

    /**
     * Cross-check: the bootloader sits in `<head>` ABOVE the
     * `<link rel="stylesheet" …>` line (parser-blocking + synchronous,
     * so the class is set BEFORE the stylesheet resolves the cascade
     * for `:root` vs. `html.dark` tokens). A bootloader that lives
     * AFTER the stylesheet would still beat `<body>` parsing for a
     * static HTML file, but a slow stylesheet network response would
     * push the `<script>` execution behind the first paint — re-opening
     * the FOUC on slow connections. Pin the order so a future
     * "let's move scripts to the bottom" cleanup catches this gate.
     */
    public function testBootloaderPrecedesStylesheetLink(): void
    {
        $misordered = [];

        foreach (self::templatesRequiringBootloader() as $relativePath) {
            // Strip Smarty comments BEFORE computing offsets — the
            // canonical bootloader in `core/header.tpl` carries a long
            // `{*...*}` comment block that explains the placement
            // rationale and the prose itself includes the literal
            // string `<link rel="stylesheet">` ("Placement: BEFORE
            // the <link rel="stylesheet"> below..."). Without the
            // strip step, `strpos` returns the comment-block offset
            // and the ordering check fires a false positive.
            $contents = self::stripSmartyComments(self::loadTemplate($relativePath));

            $bootloaderOffset = strpos($contents, "localStorage.getItem('sbpp-theme')");
            $stylesheetOffset = strpos($contents, '<link rel="stylesheet"');

            if ($bootloaderOffset === false || $stylesheetOffset === false) {
                // The previous test already names the missing-fragment
                // failure; skip here so this test stays scoped to
                // ordering alone.
                continue;
            }

            if ($bootloaderOffset > $stylesheetOffset) {
                $misordered[] = "$relativePath has the bootloader AFTER `<link rel=\"stylesheet\">` "
                    . "(bootloader offset $bootloaderOffset, stylesheet offset $stylesheetOffset)";
            }
        }

        $this->assertSame(
            [],
            $misordered,
            "Anti-FOUC bootloader placement regressed. The inline `<script>` must precede "
                . "`<link rel=\"stylesheet\">` so a slow stylesheet response doesn't push the "
                . "class-flip behind first paint (re-opening the FOUC on slow connections). "
                . "See `core/header.tpl`'s in-template comment for the placement rationale.\n  - "
                . implode("\n  - ", $misordered),
        );
    }

    /**
     * Whitespace-normalized equivalence across every bootloader copy.
     * `testEveryTemplateShipsTheBootloader` catches missing fragments,
     * but two templates could have entirely different shape around the
     * fragments — e.g. one drops the `window.matchMedia &&` null check,
     * the other keeps it; both contain `matchMedia('(prefers-color-scheme: dark)').matches`
     * so the fragment grep passes. This test extracts the first
     * `<script>...</script>` body from each template and asserts they
     * all normalize to the SAME string. Catches:
     *
     *   - Surrounding-code drift (added an unrelated statement to one
     *     copy; the others stayed at the minimal IIFE).
     *   - Null-check drift (one copy drops `window.matchMedia &&`).
     *   - try/catch scope drift (one copy narrows the catch to just
     *     the localStorage call, others keep the wide try around the
     *     matchMedia branch too).
     *   - classList method drift (one copy uses `.toggle('dark', d)`
     *     instead of the correct `.add('dark')`).
     *
     * The reference is `core/header.tpl` (the chrome's bootloader,
     * shipped first, by far the most-touched template — drift will
     * always be "the other copies fell behind this one", not vice
     * versa). Diff messages name the offending template so a
     * maintainer hitting this gate sees which copy drifted.
     */
    public function testBootloaderBodiesAreEquivalentAfterNormalization(): void
    {
        $bodies = [];
        foreach (self::templatesRequiringBootloader() as $relativePath) {
            $body = self::extractNormalizedBootloaderBody(self::loadTemplate($relativePath));
            if ($body === null) {
                // Don't double-fail with the missing-fragments test;
                // skip this template for the equivalence check.
                continue;
            }
            $bodies[$relativePath] = $body;
        }

        $reference = $bodies['core/header.tpl'] ?? null;
        $this->assertNotNull(
            $reference,
            "core/header.tpl's bootloader is the reference for the equivalence check — "
                . "if it's missing, fix `testEveryTemplateShipsTheBootloader` first (the "
                . "chrome's bootloader is the canonical shape)."
        );

        $drifted = [];
        foreach ($bodies as $path => $body) {
            if ($body !== $reference) {
                $drifted[] = "$path: bootloader body diverges from core/header.tpl"
                    . " (normalized — whitespace collapsed). Got:\n      "
                    . $body
                    . "\n    Expected (from core/header.tpl):\n      "
                    . $reference;
            }
        }

        $this->assertSame(
            [],
            $drifted,
            "Anti-FOUC bootloader body drifted between template copies. The bootloader's "
                . "IIFE must be byte-identical (modulo whitespace) across every chromeless "
                . "`<head>` surface — diverging shape silently changes behaviour on edge cases "
                . "(missing matchMedia, private-mode localStorage SecurityError, etc.) and a "
                . "user navigating between sibling pages sees the theme flicker mid-flow. "
                . "Findings:\n  - "
                . implode("\n  - ", $drifted),
        );
    }

    /**
     * Install-wizard exemption: NONE of the templates under
     * `web/themes/default/install/` may carry the bootloader. The
     * wizard runs against an unconfigured panel with no `theme.js`,
     * no toggle, no logged-in user, and therefore no
     * `localStorage['sbpp-theme']` to read. Adding the bootloader
     * there without a paired toggle would be a no-op at best
     * (`localStorage.getItem('sbpp-theme')` returns `null` →
     * fall-through to system → resolves to OS-dark only when OS is
     * dark) and a confusing one-off "wizard partially-honours-OS"
     * surface at worst. The exemption is documented in AGENTS.md
     * Conventions "Anti-FOUC theme bootloader" → "The install wizard
     * (`web/install/_chrome.tpl`) does NOT carry the bootloader.";
     * pin it across EVERY install template so a well-meaning "let's
     * mirror the panel chrome" sweep has to delete this test first
     * (forcing the conversation).
     *
     * Pre-review (#1438) this test only checked `_chrome.tpl`. The
     * gap let `_chrome_close.tpl` or any of the per-step
     * `page_*.tpl` templates silently carry the bootloader; the
     * follow-up review caught this. Now globs the whole directory.
     */
    public function testInstallWizardTemplatesDoNotCarryBootloader(): void
    {
        $installDir = ROOT . 'themes/default/install';
        $this->assertDirectoryExists(
            $installDir,
            'install/ template directory must exist for the exemption check to land somewhere meaningful',
        );

        $templates = glob($installDir . '/*.tpl');
        $this->assertNotFalse(
            $templates,
            "glob() returned false for $installDir — filesystem error?",
        );
        $this->assertNotEmpty(
            $templates,
            "No *.tpl files under $installDir — install wizard moved? Update this test or the wizard.",
        );

        $offenders = [];
        foreach ($templates as $path) {
            $contents = (string) file_get_contents($path);
            $stripped = self::stripSmartyComments($contents);
            if (str_contains($stripped, "localStorage.getItem('sbpp-theme')")) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'Install wizard templates must NOT carry the anti-FOUC bootloader. The wizard '
                . 'runs pre-configuration with no `theme.js` / no theme toggle / no logged-in '
                . 'user, so `localStorage[\'sbpp-theme\']` is never set during install. '
                . 'Adding the bootloader here without a paired theme toggle silently introduces '
                . 'an inconsistent partial-OS-honouring surface. See AGENTS.md Conventions '
                . '"Anti-FOUC theme bootloader" → "The install wizard does NOT carry the '
                . "bootloader.\". Offenders:\n  - "
                . implode("\n  - ", $offenders),
        );
    }
}
