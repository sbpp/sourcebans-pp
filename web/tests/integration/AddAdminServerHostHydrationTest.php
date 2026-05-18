<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1405: the Add Admin sub-route
 * (`?p=admin&c=admins&section=add-admin`) renders a per-server access
 * checkbox grid (one row per `:prefix_servers` entry) for picking the
 * server-level admin scope for the new admin. Each row showed the bare
 * `IP:port` inside a `<span id="sa{$server.sid}">…</span>` placeholder.
 *
 * Pre-v2.0 the legacy `LoadServerHost(...)` per-row script feeder
 * (defined in `web/scripts/sourcebans.js`, deleted at #1123 D1)
 * replaced those placeholders with the live hostname after an A2S
 * round-trip. Sister cleanup PR #1404 dropped the dead `<script>`
 * blob, the orphan `$server_script` View property, and the
 * `{$server_script nofilter}` echo in the template.
 *
 * This PR is the additive replacement: the per-row span now carries
 * `[data-testid="server-host"]` + `data-fallback="<ip>:<port>"` and
 * the wrapping grid div opts into the shared
 * `web/scripts/server-tile-hydrate.js` helper via
 * `data-server-hydrate="auto"` + `data-trunchostname="40"`. The
 * helper auto-runs on first paint for every container marked
 * `data-server-hydrate="auto"`, walks every `[data-testid="server-tile"]`
 * child, and fires `Actions.ServersHostPlayers` per row to patch the
 * live hostname into the `[data-testid="server-host"]` slot via
 * `sb.setHTML`. `Sbpp\Servers\SourceQueryCache` (~30s TTL per
 * `(ip, port)`) coalesces the per-row probes server-side.
 *
 * Mirrors the dashboard widget's minimal-integration shape exactly:
 * the only optional hydration testid the row ships is `server-host` —
 * every other cell hook (`server-status` / `server-map` /
 * `server-players` / `server-players-bar` / `server-map-img`) is
 * intentionally omitted, and the helper's feature-detection branches
 * no-op for the missing ones. The Add Admin form is the editor for
 * per-server access, not a player-row table; status pills / player
 * counts would only add visual noise to the checkbox grid.
 *
 * Why this is a template-string-shape test rather than a stub-Smarty
 * render harness (cf. `AdminServersListHydrationTest`)
 * ----------------------------------------------------------------
 * The regression we're guarding against is "the
 * `data-server-hydrate` attribute got dropped" / "the
 * `data-testid="server-host"` slot got renamed" / "the script
 * include got moved out of the template" / "the legacy `id="sa…"`
 * hook + LoadServerHost feeder snuck back in". Each of those is a
 * structural change to the template's source text —
 * `file_get_contents` + assert pins them directly without booting
 * Smarty, seeding `:prefix_servers` rows, or instantiating the View
 * DTO. The E2E suite covers the runtime observable (the hostname
 * actually paints in after the JSON action lands); this PHPUnit
 * guard is the contract gate, sub-millisecond and deterministic.
 *
 * Pattern mirrors `DashboardServersWidgetHydrationTest` +
 * `ServerMapImageRenderTest` — same setUp, same source-shape
 * assertions, plus the sister-contract checks from
 * `DeadJsCallSitesTest` to pin the absence of the deleted legacy
 * helpers + the View-DTO orphan property.
 */
final class AddAdminServerHostHydrationTest extends TestCase
{
    /** Raw template source — used by the positive-contract tests that
     *  pin attributes / opener shapes the template ACTUALLY emits at
     *  runtime (Smarty comments are stripped at compile time, so any
     *  attribute that survives the comment-stripping pass is also
     *  what reaches the browser).
     */
    private string $template;

    /** Comment-stripped template source — used by the
     *  anti-regression tests that pin the absence of dead legacy
     *  shapes. The template carries historical-context comment
     *  blocks that narrate what was removed (e.g. the literal
     *  `LoadServerHost('SID', ...)` shape from the deleted
     *  `sourcebans.js`); without stripping those narratives,
     *  the anti-regression regexes would match the comment text
     *  itself and false-fire. Sister to `DeadJsCallSitesTest`'s
     *  `stripSmartyComments()` helper — same regression class, same
     *  defensive shape.
     */
    private string $templateNoComments;

    protected function setUp(): void
    {
        parent::setUp();

        $tplPath = ROOT . 'themes/default/page_admin_admins_add.tpl';
        $tpl     = file_get_contents($tplPath);
        if ($tpl === false) {
            self::fail("setUp could not read {$tplPath}");
        }
        $this->template           = $tpl;
        $this->templateNoComments = self::stripSmartyComments($tpl);
    }

    /**
     * Strip Smarty `{* ... *}` block comments from the template
     * source so anti-regression substring / regex assertions don't
     * false-fire on historical-context narrative inside the
     * comment blocks. Mirror of `DeadJsCallSitesTest::stripSmartyComments`.
     */
    private static function stripSmartyComments(string $contents): string
    {
        return (string) preg_replace('/\{\*.*?\*\}/s', '', $contents);
    }

    /**
     * The shared hydration helper script must be referenced from the
     * Add Admin template. Without this include the per-row testids
     * are inert markup and the hostname never paints — the grid
     * silently degrades to the no-JS shape forever (same regression
     * class as the dashboard widget pre-#1375 and the admin Server
     * Management list pre-#1313).
     */
    public function testTemplateIncludesHydrationHelperScript(): void
    {
        $this->assertStringContainsString(
            'src="./scripts/server-tile-hydrate.js"',
            $this->template,
            'page_admin_admins_add.tpl must <script src> web/scripts/server-tile-hydrate.js so '
            . 'the per-server access grid\'s `[data-testid="server-host"]` slots hydrate from '
            . 'the live A2S probe (#1405). Without the include every row stays at the IP:port '
            . 'fallback forever — exactly the post-#1404 state this PR is the replacement for.',
        );
    }

    /**
     * The grid wrapper opts into auto-hydration. The helper walks
     * every `[data-server-hydrate="auto"]` container in the document
     * at first paint; without this attribute the Add Admin rows are
     * skipped entirely.
     *
     * `data-trunchostname="40"` matches the dashboard widget's
     * cramped-column hint — the per-row card is ~18rem wide
     * (`grid-template-columns: repeat(auto-fill, minmax(18rem, 1fr))`)
     * and a long hostname would trip `truncate`'s ellipsis
     * prematurely. The presence of the attribute is the contract;
     * the helper falls back to 70 otherwise.
     */
    public function testGridOptsIntoAutoHydration(): void
    {
        // Match a `<div …>` opener that carries BOTH attrs. The order
        // is not asserted (a future cosmetic re-ordering shouldn't
        // fail the gate), so we run two narrower regex assertions and
        // also one combined assertion for an early "both must live on
        // the same opener" failure message.
        $this->assertMatchesRegularExpression(
            '/<div\b[^>]*\bdata-server-hydrate="auto"[^>]*\bdata-trunchostname="40"/',
            $this->template,
            'The Add Admin per-server access grid wrapper must carry '
            . '`data-server-hydrate="auto" data-trunchostname="40"` on the SAME `<div>` opener '
            . 'so the shared hydration helper auto-runs on first paint and forwards the '
            . 'cramped-column truncation hint to the JSON action (#1405). The 40-char hint '
            . 'matches the dashboard widget\'s convention — same column constraint, same '
            . 'truncation budget.',
        );
    }

    /**
     * Each row carries the testability hooks the helper looks for.
     * `[data-testid="server-tile"]` is the row selector;
     * `data-id="{$server.sid}"` is the primary-key the helper
     * forwards to `Actions.ServersHostPlayers` as the `sid` param.
     *
     * Asserted as a single regex against the `<label …>` opener so a
     * future refactor that splits the row across multiple elements
     * (and leaves the testid + data-id on different children) fails
     * the gate — the helper looks for `[data-testid="server-tile"]`
     * with `data-id` as siblings on the same element.
     */
    public function testRowsCarryServerTileTestidAndDataId(): void
    {
        $this->assertMatchesRegularExpression(
            '/<label\b[^>]*\bdata-testid="server-tile"[^>]*\bdata-id="\{\$server\.sid\}"/',
            $this->template,
            'Each per-server `<label>` row in the Add Admin grid must carry '
            . '`data-testid="server-tile"` + `data-id="{$server.sid}"` on the SAME opener so '
            . 'the shared hydration helper recognises the row and can forward the sid to '
            . '`Actions.ServersHostPlayers` (#1405).',
        );
    }

    /**
     * The hostname slot lives inside the row and carries the
     * `data-fallback` attribute. The fallback is the IP:port the
     * helper repaints on probe failure (see `applyData()` in
     * `web/scripts/server-tile-hydrate.js`); without it the span
     * goes blank when the live UDP probe times out.
     *
     * The initial inner text is also IP:port (the same value
     * `data-fallback` carries) so the no-JS / pre-hydration paint
     * stays informative.
     */
    public function testRowsCarryHostnameSlotWithFallback(): void
    {
        // The hostname `<span>` must carry BOTH the testid AND
        // `data-fallback="{$server.ip}:{$server.port}"`. The literal
        // Smarty interpolation survives in the template source.
        //
        // No explicit `|escape` filter — Smarty's global
        // `setEscapeHtml(true)` (web/init.php) auto-escapes every
        // interpolation, and the sibling canonical minimal-integration
        // surface `page_dashboard.tpl` ships the same shape. Adding an
        // explicit `|escape` here would double-escape (no harm for
        // IP/port which carry no HTML-special chars, but the drift
        // hides the auto-escape contract).
        $this->assertMatchesRegularExpression(
            '/<span\b[^>]*\bdata-testid="server-host"[^>]*\bdata-fallback="\{\$server\.ip\}:\{\$server\.port\}"/',
            $this->template,
            'Each per-server row must ship a `<span data-testid="server-host" '
            . 'data-fallback="{$server.ip}:{$server.port}">` slot so the shared '
            . 'hydration helper has a target for `sb.setHTML(d.hostname)` and a fallback to '
            . 'repaint when the UDP probe fails (#1405). The bare IP:port stays as the inner '
            . 'text so the no-JS / cache-cold path renders the same address the helper would '
            . 'eventually paint via `data-fallback`. The interpolation rides Smarty\'s global '
            . 'auto-escape (no explicit `|escape` filter) to stay byte-symmetric with the '
            . 'sibling minimal-integration surface `page_dashboard.tpl`.',
        );
    }

    /**
     * Anti-regression: the legacy v1.4.11 `<span id="sa{$server.sid}">`
     * hook is gone. That id was the target of the deleted
     * `LoadServerHost(...)` per-row script feeder; reintroducing it
     * would suggest the dead feeder is back too.
     *
     * Sister-shape to `DeadJsCallSitesTest`'s
     * `testDeadTemplateSidesStayDropped` — that test pins the
     * absence of the `{$server_script nofilter}` echo, this one
     * pins the absence of the per-row id hook the echo targeted.
     */
    public function testLegacyIdSpanHookIsGone(): void
    {
        // Match against any `<span … id="sa{$server.sid}" …>` shape.
        // The legacy form was the canonical `id="sa{$server.sid}"`
        // string; a quoted-attribute literal is the unambiguous shape
        // a regression would emit.
        $this->assertDoesNotMatchRegularExpression(
            '/<span\b[^>]*\bid="sa\{\$server\.sid\}"/',
            $this->templateNoComments,
            'page_admin_admins_add.tpl must NOT carry the legacy `<span id="sa{$server.sid}">` '
            . 'hook — that id was the target of the deleted `LoadServerHost(...)` per-row '
            . 'script feeder (sourcebans.js, removed at #1123 D1; the feeder itself was '
            . 'dropped at #1404). The replacement contract is `[data-testid="server-host"]` '
            . '+ `data-fallback` per #1405; the legacy id is dead and stays dead.',
        );
    }

    /**
     * Anti-regression: the deleted `LoadServerHost(` call literal
     * (the legacy v1.4.11 helper name) must not be reintroduced.
     * Same shape as `DeadJsCallSitesTest`'s
     * `forbiddenPatternsByFile()` map for `admin.admins.php`,
     * applied to the template-side of the same contract.
     *
     * Pre-#1404 the call literal lived in a `<script>` blob at the
     * tail of this template (echoed via `{$server_script nofilter}`).
     * The PHP-side echo went at #1404; this gate prevents a copy-
     * paste from a fork template that pre-dates #1404 from sneaking
     * the literal back in.
     */
    public function testLegacyLoadServerHostCallIsGone(): void
    {
        $this->assertStringNotContainsString(
            "LoadServerHost('",
            $this->templateNoComments,
            'page_admin_admins_add.tpl must NOT contain the legacy `LoadServerHost(\'…\')` '
            . 'call literal — the helper was deleted with `sourcebans.js` at #1123 D1 (every '
            . 'call site raised `ReferenceError`) and the per-row feeder was dropped at '
            . '#1404. The replacement is the `server-tile-hydrate.js` auto-hydration shape '
            . 'per #1405 — see AGENTS.md "Anti-patterns" for the matching `LoadServerHost` '
            . 'entry.',
        );
    }

    /**
     * View-DTO cross-check: `Sbpp\View\AdminAdminsAddView` no longer
     * declares the `server_script` constructor parameter (#1404
     * dropped it). #1405 is purely client-side hydration off the
     * existing per-server rows the View already carries — no new
     * View property is needed. Sister to
     * `DeadJsCallSitesTest::testAdminAdminsAddViewDoesNotCarryServerScriptProperty`;
     * this test pins the contract here so a regression points at
     * the View directly without having to grep across tests.
     */
    public function testAdminAdminsAddViewCarriesNoOrphanServerScriptProperty(): void
    {
        $viewPath = ROOT . 'includes/View/AdminAdminsAddView.php';
        $this->assertFileExists($viewPath);
        $stripped = php_strip_whitespace($viewPath);

        $this->assertDoesNotMatchRegularExpression(
            '/\$server_script\b/',
            $stripped,
            'AdminAdminsAddView must not declare a `server_script` property — it fed the '
            . 'deleted `LoadServerHost(...)` echo (dropped at #1404). #1405 is purely '
            . 'client-side hydration off the existing per-server rows the View already '
            . 'carries, so no new View property is needed.',
        );
    }

    /**
     * The hydration grid wrapper must live AFTER the "Individual
     * servers" section heading and INSIDE the `$server_list` guard,
     * so the wrapper never paints on installs with zero servers
     * (the surrounding `{if !$group_list && !$server_list}` empty-
     * state branch takes over there). Cheap ordering check: the
     * "Individual servers" header is the unambiguous anchor that
     * scopes the grid to the right context, and the wrapper must
     * appear after it.
     */
    public function testHydrationWrapperLivesInsideIndividualServersBlock(): void
    {
        $headerPos = strpos($this->template, 'Individual servers');
        $wrapperPos = strpos($this->template, 'data-server-hydrate="auto"');

        $this->assertNotFalse($headerPos, 'the "Individual servers" section heading must exist in the template');
        $this->assertNotFalse($wrapperPos, 'the `data-server-hydrate="auto"` wrapper must exist in the template');
        $this->assertGreaterThan(
            $headerPos,
            $wrapperPos,
            'The `data-server-hydrate="auto"` grid wrapper must appear AFTER the "Individual '
            . 'servers" section heading so it scopes to the per-server checkbox rows (NOT the '
            . '"Server groups" block above it — the helper is per-server, not per-group, and '
            . 'the group rows don\'t carry sids the API action would recognise) (#1405).',
        );
    }
}
