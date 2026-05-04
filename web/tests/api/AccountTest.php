<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

final class AccountTest extends ApiTestCase
{
    public function testCheckPasswordMatches(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('account.check_password', [
            'aid'      => Fixture::adminAid(),
            'password' => 'admin',
        ]);
        $this->assertTrue($env['ok']);
        $this->assertTrue($env['data']['matches']);
        $this->assertSnapshot('account/check_password_matches', $env);
    }

    public function testCheckPasswordRejectsWrongPassword(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('account.check_password', [
            'aid'      => Fixture::adminAid(),
            'password' => 'definitely-not-admin',
        ]);
        $this->assertTrue($env['ok']);
        $this->assertFalse($env['data']['matches']);
        $this->assertSnapshot('account/check_password_no_match', $env);
    }

    /**
     * Regression: account.check_password used to be reachable by anonymous
     * callers (defaults registration + handler had no aid match), so the API
     * was a free password oracle for any aid. The dispatcher now baseline-
     * enforces is_logged_in() for any non-public action; this test pins
     * that contract for the specific handler.
     */
    public function testCheckPasswordRejectsAnonymousCaller(): void
    {
        $env = $this->api('account.check_password', [
            'aid'      => Fixture::adminAid(),
            'password' => 'admin',
        ]);
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('account/check_password_forbidden', $env);
    }

    /**
     * Defense in depth: even an authenticated user must not be able to
     * probe a different user's password by passing a different aid.
     */
    public function testCheckPasswordRejectsCrossAccountProbe(): void
    {
        // Seed a second admin to serve as the "victim" aid.
        $pdo  = Fixture::rawPdo();
        $hash = password_hash('victim-secret', PASSWORD_BCRYPT);
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, validate, extraflags, immunity)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX
        ))->execute(['victim', 'STEAM_0:0:1', $hash, -1, 'victim@example.test', null, 0, 0]);
        $victimAid = (int)$pdo->lastInsertId();

        $this->loginAsAdmin();
        $env = $this->api('account.check_password', [
            'aid'      => $victimAid,
            'password' => 'victim-secret',
        ]);
        // The fixed handler returns Api::redirect() on cross-account probes.
        $this->assertFalse($env['ok'] ?? true);
        $this->assertSame('index.php?p=login&m=no_access', $env['redirect'] ?? null);
    }

    public function testChangeSrvPasswordRejectsAnonymousUser(): void
    {
        // Not logged in: dispatcher's is_logged_in() baseline rejects with
        // a 403/forbidden envelope before the handler runs; the row must
        // not be touched.
        $env = $this->api('account.change_srv_password', [
            'aid'          => Fixture::adminAid(),
            'srv_password' => 'hacker',
        ]);
        $this->assertEnvelopeError($env, 'forbidden');

        $row = $this->row('admins', ['aid' => Fixture::adminAid()]);
        $this->assertNull($row['srv_password']);
    }

    public function testChangeSrvPasswordWritesValueWhenAuthorized(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('account.change_srv_password', [
            'aid'          => Fixture::adminAid(),
            'srv_password' => 'newpass',
        ]);
        $this->assertTrue($env['ok']);
        $row = $this->row('admins', ['aid' => Fixture::adminAid()]);
        $this->assertSame('newpass', $row['srv_password']);
        $this->assertSnapshot('account/change_srv_password_success', $env);
    }

    public function testChangeSrvPasswordClearsValueWhenSentEmpty(): void
    {
        // Set a password first so we can confirm the empty-value path
        // really nulls the column out (matches the legacy `'NULL'` magic
        // string still accepted by the handler).
        $this->loginAsAdmin();
        $this->api('account.change_srv_password', [
            'aid'          => Fixture::adminAid(),
            'srv_password' => 'temporary',
        ]);
        $env = $this->api('account.change_srv_password', [
            'aid'          => Fixture::adminAid(),
            'srv_password' => '',
        ]);
        $this->assertTrue($env['ok']);
        $row = $this->row('admins', ['aid' => Fixture::adminAid()]);
        $this->assertNull($row['srv_password']);
    }

    public function testCheckSrvPasswordMatches(): void
    {
        $this->loginAsAdmin();
        // Seed a srv_password to match against.
        $this->api('account.change_srv_password', [
            'aid'          => Fixture::adminAid(),
            'srv_password' => 'serverpw',
        ]);

        $env = $this->api('account.check_srv_password', [
            'aid'      => Fixture::adminAid(),
            'password' => 'serverpw',
        ]);
        $this->assertTrue($env['ok']);
        $this->assertTrue($env['data']['matches']);
        $this->assertSnapshot('account/check_srv_password_matches', $env);
    }

    public function testCheckSrvPasswordRejectsWrong(): void
    {
        $this->loginAsAdmin();
        $this->api('account.change_srv_password', [
            'aid'          => Fixture::adminAid(),
            'srv_password' => 'serverpw',
        ]);
        $env = $this->api('account.check_srv_password', [
            'aid'      => Fixture::adminAid(),
            'password' => 'not-it',
        ]);
        $this->assertTrue($env['ok']);
        $this->assertFalse($env['data']['matches']);
    }

    public function testCheckSrvPasswordRejectsCrossAccount(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('account.check_srv_password', [
            'aid'      => 9999, // someone else's aid
            'password' => 'whatever',
        ]);
        $this->assertFalse($env['ok'] ?? true);
        $this->assertSame('index.php?p=login&m=no_access', $env['redirect'] ?? null);
    }

    public function testChangePasswordHappyPath(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('account.change_password', [
            'aid'          => Fixture::adminAid(),
            'old_password' => 'admin',
            'new_password' => 'a-much-better-password',
        ]);
        // The handler logs the user out and returns a redirect envelope.
        $this->assertFalse($env['ok'] ?? true);
        $this->assertSame('index.php?p=login', $env['redirect'] ?? null);

        // The bcrypt hash on the row should now match the new password.
        $row = $this->row('admins', ['aid' => Fixture::adminAid()]);
        $this->assertTrue(password_verify('a-much-better-password', $row['password']));
        $this->assertSnapshot('account/change_password_success', $env);
    }

    public function testChangePasswordRejectsBadCurrentPassword(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('account.change_password', [
            'aid'          => Fixture::adminAid(),
            'old_password' => 'wrong-current',
            'new_password' => 'doesntmatter',
        ]);
        $this->assertEnvelopeError($env, 'bad_password');
        $this->assertSame('current', $env['error']['field']);
        $this->assertSnapshot('account/change_password_bad_current', $env);
    }

    public function testChangePasswordRejectsAnonymousCaller(): void
    {
        $env = $this->api('account.change_password', [
            'aid'          => Fixture::adminAid(),
            'old_password' => 'admin',
            'new_password' => 'anything',
        ]);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testChangeEmailHappyPath(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('account.change_email', [
            'aid'      => Fixture::adminAid(),
            'email'    => 'admin+new@example.test',
            'password' => 'admin',
        ]);
        $this->assertTrue($env['ok'], json_encode($env));
        $row = $this->row('admins', ['aid' => Fixture::adminAid()]);
        $this->assertSame('admin+new@example.test', $row['email']);
        $this->assertSnapshot('account/change_email_success', $env);
    }

    public function testChangeEmailRejectsBadPassword(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('account.change_email', [
            'aid'      => Fixture::adminAid(),
            'email'    => 'admin+x@example.test',
            'password' => 'wrong',
        ]);
        $this->assertEnvelopeError($env, 'bad_password');
        $this->assertSame('emailpw', $env['error']['field']);
        $this->assertSnapshot('account/change_email_bad_password', $env);
    }

    public function testChangeEmailRejectsBadEmail(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('account.change_email', [
            'aid'      => Fixture::adminAid(),
            'email'    => 'not-an-email',
            'password' => 'admin',
        ]);
        $this->assertEnvelopeError($env, 'bad_email');
        $this->assertSame('email1', $env['error']['field']);
    }

    public function testChangeEmailRejectsAnonymousCaller(): void
    {
        $env = $this->api('account.change_email', [
            'aid'      => Fixture::adminAid(),
            'email'    => 'somehow@example.test',
            'password' => 'admin',
        ]);
        $this->assertEnvelopeError($env, 'forbidden');
    }
}
