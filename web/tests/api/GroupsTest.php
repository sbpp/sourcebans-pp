<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Per-handler coverage for web/api/handlers/groups.php. Covers the
 * three create paths (web, server-mod, web-only-no-perms), delete +
 * cleanup, edit (including override CRUD), and the stateless
 * update_perms / add_server_group_name HTML helpers.
 */
final class GroupsTest extends ApiTestCase
{
    public function testAddRejectsAnonymous(): void
    {
        $env = $this->api('groups.add', [
            'name' => 'X', 'type' => '1', 'bitmask' => 0, 'srvflags' => '',
        ]);
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('groups/add_forbidden', $env);
    }

    public function testAddCreatesWebGroup(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('groups.add', [
            'name'     => 'Mods',
            'type'     => '1',
            'bitmask'  => ADMIN_LIST_ADMINS,
            'srvflags' => '',
        ]);
        $this->assertTrue($env['ok'], json_encode($env));

        $row = $this->row('groups', ['name' => 'Mods']);
        $this->assertNotNull($row);
        $this->assertSame(1, (int)$row['type']);
        $this->assertSame(ADMIN_LIST_ADMINS, (int)$row['flags']);
        $this->assertSnapshot('groups/add_web_success', $env);
    }

    public function testAddCreatesServerGroupWithImmunity(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('groups.add', [
            'name'     => 'Trusted',
            'type'     => '2',
            'bitmask'  => 0,
            'srvflags' => 'b#10',
        ]);
        $this->assertTrue($env['ok'], json_encode($env));

        $row = $this->row('srvgroups', ['name' => 'Trusted']);
        $this->assertNotNull($row);
        $this->assertSame('b', $row['flags']);
        $this->assertSame(10, (int)$row['immunity']);
    }

    public function testAddValidatesName(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('groups.add', ['name' => '', 'type' => '1']);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('name', $env['error']['field']);
        $this->assertSnapshot('groups/add_validation_name', $env);
    }

    public function testAddRejectsCommaInName(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('groups.add', ['name' => 'Bad,Name', 'type' => '1']);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('name', $env['error']['field']);
    }

    public function testAddRejectsDuplicateName(): void
    {
        $this->loginAsAdmin();
        $this->api('groups.add', [
            'name' => 'Dupe', 'type' => '1', 'bitmask' => 0, 'srvflags' => '',
        ]);
        $env = $this->api('groups.add', [
            'name' => 'Dupe', 'type' => '1', 'bitmask' => 0, 'srvflags' => '',
        ]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('name', $env['error']['field']);
    }

    public function testAddValidatesType(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('groups.add', ['name' => 'X', 'type' => '0']);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('type', $env['error']['field']);
    }

    public function testRemoveWebGroupResetsAdminGid(): void
    {
        $this->loginAsAdmin();
        $this->api('groups.add', [
            'name' => 'ToDelete', 'type' => '1', 'bitmask' => 0, 'srvflags' => '',
        ]);
        $gid = (int)$this->row('groups', ['name' => 'ToDelete'])['gid'];

        // Point an admin at this group so we can prove the cleanup runs.
        Fixture::rawPdo()->prepare(sprintf(
            'UPDATE `%s_admins` SET gid = ? WHERE aid = ?', DB_PREFIX
        ))->execute([$gid, Fixture::adminAid()]);

        $env = $this->api('groups.remove', ['gid' => $gid, 'type' => 'web']);
        $this->assertTrue($env['ok']);
        $this->assertNull($this->row('groups', ['gid' => $gid]));

        $admin = $this->row('admins', ['aid' => Fixture::adminAid()]);
        $this->assertSame(-1, (int)$admin['gid'], 'membership should be cleared on group deletion');
        $this->assertSnapshot('groups/remove_success', $env, ['data.remove']);
    }

    public function testRemoveSrvGroupClearsAdminAssignment(): void
    {
        $this->loginAsAdmin();
        $this->api('groups.add', [
            'name' => 'SrvDelete', 'type' => '2', 'bitmask' => 0, 'srvflags' => 'b',
        ]);
        $sgRow = $this->row('srvgroups', ['name' => 'SrvDelete']);
        $this->assertNotNull($sgRow);

        // Assign the admin to the soon-to-be-deleted srvgroup name.
        Fixture::rawPdo()->prepare(sprintf(
            'UPDATE `%s_admins` SET srv_group = ? WHERE aid = ?', DB_PREFIX
        ))->execute(['SrvDelete', Fixture::adminAid()]);

        $env = $this->api('groups.remove', ['gid' => $sgRow['id'], 'type' => 'srv']);
        $this->assertTrue($env['ok']);

        $admin = $this->row('admins', ['aid' => Fixture::adminAid()]);
        $this->assertNull($admin['srv_group'], 'admin\'s srv_group must be NULL after the row is dropped');
    }

    public function testRemoveRejectsAnonymous(): void
    {
        $env = $this->api('groups.remove', ['gid' => 1, 'type' => 'web']);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testEditUpdatesWebGroup(): void
    {
        $this->loginAsAdmin();
        $this->api('groups.add', [
            'name' => 'EditMe', 'type' => '1', 'bitmask' => 0, 'srvflags' => '',
        ]);
        $gid = (int)$this->row('groups', ['name' => 'EditMe'])['gid'];

        $env = $this->api('groups.edit', [
            'gid'       => $gid,
            'web_flags' => ADMIN_LIST_ADMINS | ADMIN_LIST_SERVERS,
            'srv_flags' => '',
            'type'      => 'web',
            'name'      => 'EditMe',
        ]);
        $this->assertTrue($env['ok']);

        $row = $this->row('groups', ['gid' => $gid]);
        $this->assertSame(ADMIN_LIST_ADMINS | ADMIN_LIST_SERVERS, (int)$row['flags']);
        $this->assertSnapshot('groups/edit_success', $env);
    }

    public function testEditValidatesName(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('groups.edit', [
            'gid' => 1, 'web_flags' => 0, 'srv_flags' => '',
            'type' => 'web', 'name' => '',
        ]);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('name', $env['error']['field']);
    }

    public function testEditServerGroupOverridesInsertAndUpdate(): void
    {
        $this->loginAsAdmin();
        $this->api('groups.add', [
            'name' => 'OverGroup', 'type' => '2', 'bitmask' => 0, 'srvflags' => 'b',
        ]);
        $gid = (int)$this->row('srvgroups', ['name' => 'OverGroup'])['id'];

        // Insert a new override via the new_override path. The `access`
        // column is `enum('allow','deny')` in struc.sql.
        $env = $this->api('groups.edit', [
            'gid'       => $gid,
            'web_flags' => 0,
            'srv_flags' => 'b',
            'type'      => 'srv',
            'name'      => 'OverGroup',
            'overrides' => [],
            'new_override' => ['type' => 'command', 'name' => 'sm_kick', 'access' => 'allow'],
        ]);
        $this->assertTrue($env['ok'], json_encode($env));

        $rows = $this->rows('srvgroups_overrides', ['group_id' => $gid]);
        $this->assertCount(1, $rows);
        $this->assertSame('sm_kick', $rows[0]['name']);

        // Re-edit to flip the override's access via the `overrides` path.
        $env = $this->api('groups.edit', [
            'gid'       => $gid,
            'web_flags' => 0,
            'srv_flags' => 'b',
            'type'      => 'srv',
            'name'      => 'OverGroup',
            'overrides' => [['id' => (int)$rows[0]['id'], 'type' => 'command', 'name' => 'sm_kick', 'access' => 'deny']],
            'new_override' => [],
        ]);
        $this->assertTrue($env['ok'], json_encode($env));
        $row = $this->row('srvgroups_overrides', ['id' => $rows[0]['id']]);
        $this->assertSame('deny', $row['access']);
    }

    public function testEditServerGroupRejectsDuplicateOverride(): void
    {
        $this->loginAsAdmin();
        $this->api('groups.add', [
            'name' => 'DupOver', 'type' => '2', 'bitmask' => 0, 'srvflags' => 'b',
        ]);
        $gid = (int)$this->row('srvgroups', ['name' => 'DupOver'])['id'];
        $this->api('groups.edit', [
            'gid' => $gid, 'web_flags' => 0, 'srv_flags' => 'b',
            'type' => 'srv', 'name' => 'DupOver',
            'overrides' => [],
            'new_override' => ['type' => 'command', 'name' => 'sm_ban', 'access' => 'allow'],
        ]);

        $env = $this->api('groups.edit', [
            'gid' => $gid, 'web_flags' => 0, 'srv_flags' => 'b',
            'type' => 'srv', 'name' => 'DupOver',
            'overrides' => [],
            'new_override' => ['type' => 'command', 'name' => 'sm_ban', 'access' => 'allow'],
        ]);
        $this->assertEnvelopeError($env, 'duplicate_override');
        $this->assertSnapshot('groups/edit_duplicate_override', $env);
    }

    public function testUpdatePermsHonorsGidContextSwitch(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('groups.update_perms', ['gid' => 1]);
        $this->assertTrue($env['ok']);
        $this->assertNotEmpty($env['data']['permissions']);
        $this->assertTrue($env['data']['is_owner']);
        $this->assertSnapshot('groups/update_perms_web', $env, ['data.permissions']);
    }

    public function testUpdatePermsRejectsAnonymous(): void
    {
        $env = $this->api('groups.update_perms', ['gid' => 1]);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testAddServerGroupNameReturnsHtmlBlob(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('groups.add_server_group_name', []);
        $this->assertTrue($env['ok']);
        // The blob is an HTML fragment for the legacy form. We only lock
        // its presence + general shape, not the exact bytes.
        $this->assertNotEmpty($env['data']['html']);
        $this->assertStringContainsString('id="sgroup"', $env['data']['html']);
    }
}
