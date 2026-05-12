<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use ReflectionClass;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * Player context-menu restoration — supersedes the #1306 hint-removal
 * contract.
 *
 * Pre-v2.0.0 the public servers page (`?p=servers`) rendered a help
 * hint ("Right-click a player on an expanded card to kick, ban, or
 * message them.") promising a right-click context menu on player
 * rows. That menu's supporting JS lived in
 * `web/scripts/sourcebans.js`'s `LoadServerHost(...)` helper which
 * was deleted at #1123 D1, and the v2.0.0 `page_servers.tpl` rewrite
 * never re-registered it. Issue #1306 dropped the misleading hint
 * + the dead context-menu helpers (`web/scripts/contextMenoo.js`,
 * `sb.contextMenu`, the legacy global `AddContextMenu`) and locked
 * the no-hint contract here. The pre-restoration test pinned three
 * invariants:
 *
 *   1. The rendered HTML for `?p=servers` did NOT contain
 *      `data-testid="servers-rcon-hint"`.
 *   2. The server tile DID render (proving the assertion above
 *      wasn't a false negative caused by an empty render).
 *   3. `Sbpp\View\ServersView` did not accept `IN_SERVERS_PAGE` or
 *      `access_bans` named parameters (they were only consumed by
 *      the hint's `{if …}` conditional in `page_servers.tpl`).
 *
 * This PR restores the feature under a NEW contract documented in
 * AGENTS.md ("Anti-patterns") and at the top of
 * `web/scripts/server-context-menu.js`:
 *
 *   - The menu is built from scratch against the current
 *     event-delegate pattern — single
 *     `document.addEventListener('contextmenu', …)` filtered by
 *     `closest('[data-context-menu="server-player"]')`. The legacy
 *     MooTools-era `sb.contextMenu` / `AddContextMenu` /
 *     `contextMenoo.js` helpers stay deleted; reintroducing them
 *     remains an anti-pattern.
 *   - The SteamIDs the menu reads come from a NEW extension to
 *     `api_servers_host_players` that pairs the A2S response with
 *     a cached RCON `status` round-trip (`Sbpp\Servers\RconStatusCache`).
 *     The handler is the load-bearing gate; anonymous and
 *     partial-permission callers never receive SteamIDs.
 *   - The admin hint copy returns, gated on
 *     `can_use_context_menu` (the `can_add_ban` slot of
 *     `Sbpp\View\Perms::for($userbank)`). Anonymous viewers see
 *     no hint. Pre-restoration this test pinned `assertStringNotContainsString`
 *     for the same testid; the assertions are inverted here on
 *     purpose.
 *
 * The class name stays the same so `git log -- ServerListHintRegressionTest`
 * surfaces both the original #1306 lock and this restoration in
 * one place.
 */
final class ServerListHintRegressionTest extends ApiTestCase
{
    private int $seededSid = 0;

    protected function setUp(): void
    {
        parent::setUp();
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
     * An admin with `ADMIN_OWNER | ADMIN_ADD_BAN` (the gating
     * permission for the restored context menu) MUST see the hint
     * copy AND the server tile. The pre-restoration test pinned the
     * opposite — the hint MUST NOT render. The post-restoration
     * contract inverts both assertions; see the class docblock for
     * the lineage.
     */
    public function testAdminSeesContextMenuHintWhenServerConfigured(): void
    {
        $this->loginAsAdmin();
        $_GET = ['p' => 'servers'];

        $html = $this->renderServersPage();

        $this->assertStringContainsString(
            'data-testid="server-tile"',
            $html,
            'The seeded server row must render — otherwise the hint presence assertion below could be a false positive caused by a render with stale chrome and no body.',
        );

        $this->assertStringContainsString(
            'data-testid="servers-rcon-hint"',
            $html,
            'The right-click context menu hint must render for admins with ADMIN_OWNER | ADMIN_ADD_BAN — restored after #1306 superseded the no-hint contract.',
        );
        $this->assertStringContainsString(
            'Right-click a player',
            $html,
            'Belt-and-braces text-level assertion: the visible copy describes the feature the menu now ships.',
        );

        // Paired script include — restoration ships both halves
        // together (so a fork that strips the JS in a future branch
        // also drops the hint and the chrome stays self-consistent).
        $this->assertStringContainsString(
            'server-context-menu.js',
            $html,
            'The page must include server-context-menu.js for admin viewers — the menu is the load-bearing affordance the hint describes.',
        );
    }

    /**
     * Anonymous viewers (logged-out) MUST NOT see the hint copy or
     * the JS include — the SteamID side-channel `api_servers_host_players`
     * surfaces is server-side gated on the same permission, so a
     * logged-out caller can't use the menu anyway. Same shape as
     * #1304's filtered chrome navigation principle.
     */
    public function testAnonymousDoesNotSeeContextMenuHint(): void
    {
        // No login — bootstrapped with anonymous $userbank.
        $_GET = ['p' => 'servers'];

        $html = $this->renderServersPage();

        $this->assertStringNotContainsString(
            'data-testid="servers-rcon-hint"',
            $html,
            'Anonymous viewers must NOT see the hint copy — they have no path to use the menu.',
        );
        $this->assertStringNotContainsString(
            'server-context-menu.js',
            $html,
            'Anonymous viewers must NOT load the context-menu JS — loading would be a wasted byte and a no-op since the JSON handler refuses SteamIDs to them.',
        );
    }

    /**
     * Constructor-shape guard. `Sbpp\View\ServersView` accepts a
     * `can_use_context_menu` named parameter that the pre-#1306
     * `IN_SERVERS_PAGE` / `access_bans` props used to gate. The
     * new prop is a single bool splatted from
     * `Sbpp\View\Perms::for($userbank)`'s `can_add_ban` slot, and
     * SmartyTemplateRule cross-checks the property against the
     * template's `{if $can_use_context_menu}` conditional.
     */
    public function testServersViewAcceptsContextMenuProp(): void
    {
        $reflection = new ReflectionClass(\Sbpp\View\ServersView::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor, 'ServersView must declare a constructor.');

        $paramNames = array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            $constructor->getParameters(),
        );

        $this->assertContains('can_use_context_menu', $paramNames,
            'ServersView must accept `can_use_context_menu` so page.servers.php can gate the hint + JS include on the caller\'s permission.');

        // The pre-restoration props stay gone — they were specific
        // to the hint's `{if (IN_SERVERS_PAGE && $access_bans)}`
        // conditional, which the post-restoration template replaces
        // with a simpler `{if $can_use_context_menu}` guard.
        $this->assertNotContains('IN_SERVERS_PAGE', $paramNames,
            'IN_SERVERS_PAGE belonged to the pre-#1306 hint shape; the new gate uses `can_use_context_menu`.');
        $this->assertNotContains('access_bans', $paramNames,
            'access_bans belonged to the pre-#1306 hint shape; the new gate uses `can_use_context_menu`.');
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
