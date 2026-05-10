<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * Issue #1313 — admin Server Management list (`?p=admin&c=servers&section=list`)
 * shipped placeholder Map / Players cells with no script behind them, so
 * the values stayed at the em-dash forever. The public servers list
 * (`?p=servers`) drove the same `Actions.ServersHostPlayers` JSON
 * action via an inline `<script>` block; the admin surface had the
 * markup contract, the View DTO, and the handler in place — only the
 * call site was missing.
 *
 * The fix:
 *
 *   1. Renames the admin tile's hydration hooks from
 *      `[data-hydrate="map"]` / `[data-hydrate="players"]` to
 *      `[data-testid="server-map"]` / `[data-testid="server-players"]`
 *      so they match the public tile's contract.
 *   2. Adds a status pill (`[data-testid="server-status"]`), a hostname
 *      target (`[data-testid="server-host"]`), and a refresh button
 *      (`[data-testid="server-refresh"]`) per enabled tile, plus
 *      `data-status="loading"` on the outer card so the helper can
 *      flip it to online / offline.
 *   3. Marks disabled tiles with `data-server-skip="1"` so the helper
 *      doesn't poke a UDP socket for a server the panel just told you
 *      is offline by config.
 *   4. Includes `web/scripts/server-tile-hydrate.js` from both
 *      templates — the shared helper that walks the
 *      `[data-server-hydrate="auto"]` grid and fires the JSON action
 *      per tile.
 *
 * This test renders the admin servers list page in-process and locks
 * the hooks the helper looks for, plus the script include itself. If
 * a future refactor strips the testids or the script tag, the
 * hydration silently breaks again on every install — the cell
 * placeholders revert to `—` forever and the only signal is "the
 * admin page looks like the public page used to before #1313 fixed
 * it". The test pins each contract as an HTML substring assertion
 * (cheap, no JSDOM, deterministic).
 *
 * Pattern mirrors `AdminAdminsSearchTest`: stub Smarty matching
 * init.php, capture rendered HTML via `ob_start`, assert testids /
 * script tags are present.
 */
final class AdminServersListHydrationTest extends ApiTestCase
{
    /** @var int sid of the enabled server seeded per case. */
    private int $enabledSid = 0;

    /** @var int sid of the disabled server seeded per case. */
    private int $disabledSid = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        $this->seedTestServers();
        $this->bootstrapSmartyTheme();
    }

    protected function tearDown(): void
    {
        $_GET = [];
        unset($GLOBALS['theme']);
        parent::tearDown();
    }

    /**
     * The hydration helper script must be referenced from the admin
     * list page. Without this include the per-tile testids below are
     * just inert markup.
     */
    public function testIncludesHydrationHelperScript(): void
    {
        $_GET = ['p' => 'admin', 'c' => 'servers', 'section' => 'list'];
        $html = $this->renderAdminServersPage();

        $this->assertStringContainsString(
            'src="./scripts/server-tile-hydrate.js"',
            $html,
            'Admin servers list must <script src> the shared hydration helper from web/scripts/server-tile-hydrate.js (#1313).'
        );
    }

    /**
     * The grid wrapper opts into auto-hydration via
     * `data-server-hydrate="auto"`. Without this attribute the helper
     * skips the page entirely and the placeholders stay forever.
     */
    public function testGridOptsIntoAutoHydration(): void
    {
        $_GET = ['p' => 'admin', 'c' => 'servers', 'section' => 'list'];
        $html = $this->renderAdminServersPage();

        $this->assertMatchesRegularExpression(
            '/<div\b[^>]*\bdata-testid="server-grid"[^>]*\bdata-server-hydrate="auto"/',
            $html,
            'The server-grid wrapper must carry data-server-hydrate="auto" so server-tile-hydrate.js auto-runs on first paint (#1313).'
        );
    }

    /**
     * Each enabled tile carries the testids the helper looks for:
     *   - `[data-testid="server-status"]` (pill)
     *   - `[data-testid="server-map"]`    (cell)
     *   - `[data-testid="server-players"]` (cell)
     *   - `[data-testid="server-host"]`    (hostname target)
     *   - `data-status="loading"`          (initial state)
     *
     * #1313 renamed the `[data-hydrate="…"]` placeholders from before
     * the fix; the new names match the public tile's contract.
     */
    public function testEnabledTileCarriesHydrationTestids(): void
    {
        $_GET = ['p' => 'admin', 'c' => 'servers', 'section' => 'list'];
        $html = $this->renderAdminServersPage();

        // The whole tile chunk for the enabled server.
        $tileHtml = $this->extractTile($html, $this->enabledSid);

        $this->assertStringContainsString('data-testid="server-status"', $tileHtml,
            'Enabled tile must carry the status pill testid the hydration helper drives (#1313).');
        $this->assertStringContainsString('data-testid="server-map"', $tileHtml,
            'Enabled tile must expose data-testid="server-map" so the helper can patch the map cell (#1313).');
        $this->assertStringContainsString('data-testid="server-players"', $tileHtml,
            'Enabled tile must expose data-testid="server-players" so the helper can patch the players cell (#1313).');
        $this->assertStringContainsString('data-testid="server-host"', $tileHtml,
            'Enabled tile must expose data-testid="server-host" so the helper can patch the hostname cell (#1313).');
        $this->assertStringContainsString('data-status="loading"', $tileHtml,
            'Enabled tile must start at data-status="loading" so the helper can flip it to online/offline (#1313).');
        $this->assertStringContainsString('data-testid="server-refresh"', $tileHtml,
            'Enabled tile must expose a refresh button — the helper auto-wires it to re-fire the JSON action (#1313).');

        // The refresh button must START disabled so a click before the
        // helper boots is a no-op, and so the bootstrap probe (#1311)
        // can re-enable it on settle — same gate the public servers
        // list uses. Without this, a hand-mash between page paint and
        // helper boot translates into uncached A2S probes.
        $this->assertMatchesRegularExpression(
            '/<button\b[^>]*\bdata-testid="server-refresh"[^>]*\bdisabled\b/s',
            $tileHtml,
            'Enabled tile refresh button must start `disabled` so the helper-driven bootstrap probe (#1311) controls the enable/disable cycle.'
        );
    }

    /**
     * Anti-regression: the legacy `[data-hydrate="…"]` placeholders
     * are gone. They have no JS behind them anywhere in the codebase
     * (#1313 audit), so leaving them in the DOM would let a
     * future "consistency" PR resurrect the bug by wiring code to
     * the dead names.
     */
    public function testLegacyDataHydratePlaceholdersAreGone(): void
    {
        $_GET = ['p' => 'admin', 'c' => 'servers', 'section' => 'list'];
        $html = $this->renderAdminServersPage();

        $this->assertStringNotContainsString('data-hydrate="map"', $html,
            'Legacy [data-hydrate="map"] placeholder must not survive #1313 — rename to data-testid="server-map".');
        $this->assertStringNotContainsString('data-hydrate="players"', $html,
            'Legacy [data-hydrate="players"] placeholder must not survive #1313 — rename to data-testid="server-players".');
    }

    /**
     * Disabled tiles are marked `data-server-skip="1"` so the helper
     * doesn't poke a UDP socket — there's no point probing a server
     * the admin panel just told you is offline by config. The disabled
     * pill replaces the live status pill, so the testid disappears
     * for that tile (the cells stay at the placeholder, which is the
     * expected terminal state).
     */
    public function testDisabledTileSkipsHydration(): void
    {
        $_GET = ['p' => 'admin', 'c' => 'servers', 'section' => 'list'];
        $html = $this->renderAdminServersPage();

        $disabledTile = $this->extractTile($html, $this->disabledSid);

        $this->assertStringContainsString('data-server-skip="1"', $disabledTile,
            'Disabled tile must carry data-server-skip="1" so the hydration helper leaves it alone (#1313).');
        $this->assertStringNotContainsString('data-testid="server-status"', $disabledTile,
            'Disabled tile must NOT carry the live status pill — the chrome shows a "Disabled" pill instead (#1313).');
        $this->assertStringNotContainsString('data-testid="server-refresh"', $disabledTile,
            'Disabled tile must NOT render a refresh button — there is nothing to re-query (#1313).');
    }

    /**
     * The View DTO and handler must continue to feed the template the
     * fields the markup contract depends on. This is a belt-and-braces
     * regression guard: if a future PR cleans up the View, the test
     * fails before the user sees a blank cell.
     */
    public function testTileCarriesStableSidHook(): void
    {
        $_GET = ['p' => 'admin', 'c' => 'servers', 'section' => 'list'];
        $html = $this->renderAdminServersPage();

        $this->assertMatchesRegularExpression(
            '/<article\b[^>]*\bdata-id="' . $this->enabledSid . '"/',
            $html,
            'Enabled tile must carry data-id="<sid>" so the hydration helper can route the response to the right tile (#1313).'
        );
    }

    private function seedTestServers(): void
    {
        $pdo = Fixture::rawPdo();

        // Mod row #1 ships from data.sql (Half-Life 2 Deathmatch).
        // The admin servers list LEFT JOINs :prefix_mods on modid for the
        // mod_name cell, so reusing the seeded row keeps the test
        // independent of the mod table's content.
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_servers` (ip, port, modid, rcon, enabled) VALUES (?, ?, ?, ?, ?)',
            DB_PREFIX,
        ))->execute(['10.0.0.10', 27015, 1, '', 1]);
        $this->enabledSid = (int) $pdo->lastInsertId();

        $pdo->prepare(sprintf(
            'INSERT INTO `%s_servers` (ip, port, modid, rcon, enabled) VALUES (?, ?, ?, ?, ?)',
            DB_PREFIX,
        ))->execute(['10.0.0.11', 27016, 1, '', 0]);
        $this->disabledSid = (int) $pdo->lastInsertId();
    }

    /**
     * Spin a Smarty `$theme` matching init.php so admin.servers.php
     * (which renders one View per request) doesn't crash on
     * Renderer::render. Mirrors the pattern from
     * AdminAdminsSearchTest::bootstrapSmartyTheme().
     */
    private function bootstrapSmartyTheme(): void
    {
        require_once INCLUDES_PATH . '/SmartyCustomFunctions.php';
        require_once INCLUDES_PATH . '/View/View.php';
        require_once INCLUDES_PATH . '/View/Renderer.php';

        $compileDir = sys_get_temp_dir() . '/sbpp-test-smarty-' . getmypid();
        if (!is_dir($compileDir)) {
            mkdir($compileDir, 0o775, true);
        }

        $theme = new Smarty();
        $theme->setUseSubDirs(false);
        $theme->setCompileId('default');
        $theme->setCaching(Smarty::CACHING_OFF);
        $theme->setForceCompile(true);
        $theme->setTemplateDir(SB_THEMES . SB_THEME);
        $theme->setCompileDir($compileDir);
        $theme->setCacheDir($compileDir);
        $theme->setEscapeHtml(true);
        $theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'help_icon',     'smarty_function_help_icon');
        $theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'sb_button',     'smarty_function_sb_button');
        $theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'load_template', 'smarty_function_load_template');
        $theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'csrf_field',    'smarty_function_csrf_field');
        $theme->registerPlugin(Smarty::PLUGIN_BLOCK,    'has_access',    'smarty_block_has_access');
        $theme->registerPlugin('modifier', 'smarty_stripslashes',     'smarty_stripslashes');
        $theme->registerPlugin('modifier', 'smarty_htmlspecialchars', 'smarty_htmlspecialchars');

        $GLOBALS['theme']    = $theme;
        $GLOBALS['username'] = 'admin';
    }

    /**
     * Run admin.servers.php in-process and capture its rendered HTML.
     */
    private function renderAdminServersPage(): string
    {
        ob_start();
        try {
            (function (): void {
                global $userbank, $theme;
                $userbank = $GLOBALS['userbank'];
                $theme    = $GLOBALS['theme'];
                require ROOT . 'pages/admin.servers.php';
            })();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    /**
     * Pull the `<article …>…</article>` chunk for a specific server
     * by sid so per-tile assertions don't see the other tile's markup.
     * Each tile carries `id="sid_<sid>"` so the boundary is unambiguous.
     */
    private function extractTile(string $html, int $sid): string
    {
        $pattern = '/<article\b[^>]*\bid="sid_' . $sid . '"[\s\S]*?<\/article>/';
        if (preg_match($pattern, $html, $m) !== 1) {
            $this->fail("Could not extract <article id=\"sid_$sid\"> tile from rendered HTML.");
        }
        return $m[0];
    }
}
