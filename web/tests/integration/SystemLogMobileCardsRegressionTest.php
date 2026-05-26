<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1462: the admin System Log sub-tab
 * (`page_admin_settings_logs.tpl`) renders a `<table class="table">`
 * for the desktop view. At `<=768px` the global
 * `.table { display: none }` rule in `web/themes/default/css/theme.css`
 * (originally paired with the bans / comms `.ban-cards` mobile mirror)
 * collapses every `<table class="table">` on the panel. The System Log
 * never shipped a paired mobile surface, so on mobile the chrome
 * around the table rendered fine (page heading, "Clear log" button,
 * pagination footer) while the actual log rows were silently hidden —
 * the reporter's exact symptom ("no logs visible" on the iPhone view).
 *
 * The fix adds a paired `<ul class="log-cards">` block that mirrors
 * `$log_items` as native `<details>` disclosures, gated by the same
 * `<=768px` breakpoint the desktop table is hidden at. Native
 * `<details>` carries the disclosure semantic for free — keyboard-
 * reachable, screen-reader-announced, JS-free.
 *
 * This suite is the static gate against the regression. The five
 * contracts pinned:
 *
 *   1. The template ships `<ul class="log-cards" data-testid="logs-cards">`.
 *   2. Each entry is a `<details class="log-card" data-testid="log-card" data-id="{$log.lid}">`.
 *   3. Both surfaces iterate the SAME `$log_items` (so the visible
 *      row count is viewport-symmetric — no chance one surface
 *      filters the data while the other doesn't).
 *   4. `theme.css` declares `.log-cards { display: none }` by default
 *      AND `.log-cards { display: block }` inside the `<=768px`
 *      media query, paired with `.log-cards { display: none }`
 *      inside the `>=769px` media query — the same dance `.ban-cards`
 *      uses, so the surfaces never compete at intermediate viewports.
 *   5. The desktop table still carries `class="table table--clickable-rows"`
 *      + `data-testid="logs-table"` so the sibling #1443 regression
 *      guard's contract stays intact (don't drop the table when
 *      adding the card mirror — both surfaces are load-bearing,
 *      each at its own viewport).
 *   6. The `onkeydown` keyboard dispatcher on the summary row does
 *      NOT carry an inline `{event.preventDefault();…}` body. The
 *      pre-existing (`#1451`) form
 *      `onkeydown="if(event.key==='Enter'…){event.preventDefault();…}"`
 *      contains a `{event.preventDefault();…}` substring with no
 *      whitespace after the opening brace — under Smarty's default
 *      `auto_literal=true` that substring is parsed as a Smarty tag
 *      and the template fails fresh-compile with
 *      "Unexpected '.', expected one of: '}'". Caches built on older
 *      Smarty versions still served, so the bug hid for months until
 *      this PR's E2E spec finally exercised the surface on a clean
 *      compile-dir. The fix lifts the handler into the existing
 *      `<script>{literal}…{/literal}</script>` block as
 *      `window.handleLogRowKey` and the attribute reduces to a
 *      simple `onkeydown="handleLogRowKey(event, this);"` — no inner
 *      braces, no Smarty hazard. This contract pins that the fix is
 *      in place and the inline-brace form does not creep back.
 */
final class SystemLogMobileCardsRegressionTest extends TestCase
{
    private string $template;
    private string $css;

    protected function setUp(): void
    {
        parent::setUp();

        $templatePath = ROOT . 'themes/default/page_admin_settings_logs.tpl';
        $template     = file_get_contents($templatePath);
        if ($template === false) {
            self::fail("setUp could not read system-log template at {$templatePath}");
        }
        $this->template = $template;

        $cssPath = ROOT . 'themes/default/css/theme.css';
        $css     = file_get_contents($cssPath);
        if ($css === false) {
            self::fail("setUp could not read theme.css at {$cssPath}");
        }
        $this->css = $css;
    }

    /**
     * The template must ship the mobile card wrapper. The
     * `data-testid="logs-cards"` hook is the E2E anchor; the
     * `class="log-cards"` is the load-bearing visibility gate.
     */
    public function testTemplateShipsMobileCardWrapper(): void
    {
        $this->assertMatchesRegularExpression(
            '/<ul\b[^>]*\bclass="[^"]*\blog-cards\b[^"]*"[^>]*\bdata-testid="logs-cards"/',
            $this->template,
            "`page_admin_settings_logs.tpl` must ship an "
            . "`<ul class=\"log-cards\" data-testid=\"logs-cards\">` block "
            . "alongside the desktop `<table class=\"table\">`. At `<=768px` the "
            . "global `.table { display: none }` rule in `theme.css` hides the "
            . "desktop table; without the paired mobile surface the rows vanish "
            . "from the iPhone view (#1462). The `aria-label` on the `<ul>` "
            . "should also be present so AT users hear the list described — "
            . "see the canonical reference shape in the same template."
        );
    }

    /**
     * Each entry in the mobile list is a native `<details>` element.
     * This is intentional — `<details>` is the platform's built-in
     * disclosure widget, so we get keyboard reachability (Tab),
     * keyboard activation (Enter / Space), and screen-reader
     * announcement ("disclosure triangle, collapsed") for free.
     * Re-rolling this with a `<div>` + `role="button"` + `tabindex` +
     * `onkeydown` would be the wrong shape — the native semantic is
     * the contract.
     *
     * The hook `data-testid="log-card"` is the E2E anchor; the
     * `data-id="{$log.lid}"` carries the row identifier for spec
     * lookups (so a spec can assert "the card for log #N opened").
     */
    public function testEachMobileEntryIsANativeDetailsDisclosure(): void
    {
        $this->assertMatchesRegularExpression(
            '/<details\b[^>]*\bclass="[^"]*\blog-card\b[^"]*"[^>]*\bdata-testid="log-card"[^>]*\bdata-id="\{\$log\.lid\}"/',
            $this->template,
            "Each `<li>` inside `.log-cards` must contain a "
            . "`<details class=\"log-card\" data-testid=\"log-card\" data-id=\"{\$log.lid}\">` "
            . "element. Native `<details>` is the contract — the disclosure "
            . "is keyboard-reachable + screen-reader-announced for free, no JS "
            . "needed. Don't re-roll this with `<div>` + `role=\"button\"` + "
            . "`tabindex=\"0\"` + an `onkeydown` handler; that's the desktop "
            . "table's shape (where the click target is `<tr>`, which has no "
            . "native disclosure semantic). On `<details>` the browser does "
            . "the right thing out of the box."
        );
    }

    /**
     * Both surfaces must iterate the same source — `$log_items`.
     * If the mobile card foreach ever drifts to a different View
     * property (a filter, a slice, a re-fetch), the viewport would
     * silently determine "what set of logs the user sees", which is
     * the same bug class as the original #1462 (rows invisible at
     * one viewport) but worse — the mismatch would be subtle.
     */
    public function testBothSurfacesIterateTheSameLogItemsSource(): void
    {
        // Pull both `{foreach}` constructs out of the template and
        // assert each iterates `$log_items`. Smarty's `{foreach}` shape
        // is `{foreach from=$x item="y"}` — match the `from=` clause.
        $foreachCount = preg_match_all(
            '/\{foreach\s+from=\$log_items\s+item="log"\}/',
            $this->template,
            $matches,
        );

        // Exactly two `{foreach from=$log_items item="log"}` calls:
        // one for the desktop `<tbody>`, one for the mobile `<ul>`.
        // A future drift (extra foreach for a third surface, or
        // dropping one of them) is caught here.
        $this->assertSame(
            2,
            $foreachCount,
            "`page_admin_settings_logs.tpl` must contain exactly TWO "
            . "`{foreach from=\$log_items item=\"log\"}` constructs — one for "
            . "the desktop `<table>`'s `<tbody>` and one for the mobile "
            . "`<ul class=\"log-cards\">`. Found {$foreachCount}. If the "
            . "iteration drifts to a different View property on one surface, "
            . "the viewport would silently filter the visible row set — same "
            . "bug class as the original #1462 but harder to notice."
        );
    }

    /**
     * `theme.css` must declare `.log-cards { display: none }` by
     * default. The cards are mobile-only — at desktop viewports
     * the `<table>` does the rendering and the cards must stay
     * out of layout.
     */
    public function testThemeCssDefaultsLogCardsToHidden(): void
    {
        $this->assertMatchesRegularExpression(
            '/(?<![\w-])\.log-cards\s*\{[^}]*display\s*:\s*none/',
            $this->css,
            "`theme.css` must declare `.log-cards { display: none; … }` as "
            . "the default rule — desktop viewports paint the `<table>`, the "
            . "cards stay out of layout. Without the default-hidden rule both "
            . "surfaces would paint at desktop viewports (duplicate row set, "
            . "broken layout). The `>=769px` media query below also pins this "
            . "shape; the default-hidden rule is the load-bearing belt against "
            . "media-query-stripping theme forks."
        );
    }

    /**
     * `theme.css` must flip `.log-cards { display: block }` inside
     * a `@media (max-width: 768px)` block. There are several such
     * blocks scattered across `theme.css` (each subsystem owns its
     * own mobile overrides next to its own desktop rules); the
     * contract is "AT LEAST ONE of them carries the `.log-cards`
     * rule", not "only one of them". The same shape `.ban-cards`
     * uses — its mobile-block rule lives next to the desktop
     * table-collapse rule for the same reason (co-located concerns).
     */
    public function testThemeCssShowsLogCardsAtMobileBreakpoint(): void
    {
        $blocks = $this->extractAllMediaBlocks($this->css, '/@media\s*\(\s*max-width:\s*768px\s*\)\s*/');
        $this->assertNotEmpty(
            $blocks,
            "`theme.css` must include at least one `@media (max-width: 768px) { … }` "
            . "block. The block that hosts `.table { display: none; }` + "
            . "`.ban-cards { display: block; }` must also carry "
            . "`.log-cards { display: block; }` so the trio flips together."
        );

        $foundMatchingBlock = false;
        foreach ($blocks as $block) {
            if (preg_match('/\.log-cards\s*\{\s*display\s*:\s*block/', $block) === 1) {
                $foundMatchingBlock = true;
                break;
            }
        }
        $this->assertTrue(
            $foundMatchingBlock,
            "At least one `@media (max-width: 768px) { … }` block in `theme.css` "
            . "must include `.log-cards { display: block; }`, alongside "
            . "`.table { display: none; }` and `.ban-cards { display: block; }`. "
            . "Without this rule the mobile card surface stays hidden by the "
            . "default `display: none` and the System Log paints empty on mobile "
            . "(the #1462 regression). Co-locate it next to the `.table` / "
            . "`.ban-cards` rules so the trio flips together on the same "
            . "breakpoint — splitting them across separate media queries "
            . "invites drift the next time someone tweaks the breakpoint."
        );
    }

    /**
     * The sibling `@media (min-width: 769px)` block must ALSO carry
     * `.log-cards { display: none; }`. The default-hidden rule above
     * already covers desktop, but mirroring the `.ban-cards` shape
     * (which uses BOTH rules — default-hidden AND `>=769px`-hidden)
     * keeps the chrome contract symmetric and makes the intent
     * obvious to future readers: "this surface flips between two
     * states, one per breakpoint" — no "what about widths > 768px
     * that don't match the `<=768px` media query?" ambiguity.
     */
    public function testThemeCssExplicitlyHidesLogCardsAtDesktop(): void
    {
        $blocks = $this->extractAllMediaBlocks($this->css, '/@media\s*\(\s*min-width:\s*769px\s*\)\s*/');
        $this->assertNotEmpty(
            $blocks,
            "`theme.css` must include at least one `@media (min-width: 769px) { … }` "
            . "block. The sibling `.ban-cards { display: none; }` rule lives in "
            . "such a block; the System Log's mobile mirror follows the same shape."
        );

        $foundMatchingBlock = false;
        foreach ($blocks as $block) {
            if (preg_match('/\.log-cards\s*\{\s*display\s*:\s*none/', $block) === 1) {
                $foundMatchingBlock = true;
                break;
            }
        }
        $this->assertTrue(
            $foundMatchingBlock,
            "At least one `@media (min-width: 769px) { … }` block in `theme.css` "
            . "must include `.log-cards { display: none; }` alongside the matching "
            . "`.ban-cards { display: none; }` rule. The default `display: none` "
            . "above already handles desktop, but mirroring the `.ban-cards` "
            . "explicit-pair shape keeps the chrome contract symmetric and "
            . "obvious to future readers (one rule per breakpoint, in lockstep "
            . "with `.ban-cards`)."
        );
    }

    /**
     * Pull the bodies of every `@media (…)` block matching
     * `$openAnchorRegex` out of a CSS string by walking brace depth.
     * PCRE's `[^}]*` shape can't traverse the nested per-selector
     * `{}` pairs inside a media block (the first inner `}` ends the
     * match), and `theme.css` ships ten distinct
     * `@media (max-width: 768px)` blocks scattered across subsystems
     * — each rules block lives next to its desktop sibling. A
     * regex anchored to "the" mobile block would only match the
     * first one and miss the one that actually carries the
     * `.log-cards` rule.
     *
     * Returns a list of substrings between matching `{` and `}`
     * pairs (NOT including the braces themselves).
     *
     * @return list<string>
     */
    private function extractAllMediaBlocks(string $css, string $openAnchorRegex): array
    {
        if (preg_match_all($openAnchorRegex, $css, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return [];
        }

        $blocks = [];
        $len    = strlen($css);
        foreach ($matches[0] as [$matchText, $matchPos]) {
            $cursor = (int) ($matchPos + strlen((string) $matchText));
            while ($cursor < $len && ctype_space($css[$cursor])) {
                $cursor++;
            }
            if ($cursor >= $len || $css[$cursor] !== '{') {
                continue;
            }
            $start = $cursor + 1;
            $depth = 1;
            $i     = $start;
            while ($i < $len && $depth > 0) {
                $ch = $css[$i];
                if ($ch === '{') {
                    $depth++;
                } elseif ($ch === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $blocks[] = substr($css, $start, $i - $start);
                        break;
                    }
                }
                $i++;
            }
        }
        return $blocks;
    }

    /**
     * The desktop `<table>` must still carry
     * `class="table table--clickable-rows"` + `data-testid="logs-table"`.
     * The sibling #1443 regression guard
     * (`TableRowCursorPointerRegressionTest::testSystemLogTableOptsIntoClickableRowsModifier`)
     * pins the same shape, but having the assertion here too means
     * a PR that adds the mobile cards by deleting the desktop table
     * fails the per-file contract before the cross-file one fires.
     */
    public function testDesktopTableSurvives(): void
    {
        $this->assertMatchesRegularExpression(
            '/<table\b[^>]*\bclass="[^"]*\btable\b[^"]*\btable--clickable-rows\b[^"]*"[^>]*\bdata-testid="logs-table"/',
            $this->template,
            "`page_admin_settings_logs.tpl` must STILL ship the desktop "
            . "`<table class=\"table table--clickable-rows\" data-testid=\"logs-table\">` "
            . "alongside the new mobile `.log-cards` block. Both surfaces are "
            . "load-bearing — the table for `>=769px`, the cards for `<=768px`. "
            . "A future refactor that 'consolidates onto the mobile shape' for "
            . "everyone re-opens the desktop chrome regression on the inverse "
            . "viewport (the table's `<tbody>` row structure is the canonical "
            . "data table chrome for the panel; replacing it with stacked cards "
            . "on desktop would break the established visual vocabulary)."
        );
    }

    /**
     * The summary-row `onkeydown` handler must NOT carry an inline
     * `{event.preventDefault();…}` body. Under Smarty's default
     * `auto_literal=true`, the `{event.preventDefault();…}` substring
     * has NO whitespace after the opening brace, so the compiler parses
     * it as a Smarty tag (`event.preventDefault()`) and fails with
     * `Unexpected '.', expected one of: '}'`. The bug compiled cleanly
     * for months because Smarty's compile-dir was caching an earlier
     * artifact (pre-#1451), but the moment a fresh compile-dir touches
     * the template the whole page handler 500s. The two affected user
     * surfaces are (a) `?p=admin&c=settings&section=logs` (the page
     * this template renders) and (b) any E2E spec that exercises it.
     *
     * The fix moves the dispatcher into the existing
     * `<script>{literal}…{/literal}</script>` block as
     * `window.handleLogRowKey(event, row)` and the inline attribute
     * reduces to `onkeydown="handleLogRowKey(event, this);"` —
     * no braces inside the value, no Smarty hazard.
     *
     * The contract: the template must NOT contain the
     * `{event.preventDefault` token anywhere (case-sensitive — the
     * Smarty parser is). A future "let me put it back inline, it
     * worked before" PR re-opens the bug class and fails this gate.
     * The cache-fragility shape is shared across every inline
     * handler that opens a `{` followed immediately by a JS
     * identifier; if a future template ships the same trap, lift the
     * paired test alongside it.
     */
    public function testSummaryRowKeyDispatcherIsNotInlineSmartyHazard(): void
    {
        // Strip Smarty `{* … *}` and JS `/* … */` block comments before
        // scanning so the explanatory blocks (one above the `<tr>` and
        // one above the JS `handleLogRowKey` helper) — both of which
        // intentionally quote the forbidden inline form for posterity —
        // don't false-fire the gate. Smarty strips `{* *}` at compile
        // time; the JS comments don't reach the browser DOM either.
        // Scanning the post-strip body matches what the compiler /
        // runtime actually sees.
        $stripped = preg_replace('/\{\*.*?\*\}/s', '', $this->template) ?? $this->template;
        $stripped = preg_replace('#/\*.*?\*/#s', '', $stripped) ?? $stripped;

        // The bug class is broader than `onkeydown=` + `{event.`. Any
        // `on\w+="…{<JS-identifier>(…"` shape carries the same trap —
        // `{foo.bar()` is parsed as a Smarty tag the moment Smarty
        // touches a fresh compile-dir. The narrower regex would pass on
        // a copy-paste of the inline body into `onclick="if(x){foo.bar()}"`
        // (a likely slip when wiring a new affordance next to the
        // keyboard handler), AND on `onkeyup` / `onfocus` / `onsubmit` /
        // `onmouseover` siblings. Broaden to the full attribute family
        // so a sibling regression on any inline JS-event attribute
        // surfaces here, not in a CI run on a cold compile-dir.
        $this->assertDoesNotMatchRegularExpression(
            '/\bon\w+\s*=\s*"[^"]*\{[a-zA-Z_$]/',
            $stripped,
            "`page_admin_settings_logs.tpl` must NOT carry an inline "
            . "`on\w+=\"…{<JS-identifier>(…}…\"` body on ANY event "
            . "attribute (`onclick`, `onkeydown`, `onkeyup`, `onfocus`, "
            . "`onsubmit`, …). Smarty's default `auto_literal=true` "
            . "parses the `{event.preventDefault();…}` / `{foo.bar();…}` "
            . "substring as a tag (no whitespace after `{`, so the JS "
            . "identifier looks like a function call to the lexer) and "
            . "the template fails fresh-compile with `Unexpected '.', "
            . "expected one of: '}'`. The bug compiled cleanly for months "
            . "because Smarty's compile-dir was caching an earlier "
            . "artifact (pre-#1451); a fresh `templates_c` 500s the page. "
            . "Lift the dispatcher into the page-tail "
            . "`<script>{literal}…{/literal}</script>` block "
            . "(`window.<handler>`) and call it from the attribute by "
            . "name — no braces in the attribute body, no Smarty hazard. "
            . "If a future legitimate JS object-literal genuinely needs "
            . "to ship inline (vanishingly rare in this codebase), wrap "
            . "the entire attribute value in `{literal}…{/literal}`."
        );

        $this->assertStringContainsString(
            'onkeydown="handleLogRowKey(event, this);"',
            $this->template,
            "The summary row's `onkeydown` attribute must dispatch via the "
            . "page-tail `window.handleLogRowKey` helper "
            . "(`onkeydown=\"handleLogRowKey(event, this);\"`). This is the "
            . "load-bearing replacement for the pre-existing inline "
            . "`if(event.key==='Enter'…){event.preventDefault();…}` body; without "
            . "it the keyboard contract (Enter / Space toggles the row) is broken "
            . "AND the next E2E run on a fresh Smarty compile-dir 500s the page."
        );

        $this->assertStringContainsString(
            'window.handleLogRowKey = function (event, row)',
            $this->template,
            "The page-tail `<script>{literal}…{/literal}</script>` block must "
            . "define `window.handleLogRowKey` so the `onkeydown` attribute's "
            . "named-handler dispatch resolves at runtime. The block sits below "
            . "`window.toggleLogRow` and `window.clearLogs`; keep the helper next "
            . "to its peers and the `<script>{literal}` wrapping unchanged."
        );
    }
}
