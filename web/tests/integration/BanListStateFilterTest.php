<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * #1352 — server-side state filter on the public banlist
 * (`?p=banlist&state=<permanent|active|expired|unbanned>`).
 *
 * Pre-#1352 the chip strip was a vanilla-JS row-hide layer
 * (`web/scripts/banlist.js applyStateFilter`) that flipped
 * `display: none` on rows whose `data-state` didn't match — only
 * operating on the rowset the server already returned. With 10k
 * bans of which 50 are unbanned, page 1 of `?state=unbanned`
 * rendered 30 invisible rows; the chip read as broken. The
 * server-side filter narrows the rowset BEFORE pagination so
 * page 1 of `?state=unbanned` is the first 30 unbanned rows.
 *
 * Five test surfaces:
 *
 *   1. Each `?state=<slug>` value narrows to its expected subset,
 *      including pre-2.0 admin-lifted bans (`RemoveType IS NULL`
 *      but `RemovedOn` + `RemovedBy > 0`) under `?state=unbanned`.
 *   2. Pre-2.0 natural-expiry rows (`RemovedOn IS NOT NULL` but
 *      `RemovedBy IS NULL OR = 0`) land under `?state=expired`,
 *      NOT `?state=unbanned`.
 *   3. Per-row state pill mirrors the SQL filter: a row pulled
 *      in by `?state=unbanned` renders with the "Unbanned" pill,
 *      not the legacy mis-classified "Active" pill that v2.0 RC
 *      shipped for pre-2.0 lifted rows.
 *   4. Chip strip renders server-side: anchors (not buttons) with
 *      `aria-pressed="true"` / `data-active="true"` on the active
 *      chip; `href` preserves other active filters.
 *   5. Hide-inactive toggle is suppressed when `?state=` is
 *      explicit (otherwise the two predicates fight over
 *      `RemoveType IS NULL`).
 *
 * Mirrors `PublicBanListRegressionTest`'s in-process Smarty +
 * page-handler render harness.
 */
final class BanListStateFilterTest extends ApiTestCase
{
    /** @var int bid: active permanent ban (RemoveType IS NULL, length=0). */
    private int $activePermBid = 0;

    /** @var int bid: active timed ban (RemoveType IS NULL, length>0, ends>now). */
    private int $activeTimedBid = 0;

    /** @var int bid: expired ban (RemoveType='E', PruneBans-shape). */
    private int $expiredEBid = 0;

    /** @var int bid: pre-2.0 natural-expiry — `length > 0`, `ends < now`, `RemoveType IS NULL`, `RemovedOn IS NULL` (PruneBans never wrote the row). */
    private int $expiredPre2Bid = 0;

    /** @var int bid: pre-2.0 PruneBans-shape — `RemoveType IS NULL`, `RemovedOn IS NOT NULL`, `RemovedBy = 0`, `length > 0`. The prune writer set `RemovedOn` but the fork it ran on didn't set `RemoveType`. */
    private int $expiredPre2PrunedBid = 0;

    /** @var int bid: admin-deleted ban (RemoveType='D'). */
    private int $deletedBid = 0;

    /** @var int bid: admin-unbanned ban (RemoveType='U'). */
    private int $unbannedBid = 0;

    /** @var int bid: pre-2.0 admin-lifted ban (RemoveType IS NULL, RemovedBy>0, ends>now). */
    private int $unbannedPre2Bid = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        $this->seedBans();
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
    public function testStateUnbannedIncludesV2RemoveTypeAndPre2AdminLift(): void
    {
        $_GET = ['p' => 'banlist', 'state' => 'unbanned'];

        $html = $this->renderBanlistPage();
        $bids = $this->extractRowBids($html);

        // RemoveType='U', RemoveType='D', and the pre-2.0 admin-lift
        // (RemoveType IS NULL but RemovedBy>0 + RemovedOn IS NOT NULL).
        $this->assertContains($this->unbannedBid, $bids,
            'state=unbanned must include RemoveType=\'U\' rows');
        $this->assertContains($this->deletedBid, $bids,
            'state=unbanned must include RemoveType=\'D\' rows (admin-deleted)');
        $this->assertContains($this->unbannedPre2Bid, $bids,
            'state=unbanned must include pre-2.0 admin-lifted rows '
            . '(RemoveType IS NULL but RemovedBy>0 + RemovedOn IS NOT NULL)');

        // Active / expired / natural-expiry rows must NOT be in this slice.
        $this->assertNotContains($this->activePermBid, $bids,
            'state=unbanned must NOT include active permanent rows');
        $this->assertNotContains($this->activeTimedBid, $bids,
            'state=unbanned must NOT include active timed rows');
        $this->assertNotContains($this->expiredEBid, $bids,
            'state=unbanned must NOT include RemoveType=\'E\' (natural expiry) rows');
        $this->assertNotContains($this->expiredPre2Bid, $bids,
            'state=unbanned must NOT include pre-2.0 natural-expiry rows '
            . '(RemoveType IS NULL but RemovedBy IS NULL — the OR-clause guard '
            . 'must distinguish lifts from natural expiry)');
        $this->assertNotContains($this->expiredPre2PrunedBid, $bids,
            'state=unbanned must NOT include pre-2.0 PruneBans-shape rows '
            . '(RemoveType IS NULL, RemovedOn IS NOT NULL, RemovedBy = 0) — '
            . 'the OR-clause guard requires `RemovedBy > 0`, distinguishing '
            . 'lifts from PruneBans natural expiry');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testStateExpiredIncludesPruneBansShapeAndPre2NaturalExpiry(): void
    {
        $_GET = ['p' => 'banlist', 'state' => 'expired'];

        $html = $this->renderBanlistPage();
        $bids = $this->extractRowBids($html);

        // Arm 1: post-migration shape (RemoveType='E').
        $this->assertContains($this->expiredEBid, $bids,
            'state=expired must include RemoveType=\'E\' rows (PruneBans shape)');
        // Arm 2: pre-2.0 natural-expiry where the prune writer never
        // touched the row — RemoveType IS NULL, RemovedOn IS NULL,
        // length > 0, ends < now. The defensive OR's second arm
        // catches these via `length>0 AND ends<now AND
        // RemovedOn IS NULL`.
        $this->assertContains($this->expiredPre2Bid, $bids,
            'state=expired must include pre-2.0 natural-expiry rows '
            . '(RemoveType IS NULL, RemovedOn IS NULL, length>0, ends<now)');
        // Arm 3: pre-2.0 PruneBans-shape — RemoveType IS NULL,
        // RemovedOn IS NOT NULL, RemovedBy = 0, length > 0. Without
        // arm 3 these rows would NEITHER match expired (because
        // arm 2 requires `RemovedOn IS NULL`) NOR unbanned (because
        // arm 2 requires `RemovedBy > 0`) — they'd silently fall
        // through the cracks until the migration runs. Symmetric
        // with the unbanned filter's defensive OR shape.
        $this->assertContains($this->expiredPre2PrunedBid, $bids,
            'state=expired must include pre-2.0 PruneBans-shape rows '
            . '(RemoveType IS NULL, RemovedOn IS NOT NULL, RemovedBy = 0, length>0) '
            . 'via arm 3 of the defensive OR — symmetric with unbanned\'s arm 2');

        // Unbanned / active rows must NOT be here.
        $this->assertNotContains($this->unbannedBid, $bids,
            'state=expired must NOT include RemoveType=\'U\' rows');
        $this->assertNotContains($this->unbannedPre2Bid, $bids,
            'state=expired must NOT include pre-2.0 admin-lifted rows');
        $this->assertNotContains($this->activePermBid, $bids,
            'state=expired must NOT include active permanent rows');
        $this->assertNotContains($this->activeTimedBid, $bids,
            'state=expired must NOT include active timed rows');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testStateActiveIncludesOnlyLiveRows(): void
    {
        $_GET = ['p' => 'banlist', 'state' => 'active'];

        $html = $this->renderBanlistPage();
        $bids = $this->extractRowBids($html);

        $this->assertContains($this->activePermBid, $bids,
            'state=active must include permanent active rows (length=0)');
        $this->assertContains($this->activeTimedBid, $bids,
            'state=active must include timed active rows (length>0, ends>now)');

        $this->assertNotContains($this->expiredEBid, $bids,
            'state=active must NOT include RemoveType=\'E\' rows');
        $this->assertNotContains($this->expiredPre2Bid, $bids,
            'state=active must NOT include pre-2.0 natural-expiry rows');
        $this->assertNotContains($this->expiredPre2PrunedBid, $bids,
            'state=active must NOT include pre-2.0 PruneBans-shape rows '
            . '(the `RemovedOn IS NULL` guard on the active predicate '
            . 'drops them out of the live-row bucket)');
        $this->assertNotContains($this->unbannedBid, $bids,
            'state=active must NOT include RemoveType=\'U\' rows');
        $this->assertNotContains($this->deletedBid, $bids,
            'state=active must NOT include RemoveType=\'D\' rows');
        $this->assertNotContains($this->unbannedPre2Bid, $bids,
            'state=active must NOT include pre-2.0 admin-lifted rows');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testStatePermanentIncludesOnlyLengthZeroLiveRows(): void
    {
        $_GET = ['p' => 'banlist', 'state' => 'permanent'];

        $html = $this->renderBanlistPage();
        $bids = $this->extractRowBids($html);

        $this->assertContains($this->activePermBid, $bids,
            'state=permanent must include permanent rows (length=0, RemoveType IS NULL)');

        $this->assertNotContains($this->activeTimedBid, $bids,
            'state=permanent must NOT include timed rows');
        $this->assertNotContains($this->unbannedBid, $bids,
            'state=permanent must NOT include unbanned rows');
        $this->assertNotContains($this->deletedBid, $bids,
            'state=permanent must NOT include deleted rows');
        $this->assertNotContains($this->expiredEBid, $bids,
            'state=permanent must NOT include expired rows');
        $this->assertNotContains($this->unbannedPre2Bid, $bids,
            'state=permanent must NOT include pre-2.0 admin-lifted rows');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testNoStateFilterIncludesEveryRow(): void
    {
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();
        $bids = $this->extractRowBids($html);

        $this->assertContains($this->activePermBid, $bids);
        $this->assertContains($this->activeTimedBid, $bids);
        $this->assertContains($this->expiredEBid, $bids);
        $this->assertContains($this->expiredPre2Bid, $bids);
        $this->assertContains($this->expiredPre2PrunedBid, $bids);
        $this->assertContains($this->unbannedBid, $bids);
        $this->assertContains($this->deletedBid, $bids);
        $this->assertContains($this->unbannedPre2Bid, $bids,
            'bare ?p=banlist must include pre-2.0 lifted rows '
            . '(no state filter applied)');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testInvalidStateFallsThroughToAll(): void
    {
        // A `?state=GARBAGE` URL must NOT silently apply some
        // partial filter or throw — the allowlist drops it back
        // to the empty string and the page renders the same
        // rowset as bare `?p=banlist`.
        $_GET = ['p' => 'banlist', 'state' => 'GARBAGE'];

        $html = $this->renderBanlistPage();
        $bids = $this->extractRowBids($html);

        $this->assertContains($this->activePermBid, $bids,
            'invalid ?state= must fall through to "All" — every row visible');
        $this->assertContains($this->unbannedBid, $bids);
        $this->assertContains($this->expiredEBid, $bids);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testActiveChipIsCurrentAndOthersAreNot(): void
    {
        $_GET = ['p' => 'banlist', 'state' => 'unbanned'];

        $html = $this->renderBanlistPage();

        // The active chip carries `aria-current="true"` AND
        // `data-active="true"` server-rendered. Pre-#1352 the
        // chip was a `<button>` with `aria-pressed="false"` until
        // JS flipped it post-load — broken on no-JS browsers and
        // visibly slow even with JS. We use `aria-current` (NOT
        // `aria-pressed`) because the new shape is `<a>`, and
        // axe rejects `aria-pressed` on anchors as the
        // `aria-allowed-attr` rule (the toggle semantics
        // `aria-pressed` implies require role=button).
        $this->assertMatchesRegularExpression(
            '/data-testid="filter-chip-unbanned"[^>]*\baria-current="true"/s',
            $html,
            'active chip must render aria-current="true" server-side',
        );
        $this->assertMatchesRegularExpression(
            '/data-testid="filter-chip-unbanned"[^>]*\bdata-active="true"/s',
            $html,
            'active chip must render data-active="true" server-side',
        );

        // Other chips MUST NOT be current.
        $this->assertMatchesRegularExpression(
            '/data-testid="filter-chip-active"[^>]*\baria-current="false"/s',
            $html,
            'inactive chips must render aria-current="false"',
        );
        $this->assertMatchesRegularExpression(
            '/data-testid="filter-chip-permanent"[^>]*\baria-current="false"/s',
            $html,
            'inactive chips must render aria-current="false"',
        );
        $this->assertMatchesRegularExpression(
            '/data-testid="filter-chip-expired"[^>]*\baria-current="false"/s',
            $html,
            'inactive chips must render aria-current="false"',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testChipsAreAnchorsThatNavigate(): void
    {
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();

        // Each chip is an `<a>` (not `<button type="button">`) so
        // a no-JS browser navigates on click. Pre-#1352 the chips
        // were buttons that JS hooked — no JS means no chip
        // function at all. The template renders attributes as
        // class → href → data-state-filter → data-testid → ..., so
        // the regex doesn't pin a specific attribute order — just
        // that BOTH `data-testid` and `href` live on the same `<a>`.
        $this->assertMatchesRegularExpression(
            '#<a\b(?=[^>]*\bdata-testid="filter-chip-unbanned")[^>]*\bhref="index\.php\?p=banlist&state=unbanned"#s',
            $html,
            'unbanned chip must be an anchor with href=...&state=unbanned',
        );
        $this->assertMatchesRegularExpression(
            '#<a\b(?=[^>]*\bdata-testid="filter-chip-all")[^>]*\bhref="index\.php\?p=banlist"\s#s',
            $html,
            'All chip must be an anchor with NO &state= param',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testChipUrlsPreserveOtherActiveFilters(): void
    {
        // searchText + a chip click together: the chip's anchor
        // must keep the searchText param so the user doesn't lose
        // their search when narrowing by state.
        $_GET = ['p' => 'banlist', 'searchText' => 'CheaterAlpha'];

        $html = $this->renderBanlistPage();

        // The `&` in the chip URL is rendered as `&amp;` for the
        // searchText prefix (Smarty auto-escape on the template
        // variable `$chip_base_link`), then a literal `&state=`
        // suffix. Match both shapes via `(?:&amp;|&)` to be flexible.
        // The chip's `href` comes BEFORE `data-testid` in the
        // template, so the regex uses lookahead to pin both
        // attributes onto the same `<a>` regardless of order.
        $this->assertMatchesRegularExpression(
            '#<a\b(?=[^>]*\bdata-testid="filter-chip-unbanned")[^>]*\bhref="index\.php\?p=banlist(?:&amp;|&)searchText=CheaterAlpha(?:&amp;|&)state=unbanned"#s',
            $html,
            'chip href must preserve the active searchText filter',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHideInactiveToggleIsSuppressedWhenStateFilterActive(): void
    {
        // The session-based "Hide inactive" predicate
        // (`RemoveType IS NULL`) is mutually exclusive with an
        // explicit `?state=expired` / `?state=unbanned` chip.
        // The handler drops the predicate from SQL when state is
        // set; the template hides the toggle so the two
        // surfaces don't visually compete.
        $_GET = ['p' => 'banlist', 'state' => 'unbanned'];

        $html = $this->renderBanlistPage();

        $this->assertStringNotContainsString(
            'data-testid="toggle-hide-inactive"',
            $html,
            'Hide inactive toggle must be suppressed when ?state= chip is active',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHideInactiveToggleIsVisibleWithoutStateFilter(): void
    {
        // Mirror: bare ?p=banlist DOES render the toggle.
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();

        $this->assertStringContainsString(
            'data-testid="toggle-hide-inactive"',
            $html,
            'Hide inactive toggle must render on bare ?p=banlist',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPre2AdminLiftRendersUnbannedPillNotActivePill(): void
    {
        // The per-row state classification in `page.banlist.php`
        // must mirror the SQL filter — a row pulled in by
        // ?state=unbanned must render with `pill--unbanned`, not
        // the legacy mis-classified `pill--active` v2.0 RC shipped
        // for pre-2.0 lifted rows. Without this parity the chip
        // would surface rows that visibly contradict the filter
        // ("you asked for unbanned, here's an Active row").
        $_GET = ['p' => 'banlist', 'state' => 'unbanned'];

        $html = $this->renderBanlistPage();

        // Find the row for the pre-2.0 lifted bid and check its
        // state. Template emits `data-state` BEFORE `data-id` on
        // the `<tr>`, so the assertion order matches the rendered
        // shape: `data-state="unbanned" ... data-id="<bid>"`.
        $this->assertMatchesRegularExpression(
            '/<tr[^>]*data-state="unbanned"[^>]*data-id="' . $this->unbannedPre2Bid . '"/s',
            $html,
            'pre-2.0 lifted row must render with data-state="unbanned" '
            . '(per-row state classifier mirrors the SQL filter)',
        );
        // The pill itself must use `pill--unbanned`, not `pill--active`.
        // The pill is downstream of the `<tr>` opener; match across the
        // intervening cells with `.*?` (non-greedy) bounded by the next
        // `</tr>` so we don't accidentally cross row boundaries.
        $this->assertMatchesRegularExpression(
            '#<tr[^>]*data-id="' . $this->unbannedPre2Bid . '"[^>]*>(?:(?!</tr>).)*<span class="pill pill--unbanned">#s',
            $html,
            'pre-2.0 lifted row must show the Unbanned pill, not the Active pill',
        );
    }

    private function seedBans(): void
    {
        $pdo  = Fixture::rawPdo();
        $now  = time();
        $aid  = Fixture::adminAid();
        $hour = 3600;

        $insert = $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (type, ip, authid, name, created, ends, length, reason, ureason, aid, RemovedBy, RemovedOn, RemoveType)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX,
        ));

        // 1) Active permanent (length=0, RemoveType IS NULL).
        $insert->execute([
            0, 'STEAM_0:1:90001', 'ActivePerm',
            $now - $hour, 0, 0, 'aimbot', null,
            $aid, null, null, null,
        ]);
        $this->activePermBid = (int) $pdo->lastInsertId();

        // 2) Active timed (length>0, ends>now, RemoveType IS NULL).
        $insert->execute([
            0, 'STEAM_0:1:90002', 'ActiveTimed',
            $now - $hour, $now + 7 * 24 * $hour, 7 * 24 * $hour,
            'wallhack', null, $aid, null, null, null,
        ]);
        $this->activeTimedBid = (int) $pdo->lastInsertId();

        // 3) Expired (RemoveType='E', PruneBans shape).
        $insert->execute([
            0, 'STEAM_0:1:90003', 'ExpiredE',
            $now - 14 * 24 * $hour, $now - 7 * 24 * $hour, 6 * 24 * $hour,
            'spawn camping', null, $aid, 0, $now - 7 * 24 * $hour, 'E',
        ]);
        $this->expiredEBid = (int) $pdo->lastInsertId();

        // 4) Pre-2.0 natural-expiry — RemoveType IS NULL, RemovedBy
        //    IS NULL, RemovedOn IS NULL, length>0, ends<now. Pre-475
        //    installs that didn't have the column never wrote it; the
        //    panel infers expiry from the timestamps. The `expired`
        //    SQL fragment's arm 2 catches this via
        //    `length>0 AND ends<now AND RemovedOn IS NULL`.
        $insert->execute([
            0, 'STEAM_0:1:90004', 'ExpiredPre2',
            $now - 30 * 24 * $hour, $now - 24 * $hour, 29 * 24 * $hour,
            'old ban', null, $aid, null, null, null,
        ]);
        $this->expiredPre2Bid = (int) $pdo->lastInsertId();

        // 4b) Pre-2.0 PruneBans-shape natural-expiry — RemoveType IS
        //     NULL, RemovedOn IS NOT NULL, RemovedBy = 0, length>0.
        //     The prune writer on the fork set `RemovedOn` but didn't
        //     populate `RemoveType` (the fork-divergence shape
        //     `web/updater/data/810.php` pass 2 backfills to 'E').
        //     The `expired` SQL fragment's arm 3 catches this via
        //     `RemoveType IS NULL AND RemovedOn IS NOT NULL AND
        //     length>0 AND (RemovedBy IS NULL OR RemovedBy = 0)`,
        //     and the arm CRITICALLY requires `RemovedBy IS NULL OR
        //     = 0` so the row never lands in `?state=unbanned` (which
        //     requires `RemovedBy > 0`).
        $insert->execute([
            0, 'STEAM_0:1:90014', 'ExpiredPre2Pruned',
            $now - 30 * 24 * $hour, $now - 24 * $hour, 29 * 24 * $hour,
            'old ban (prune wrote RemovedOn)', null, $aid,
            0, $now - 12 * $hour, null,
        ]);
        $this->expiredPre2PrunedBid = (int) $pdo->lastInsertId();

        // 5) Admin-deleted (RemoveType='D').
        $insert->execute([
            0, 'STEAM_0:1:90005', 'Deleted',
            $now - 5 * 24 * $hour, 0, 0, 'duplicate', 'admin removed',
            $aid, $aid, $now - 4 * 24 * $hour, 'D',
        ]);
        $this->deletedBid = (int) $pdo->lastInsertId();

        // 6) Admin-unbanned (RemoveType='U').
        $insert->execute([
            0, 'STEAM_0:1:90006', 'Unbanned',
            $now - 5 * 24 * $hour, $now + 7 * 24 * $hour, 12 * 24 * $hour,
            'griefing', 'appeal accepted',
            $aid, $aid, $now - 24 * $hour, 'U',
        ]);
        $this->unbannedBid = (int) $pdo->lastInsertId();

        // 7) Pre-2.0 admin-lifted (RemoveType IS NULL but RemovedBy>0
        //    and RemovedOn IS NOT NULL). The marquee bug: v2.0 RC
        //    classified these as 'active' (since ends>now) and the
        //    `?state=unbanned` chip never matched them.
        $insert->execute([
            0, 'STEAM_0:1:90007', 'UnbannedPre2',
            $now - 30 * 24 * $hour, $now + 7 * 24 * $hour, 37 * 24 * $hour,
            'wallhack v1', 'pre-2.0 unban',
            $aid, $aid, $now - 24 * $hour, null,
        ]);
        $this->unbannedPre2Bid = (int) $pdo->lastInsertId();
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

    /**
     * Pull every distinct `data-id="N"` value from rendered HTML
     * (the desktop `<tr ... data-id="N" ... data-testid="ban-row">`
     * rows). The template emits attributes as `class` →
     * `data-state` → `data-id` → `data-testid`, so the regex
     * matches the testid on either side of `data-id`.
     *
     * @return list<int>
     */
    private function extractRowBids(string $html): array
    {
        // Match any `<tr>` that carries BOTH `data-testid="ban-row"`
        // AND `data-id="N"`, regardless of attribute order. The
        // template currently emits `data-id` before `data-testid`
        // but the regex is permissive so an attribute reorder
        // doesn't silently break the test.
        preg_match_all(
            '/<tr\b(?=[^>]*\bdata-testid="ban-row")[^>]*\bdata-id="(\d+)"/s',
            $html,
            $matches,
        );
        $bids = array_map('intval', $matches[1] ?? []);
        return array_values(array_unique($bids));
    }
}
