<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Per-handler coverage for web/api/handlers/system.php.
 *
 * The system handlers are mostly thin wrappers around external state
 * (mail, theme files on disk, the upstream version JSON), so the tests
 * here focus on the structured-error envelopes the panel actually
 * branches on, plus the local-only side effects that don't need
 * working RCON / SMTP / network.
 */
final class SystemTest extends ApiTestCase
{
    public function testCheckVersionIsPublicAndReturnsShape(): void
    {
        // Public action; the handler hits a remote URL but the envelope
        // shape is the same regardless of fetch success.
        $env = $this->api('system.check_version', []);
        $this->assertTrue($env['ok']);
        $this->assertArrayHasKey('release_latest', $env['data']);
        $this->assertArrayHasKey('release_msg',    $env['data']);
        $this->assertArrayHasKey('release_update', $env['data']);
        $this->assertArrayHasKey('dev',            $env['data']);
    }

    public function testSelThemeRejectsBlank(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('system.sel_theme', ['theme' => '']);
        $this->assertEnvelopeError($env, 'invalid_theme');
        $this->assertSnapshot('system/sel_theme_invalid', $env);
    }

    public function testSelThemeRejectsTraversal(): void
    {
        // The handler scrubs `../` and `..\\` before basename(), so any
        // injected traversal turns into a stripped basename that won't
        // resolve to a real theme dir.
        $this->loginAsAdmin();
        $env = $this->api('system.sel_theme', ['theme' => '../etc/passwd']);
        $this->assertEnvelopeError($env, 'invalid_theme');
    }

    public function testSelThemeRejectsAnonymous(): void
    {
        $env = $this->api('system.sel_theme', ['theme' => 'default']);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testApplyThemeRejectsInvalid(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('system.apply_theme', ['theme' => 'no-such-theme']);
        $this->assertEnvelopeError($env, 'invalid_theme');
    }

    public function testApplyThemeWritesSetting(): void
    {
        // The dev panel ships only `default`. Pin `config.theme` to
        // something else first so we can prove the handler updated it.
        Fixture::rawPdo()->prepare(sprintf(
            "REPLACE INTO `%s_settings` (`setting`, `value`) VALUES ('config.theme', 'old')",
            DB_PREFIX
        ))->execute();

        $this->loginAsAdmin();
        $env = $this->api('system.apply_theme', ['theme' => 'default']);
        $this->assertTrue($env['ok'], json_encode($env));

        $val = Fixture::rawPdo()->query(sprintf(
            "SELECT value FROM `%s_settings` WHERE setting = 'config.theme'",
            DB_PREFIX
        ))->fetchColumn();
        $this->assertSame('default', $val);
        $this->assertSnapshot('system/apply_theme_success', $env);
    }

    public function testApplyThemeRejectsAnonymous(): void
    {
        $env = $this->api('system.apply_theme', ['theme' => 'default']);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testClearCacheReportsClearedTrue(): void
    {
        $this->loginAsAdmin();

        // Pre-seed a file so we can prove it's gone afterwards.
        $cache = SB_CACHE;
        if (!is_dir($cache) && !@mkdir($cache, 0o775, true) && !is_dir($cache)) {
            $this->markTestSkipped('cache dir not writable');
        }
        file_put_contents($cache . 'sentinel.txt', 'remove me');

        $env = $this->api('system.clear_cache', []);
        $this->assertTrue($env['ok']);
        $this->assertTrue($env['data']['cleared']);
        $this->assertFileDoesNotExist($cache . 'sentinel.txt');
        $this->assertSnapshot('system/clear_cache_success', $env);
    }

    public function testClearCacheRejectsAnonymous(): void
    {
        $env = $this->api('system.clear_cache', []);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testSendMailRejectsBadType(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('system.send_mail', [
            'subject' => 'x', 'message' => 'y', 'type' => 'q', 'id' => 1,
        ]);
        $this->assertEnvelopeError($env, 'bad_type');
        $this->assertSnapshot('system/send_mail_bad_type', $env);
    }

    public function testSendMailRejectsMissingEmail(): void
    {
        $this->loginAsAdmin();
        // No submission seeded → handler reads $row['email'] = '' and
        // throws no_email.
        $env = $this->api('system.send_mail', [
            'subject' => 'x', 'message' => 'y', 'type' => 's', 'id' => 9999,
        ]);
        $this->assertEnvelopeError($env, 'no_email');
        $this->assertSnapshot('system/send_mail_no_email', $env);
    }

    public function testSendMailReportsMailFailureForKnownAddress(): void
    {
        // Seed a submission with an email so we hit the Mail::send path.
        // Without working SMTP the handler turns the false return into
        // the structured `mail_failed` envelope. The wire shape of that
        // failure is what we lock down.
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_submissions`
              (`name`, `SteamId`, `email`, `reason`, `archiv`, `submitted`, `ModID`, `ip`, `server`)
             VALUES (?, ?, ?, ?, "0", ?, 0, "127.0.0.1", 0)',
            DB_PREFIX
        ))->execute(['MailMe', 'STEAM_0:0:1', 'mailme@example.test', 'reason', time()]);
        $sid = (int)$pdo->lastInsertId();

        $this->loginAsAdmin();
        $env = $this->api('system.send_mail', [
            'subject' => 'hi', 'message' => 'hello', 'type' => 's', 'id' => $sid,
        ]);
        $this->assertEnvelopeError($env, 'mail_failed');
        $this->assertSnapshot('system/send_mail_failed', $env);
    }

    public function testSendMailRejectsAnonymous(): void
    {
        $env = $this->api('system.send_mail', [
            'subject' => 'x', 'message' => 'y', 'type' => 's', 'id' => 1,
        ]);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testRehashAdminsReturnsResultsListWithoutServers(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('system.rehash_admins', ['servers' => '']);
        $this->assertTrue($env['ok']);
        $this->assertSame([], $env['data']['results']);
        $this->assertSnapshot('system/rehash_admins_empty', $env);
    }

    public function testRehashAdminsReportsRconFailureForUnknownSid(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('system.rehash_admins', ['servers' => '9999']);
        $this->assertTrue($env['ok']);
        $this->assertCount(1, $env['data']['results']);
        $this->assertSame(9999, (int)$env['data']['results'][0]['sid']);
        $this->assertFalse($env['data']['results'][0]['success']);
    }

    public function testRehashAdminsRejectsAnonymous(): void
    {
        $env = $this->api('system.rehash_admins', ['servers' => '']);
        $this->assertEnvelopeError($env, 'forbidden');
    }
}
