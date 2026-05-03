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

    public function testChangeSrvPasswordRedirectsAnonymousUser(): void
    {
        // Not logged in: must redirect, never touch the row.
        $env = $this->api('account.change_srv_password', [
            'aid'          => Fixture::adminAid(),
            'srv_password' => 'hacker',
        ]);
        $this->assertFalse($env['ok'] ?? true);
        $this->assertSame('index.php?p=login&m=no_access', $env['redirect'] ?? null);

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
