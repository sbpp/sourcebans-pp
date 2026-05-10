<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use ReflectionClass;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * Issue #1306: the public servers page (`?p=servers`) used to render
 * a help hint ("Right-click a player on an expanded card to kick, ban,
 * or message them.") to admins with `ADMIN_OWNER | ADMIN_ADD_BAN`.
 * The hint promised a right-click context menu that no longer ships —
 * the supporting JS lived in `web/scripts/sourcebans.js`'s
 * `LoadServerHost(...)` helper which was deleted with the rest of the
 * file at #1123 D1, and the v2.0.0 `page_servers.tpl` rewrite never
 * re-registered the menu.
 *
 * The fix dropped the hint markup, the dead context-menu helpers
 * (`web/scripts/contextMenoo.js`, `sb.contextMenu`, the legacy global
 * `AddContextMenu`), and the `IN_SERVERS_PAGE` / `access_bans` View
 * props that gated the hint visibility on `Sbpp\View\ServersView`
 * (they were only consumed by the hint conditional in the template).
 *
 * This test pins three invariants:
 *
 * 1. The rendered HTML for `?p=servers`, when an admin with the gating
 *    flags is logged in AND at least one server row is present in
 *    `:prefix_servers`, does NOT contain the
 *    `data-testid="servers-rcon-hint"` element. The bug only manifested
 *    when servers > 0, so the seeded row is load-bearing for the
 *    regression assertion — without it the hint was already hidden by
 *    the empty-state branch and a "no hint" assertion would falsely
 *    pass even on the buggy code.
 * 2. The same render still emits the server tile for the seeded row
 *    (`data-testid="server-tile"`), proving the assertion above isn't
 *    a false negative caused by a render failure that emitted no
 *    output at all.
 * 3. The `Sbpp\View\ServersView` constructor no longer accepts the
 *    `IN_SERVERS_PAGE` or `access_bans` named parameters. The View is
 *    the production source of truth for the template's variable
 *    surface; if a future contributor re-adds the props to support
 *    a new feature, this assertion fires first and they have to
 *    update the test deliberately. SmartyTemplateRule (PHPStan) is
 *    the static gate that catches the inverse direction (re-adding
 *    the hint markup without the props), but the static gate doesn't
 *    run in this test suite, so this case stands alone.
 *
 * Mirrors the per-page integration shape from
 * `AdminAdminsSearchTest::renderAdminsPage()` (real Smarty +
 * `ob_start` capture against the production page handler).
 */
final class ServerListHintRegressionTest extends ApiTestCase
{
    private int $seededSid = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        $this->seedSingleServer();
        $this->bootstrapSmartyTheme();
    }

    protected function tearDown(): void
    {
        $_GET = [];
        unset($GLOBALS['theme']);
        parent::tearDown();
    }

    /**
     * #1306 headline assertion: render `?p=servers` as an admin who
     * holds `ADMIN_OWNER | ADMIN_ADD_BAN` (the gating flags the
     * pre-fix hint conditional asked for) with at least one server
     * configured. The seeded server tile must render, but the
     * misleading right-click hint must NOT.
     */
    public function testServersPageDoesNotRenderRightClickHint(): void
    {
        $_GET = ['p' => 'servers'];

        $html = $this->renderServersPage();

        $this->assertStringContainsString(
            'data-testid="server-tile"',
            $html,
            'The seeded server row must render — otherwise the hint absence below could be a false negative caused by an empty render.',
        );

        $this->assertStringNotContainsString(
            'data-testid="servers-rcon-hint"',
            $html,
            '#1306: the misleading "Right-click a player on an expanded card to kick, ban, or message them" hint must not render — the right-click context menu the hint described was removed at #1123 D1 and the supporting `api_servers_host_players` response does not carry the SteamIDs the menu would need.',
        );
        $this->assertStringNotContainsString(
            'Right-click a player on an expanded card',
            $html,
            'Belt-and-braces text-level guard against a future contributor re-adding the hint copy under a different testid.',
        );
    }

    /**
     * Constructor-shape guard. `Sbpp\View\ServersView` no longer
     * accepts `IN_SERVERS_PAGE` or `access_bans` named parameters
     * — both were only consumed by the hint's `{if …}` conditional
     * in `page_servers.tpl` and PHP 8.1's "Unknown named parameter"
     * error would surface a re-add at runtime. Lock the contract
     * here so a future refactor either re-introduces the props
     * deliberately AND updates this test, or stays clear.
     */
    public function testServersViewDoesNotAcceptHintProps(): void
    {
        $reflection = new ReflectionClass(\Sbpp\View\ServersView::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor, 'ServersView must declare a constructor.');

        $paramNames = array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            $constructor->getParameters(),
        );

        $this->assertNotContains('IN_SERVERS_PAGE', $paramNames,
            'IN_SERVERS_PAGE was only consumed by the #1306 hint; re-adding it without re-adding the hint risks orphaning the prop.');
        $this->assertNotContains('access_bans', $paramNames,
            'access_bans was only consumed by the #1306 hint; re-adding it without re-adding the hint risks orphaning the prop.');
    }

    private function seedSingleServer(): void
    {
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_servers`
                (sid, ip, port, rcon, modid, enabled)
             VALUES (?, ?, ?, ?, ?, ?)',
            DB_PREFIX,
        ))->execute([1, '127.0.0.1', 27015, '', 0, 1]);
        $this->seededSid = 1;
    }

    /**
     * Boot a Smarty `$theme` matching `init.php`'s wiring closely
     * enough that `page.servers.php` -> `Renderer::render` ->
     * `display('page_servers.tpl')` produces real HTML. Mirrors
     * `AdminAdminsSearchTest::bootstrapSmartyTheme()`.
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
     * Run `page.servers.php` in-process with the production globals
     * wired and capture the rendered HTML.
     */
    private function renderServersPage(): string
    {
        ob_start();
        try {
            (function (): void {
                global $userbank, $theme;
                $userbank = $GLOBALS['userbank'];
                $theme    = $GLOBALS['theme'];
                require ROOT . 'pages/page.servers.php';
            })();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }
}
