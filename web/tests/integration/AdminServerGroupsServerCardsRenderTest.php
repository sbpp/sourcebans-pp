<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1406: the admin Server Groups list (`?p=admin&c=groups`, the
 * "Server groups" section that renders `:prefix_groups WHERE type = 3`
 * rows) lost its per-card hydration surface at v2.0.
 *
 * Pre-#1123 v1.x: each row carried a `<div id="servers_{$group.gid}">`
 * placeholder and the page handler echoed a
 * `<script>LoadServerHostPlayersList('<sid1>;<sid2>;...', 'id', 'servers_<gid>')</script>`
 * blob per group meant to async-populate the slot.
 *
 * #1123 D1 deleted `web/scripts/sourcebans.js` (including
 * `LoadServerHostPlayersList`). For two minor versions every admin
 * Server Groups page load raised `ReferenceError: LoadServerHostPlayersList
 * is not defined` once per group, AND the literal admin-facing copy
 * "Servers populate via the legacy `LoadServerHostPlayersList` hook."
 * showed for every group, every time.
 *
 * Cleanup PR #1404 dropped the dead echo + the slot + the placeholder
 * copy. This PR (#1406) is the **additive replacement**:
 *
 *   1. The page handler (`web/pages/admin.groups.php`) re-introduces
 *      the `:prefix_servers_groups` lookup, INNER-JOINs against
 *      `:prefix_servers`, and exposes the per-group `servers` array
 *      (`[{sid: int, ip: string, port: int}, ...]`) on each
 *      `$server_list` row.
 *   2. The template (`web/themes/default/page_admin_groups_list.tpl`)
 *      renders one `[data-testid="server-tile"] data-id="{sid}"` per
 *      server inside a `[data-server-hydrate="auto"]` container per
 *      group, with the bare `IP:port` as the in-cell SSR / cache-cold
 *      / no-JS fallback inside `[data-testid="server-host"]`.
 *   3. The shared `web/scripts/server-tile-hydrate.js` helper picks
 *      up each tile and fires `Actions.ServersHostPlayers` to replace
 *      the SSR fallback with the live hostname (the same minimal-
 *      integration shape the dashboard widget #1375 rides — only the
 *      hostname cell is consumed; every other helper testid is
 *      feature-detected and silently no-ops).
 *
 * This test mirrors `ServerMapImageRenderTest`'s contract-style file
 * scan — no DB seed, no Smarty render. The wiring lives across three
 * files (page handler, template, hydration helper) and a regression
 * in any one of them silently breaks the surface; pinning the
 * contract at each link is cheaper than a full live-render test and
 * the E2E spec
 * (`web/tests/e2e/specs/flows/admin-groups-server-cards-hydration.spec.ts`)
 * covers the runtime observable.
 *
 * The "dead shape stays dead" assertions duplicate what
 * `DeadJsCallSitesTest` already pins — they live here too so a future
 * cleanup that loosens the cross-file gate (e.g. dropping the
 * `admin.groups.php` entry from `forbiddenPatternsByFile()` because
 * "the page handler doesn't carry that legacy shape any more") still
 * fails LOUDLY against THIS issue's contract. Belt-and-braces; the
 * surface they protect is the same.
 */
final class AdminServerGroupsServerCardsRenderTest extends TestCase
{
    private string $template;
    private string $handler;
    private string $hydrate;

    protected function setUp(): void
    {
        parent::setUp();

        $tplPath = ROOT . 'themes/default/page_admin_groups_list.tpl';
        $hdlPath = ROOT . 'pages/admin.groups.php';
        $jsPath  = ROOT . 'scripts/server-tile-hydrate.js';

        $tpl = file_get_contents($tplPath);
        $hdl = file_get_contents($hdlPath);
        $js  = file_get_contents($jsPath);
        if ($tpl === false || $hdl === false || $js === false) {
            self::fail("setUp could not read template / handler / hydrate helper ({$tplPath} / {$hdlPath} / {$jsPath})");
        }
        $this->template = $tpl;
        $this->handler  = $hdl;
        $this->hydrate  = $js;
    }

    /**
     * The page handler must JOIN `:prefix_servers_groups` against
     * `:prefix_servers` and expose the result on each `$server_list`
     * row's `servers` key. Without this, the template's `{foreach
     * from=$group.servers}` body resolves to the empty arm on every
     * group regardless of how many servers are actually bound — the
     * card body would show "No servers bound to this group yet." for
     * every group, which is the same operator-facing wrongness as the
     * pre-#1404 placeholder copy (just less obviously broken).
     *
     * Matched regex is loose on whitespace so a future SQL re-flow
     * (line breaks, indentation) doesn't false-fire the gate; the
     * load-bearing parts are the JOIN, the WHERE on `:gid`, and the
     * column list.
     */
    public function testHandlerJoinsServersGroupsAgainstServersForEachRow(): void
    {
        // The legacy COUNT(server_id) shape would emit
        // `server_count` only; the new shape pulls the actual rows so
        // each `$server_list` row carries a `servers` array.
        $this->assertMatchesRegularExpression(
            '/INNER\s+JOIN\s+`:prefix_servers`\s+AS\s+S\s+ON\s+S\.sid\s*=\s*SG\.server_id/i',
            $this->handler,
            'admin.groups.php must INNER JOIN :prefix_servers_groups against :prefix_servers to '
            . 'populate each $server_list row\'s `servers` array — without the JOIN the per-group '
            . 'server cards in page_admin_groups_list.tpl all resolve to the empty arm regardless of '
            . 'how many servers are actually bound to the group (#1406).',
        );

        $this->assertMatchesRegularExpression(
            '/SELECT\s+S\.sid,\s*S\.ip,\s*S\.port,\s*S\.enabled/i',
            $this->handler,
            'admin.groups.php must SELECT sid / ip / port / enabled so the template has the '
            . 'canonical (sid, ip, port, enabled) quadruple to render one '
            . '`[data-testid="server-tile"] data-id="{sid}"` per server with the bare `IP:port` SSR '
            . 'fallback inside `[data-testid="server-host"]` AND emit `data-server-skip="1"` on '
            . 'disabled rows so `server-tile-hydrate.js` short-circuits the per-tile probe '
            . '(#1406, post-review).',
        );

        $this->assertMatchesRegularExpression(
            '/\$row\[[\'"]servers[\'"]\]\s*=/',
            $this->handler,
            'admin.groups.php must write the bound-server list to $row[\'servers\'] so the View DTO '
            . 'forwards it to the template — without this assignment the {foreach from=$group.servers} '
            . 'block has nothing to iterate (#1406).',
        );

        // The handler maps the raw row through `array_map` and the
        // shape needs to carry `enabled` so the template's
        // `{if !$server.enabled}` branch sees a truthy/falsy value. A
        // future refactor that drops the `enabled` key from the
        // composed shape would silently re-enable probing on
        // disabled servers — pin the key here.
        $this->assertMatchesRegularExpression(
            '/[\'"]enabled[\'"]\s*=>\s*\(bool\)/i',
            $this->handler,
            'admin.groups.php must propagate the cast `enabled` flag into the per-server array so '
            . 'the template\'s `data-server-skip` gate has a bool to branch on. Casting at the '
            . 'handler keeps the on-disk TINYINT shape an implementation detail the template '
            . 'doesn\'t need to know about (#1406, post-review).',
        );
    }

    /**
     * The per-group card body must wrap its server-tile list in a
     * `[data-server-hydrate="auto"]` container so the shared
     * `server-tile-hydrate.js` helper auto-runs on first paint and
     * fires `Actions.ServersHostPlayers` per tile. Same opt-in shape
     * the public Server List (`page_servers.tpl`), admin Server
     * Management list (`page_admin_servers_list.tpl`), and dashboard
     * Servers widget (`page_dashboard.tpl`) use.
     *
     * Without the container the tiles render with the SSR `IP:port`
     * fallback but never get the live hostname — exactly the
     * pre-#1404 dead-feeder symptom in different clothing.
     */
    public function testTemplateOptsServerGroupCardsIntoAutoHydration(): void
    {
        $this->assertStringContainsString(
            'data-server-hydrate="auto"',
            $this->template,
            'page_admin_groups_list.tpl must wrap each server group\'s per-tile list in a '
            . '`[data-server-hydrate="auto"]` container so server-tile-hydrate.js auto-runs on first '
            . 'paint and fires Actions.ServersHostPlayers per tile (#1406).',
        );

        // The hydration container lives inside the "Server groups" section,
        // not the master-detail flag grid above. We pin the relative ordering
        // so a future refactor that accidentally hoists the wrapper out of
        // the per-group foreach (turning the per-card stack into one shared
        // global tile list) fails loudly.
        $serverGroupsSectionAt = strpos($this->template, 'data-testid="server-groups-section"');
        $hydrateContainerAt    = strpos($this->template, 'data-server-hydrate="auto"');
        $this->assertNotFalse($serverGroupsSectionAt, 'server-groups section anchor must exist');
        $this->assertNotFalse($hydrateContainerAt,    'hydration container must exist');
        $this->assertGreaterThan(
            $serverGroupsSectionAt,
            $hydrateContainerAt,
            'The `[data-server-hydrate="auto"]` wrapper must appear AFTER the '
            . '`data-testid="server-groups-section"` opening anchor so the auto-hydrated tiles live '
            . 'inside the Server Groups card stack — pulling the wrapper outside the foreach would '
            . 'fan every group\'s servers into one shared list and break the per-group association '
            . '(#1406).',
        );
    }

    /**
     * The card body's per-server `<li>` (or equivalent block) must
     * ship the `[data-testid="server-tile"]` outer marker + the
     * `data-id="{sid}"` attribute the hydration helper keys off, AND
     * the inner `[data-testid="server-host"]` slot the helper patches
     * the live hostname into.
     *
     * The matched regex pins the `{$server.sid}` interpolation so a
     * future refactor that swaps in a different field name (or drops
     * the per-server foreach in favor of a static `<li>`) fails fast.
     */
    public function testTemplateEmitsServerTilePerBoundServerInsideServerGroupsSection(): void
    {
        $serverGroupsSectionAt = strpos($this->template, 'data-testid="server-groups-section"');
        $this->assertNotFalse($serverGroupsSectionAt, 'server-groups section anchor must exist');
        // Bound to the rest of the template after the section anchor; the
        // master-detail web-admin-groups + server-admin-groups sections above
        // do NOT ship server-tile markup and the assertions below would
        // false-match if they did.
        $serverGroupsBody = substr($this->template, $serverGroupsSectionAt);

        // `data-testid="server-tile"` paired with `data-id="{$server.sid}"`
        // is the canonical hook the hydration helper iterates inside its
        // container. Pre-#1406 the section had no server-tile markup at all
        // — pinning the foreach interpolation guards against a future
        // refactor that wires `data-id` to a static value or a different
        // field.
        $this->assertMatchesRegularExpression(
            '/data-testid="server-tile"[^>]*\bdata-id="\{\$server\.sid\}"/s',
            $serverGroupsBody,
            'page_admin_groups_list.tpl\'s server-groups section must emit one '
            . '`[data-testid="server-tile"] data-id="{$server.sid}"` per bound server inside the '
            . 'per-group foreach — this is the contract `web/scripts/server-tile-hydrate.js` '
            . 'iterates via `container.querySelectorAll(\'[data-testid="server-tile"]\')` (#1406).',
        );

        // The hostname slot. The helper patches the inner-text via
        // `sb.setHTML(host.id, d.hostname)` on probe success and re-paints
        // the IP:port from the `data-fallback` attribute on probe failure.
        // Asserting both halves (the testid AND the data-fallback) is what
        // pins the SSR / cache-cold / no-JS observable contract.
        $this->assertMatchesRegularExpression(
            '/data-testid="server-host"[^>]*data-fallback="\{\$server\.ip\|escape\}:\{\$server\.port\}"/s',
            $serverGroupsBody,
            'Each `[data-testid="server-tile"]` must contain a `[data-testid="server-host"]` slot '
            . 'with `data-fallback="{$server.ip|escape}:{$server.port}"` so server-tile-hydrate.js '
            . 'has the canonical IP:port fallback to re-paint on probe failure (#1406).',
        );

        // Inner-text SSR fallback. With the data-fallback attribute alone
        // the no-JS path would render an empty hostname cell; the inner-text
        // is what makes the surface useful when hydration is disabled / a
        // third-party theme strips theme.js + the helper. Foreshadowed in
        // the issue body: "the fallback (no JS / hydration disabled / cache
        // cold) is the bare IP:port list, which is still useful operator
        // context".
        $this->assertMatchesRegularExpression(
            '/<div\s+class="[^"]*"[^>]*data-testid="server-host"[^>]*>\{\$server\.ip\|escape\}:\{\$server\.port\}<\/div>/s',
            $serverGroupsBody,
            'The `[data-testid="server-host"]` slot must SSR-render `{$server.ip|escape}:{$server.port}` '
            . 'as its inner-text so the no-JS path stays informative (#1406 issue body: "the fallback '
            . '(no JS / hydration disabled / cache cold) is the bare IP:port list").',
        );
    }

    /**
     * Disabled servers stay visible (the bound-but-disabled
     * relationship is the useful operator context) but ride the
     * `data-server-skip="1"` short-circuit so
     * `server-tile-hydrate.js`'s `loadTile()` returns early instead
     * of firing `Actions.ServersHostPlayers` against a server the
     * panel already knows is offline by config. Mirror of the sibling
     * contract in `page_admin_servers_list.tpl` (single-source the
     * `pill--offline` "Disabled" affordance + the `data-server-skip`
     * gate).
     *
     * Pin BOTH halves: the structural gate (`data-server-skip="1"`
     * conditional on `!$server.enabled`) AND the visible affordance
     * (`[data-testid="server-disabled-tag"]` pill). Without the
     * pill the row would silently stay at the SSR `IP:port`
     * fallback and an admin would reasonably wonder whether the
     * probe failed; without the `data-server-skip` the helper would
     * fire a pointless `Actions.ServersHostPlayers` round-trip
     * against a server the panel already knows is offline.
     */
    public function testTemplateGatesDisabledServersOutOfTheHydrationProbe(): void
    {
        // The conditional must key on `!$server.enabled` (the cast
        // handler-side flag — see `testHandlerJoinsServersGroupsAgainstServersForEachRow`
        // above) and emit `data-server-skip="1"` on the per-server
        // `<li>` so the helper's `loadTile()` early-returns. A regex
        // here so a future re-indent doesn't false-fire the gate.
        $this->assertMatchesRegularExpression(
            '/\{if\s+!\$server\.enabled\}data-server-skip="1"\{\/if\}/',
            $this->template,
            'page_admin_groups_list.tpl must emit `{if !$server.enabled}data-server-skip="1"{/if}` on '
            . 'each per-server `<li>` so server-tile-hydrate.js\'s loadTile() short-circuits on '
            . 'disabled servers — without this gate the helper fires a pointless '
            . 'Actions.ServersHostPlayers round-trip against every disabled server every page load '
            . '(#1406, post-review).',
        );

        // The visible "Disabled" pill — `[data-testid="server-disabled-tag"]`
        // for E2E + integration test anchoring. The `pill pill--offline`
        // class chain matches the sibling contract in
        // `page_admin_servers_list.tpl` (same single-source affordance).
        $this->assertStringContainsString(
            'data-testid="server-disabled-tag"',
            $this->template,
            'page_admin_groups_list.tpl must surface a `[data-testid="server-disabled-tag"]` pill '
            . 'on disabled rows so the admin sees WHY the row stays at the SSR IP:port — without '
            . 'the affordance the row reads as "the probe just hasn\'t resolved yet" and an admin '
            . 'would reasonably wonder if the network failed (#1406, post-review).',
        );

        // The pill must live INSIDE the per-server foreach so it
        // renders per disabled row, not as a sibling above/below the
        // server-list. Cheap proximity check: the disabled-tag string
        // appears AFTER the foreach opener and BEFORE the foreach closer.
        $foreachOpenAt  = strpos($this->template, '{foreach from=$group.servers item="server"}');
        $foreachCloseAt = strrpos($this->template, '{/foreach}');
        $pillAt         = strpos($this->template, 'data-testid="server-disabled-tag"');
        $this->assertNotFalse($foreachOpenAt,  'per-server foreach opener must exist');
        $this->assertNotFalse($foreachCloseAt, 'per-server foreach closer must exist');
        $this->assertNotFalse($pillAt,         'disabled-tag pill must exist');
        $this->assertGreaterThan(
            $foreachOpenAt,
            $pillAt,
            'The `server-disabled-tag` pill must live inside the per-server foreach so it renders '
            . 'per disabled row, not as a sibling block (#1406, post-review).',
        );
        $this->assertLessThan(
            $foreachCloseAt,
            $pillAt,
            'The `server-disabled-tag` pill must live inside the per-server foreach so it renders '
            . 'per disabled row, not as a sibling block (#1406, post-review).',
        );
    }

    /**
     * The empty-state arm: when a group has NO servers bound, the
     * card body shows a one-liner instead of mounting an empty
     * hydration container. Two reasons:
     *
     *   1. An empty `[data-server-hydrate="auto"]` container is a
     *      no-op (the helper short-circuits via `tiles.length === 0`),
     *      so semantically equivalent to omitting it — but the operator
     *      surface should explicitly call out the "0 servers bound"
     *      state instead of leaving the card body blank.
     *   2. A future helper change that fires a one-time probe per
     *      container regardless of tile count would surface this gap
     *      as wasted load.
     */
    public function testTemplateRendersEmptyStateForGroupsWithoutBoundServers(): void
    {
        $this->assertStringContainsString(
            'data-testid="server-group-empty"',
            $this->template,
            'page_admin_groups_list.tpl must ship a `[data-testid="server-group-empty"]` empty-state '
            . 'marker for the "0 servers bound to this group" arm so the operator surface explicitly '
            . 'calls out the state instead of rendering an empty card body — and so E2E specs can '
            . 'anchor on the testid (#1406).',
        );
    }

    /**
     * The template must `<script src>` the shared hydration helper
     * (or rely on a sibling include — the gate is "the helper is on
     * the page"). Without it the `[data-server-hydrate="auto"]`
     * container is inert; the cards stay at the SSR IP:port forever.
     *
     * Note the path is relative (`./scripts/...`) per the chrome's
     * standard pattern; matches `page_servers.tpl` /
     * `page_admin_servers_list.tpl` / `page_dashboard.tpl`.
     */
    public function testTemplateIncludesHydrationHelperScript(): void
    {
        $this->assertStringContainsString(
            'src="./scripts/server-tile-hydrate.js"',
            $this->template,
            'page_admin_groups_list.tpl must `<script src>` web/scripts/server-tile-hydrate.js — '
            . 'without the include the `[data-server-hydrate="auto"]` container is inert and the '
            . 'per-group server cards stay at the SSR IP:port forever (#1406, mirror of the '
            . 'sibling shape in page_servers.tpl + page_admin_servers_list.tpl + page_dashboard.tpl).',
        );
    }

    /**
     * The hydration helper's per-tile contract. We mirror the
     * sibling assertion in `ServerMapImageRenderTest::testHydrationHelperWiresMapImg`
     * for the `[data-testid="server-host"]` slot the dashboard widget
     * (and now the per-group cards) consume.
     *
     * Without this wiring the helper would still iterate the tiles
     * but never patch the hostname — the SSR `IP:port` would stay on
     * every card forever, regardless of the cache or A2S probe
     * result.
     */
    public function testHydrationHelperWiresServerHostSlot(): void
    {
        $this->assertStringContainsString(
            "tile.querySelector('[data-testid=\"server-host\"]')",
            $this->hydrate,
            'server-tile-hydrate.js must locate the `[data-testid="server-host"]` slot via its testid '
            . 'hook so it can patch the inner-text with the live hostname (#1406, mirror of the '
            . 'sibling map-img wiring contract in ServerMapImageRenderTest).',
        );

        // The helper writes via `sb.setHTML(hostId, d.hostname)`. We assert
        // the call shape so a future refactor that swaps in plain
        // `textContent =` (lossy on entity-escaped hostnames; the handler
        // htmlspecialchars()'s the value server-side) fails fast.
        $this->assertMatchesRegularExpression(
            '/sb\.setHTML\(\s*hostId\s*,\s*d\.hostname\s*\)/',
            $this->hydrate,
            'server-tile-hydrate.js must write the hostname via sb.setHTML so the htmlspecialchars()-ed '
            . 'value the handler emits surfaces correctly — plain `textContent =` would render the '
            . 'entity references as visible literal text (#1406).',
        );
    }

    /**
     * Sister contract to `DeadJsCallSitesTest::testDeadJsCallSitesStayDeleted`:
     * the dead `LoadServerHostPlayersList(...)` echo stays gone in
     * `admin.groups.php`. We pin it here too so a regression against
     * this specific surface fails inside the same test class that
     * documents the additive replacement, not five surfaces away in
     * the cross-file gate.
     *
     * Comment-stripped via `php_strip_whitespace()` (same defensiveness
     * `DeadJsCallSitesTest` uses) so this file's own docblock + the
     * page handler's `#1404 dropped the pre-fix ...` comment that
     * names the helper for historical context don't false-fire the
     * gate.
     */
    public function testHandlerDoesNotReintroduceLoadServerHostPlayersList(): void
    {
        $stripped = php_strip_whitespace(ROOT . 'pages/admin.groups.php');

        $this->assertStringNotContainsString(
            'LoadServerHostPlayersList(',
            $stripped,
            'admin.groups.php must NOT re-emit `<script>LoadServerHostPlayersList(...)</script>` — the '
            . 'helper was deleted with sourcebans.js at #1123 D1 and every regression raises '
            . 'ReferenceError once per server group. The modern replacement is the per-card '
            . '`[data-testid="server-tile"]` stack hydrated by web/scripts/server-tile-hydrate.js '
            . '(#1406; sister to DeadJsCallSitesTest::testDeadJsCallSitesStayDeleted).',
        );

        $this->assertStringNotContainsString(
            'echo "<script>";',
            $stripped,
            'admin.groups.php must NOT re-emit the bare `<script>` opening that wrapped the per-group '
            . 'LoadServerHostPlayersList feeder. Use the data-attribute hooks (`data-server-hydrate` '
            . '/ `data-testid`) instead (#1406).',
        );
    }

    /**
     * Sister contract: the dead `<div id="servers_{$group.gid}">` slot
     * the legacy hydration feeder targeted, and the literal
     * "Servers populate via the legacy LoadServerHostPlayersList hook."
     * placeholder copy that was admin-facing for two minor versions,
     * both stay deleted in the template.
     *
     * `DeadJsCallSitesTest::testDeadTemplateSidesStayDropped` already
     * pins these but the per-template gate is the right place for
     * #1406's belt-and-braces — a future refactor that moves the
     * Server Groups section into its own partial would silently take
     * the global gate's coverage with it.
     */
    public function testTemplateDoesNotReintroduceLegacyHydrationSurface(): void
    {
        // Strip `{* ... *}` Smarty comments first so this file's
        // explanatory `#1406: per-group server-card stack. ...`
        // template comment that names the legacy slot for historical
        // context doesn't false-fire the gate.
        $stripped = (string) preg_replace('/\{\*.*?\*\}/s', '', $this->template);

        $this->assertDoesNotMatchRegularExpression(
            '/<div\s+id\s*=\s*"servers_\{\$group\.gid\}"/',
            $stripped,
            'page_admin_groups_list.tpl must NOT re-introduce the `<div id="servers_{$group.gid}">` '
            . 'slot the dead LoadServerHostPlayersList feeder targeted. Use the data-attribute hooks '
            . '(`data-testid="server-tile"` / `data-id="{$server.sid}"`) instead (#1406).',
        );

        $this->assertStringNotContainsString(
            'Servers populate via the legacy',
            $stripped,
            'page_admin_groups_list.tpl must NOT re-introduce the "Servers populate via the legacy '
            . 'LoadServerHostPlayersList hook." admin-facing placeholder copy — pre-#1404 this '
            . 'string showed for every server group, every page load, for two minor versions. The '
            . 'modern shape SSRs the bare IP:port directly so the operator sees useful state '
            . 'whether or not the hydration helper boots (#1406).',
        );
    }
}
