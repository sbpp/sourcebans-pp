<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;

final class AuthTest extends ApiTestCase
{
    /**
     * #1456 — privacy fix. The pre-fix handler threw
     * `ApiError('not_registered', …)` on the miss branch and let any
     * unauthenticated visitor probe the panel for registered emails
     * one HTTP request at a time. Post-fix every reachable branch
     * (unknown email, known email + mail ok, known email + mail err)
     * returns the SAME generic envelope. This test pins the miss
     * branch's wire shape; the next test pins the matched-but-mail-
     * failed branch; together with the snapshot they assert
     * byte-for-byte equality between miss and mail-failed responses,
     * which is the structural contract for #1456.
     */
    public function testLostPasswordReturnsGenericResponseForUnknownEmail(): void
    {
        $env = $this->api('auth.lost_password', ['email' => 'nobody@example.test']);
        $this->assertTrue($env['ok'] ?? false, 'expected ok envelope: ' . json_encode($env));
        $this->assertGenericLostPasswordEnvelope($env);
        $this->assertSnapshot('auth/lost_password_generic', $env);
    }

    /**
     * Companion to {@see testLostPasswordReturnsGenericResponseForUnknownEmail}
     * — without working SMTP the handler reaches the `Mail::send`
     * false-branch. Pre-#1456 that translated to a `mail_failed`
     * error envelope; post-fix it returns the SAME generic envelope
     * as the unknown-email branch, because surfacing the mail-failed
     * shape would let an attacker distinguish a registered email
     * (mail attempted -> failure visible) from an unregistered one
     * (mail never attempted -> immediate success), trivially
     * undoing the privacy gate.
     *
     * We re-run the unknown-email request inline (no second API call
     * — `api()` is idempotent given the same params) and assert the
     * two envelopes are STRUCTURALLY identical via the same snapshot
     * file. If a future refactor accidentally re-introduces a
     * branch-specific code path, this assertion catches the
     * drift loudly.
     */
    public function testLostPasswordReturnsGenericResponseForKnownEmailEvenWhenMailFails(): void
    {
        $env = $this->api('auth.lost_password', ['email' => 'admin@example.test']);
        $this->assertTrue($env['ok'] ?? false, 'expected ok envelope: ' . json_encode($env));
        $this->assertGenericLostPasswordEnvelope($env);
        $this->assertSnapshot('auth/lost_password_generic', $env);
    }

    /**
     * Structural contract for #1456: the response for a known email
     * is byte-for-byte indistinguishable from the response for an
     * unknown email. Pinning both `===` AND the JSON-encoded form
     * because an attacker observing the wire would diff the raw
     * response body, not the parsed PHP array.
     */
    public function testLostPasswordResponseIsIdenticalForKnownAndUnknownEmail(): void
    {
        $unknown = $this->api('auth.lost_password', ['email' => 'nobody@example.test']);
        $known   = $this->api('auth.lost_password', ['email' => 'admin@example.test']);

        $this->assertSame(
            json_encode($unknown),
            json_encode($known),
            '#1456 — known-email and unknown-email responses must be byte-identical, '
                . 'otherwise the response shape leaks whether the address is registered',
        );
    }

    /**
     * The `:prefix_admins.validate` column must only be touched when
     * the email actually matched a row. Probing the form for an
     * unknown email must not mutate any DB state — otherwise an
     * attacker could detect a hit by observing a behavior change
     * after a flurry of probes. Defense in depth on top of the
     * envelope-shape contract above.
     */
    public function testLostPasswordDoesNotTouchAdminsTableForUnknownEmail(): void
    {
        $rowBefore = $this->row('admins', ['user' => 'admin']);
        $this->api('auth.lost_password', ['email' => 'nobody@example.test']);
        $rowAfter  = $this->row('admins', ['user' => 'admin']);

        $this->assertSame(
            $rowBefore['validate'] ?? null,
            $rowAfter['validate']  ?? null,
            'Unknown-email probe must not touch :prefix_admins.validate on any row',
        );
    }

    /**
     * Conversely, a hit on a known email DOES roll the validate
     * token. Without this we wouldn't detect a regression that
     * accidentally short-circuited the match branch too (e.g. a
     * "let's always skip the UPDATE" refactor would silently break
     * the actual reset flow without any of the privacy tests above
     * catching it).
     */
    public function testLostPasswordRollsValidateTokenForKnownEmail(): void
    {
        $rowBefore = $this->row('admins', ['user' => 'admin']);
        $this->api('auth.lost_password', ['email' => 'admin@example.test']);
        $rowAfter  = $this->row('admins', ['user' => 'admin']);

        $this->assertNotSame(
            $rowBefore['validate'] ?? null,
            $rowAfter['validate']  ?? null,
            'Known-email request must roll the :prefix_admins.validate token so the '
                . 'subsequent ?validation=… link can authorise the reset',
        );
        $this->assertNotNull(
            $rowAfter['validate'] ?? null,
            'Expected validate token to be populated for the admin row after a known-email probe',
        );
    }

    /**
     * Helper: assert the envelope is the documented generic shape.
     * One-stop check used by both the miss-branch and matched-but-
     * mail-failed-branch tests above so a tweak to the copy / kind
     * lands in one place.
     *
     * @param array<string, mixed> $env
     */
    private function assertGenericLostPasswordEnvelope(array $env): void
    {
        $data = $env['data'] ?? null;
        $this->assertIsArray($data, 'expected ok envelope to carry data array');
        $msg = $data['message'] ?? null;
        $this->assertIsArray($msg, 'expected data.message to be an array');
        $this->assertSame('Check E-Mail', $msg['title'] ?? null);
        $this->assertSame('blue',         $msg['kind']  ?? null);
        // Body wording is asserted via the snapshot so future copy
        // edits show up as a single diff in the snapshot file rather
        // than as a wall of inline string assertions here.
        $this->assertIsString($msg['body'] ?? null);
        $this->assertNotEmpty($msg['body']);
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
