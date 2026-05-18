<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1375: the dashboard's "Servers" widget rendered
 * `IP:port` as the primary label and `sid N` as the subtitle. The
 * `sid` is an internal `:prefix_servers` PK — meaningful to the
 * panel, meaningless at-a-glance to the operator the dashboard is
 * built for. The issue reporter described it as "not meaningful";
 * the surrounding cards on the dashboard (Latest bans, Latest comm
 * blocks, Latest blocked attempts) all key off player names that
 * read as human content, so the Servers card was visibly the odd
 * one out.
 *
 * The fix mirrors the public servers list's "hostname (primary) +
 * IP:port (mono subtitle)" convention by wiring the same shared
 * hydration helper (`web/scripts/server-tile-hydrate.js`) into the
 * dashboard widget. The helper auto-runs on every
 * `[data-server-hydrate="auto"]` container and patches a live
 * hostname into the row's `[data-testid="server-host"]` slot via
 * the same `Actions.ServersHostPlayers` JSON action the public list
 * fires. The IP:port stands in for the hostname slot until the
 * probe lands (and re-paints from `data-fallback` on probe failure)
 * so the no-JS path stays informative.
 *
 * The dashboard widget feature-uses ONLY the hostname cell. Every
 * other testid hook the helper recognises (status pill, map cell,
 * players cell, players bar, map preview, refresh / toggle buttons,
 * players panel) is intentionally omitted — the chrome is a compact
 * one-line link per server, not a full server card. The helper's
 * feature-detection branches no-op for the missing testids, so the
 * widget hydrates the hostname and ignores everything else.
 *
 * Why this is a template-string-shape test rather than a stub-Smarty
 * render harness (cf. `AdminServersListHydrationTest`)
 * ----------------------------------------------------------------
 * The regression we're guarding against is "the
 * `data-server-hydrate` attribute got dropped" / "the
 * `data-testid="server-host"` slot got renamed" / "the script
 * include got moved out of the dashboard template" / "the `sid N`
 * subtitle came back". Each of those is a structural change to the
 * template's source text — `file_get_contents` + assert pins them
 * directly without booting Smarty, seeding `:prefix_servers` rows,
 * or instantiating the View DTO. The E2E suite covers the runtime
 * observable (the hostname actually paints in after the JSON action
 * lands); this PHPUnit guard is the contract gate, sub-millisecond
 * and deterministic.
 *
 * Pattern mirrors `ServerMapImageRenderTest` — same setUp, same
 * source-shape assertions.
 */
final class DashboardServersWidgetHydrationTest extends TestCase
{
    private string $template;

    protected function setUp(): void
    {
        parent::setUp();

        $tplPath = ROOT . 'themes/default/page_dashboard.tpl';
        $tpl = file_get_contents($tplPath);
        if ($tpl === false) {
            self::fail("setUp could not read {$tplPath}");
        }
        $this->template = $tpl;
    }

    /**
     * The shared hydration helper script must be referenced from the
     * dashboard template. Without this include the per-row testids
     * are inert markup and the hostname never paints — the widget
     * silently degrades to the no-JS shape forever.
     */
    public function testTemplateIncludesHydrationHelperScript(): void
    {
        $this->assertStringContainsString(
            'src="./scripts/server-tile-hydrate.js"',
            $this->template,
            'page_dashboard.tpl must <script src> web/scripts/server-tile-hydrate.js so the '
            . 'Servers widget\'s `[data-testid="server-host"]` slots hydrate from the live A2S '
            . 'probe (#1375). Without the include every row stays at the IP:port fallback even '
            . 'on installs where the JSON action would resolve cleanly.',
        );
    }

    /**
     * The row-list wrapper opts into auto-hydration. The helper
     * walks every `[data-server-hydrate="auto"]` container in the
     * document at first paint; without this attribute the dashboard
     * rows are skipped entirely.
     *
     * `data-trunchostname="40"` forwards to `api_servers_host_players`
     * as the SourceQuery truncation hint — the dashboard column is
     * cramped (shared with the Latest Bans card under the 2-up grid)
     * and the public list's `=70` would overflow the `truncate`
     * ellipsis. The number itself is a UX call but the presence of
     * the attribute is the contract: the helper falls back to 70
     * otherwise.
     */
    public function testServerListWrapperOptsIntoAutoHydration(): void
    {
        // Match against a `<div …>` opener that carries BOTH attrs.
        // `[\s\S]*?` is the non-greedy "any char including newline"
        // span; `\bdata-…\b` ensures we don't trip on a prefix match.
        $this->assertMatchesRegularExpression(
            '/<div\b[^>]*\bdata-server-hydrate="auto"[^>]*\bdata-trunchostname="40"/',
            $this->template,
            'The dashboard Servers widget\'s row-list wrapper must carry '
            . '`data-server-hydrate="auto" data-trunchostname="40"` so the shared hydration '
            . 'helper auto-runs on first paint and forwards the cramped-column truncation hint '
            . 'to the JSON action (#1375). Pre-fix the widget had neither attribute and the '
            . 'rows stayed at the IP:port placeholder forever — same regression class as '
            . 'the admin Server Management list before #1313.',
        );
    }

    /**
     * Each row carries the hostname slot the helper patches. The
     * `data-fallback` attribute is the IP:port the helper repaints
     * on probe failure (see `applyData()` in
     * `web/scripts/server-tile-hydrate.js`); without it the row goes
     * blank when the live UDP probe times out.
     *
     * The initial inner text is also IP:port (the same value
     * data-fallback carries) so the no-JS / pre-hydration paint
     * stays informative — same shape the public servers list uses.
     */
    public function testRowsCarryHostnameSlotWithFallback(): void
    {
        // The hostname `<div>` must carry BOTH testid AND
        // data-fallback="{$server.ip}:{$server.port}". The literal
        // Smarty interpolation survives in the template source.
        $this->assertMatchesRegularExpression(
            '/<div\b[^>]*\bdata-testid="server-host"[^>]*\bdata-fallback="\{\$server\.ip\}:\{\$server\.port\}"/',
            $this->template,
            'Each Servers widget row must ship a `<div data-testid="server-host" '
            . 'data-fallback="{$server.ip}:{$server.port}">` slot so the shared hydration '
            . 'helper has a target for `sb.setHTML(d.hostname)` and a fallback to repaint when '
            . 'the UDP probe fails (#1375). Pre-fix the row had no hostname slot — the primary '
            . 'label was a hardcoded `{$server.ip}:{$server.port}` `<div>` with no testid hook.',
        );
    }

    /**
     * Anti-regression: the pre-#1375 "sid N" subtitle is gone. The
     * subtitle now carries the IP:port (the same value the primary
     * label falls back to) so admins can copy/paste the address
     * without having to expand the dashboard widget. A future PR
     * that resurrects the `sid` subtitle would silently undo the
     * fix; the test pins the absence as a structural contract.
     */
    public function testSidSubtitleIsGone(): void
    {
        // Loose match: any `sid {$server.sid}` substring (with or
        // without surrounding markup) is a regression. The legacy
        // shape was `<div class="text-xs text-faint">sid {$server.sid}</div>`
        // — checking just the inner text suffices.
        $this->assertStringNotContainsString(
            'sid {$server.sid}',
            $this->template,
            'page_dashboard.tpl must NOT carry the pre-#1375 `sid {$server.sid}` subtitle. '
            . 'The Servers widget subtitle now carries the IP:port (the same value the primary '
            . 'label falls back to until the live hostname lands); `sid N` is an internal '
            . 'PK that means nothing at-a-glance to operators (#1375).',
        );
    }

    /**
     * The IP:port subtitle (`<div class="text-xs text-faint
     * font-mono truncate">{$server.ip}:{$server.port}</div>`)
     * replaces the legacy `sid N` line so the row keeps a two-line
     * shape (primary + subtitle) and the admin can read the address
     * without expanding the widget. We assert the substring shape
     * rather than the exact class list so a future styling tweak
     * (e.g. a `text-muted` swap) doesn't fail the gate.
     */
    public function testIpPortSubtitleIsPresent(): void
    {
        // The subtitle's `<div>` carries `font-mono` (the IP:port
        // is monospaced for tabular alignment) AND `text-faint`
        // (lower contrast than the primary label) — both are the
        // canonical "subtitle" treatment elsewhere in the
        // dashboard. Match the chain so a partial-rename doesn't
        // sneak through.
        $this->assertMatchesRegularExpression(
            '/<div\b[^>]*\bclass="[^"]*\btext-xs\b[^"]*\btext-faint\b[^"]*\bfont-mono\b[^"]*"[^>]*>\{\$server\.ip\}:\{\$server\.port\}<\/div>/',
            $this->template,
            'The dashboard Servers widget must ship an IP:port subtitle styled '
            . '`text-xs text-faint font-mono truncate` (lower contrast monospaced subtitle) so '
            . 'operators can still see the address without hovering / clicking through to the '
            . 'servers page (#1375). The Smarty placeholders `{$server.ip}:{$server.port}` are '
            . 'matched literally — a future refactor that switches to (say) `{$server.address}` '
            . 'should update this assertion too.',
        );
    }

    /**
     * Anti-regression for the docblock at the top of the template:
     * pre-#1375 the file carried "Live server status (player counts,
     * hostname, online/offline) is the other intentional omission"
     * — a comment that was *true* during Phase B (no JSON action
     * existed) but became stale once `Actions.ServersHostPlayers`
     * shipped. The comment block was rewritten to describe the new
     * hydration wiring; this test pins the rewrite so a careless
     * "let me restore the original docblock" doesn't ship a comment
     * that contradicts the code.
     *
     * Keep the predicate loose — we assert the "intentional
     * omission" phrasing is gone AND a `#1375` reference is
     * present, NOT the exact wording of the new block. Future
     * docblock tweaks (typo fixes, prose polish) should stay
     * green; a wholesale rollback should fail.
     */
    public function testDocblockReflectsHydrationWiring(): void
    {
        $this->assertStringNotContainsString(
            'Live server status (player counts, hostname, online/offline) is the',
            $this->template,
            'The pre-#1375 docblock claimed live server status was "the other intentional '
            . 'omission" — that premise is gone now that the shared hydration helper drives '
            . 'the dashboard\'s Servers widget. The comment block was rewritten to describe '
            . 'the new wiring; restoring the legacy prose would contradict the code (#1375).',
        );

        $this->assertStringContainsString(
            '#1375',
            $this->template,
            'page_dashboard.tpl should reference #1375 in the rewritten docblock + the inline '
            . 'comments above the hydration container — anchors for a future maintainer '
            . 'trying to understand why the widget grew a hydration container.',
        );
    }
}
