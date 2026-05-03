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
    }
}
