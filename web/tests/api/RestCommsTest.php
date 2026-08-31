<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\Fixture;

final class RestCommsTest extends RestTestCase
{
    public function testAnonymousGetHidesAdminName(): void
    {
        $cid = $this->seedComm('STEAM_0:1:9201', 1);
        $response = $this->rest('GET', '/comms/' . $cid);
        $this->assertSame(200, $response->status, json_encode($response->payload));
        $data = $response->payload['data'];
        $this->assertSame($cid, $data['id']);
        $this->assertSame('mute', $data['kind']);
        $this->assertSame('STEAM_0:1:9201', $data['steam']);
        $this->assertIsString($data['steam64']);
        $this->assertNull($data['admin_name']);
        $this->assertSame('permanent', $data['state']);
    }

    public function testPatGetShowsAdminName(): void
    {
        $cid = $this->seedComm('STEAM_0:1:9202', 2);
        $token = $this->mintToken();
        $response = $this->rest('GET', '/comms/' . $cid, token: $token);
        $this->assertSame(200, $response->status);
        $this->assertSame('gag', $response->payload['data']['kind']);
        $this->assertSame('admin', $response->payload['data']['admin_name']);
    }

    public function testCreateRequiresToken(): void
    {
        $response = $this->rest('POST', '/comms', [
            'steam' => 'STEAM_0:1:9300',
            'kind' => 'mute',
            'reason' => 'spam',
            'length' => 0,
        ]);
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testCreateMuteUnblockAndDelete(): void
    {
        $token = $this->mintToken();
        $created = $this->rest('POST', '/comms', [
            'steam' => 'STEAM_0:1:9301',
            'kind' => 'mute',
            'name' => 'RestComm',
            'reason' => 'rest-comm-reason',
            'length' => 30,
        ], $token);
        $this->assertSame(201, $created->status, json_encode($created->payload));
        $block = $created->payload['data'];
        $this->assertSame('mute', $block['kind']);
        $this->assertSame(1800, $block['length']);
        $this->assertSame('active', $block['state']);

        $empty = $this->rest('POST', '/comms/' . $block['id'] . '/unblock', [], $token);
        $this->assertRestError($empty, 400, 'validation');

        $unblock = $this->rest('POST', '/comms/' . $block['id'] . '/unblock', [
            'ureason' => 'apology',
        ], $token);
        $this->assertSame(200, $unblock->status, json_encode($unblock->payload));
        $this->assertSame('unmuted', $unblock->payload['data']['state']);
        $this->assertSame('apology', $unblock->payload['data']['unblock_reason']);

        $delete = $this->rest('DELETE', '/comms/' . $block['id'], token: $token);
        $this->assertSame(200, $delete->status, json_encode($delete->payload));
        $this->assertTrue($delete->payload['data']['deleted']);
        $gone = $this->rest('GET', '/comms/' . $block['id']);
        $this->assertRestError($gone, 404, 'not_found');
    }

    public function testSilenceCreatesTwoRows(): void
    {
        $token = $this->mintToken();
        $created = $this->rest('POST', '/comms', [
            'steam' => 'STEAM_0:1:9302',
            'kind' => 'silence',
            'name' => 'Quiet',
            'reason' => 'both',
            'length' => 0,
        ], $token);
        $this->assertSame(201, $created->status, json_encode($created->payload));
        $data = $created->payload['data'];
        $this->assertSame('silence', $data['kind']);
        $this->assertCount(2, $data['blocks']);
        $this->assertNotNull($data['mute']);
        $this->assertNotNull($data['gag']);
        $this->assertSame('mute', $data['mute']['kind']);
        $this->assertSame('gag', $data['gag']['kind']);
    }

    public function testDuplicateCreateIs409(): void
    {
        $token = $this->mintToken();
        $body = [
            'steam' => 'STEAM_0:1:9303',
            'kind' => 'gag',
            'reason' => 'x',
            'length' => 0,
        ];
        $first = $this->rest('POST', '/comms', $body, $token);
        $this->assertSame(201, $first->status, json_encode($first->payload));
        $second = $this->rest('POST', '/comms', $body, $token);
        $this->assertRestError($second, 409, 'already_blocked');
    }

    public function testInvalidKindIs400(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('POST', '/comms', [
            'steam' => 'STEAM_0:1:9304',
            'kind' => 'ban',
            'length' => 0,
        ], $token);
        $this->assertRestError($response, 400, 'validation');
        $this->assertSame('kind', $response->payload['error']['field'] ?? null);
    }

    private function seedComm(string $steam, int $type): int
    {
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_comms` (created, type, authid, name, ends, length, reason, aid, adminIp, admin_name)
             VALUES (UNIX_TIMESTAMP(), ?, ?, ?, UNIX_TIMESTAMP(), 0, ?, ?, "127.0.0.1", ?)',
            DB_PREFIX
        ))->execute([$type, $steam, 'Player', 'test', Fixture::adminAid(), 'admin']);
        return (int) $pdo->lastInsertId();
    }
}
