<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\Fixture;

final class RestBansTest extends RestTestCase
{
    public function testAnonymousGetHidesIpAndAdminName(): void
    {
        $bid = $this->seedBan('STEAM_0:1:9001', '1.2.3.4');
        $response = $this->rest('GET', '/bans/' . $bid);
        $this->assertSame(200, $response->status, json_encode($response->payload));
        $data = $response->payload['data'];
        $this->assertSame($bid, $data['id']);
        $this->assertSame('steam', $data['type']);
        $this->assertSame('STEAM_0:1:9001', $data['steam']);
        $this->assertIsString($data['steam64']);
        $this->assertNull($data['ip']);
        $this->assertNull($data['admin_name']);
        $this->assertSame('permanent', $data['state']);
        $this->assertSame(0, $data['length']);
    }

    public function testPatGetShowsIpAndAdminName(): void
    {
        $bid = $this->seedBan('STEAM_0:1:9002', '5.6.7.8');
        $token = $this->mintToken();
        $response = $this->rest('GET', '/bans/' . $bid, token: $token);
        $this->assertSame(200, $response->status, json_encode($response->payload));
        $data = $response->payload['data'];
        $this->assertSame('5.6.7.8', $data['ip']);
        $this->assertSame('admin', $data['admin_name']);
    }

    public function testCookieJwtDoesNotBypassHideOnPublicGet(): void
    {
        $bid = $this->seedBan('STEAM_0:1:9003', '9.9.9.9');
        $this->loginAsAdmin();
        $response = $this->rest('GET', '/bans/' . $bid);
        $this->assertSame(200, $response->status);
        $this->assertNull($response->payload['data']['ip']);
        $this->assertNull($response->payload['data']['admin_name']);
    }

    public function testCreateRequiresToken(): void
    {
        $response = $this->rest('POST', '/bans', [
            'steam' => 'STEAM_0:1:9100',
            'name' => 'Rest',
            'reason' => 'cheat',
            'length' => 0,
        ]);
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testCreateAndListAndUnban(): void
    {
        $token = $this->mintToken();
        $created = $this->rest('POST', '/bans', [
            'steam' => 'STEAM_0:1:9101',
            'name' => 'RestBan',
            'reason' => 'rest-unique-reason',
            'length' => 60,
        ], $token);
        $this->assertSame(201, $created->status, json_encode($created->payload));
        $ban = $created->payload['data'];
        $this->assertSame('RestBan', $ban['player_name']);
        $this->assertSame('STEAM_0:1:9101', $ban['steam']);
        $this->assertSame(3600, $ban['length']);
        $this->assertSame('active', $ban['state']);
        $this->assertArrayNotHasKey('kick', $created->payload['meta'] ?? []);

        $list = $this->rest('GET', '/bans', query: ['search' => 'rest-unique-reason']);
        $this->assertSame(200, $list->status);
        $ids = array_column($list->payload['data'], 'id');
        $this->assertContains($ban['id'], $ids);

        $state = $this->rest('GET', '/bans', query: ['state' => 'active']);
        $this->assertContains($ban['id'], array_column($state->payload['data'], 'id'));

        $empty = $this->rest('POST', '/bans/' . $ban['id'] . '/unban', [], $token);
        $this->assertRestError($empty, 400, 'validation');
        $this->assertSame('ureason', $empty->payload['error']['field'] ?? null);

        $unban = $this->rest('POST', '/bans/' . $ban['id'] . '/unban', [
            'ureason' => 'served time',
        ], $token);
        $this->assertSame(200, $unban->status, json_encode($unban->payload));
        $this->assertSame('unbanned', $unban->payload['data']['state']);
        $this->assertSame('served time', $unban->payload['data']['unban_reason']);
    }

    public function testDuplicateCreateIs409(): void
    {
        $token = $this->mintToken();
        $body = [
            'steam' => 'STEAM_0:1:9102',
            'name' => 'Dup',
            'reason' => 'x',
            'length' => 0,
        ];
        $first = $this->rest('POST', '/bans', $body, $token);
        $this->assertSame(201, $first->status, json_encode($first->payload));
        $second = $this->rest('POST', '/bans', $body, $token);
        $this->assertRestError($second, 409, 'already_banned');
    }

    public function testInvalidSteamIs400(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('POST', '/bans', [
            'steam' => 'garbage',
            'length' => 0,
            'reason' => 'x',
        ], $token);
        $this->assertRestError($response, 400, 'validation');
        $this->assertSame('steam', $response->payload['error']['field'] ?? null);
    }

    public function testMissingBanIs404(): void
    {
        $response = $this->rest('GET', '/bans/999999');
        $this->assertRestError($response, 404, 'not_found');
    }

    public function testNonNumericBidIs400(): void
    {
        $response = $this->rest('GET', '/bans/STEAM_0:1:1');
        $this->assertRestError($response, 400, 'validation');
    }

    private function seedBan(string $steam, string $ip): int
    {
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (created, type, ip, authid, name, ends, length, reason, aid, adminIp, admin_name)
             VALUES (UNIX_TIMESTAMP(), 0, ?, ?, ?, UNIX_TIMESTAMP(), 0, ?, ?, "127.0.0.1", ?)',
            DB_PREFIX
        ))->execute([$ip, $steam, 'Cheater', 'test', Fixture::adminAid(), 'admin']);
        return (int) $pdo->lastInsertId();
    }
}
