<?php

namespace Sbpp\Tests\Api;

use Sbpp\Servers\SourceQueryCache;
use Sbpp\Tests\Fixture;

final class RestServersTest extends RestTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SourceQueryCache::setProbeOverrideForTesting(static function (): array {
            return [
                'info' => [
                    'HostName' => 'Rest Query Host',
                    'Players' => 3,
                    'MaxPlayers' => 24,
                    'Map' => 'de_dust2',
                    'Secure' => true,
                ],
                'players' => [],
            ];
        });
    }

    protected function tearDown(): void
    {
        SourceQueryCache::setProbeOverrideForTesting(null);
        parent::tearDown();
    }

    public function testAnonymousGetOmitsRcon(): void
    {
        $sid = $this->seedServer('secret-rcon-rest');
        $response = $this->rest('GET', '/servers/' . $sid);
        $this->assertSame(200, $response->status, json_encode($response->payload));
        $data = $response->payload['data'];
        $this->assertSame($sid, $data['id']);
        $this->assertSame('203.0.113.50', $data['ip']);
        $this->assertArrayNotHasKey('rcon', $data);
        $this->assertStringNotContainsString('secret-rcon-rest', json_encode($response->payload));
        $this->assertSame('Rest Query Host', $data['query']['hostname']);
        $this->assertSame('de_dust2', $data['query']['map']);
    }

    public function testCookieJwtDoesNotAuthenticate(): void
    {
        $this->loginAsAdmin();
        $response = $this->rest('POST', '/servers', [
            'ip' => '203.0.113.51',
            'port' => 27015,
            'mod' => 1,
        ]);
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testCreateRequiresToken(): void
    {
        $response = $this->rest('POST', '/servers', [
            'ip' => '203.0.113.52',
            'port' => 27015,
            'mod' => 1,
        ]);
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testCreateGetPatchDelete(): void
    {
        $token = $this->mintToken();
        $created = $this->rest('POST', '/servers', [
            'ip' => '203.0.113.53',
            'port' => 27020,
            'mod' => 1,
            'enabled' => true,
        ], $token);
        $this->assertSame(201, $created->status, json_encode($created->payload));
        $sid = $created->payload['data']['id'];
        $this->assertSame('203.0.113.53', $created->payload['data']['ip']);
        $this->assertTrue($created->payload['data']['enabled']);
        $this->assertArrayNotHasKey('rcon', $created->payload['data']);

        $list = $this->rest('GET', '/servers');
        $this->assertContains($sid, array_column($list->payload['data'], 'id'));

        $patched = $this->rest('PATCH', '/servers/' . $sid, [
            'port' => 27021,
            'enabled' => false,
        ], $token);
        $this->assertSame(200, $patched->status, json_encode($patched->payload));
        $this->assertSame(27021, $patched->payload['data']['port']);
        $this->assertFalse($patched->payload['data']['enabled']);

        $deleted = $this->rest('DELETE', '/servers/' . $sid, [], $token);
        $this->assertSame(200, $deleted->status, json_encode($deleted->payload));
        $this->assertSame($sid, $deleted->payload['data']['id']);

        $missing = $this->rest('GET', '/servers/' . $sid);
        $this->assertRestError($missing, 404, 'not_found');
    }

    public function testDuplicateCreateIs409(): void
    {
        $token = $this->mintToken();
        $body = ['ip' => '203.0.113.54', 'port' => 27015, 'mod' => 1];
        $first = $this->rest('POST', '/servers', $body, $token);
        $this->assertSame(201, $first->status, json_encode($first->payload));
        $second = $this->rest('POST', '/servers', $body, $token);
        $this->assertRestError($second, 409, 'duplicate');
    }

    public function testInvalidAddressIs400(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('POST', '/servers', [
            'ip' => 'not a host',
            'port' => 27015,
            'mod' => 1,
        ], $token);
        $this->assertRestError($response, 400, 'validation');
        $this->assertSame('address', $response->payload['error']['field'] ?? null);
    }

    public function testNonNumericSidIs400(): void
    {
        $response = $this->rest('GET', '/servers/STEAM_0:1:1');
        $this->assertRestError($response, 400, 'validation');
    }

    public function testRconRequiresSmFlagAndServerMapping(): void
    {
        $sid = $this->seedServer();
        $token = $this->mintToken();
        $denied = $this->rest('POST', '/servers/' . $sid . '/rcon', [
            'command' => 'status',
        ], $token);
        $this->assertRestError($denied, 403, 'forbidden');

        Fixture::rawPdo()->prepare(sprintf(
            "UPDATE `%s_admins` SET srv_flags = 'mz' WHERE aid = ?",
            DB_PREFIX
        ))->execute([Fixture::adminAid()]);
        Fixture::rawPdo()->prepare(sprintf(
            'INSERT INTO `%s_admins_servers_groups` (admin_id, group_id, srv_group_id, server_id)
             VALUES (?, 0, -1, ?)',
            DB_PREFIX
        ))->execute([Fixture::adminAid(), $sid]);

        $token2 = $this->mintToken();
        $blocked = $this->rest('POST', '/servers/' . $sid . '/rcon', [
            'command' => 'rcon_password',
        ], $token2);
        $this->assertSame(200, $blocked->status, json_encode($blocked->payload));
        $this->assertSame('error', $blocked->payload['data']['kind']);
        $this->assertStringContainsString("Don't try to cheat", $blocked->payload['data']['error']);
    }

    private function seedServer(string $rcon = ''): int
    {
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_servers` (ip, port, rcon, modid, enabled) VALUES (?, ?, ?, 1, 1)',
            DB_PREFIX
        ))->execute(['203.0.113.50', 27015, $rcon]);
        return (int) $pdo->lastInsertId();
    }
}
