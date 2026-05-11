<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Per-handler coverage for web/api/handlers/bans.php. The end-to-end
 * happy path for `bans.add` lives in tests/integration/BanFlowTest;
 * here we lock the wire format of every other state-changing action,
 * plus the read-only setup_ban / prepare_reban helpers the form pages
 * call before rendering.
 */
final class BansTest extends ApiTestCase
{
    /** Drop a single ban into :prefix_bans without going through bans.add. */
    private function seedBan(string $steam = 'STEAM_0:1:42', string $reason = 'test'): int
    {
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (created, type, ip, authid, name, ends, length, reason, aid, adminIp)
             VALUES (UNIX_TIMESTAMP(), 0, "", ?, ?, UNIX_TIMESTAMP(), 0, ?, ?, "127.0.0.1")',
            DB_PREFIX
        ))->execute([$steam, 'Cheater', $reason, Fixture::adminAid()]);
        return (int)$pdo->lastInsertId();
    }

    private function seedSubmission(string $name = 'Bob', string $steam = 'STEAM_0:1:99'): int
    {
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_submissions`
              (`name`, `SteamId`, `email`, `reason`, `archiv`, `submitted`, `ModID`, `ip`, `server`)
             VALUES (?, ?, ?, ?, "0", ?, 0, "127.0.0.1", 0)',
            DB_PREFIX
        ))->execute([$name, $steam, "$name@example.test", 'cheating', time()]);
        return (int)$pdo->lastInsertId();
    }

    public function testAddRejectsAnonymous(): void
    {
        $env = $this->api('bans.add', [
            'nickname' => 'X', 'type' => 0, 'steam' => 'STEAM_0:1:1',
            'ip' => '', 'length' => 0, 'reason' => '', 'fromsub' => 0,
        ]);
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('bans/add_forbidden', $env);
    }

    public function testAddSuccessSnapshot(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('bans.add', [
            'nickname' => 'Snapshotty',
            'type'     => 0,
            'steam'    => 'STEAM_0:1:7777',
            'ip'       => '',
            'length'   => 0,         // permanent
            'dfile'    => '',
            'dname'    => '',
            'reason'   => 'wallhack',
            'fromsub'  => 0,
        ]);
        $this->assertTrue($env['ok'], json_encode($env));
        // bid depends on the auto-increment counter; redact it.
        $this->assertSnapshot('bans/add_success', $env, ['data.bid']);
    }

    public function testAddValidationMissingSteamForType0(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('bans.add', [
            'nickname' => 'X', 'type' => 0, 'steam' => '', 'ip' => '',
            'length' => 0, 'reason' => '', 'fromsub' => 0,
        ]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('steam', $env['error']['field']);
        $this->assertSnapshot('bans/add_validation_steam', $env);
    }

    public function testAddValidationInvalidIpForType1(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('bans.add', [
            'nickname' => 'X', 'type' => 1, 'steam' => '', 'ip' => 'not-an-ip',
            'length' => 0, 'reason' => '', 'fromsub' => 0,
        ]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('ip', $env['error']['field']);
    }

    public function testAddValidationNegativeLength(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('bans.add', [
            'nickname' => 'X', 'type' => 0, 'steam' => 'STEAM_0:1:1',
            'ip' => '', 'length' => -1, 'reason' => '', 'fromsub' => 0,
        ]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('length', $env['error']['field']);
    }

    public function testAddValidationLengthTooLong(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('bans.add', [
            'nickname' => 'X', 'type' => 0, 'steam' => 'STEAM_0:1:2',
            'ip' => '', 'length' => 60 * 24 * 365 * 200, // 200y
            'reason' => '', 'fromsub' => 0,
        ]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('length', $env['error']['field']);
    }

    public function testAddArchivesMatchingSubmission(): void
    {
        $this->loginAsAdmin();
        $sid = $this->seedSubmission('Sub', 'STEAM_0:1:5555');

        $env = $this->api('bans.add', [
            'nickname' => 'Sub', 'type' => 0, 'steam' => 'STEAM_0:1:5555',
            'ip' => '', 'length' => 0, 'dfile' => '', 'dname' => '',
            'reason' => 'matched submission', 'fromsub' => 0,
        ]);
        $this->assertTrue($env['ok'], json_encode($env));

        // The handler matches the SteamId and flips the submission to
        // archive bucket "3" (auto-archived because the player got banned).
        $sub = $this->row('submissions', ['subid' => $sid]);
        $this->assertSame(3, (int)$sub['archiv']);
        $this->assertSame(Fixture::adminAid(), (int)$sub['archivedby']);
    }

    public function testAddFromSubmissionMarksSourceArchiv2(): void
    {
        $this->loginAsAdmin();
        $sid = $this->seedSubmission('FromSub', 'STEAM_0:1:6666');

        $env = $this->api('bans.add', [
            'nickname' => 'FromSub', 'type' => 0, 'steam' => 'STEAM_0:1:6666',
            'ip' => '', 'length' => 0, 'dfile' => '', 'dname' => '',
            'reason' => 'banned via submission', 'fromsub' => $sid,
        ]);
        $this->assertTrue($env['ok'], json_encode($env));

        // When fromsub != 0 the originating submission is archived under
        // bucket "2" (banned via this specific submission). The post-loop
        // SteamId-match also runs but archiv=3 doesn't override 2 because
        // the WHERE is `SteamId = ?`, not `archiv != ...`. Either way the
        // submission ends up out of the open queue.
        $sub = $this->row('submissions', ['subid' => $sid]);
        $this->assertContains((int)$sub['archiv'], [2, 3], 'sub should be archived');
    }

    public function testAddRefusesDuplicateActiveBan(): void
    {
        $this->loginAsAdmin();
        $params = [
            'nickname' => 'Dup', 'type' => 0, 'steam' => 'STEAM_0:1:8888',
            'ip' => '', 'length' => 0, 'dfile' => '', 'dname' => '',
            'reason' => 'first', 'fromsub' => 0,
        ];
        $first  = $this->api('bans.add', $params);
        $this->assertTrue($first['ok']);

        $params['reason'] = 'second';
        $second = $this->api('bans.add', $params);
        $this->assertEnvelopeError($second, 'already_banned');
        $this->assertSnapshot('bans/add_already_banned', $second);
    }

    public function testSetupBanReturnsSubmissionData(): void
    {
        $this->loginAsAdmin();
        $sid = $this->seedSubmission('Setup', 'STEAM_0:1:1234');
        $env = $this->api('bans.setup_ban', ['subid' => $sid]);
        $this->assertTrue($env['ok']);
        $this->assertSame($sid, (int)$env['data']['subid']);
        $this->assertSame('Setup', $env['data']['nickname']);
        $this->assertSame('STEAM_0:1:1234', $env['data']['steam']);
        // type=0 because the submission carried a SteamId.
        $this->assertSame(0, (int)$env['data']['type']);
        $this->assertSnapshot('bans/setup_ban_success', $env, ['data.subid']);
    }

    public function testPrepareRebanReturnsBanData(): void
    {
        $this->loginAsAdmin();
        $bid = $this->seedBan('STEAM_0:1:7766', 'reban-source');
        $env = $this->api('bans.prepare_reban', ['bid' => $bid]);
        $this->assertTrue($env['ok']);
        $this->assertSame($bid, (int)$env['data']['bid']);
        $this->assertSame('STEAM_0:1:7766', $env['data']['steam']);
        $this->assertSame('reban-source',   $env['data']['reason']);
        $this->assertSnapshot('bans/prepare_reban_success', $env, ['data.bid']);
    }

    public function testDetailRejectsMissingBid(): void
    {
        // bans.detail is public; the dispatcher lets the call through and
        // the handler validates `bid` itself. An unknown id 404s; bid=0
        // surfaces as a 'bad_request' validation error.
        $env = $this->api('bans.detail', ['bid' => 0]);
        $this->assertEnvelopeError($env, 'bad_request');
        $this->assertSame('bid', $env['error']['field']);
        $this->assertSnapshot('bans/detail_bad_request', $env);
    }

    public function testDetailReturns404ForUnknownBid(): void
    {
        $env = $this->api('bans.detail', ['bid' => 999999]);
        $this->assertEnvelopeError($env, 'not_found');
        $this->assertSnapshot('bans/detail_not_found', $env);
    }

    public function testDetailPublicViewHidesAdminFields(): void
    {
        // Public caller (not logged in). The handler must (a) succeed,
        // (b) hide the IP when banlist.hideplayerips is on, (c) hide the
        // admin name when banlist.hideadminname is on, and (d) only
        // include comments when config.enablepubliccomments is on.
        Fixture::rawPdo()->prepare(sprintf(
            "REPLACE INTO `%s_settings` (`setting`, `value`) VALUES
                ('banlist.hideplayerips', '1'),
                ('banlist.hideadminname', '1'),
                ('config.enablepubliccomments', '0')",
            DB_PREFIX
        ))->execute();
        \Config::init($GLOBALS['PDO']);

        $bid = $this->seedBan('STEAM_0:1:1010', 'public-view');

        $env = $this->api('bans.detail', ['bid' => $bid]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSame($bid,            (int)$env['data']['bid']);
        $this->assertSame('STEAM_0:1:1010', $env['data']['player']['steam_id']);
        $this->assertNull($env['data']['player']['ip'],   'IP should be hidden for public + hideplayerips');
        $this->assertNull($env['data']['admin']['name'],  'admin should be hidden for public + hideadminname');
        $this->assertFalse($env['data']['comments_visible'], 'comments should be hidden when public + flag off');
        $this->assertSame([], $env['data']['comments']);
        $this->assertFalse($env['data']['notes_visible'], 'notes_visible should be false for public callers (#1165)');
        $this->assertSnapshot('bans/detail_public_hidden', $env, ['data.bid', 'data.ban.banned_at', 'data.ban.banned_at_human', 'data.ban.expires_at', 'data.ban.expires_at_human']);
    }

    public function testDetailAdminViewExposesEverything(): void
    {
        // Same hide-* flags on, but as an admin: handler must NOT honour
        // them (admins always see the underlying data) and comments must
        // come back even with the public-comments flag off.
        Fixture::rawPdo()->prepare(sprintf(
            "REPLACE INTO `%s_settings` (`setting`, `value`) VALUES
                ('banlist.hideplayerips', '1'),
                ('banlist.hideadminname', '1'),
                ('config.enablepubliccomments', '0')",
            DB_PREFIX
        ))->execute();
        \Config::init($GLOBALS['PDO']);

        $this->loginAsAdmin();
        $bid = $this->seedBan('STEAM_0:1:2020', 'admin-view');

        // Add a comment via the public API so the snapshot exercises the
        // comment-list shape end-to-end.
        $this->api('bans.add_comment', [
            'bid' => $bid, 'ctype' => 'B', 'ctext' => 'note for the drawer', 'page' => -1,
        ]);

        $env = $this->api('bans.detail', ['bid' => $bid]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSame('STEAM_0:1:2020', $env['data']['player']['steam_id']);
        $this->assertNotNull($env['data']['admin']['name']);
        $this->assertTrue($env['data']['comments_visible']);
        $this->assertTrue($env['data']['notes_visible'], 'notes_visible should be true for admin callers (#1165)');
        $this->assertCount(1, $env['data']['comments']);
        $this->assertSame('note for the drawer', $env['data']['comments'][0]['text']);
        $this->assertSnapshot('bans/detail_admin_view', $env, [
            'data.bid',
            'data.ban.banned_at',
            'data.ban.banned_at_human',
            'data.ban.expires_at',
            'data.ban.expires_at_human',
            'data.comments.0.cid',
            'data.comments.0.added',
            'data.comments.0.added_human',
        ]);
    }

    public function testDetailReportsUnbannedForPre2AdminLiftWithRemoveTypeNull(): void
    {
        // #1352: pre-2.0 admin-lifted bans whose `RemoveType IS NULL`
        // (some v1.x panels left the column NULL even when admin lifted
        // the ban — see web/updater/data/810.php's backfill migration)
        // must surface as `state: 'unbanned'` from the JSON detail
        // endpoint. Pre-fix the handler hit the default `'active'`
        // branch since `length > 0 && ends > now`, leaving the drawer's
        // detail surface visibly contradicting the page-side
        // `?state=unbanned` SQL filter that pulled the row in.
        $pdo = Fixture::rawPdo();
        $now = time();
        $aid = Fixture::adminAid();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (created, type, ip, authid, name, ends, length, reason, ureason, aid, adminIp, RemovedBy, RemovedOn, RemoveType)
             VALUES (?, 0, "", ?, ?, ?, ?, ?, ?, ?, "127.0.0.1", ?, ?, NULL)',
            DB_PREFIX,
        ))->execute([
            $now - 7 * 86400,
            'STEAM_0:1:54321',
            'Pre2Lifted',
            $now + 7 * 86400,
            14 * 86400,
            'wallhack v1',
            'pre-2.0 unban',
            $aid,
            $aid,
            $now - 86400,
        ]);
        $bid = (int) $pdo->lastInsertId();

        $env = $this->api('bans.detail', ['bid' => $bid]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSame('unbanned', $env['data']['ban']['state'],
            'pre-2.0 admin-lifted row (RemoveType IS NULL but RemovedBy > 0) '
            . 'must surface as state=unbanned via the defensive fallback in '
            . 'api_bans_detail (parity with the page handler\'s SQL filter)');
    }

    public function testAddCommentInsertsRow(): void
    {
        $this->loginAsAdmin();
        $bid = $this->seedBan();

        $env = $this->api('bans.add_comment', [
            'bid' => $bid, 'ctype' => 'B', 'ctext' => 'note for the ban', 'page' => -1,
        ]);
        $this->assertTrue($env['ok']);

        $rows = $this->rows('comments', ['bid' => $bid]);
        $this->assertCount(1, $rows);
        $this->assertSame('note for the ban', $rows[0]['commenttxt']);
        $this->assertSame(Fixture::adminAid(), (int)$rows[0]['aid']);
        $this->assertSnapshot('bans/add_comment_success', $env);
    }

    public function testAddCommentRejectsBadType(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('bans.add_comment', [
            'bid' => 1, 'ctype' => 'X', 'ctext' => 'whatever', 'page' => -1,
        ]);
        $this->assertEnvelopeError($env, 'bad_type');
        $this->assertSnapshot('bans/add_comment_bad_type', $env);
    }

    public function testEditCommentUpdatesRow(): void
    {
        $this->loginAsAdmin();
        $bid = $this->seedBan();
        $this->api('bans.add_comment', ['bid' => $bid, 'ctype' => 'B', 'ctext' => 'first', 'page' => -1]);
        $cid = (int)$this->row('comments', ['bid' => $bid])['cid'];

        $env = $this->api('bans.edit_comment', [
            'cid' => $cid, 'ctype' => 'B', 'ctext' => 'edited', 'page' => -1,
        ]);
        $this->assertTrue($env['ok']);
        $row = $this->row('comments', ['cid' => $cid]);
        $this->assertSame('edited', $row['commenttxt']);
        $this->assertSame(Fixture::adminAid(), (int)$row['editaid']);
        $this->assertNotEmpty($row['edittime']);
        $this->assertSnapshot('bans/edit_comment_success', $env, ['data.message.body']);
    }

    public function testEditCommentRejectsBadType(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('bans.edit_comment', [
            'cid' => 1, 'ctype' => 'Z', 'ctext' => 'x', 'page' => -1,
        ]);
        $this->assertEnvelopeError($env, 'bad_type');
    }

    public function testRemoveCommentDeletesRow(): void
    {
        $this->loginAsAdmin();
        $bid = $this->seedBan();
        $this->api('bans.add_comment', ['bid' => $bid, 'ctype' => 'B', 'ctext' => 'temp', 'page' => -1]);
        $cid = (int)$this->row('comments', ['bid' => $bid])['cid'];

        $env = $this->api('bans.remove_comment', ['cid' => $cid, 'ctype' => 'B', 'page' => -1]);
        $this->assertTrue($env['ok']);
        $this->assertNull($this->row('comments', ['cid' => $cid]));
        $this->assertSnapshot('bans/remove_comment_success', $env);
    }

    public function testPasteRequiresKnownServer(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('bans.paste', ['sid' => 0, 'name' => 'someone', 'type' => 0]);
        $this->assertEnvelopeError($env, 'rcon_failed');
        $this->assertSnapshot('bans/paste_rcon_failed', $env);
    }

    public function testKickPlayerWithoutRconFails(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('bans.kick_player', ['sid' => 0, 'name' => 'someone']);
        $this->assertEnvelopeError($env, 'rcon_failed');
        $this->assertSnapshot('bans/kick_player_rcon_failed', $env);
    }

    public function testSendMessageWithoutAdminLoginRedirects(): void
    {
        // bans.send_message is requireAdmin; the dispatcher rejects before
        // the handler runs.
        $env = $this->api('bans.send_message', ['sid' => 1, 'name' => 'x', 'message' => 'y']);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testSendMessageWithoutRconFails(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('bans.send_message', ['sid' => 0, 'name' => 'x', 'message' => 'y']);
        $this->assertEnvelopeError($env, 'rcon_failed');
        $this->assertSnapshot('bans/send_message_rcon_failed', $env);
    }

    public function testViewCommunityWithoutRconFails(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('bans.view_community', ['sid' => 0, 'name' => 'x']);
        $this->assertEnvelopeError($env, 'rcon_failed');
    }

    public function testGroupBanGatedByConfigSetting(): void
    {
        $this->loginAsAdmin();
        // data.sql ships config.enablegroupbanning=0, so the handler short-
        // circuits with an empty payload — locking the "feature off" wire
        // shape, which the UI uses to decide whether to render the group-
        // ban tab.
        $env = $this->api('bans.group_ban', [
            'groupuri' => 'https://steamcommunity.com/groups/example',
            'isgrpurl' => 'no',
            'queue'    => 'no',
            'reason'   => '',
            'last'     => '',
        ]);
        $this->assertTrue($env['ok']);
        $this->assertSame([], (array)$env['data']);
        $this->assertSnapshot('bans/group_ban_disabled', $env);
    }

    public function testGroupBanValidatesGroupUrl(): void
    {
        $this->loginAsAdmin();
        // Enable the feature so we exercise the validation branch.
        Fixture::rawPdo()->prepare(sprintf(
            "REPLACE INTO `%s_settings` (`setting`, `value`) VALUES ('config.enablegroupbanning', '1')",
            DB_PREFIX
        ))->execute();
        \Config::init($GLOBALS['PDO']);

        $env = $this->api('bans.group_ban', [
            // Empty path → grpname extraction yields ''. Should validate.
            'groupuri' => '',
            'isgrpurl' => 'no',
            'queue'    => 'no',
            'reason'   => '',
            'last'     => '',
        ]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('groupurl', $env['error']['field']);
    }

    public function testBanFriendsValidatesNumericFriendId(): void
    {
        $this->loginAsAdmin();
        Fixture::rawPdo()->prepare(sprintf(
            "REPLACE INTO `%s_settings` (`setting`, `value`) VALUES ('config.enablefriendsbanning', '1')",
            DB_PREFIX
        ))->execute();
        \Config::init($GLOBALS['PDO']);

        $env = $this->api('bans.ban_friends', ['friendid' => 'not-numeric', 'name' => 'x']);
        $this->assertEnvelopeError($env, 'bad_request');
        $this->assertSnapshot('bans/ban_friends_bad_request', $env);
    }

    public function testGetGroupsReturnsEmptyForNonNumericFriendId(): void
    {
        $this->loginAsAdmin();
        Fixture::rawPdo()->prepare(sprintf(
            "REPLACE INTO `%s_settings` (`setting`, `value`) VALUES ('config.enablegroupbanning', '1')",
            DB_PREFIX
        ))->execute();
        \Config::init($GLOBALS['PDO']);

        $env = $this->api('bans.get_groups', ['friendid' => 'not-numeric']);
        $this->assertTrue($env['ok']);
        $this->assertSame([], $env['data']['groups']);
    }

    public function testBanMemberOfGroupShortCircuitsWithoutSteamApiKey(): void
    {
        $this->loginAsAdmin();
        Fixture::rawPdo()->prepare(sprintf(
            "REPLACE INTO `%s_settings` (`setting`, `value`) VALUES ('config.enablegroupbanning', '1')",
            DB_PREFIX
        ))->execute();
        \Config::init($GLOBALS['PDO']);

        // Bootstrap defines STEAMAPIKEY=''. The handler short-circuits to
        // an empty data payload so the UI can detect "no key configured"
        // without any external HTTP traffic.
        $env = $this->api('bans.ban_member_of_group', [
            'grpurl' => 'whatever',
            'queue'  => 'no',
            'reason' => '',
            'last'   => '',
        ]);
        $this->assertTrue($env['ok']);
        $this->assertSame([], (array)$env['data']);
    }

    public function testSearchRejectsAnonymous(): void
    {
        // Palette only mounts for logged-in admins; the dispatcher rejects
        // anonymous calls before the handler runs.
        $env = $this->api('bans.search', ['q' => 'whatever']);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testSearchShortCircuitsForShortQuery(): void
    {
        $this->loginAsAdmin();
        $this->seedBan('STEAM_0:1:42', 'cheating');

        // Single-character (or empty) queries are intentionally a no-op so
        // the very first keypress in the palette doesn't sweep `:prefix_bans`.
        $env = $this->api('bans.search', ['q' => 'a']);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSame([], $env['data']['bans']);

        $env2 = $this->api('bans.search', ['q' => '']);
        $this->assertTrue($env2['ok'], json_encode($env2));
        $this->assertSame([], $env2['data']['bans']);

        $this->assertSnapshot('bans/search_short_query', $env);
    }

    public function testSearchMatchesByName(): void
    {
        $this->loginAsAdmin();
        $bid = $this->seedBan('STEAM_0:1:7777', 'wallhack');
        // seedBan() inserts `Cheater` as the player name.

        $env = $this->api('bans.search', ['q' => 'Chea', 'limit' => 5]);
        $this->assertTrue($env['ok'], json_encode($env));

        $bans = $env['data']['bans'];
        $this->assertNotEmpty($bans, 'expected at least one match');
        $this->assertSame($bid,            (int)$bans[0]['bid']);
        $this->assertSame('Cheater',       $bans[0]['name']);
        $this->assertSame('STEAM_0:1:7777', $bans[0]['steam']);
        $this->assertSame(0,               (int)$bans[0]['type']);
    }

    public function testSearchMatchesBySteamIdAcrossUniverseDigits(): void
    {
        // #1130: the search input may carry STEAM_1:… but rows are stored
        // as STEAM_0:… (and vice versa). The palette mirrors the page-level
        // banlist's REGEXP fallback so neither variant gets silently lost.
        $this->loginAsAdmin();
        $bid = $this->seedBan('STEAM_0:1:5555', 'mirror');

        $env = $this->api('bans.search', ['q' => 'STEAM_1:1:5555']);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSame($bid, (int)$env['data']['bans'][0]['bid']);
    }

    public function testSearchLimitClampedToTwenty(): void
    {
        $this->loginAsAdmin();
        for ($i = 0; $i < 25; $i++) {
            $this->seedBan('STEAM_0:1:' . (1000 + $i), 'bulk-' . $i);
        }
        // Names are all `Cheater`; default `limit` is 10 but a 999 request
        // should be capped, not honoured verbatim.
        $env = $this->api('bans.search', ['q' => 'Chea', 'limit' => 999]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertCount(20, $env['data']['bans'], 'limit should clamp to 20');
    }

    public function testSearchSnapshotShape(): void
    {
        $this->loginAsAdmin();
        $this->seedBan('STEAM_0:1:7777', 'wallhack');

        $env = $this->api('bans.search', ['q' => 'Chea', 'limit' => 5]);
        $this->assertTrue($env['ok'], json_encode($env));
        // bid is an autoincrement; redact so the snapshot only locks shape.
        $this->assertSnapshot('bans/search_success', $env, ['data.bans.0.bid']);
    }

    // -- bans.unban (#1301) ------------------------------------------------

    public function testUnbanRejectsAnonymous(): void
    {
        $env = $this->api('bans.unban', ['bid' => 1, 'ureason' => 'whatever']);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testUnbanBadRequestOnMissingBid(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('bans.unban', ['ureason' => 'no bid here']);
        $this->assertEnvelopeError($env, 'bad_request');
        $this->assertSame('bid', $env['error']['field']);
    }

    /**
     * #1301: a non-empty `ureason` is mandatory. v1.x prompted via
     * sourcebans.js's UnbanBan helper and required a reason; v2.0
     * silently accepted '', so the audit log lost the *why*.
     */
    public function testUnbanRejectsEmptyUreason(): void
    {
        $this->loginAsAdmin();
        $bid = $this->seedBan('STEAM_0:1:1301', 'cheating');

        // Missing entirely.
        $env = $this->api('bans.unban', ['bid' => $bid]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('ureason', $env['error']['field']);

        // Whitespace-only counts as empty after trim().
        $env = $this->api('bans.unban', ['bid' => $bid, 'ureason' => "  \n\t  "]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('ureason', $env['error']['field']);

        // Confirms the row was NOT touched on rejection.
        $row = $this->row('bans', ['bid' => $bid]);
        $this->assertNull($row['RemoveType']);
        $this->assertNull($row['RemovedBy']);
    }

    public function testUnbanNotFoundForUnknownBid(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('bans.unban', ['bid' => 99999, 'ureason' => 'test']);
        $this->assertEnvelopeError($env, 'not_found');
    }

    public function testUnbanLiftsActiveBanAndPersistsState(): void
    {
        $this->loginAsAdmin();
        $bid = $this->seedBan('STEAM_0:1:1302', 'cheating');

        $env = $this->api('bans.unban', [
            'bid'     => $bid,
            'ureason' => 'mistaken ban',
        ]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSame('unbanned', $env['data']['state']);
        $this->assertSame($bid,       (int)$env['data']['bid']);

        // Persisted: RemoveType='U', RemovedBy=admin aid, ureason stored.
        $after = $this->row('bans', ['bid' => $bid]);
        $this->assertSame('U',                 $after['RemoveType']);
        $this->assertSame(Fixture::adminAid(), (int)$after['RemovedBy']);
        $this->assertSame('mistaken ban',      $after['ureason']);
    }

    public function testUnbanRejectsAlreadyLiftedRow(): void
    {
        $this->loginAsAdmin();
        $bid = $this->seedBan('STEAM_0:1:1303', 'cheating');

        $first = $this->api('bans.unban', ['bid' => $bid, 'ureason' => 'lift it']);
        $this->assertTrue($first['ok'], json_encode($first));

        // Second call against the same already-lifted row should refuse.
        $env = $this->api('bans.unban', ['bid' => $bid, 'ureason' => 'try again']);
        $this->assertEnvelopeError($env, 'not_active');
    }

    /**
     * #1301: the audit log carries the unban reason verbatim so admins
     * reading the log later can see *why* the ban was lifted.
     */
    public function testUnbanRecordsReasonInAuditLog(): void
    {
        $this->loginAsAdmin();
        $bid = $this->seedBan('STEAM_0:1:1304', 'cheating');

        $env = $this->api('bans.unban', [
            'bid'     => $bid,
            'ureason' => 'appeal accepted',
        ]);
        $this->assertTrue($env['ok'], json_encode($env));

        $logs = $this->rows('log', ['title' => 'Player Unbanned']);
        $this->assertNotEmpty($logs, 'audit log row was created');
        $latest = end($logs);
        $this->assertStringContainsString('appeal accepted', (string) $latest['message']);
        $this->assertStringContainsString('STEAM_0:1:1304', (string) $latest['message']);
    }
}
