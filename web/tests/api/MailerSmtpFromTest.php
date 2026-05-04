<?php

namespace Sbpp\Tests\Api;

use Sbpp\Mail\Mailer;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Issue #1109: SMTP From Email + From Name fields.
 *
 * The legacy panel implicitly used `smtp.user` as the sender identity, which
 * fails for every modern transactional provider (SendGrid, Mailgun, SES) where
 * the SMTP user is an API key or system identifier. This test pins the new
 * contract:
 *
 *   1. `config.mail.from_email` + `config.mail.from_name` round-trip through
 *      `sb_settings` and the `Config` cache.
 *   2. `Mailer::resolveFrom()` prefers DB settings over the legacy `SB_EMAIL`
 *      constant, formatting `"Name" <email>` when both are present.
 *   3. The constructed Email's envelope `From` header reflects the resolved
 *      address (so SMTP recipients actually see the configured sender).
 *   4. When `from_email` is empty, the panel falls back to `SB_EMAIL` and
 *      emits a once-per-process deprecation warning to `sb_log`.
 */
final class MailerSmtpFromTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mailer::resetDeprecationLatch();
    }

    private function setSetting(string $key, string $value): void
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'REPLACE INTO `%s_settings` (`setting`, `value`) VALUES (?, ?)',
            DB_PREFIX
        ));
        $stmt->execute([$key, $value]);

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

    public function testDefaultsAreSeeded(): void
    {
        // Freshly-installed panel: from_email is blank (forces the operator to
        // configure it before SMTP works in the modern path) and from_name
        // defaults to the project name so the display name is never empty.
        $this->assertSame('', $this->readSetting('config.mail.from_email'));
        $this->assertSame('SourceBans++', $this->readSetting('config.mail.from_name'));
    }

    public function testRoundTripsThroughSettingsCache(): void
    {
        $this->setSetting('config.mail.from_email', 'noreply@example.test');
        $this->setSetting('config.mail.from_name', 'Example Bans');

        $this->assertSame('noreply@example.test', \Config::get('config.mail.from_email'));
        $this->assertSame('Example Bans', \Config::get('config.mail.from_name'));
    }

    public function testResolveFromFormatsNameAndEmail(): void
    {
        $this->setSetting('config.mail.from_email', 'noreply@example.test');
        $this->setSetting('config.mail.from_name', 'Example Bans');

        $this->assertSame('"Example Bans" <noreply@example.test>', Mailer::resolveFrom());
    }

    public function testResolveFromUsesDefaultNameWhenNameIsEmpty(): void
    {
        // Acceptance criteria: from_name is optional and defaults to "SourceBans++".
        $this->setSetting('config.mail.from_email', 'noreply@example.test');
        $this->setSetting('config.mail.from_name', '');

        $this->assertSame('"SourceBans++" <noreply@example.test>', Mailer::resolveFrom());
    }

    public function testResolveFromFallsBackToSbEmailWhenFromEmailEmpty(): void
    {
        // SB_EMAIL is bootstrap-defined as 'test@example.com' in tests/bootstrap.php.
        $this->setSetting('config.mail.from_email', '');
        $this->setSetting('config.mail.from_name', 'Example Bans');

        $resolved = Mailer::resolveFrom();
        $this->assertSame('"Example Bans" <test@example.com>', $resolved);

        // Acceptance criteria: a deprecation warning must land in the audit log
        // so legacy installs are visibly nudged toward the new field.
        $deprecationCount = (int) Fixture::rawPdo()->query(sprintf(
            "SELECT COUNT(*) FROM `%s_log` WHERE title = 'Mail config deprecated'",
            DB_PREFIX
        ))->fetchColumn();
        $this->assertSame(1, $deprecationCount,
            'Falling back to SB_EMAIL must emit a deprecation warning to sb_log.');
    }

    public function testSbEmailDeprecationIsLatchedPerProcess(): void
    {
        // The deprecation warning is once-per-process so a busy panel doesn't
        // flood sb_log with the same row on every Mail::send() call.
        $this->setSetting('config.mail.from_email', '');

        Mailer::resolveFrom();
        Mailer::resolveFrom();
        Mailer::resolveFrom();

        $deprecationCount = (int) Fixture::rawPdo()->query(sprintf(
            "SELECT COUNT(*) FROM `%s_log` WHERE title = 'Mail config deprecated'",
            DB_PREFIX
        ))->fetchColumn();
        $this->assertSame(1, $deprecationCount,
            'Repeated resolveFrom() calls must only log the deprecation once.');
    }

    public function testBuildMessageEnvelopeFromMatchesConfiguredSender(): void
    {
        // Acceptance criteria: an SMTP send uses From: "{from_name}" <{from_email}>
        // when both settings are present. We capture the Email object the
        // transport would dispatch and assert on its envelope From header.
        $this->setSetting('config.mail.from_email', 'noreply@example.test');
        $this->setSetting('config.mail.from_name', 'Example Bans');

        $mailer = new Mailer(
            host: 'mail.example.test',
            user: 'apikey',
            password: 'secret',
            from: Mailer::resolveFrom(),
            port: 587,
            verifyPeer: true,
        );

        $email = $mailer->buildMessage(
            destination: 'player@example.test',
            subject: 'hello',
            body: '<p>hi</p>',
        );

        $from = $email->getFrom();
        $this->assertCount(1, $from, 'Email must have exactly one From address.');
        $this->assertSame('noreply@example.test', $from[0]->getAddress());
        $this->assertSame('Example Bans', $from[0]->getName());

        // And the destination is preserved unchanged.
        $to = $email->getTo();
        $this->assertCount(1, $to);
        $this->assertSame('player@example.test', $to[0]->getAddress());
    }

    public function testCreateReturnsNullWhenSmtpHostMissing(): void
    {
        // Pre-existing contract: Mailer::create() short-circuits to null when
        // SMTP isn't configured at all (no host/user/password). Pinned here so
        // the new From-resolution path doesn't accidentally make resolveFrom
        // run earlier than the SMTP gate.
        $this->setSetting('smtp.host', '');
        $this->setSetting('smtp.user', '');
        $this->setSetting('smtp.pass', '');
        $this->setSetting('config.mail.from_email', 'noreply@example.test');

        $this->assertNull(Mailer::create());
    }
}
