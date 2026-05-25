<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1443: the desktop `.table tbody tr` rule in
 * `web/themes/default/css/theme.css` carried `cursor: pointer` from
 * the initial v2.0 vendor handoff (#1123 A1, commit 7c8bb9d6) — an
 * artifact of an early design where the whole table row was meant
 * to be clickable. The final v2.0 chrome wires interaction to
 * specific *child* elements instead (the player-name `<a>` carries
 * `[data-drawer-bid]` / `[data-drawer-cid]` / `[data-drawer-href]`
 * for the drawer; row-actions buttons carry `data-action="…"`; the
 * per-row comments toggle via its native `<summary>`). The `<tr>`
 * itself has no click handler.
 *
 * Painting `cursor: pointer` on the row therefore lied to every
 * user hovering over a non-anchor cell (steam id, IP, reason,
 * status, server, admin, length, banned timestamp) — the hand
 * cursor advertised clickability, the click did nothing, and the
 * panel read as broken. The reporter's exact symptom on #1443.
 *
 * The fix drops the `cursor: pointer` declaration from the
 * `.table tbody tr` selector. Native cursors on the inner
 * `<a>` / `<button>` / `<summary>` elements already paint
 * correctly (browsers default `cursor: pointer` for links and
 * carry it for interactive widgets), so removing the row-wide
 * declaration restores honest affordance: pointer only where
 * clicking does something.
 *
 * The hover-background rule survives — it's a scanning aid for
 * tracking which row the mouse is on, not a clickability claim.
 * Every modern data table (Linear, Notion, GitHub, Vercel)
 * leans on the same row-hover-background-without-cursor-pointer
 * convention.
 *
 * This suite is the static gate against re-introducing the rule.
 * A copy-paste from a future tooltip / popover demo, a search-
 * and-replace mishap, or a well-meaning "rows feel clickable, let
 * me make them clickable" PR would all silently re-open the bug.
 */
final class TableRowCursorPointerRegressionTest extends TestCase
{
    private string $css;

    protected function setUp(): void
    {
        parent::setUp();

        $cssPath = ROOT . 'themes/default/css/theme.css';
        $css     = file_get_contents($cssPath);
        if ($css === false) {
            self::fail("setUp could not read theme.css at {$cssPath}");
        }
        $this->css = $css;
    }

    /**
     * The load-bearing assertion: the `.table tbody tr` selector
     * must NOT carry `cursor: pointer`. Match the whole rule body
     * (selector through closing brace) so a future refactor that
     * splits the rule onto multiple lines doesn't accidentally
     * sneak `cursor: pointer` back in.
     */
    public function testTableRowSelectorHasNoCursorPointer(): void
    {
        if (preg_match('/\.table\s+tbody\s+tr\s*\{[^}]*\}/', $this->css, $m) !== 1) {
            self::fail(
                'Could not locate the `.table tbody tr { … }` rule in theme.css. '
                . 'If the selector was renamed, update this regression guard in the '
                . 'same PR — #1443 is about row-wide cursor pointer being misleading, '
                . 'not about the selector shape itself.'
            );
        }
        $ruleBody = $m[0];

        $this->assertDoesNotMatchRegularExpression(
            '/cursor\s*:\s*pointer/',
            $ruleBody,
            "The `.table tbody tr { … }` rule must NOT declare `cursor: pointer`. "
            . "The `<tr>` element is not a click target on any list page (banlist, "
            . "commslist, admin admins, mods, groups, overrides, servers, logs, "
            . "kickit, blockit, bans groups, admin edit group, admin edit admins perms). "
            . "Painting pointer on the row falsely advertises every pixel of the row "
            . "as clickable while clicks on non-interactive cells (steam id, IP, "
            . "reason, status, server, admin, length, banned timestamp) actually "
            . "do nothing — the #1443 user-reported regression. Native cursors on "
            . "the inner `<a>` / `<button>` / `<summary>` elements already paint "
            . "correctly without help. Leave them alone."
        );
    }

    /**
     * The row hover-background is intentional and stays — it's a
     * scanning aid for tracking which row the mouse is on, not a
     * clickability claim. Hover-bg-without-cursor-pointer is the
     * Linear / Notion / GitHub convention for data tables. Drop
     * this affordance by accident and rows become harder to
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
     * Defense-in-depth: assert no OTHER selector in theme.css
     * silently re-applies `cursor: pointer` to `tbody tr` or
     * `.table` rows. A future utility class like
     * `.clickable-rows tbody tr { cursor: pointer; }` would be
     * the right shape if a list page genuinely wired row-wide
     * click delegation, but until that's true the codebase
     * should NOT carry such a selector. This test fails closed
     * — if you genuinely need a future row-wide click affordance,
     * gate it behind a specific class on the table (not a bare
     * `tbody tr` selector) AND update this test to allowlist the
     * new shape.
     */
    public function testNoOtherSelectorReintroducesRowWideCursorPointer(): void
    {
        // Strip CSS block comments so our scan doesn't false-positive
        // on documentation comments that legitimately mention the
        // forbidden shape (e.g. the explanatory comment above the
        // `.table tbody tr` rule itself, which says "Painting
        // `cursor: pointer` on the row falsely advertised…"). The
        // `/s` flag makes `.` match newlines so multi-line comments
        // collapse correctly; the `U` flag makes the quantifier
        // non-greedy so we don't accidentally eat across `*/...*/`
        // boundaries.
        $stripped = preg_replace('#/\*.*?\*/#s', '', $this->css);
        if (!is_string($stripped)) {
            self::fail('Failed to strip CSS comments before scanning.');
        }

        // Match any rule whose selector ends in `tbody tr`,
        // `tr.ban-row`, or `tr[data-testid="(ban|comm)-row"]` and
        // whose body contains `cursor: pointer`. Bare
        // `tr { cursor: pointer; }` would be even more catastrophic
        // so we catch that too via the `^tr` anchor (start-of-line
        // under the `/m` flag).
        //
        // Selector regex matches: one or more "selector chars" plus
        // a row-scope token, then optional further selector chars
        // (e.g. `:hover`, `.foo`), then the `{...}` body.
        $forbiddenSelectorBodies = [];
        $pattern = '/(?:^|[\n,])[^{}]*\b(?:tbody\s+tr|tr\.ban-row|tr\[data-testid="(?:ban|comm)-row"\])[^{}]*\{[^}]*\}/m';
        if (preg_match_all($pattern, $stripped, $matches) > 0) {
            foreach ($matches[0] as $rule) {
                if (preg_match('/cursor\s*:\s*pointer/', $rule) === 1) {
                    $forbiddenSelectorBodies[] = trim($rule);
                }
            }
        }

        $this->assertSame(
            [],
            $forbiddenSelectorBodies,
            "theme.css must NOT carry any selector that applies `cursor: pointer` "
            . "to a table-row scope (`tbody tr`, `tr.ban-row`, "
            . "`tr[data-testid=\"ban-row\"]`, `tr[data-testid=\"comm-row\"]`). "
            . "The `<tr>` element is not a click target — the v2.0 "
            . "chrome delegates interaction to specific child elements (drawer "
            . "trigger anchor, row-action buttons, comments details summary). "
            . "Painting row-wide pointer cursor on non-clickable rows is #1443's "
            . "regression class. Found rule(s): \n"
            . implode("\n---\n", $forbiddenSelectorBodies)
        );
    }
}
