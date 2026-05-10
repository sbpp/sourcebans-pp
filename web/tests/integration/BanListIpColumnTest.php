<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\View\BanListView;
use Sbpp\View\Renderer;
use Smarty\Smarty;

/**
 * Issue #1302: the public ban list dropped the IP column for admins
 * during the v2.0 redesign of `web/themes/default/page_bans.tpl`.
 *
 * v1.x rendered an IP column gated on `is_admin()` (so admins always
 * saw IPs, non-admins saw them only when `banlist.hideplayerips` was
 * off). The v2.0 template never references the per-row `ban_ip_raw`
 * field, so even an `ADMIN_OWNER` user got no IP at-a-glance on the
 * marquee page.
 *
 * The fix restores the column gated on `{if !$hideplayerips}` —
 * mirroring the existing `{if !$hideadminname}` guard for the Admin
 * column. The `BanListView` DTO already carried both `hideplayerips`
 * (built as `Config::getBool('banlist.hideplayerips') &&
 * !$userbank->is_admin()` in `web/pages/page.banlist.php`) and the
 * per-row `ban_ip_raw` field, so this is a template-only fix.
 *
 * The test renders `BanListView` end-to-end through Smarty (matching
 * `init.php`'s wiring) and asserts on the rendered HTML in both
 * directions:
 *
 *   1. `hideplayerips = false` (admin caller, OR non-admin with the
 *      setting off) — the IP column appears in the desktop `<thead>`
 *      AND each row's `[data-testid="ban-ip"]` cell carries the raw
 *      IP. The mobile `.ban-cards` block also surfaces the IP under
 *      the SteamID line via `[data-testid="ban-ip-mobile"]`.
 *   2. `hideplayerips = true` (non-admin caller under default
 *      `banlist.hideplayerips=1`) — neither the column header nor
 *      the per-row testid is present anywhere in the output. The
 *      suppression is total; we deliberately don't render an "IP
 *      redacted" placeholder, matching how `{if !$hideadminname}`
 *      fully omits the Admin column.
 *
 * Mirrors the `AdminAdminsSearchTest::bootstrapSmartyTheme` shape
 * (init.php-equivalent Smarty wiring + per-process tmp compile dir
 * so PHPUnit's host-user shell can write its compiled `.tpl.php`
 * artefacts into something other than the docker-image-owned
 * `web/cache/`).
 */
final class BanListIpColumnTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapSmartyTheme();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['theme']);
        parent::tearDown();
    }

    /**
     * Admin / setting-off path: the `BanListView::$hideplayerips`
     * boolean is `false`, so the template emits the IP column
     * (header + per-row cell) on the desktop table AND a per-row IP
     * line on the mobile card.
     */
    public function testIpColumnRendersWhenHideplayeripsIsFalse(): void
    {
        $html = $this->renderBanList(hideplayerips: false);

        // Desktop column header. Anchor on the `col-ip` class +
        // visible "IP" copy together so a future refactor that
        // accidentally reuses the class for a different column
        // still trips this assertion.
        $this->assertStringContainsString(
            '<th scope="col" class="col-ip">IP</th>',
            $html,
            'IP <th> must render in the desktop <thead> when $hideplayerips is false (#1302).',
        );

        // Per-row IP cell — testid is the contract for E2E specs +
        // any future filter/styling work, so we anchor on it
        // directly. The seeded value is escaped through Smarty's
        // global `escape` filter so the literal "1.2.3.4" lands
        // verbatim in the output.
        $this->assertStringContainsString(
            'data-testid="ban-ip"',
            $html,
            'Per-row [data-testid="ban-ip"] cell must render when $hideplayerips is false (#1302).',
        );
        $this->assertStringContainsString(
            '1.2.3.4',
            $html,
            'The per-row ban_ip_raw value must reach the rendered table when admins/operators are allowed to see it (#1302).',
        );

        // Mobile card mirror — same gate, different testid so
        // responsive specs can address each branch independently.
        $this->assertStringContainsString(
            'data-testid="ban-ip-mobile"',
            $html,
            'The mobile .ban-cards branch must surface the IP under the SteamID line when $hideplayerips is false (#1302).',
        );
    }

    /**
     * Non-admin / setting-on path: `BanListView::$hideplayerips` is
     * `true` (`banlist.hideplayerips=1` defaults on a fresh install
     * per `data.sql`, and the page handler ANDs in `!is_admin()`).
     * The IP column is suppressed completely — no header, no per-row
     * cell, no mobile-card line. We deliberately don't render an
     * "IP redacted" placeholder so the suppression mirrors the
     * existing `{if !$hideadminname}` Admin-column omission.
     */
    public function testIpColumnIsSuppressedWhenHideplayeripsIsTrue(): void
    {
        $html = $this->renderBanList(hideplayerips: true);

        $this->assertStringNotContainsString(
            '<th scope="col" class="col-ip">IP</th>',
            $html,
            'IP <th> must NOT render when $hideplayerips is true — banlist.hideplayerips is the gate, mirroring the public-side IP suppression contract (#1302).',
        );
        $this->assertStringNotContainsString(
            'data-testid="ban-ip"',
            $html,
            'Per-row [data-testid="ban-ip"] cell must NOT render when $hideplayerips is true (#1302).',
        );
        $this->assertStringNotContainsString(
            'data-testid="ban-ip-mobile"',
            $html,
            'Mobile-card IP line must NOT render when $hideplayerips is true (#1302).',
        );
        $this->assertStringNotContainsString(
            '1.2.3.4',
            $html,
            'The seeded raw IP must not leak into the suppressed render (#1302).',
        );
    }

    /**
     * The empty-state colspan must accommodate the maximum render
     * (10 columns: Player, SteamID, IP, Reason, Server, Admin,
     * Length, Banned, Status, Actions). A future regression that
     * forgets to bump it back to 10 when adding another column
     * would silently leave the empty-state cell short, drawing
     * a wedge of background past the table-card frame on a
     * fresh install.
     */
    public function testEmptyStateColspanCovers10Columns(): void
    {
        $html = $this->renderBanList(hideplayerips: false, banList: []);

        $this->assertStringContainsString(
            'colspan="10"',
            $html,
            'Empty-state row colspan must equal the max column count (10 with IP + Admin both visible) so the empty card stretches across the full table (#1302).',
        );
        // And the empty card itself must paint — the colspan bump
        // shouldn't have broken the {foreachelse} branch that
        // wraps it.
        $this->assertStringContainsString(
            'data-testid="banlist-empty"',
            $html,
            'Empty-state container must still render — the colspan bump should not have disturbed the {foreachelse} branch (#1302).',
        );
    }

    /**
     * Build a BanListView with one synthetic ban row, render it
     * through Smarty, and return the full HTML output.
     *
     * @param list<array<string, mixed>>|null $banList Override the seeded ban list. `null` keeps the default 1-row fixture.
     */
    private function renderBanList(bool $hideplayerips, ?array $banList = null): string
    {
        $rows = $banList ?? [$this->fixtureBanRow()];

        $view = new BanListView(
            ban_list:        $rows,
            ban_nav:         '',
            total_bans:      count($rows),
            view_bans:       false,
            view_comments:   false,
            comment:         false,
            commenttype:     '',
            commenttext:     '',
            ctype:           '',
            cid:             '',
            page:            -1,
            canedit:         false,
            othercomments:   'None',
            searchlink:      '',
            hidetext:        'Hide',
            hideadminname:   false,
            hideplayerips:   $hideplayerips,
            groupban:        false,
            friendsban:      false,
            general_unban:   false,
            can_delete:      false,
            can_export:      false,
            admin_postkey:   'test-postkey',
            can_add_ban:     false,
            is_filtered:     false,
            server_list:     [],
            filters:         ['search' => '', 'server' => '', 'time' => ''],
        );

        ob_start();
        try {
            Renderer::render($GLOBALS['theme'], $view);
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    /**
     * Single ban row with the per-row keys page_bans.tpl reads.
     * Mirrors the shape page.banlist.php builds in its $bans loop —
     * just the fields the template touches plus a synthetic IP that
     * we anchor the test assertions on.
     *
     * @return array<string, mixed>
     */
    private function fixtureBanRow(): array
    {
        return [
            'bid'             => 42,
            'name'            => 'TestPlayer',
            'steam'           => 'STEAM_0:1:1302',
            'state'           => 'permanent',
            'length'          => 0,
            'length_human'    => 'Permanent',
            'banned'          => 1700000000,
            'banned_human'    => '2023-11-14 22:13',
            'banned_iso'      => '2023-11-14T22:13:20+00:00',
            'sname'           => 'Web Ban',
            'reason'          => 'aimbot',
            'aname'           => 'admin',
            'ban_ip_raw'      => '1.2.3.4',
            'can_edit_ban'    => false,
            'can_unban'       => false,
            'mod_icon'        => 'web.png',
            'country'         => '',
            'demo_available'  => false,
            'view_delete'     => false,
            'type'            => 0,
            'commentdata'     => 'None',
            'avatar_initials' => 'TE',
            'avatar_hue'      => 60,
        ];
    }

    /**
     * Spin a Smarty `$theme` matching init.php so `Renderer::render`
     * can actually display the template. Mirrors
     * `AdminAdminsSearchTest::bootstrapSmartyTheme` — the bare
     * minimum plugin set the rendered template body touches plus a
     * per-process compile dir under `sys_get_temp_dir()` (the
     * default `web/cache/` is owned by root inside the docker image
     * and tests run as the host user).
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
}
