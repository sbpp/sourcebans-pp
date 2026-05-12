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

    /** @var int bid: an unbanned ban for a player who ALSO has an active ban
     *               on the same authid. Used to pin the has_active_sibling gate
     *               that hides the Re-apply affordance to avoid the misleading
     *               "already banned" duplicate-check error on click. */
    private int $unbannedWithActiveSiblingBid = 0;

    /** @var int bid: the active sibling of $unbannedWithActiveSiblingBid (same authid). */
    private int $activeSiblingBid = 0;

    /** @var int cid of the seeded permanent active mute. */
    private int $activeCid = 0;

    /** @var int cid of the seeded admin-unmuted block (RemoveType='U'). */
    private int $unmutedCid = 0;

    /** @var int cid: an unmuted block for a player who ALSO has an active mute
     *               on the same authid+type. Mirrors the bans-side has_active_sibling
     *               regression for commslist. */
    private int $unmutedWithActiveSiblingCid = 0;

    /** @var int cid: the active sibling of $unmutedWithActiveSiblingCid (same authid+type). */
    private int $activeSiblingCid = 0;

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
        // Two seeded rows are eligible (expired + unbanned). Each
        // eligible row now emits the anchor on BOTH the desktop
        // table AND the mobile card (`row-action-reapply` plus
        // `row-action-reapply-mobile`), so the desktop-only count
        // is exactly 2.
        $desktopReapplyCount = substr_count($html, 'data-testid="row-action-reapply"');
        $this->assertGreaterThanOrEqual(
            2,
            $desktopReapplyCount,
            'one Re-apply anchor per eligible row on the desktop branch (expired + unbanned)',
        );
        // Active rows must NOT carry the anchor — gated to the
        // expired/unbanned branches only. We seeded five rows:
        //   1) active permanent — no anchor (state == 'active')
        //   2) admin-unbanned standalone — anchor (state == 'unbanned', no sibling)
        //   3) expired natural — anchor (state == 'expired', no sibling)
        //   4) admin-unbanned WITH active sibling — no anchor (sibling gate)
        //   5) the active sibling — no anchor (state == 'active')
        // → exactly 2 desktop anchors expected.
        $this->assertLessThan(
            3,
            $desktopReapplyCount,
            'Re-apply anchor must NOT render on the active row, '
            . 'NOR on the unbanned-with-active-sibling row '
            . '(we seeded 2 active + 1 expired + 1 unbanned-standalone + 1 unbanned-with-active-sibling)',
        );
        // Mobile mirror — the public banlist's mobile card now exposes
        // the same row-actions row as the desktop table (see
        // page_bans.tpl `.ban-card__actions`). Asserts the mobile
        // testid hook is present so the mobile card's parity with the
        // desktop chrome stays locked.
        $this->assertStringContainsString(
            'data-testid="row-action-reapply-mobile"',
            $html,
            'mobile-card branch must mirror the desktop Re-apply anchor (.ban-card__actions row)',
        );
        // Anchor must point at the smart-default reban URL.
        $this->assertMatchesRegularExpression(
            '#href="index\.php\?p=admin&amp;c=bans&amp;section=add-ban&amp;rebanid=\d+#',
            $html,
            'Re-apply anchor must deep-link the admin add-ban form with the original bid',
        );
    }

    /**
     * Issue 2/3/4 (this PR): the v2.0 banlist row-actions cell shipped
     * bare HTML-entity glyphs (`&#9998;` ✎ / `&#10003;` ✓ / `&#8634;` ↺ /
     * `&#128203;` 📋) that read as broken icons next to the commslist's
     * Lucide-icon affordance set. This test pins the new chrome:
     *
     *  - Edit / Unban / Re-apply / Copy / Remove buttons each carry a
     *    `<i data-lucide="…">` icon (no entity glyphs).
     *  - The four entity glyphs the v2.0 shape used must NOT appear
     *    inside any rendered row-actions button — the `<i>`/Lucide
     *    swap is total.
     *
     * Re-apply gating is verified separately above; this test is the
     * chrome-shape regression guard.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistRowActionsUseLucideIconsNotEntityGlyphs(): void
    {
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();

        // Each affordance ships an `<i data-lucide="<name>">` icon.
        // Anchor on the testid + icon-name pair so a future renaming
        // sweep that changes one half still trips the assertion.
        $iconExpectations = [
            'row-action-edit'       => 'pencil',
            'row-action-reapply'    => 'rotate-ccw',
            'row-action-copy-steam' => 'copy',
        ];
        foreach ($iconExpectations as $testid => $iconName) {
            $this->assertMatchesRegularExpression(
                '#data-testid="' . preg_quote($testid, '#') . '"[^>]*>\s*<i data-lucide="' . preg_quote($iconName, '#') . '"#',
                $html,
                $testid . ' must render with a `<i data-lucide="' . $iconName . '">` icon (no bare entity glyph).',
            );
        }

        // The four bare entity glyphs the v2.0 shape used as
        // affordance icons. None of them should appear inside the
        // rendered row-actions cell — the swap to Lucide is total.
        // The `&#9998;` is also the Smarty/HTML pencil entity used
        // nowhere else in the surface, so the bare-substring check
        // is precise enough.
        $bannedGlyphs = ['&#9998;', '&#10003;', '&#8634;', '&#128203;'];
        foreach ($bannedGlyphs as $glyph) {
            $this->assertStringNotContainsString(
                $glyph,
                $html,
                'Bare HTML-entity glyph "' . $glyph . '" must not survive in the rendered row-actions chrome (use Lucide icons).',
            );
        }
    }

    /**
     * Issue 5 (this PR): the desktop reason column truncates at
     * `max-width:18rem` with `.truncate`; long reasons used to clip
     * with no way to surface the full text. The fix adds a `title=`
     * attribute on the `<td>` so the browser's native tooltip
     * surfaces the un-truncated reason on hover / long-press.
     *
     * Pinned: the seeded 'wallhack' reason from the unbanned row
     * appears in a `title="…"` attribute on a Reason `<td>`. Empty
     * reasons emit no `title=""`; the conditional in the template
     * gates on `!empty($ban.reason)` so absent reasons don't get a
     * useless empty tooltip.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistReasonCellCarriesHoverTooltip(): void
    {
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();

        // The seeded 'wallhack' reason on `CheaterBeta` (unbanned
        // row) appears at LEAST in the body text (existing visible
        // copy) AND in a `title="…"` attribute on the same row's
        // reason `<td>`. The regex tolerates other attributes
        // between `<td` and `title="wallhack"`.
        $this->assertMatchesRegularExpression(
            '#<td[^>]*\bclass="text-muted truncate"[^>]*\btitle="wallhack"#',
            $html,
            'desktop Reason cell must carry the seeded reason in a title= attribute so hover surfaces the full text (issue 5).',
        );
    }

    /**
     * Regression for the user-reported bug "When trying to reapply
     * ban on an unbanned player, it says SteamID: STEAM_0:0:1000119
     * is already banned." The unbanned row legitimately renders with
     * `state=='unbanned'` (RemoveType='U'), but a SECOND row on the
     * same authid is currently active, so any Re-apply attempt would
     * 4xx on `bans.add`'s duplicate-active check against the active
     * sibling — surfacing as a confusing "already banned" toast on a
     * row that visibly says Unbanned.
     *
     * The page handler computes `has_active_sibling` from the same
     * "is this player currently banned?" predicate `bans.add` uses
     * server-side. The template gates the Re-apply anchor on
     * `!$ban.has_active_sibling`, so the affordance is hidden on the
     * row whose Re-apply would silently fail. The drawer / detail
     * view still shows the unbanned row's history; only the action
     * affordance is removed.
     *
     * This test pins both halves: the unbanned-with-sibling row IS
     * still rendered (so the user can see the historical entry),
     * but its row-actions cell does NOT carry the Re-apply anchor.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistHidesReapplyOnUnbannedRowWithActiveSibling(): void
    {
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();

        $bid = $this->unbannedWithActiveSiblingBid;

        // The row IS rendered — we don't hide the row, only the
        // action. The `<tr>` carries `data-id="<bid>"`.
        $this->assertMatchesRegularExpression(
            '/<tr[^>]*\bdata-id="' . $bid . '"/s',
            $html,
            'unbanned-with-sibling row must still render — only the Re-apply action is gated, '
            . 'not the row itself',
        );

        // Slice the HTML for that single `<tr>...</tr>` and assert
        // it contains NO Re-apply anchor. This is the load-bearing
        // assertion for the bug fix: pre-fix the desktop row carried
        // `data-testid="row-action-reapply"` on the unbanned row even
        // though the click would 4xx on `bans.add`'s duplicate check.
        $rowSlice = $this->extractRowSliceForBid($html, $bid);
        $this->assertNotSame('', $rowSlice, 'failed to slice the row HTML for bid=' . $bid);
        $this->assertStringNotContainsString(
            'data-testid="row-action-reapply"',
            $rowSlice,
            'unbanned-with-active-sibling row must NOT carry the desktop Re-apply anchor — '
            . 'the click would 4xx on the active sibling and the bug surfaces as "already banned"',
        );
        $this->assertStringNotContainsString(
            'data-testid="row-action-reapply-mobile"',
            $rowSlice,
            'unbanned-with-active-sibling row must NOT carry the mobile Re-apply anchor either',
        );
    }

    /**
     * Mirror for the commslist surface — same bug shape, same fix.
     * The commslist regression is HIGHER priority than the banlist's
     * because there is no drawer fallback on a comm row, so the
     * Re-apply chip is the only on-page action. Pre-fix clicking it
     * lands on the admin add-comm form which 4xx's against the
     * active sibling on `comms.add`.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCommslistHidesReapplyOnUnmutedRowWithActiveSibling(): void
    {
        $_GET = ['p' => 'commslist'];

        $html = $this->renderCommslistPage();

        $cid = $this->unmutedWithActiveSiblingCid;

        $this->assertMatchesRegularExpression(
            '/<tr[^>]*\bdata-id="' . $cid . '"/s',
            $html,
            'unmuted-with-sibling comm row must still render — only the Re-apply action is gated',
        );

        $rowSlice = $this->extractCommRowSliceForCid($html, $cid);
        $this->assertNotSame('', $rowSlice, 'failed to slice the comm row HTML for cid=' . $cid);
        $this->assertStringNotContainsString(
            'data-testid="row-action-reapply"',
            $rowSlice,
            'unmuted-with-active-sibling comm row must NOT carry the desktop Re-apply anchor',
        );
        $this->assertStringNotContainsString(
            'data-testid="row-action-reapply-mobile"',
            $rowSlice,
            'unmuted-with-active-sibling comm row must NOT carry the mobile Re-apply anchor',
        );
    }

    /**
     * Pre-#REBAN-FIX the templates also rendered the legacy comments-modal
     * launcher on every row. Whether or not that block is present, the
     * Re-apply gate must not regress — the count assertion above pins
     * the exact-2 contract on bare `?p=banlist` with the seeded data.
     * This test confirms the standalone-unbanned row (CheaterBeta)
     * STILL gets a Re-apply anchor, so the gate isn't accidentally
     * over-broad and hiding it on every unbanned row.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistKeepsReapplyOnStandaloneUnbannedRow(): void
    {
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();

        $rowSlice = $this->extractRowSliceForBid($html, $this->unbannedBid);
        $this->assertNotSame('', $rowSlice);
        $this->assertStringContainsString(
            'data-testid="row-action-reapply"',
            $rowSlice,
            'standalone unbanned row (no active sibling) MUST still emit the desktop Re-apply anchor — '
            . 'the gate fires only when the same authid carries an active sibling',
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

        // Seed a player (STEAM_0:1:50004) with TWO bans on the same
        // authid: one admin-unbanned (RemoveType='U') and one ACTIVE
        // permanent. This pair pins the `has_active_sibling` gate
        // that hides the Re-apply affordance — clicking Re-apply on
        // the unbanned row would land at `?p=admin&c=bans&section=add-ban&rebanid=…`
        // which then fails the duplicate-active check on the ACTIVE
        // sibling, surfacing as "is already banned by ban #X." This
        // is the regression flagged by the user (`STEAM_0:0:1000119`):
        // the row visibly read "Unbanned" but Re-apply silently
        // fails because of the active sibling. Hide Re-apply when
        // a sibling is active so the operator never reaches the
        // confusing error path.
        $insert->execute([
            0,
            'STEAM_0:1:50004',
            'CheaterDelta',
            $now - 14 * 24 * $hour,
            $now - 7 * 24 * $hour,
            7 * 24 * $hour,
            'old offence',
            'forgiven for the old offence',
            $aid,
            $aid,
            $now - 6 * 24 * $hour,
            'U',
        ]);
        $this->unbannedWithActiveSiblingBid = (int) $pdo->lastInsertId();

        $insert->execute([
            0,
            'STEAM_0:1:50004',
            'CheaterDelta',
            $now - $hour,
            0,
            0,
            'fresh offence',
            null,
            $aid,
            null,
            null,
            null,
        ]);
        $this->activeSiblingBid = (int) $pdo->lastInsertId();

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

        // Mirror of the bans-side has_active_sibling pair on commslist:
        // one player (STEAM_0:1:60003) with type=1 (mute) carries an
        // unmuted block AND an active permanent mute on the same
        // authid+type. Pins the commslist's has_active_sibling gate —
        // pre-fix the Re-apply chip surfaced on the unmuted row even
        // though the click would 4xx on `comms.add` against the active
        // sibling. The gate is per-(authid, type), so a player can
        // legitimately carry an unmuted-mute + active-gag without
        // tripping it; we only seed the same-type pair here.
        $insertComm->execute([
            1,
            'STEAM_0:1:60003',
            'SpammerGamma',
            $now - 14 * 24 * $hour,
            $now - 7 * 24 * $hour,
            7 * 24 * $hour,
            'spam (old)',
            'mute lifted on appeal',
            $aid,
            $aid,
            $now - 6 * 24 * $hour,
            'U',
        ]);
        $this->unmutedWithActiveSiblingCid = (int) $pdo->lastInsertId();

        $insertComm->execute([
            1,
            'STEAM_0:1:60003',
            'SpammerGamma',
            $now - $hour,
            0,
            0,
            'spam (fresh)',
            null,
            $aid,
            null,
            null,
            null,
        ]);
        $this->activeSiblingCid = (int) $pdo->lastInsertId();
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

    /**
     * Extract the rendered HTML for a single banlist `<tr>` keyed by
     * its `data-id="<bid>"` attribute, plus the immediately-following
     * mobile-card block (`<div data-testid="ban-card" data-id="<bid>">`).
     * Returns the empty string when neither shape matches. The slice
     * lets the assertion test row-scoped substrings without
     * accidentally matching siblings.
     */
    private function extractRowSliceForBid(string $html, int $bid): string
    {
        $combined = '';

        // Desktop `<tr>` — non-greedy slice from the matching opener to
        // the next `</tr>`. Anchored on `data-id="<bid>"` plus the
        // canonical `data-testid="ban-row"` to avoid colliding with
        // any other `<tr>` that might appear in the layout chrome.
        if (
            preg_match(
                '#<tr\b(?=[^>]*\bdata-testid="ban-row")(?=[^>]*\bdata-id="' . $bid . '")[^>]*>(.*?)</tr>#s',
                $html,
                $tr,
            )
        ) {
            $combined .= $tr[0];
        }

        // Mobile card — the public banlist's mobile branch wraps the
        // row in `<div class="ban-card" ... data-testid="ban-card" data-id="<bid>">`,
        // closing at the matching `</div>` that ends the card. The
        // template emits enough nested `<div>`s that we slice on the
        // testid pair plus a defensive `</a>\s*</div>` (the card
        // ends with the row-actions row inside an anchor wrapper).
        // Cheap heuristic — the assertion only cares about whether
        // the `row-action-reapply-mobile` testid is in the slice.
        if (
            preg_match(
                '#<div\b(?=[^>]*\bdata-testid="ban-card")(?=[^>]*\bdata-id="' . $bid . '")[^>]*>.*?(?=<div\b[^>]*\bdata-testid="ban-card"|<table\b|\Z)#s',
                $html,
                $card,
            )
        ) {
            $combined .= $card[0];
        }

        return $combined;
    }

    /**
     * Mirror of extractRowSliceForBid for the commslist's rows. The
     * commslist uses `data-testid="comm-row"` on the desktop `<tr>`
     * and `data-testid="comm-card"` on the mobile card.
     */
    private function extractCommRowSliceForCid(string $html, int $cid): string
    {
        $combined = '';

        if (
            preg_match(
                '#<tr\b(?=[^>]*\bdata-testid="comm-row")(?=[^>]*\bdata-id="' . $cid . '")[^>]*>(.*?)</tr>#s',
                $html,
                $tr,
            )
        ) {
            $combined .= $tr[0];
        }

        if (
            preg_match(
                '#<div\b(?=[^>]*\bdata-testid="comm-card")(?=[^>]*\bdata-id="' . $cid . '")[^>]*>.*?(?=<div\b[^>]*\bdata-testid="comm-card"|<table\b|\Z)#s',
                $html,
                $card,
            )
        ) {
            $combined .= $card[0];
        }

        return $combined;
    }
}
