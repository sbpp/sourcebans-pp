<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * #1315 — public banlist / commslist v1.x → v2.0 regressions.
 *
 * Three surfaces regressed when the v2.0 redesign collapsed the
 * sourcebans.js-driven legacy banlist / commslist into the new
 * Smarty-only `page_bans.tpl` / `page_comms.tpl`:
 *
 *  1. **Unban reason / removed-by line hidden on lifted rows.** The
 *     v1.x list rendered "Unbanned by <admin>: <reason>" inline below
 *     the truncated reason cell so admins / players could see who
 *     lifted a ban without opening the drawer (banlist) or hunting
 *     for a non-existent drawer (commslist — there is no drawer
 *     fallback). The v2.0 templates dropped the inline render.
 *  2. **Re-apply / Reban affordance gone from the banlist.** The v1.x
 *     `reban_link` (a `?p=admin&c=bans&section=add-ban&rebanid=…`
 *     deep link that the smart-default block pre-populates with the
 *     original ban's parameters) was wired into `$ban.reban_link` by
 *     `page.banlist.php` but never rendered by `page_bans.tpl`'s
 *     row-actions cell. The commslist already shipped a Re-apply
 *     anchor; this test ensures the banlist matches.
 *  3. **Advanced filter form UI gone.** Both lists used to render the
 *     legacy multi-criterion advanced-search form (nickname / SteamID
 *     / IP / reason / date range / length op / ban type / admin /
 *     server / comment) above the row table. The v2.0 redesign left
 *     only the simple inline filter bar; power users had to URL-spelunk
 *     to reach the advanced filters via the legacy
 *     `?advSearch=…&advType=…` shim. This test pins the new
 *     `<details class="filters-details">` disclosure shape (defaulted
 *     closed; auto-opens on a post-submit paint).
 *
 * The test bootstraps a Smarty `$theme` matching `init.php`'s wiring
 * and renders the page handlers in-process. Output is captured via
 * `ob_start` so the templates' direct echos land in the assertion
 * surface, not on PHPUnit's stdout. Mirrors the AdminAdminsSearchTest
 * pattern.
 *
 * Each test method runs in a separate process because the page handlers
 * declare top-level helper functions (e.g. `setPostKey()`) that PHP
 * can't redeclare in the same process. Mirrors `Php82DeprecationsTest`'s
 * isolation contract.
 */
final class PublicBanListRegressionTest extends ApiTestCase
{
    /** @var int bid of the seeded permanent active ban. */
    private int $activeBid = 0;

    /** @var int bid of the seeded admin-unbanned ban (RemoveType='U'). */
    private int $unbannedBid = 0;

    /** @var int bid of the seeded expired ban (length elapsed; RemoveType NULL). */
    private int $expiredBid = 0;

    /** @var int cid of the seeded permanent active mute. */
    private int $activeCid = 0;

    /** @var int cid of the seeded admin-unmuted block (RemoveType='U'). */
    private int $unmutedCid = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        $this->seedBansAndComms();
        $this->bootstrapSmartyTheme();
    }

    protected function tearDown(): void
    {
        $_GET     = [];
        $_SESSION = [];
        unset($GLOBALS['theme']);
        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistAdvancedSearchDisclosureDefaultsClosed(): void
    {
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();

        $this->assertStringContainsString(
            'data-testid="banlist-advsearch-disclosure"',
            $html,
            'disclosure must render so power users can reach the advanced form',
        );
        $this->assertMatchesRegularExpression(
            '/<details[^>]+data-testid="banlist-advsearch-disclosure"(?![^>]*\bopen\b)[^>]*>/',
            $html,
            'bare ?p=banlist must render the disclosure WITHOUT [open] so the unfiltered list reaches above the fold',
        );
        $this->assertStringNotContainsString(
            'data-testid="banlist-advsearch-active"',
            $html,
            'count badge must not render when no advanced filter is active',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistAdvancedSearchDisclosureAutoOpensOnSubmit(): void
    {
        $_GET = [
            'p'         => 'banlist',
            'advType'   => 'name',
            'advSearch' => 'Cheater',
        ];

        $html = $this->renderBanlistPage();

        $this->assertMatchesRegularExpression(
            '/<details[^>]+data-testid="banlist-advsearch-disclosure"[^>]*\bopen\b[^>]*>/',
            $html,
            'post-submit paint must render <details open> so the form chrome stays visible',
        );
        $this->assertStringContainsString(
            'data-testid="banlist-advsearch-active"',
            $html,
            'count badge must render to communicate that a filter is active',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistRendersUnbanMetaOnUnbannedRow(): void
    {
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();

        $this->assertStringContainsString(
            'data-testid="ban-unban-meta"',
            $html,
            'desktop branch must render the inline unban-meta line on the seeded unbanned row',
        );
        $this->assertStringContainsString(
            'data-testid="ban-unban-meta-mobile"',
            $html,
            'mobile-card branch must render the inline unban-meta line on the seeded unbanned row',
        );
        $this->assertStringContainsString(
            'witness was a teammate',
            $html,
            'the seeded ureason text must surface on the rendered row',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistRendersReapplyAffordanceOnExpiredAndUnbannedRows(): void
    {
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();

        $this->assertStringContainsString(
            'data-testid="row-action-reapply"',
            $html,
            'desktop row-actions cell must include a Re-apply anchor for expired/unbanned rows',
        );
        // Two seeded rows are eligible (expired + unbanned); both
        // emit the anchor exactly once, so the testid count is
        // at least two.
        $reapplyMatchCount = substr_count($html, 'data-testid="row-action-reapply"');
        $this->assertGreaterThanOrEqual(
            2,
            $reapplyMatchCount,
            'one Re-apply anchor per eligible row (expired + unbanned)',
        );
        // Active rows must NOT carry the anchor — gated to the
        // expired/unbanned branches only. Three seeded rows total;
        // only two are eligible.
        $this->assertLessThan(
            3,
            $reapplyMatchCount,
            'Re-apply anchor must NOT render on the active row (we seeded 1 active + 1 expired + 1 unbanned)',
        );
        // Anchor must point at the smart-default reban URL.
        $this->assertMatchesRegularExpression(
            '#href="index\.php\?p=admin&amp;c=bans&amp;section=add-ban&amp;rebanid=\d+#',
            $html,
            'Re-apply anchor must deep-link the admin add-ban form with the original bid',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCommslistAdvancedSearchDisclosureDefaultsClosed(): void
    {
        $_GET = ['p' => 'commslist'];

        $html = $this->renderCommslistPage();

        $this->assertStringContainsString(
            'data-testid="commslist-advsearch-disclosure"',
            $html,
            'commslist disclosure must render — higher priority than banlist (no drawer fallback)',
        );
        $this->assertMatchesRegularExpression(
            '/<details[^>]+data-testid="commslist-advsearch-disclosure"(?![^>]*\bopen\b)[^>]*>/',
            $html,
            'bare ?p=commslist must render the disclosure WITHOUT [open]',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCommslistAdvancedSearchDisclosureAutoOpensOnSubmit(): void
    {
        $_GET = [
            'p'         => 'commslist',
            'advType'   => 'name',
            'advSearch' => 'Spammer',
        ];

        $html = $this->renderCommslistPage();

        $this->assertMatchesRegularExpression(
            '/<details[^>]+data-testid="commslist-advsearch-disclosure"[^>]*\bopen\b[^>]*>/',
            $html,
            'commslist post-submit paint must render <details open>',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCommslistRendersUnbanMetaOnUnmutedRow(): void
    {
        $_GET = ['p' => 'commslist'];

        $html = $this->renderCommslistPage();

        $this->assertStringContainsString(
            'data-testid="comm-unban-meta"',
            $html,
            'commslist desktop must render the inline lift-by line on unmuted rows (priority — no drawer fallback)',
        );
        $this->assertStringContainsString(
            'data-testid="comm-unban-meta-mobile"',
            $html,
            'commslist mobile must mirror the desktop lift-by line',
        );
        $this->assertStringContainsString(
            'spam was over the line',
            $html,
            'the seeded ureason text must surface on the rendered comm row',
        );
    }

    private function seedBansAndComms(): void
    {
        $pdo  = Fixture::rawPdo();
        $now  = time();
        $aid  = Fixture::adminAid();
        $hour = 3600;

        // Seed three bans:
        //   1) active permanent ban — emits no Re-apply, no unban-meta
        //   2) admin-unbanned ban — emits unban-meta + Re-apply
        //   3) expired ban (length elapsed) — emits Re-apply, no unban-meta
        $insert = $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (type, ip, authid, name, created, ends, length, reason, ureason, aid, RemovedBy, RemovedOn, RemoveType)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX,
        ));

        $insert->execute([
            0,
            'STEAM_0:1:50001',
            'CheaterAlpha',
            $now - $hour,
            0,
            0,
            'aimbot',
            null,
            $aid,
            null,
            null,
            null,
        ]);
        $this->activeBid = (int) $pdo->lastInsertId();

        $insert->execute([
            0,
            'STEAM_0:1:50002',
            'CheaterBeta',
            $now - 7 * 24 * $hour,
            $now - 24 * $hour,
            6 * 24 * $hour,
            'wallhack',
            'witness was a teammate',
            $aid,
            $aid,
            $now - 12 * $hour,
            'U',
        ]);
        $this->unbannedBid = (int) $pdo->lastInsertId();

        $insert->execute([
            0,
            'STEAM_0:1:50003',
            'CheaterGamma',
            $now - 7 * 24 * $hour,
            $now - 24 * $hour,
            6 * 24 * $hour,
            'spawn camping',
            null,
            $aid,
            null,
            null,
            null,
        ]);
        $this->expiredBid = (int) $pdo->lastInsertId();

        // Seed two comm rows: one active, one admin-unmuted.
        $insertComm = $pdo->prepare(sprintf(
            'INSERT INTO `%s_comms` (type, authid, name, created, ends, length, reason, ureason, aid, RemovedBy, RemovedOn, RemoveType)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX,
        ));

        $insertComm->execute([
            1,
            'STEAM_0:1:60001',
            'SpammerAlpha',
            $now - $hour,
            0,
            0,
            'spam',
            null,
            $aid,
            null,
            null,
            null,
        ]);
        $this->activeCid = (int) $pdo->lastInsertId();

        $insertComm->execute([
            1,
            'STEAM_0:1:60002',
            'SpammerBeta',
            $now - 7 * 24 * $hour,
            $now - 24 * $hour,
            6 * 24 * $hour,
            'spam',
            'spam was over the line',
            $aid,
            $aid,
            $now - 12 * $hour,
            'U',
        ]);
        $this->unmutedCid = (int) $pdo->lastInsertId();
    }

    private function bootstrapSmartyTheme(): void
    {
        require_once INCLUDES_PATH . '/SmartyCustomFunctions.php';
        require_once INCLUDES_PATH . '/View/View.php';
        require_once INCLUDES_PATH . '/View/Renderer.php';

        // Per-process compile dir — `web/cache/` is owned by root in
        // the docker image; tests run as the host user.
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

    private function renderBanlistPage(): string
    {
        ob_start();
        try {
            (function (): void {
                global $userbank, $theme;
                $userbank = $GLOBALS['userbank'];
                $theme    = $GLOBALS['theme'];
                require ROOT . 'pages/page.banlist.php';
            })();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    private function renderCommslistPage(): string
    {
        ob_start();
        try {
            (function (): void {
                global $userbank, $theme;
                $userbank = $GLOBALS['userbank'];
                $theme    = $GLOBALS['theme'];
                require ROOT . 'pages/page.commslist.php';
            })();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }
}
