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
}
