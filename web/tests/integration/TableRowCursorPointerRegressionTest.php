<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1443: the bare `.table tbody tr` rule in
 * `web/themes/default/css/theme.css` carried `cursor: pointer` from
 * the initial v2.0 vendor handoff (#1123 A1, commit 7c8bb9d6) — an
 * artifact of an early design where the whole table row was meant
 * to be clickable. The final v2.0 chrome wires interaction to
 * specific *child* elements on every list page EXCEPT the System
 * Log table (`page_admin_settings_logs.tpl`, which legitimately
 * carries an `onclick="toggleLogRow"` on the `<tr>` to flip a
 * sibling detail row): the player-name `<a>` carries
 * `[data-drawer-bid]` / `[data-drawer-cid]` /
 * `[data-drawer-href]` for the drawer; row-actions buttons carry
 * `data-action="…"`; the per-row comments toggle via its native
 * `<summary>`. The `<tr>` itself has no click handler on those
 * pages.
 *
 * Painting `cursor: pointer` on every row therefore lied to every
 * user hovering over a non-anchor cell (steam id, IP, reason,
 * status, server, admin, length, banned timestamp) — the hand
 * cursor advertised clickability, the click did nothing, and the
 * panel read as broken. The reporter's exact symptom on #1443.
 *
 * The fix drops the `cursor: pointer` declaration from the bare
 * `.table tbody tr` selector and adds an opt-in
 * `.table.table--clickable-rows tbody tr { cursor: pointer; }`
 * modifier for the one in-tree surface that needs it. The System
 * Log table opts in by setting `class="table table--clickable-rows"`
 * on the `<table>`. Native cursors on inner `<a>` / `<button>` /
 * `<summary>` elements already paint correctly on every other
 * surface (browsers default `cursor: pointer` for links and
 * interactive widgets).
 *
 * The hover-background rule survives — it's a scanning aid for
 * tracking which row the mouse is on, not a clickability claim.
 * Every modern data table (Linear, Notion, GitHub, Vercel)
 * leans on the same hover-bg-without-cursor-pointer convention
 * for non-clickable rows.
 *
 * This suite is the static gate against re-introducing the rule.
 * A copy-paste from a future tooltip / popover demo, a search-
 * and-replace mishap, or a well-meaning "rows feel clickable, let
 * me make them clickable" PR would all silently re-open the bug.
 *
 * The contracts pinned in five tests:
 *
 *   1. The bare `.table tbody tr` rule must NOT declare
 *      `cursor: pointer`.
 *   2. The hover-background rule must survive (scanning aid).
 *   3. The opt-in `.table.table--clickable-rows tbody tr` rule
 *      must exist and DOES declare `cursor: pointer` (so any
 *      future row-wide click surface has a working idiom to
 *      reach for).
 *   4. No OTHER selector silently re-applies `cursor: pointer`
 *      to a table-row scope (the "fail closed" arm).
 *   5. The System Log template `page_admin_settings_logs.tpl`
 *      sets the opt-in class on its table (so the regression
 *      that motivated the opt-in pattern stays caught).
 */
final class TableRowCursorPointerRegressionTest extends TestCase
{
    private string $css;
    private string $logsTemplate;

    protected function setUp(): void
    {
        parent::setUp();

        $cssPath = ROOT . 'themes/default/css/theme.css';
        $css     = file_get_contents($cssPath);
        if ($css === false) {
            self::fail("setUp could not read theme.css at {$cssPath}");
        }
        $this->css = $css;

        $logsPath = ROOT . 'themes/default/page_admin_settings_logs.tpl';
        $logs     = file_get_contents($logsPath);
        if ($logs === false) {
            self::fail("setUp could not read system-log template at {$logsPath}");
        }
        $this->logsTemplate = $logs;
    }

    /**
     * The load-bearing assertion: the bare `.table tbody tr`
     * selector must NOT carry `cursor: pointer`. Match the whole
     * rule body (selector through closing brace) so a future
     * refactor that splits the rule onto multiple lines doesn't
     * accidentally sneak `cursor: pointer` back in. The selector
     * pattern carries `(?![^{]*table--clickable-rows)` so we don't
     * accidentally match the sibling opt-in rule.
     */
    public function testBareTableRowSelectorHasNoCursorPointer(): void
    {
        // Locate the bare `.table tbody tr { ... }` rule (NOT the
        // sibling `.table.table--clickable-rows tbody tr { ... }`
        // opt-in rule). We do this by finding a rule whose selector
        // line STARTS with `.table tbody tr` and has no modifier
        // class before `tbody`.
        if (preg_match('/(?:^|[\n;\}])\s*\.table\s+tbody\s+tr\s*\{[^}]*\}/', $this->css, $m) !== 1) {
            self::fail(
                'Could not locate the bare `.table tbody tr { … }` rule in theme.css. '
                . 'If the selector was renamed, update this regression guard in the '
                . 'same PR — #1443 is about row-wide cursor pointer being misleading, '
                . 'not about the selector shape itself.'
            );
        }
        $ruleBody = $m[0];

        $this->assertDoesNotMatchRegularExpression(
            '/cursor\s*:\s*pointer/',
            $ruleBody,
            "The bare `.table tbody tr { … }` rule must NOT declare `cursor: pointer`. "
            . "The `<tr>` element is not a click target on banlist, commslist, admin "
            . "admins / mods / groups / overrides / servers, kickit, blockit, "
            . "bans-groups, admin-edit-group, or admin-edit-admins-perms. Painting "
            . "pointer on the row falsely advertises every pixel as clickable while "
            . "clicks on non-interactive cells (steam id, IP, reason, status, server, "
            . "admin, length, banned timestamp) actually do nothing — the #1443 "
            . "user-reported regression. Native cursors on the inner `<a>` / "
            . "`<button>` / `<summary>` elements already paint correctly without "
            . "help. For genuinely row-clickable surfaces (currently only the System "
            . "Log table), set `class=\"table table--clickable-rows\"` on the "
            . "`<table>` — see `testClickableRowsModifierExists()` for the opt-in "
            . "selector contract."
        );
    }

    /**
     * The row hover-background is intentional and stays — it's a
     * scanning aid for tracking which row the mouse is on, not a
     * clickability claim. Hover-bg-without-cursor-pointer is the
     * Linear / Notion / GitHub / Vercel convention for data tables.
     * Drop this affordance by accident and rows become harder to
     * visually track.
     */
    public function testTableRowHoverBackgroundSurvives(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.table\s+tbody\s+tr:hover\s*\{[^}]*background\s*:\s*var\(--bg-muted\)/',
            $this->css,
            "The `.table tbody tr:hover { background: var(--bg-muted); }` rule must "
            . "survive — it's a scanning aid that helps users track which row they're "
            . "hovering over. The hover background is NOT a clickability claim (the "
            . "Linear / Notion / GitHub convention is hover-bg-without-pointer-cursor); "
            . "removing it would make data-table rows visually harder to track without "
            . "fixing the #1443 cursor problem the way dropping `cursor: pointer` did."
        );
    }

    /**
     * The opt-in `.table.table--clickable-rows tbody tr` rule must
     * exist AND carry `cursor: pointer`. This is the documented
     * escape hatch for tables where the `<tr>` IS a click target
     * (currently only the System Log table). Without this idiom,
     * future authors would either re-introduce the bare rule (the
     * #1443 regression) or invent another opt-in shape (theme
     * fragmentation).
     */
    public function testClickableRowsModifierExists(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.table\.table--clickable-rows\s+tbody\s+tr\s*\{[^}]*cursor\s*:\s*pointer/',
            $this->css,
            "The `.table.table--clickable-rows tbody tr { cursor: pointer; }` opt-in "
            . "rule must exist in theme.css — it's the documented escape hatch for "
            . "tables where the `<tr>` IS a click target (currently only the System "
            . "Log table). Without this idiom, future authors would either re-"
            . "introduce the bare `.table tbody tr { cursor: pointer; }` rule (the "
            . "#1443 regression class) or invent another opt-in shape (theme "
            . "fragmentation). The modifier must be applied to the `<table>` element "
            . "(not the individual `<tr>`s) so it composes cleanly with future "
            . "table-scoped variants."
        );
    }

    /**
     * Defense-in-depth: assert no OTHER selector in theme.css
     * silently re-applies `cursor: pointer` to a table-row scope.
     * This is the "fail closed" arm — any new selector that
     * targets `tr` (in any shape) and declares `cursor: pointer`
     * fails the test, and the contract is "if you genuinely need
     * a new row-wide click affordance, gate it behind
     * `.table--clickable-rows` AND wire a real `<tr>` click
     * handler AND update this test's allowlist". The bare `tr`
     * selector and every plausible variant (child combinator,
     * tbody-less, attribute-selector, ARIA role) all get caught
     * by this gate.
     */
    public function testNoOtherSelectorReintroducesRowWideCursorPointer(): void
    {
        // Strip CSS block comments so our scan doesn't false-positive
        // on documentation comments that legitimately mention the
        // forbidden shape (e.g. the explanatory comment above the
        // `.table tbody tr` rule itself, which says "Painting
        // `cursor: pointer` on the row falsely advertised…"). The
        // `/s` flag makes `.` match newlines so multi-line comments
        // collapse correctly. `.*?` is non-greedy so we don't
        // accidentally eat across `*/...*/` boundaries.
        $stripped = preg_replace('#/\*.*?\*/#s', '', $this->css);
        if (!is_string($stripped)) {
            self::fail('Failed to strip CSS comments before scanning.');
        }

        // Walk every rule body and extract the `(selector) { body }`
        // pair, then test:
        //   1. Does the body declare `cursor: pointer`?
        //   2. Does the selector touch a table-row scope?
        //   3. Is the selector the documented opt-in?
        // We use a depth-zero CSS parser since theme.css is flat
        // (no @-rules nesting selectors). The split is by `}` after
        // a top-level `{`.
        $forbiddenSelectorBodies = [];

        // Allowlisted selectors — exact substring match.
        $allowlist = [
            '.table.table--clickable-rows tbody tr',
        ];

        // The rule-extraction loop: find every `{ ... }` body and
        // pair it with the selector portion that precedes it.
        $offset = 0;
        while (preg_match('/(?<sel>[^{}]+)\{(?<body>[^{}]*)\}/s', $stripped, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $sel    = trim($m['sel'][0]);
            $body   = $m['body'][0];
            $next   = $m[0][1] + strlen($m[0][0]);
            $offset = $next;

            // Skip rules whose body doesn't declare cursor: pointer.
            if (preg_match('/cursor\s*:\s*pointer/', $body) !== 1) {
                continue;
            }

            // Walk every selector in the comma-separated list and
            // flag any that touches a `tr` scope.
            foreach (preg_split('/\s*,\s*/', $sel) as $oneSel) {
                $oneSel = trim($oneSel);
                if ($oneSel === '') {
                    continue;
                }

                // Allowlisted?
                $isAllowlisted = false;
                foreach ($allowlist as $allowed) {
                    if (str_contains($oneSel, $allowed)) {
                        $isAllowlisted = true;
                        break;
                    }
                }
                if ($isAllowlisted) {
                    continue;
                }

                // Touches a `tr` scope? Cover every plausible shape:
                //   - bare `tr`                              (catastrophic)
                //   - any descendant or child combinator ending in `tr`
                //     (`tbody tr`, `tbody > tr`, `.table tr`)
                //   - `tr` with class / id / attribute / pseudo
                //     (`tr.x`, `tr#x`, `tr[…]`, `tr:hover`)
                //   - `[role="row"]` (ARIA equivalent)
                $touchesRowScope = (bool) preg_match(
                    '/(?:^|[\s>+~])tr(?:[\s.:#\[]|$)|\[role\s*=\s*["\']?row["\']?\]/',
                    $oneSel,
                );

                if ($touchesRowScope) {
                    $forbiddenSelectorBodies[] = $oneSel . ' { ' . trim($body) . ' }';
                }
            }
        }

        $this->assertSame(
            [],
            $forbiddenSelectorBodies,
            "theme.css must NOT carry any selector that applies `cursor: pointer` "
            . "to a table-row scope (any descendant of / child of / direct match on "
            . "`tr`, including ARIA-equivalent `[role=\"row\"]`). The `<tr>` element "
            . "is not a click target on any list page outside the System Log — the "
            . "v2.0 chrome delegates interaction to specific child elements (drawer "
            . "trigger anchor, row-action buttons, comments details summary). "
            . "Painting row-wide pointer cursor on non-clickable rows is #1443's "
            . "regression class. If you're adding a new row-wide click affordance, "
            . "gate it behind the existing `.table.table--clickable-rows` opt-in "
            . "(currently the only allowlisted shape — see the `\$allowlist` array "
            . "above) AND wire a real `<tr>` click handler AND update this test's "
            . "allowlist to add the new opt-in variant. Found offending rule(s): \n"
            . implode("\n---\n", $forbiddenSelectorBodies)
        );
    }

    /**
     * The System Log template must apply the
     * `table--clickable-rows` modifier to its `<table>` — without
     * the opt-in, the `<tr onclick="toggleLogRow">` rows lose
     * their cursor affordance (the row stays clickable but reads
     * as inert, the exact "click works but cursor lies" inversion
     * of #1443 the opt-in pattern exists to prevent). The
     * template's `<p>Click a row to expand.</p>` instruction makes
     * the affordance gap especially user-visible.
     */
    public function testSystemLogTableOptsIntoClickableRowsModifier(): void
    {
        $this->assertMatchesRegularExpression(
            '/<table\b[^>]*\bclass="[^"]*\btable--clickable-rows\b[^"]*"[^>]*\bdata-testid="logs-table"/',
            $this->logsTemplate,
            "`page_admin_settings_logs.tpl` must set "
            . "`class=\"table table--clickable-rows\"` on its `<table data-testid=\"logs-table\">` "
            . "so the legitimately row-wide-clickable rows (each carries "
            . "`onclick=\"toggleLogRow(this)\"`) keep their cursor affordance after "
            . "#1443 stripped the bare-rule cursor on every other table. The opt-in "
            . "is the documented contract — without it, the System Log row stays "
            . "clickable but reads as inert (cursor stays default arrow, even though "
            . "the template's `<p>Click a row to expand.</p>` instruction tells the "
            . "user to click). See the `.table.table--clickable-rows tbody tr` rule "
            . "in `theme.css`."
        );
    }

    /**
     * The System Log row must carry `role="button"` + `tabindex="0"`
     * + `aria-expanded` + a matching `onkeydown` handler so the
     * disclosure works for keyboard-only and AT users. Pre-#1443
     * the row was a bare `<tr onclick="toggleLogRow">` — no role,
     * no tabindex, no key handling — so the only way to expand a
     * log row was a mouse click. Fixing #1443 (cursor-on-clickable
     * surface) without paying the a11y cost on the same surface
     * would be the wrong shape.
     */
    public function testSystemLogRowIsKeyboardReachable(): void
    {
        $this->assertMatchesRegularExpression(
            '/<tr\b[^>]*\bdata-testid="log-row"[^>]*\brole="button"[^>]*\btabindex="0"[^>]*\baria-expanded="false"[^>]*\baria-controls="log-detail-\{\$log\.lid\}"/',
            $this->logsTemplate,
            "`page_admin_settings_logs.tpl`'s log-row `<tr>` must carry "
            . "`role=\"button\"` + `tabindex=\"0\"` + `aria-expanded=\"false\"` + "
            . "`aria-controls=\"log-detail-{\$log.lid}\"` so the row is "
            . "keyboard-reachable and announced as an expandable disclosure to "
            . "screen-reader users. The matching `<tr data-detail-for=\"…\">` "
            . "row must carry the matching `id=\"log-detail-…\"` for "
            . "`aria-controls` to resolve."
        );

        $this->assertStringContainsString(
            'onkeydown="if(event.key===\'Enter\'||event.key===\' \'){event.preventDefault();toggleLogRow(this);}"',
            $this->logsTemplate,
            "`page_admin_settings_logs.tpl`'s log-row `<tr>` must carry an "
            . "`onkeydown` handler that dispatches Enter / Space to "
            . "`toggleLogRow(this)` — otherwise the `role=\"button\"` + "
            . "`tabindex=\"0\"` chrome paints the affordance but the key press "
            . "doesn't fire the toggle. AT users would hear \"button, collapsed\" "
            . "but pressing Enter / Space would do nothing."
        );

        $this->assertMatchesRegularExpression(
            '/<tr\b[^>]*\bdata-detail-for="\{\$log\.lid\}"[^>]*\bid="log-detail-\{\$log\.lid\}"/',
            $this->logsTemplate,
            "`page_admin_settings_logs.tpl`'s detail row `<tr>` must carry "
            . "`id=\"log-detail-{\$log.lid}\"` so the summary row's "
            . "`aria-controls=\"log-detail-{\$log.lid}\"` resolves to a real "
            . "element. Without the `id`, screen readers can't follow the "
            . "disclosure relationship."
        );
    }
}
