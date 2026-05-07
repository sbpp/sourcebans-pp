<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Theme;
use Smarty\Smarty;

/**
 * Issue #1270 — `web/pages/page.admin.php` was paying the cost of a
 * 9-subquery composite COUNT (over `:prefix_banlog`,
 * `:prefix_bans`, `:prefix_comms`, `:prefix_admins`,
 * `:prefix_submissions`, `:prefix_protests`, `:prefix_servers`)
 * plus a recursive {@see \getDirSize()} walk over `web/demos/` on
 * every admin-landing render — even though the v2.0.0 default
 * theme (#1146) never displays the values. The matching DTO fields
 * on {@see \Sbpp\View\AdminHomeView} (`$total_*`, `$archived_*`,
 * `$demosize`) live behind an unreachable `{if false}` parity block
 * in `page_admin.tpl` that exists only so `SmartyTemplateRule`'s
 * "every assigned property is referenced somewhere in the template
 * tree" check stays green for the default PHPStan leg.
 *
 * The fix gates the compute behind {@see Theme::wantsLegacyAdminCounts()}.
 * Default-theme installs skip the work; theme forks that DO render
 * the legacy fields opt back in by adding
 *
 *     define('theme_legacy_admin_counts', true);
 *
 * to their `theme.conf.php`.
 *
 * This file pins the contract that protects the perf win:
 *
 * 1. **Predicate** — {@see Theme::wantsLegacyAdminCounts()} returns
 *    false when the per-theme constant is undefined (the shipped
 *    default), true when a fork defines it. Pure unit test, no DB.
 * 2. **Default path skips the work** — including `page.admin.php`
 *    in default-theme mode leaves
 *    {@see Theme::legacyComputeCount()} at zero AND assigns the
 *    legacy DTO fields to placeholder zeros / `'0 B'` (so the
 *    `{if false}` parity block still renders harmlessly). Locks
 *    that the COUNT query + getDirSize() are NOT invoked.
 * 3. **Fork opt-in re-enables the work** — when a fork's
 *    `theme.conf.php` would have defined the constant, the same
 *    page handler runs the COUNT + getDirSize and bumps the
 *    counter. Run in a separate process because PHP `define()`
 *    is permanent within a process and would leak into other
 *    cases otherwise.
 *
 * Pairs with `web/pages/page.admin.php`'s gating block + the
 * `Sbpp\Theme` class. If a future contributor reverts either side
 * of the gate (`if (Sbpp\Theme::wantsLegacyAdminCounts()) { … }`)
 * back to the unconditional compute, this test trips before users
 * regress to the pre-#1270 latency.
 */
final class AdminHomePerformanceTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();
        Theme::resetLegacyComputeCount();
    }

    protected function tearDown(): void
    {
        $_GET = [];
        unset($GLOBALS['theme']);
        Theme::resetLegacyComputeCount();
        parent::tearDown();
    }

    /**
     * Default theme: the per-theme opt-in constant is undefined, so
     * the gate keeps the legacy compute off the request path.
     */
    public function testWantsLegacyAdminCountsDefaultsToFalse(): void
    {
        $this->assertFalse(
            defined(Theme::LEGACY_ADMIN_COUNTS_CONSTANT),
            'The shipped default theme must not define ' . Theme::LEGACY_ADMIN_COUNTS_CONSTANT
                . ' — that constant is the third-party-fork opt-in.'
        );
        $this->assertFalse(
            Theme::wantsLegacyAdminCounts(),
            'Without the per-theme constant, the predicate must be false.'
        );
    }

    /**
     * The acceptance-criteria regression guard: rendering
     * `?p=admin` against the default theme must NOT invoke the
     * 9-COUNT subquery or the recursive getDirSize() walk.
     *
     * The counter is bumped by `Theme::recordLegacyComputePass()`
     * inside the gated branch in `page.admin.php`, so a non-zero
     * reading after the include means the slow path fired.
     *
     * We also assert the View's legacy DTO fields are populated
     * with the placeholder values the gate's else-branch assigns —
     * any drift to "real" numbers under default theme would mean
     * the gate was bypassed.
     */
    public function testDefaultThemeAdminHomeSkipsLegacyComputeAndRendersGrid(): void
    {
        $this->assertSame(0, Theme::legacyComputeCount(), 'pre-render counter');

        $html = $this->renderAdminPage();

        $this->assertSame(
            0,
            Theme::legacyComputeCount(),
            'page.admin.php must NOT take the legacy compute branch on the default theme '
                . '(no COUNT subquery + no getDirSize walk). The 8-card grid does not display '
                . 'these values; computing them is wasted work (#1270).'
        );

        // The 8-card grid still rendered. Owner has every flag, so
        // every can_<area> is true and every card is in the DOM.
        $this->assertStringContainsString('class="admin-cards"', $html);
        $this->assertStringContainsString('data-testid="admin-card-admins"',  $html);
        $this->assertStringContainsString('data-testid="admin-card-bans"',    $html);
        $this->assertStringContainsString('data-testid="admin-card-servers"', $html);

        // Smarty assigns are introspectable — pin the placeholder values
        // the else-branch writes so the `{if false}` parity reference
        // sees zeros / '0 B' rather than real numbers (which would be
        // proof the COUNT/getDirSize ran).
        $tpl = $GLOBALS['theme'];
        $this->assertSame(0,    $tpl->getTemplateVars('total_bans'),         'total_bans placeholder');
        $this->assertSame(0,    $tpl->getTemplateVars('total_blocks'),       'total_blocks placeholder');
        $this->assertSame(0,    $tpl->getTemplateVars('total_admins'),       'total_admins placeholder');
        $this->assertSame(0,    $tpl->getTemplateVars('total_comms'),        'total_comms placeholder');
        $this->assertSame(0,    $tpl->getTemplateVars('total_servers'),      'total_servers placeholder');
        $this->assertSame(0,    $tpl->getTemplateVars('total_protests'),     'total_protests placeholder');
        $this->assertSame(0,    $tpl->getTemplateVars('total_submissions'),  'total_submissions placeholder');
        $this->assertSame(0,    $tpl->getTemplateVars('archived_protests'),  'archived_protests placeholder');
        $this->assertSame(0,    $tpl->getTemplateVars('archived_submissions'), 'archived_submissions placeholder');
        $this->assertSame('0 B', $tpl->getTemplateVars('demosize'),          'demosize placeholder');
    }

    /**
     * The fork escape hatch: when a third-party theme's `theme.conf.php`
     * defines `theme_legacy_admin_counts`, the same page handler runs
     * the COUNT + getDirSize and bumps the counter. The values go on
     * the View DTO and the fork's template renders them.
     *
     * Runs in a separate process because PHP `define()` is permanent
     * within a process and the constant would leak into the
     * default-path test cases above otherwise.
     */
    #[RunInSeparateProcess]
    public function testForkOptInRunsLegacyComputeBranch(): void
    {
        define(Theme::LEGACY_ADMIN_COUNTS_CONSTANT, true);
        $this->assertTrue(
            Theme::wantsLegacyAdminCounts(),
            'A fork that defines the per-theme constant must opt in.'
        );

        $html = $this->renderAdminPage();

        $this->assertSame(
            1,
            Theme::legacyComputeCount(),
            'page.admin.php must take the legacy compute branch exactly once when a fork '
                . 'opts in — anything else means the gate broke or fired twice per request.'
        );

        // Counter alone could lie if the branch fired but didn't
        // actually run the COUNT. Pin one of the assigned values:
        // the seeded admin row makes total_admins >= 1 (the
        // CONSOLE row excluded by `WHERE aid > 0`, the `admin`
        // user counted).
        $tpl = $GLOBALS['theme'];
        $this->assertGreaterThanOrEqual(
            1,
            (int) $tpl->getTemplateVars('total_admins'),
            'fork mode must populate total_admins from the actual COUNT query'
        );
        $this->assertStringContainsString('class="admin-cards"', $html, 'page still renders');
    }

    /**
     * Spin a Smarty `$theme` matching `init.php` so `page.admin.php`
     * doesn't crash on `Renderer::render`. Mirrors the helper in
     * AdminAdminsSearchTest; kept inline so this test file is
     * self-contained and the helper there can stay private.
     */
    private function bootstrapSmartyTheme(): void
    {
        require_once INCLUDES_PATH . '/SmartyCustomFunctions.php';
        require_once INCLUDES_PATH . '/View/View.php';
        require_once INCLUDES_PATH . '/View/Renderer.php';

        // Process-private compile/cache dir — the docker image's
        // `web/cache/` is owned by root and PHPUnit runs as the host
        // user.
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
     * Drive `web/pages/page.admin.php` end-to-end and capture the
     * rendered HTML. Mirrors `web/index.php`'s include semantics so
     * the global `$userbank` / `$theme` the handler reaches into
     * match a real request.
     */
    private function renderAdminPage(): string
    {
        ob_start();
        try {
            (function (): void {
                global $userbank, $theme;
                $userbank = $GLOBALS['userbank'];
                $theme    = $GLOBALS['theme'];
                require ROOT . 'pages/page.admin.php';
            })();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }
}
