<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\Fixture;

final class RestAdminsTest extends RestTestCase
{
    private const NEW_STEAM64 = '76561198000000000';

    public function testPutSteam64CreatesAdmin(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('PUT', '/admins/' . self::NEW_STEAM64, [
            'name' => 'RestBot',
        ], $token);
        $this->assertSame(201, $response->status, json_encode($response->payload));
        $data = $response->payload['data'];
        $this->assertSame('RestBot', $data['name']);
        $this->assertSame(self::NEW_STEAM64, $data['steam64']);
        $this->assertTrue($data['enabled']);
        $this->assertArrayHasKey('rehash', $response->payload['meta']);
        $this->assertArrayHasKey('attempted', $response->payload['meta']['rehash']);
    }

    public function testPutSteam64UpdatesExisting(): void
    {
        $token = $this->mintToken();
        $this->rest('PUT', '/admins/' . self::NEW_STEAM64, ['name' => 'RestBot'], $token);
        $response = $this->rest('PUT', '/admins/' . self::NEW_STEAM64, [
            'name' => 'RestBotUpdated',
            'immunity' => 12,
        ], $token);
        $this->assertSame(200, $response->status, json_encode($response->payload));
        $this->assertSame('RestBotUpdated', $response->payload['data']['name']);
        $this->assertSame(12, $response->payload['data']['immunity']);
    }

    public function testPutAidMissingIs404(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('PUT', '/admins/999999', ['name' => 'Nope'], $token);
        $this->assertRestError($response, 404, 'not_found');
    }

    public function testGetByAidAndSteam64(): void
    {
        $token = $this->mintToken();
        $created = $this->rest('PUT', '/admins/' . self::NEW_STEAM64, ['name' => 'RestBot'], $token);
        $aid = (int) $created->payload['data']['id'];

        $byAid = $this->rest('GET', '/admins/' . $aid, token: $token);
        $this->assertSame(200, $byAid->status);
        $this->assertSame(self::NEW_STEAM64, $byAid->payload['data']['steam64']);

        $bySteam = $this->rest('GET', '/admins/' . self::NEW_STEAM64, token: $token);
        $this->assertSame(200, $bySteam->status);
        $this->assertSame($aid, $bySteam->payload['data']['id']);
    }

    public function testSteam2PathIs400(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('GET', '/admins/STEAM_0:0:1', token: $token);
        $this->assertRestError($response, 400, 'validation');
        $this->assertSame('id', $response->payload['error']['field'] ?? null);
    }

    public function testSteam3PathIs400(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('GET', '/admins/[U:1:1]', token: $token);
        $this->assertRestError($response, 400, 'validation');
    }

    public function testSteam64BelowUniverseIs400(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('PUT', '/admins/70001788202945420', [
            'name' => 'RestBot',
        ], $token);
        $this->assertRestError($response, 400, 'validation');
        $this->assertSame('id', $response->payload['error']['field'] ?? null);
    }

    public function testBodySteamMismatchIs409(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('PUT', '/admins/' . self::NEW_STEAM64, [
            'name' => 'RestBot',
            'steam' => 'STEAM_0:0:0',
        ], $token);
        $this->assertRestError($response, 409, 'conflict');
        $this->assertSame('steam', $response->payload['error']['field'] ?? null);
    }

    public function testDeactivateAndReactivate(): void
    {
        $token = $this->mintToken();
        $created = $this->rest('PUT', '/admins/' . self::NEW_STEAM64, ['name' => 'RestBot'], $token);
        $this->assertSame(201, $created->status, json_encode($created->payload));

        $deact = $this->rest('POST', '/admins/' . self::NEW_STEAM64 . '/deactivate', [
            'reason' => 'demote from hub',
        ], $token);
        $this->assertSame(200, $deact->status, json_encode($deact->payload));
        $this->assertFalse($deact->payload['data']['enabled']);
        $this->assertArrayHasKey('rehash', $deact->payload['meta']);

        $got = $this->rest('GET', '/admins/' . self::NEW_STEAM64, token: $token);
        $this->assertFalse($got->payload['data']['enabled']);

        $react = $this->rest('POST', '/admins/' . self::NEW_STEAM64 . '/reactivate', [
            'reason' => 'restore',
        ], $token);
        $this->assertSame(200, $react->status, json_encode($react->payload));
        $this->assertTrue($react->payload['data']['enabled']);
    }

    public function testPutSteam64ReactivatesInactiveAdmin(): void
    {
        $token = $this->mintToken();
        $this->rest('PUT', '/admins/' . self::NEW_STEAM64, ['name' => 'RestBot'], $token);
        $this->rest('POST', '/admins/' . self::NEW_STEAM64 . '/deactivate', ['reason' => 'off'], $token);

        $response = $this->rest('PUT', '/admins/' . self::NEW_STEAM64, [
            'name' => 'RestBot',
        ], $token);
        $this->assertSame(200, $response->status, json_encode($response->payload));
        $this->assertTrue($response->payload['data']['enabled']);
    }

    public function testCannotDeactivateSelf(): void
    {
        $token = $this->mintToken();
        $aid = Fixture::adminAid();
        $response = $this->rest('POST', '/admins/' . $aid . '/deactivate', [
            'reason' => 'oops',
        ], $token);
        $this->assertRestError($response, 400, 'validation');
    }

    public function testAdminsListHasMeta(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('GET', '/admins', token: $token, query: ['page' => 1, 'per_page' => 10]);
        $this->assertSame(200, $response->status);
        $this->assertIsArray($response->payload['data']);
        $this->assertSame(1, $response->payload['meta']['page']);
        $this->assertSame(10, $response->payload['meta']['per_page']);
        $this->assertGreaterThanOrEqual(1, $response->payload['meta']['total']);
    }

    public function testGroupsList(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('GET', '/groups', token: $token);
        $this->assertSame(200, $response->status, json_encode($response->payload));
        $this->assertArrayHasKey('web', $response->payload['data']);
        $this->assertArrayHasKey('server', $response->payload['data']);
        $this->assertIsArray($response->payload['data']['web']);
        $this->assertIsArray($response->payload['data']['server']);
    }

    public function testSystemRehash(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('POST', '/system/rehash', [], $token);
        $this->assertSame(200, $response->status, json_encode($response->payload));
        $this->assertArrayHasKey('rehash', $response->payload['data']);
        $this->assertArrayHasKey('attempted', $response->payload['data']['rehash']);
    }

    public function testAdminsListRequiresAuth(): void
    {
        $response = $this->rest('GET', '/admins');
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testDeleteAdminsOnlyPatDoesNotRehashAfterDelete(): void
    {
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'REPLACE INTO `%s_settings` (`value`, `setting`) VALUES ("1", "config.enableadminrehashing")',
            DB_PREFIX
        ))->execute();
        \Config::init($GLOBALS['PDO']);

        $pdo->prepare(sprintf(
            'INSERT INTO `%s_servers` (ip, port, rcon, modid, enabled) VALUES (?, ?, ?, 1, 1)',
            DB_PREFIX
        ))->execute(['203.0.113.80', 27015, '']);
        $sid = (int) $pdo->lastInsertId();

        $targetAid = $this->insertAdmin('rehash-target', 'STEAM_0:0:9701', ADMIN_ADD_BAN);
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins_servers_groups` (admin_id, group_id, srv_group_id, server_id)
             VALUES (?, 0, -1, ?)',
            DB_PREFIX
        ))->execute([$targetAid, $sid]);

        $deleterAid = $this->insertAdmin('rehash-deleter', 'STEAM_0:0:9702', ADMIN_DELETE_ADMINS);
        $token = $this->mintToken($deleterAid);

        $response = $this->rest('DELETE', '/admins/' . $targetAid, ['reason' => 'cleanup'], $token);
        $this->assertSame(200, $response->status, json_encode($response->payload));
        $this->assertSame($targetAid, $response->payload['data']['id']);
        $this->assertFalse($response->payload['meta']['rehash']['attempted']);
        $this->assertContains($sid, $response->payload['meta']['rehash']['sids']);

        $gone = $pdo->prepare(sprintf('SELECT aid FROM `%s_admins` WHERE aid = ?', DB_PREFIX));
        $gone->execute([$targetAid]);
        $this->assertFalse($gone->fetch());
    }

    public function testEditAdminsPatCannotPatchOwner(): void
    {
        $editorAid = $this->insertAdmin('owner-editor', 'STEAM_0:0:9703', ADMIN_EDIT_ADMINS);
        $token = $this->mintToken($editorAid);
        $ownerAid = Fixture::adminAid();

        $name = $this->rest('PATCH', '/admins/' . $ownerAid, [
            'name' => 'HackedOwner',
        ], $token);
        $this->assertRestError($name, 403, 'forbidden');

        $steam = $this->rest('PATCH', '/admins/' . $ownerAid, [
            'steam' => 'STEAM_0:0:99999',
        ], $token);
        $this->assertRestError($steam, 403, 'forbidden');

        $servers = $this->rest('PATCH', '/admins/' . $ownerAid, [
            'server_ids' => [1],
        ], $token);
        $this->assertRestError($servers, 403, 'forbidden');
    }

    public function testOwnerPatCanPatchOwner(): void
    {
        $token = $this->mintToken();
        $ownerAid = Fixture::adminAid();

        $name = $this->rest('PATCH', '/admins/' . $ownerAid, [
            'name' => 'OwnerRenamed',
        ], $token);
        $this->assertSame(200, $name->status, json_encode($name->payload));
        $this->assertSame('OwnerRenamed', $name->payload['data']['name']);

        $steam = $this->rest('PATCH', '/admins/' . $ownerAid, [
            'steam' => 'STEAM_0:0:9704',
        ], $token);
        $this->assertSame(200, $steam->status, json_encode($steam->payload));
        $this->assertSame('STEAM_0:0:9704', $steam->payload['data']['steam']);

        $servers = $this->rest('PATCH', '/admins/' . $ownerAid, [
            'server_ids' => [],
        ], $token);
        $this->assertSame(200, $servers->status, json_encode($servers->payload));
    }

    private function insertAdmin(string $user, string $steam, int $flags): int
    {
        $pdo = Fixture::rawPdo();
        $hash = password_hash('other', PASSWORD_BCRYPT);
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, extraflags, immunity, enabled)
             VALUES (?, ?, ?, -1, ?, ?, 0, 1)',
            DB_PREFIX
        ))->execute([$user, $steam, $hash, $user . '@example.test', $flags]);
        return (int) $pdo->lastInsertId();
    }
}
