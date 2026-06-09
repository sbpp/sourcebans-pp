<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * Per-ban / per-comm comments visibility on the public banlist + commslist.
 *
 * v1.x rendered admin-authored per-row comments inline below each row
 * via the `mooaccordion` sliding panel that lived in `web/scripts/sourcebans.js`.
 * That script was deleted at #1123 D1 ("Hard cutover: rename sbpp2026 to
 * default, drop sourcebans.js"). The v2.0 rewrite of `page_bans.tpl`
 * kept the page handler's `commentdata` build (`page.banlist.php` line
 * 765) but only emitted a silent `<span>[N]</span>` count badge — no
 * affordance, no inline body. The actual comment text moved to the
 * right-side player drawer (`renderOverviewPane` in `theme.js`'s
 * Overview pane → "Comments" section), reachable only by clicking the
 * player-name anchor. The commslist regression is worse: the v2.0
 * rewrite of `page_comms.tpl` dropped the comment surface entirely
 * (no badge, no drawer fallback — `<tr data-testid="comm-row">`
 * carries no `data-drawer-href`), so per-row comments became 100%
 * invisible on the page even though the page handler was still
 * building `commentdata` for every row.
 *
 * The fix wires a native `<details data-testid="ban-comments-inline">`
 * disclosure into both templates — summary is the clickable count
 * chip, body lists each comment with author + timestamp + text. The
 * drawer's comments section (banlist only) continues to render the
 * same data via `api_bans_detail`; the two surfaces share the
 * `Config::getBool('config.enablepubliccomments') || $userbank->is_admin()`
 * gate.
 *
 * What this file pins:
 *   - Admin caller sees the inline disclosure on every commented row
 *     (banlist + commslist), regardless of `config.enablepubliccomments`.
 *   - Anonymous caller with `config.enablepubliccomments=0` sees NO
 *     disclosure (gating intact — the data isn't built at all).
 *   - Anonymous caller with `config.enablepubliccomments=1` sees the
 *     same disclosure shape as admin.
 *   - Comment author / timestamp / text reach the rendered HTML
 *     through the same `encodePreservingBr` + URL-wrap regex pipeline
 *     the comment-edit-mode "Other comments" foreach has used since
 *     v1.x — the disclosure reuses the `nofilter` annotation pattern
 *     from that block.
 *   - Mobile cards emit a non-interactive count indicator (the card
 *     is wrapped in a single `<a>` so a nested `<details>` would be
 *     invalid HTML; mobile users tap through to the drawer where
 *     `renderOverviewPane` paints the same comments under Overview).
 *
 * Mirrors the AdminAdminsSearchTest / PublicBanListRegressionTest
 * pattern: in-process `require` of the page handler, output captured
 * via `ob_start` for assertion, separate process per method because
 * the page handlers declare top-level helpers (`setPostKey()` etc.)
 * that PHP can't redeclare.
 */
final class BanlistCommentsVisibilityTest extends ApiTestCase
{
    /** @var int bid of the seeded ban with multiple comments. */
    private int $banWithCommentsBid = 0;

    /** @var int bid of the seeded ban with no comments. */
    private int $banWithoutCommentsBid = 0;

    /** @var int cid of the seeded mute with comments. */
    private int $commWithCommentsCid = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRowsAndComments();
        $this->bootstrapSmartyTheme();
    }

    protected function tearDown(): void
    {
        $_GET     = [];
        $_SESSION = [];
        unset($GLOBALS['theme']);
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Banlist — admin caller (always sees comments regardless of flag)
    // ---------------------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistAdminSeesInlineDisclosureOnCommentedRow(): void
    {
        $this->loginAsAdmin();
        $this->setPublicCommentsFlag(false);
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();

        $this->assertStringContainsString(
            'data-testid="ban-comments-inline"',
            $html,
            'admin must see the per-row inline comments disclosure regardless of the public-comments flag',
        );
        $this->assertStringContainsString(
            'data-testid="ban-comments-toggle"',
            $html,
            'the disclosure summary doubles as the count chip / clickable affordance',
        );
        $this->assertStringContainsString(
            'data-bid="' . $this->banWithCommentsBid . '"',
            $html,
            'the disclosure must carry the bid so future deeplinks / E2E selectors anchor on it',
        );
        $this->assertStringContainsString(
            'data-testid="ban-comment-text"',
            $html,
            'each comment row inside the disclosure must expose the per-comment testid',
        );
        // Comment text passes through encodePreservingBr → only `<br/>`
        // survives, the rest is htmlspecialchars-escaped per text
        // segment. The seeded body is one line so no `<br/>` is
        // emitted; the literal text reaches the assertion surface.
        // Control for #1500: an admin viewer resolves hideadminname to
        // false (`getBool && !is_admin()`), so the comment author renders
        // un-hidden as `<strong>admin</strong>`. The anonymous + #1500
        // tests assert this exact wrapper is suppressed.
        $this->assertStringContainsString(
            '<strong>admin</strong>',
            $html,
            'admin viewer sees the un-hidden comment author username (control for #1500)',
        );
        $this->assertStringContainsString(
            'first comment from worker C',
            $html,
            'seeded comment text must reach the rendered HTML inside the disclosure body',
        );
        $this->assertStringContainsString(
            'second seed comment',
            $html,
            'every comment must render — not just the first',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistMobileCardEmitsCountIndicator(): void
    {
        $this->loginAsAdmin();
        $this->setPublicCommentsFlag(false);
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();

        $this->assertStringContainsString(
            'data-testid="ban-comments-count-mobile"',
            $html,
            'mobile card must surface a non-interactive count indicator (the card wraps in <a>, so a nested <details> would be invalid HTML — drawer is the canonical mobile expansion)',
        );
        // The seeded ban has 2 comments; the count must reach the
        // mobile indicator's text content. Anchor via the singular
        // vs plural label so we know the count branch is exercised.
        $this->assertMatchesRegularExpression(
            '/data-testid="ban-comments-count-mobile"[^>]*>.*?2.*?comments/s',
            $html,
            'mobile count indicator must show the actual comment count + plural label',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistDisclosureDoesNotRenderOnUncommentedRow(): void
    {
        $this->loginAsAdmin();
        $this->setPublicCommentsFlag(false);
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();

        // The disclosure renders TWICE per commented ban (desktop
        // table + mobile-card branch... wait, we don't render
        // a `<details>` on mobile — only the count indicator).
        // The seeded fixture has exactly one commented ban
        // (banWithCommentsBid) and one uncommented ban
        // (banWithoutCommentsBid), so the disclosure count must be
        // exactly 1 (desktop only).
        $disclosureMatchCount = substr_count($html, 'data-testid="ban-comments-inline"');
        $this->assertSame(
            1,
            $disclosureMatchCount,
            'disclosure must render exactly once (one commented ban, desktop only) — uncommented rows do NOT emit the surface',
        );
    }

    // ---------------------------------------------------------------
    // Banlist — anonymous gating
    // ---------------------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistAnonymousWithFlagOffSeesNoDisclosure(): void
    {
        $this->setPublicCommentsFlag(false);
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();

        $this->assertStringNotContainsString(
            'data-testid="ban-comments-inline"',
            $html,
            'anonymous caller must NOT see comments when config.enablepubliccomments=0 (gating intact — page handler does not even build commentdata in this branch)',
        );
        $this->assertStringNotContainsString(
            'data-testid="ban-comments-count-mobile"',
            $html,
            'anonymous caller must NOT see the mobile count either — same gate',
        );
        $this->assertStringNotContainsString(
            'first comment from worker C',
            $html,
            'no comment text may leak to anonymous callers regardless of which surface (disclosure / mobile / drawer)',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistAnonymousWithFlagOnSeesDisclosure(): void
    {
        $this->setPublicCommentsFlag(true);
        $_GET = ['p' => 'banlist'];

        $html = $this->renderBanlistPage();

        $this->assertStringContainsString(
            'data-testid="ban-comments-inline"',
            $html,
            'flipping config.enablepubliccomments=1 must reveal the disclosure to anonymous callers',
        );
        $this->assertStringContainsString(
            'first comment from worker C',
            $html,
            'comment text must reach anonymous callers when the flag is on (this is the public-comments use case)',
        );
        // banlist.hideadminname defaults to '1' (data.sql), so this
        // anonymous render already hides the author — the comment text
        // reaches the page but the admin username does NOT. (The
        // dedicated #1500 test below pins that suppression explicitly;
        // the admin-viewer test pins the un-hidden control.)
        $this->assertStringNotContainsString(
            '<strong>admin</strong>',
            $html,
            'comment author username must not leak to anonymous callers (hideadminname defaults on) (#1500)',
        );
    }

    // ---------------------------------------------------------------
    // Banlist — #1500: hideadminname suppresses the comment author too
    // ---------------------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistHidesCommentAuthorWhenHideAdminName(): void
    {
        // #1500: with public comments ON, the inline disclosure must
        // still suppress the comment author (an admin username) for
        // anonymous callers when banlist.hideadminname is on — same gate
        // the unban-meta inline already honours. Pre-fix the author
        // leaked even though the focal ban's admin name was suppressed.
        Fixture::rawPdo()->prepare(sprintf(
            "REPLACE INTO `%s_settings` (`setting`, `value`) VALUES
                ('config.enablepubliccomments', '1'),
                ('banlist.hideadminname', '1')",
            DB_PREFIX,
        ))->execute();
        \Config::init($GLOBALS['PDO']);

        $_GET = ['p' => 'banlist'];
        $html = $this->renderBanlistPage();

        // The disclosure + comment body still render — hideadminname
        // gates the NAME, not the comment.
        $this->assertStringContainsString('data-testid="ban-comments-inline"', $html);
        $this->assertStringContainsString('first comment from worker C', $html);
        // The author username must NOT leak inline...
        $this->assertStringNotContainsString(
            '<strong>admin</strong>',
            $html,
            'comment author (admin username) must not leak inline when hideadminname is on (#1500)',
        );
        // ...and the gated placeholder renders in its place.
        $this->assertStringContainsString(
            '<i class="text-faint">Hidden</i>',
            $html,
            'the gated comment author must render the Hidden placeholder (#1500)',
        );
    }

    // ---------------------------------------------------------------
    // Banlist — #1500 (M2): the ?comment=N "Other comments" surface.
    // The comment-edit view (page.banlist.php $cotherdata block) is
    // reachable by ANY caller — there is no admin gate; $commentCanedit
    // only controls whether the textarea + submit render. Pre-fix it
    // listed every comment on the ban with the author / editor admin
    // username inline regardless of banlist.hideadminname, so it was a
    // second leak surface distinct from the inline disclosure above.
    // ---------------------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistCommentViewHidesAuthorAndEditorForPublicWhenHideAdminName(): void
    {
        // Give both seeded comments an editor so the "last edit by"
        // branch is exercised (the editor name is a second admin
        // username that must be suppressed too).
        Fixture::rawPdo()->prepare(sprintf(
            'UPDATE `%s_comments` SET editaid = ?, edittime = ? WHERE bid = ? AND type = ?',
            DB_PREFIX,
        ))->execute([Fixture::adminAid(), time() - 300, $this->banWithCommentsBid, 'B']);

        Fixture::rawPdo()->prepare(sprintf(
            "REPLACE INTO `%s_settings` (`setting`, `value`) VALUES
                ('config.enablepubliccomments', '1'),
                ('banlist.hideadminname', '1')",
            DB_PREFIX,
        ))->execute();
        \Config::init($GLOBALS['PDO']);

        // Anonymous caller hits the comment-edit view in "Add" mode (no
        // cid), which lists every comment on the ban as "Other comments".
        $_GET = [
            'p'       => 'banlist',
            'comment' => (string) $this->banWithCommentsBid,
            'ctype'   => 'B',
        ];
        $html = $this->renderBanlistPage();

        // The "Other comments" surface + bodies render...
        $this->assertStringContainsString(
            'Other comments',
            $html,
            'the comment-edit view must list existing comments under "Other comments"',
        );
        $this->assertStringContainsString('first comment from worker C', $html);
        // ...the "last edit by" line renders (edittime present, gated on
        // edittime not editname so the indicator survives the #1500 null)...
        $this->assertStringContainsString(
            'last edit',
            $html,
            'the edit indicator must survive — it gates on edittime, which #1500 does NOT null',
        );
        // ...but neither the author nor the editor admin username leaks...
        $this->assertStringNotContainsString(
            '<strong>admin</strong>',
            $html,
            'comment author (admin username) must not leak in the ?comment=N view (#1500 M2)',
        );
        $this->assertStringNotContainsString(
            'by admin',
            $html,
            'comment editor (admin username) must not leak in the "last edit by" line (#1500 M2)',
        );
        // ...and the Hidden placeholder stands in for author AND editor
        // (two comments × {author + editor} = at least 2 occurrences).
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($html, '<i class="text-faint">Hidden</i>'),
            'both the suppressed author and editor must render the Hidden placeholder (#1500 M2)',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistCommentViewShowsAuthorAndEditorForAdmin(): void
    {
        // Control for the M2 test above: an admin viewer resolves
        // hideadminname=false, so the ?comment=N view shows both the
        // author and editor admin usernames un-hidden.
        Fixture::rawPdo()->prepare(sprintf(
            'UPDATE `%s_comments` SET editaid = ?, edittime = ? WHERE bid = ? AND type = ?',
            DB_PREFIX,
        ))->execute([Fixture::adminAid(), time() - 300, $this->banWithCommentsBid, 'B']);
        $this->setPublicCommentsFlag(true);
        $this->loginAsAdmin();

        $_GET = [
            'p'       => 'banlist',
            'comment' => (string) $this->banWithCommentsBid,
            'ctype'   => 'B',
        ];
        $html = $this->renderBanlistPage();

        $this->assertStringContainsString('Other comments', $html);
        $this->assertStringContainsString(
            '<strong>admin</strong>',
            $html,
            'admin viewer sees the un-hidden comment author in the ?comment=N view (control for #1500 M2)',
        );
        $this->assertStringNotContainsString(
            '<i class="text-faint">Hidden</i>',
            $html,
            'no Hidden placeholder for admin viewers',
        );
    }

    // ---------------------------------------------------------------
    // Commslist — same shape, sister-fix
    // ---------------------------------------------------------------

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCommslistAdminSeesInlineDisclosureOnCommentedRow(): void
    {
        $this->loginAsAdmin();
        $this->setPublicCommentsFlag(false);
        $_GET = ['p' => 'commslist'];

        $html = $this->renderCommslistPage();

        $this->assertStringContainsString(
            'data-testid="comm-comments-inline"',
            $html,
            'admin must see the comm-block inline comments disclosure (commslist regression was worse than banlist — no drawer fallback to recover the data)',
        );
        $this->assertStringContainsString(
            'data-testid="comm-comments-toggle"',
            $html,
            'the disclosure summary doubles as the count chip / clickable affordance',
        );
        $this->assertStringContainsString(
            'data-cid="' . $this->commWithCommentsCid . '"',
            $html,
            'the disclosure must carry the cid (commslist primary key) so future deeplinks anchor on it',
        );
        $this->assertStringContainsString(
            'mute comment for worker C',
            $html,
            'seeded mute-comment text must reach the rendered HTML inside the disclosure body',
        );
        // Control for #1500: admin viewer sees the un-hidden author.
        $this->assertStringContainsString(
            '<strong>admin</strong>',
            $html,
            'admin viewer sees the un-hidden comm-block comment author username (control for #1500)',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCommslistAnonymousWithFlagOffSeesNoDisclosure(): void
    {
        $this->setPublicCommentsFlag(false);
        $_GET = ['p' => 'commslist'];

        $html = $this->renderCommslistPage();

        $this->assertStringNotContainsString(
            'data-testid="comm-comments-inline"',
            $html,
            'anonymous caller must NOT see comm-block comments when config.enablepubliccomments=0',
        );
        $this->assertStringNotContainsString(
            'mute comment for worker C',
            $html,
            'no mute-comment text may leak to anonymous callers when the flag is off',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCommslistHidesCommentAuthorWhenHideAdminName(): void
    {
        // #1500 sister-fix: the commslist inline disclosure has no
        // drawer fallback, so a leaked comm-block comment author is the
        // ONLY on-page surface — must honour banlist.hideadminname too.
        Fixture::rawPdo()->prepare(sprintf(
            "REPLACE INTO `%s_settings` (`setting`, `value`) VALUES
                ('config.enablepubliccomments', '1'),
                ('banlist.hideadminname', '1')",
            DB_PREFIX,
        ))->execute();
        \Config::init($GLOBALS['PDO']);

        $_GET = ['p' => 'commslist'];
        $html = $this->renderCommslistPage();

        // Disclosure + comment body still render — the gate suppresses
        // the NAME, not the comment.
        $this->assertStringContainsString('data-testid="comm-comments-inline"', $html);
        $this->assertStringContainsString('mute comment for worker C', $html);
        // The author username must NOT leak inline...
        $this->assertStringNotContainsString(
            '<strong>admin</strong>',
            $html,
            'comm-block comment author (admin username) must not leak inline when hideadminname is on (#1500)',
        );
        // ...and the gated placeholder renders in its place.
        $this->assertStringContainsString(
            '<i class="text-faint">Hidden</i>',
            $html,
            'the gated comm-block comment author must render the Hidden placeholder (#1500)',
        );
    }

    // ---------------------------------------------------------------
    // Drawer / API — verify the drawer surface stays in sync with the
    // disclosure (the fix preserves both surfaces, doesn't replace one
    // with the other).
    // ---------------------------------------------------------------

    public function testApiBansDetailReturnsCommentsForAdminMatchingDisclosure(): void
    {
        $this->loginAsAdmin();
        $this->setPublicCommentsFlag(false);

        $env = $this->api('bans.detail', ['bid' => $this->banWithCommentsBid]);

        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertTrue(
            $env['data']['comments_visible'],
            'admin caller must see comments_visible=true (drawer surface must stay in sync with the inline disclosure gate)',
        );
        $this->assertCount(
            2,
            $env['data']['comments'],
            'admin caller must see all seeded comments through the API too',
        );
    }

    public function testApiBansDetailHidesCommentsForAnonymousWithFlagOff(): void
    {
        $this->setPublicCommentsFlag(false);

        $env = $this->api('bans.detail', ['bid' => $this->banWithCommentsBid]);

        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertFalse(
            $env['data']['comments_visible'],
            'anonymous caller must see comments_visible=false (drawer surface honours the same gate as the disclosure)',
        );
        $this->assertSame(
            [],
            $env['data']['comments'],
            'anonymous caller must NOT receive comment text via the API when the flag is off',
        );
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function setPublicCommentsFlag(bool $on): void
    {
        Fixture::rawPdo()->prepare(sprintf(
            "REPLACE INTO `%s_settings` (`setting`, `value`) VALUES ('config.enablepubliccomments', ?)",
            DB_PREFIX,
        ))->execute([$on ? '1' : '0']);
        \Config::init($GLOBALS['PDO']);
    }

    private function seedRowsAndComments(): void
    {
        $pdo  = Fixture::rawPdo();
        $now  = time();
        $aid  = Fixture::adminAid();
        $hour = 3600;

        // Seed two bans: one with comments, one without. The
        // uncommented row is the negative control — the disclosure
        // must NOT render on it.
        $insert = $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (type, ip, authid, name, created, ends, length, reason, ureason, aid, RemovedBy, RemovedOn, RemoveType)
             VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX,
        ));

        $insert->execute([
            0, 'STEAM_0:1:42001', 'CommentedPlayer', $now - $hour,
            0, 0, 'aimbot', null, $aid, null, null, null,
        ]);
        $this->banWithCommentsBid = (int) $pdo->lastInsertId();

        $insert->execute([
            0, 'STEAM_0:1:42002', 'UncommentedPlayer', $now - $hour,
            0, 0, 'wallhack', null, $aid, null, null, null,
        ]);
        $this->banWithoutCommentsBid = (int) $pdo->lastInsertId();

        // Two comments on the first ban so the plural label branch
        // (`comments` vs `comment`) is exercised by the test that
        // anchors on the mobile count indicator.
        $insertComment = $pdo->prepare(sprintf(
            'INSERT INTO `%s_comments` (type, bid, aid, commenttxt, added)
             VALUES (?, ?, ?, ?, ?)',
            DB_PREFIX,
        ));
        $insertComment->execute(['B', $this->banWithCommentsBid, $aid, 'first comment from worker C', $now - 1800]);
        $insertComment->execute(['B', $this->banWithCommentsBid, $aid, 'second seed comment', $now - 600]);

        // Seed a mute with comments so the commslist disclosure has
        // something to render. The ureason / RemoveType fields stay
        // null (active mute).
        $insertComm = $pdo->prepare(sprintf(
            'INSERT INTO `%s_comms` (type, authid, name, created, ends, length, reason, ureason, aid, RemovedBy, RemovedOn, RemoveType)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX,
        ));
        $insertComm->execute([
            1, 'STEAM_0:1:42010', 'MutedPlayer', $now - $hour,
            0, 0, 'spam', null, $aid, null, null, null,
        ]);
        $this->commWithCommentsCid = (int) $pdo->lastInsertId();

        $insertComment->execute(['C', $this->commWithCommentsCid, $aid, 'mute comment for worker C', $now - 600]);
    }

    private function bootstrapSmartyTheme(): void
    {
        require_once INCLUDES_PATH . '/SmartyCustomFunctions.php';
        require_once INCLUDES_PATH . '/View/View.php';
        require_once INCLUDES_PATH . '/View/Renderer.php';

        // Per-process compile dir — `web/cache/` is owned by root in
        // the docker image; tests run as the host user. Mirrors the
        // PublicBanListRegressionTest convention.
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
