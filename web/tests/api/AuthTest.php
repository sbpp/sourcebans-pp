<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

final class AuthTest extends ApiTestCase
{
    public function testLostPasswordRejectsUnknownEmail(): void
    {
        $env = $this->api('auth.lost_password', ['email' => 'nobody@example.test']);
        $this->assertEnvelopeError($env, 'not_registered');
        $this->assertSnapshot('auth/lost_password_not_registered', $env);
    }

    public function testLostPasswordReportsMailFailureForKnownEmail(): void
    {
        // Without working SMTP the handler hits the Mail::send false path,
        // which translates to the structured `mail_failed` envelope. The
        // mail send is best-effort by design — the wire shape of the
        // failure envelope is what we are locking down here.
        $env = $this->api('auth.lost_password', ['email' => 'admin@example.test']);
        $this->assertEnvelopeError($env, 'mail_failed');
        $this->assertSnapshot('auth/lost_password_mail_failed', $env);
    }

    public function testLoginActionIsPublic(): void
    {
        // Hitting login while not authenticated must reach the handler;
        // the handler then redirects on bad creds.
        $env = $this->api('auth.login', ['username' => 'admin', 'password' => 'wrong']);
        $this->assertFalse($env['ok'] ?? true);
        $this->assertSame('?p=login&m=failed', $env['redirect'] ?? null);
        $this->assertSnapshot('auth/login_failed_redirect', $env);
    }

    public function testLoginSuccessRedirectsToOptionalTarget(): void
    {
        $env = $this->api('auth.login', [
            'username' => 'admin',
            'password' => 'admin',
            'redirect' => 'p=home',
        ]);
        $this->assertFalse($env['ok'] ?? true);
        // On success the handler issues a redirect with `?` + the caller's
        // requested target so the panel resumes where the user came from.
        $this->assertSame('?p=home', $env['redirect'] ?? null);
        $this->assertSnapshot('auth/login_success_redirect', $env);

        // The lockout counter must be reset to 0 once auth succeeds —
        // a successful login wipes any prior failed attempts.
        $row = $this->row('admins', ['user' => 'admin']);
        $this->assertSame(0, (int)$row['attempts']);
        $this->assertNull($row['lockout_until']);
    }

    public function testLoginEmptyPasswordRedirectsWithSpecificFlag(): void
    {
        $env = $this->api('auth.login', ['username' => 'admin', 'password' => '']);
        $this->assertFalse($env['ok'] ?? true);
        $this->assertSame('?p=login&m=empty_pwd', $env['redirect'] ?? null);
    }

    /**
     * 5 wrong attempts puts the account in lockout. Subsequent login
     * attempts (even with the correct password) get the lockout redirect
     * until the timeout expires. This locks #1081's hardening into the
     * wire contract.
     */
    public function testLoginLocksAccountAfterFiveFailedAttempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $env = $this->api('auth.login', ['username' => 'admin', 'password' => 'wrong']);
            $this->assertFalse($env['ok'] ?? true, "attempt $i should fail");
        }
        $row = $this->row('admins', ['user' => 'admin']);
        $this->assertSame(5, (int)$row['attempts']);
        $this->assertNotNull($row['lockout_until']);

        $env = $this->api('auth.login', ['username' => 'admin', 'password' => 'admin']);
        $this->assertFalse($env['ok'] ?? true);
        $this->assertStringStartsWith('?p=login&m=locked', $env['redirect'] ?? '',
            'a locked account should hit the locked redirect even with the right password');
    }

    public function testLoginActionRequiresNoCsrfWhenInvokedDirectly(): void
    {
        // auth.login is the only handler the unauthenticated landing page
        // can reach. It is `public => true` so the dispatcher does not
        // require a logged-in user; CSRF still applies at the HTTP boundary.
        // (Bootstrap-time check that the registry actually has it as public
        // is locked down in PermissionMatrixTest::testRegisteredPermissionMaskMatches
        // for `auth.login`.)
        $entry = \Api::lookup('auth.login');
        $this->assertNotNull($entry);
        $this->assertTrue($entry['public'], 'auth.login must remain public');
    }
}
