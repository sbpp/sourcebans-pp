<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1312: the public servers list (`?p=servers`) lost the map
 * thumbnail surface when #1123 D1 rebuilt `page_servers.tpl` from the
 * handoff card grid. The legacy v1.x card carried an inline
 * `<img id="mapimg_{$server.sid}">` whose `src` was patched in by JS
 * once the SourceQuery response landed; the v2.0 redesign kept the
 * `mapimg` field in the API envelope but dropped the rendering site.
 *
 * The fix restores the thumbnail in the EXPANDED panel (so it rides
 * the same `data-expanded="true"` toggle that surfaces the player
 * list) with a `hidden`-by-default `<img>` slot the hydration helper
 * patches from `r.data.mapimg`. `onload` unhides the element; `onerror`
 * keeps it hidden so missing files never paint a broken-image icon.
 *
 * #1313 extracted the per-tile hydration code from the inline
 * `<script>` block at the bottom of `page_servers.tpl` into a
 * shared helper at `web/scripts/server-tile-hydrate.js` so the
 * admin Server Management list could reuse the wiring. The mapimg
 * patch moved with the rest of `applyData()`; the template still
 * ships the `<img>` slot and now `<script src>`s the helper.
 *
 * This suite is the hermetic regression guard for that contract:
 *
 *   1. The API handler still returns `mapimg` (the contract is
 *      load-bearing for any future consumer — the legacy admin
 *      surfaces, the v1.x reference render, plus this template).
 *   2. The template ships the `<img data-testid="server-map-img">`
 *      slot inside the players panel.
 *   3. The hydration helper wires `r.data.mapimg` into the slot's
 *      `src` and toggles `hidden` on `load` / `error`, AND the
 *      template <script src>s the helper.
 *   4. `GetMapImage()` still falls back to `nomap` when the file
 *      can't be found, and the bundled `nomap.jpg` placeholder still
 *      ships (so the fallback path renders something instead of a
 *      404 / broken-image icon).
 *
 * The assertions are deliberately string-shape against the template
 * source rather than a full Smarty render: the regression we're
 * guarding against is "the slot got removed from the template" /
 * "the JS got rewired to ignore the field", and a sub-millisecond
 * `file_get_contents` + `assertStringContainsString` catches both
 * without booting a stub Smarty or seeding a server row. The E2E
 * suite covers the runtime observable (the `<img>` actually paints
 * after the API call lands) — this PHPUnit guard is the contract
 * gate.
 */
final class ServerMapImageRenderTest extends TestCase
{
    private string $template;
    private string $handler;
    private string $hydrate;

    protected function setUp(): void
    {
        parent::setUp();

        $tplPath = ROOT . 'themes/default/page_servers.tpl';
        $hdlPath = ROOT . 'api/handlers/servers.php';
        // #1313 extracted the per-tile hydration into a shared helper
        // so the admin Server Management list could reuse the wiring
        // without copy-pasting ~200 lines of JS. The mapimg patch
        // moved with it; the template still ships the `<img>` slot,
        // the helper holds the `applyData()` site that patches `src`
        // + toggles `hidden`.
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
     * `api_servers_host_players` must keep returning the `mapimg`
     * field; the template's `<img>` slot has no value if the handler
     * stops emitting the URL. Pre-#1312 the field was effectively
     * dead weight in the response — this test pins that the contract
     * is now load-bearing on both ends.
     */
    public function testHandlerStillEmitsMapimgField(): void
    {
        // Match the array-literal entry regardless of the surrounding
        // whitespace shape — the column alignment of the `=>` arrows
        // in the handler's response builder has shifted across
        // refactors (e.g. #1311's SourceQueryCache split) without the
        // contract changing. We care that `mapimg` is sourced from
        // `GetMapImage()`, not how many spaces sit before the arrow.
        $this->assertMatchesRegularExpression(
            "/'mapimg'\\s*=>\\s*GetMapImage\\s*\\(/",
            $this->handler,
            "api_servers_host_players must keep emitting `mapimg` so page_servers.tpl's "
            . "`<img data-testid=\"server-map-img\">` slot can render — see #1312 for the "
            . "v1.x → v2.0 regression that motivated the surface restore.",
        );
    }

    /**
     * The thumbnail slot itself. Asserts both the testid hook (E2E
     * specs anchor on this) AND the `hidden` default — the slot
     * stays invisible until `onload` fires so the empty `<img>`
     * never paints a broken-image icon between page load and the
     * SourceQuery round-trip landing.
     */
    public function testTemplateShipsMapImgSlot(): void
    {
        $this->assertStringContainsString(
            'data-testid="server-map-img"',
            $this->template,
            "page_servers.tpl must ship the `<img data-testid=\"server-map-img\">` slot "
            . "inside the players panel so the inline initializer has a target to patch "
            . "from r.data.mapimg (#1312).",
        );

        // The slot must be `hidden` by default so the empty src=""
        // doesn't paint a broken-image icon during the loading window.
        // Match against the literal markup we emit so a future style
        // refactor doesn't accidentally drop the attribute.
        $this->assertMatchesRegularExpression(
            '/<img[^>]*\bdata-testid="server-map-img"[^>]*\bhidden\b/s',
            $this->template,
            "The `<img data-testid=\"server-map-img\">` slot must default to `hidden` so "
            . "the empty `src=\"\"` placeholder never paints a broken-image icon between "
            . "page load and the SourceQuery response landing (#1312).",
        );
    }

    /**
     * Slot must live inside the `server-players-panel` div so it
     * rides the same `[hidden]` toggle that surfaces the player list
     * — the issue's "expanded server card" framing pins this. A
     * future refactor that pulls the `<img>` outside the panel
     * (always-visible, even on collapsed cards) would silently
     * regress the contract.
     *
     * Cheap sanity: search for the panel's opening div, then assert
     * the `<img>` slot appears between that opening tag and the
     * panel's closing `</div>`. Smarty parses the file linearly so
     * substring ordering is a faithful proxy for DOM ancestry.
     */
    public function testMapImgSlotLivesInsidePlayersPanel(): void
    {
        $panelStart = strpos($this->template, 'data-testid="server-players-panel"');
        $imgStart   = strpos($this->template, 'data-testid="server-map-img"');

        $this->assertNotFalse($panelStart, 'players panel container must exist in the template');
        $this->assertNotFalse($imgStart,   'map-img slot must exist in the template');
        $this->assertGreaterThan(
            $panelStart,
            $imgStart,
            "The `<img data-testid=\"server-map-img\">` slot must appear AFTER the "
            . "`server-players-panel` opening tag so it renders inside the panel that "
            . "the toggle expands — pulling it outside the panel makes the thumbnail "
            . "always-visible and changes the affordance the issue restored (#1312).",
        );
    }

    /**
     * The hydration helper must wire `d.mapimg` into the slot's
     * `src` and the `onload` / `onerror` pair into the `hidden`
     * toggle — without this wiring the slot stays empty + hidden
     * forever and the thumbnail never paints.
     *
     * Pre-#1313 the wiring lived inline at the bottom of
     * `page_servers.tpl`; the issue extracted it into
     * `web/scripts/server-tile-hydrate.js` so the admin Server
     * Management list could reuse it. The template still ships the
     * `<img>` slot (see `testTemplateShipsMapImgSlot()`), but the
     * `src` / `onload` / `onerror` patch site moved with the rest of
     * `applyData()`.
     *
     * The template must also still <script src> the helper —
     * without that include the `<img>` is just inert markup.
     */
    public function testHydrationHelperWiresMapImg(): void
    {
        $this->assertStringContainsString(
            'src="./scripts/server-tile-hydrate.js"',
            $this->template,
            "page_servers.tpl must <script src> web/scripts/server-tile-hydrate.js — "
            . "without the include the helper never boots and the `<img>` slot stays "
            . "empty + hidden forever (#1312, #1313).",
        );

        $this->assertStringContainsString(
            "tile.querySelector('[data-testid=\"server-map-img\"]')",
            $this->hydrate,
            "server-tile-hydrate.js must locate the `<img>` slot via its testid hook "
            . "so it can patch the `src` from r.data.mapimg (#1312).",
        );

        // Match against any local binding name (the helper currently
        // pins `imgEl = mapImg` to satisfy tsc's closure-narrowing
        // rules — a future refactor might shuffle the name again).
        // The semantic contract is `<binding>.src = String(d.mapimg)`.
        $this->assertMatchesRegularExpression(
            '/\w+\.src\s*=\s*String\(d\.mapimg\)/',
            $this->hydrate,
            "server-tile-hydrate.js must assign d.mapimg to the slot's `src` — "
            . "without this assignment the empty `src=\"\"` placeholder never gets "
            . "the live value the API returns (#1312).",
        );

        $this->assertMatchesRegularExpression(
            '/\w+\.onload\s*=\s*function\s*\([^)]*\)\s*\{[^}]*removeAttribute\([\'\"]hidden[\'\"]\)/s',
            $this->hydrate,
            "server-tile-hydrate.js must unhide the slot on `load` so the thumbnail "
            . "becomes visible only after the file actually downloads — pre-load the "
            . "slot stays `hidden` (#1312).",
        );

        $this->assertMatchesRegularExpression(
            '/\w+\.onerror\s*=\s*function\s*\([^)]*\)\s*\{[^}]*setAttribute\([\'\"]hidden[\'\"],\s*[\'\"][\'\"]\)/s',
            $this->hydrate,
            "server-tile-hydrate.js must KEEP the slot hidden on `error` so a missing "
            . "file (or a missing `nomap.jpg`) never paints a broken-image icon (#1312).",
        );
    }

    /**
     * `GetMapImage()` is the helper the API handler calls to derive
     * the URL the template will eventually patch in. Its fallback to
     * `nomap.jpg` is what keeps unknown maps from producing a 404 —
     * this test pins both halves of the contract: the helper's
     * behaviour AND the bundled `nomap.jpg` placeholder.
     */
    public function testGetMapImageFallsBackToNomap(): void
    {
        $url = \GetMapImage('definitely-not-a-real-map-' . uniqid());
        $this->assertSame(
            SB_MAP_LOCATION . '/nomap.jpg',
            $url,
            "GetMapImage() must return the `nomap.jpg` URL when the requested map's "
            . "file is missing — without this fallback every server running an "
            . "unrecognised map would emit a 404 / broken-image icon (#1312).",
        );

        // Belt-and-suspenders: the bundled `nomap.jpg` placeholder
        // still needs to exist on disk, otherwise the fallback URL
        // points at a 404 and the `onerror` branch is the only thing
        // standing between a missing file and a visible broken-image
        // icon. Assert the asset ships.
        $this->assertFileExists(
            ROOT . SB_MAP_LOCATION . '/nomap.jpg',
            "web/images/maps/nomap.jpg must ship as the fallback placeholder for "
            . "`GetMapImage()` — without it the helper returns a URL that 404s and "
            . "every server running an unrecognised map relies on the JS `onerror` "
            . "handler as the last line of defense against a broken-image icon (#1312).",
        );
    }
}
