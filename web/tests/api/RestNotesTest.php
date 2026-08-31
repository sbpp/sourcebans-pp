<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\Fixture;

final class RestNotesTest extends RestTestCase
{
    public function testListRequiresToken(): void
    {
        $response = $this->rest('GET', '/notes', query: ['steam' => 'STEAM_0:1:8801']);
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testCreateListDelete(): void
    {
        $token = $this->mintToken();
        $created = $this->rest('POST', '/notes', [
            'steam' => 'STEAM_0:1:8802',
            'body' => 'rest note body',
        ], $token);
        $this->assertSame(201, $created->status, json_encode($created->payload));
        $note = $created->payload['data'];
        $this->assertSame('STEAM_0:1:8802', $note['steam']);
        $this->assertIsString($note['steam64']);
        $this->assertSame('rest note body', $note['body']);
        $this->assertSame('admin', $note['author']);

        $list = $this->rest('GET', '/notes', token: $token, query: ['steam' => 'STEAM_0:1:8802']);
        $this->assertSame(200, $list->status);
        $this->assertSame(1, $list->payload['meta']['total']);
        $this->assertSame($note['id'], $list->payload['data'][0]['id']);

        $by64 = $this->rest('GET', '/notes', token: $token, query: ['steam' => $note['steam64']]);
        $this->assertSame(200, $by64->status);
        $this->assertSame($note['id'], $by64->payload['data'][0]['id']);

        $deleted = $this->rest('DELETE', '/notes/' . $note['id'], [], $token);
        $this->assertSame(200, $deleted->status, json_encode($deleted->payload));
        $empty = $this->rest('GET', '/notes', token: $token, query: ['steam' => 'STEAM_0:1:8802']);
        $this->assertSame(0, $empty->payload['meta']['total']);
    }

    public function testEmptyBodyIs400(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('POST', '/notes', [
            'steam' => 'STEAM_0:1:8803',
            'body' => '   ',
        ], $token);
        $this->assertRestError($response, 400, 'validation');
        $this->assertSame('body', $response->payload['error']['field'] ?? null);
    }

    public function testInvalidSteamIs400(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('POST', '/notes', [
            'steam' => 'garbage',
            'body' => 'x',
        ], $token);
        $this->assertRestError($response, 400, 'validation');
        $this->assertSame('steam', $response->payload['error']['field'] ?? null);
    }

    public function testDeleteOthersNoteIs403(): void
    {
        $ownerToken = $this->mintToken();
        $created = $this->rest('POST', '/notes', [
            'steam' => 'STEAM_0:1:8804',
            'body' => 'owner note',
        ], $ownerToken);
        $this->assertSame(201, $created->status, json_encode($created->payload));
        $nid = $created->payload['data']['id'];

        $pdo = Fixture::rawPdo();
        $hash = password_hash('other', PASSWORD_BCRYPT);
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, extraflags, immunity, enabled)
             VALUES (?, ?, ?, -1, ?, ?, 0, 1)',
            DB_PREFIX
        ))->execute(['noter', 'STEAM_0:0:8804', $hash, 'noter@example.test', ADMIN_ADD_BAN]);
        $aid = (int) $pdo->lastInsertId();
        $otherToken = $this->mintToken($aid);

        $denied = $this->rest('DELETE', '/notes/' . $nid, [], $otherToken);
        $this->assertRestError($denied, 403, 'forbidden');
    }
}
