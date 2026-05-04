<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Per-handler coverage for web/api/handlers/admins.php. End-to-end
 * add+remove of an admin lives in tests/integration/AdminFlowTest;
 * here we lock validation paths, the perm-edit handler, and the
 * stateless update_perms / generate_password helpers.
 */
final class AdminsTest extends ApiTestCase
{
    /** Build the minimum-required params accepted by api_admins_add. */
    private function adminParams(array $overrides = []): array
    {
        return array_merge([
            'mask'             => 0,
            'srv_mask'         => '',
            'name'             => 'Sidekick',
            'steam'            => 'STEAM_0:0:7777',
            'email'            => 'side@kick.test',
            'password'         => 'longpassword',
            'password2'        => 'longpassword',
            'server_group'     => 'c',
            'web_group'        => 'c',
            'server_password'  => '-1',
            'web_name'         => '',
            'server_name'      => '0',
            'servers'          => '',
            'single_servers'   => '',
        ], $overrides);
    }

    public function testAddRejectsAnonymous(): void
    {
        $env = $this->api('admins.add', $this->adminParams());
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('admins/add_forbidden', $env);
    }

    public function testAddSuccessSnapshot(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('admins.add', $this->adminParams());
        $this->assertTrue($env['ok'], json_encode($env));
        // aid depends on the auto-increment counter (admin row + new); redact.
        $this->assertSnapshot('admins/add_success', $env, ['data.aid']);
    }

    public function testAddValidatesName(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('admins.add', $this->adminParams(['name' => '']));
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('name', $env['error']['field']);
        $this->assertSnapshot('admins/add_validation_name', $env);
    }

    public function testAddRejectsApostropheInName(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('admins.add', $this->adminParams(['name' => "O'Brien"]));
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('name', $env['error']['field']);
    }

    public function testAddValidatesSteamId(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('admins.add', $this->adminParams(['steam' => '']));
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('steam', $env['error']['field']);
    }

    public function testAddRejectsTakenSteamId(): void
    {
        $this->loginAsAdmin();
        // The seeded admin owns STEAM_0:0:0 (Fixture::seedAdmin).
        $env = $this->api('admins.add', $this->adminParams(['steam' => 'STEAM_0:0:0']));
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('steam', $env['error']['field']);
    }

    public function testAddValidatesPassword(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('admins.add', $this->adminParams(['password' => 'short', 'password2' => 'short']));
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('password', $env['error']['field']);
    }

    public function testAddRejectsMismatchedPasswords(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('admins.add', $this->adminParams([
            'password'  => 'longpassword',
            'password2' => 'differentpw',
        ]));
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('password2', $env['error']['field']);
    }

    public function testRemoveDeletesRow(): void
    {
        $this->loginAsAdmin();
        $add = $this->api('admins.add', $this->adminParams(['name' => 'TempUser', 'steam' => 'STEAM_0:0:1234']));
        $this->assertTrue($add['ok'], json_encode($add));
        $aid = (int)$add['data']['aid'];

        $env = $this->api('admins.remove', ['aid' => $aid]);
        $this->assertTrue($env['ok']);
        $this->assertNull($this->row('admins', ['aid' => $aid]));
        // The remove envelope embeds the aid; redact for snapshot stability.
        $this->assertSnapshot('admins/remove_success', $env, [
            'data.remove',
            'data.counter.admincount',
        ]);
    }

    public function testRemoveRejectsAnonymous(): void
    {
        $env = $this->api('admins.remove', ['aid' => 1]);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testRemoveRefusesOwner(): void
    {
        $this->loginAsAdmin();
        // The Fixture-seeded admin holds ADMIN_OWNER (extraflags=16777216).
        $env = $this->api('admins.remove', ['aid' => Fixture::adminAid()]);
        $this->assertEnvelopeError($env, 'cannot_delete_owner');
        $this->assertSnapshot('admins/remove_owner_blocked', $env);
    }

    public function testEditPermsRequiresAid(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('admins.edit_perms', ['aid' => 0]);
        $this->assertEnvelopeError($env, 'bad_request');
        $this->assertSnapshot('admins/edit_perms_bad_request', $env);
    }

    public function testEditPermsUpdatesFlags(): void
    {
        $this->loginAsAdmin();
        // Seed a target admin with a password and email so we don't trip
        // the missing_credentials check inside the handler.
        $add = $this->api('admins.add', $this->adminParams([
            'name' => 'PermTarget', 'steam' => 'STEAM_0:0:5678',
        ]));
        $this->assertTrue($add['ok']);
        $aid = (int)$add['data']['aid'];

        $env = $this->api('admins.edit_perms', [
            'aid'       => $aid,
            'web_flags' => ADMIN_LIST_ADMINS, // 1
            'srv_flags' => 'b#5',             // generic admin + immunity 5
        ]);
        $this->assertTrue($env['ok'], json_encode($env));

        $row = $this->row('admins', ['aid' => $aid]);
        $this->assertSame(ADMIN_LIST_ADMINS, (int)$row['extraflags']);
        $this->assertSame('b',               $row['srv_flags']);
        $this->assertSame(5,                 (int)$row['immunity']);
        $this->assertSnapshot('admins/edit_perms_success', $env);
    }

    public function testEditPermsBlocksGrantingOwnerWithoutOwner(): void
    {
        // Seed a non-owner admin and log in as them.
        $pdo  = Fixture::rawPdo();
        $hash = password_hash('admin', PASSWORD_BCRYPT);
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, validate, extraflags, immunity)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX
        ))->execute(['nonowner', 'STEAM_0:0:99', $hash, -1, 'no@own.test', null, ADMIN_EDIT_ADMINS, 0]);
        $nonOwnerAid = (int)$pdo->lastInsertId();

        $this->loginAs($nonOwnerAid);
        $env = $this->api('admins.edit_perms', [
            'aid'       => $nonOwnerAid,
            'web_flags' => ADMIN_OWNER,
            'srv_flags' => '',
        ]);
        // The handler returns Api::redirect on the OWNER escalation attempt.
        $this->assertFalse($env['ok'] ?? true);
        $this->assertSame('index.php?p=login&m=no_access', $env['redirect'] ?? null);
    }

    public function testUpdatePermsReturnsTemplateBlobForWebGroup(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('admins.update_perms', ['type' => 1, 'value' => 'c']);
        $this->assertTrue($env['ok']);
        $this->assertSame('web', $env['data']['id']);
        $this->assertNotEmpty($env['data']['permissions']);
        $this->assertTrue($env['data']['is_owner']);
        $this->assertSnapshot('admins/update_perms_web', $env, ['data.permissions']);
    }

    public function testUpdatePermsReturnsTemplateBlobForServerGroup(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('admins.update_perms', ['type' => 2, 'value' => 'c']);
        $this->assertTrue($env['ok']);
        $this->assertSame('server', $env['data']['id']);
        $this->assertNotEmpty($env['data']['permissions']);
    }

    public function testUpdatePermsRejectsAnonymous(): void
    {
        // requireAdmin=true → dispatcher rejects non-admins.
        $env = $this->api('admins.update_perms', ['type' => 1, 'value' => 'c']);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testGeneratePasswordReturnsString(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('admins.generate_password', []);
        $this->assertTrue($env['ok']);
        $this->assertIsString($env['data']['password']);
        $this->assertGreaterThanOrEqual(MIN_PASS_LENGTH, strlen($env['data']['password']));
        // The password itself is random so we redact, but we lock the
        // shape so any future field addition is forced through review.
        $this->assertSnapshot('admins/generate_password_success', $env, ['data.password']);
    }

    public function testGeneratePasswordRejectsAnonymous(): void
    {
        $env = $this->api('admins.generate_password', []);
        $this->assertEnvelopeError($env, 'forbidden');
    }
}
