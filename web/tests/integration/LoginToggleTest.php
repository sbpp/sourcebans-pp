<?php

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Issue #1102: normal-auth login is gated by `config.enablenormallogin`,
 * not `config.enablesteamlogin`.
 *
 * Four things are exercised here:
 *   1. Disabling the Steam-login button must NOT block normal-auth logins.
 *   2. Disabling normal login does block auth.login.
 *   3. Disabling normal login also blocks auth.lost_password (a reset
 *      password is unusable when normal login is off, and the form would
 *      otherwise let a visitor probe for registered email addresses).
 *   4. The 802 updater step inserts the new key when upgrading from a
 *      panel that pre-dates it.
 */
final class LoginToggleTest extends ApiTestCase
{
    private function setSetting(string $key, string $value): void
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'REPLACE INTO `%s_settings` (`setting`, `value`) VALUES (?, ?)',
            DB_PREFIX
        ));
        $stmt->execute([$key, $value]);

        // Refresh the in-process Config cache so subsequent handler calls
        // see the new value.
        \Config::init($GLOBALS['PDO']);
    }

    private function deleteSetting(string $key): void
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'DELETE FROM `%s_settings` WHERE `setting` = ?',
            DB_PREFIX
        ));
        $stmt->execute([$key]);
        \Config::init($GLOBALS['PDO']);
    }

    private function readSetting(string $key): ?string
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'SELECT value FROM `%s_settings` WHERE `setting` = ?',
            DB_PREFIX
        ));
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row === false ? null : (string)$row['value'];
    }

    public function testDisablingSteamLoginDoesNotBlockNormalLogin(): void
    {
        $this->setSetting('config.enablesteamlogin', '0');
        $this->setSetting('config.enablenormallogin', '1');

        // Regression guard for issue #1102: pre-fix, auth.login was gated on
        // `config.enablesteamlogin`, so this call would short-circuit (never
        // reaching NormalAuthHandler) and the lockout counter below would
        // stay at 0 instead of advancing to 1.
        $env = $this->api('auth.login', ['username' => 'admin', 'password' => 'wrong']);
        $this->assertSame('?p=login&m=failed', $env['redirect'] ?? null);

        $row = $this->row('admins', ['user' => 'admin']);
        $this->assertSame(1, (int)$row['attempts'],
            'Wrong password should still increment the lockout counter when steam login is disabled.');

        // Pre-fix the short-circuit also emitted a bogus "Hacking attempt"
        // log entry; that line is gone now.
        $hackingLogs = Fixture::rawPdo()->query(sprintf(
            "SELECT COUNT(*) FROM `%s_log` WHERE title = 'Hacking attempt'",
            DB_PREFIX
        ))->fetchColumn();
        $this->assertSame(0, (int)$hackingLogs,
            'Disabled-feature rejections must not be logged as hacking attempts.');
    }

    public function testDisablingNormalLoginBlocksAuthLogin(): void
    {
        $this->setSetting('config.enablenormallogin', '0');

        $env = $this->api('auth.login', ['username' => 'admin', 'password' => 'admin']);
        $this->assertSame('?p=login&m=failed', $env['redirect'] ?? null);

        // Lockout counter must not advance: we never reached NormalAuthHandler.
        $row = $this->row('admins', ['user' => 'admin']);
        $this->assertSame(0, (int)$row['attempts'],
            'Disabled-feature short-circuit must not advance the lockout counter.');

        // And no "Hacking attempt" log entry either.
        $hackingLogs = Fixture::rawPdo()->query(sprintf(
            "SELECT COUNT(*) FROM `%s_log` WHERE title = 'Hacking attempt'",
            DB_PREFIX
        ))->fetchColumn();
        $this->assertSame(0, (int)$hackingLogs);
    }

    public function testDisablingNormalLoginAlsoBlocksLostPassword(): void
    {
        $this->setSetting('config.enablenormallogin', '0');

        // The handler should refuse with a structured ApiError envelope, not
        // probe the admins table or send mail. (Pre-fix the form's link was
        // hidden in the template but the handler URL was still reachable.)
        $env = $this->api('auth.lost_password', ['email' => 'admin@example.test']);
        $this->assertEnvelopeError($env, 'disabled');
    }

    public function testUpdater802InsertsEnableNormalLoginIfMissing(): void
    {
        // Simulate a panel upgraded from a release that pre-dates this key.
        $this->deleteSetting('config.enablenormallogin');
        $this->assertNull($this->readSetting('config.enablenormallogin'),
            'Pre-condition: the key should be absent before the migration runs.');

        // Run the migration in a context that exposes the same `$this->dbs`
        // shape the real Updater hands its migration files. Using `require`
        // (not require_once) so this test can also run after the production
        // updater code-path has loaded the file.
        $ctx = new class($GLOBALS['PDO']) {
            public function __construct(public \Database $dbs) {}
            public function run(string $path): mixed
            {
                return require $path;
            }
        };
        $ok = $ctx->run(ROOT . 'updater/data/802.php');

        $this->assertTrue($ok, 'Migration should report success.');
        $this->assertSame('1', $this->readSetting('config.enablenormallogin'),
            'Migration must default the new key ON so existing panels keep allowing normal logins.');

        // Re-running the migration is a no-op (idempotent INSERT IGNORE) and
        // must not stomp an admin-customised value.
        $this->setSetting('config.enablenormallogin', '0');
        $ctx->run(ROOT . 'updater/data/802.php');
        $this->assertSame('0', $this->readSetting('config.enablenormallogin'),
            'Re-running the migration must not overwrite an existing value.');
    }
}
