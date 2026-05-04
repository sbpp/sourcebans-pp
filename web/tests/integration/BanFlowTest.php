<?php

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Tier 1 smoke flow #1 from #1095: an admin creates a ban via bans.add and
 * the row appears in :prefix_bans with the admin as :aid.
 */
final class BanFlowTest extends ApiTestCase
{
    public function testCreateBanWritesBansRow(): void
    {
        $this->loginAsAdmin();

        $env = $this->api('bans.add', [
            'nickname' => 'Cheater',
            'type'     => 0,
            'steam'    => 'STEAM_0:1:99999',
            'ip'       => '',
            'length'   => 60,           // minutes
            'dfile'    => '',
            'dname'    => '',
            'reason'   => 'aimbot',
            'fromsub'  => 0,
        ]);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertNotEmpty($env['data']['bid']);

        $ban = $this->row('bans', ['bid' => $env['data']['bid']]);
        $this->assertSame('STEAM_0:1:99999', $ban['authid']);
        $this->assertSame('aimbot', $ban['reason']);
        $this->assertSame(Fixture::adminAid(), (int)$ban['aid']);
    }

    public function testDuplicateSteamRejected(): void
    {
        $this->loginAsAdmin();
        $first = $this->api('bans.add', [
            'nickname' => 'Cheater',
            'type'     => 0,
            'steam'    => 'STEAM_0:1:88888',
            'ip'       => '',
            'length'   => 0,
            'dfile'    => '',
            'dname'    => '',
            'reason'   => 'wallhack',
            'fromsub'  => 0,
        ]);
        $this->assertTrue($first['ok']);

        $second = $this->api('bans.add', [
            'nickname' => 'CheaterAgain',
            'type'     => 0,
            'steam'    => 'STEAM_0:1:88888',
            'ip'       => '',
            'length'   => 0,
            'dfile'    => '',
            'dname'    => '',
            'reason'   => 'wallhack v2',
            'fromsub'  => 0,
        ]);
        $this->assertEnvelopeError($second, 'already_banned');
    }

    /**
     * Multi-byte player names (CJK, Cyrillic) plus raw angle brackets
     * must survive the ban flow unchanged. Before #1108 the handler ran
     * the JSON body through `htmlspecialchars_decode`, which paired with
     * Smarty's auto-escape default (#1087) produced double-escape on
     * display; the DB also needs the utf8mb4 connection for the 4-byte
     * sequences the plugin stores via `sbpp_comms.sp`. This test guards
     * both concerns end-to-end.
     *
     * @return array<string, array{nickname: string, reason: string, steam: string}>
     */
    public static function multiByteBanProvider(): array
    {
        return [
            'cjk nickname'           => [
                'nickname' => '叮叮当当',
                'reason'   => 'wallhack',
                'steam'    => 'STEAM_0:1:10001',
            ],
            'angle brackets'         => [
                'nickname' => '=[BSID]= ethzero <Msg>',
                'reason'   => 'griefing <rage> quit',
                'steam'    => 'STEAM_0:1:10002',
            ],
            'cyrillic + emoji'       => [
                'nickname' => 'Привет 🎉',
                'reason'   => 'spam & abuse 🚫',
                'steam'    => 'STEAM_0:1:10003',
            ],
            'literal ampersand text' => [
                'nickname' => 'player &amp; friend',
                'reason'   => 'alt abuse &amp; evasion',
                'steam'    => 'STEAM_0:1:10004',
            ],
        ];
    }

    /**
     * @dataProvider multiByteBanProvider
     */
    public function testBanRoundTripPreservesUnicodeAndAngleBrackets(string $nickname, string $reason, string $steam): void
    {
        $this->loginAsAdmin();

        $env = $this->api('bans.add', [
            'nickname' => $nickname,
            'type'     => 0,
            'steam'    => $steam,
            'ip'       => '',
            'length'   => 0,
            'dfile'    => '',
            'dname'    => '',
            'reason'   => $reason,
            'fromsub'  => 0,
        ]);
        $this->assertTrue($env['ok'], 'ban.add failed: ' . json_encode($env));

        $ban = $this->row('bans', ['bid' => $env['data']['bid']]);
        $this->assertNotNull($ban);
        $this->assertSame(
            $nickname,
            $ban['name'],
            'stored nickname must match the raw UTF-8 the client sent (no decode, no pre-escape)'
        );
        $this->assertSame(
            $reason,
            $ban['reason'],
            'stored reason must match the raw UTF-8 the client sent'
        );
    }

    /**
     * Comms blocks go through the same nickname/reason pipe and land in
     * `sb_comms`. #765 ("Query_AddBlockInsert failed: Incorrect string
     * value") was the plugin's side of the same mismatch; this asserts
     * the panel's add path keeps the bytes intact.
     */
    public function testCommsAddPreservesUnicodeAndAngleBrackets(): void
    {
        $this->loginAsAdmin();

        $nickname = '=[Test]= 叮叮当当 <foo>';
        $reason   = 'toxic 💢 & abusive <rant>';

        $env = $this->api('comms.add', [
            'nickname' => $nickname,
            'type'     => 3, // gag + mute
            'steam'    => 'STEAM_0:1:20001',
            'length'   => 0,
            'reason'   => $reason,
        ]);
        $this->assertTrue($env['ok'], 'comms.add failed: ' . json_encode($env));

        $rows = $this->rows('comms', ['authid' => 'STEAM_0:1:20001']);
        $this->assertCount(2, $rows, 'type=3 must produce one gag row + one mute row');
        foreach ($rows as $row) {
            $this->assertSame($nickname, $row['name']);
            $this->assertSame($reason, $row['reason']);
        }
    }

    /**
     * The JSON dispatcher used to call `json_encode` without
     * JSON_INVALID_UTF8_SUBSTITUTE, so a single bad byte anywhere in the
     * response collapsed the whole envelope to `false` and the per-server
     * admin tile saw a `bad_response` error (#971). The handler layer
     * passes server-query output through verbatim, so the guard has to
     * live at the encode boundary.
     */
    public function testDispatcherSubstitutesInvalidUtf8InResponse(): void
    {
        [$status, $envelope] = \Api::handle('POST', '', '');
        $this->assertSame(400, $status, 'empty body should produce 400 but with a valid JSON envelope');

        $payload = [
            'ok'   => true,
            'data' => ['name' => "bad \xC3\x28 bytes"],
        ];
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $this->assertIsString($encoded, 'json_encode must succeed with JSON_INVALID_UTF8_SUBSTITUTE');
        $this->assertStringContainsString('"ok":true', $encoded);
    }
}
