<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1446: pin the `web/scripts/server-tile-hydrate.js`
 * online-counter lookup contract.
 *
 * Background
 * ----------
 *
 * The public Server List header copy reads "{N} configured · {M}
 * online" where `{M}` is the live count of tiles whose A2S probe
 * resolved to `online`. The count is painted by `updateOnlineCount`
 * in `web/scripts/server-tile-hydrate.js` which calls `summaryNode`
 * to resolve the `[data-testid="servers-summary"]` element it writes
 * into.
 *
 * Pre-fix `summaryNode` only queried DESCENDANTS of the hydration
 * container (the `.servers-grid` `<div data-server-hydrate="auto">`).
 * On `page_servers.tpl` the summary lives inside the page-level
 * `<header>` — a SIBLING of the grid, not a descendant — so
 * `container.querySelector('[data-testid="servers-summary"]')`
 * returned `null` and `updateOnlineCount` early-returned every time
 * a tile flipped to `online`. The counter stayed frozen at the
 * server-rendered `0` regardless of how many servers actually
 * answered the A2S probe (#1446 — "Server page always reports zero
 * online servers", reported on V2 rc5).
 *
 * Fix (post-#1446)
 * ----------------
 *
 * `summaryNode` keeps the descendant-first lookup (forward-looking
 * allowance for a future surface that wraps the summary inside the
 * hydration container) and falls back to a document-wide
 * `document.querySelector('[data-testid="servers-summary"]')` when
 * the descendant lookup misses. The public Server List is the only
 * consumer that ships the summary node and it ships it as a
 * sibling, so the fallback is what actually fires today. Surfaces
 * that don't render the summary at all (admin Server Management,
 * dashboard Servers widget, Add Admin per-server grid, admin Server
 * Groups card stack) get `null` from both lookups and
 * `updateOnlineCount` no-ops harmlessly.
 *
 * Static gate
 * -----------
 *
 * This test reads `web/scripts/server-tile-hydrate.js` as a string
 * and asserts:
 *
 *   - The literal `document.querySelector('[data-testid="servers-summary"]')`
 *     appears (the document-wide fallback that makes the counter
 *     work on the actual page layout).
 *   - The pre-fix shape — a `summaryNode` body that branches on
 *     `instanceof Element` / `instanceof DocumentFragment` and
 *     ONLY queries the container (no document-wide fallback after
 *     the conditional, no unconditional document.querySelector) —
 *     stays gone. We approximate this by asserting the post-fix
 *     `if (!found)` guard is present immediately before the
 *     fallback lookup, which is the structural shape that
 *     distinguishes "descendant + document fallback" from
 *     "descendant only".
 *   - The painted text-content target `[data-online-num]` is still
 *     looked up via the summary node, so a future refactor that
 *     repoints the painter at a different testid has to update
 *     this test deliberately (the template uses `data-online-num`
 *     too — they have to agree).
 *
 * This catches a future "simplify summaryNode" refactor that drops
 * the fallback and silently re-opens the bug — the kind of edit
 * that looks safe in isolation because the public Server List is
 * the only consumer that exercises the counter.
 *
 * Pure file scanning — extends `PHPUnit\Framework\TestCase` directly
 * (no DB / session / Smarty bring-up). Sister of
 * `ApiJsEndpointResolutionTest` and `DeadJsCallSitesTest`.
 */
final class ServerTileHydrateOnlineCountTest extends TestCase
{
    private static function helperPath(): string
    {
        return ROOT . 'scripts/server-tile-hydrate.js';
    }

    private static function helperContents(): string
    {
        $contents = @file_get_contents(self::helperPath());
        if ($contents === false) {
            self::fail(
                'Could not read ' . self::helperPath()
                . ' — file moved or test bootstrap broke? If you renamed it,'
                . ' update both this test AND every `<script src="…">` reference'
                . ' across the four consumer templates (see the per-surface'
                . ' notes at the top of the helper file).',
            );
        }
        return $contents;
    }

    public function testHelperFileExists(): void
    {
        $this->assertFileExists(
            self::helperPath(),
            'web/scripts/server-tile-hydrate.js must live at scripts/server-tile-hydrate.js so the public Server List, the admin Server Management list, the dashboard Servers widget, the Add Admin per-server grid, and the admin Server Groups card stack can all load it via their `<script src="…">` tags. If you moved it, update this test AND the per-template references.',
        );
    }

    public function testHelperRendersTheSummaryNodeViaDocumentWideFallback(): void
    {
        $contents = self::helperContents();
        $this->assertStringContainsString(
            "document.querySelector('[data-testid=\"servers-summary\"]')",
            $contents,
            'server-tile-hydrate.js must perform a document-wide '
            . 'fallback lookup for the `[data-testid="servers-summary"]` '
            . 'node (#1446). On `page_servers.tpl` the summary lives in '
            . 'the page-level `<header>` — a sibling of the hydration '
            . 'container, NOT a descendant — so a container-only '
            . '`querySelector` returns null and the online counter '
            . 'never updates. The reporter on V2 rc5 saw '
            . '"5 configured · 0 online" with 5 healthy servers. If '
            . 'you refactored `summaryNode`, keep the document-wide '
            . 'fallback OR move the summary node inside the hydration '
            . 'container in `page_servers.tpl` (and update this '
            . 'assertion to reflect the new contract).',
        );
    }

    public function testHelperGuardsTheDocumentWideLookupBehindTheDescendantMiss(): void
    {
        // The post-fix shape is:
        //
        //     if (!found) {
        //         found = document.querySelector('[data-testid="servers-summary"]');
        //     }
        //
        // Asserting the literal `if (!found)` immediately before the
        // document-wide query catches a refactor that drops the
        // descendant-first branch entirely (which would work but
        // changes the contract documented in the helper's docblock
        // — the descendant-first lookup is the forward-looking
        // allowance for surfaces that wrap the summary inside the
        // hydration container, and someone reading the test should
        // see both halves still present).
        $contents = self::helperContents();
        $this->assertMatchesRegularExpression(
            "/if \\(!found\\) \\{\\s+found = document\\.querySelector\\('\\[data-testid=\"servers-summary\"\\]'\\);/",
            $contents,
            'server-tile-hydrate.js `summaryNode` must keep the '
            . '`if (!found)` document-wide fallback guard (#1446). The '
            . 'descendant-first lookup is the forward-looking allowance '
            . 'for surfaces that wrap the summary inside the hydration '
            . 'container; the `if (!found)` fallback is what makes the '
            . 'counter work on the current `page_servers.tpl` layout '
            . 'where the summary is a sibling of the grid. If you '
            . 'changed the structure, keep BOTH halves or update this '
            . 'test deliberately.',
        );
    }

    public function testHelperPaintsIntoTheDataOnlineNumTextNode(): void
    {
        $contents = self::helperContents();
        $this->assertStringContainsString(
            "summary.querySelector('[data-online-num]')",
            $contents,
            'server-tile-hydrate.js `updateOnlineCount` must look up '
            . 'the painted text-content target via the '
            . '`[data-online-num]` attribute (#1446). The matching '
            . 'template hook lives at `web/themes/default/page_servers.tpl`'
            . ' inside the `[data-testid="servers-summary"]` paragraph '
            . '(`<span data-online-num>0</span>`). If you renamed '
            . 'either side, update both AND this assertion in the same '
            . 'PR — drift between the two silently breaks the '
            . 'counter painting again.',
        );
    }
}
