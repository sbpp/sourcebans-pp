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

    private function seedDemoForBan(int $bid, string $filename = 'testdemo1464.dem'): void
    {
        $path = SB_DEMOS . '/' . $filename;
        if (!is_dir(SB_DEMOS)) {
            mkdir(SB_DEMOS, 0775, true);
        }
        file_put_contents($path, 'demo-bytes');
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_demos` (demid, demtype, filename, origname) VALUES (?, ?, ?, ?)',
            DB_PREFIX
        ))->execute([$bid, 'B', $filename, 'evidence.dem']);
    }

    private function createAdminWithFlags(int $mask): int
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, validate, extraflags, immunity)
             VALUES (?, ?, ?, -1, ?, NULL, ?, 50)',
            DB_PREFIX,
        ));
        $stmt->execute([
            'flagged-' . $mask,
            'STEAM_0:0:' . (4_000_000 + $mask),
            password_hash('x', PASSWORD_BCRYPT),
            'flagged-' . $mask . '@example.test',
            $mask,
        ]);
        return (int) $pdo->lastInsertId();
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

    /**
     * #1420 — regression: a SteamID input the `SteamID` library
     * can't pattern-match used to escape from `api_bans_add` as a
     * generic `\Exception("Invalid SteamID input!")` thrown deep
     * inside `SteamID::resolveInputID()`. The dispatcher caught it
     * in the catch-all `Throwable` arm and surfaced a 500 with
     * body "An unexpected error occurred", which the chrome read
     * as a server error rather than a per-field validation
     * failure. The fix validates the SteamID shape against a
     * strict anchored regex BEFORE handing it to
     * `SteamID::toSteam2()`. `SteamID::isValidID()` on its own is
     * not a sufficient gate — its regexes are unanchored with
     * loose character classes, so a substring containing a
     * valid-looking SteamID passes (and `toSteam2()` then either
     * round-trips the corrupt input verbatim or emits a
     * negative-Z-component canonical form into the DB). Comms-add
     * and admins-add carry the same regex; all three handlers
     * stay in lockstep so a future caller only has to learn one
     * accepted shape.
     */
    public function testAddRejectsInvalidSteamIdShapeForType0(): void
    {
        // Mirrors CommsTest::testAddRejectsInvalidSteamIdShape — every
        // case the strict regex rejects must be rejected here too so
        // the three handlers (api_bans_add / api_comms_add /
        // api_admins_add) stay in lockstep.
        $this->loginAsAdmin();
        foreach (
            [
                'asdf',                            // reporter's example
                '12345',                           // short numeric
                '7656119xxxxxxxxxx',               // 17 chars, not 17 digits
                'STEAM_0:0:',                      // empty Z; pre-fix `\d*` accepted
                'asdfSTEAM_0:0:123',               // substring-bypass (unanchored)
                'asdf 76561197960265728 garbage',  // embedded Steam64 — pre-fix corrupted
                'U:1:1',                           // bracketless Steam3 — form pattern requires brackets
                // #1423 follow-up #4 — bypass shapes that `trim()`
                // upstream of the gate does NOT defend against. The
                // common trailing-newline case `"STEAM_0:0:1\n"` is
                // stripped by `trim()` before the regex sees it —
                // that path is fine, and the library-level
                // `HANDLER_STRICT_REGEX` test pins the regex's
                // newline-bypass rejection directly
                // (`SteamIDValidationTest::testHandlerStrictRegexRejectsNewlineBypass`).
                // The cases below are what the handler-side gate
                // actually has to catch — mid-string `\n` (not
                // strippable) and unicode whitespace like NBSP
                // (`trim()` only handles the ASCII whitespace set
                // `\t\n\v\f\r ` + null + the spec-listed printable
                // whitespace). The strict gate's `^…$` anchors +
                // non-`m` mode require the whole string match the
                // pattern; embedded `\n` is rejected.
                "STEAM_0:0:1\nGARBAGE",
                "GARBAGE\nSTEAM_0:0:1",
                "STEAM_0:0:1\xC2\xA0",             // trailing NBSP (U+00A0); `trim` does NOT strip
            ] as $badSteam
        ) {
            $env = $this->api('bans.add', [
                'nickname' => 'X',
                'type'     => 0,
                'steam'    => $badSteam,
                'ip'       => '',
                'length'   => 0,
                'reason'   => '',
                'fromsub'  => 0,
            ]);
            $this->assertEnvelopeError($env, 'validation');
            $this->assertSame(
                'steam',
                $env['error']['field'],
                sprintf('expected error.field=steam for steam=%s', var_export($badSteam, true)),
            );
        }
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

    /**
     * #1486 — IP-type bans keep a Steam ID-of-record when the operator
     * fills both fields, instead of silently dropping it. The schema has
     * always had room (separate `ip` + `authid` columns); the previous
     * code hard-cleared `authid` for `type=1`. Enforcement stays IP-only
     * (the SourceMod plugin matches an IP ban on the `ip` column alone),
     * so the recorded authid is inert plugin-side; it exists so the ban
     * detail / banlist can show which account the IP belonged to.
     *
     * The shape gate from #1423 follow-up #4 is preserved: a non-empty
     * Steam ID on an IP ban is validated BEFORE `toSteam2()` so a garbage
     * value can't escape as `Exception('Invalid SteamID input!')` → 500
     * envelope. Garbage now returns a structured `validation` error on
     * the `steam` field instead of either 500ing (the original bug) or
     * being silently swallowed (the pre-#1486 drop). An empty Steam ID
     * field on an IP ban records nothing.
     */
    public function testAddIpTypeKeepsValidatedSteamOfRecord(): void
    {
        $this->loginAsAdmin();
        // 1) Valid Steam-shape `steam` with `type=1` (IP-type ban).
        //    The canonical SteamID is stored alongside the IP.
        $env = $this->api('bans.add', [
            'nickname' => 'IpType_validSteam', 'type' => 1,
            'steam' => 'STEAM_0:1:9999', 'ip' => '203.0.113.42',
            'length' => 0, 'reason' => 'ip ban with steam input',
            'fromsub' => 0,
        ]);
        $this->assertTrue($env['ok'], 'IP-type ban with a valid steam should succeed');
        $row = $this->row('bans', ['ip' => '203.0.113.42']);
        $this->assertSame('STEAM_0:1:9999', (string) $row['authid'], 'IP-type ban keeps the recorded steam');
        $this->assertSame('1', (string) $row['type']);

        // 2) No `steam` typed with `type=1`: nothing recorded, empty authid.
        $env = $this->api('bans.add', [
            'nickname' => 'IpType_noSteam', 'type' => 1,
            'steam' => '', 'ip' => '203.0.113.45',
            'length' => 0, 'reason' => 'ip ban without steam input',
            'fromsub' => 0,
        ]);
        $this->assertTrue($env['ok'], 'IP-type ban with no steam should succeed');
        $row = $this->row('bans', ['ip' => '203.0.113.45']);
        $this->assertSame('', (string) $row['authid'], 'IP-type ban without steam writes empty authid');

        // 3) Garbage `steam` with `type=1`. Pre-#1423-follow-up-#4 this
        //    raised `Exception('Invalid SteamID input!')` → 500 envelope.
        //    The shape gate now rejects it with a structured validation
        //    error (NOT a 500, NOT a silent drop).
        $env = $this->api('bans.add', [
            'nickname' => 'IpType_garbageSteam', 'type' => 1,
            'steam' => 'garbage', 'ip' => '203.0.113.43',
            'length' => 0, 'reason' => 'ip ban with garbage steam input',
            'fromsub' => 0,
        ]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('steam', $env['error']['field']);
        $this->assertNull($this->row('bans', ['ip' => '203.0.113.43']), 'garbage steam must NOT create an IP ban row');

        // 4) Non-trimmable malformed `steam` with `type=1`. The handler
        //    trims `$rawSteam` first, so a trailing newline (`STEAM_0:0:1\n`)
        //    is stripped to the valid `STEAM_0:0:1` (see case 5) — the gate
        //    only has to catch shapes `trim()` can't fix. A MID-string
        //    newline is the canonical such case, mirroring the Steam-branch
        //    lockstep in testAddRejectsInvalidSteamIdShapeForType0.
        $env = $this->api('bans.add', [
            'nickname' => 'IpType_malformedSteam', 'type' => 1,
            'steam' => "STEAM_0:0:1\nGARBAGE", 'ip' => '203.0.113.44',
            'length' => 0, 'reason' => 'ip ban with malformed steam input',
            'fromsub' => 0,
        ]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('steam', $env['error']['field']);
        $this->assertNull($this->row('bans', ['ip' => '203.0.113.44']), 'malformed steam must NOT create an IP ban row');

        // 5) Trailing-newline `steam` with `type=1`: `trim()` strips it to
        //    the valid `STEAM_0:0:1`, which is then kept as the record (the
        //    handler is symmetric with the Steam branch here — the regex's
        //    own newline-bypass rejection is pinned at the library level by
        //    SteamIDValidationTest::testHandlerStrictRegexRejectsNewlineBypass).
        $env = $this->api('bans.add', [
            'nickname' => 'IpType_trailingNewline', 'type' => 1,
            'steam' => "STEAM_0:0:1\n", 'ip' => '203.0.113.46',
            'length' => 0, 'reason' => 'ip ban with trailing-newline steam',
            'fromsub' => 0,
        ]);
        $this->assertTrue($env['ok'], 'trailing-newline trims to a valid steam and is kept');
        $row = $this->row('bans', ['ip' => '203.0.113.46']);
        $this->assertSame('STEAM_0:0:1', (string) $row['authid'], 'trimmed steam is stored on the IP ban');
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

    public function testAddDuplicateErrorIncludesConflictingBid(): void
    {
        // Pre-seed a non-#1 ban so this asserts the bid is actually
        // substituted into the error message rather than always rendering
        // `#1` because the duplicate is the second row in the table.
        // The reported regression (#STEAM_0:0:1000119) confused operators
        // because the bare "is already banned" string gave them no anchor
        // to look up the conflicting row — they saw an unbanned row in
        // the UI and reasonably concluded the panel was lying. The fix
        // surfaces the conflicting bid; this test pins that contract.
        $this->seedBan('STEAM_0:1:7000', 'first-noise');
        $this->seedBan('STEAM_0:1:7001', 'second-noise');
        $conflictBid = $this->seedBan('STEAM_0:1:9999', 'active-original');

        $this->loginAsAdmin();
        $env = $this->api('bans.add', [
            'nickname' => 'Reban', 'type' => 0, 'steam' => 'STEAM_0:1:9999',
            'ip' => '', 'length' => 0, 'dfile' => '', 'dname' => '',
            'reason' => 'reban-attempt', 'fromsub' => 0,
        ]);
        $this->assertEnvelopeError($env, 'already_banned');
        $this->assertSame(
            'SteamID: STEAM_0:1:9999 is already banned by ban #' . $conflictBid . '.',
            $env['error']['message']
        );
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

    public function testDetailHidesCommentAuthorForPublicWhenHideAdminName(): void
    {
        // #1500: with public comments ENABLED, the comment author/editor
        // (admin usernames) must still be suppressed for public callers
        // under banlist.hideadminname — same as the focal admin.name and
        // removed_by fields. Pre-fix the handler leaked them: the
        // testDetailPublicViewHidesAdminFields case above turns public
        // comments OFF, so it never exercised this combination.
        Fixture::rawPdo()->prepare(sprintf(
            "REPLACE INTO `%s_settings` (`setting`, `value`) VALUES
                ('banlist.hideadminname', '1'),
                ('config.enablepubliccomments', '1')",
            DB_PREFIX
        ))->execute();
        \Config::init($GLOBALS['PDO']);

        $bid = $this->seedBan('STEAM_0:1:1500', 'comment-author-leak');
        // Author AND editor set to the seeded admin so both subqueries
        // resolve to a real username (the value that must NOT leak).
        Fixture::rawPdo()->prepare(sprintf(
            "INSERT INTO `%s_comments` (`bid`, `type`, `aid`, `editaid`, `commenttxt`, `added`, `edittime`)
             VALUES (?, 'B', ?, ?, ?, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())",
            DB_PREFIX
        ))->execute([$bid, Fixture::adminAid(), Fixture::adminAid(), 'leaky comment']);

        // Public caller (not logged in).
        $env = $this->api('bans.detail', ['bid' => $bid]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertTrue($env['data']['comments_visible'], 'public comments are enabled');
        $this->assertCount(1, $env['data']['comments']);
        $this->assertSame('leaky comment', $env['data']['comments'][0]['text']);
        $this->assertNull($env['data']['comments'][0]['author'],
            'comment author must be hidden for public + hideadminname (#1500)');
        $this->assertNull($env['data']['comments'][0]['edited_by'],
            'comment editor must be hidden for public + hideadminname (#1500)');
        $this->assertTrue($env['data']['comments'][0]['author_hidden'],
            'author_hidden sentinel must be true when a real name was suppressed (#1500 m1: lets the drawer render "Hidden" vs "unknown")');
        $this->assertNull($env['data']['admin']['name'], 'focal admin name is hidden too');

        // Admins still see the author + editor; author_hidden is false.
        $this->loginAsAdmin();
        $adminEnv = $this->api('bans.detail', ['bid' => $bid]);
        $this->assertTrue($adminEnv['ok'], json_encode($adminEnv));
        $this->assertNotNull($adminEnv['data']['comments'][0]['author'],
            'admins still see the comment author');
        $this->assertNotNull($adminEnv['data']['comments'][0]['edited_by'],
            'admins still see the comment editor');
        $this->assertFalse($adminEnv['data']['comments'][0]['author_hidden'],
            'author_hidden must be false for admin viewers (#1500 m1)');
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

    public function testDetailIpTypeBanReportsNoSteamOrCommunityId(): void
    {
        // #1486: an IP-type ban with NO recorded SteamID stores an empty
        // authid (the operator only filled the IP field). community_id is
        // computed in SQL straight off authid, so an empty authid collapsed
        // the arithmetic to the base 76561197960265728 (STEAM_0:0:0) and the
        // drawer rendered a bogus "Community" id. steam_id / steam_id_3 were
        // already gated on SteamID::isValidID(); community_id must match.
        // (The "filled both fields" case now keeps the SteamID — see
        // testAddIpTypeKeepsValidatedSteamOfRecord; this test pins the
        // IP-only shape where the guard is load-bearing.) The IP is the
        // row's real identity, so it must still surface. Assert as admin so
        // banlist.hideplayerips can't mask the IP regardless of seed defaults.
        $this->loginAsAdmin();
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (created, type, ip, authid, name, ends, length, reason, aid, adminIp)
             VALUES (UNIX_TIMESTAMP(), 1, ?, "", ?, UNIX_TIMESTAMP(), 0, ?, ?, "127.0.0.1")',
            DB_PREFIX
        ))->execute(['203.0.113.77', 'IpOnly', 'ip-type ban', Fixture::adminAid()]);
        $bid = (int) $pdo->lastInsertId();

        $env = $this->api('bans.detail', ['bid' => $bid]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSame(1, $env['data']['player']['type'], 'seeded ban is IP-type');
        $this->assertSame('', $env['data']['player']['steam_id']);
        $this->assertSame('', $env['data']['player']['steam_id_3']);
        $this->assertSame('', $env['data']['player']['community_id'],
            'IP-type ban (empty authid) must not surface a synthetic community id (#1486)');
        $this->assertSame('203.0.113.77', $env['data']['player']['ip'],
            'IP-type ban must still surface the IP as the row identity (#1486)');
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

    // -- bans.player_history with `authid` parameter (#COMMS-DRAWER) -------

    public function testPlayerHistoryAcceptsAuthidWithoutBid(): void
    {
        // Drawer parity: the comm-focal drawer JS sends
        // `{authid: <steamid>}` to bans.player_history because there's
        // no anchor `bid` in :prefix_bans (the focal record is on
        // :prefix_comms). The handler must accept the authid path,
        // skip the bid lookup, and skip the focal-ban exclusion clause
        // (no focal bid to exclude — every matching row is "other").
        $this->loginAsAdmin();
        $this->seedBan('STEAM_0:1:6060', 'first');
        $this->seedBan('STEAM_0:1:6060', 'second');

        $env = $this->api('bans.player_history', ['authid' => 'STEAM_0:1:6060']);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSame(2, (int)$env['data']['total']);
        $this->assertCount(2, $env['data']['items']);
    }

    public function testPlayerHistoryRejectsCallWithoutBidOrAuthid(): void
    {
        // Pre-#COMMS-DRAWER the handler validated only `bid`; now either
        // shape is accepted, but a call with neither is still a
        // bad_request to preserve the legacy contract.
        $env = $this->api('bans.player_history', []);
        $this->assertEnvelopeError($env, 'bad_request');
        $this->assertSame('bid', $env['error']['field']);
    }

    public function testPlayerHistoryAuthidPathReturnsEmptyForUnknownPlayer(): void
    {
        // No ban rows for this authid -> empty feed (NOT 404). The
        // handler's empty-feed branch lets the drawer render the
        // "No prior bans on file" empty state cleanly.
        $env = $this->api('bans.player_history', ['authid' => 'STEAM_0:1:9999999']);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSame(0, (int)$env['data']['total']);
        $this->assertSame([], $env['data']['items']);
    }

    public function testPlayerHistoryBidPathExcludesFocalBan(): void
    {
        // Bans-focal drawer contract: the History tab calls
        // `bans.player_history({bid: <focal>})` and the handler's
        // `BA.bid <> ?` clause excludes the focal record so the
        // Overview pane and the History pane never render the same
        // row twice. Sister regression to
        // `testPlayerHistoryCidPathExcludesFocalRecord` on
        // CommsTest — both paths share the focal-exclusion contract.
        $this->loginAsAdmin();
        $focalBid    = $this->seedBan('STEAM_0:1:6065', 'focal ban');
        $siblingBid  = $this->seedBan('STEAM_0:1:6065', 'sibling ban');

        $env = $this->api('bans.player_history', ['bid' => $focalBid]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSame(1, (int)$env['data']['total']);
        $this->assertCount(1, $env['data']['items']);
        $this->assertSame($siblingBid, (int)$env['data']['items'][0]['bid']);
    }

    public function testPlayerHistoryBidPathReturnsEmptyWhenOnlyFocalExists(): void
    {
        // Lone-ban / first-ban-on-file path: the handler must return
        // an empty feed (NOT a 404, NOT the focal itself) so the
        // drawer's History pane renders its "No prior bans on file"
        // empty state cleanly. A regression that flipped `BA.bid <> ?`
        // to a tautology (or reused the bid as authid) would silently
        // pass the focal-exclusion test above (still excluded) but
        // fail here (sibling=focal would slip through).
        $this->loginAsAdmin();
        $bid = $this->seedBan('STEAM_0:1:6066', 'lone ban');

        $env = $this->api('bans.player_history', ['bid' => $bid]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertSame(0, (int)$env['data']['total']);
        $this->assertSame([], $env['data']['items']);
    }

    public function testRemoveDemoRejectsAnonymous(): void
    {
        $bid = $this->seedBan();
        $env = $this->api('bans.remove_demo', ['bid' => $bid]);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testRemoveDemoNotFoundWhenNoDemoAttached(): void
    {
        $this->loginAsAdmin();
        $bid = $this->seedBan();
        $env = $this->api('bans.remove_demo', ['bid' => $bid]);
        $this->assertEnvelopeError($env, 'not_found');
    }

    public function testRemoveDemoForbiddenWhenAdminCannotEditBan(): void
    {
        $bid = $this->seedBan();
        $this->seedDemoForBan($bid, 'forbidden1464.dem');
        $aid = $this->createAdminWithFlags(ADMIN_ADD_BAN);
        $this->loginAs($aid);

        $env = $this->api('bans.remove_demo', ['bid' => $bid]);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testRemoveDemoSuccess(): void
    {
        $this->loginAsAdmin();
        $bid = $this->seedBan();
        $filename = 'testdemo1464.dem';
        $this->seedDemoForBan($bid, $filename);
        $path = SB_DEMOS . '/' . $filename;

        $env = $this->api('bans.remove_demo', ['bid' => $bid]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertTrue($env['data']['removed']);

        $pdo = Fixture::rawPdo();
        $row = $pdo->prepare(sprintf(
            'SELECT COUNT(*) FROM `%s_demos` WHERE demid = ? AND UPPER(demtype) = ?',
            DB_PREFIX
        ));
        $row->execute([$bid, 'B']);
        $this->assertSame(0, (int) $row->fetchColumn());
        $this->assertFileDoesNotExist($path);
    }
}
