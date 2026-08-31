<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\Fixture;

final class RestCommentsTest extends RestTestCase
{
    public function testCreateListPatchDeleteOnBan(): void
    {
        $bid = $this->seedBan('STEAM_0:1:9501');
        $token = $this->mintToken();

        $created = $this->rest('POST', '/bans/' . $bid . '/comments', [
            'body' => 'rest comment',
        ], $token);
        $this->assertSame(201, $created->status, json_encode($created->payload));
        $comment = $created->payload['data'];
        $this->assertSame('rest comment', $comment['body']);
        $this->assertSame('ban', $comment['type']);
        $this->assertSame($bid, $comment['parent_id']);
        $this->assertSame('admin', $comment['author']);
        $this->assertSame(Fixture::adminAid(), $comment['author_aid']);

        $list = $this->rest('GET', '/bans/' . $bid . '/comments', token: $token);
        $this->assertSame(200, $list->status);
        $this->assertSame(1, $list->payload['meta']['total']);
        $this->assertSame($comment['id'], $list->payload['data'][0]['id']);

        $anon = $this->rest('GET', '/bans/' . $bid . '/comments');
        $this->assertSame(200, $anon->status);
        $this->assertSame(0, $anon->payload['meta']['total']);

        $patched = $this->rest('PATCH', '/comments/' . $comment['id'], [
            'body' => 'edited comment',
        ], $token);
        $this->assertSame(200, $patched->status, json_encode($patched->payload));
        $this->assertSame('edited comment', $patched->payload['data']['body']);
        $this->assertNotNull($patched->payload['data']['edited_at']);

        $deleted = $this->rest('DELETE', '/comments/' . $comment['id'], [], $token);
        $this->assertSame(200, $deleted->status, json_encode($deleted->payload));
        $gone = $this->rest('GET', '/bans/' . $bid . '/comments', token: $token);
        $this->assertSame(0, $gone->payload['meta']['total']);
    }

    public function testAnonymousSeesCommentsWhenPublicEnabled(): void
    {
        $bid = $this->seedBan('STEAM_0:1:9502');
        $token = $this->mintToken();
        $created = $this->rest('POST', '/bans/' . $bid . '/comments', [
            'body' => 'public comment',
        ], $token);
        $this->assertSame(201, $created->status);

        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'REPLACE INTO `%s_settings` (`value`, `setting`) VALUES ("1", "config.enablepubliccomments")',
            DB_PREFIX
        ))->execute();
        \Config::init($GLOBALS['PDO']);

        $anon = $this->rest('GET', '/bans/' . $bid . '/comments');
        $this->assertSame(200, $anon->status, json_encode($anon->payload));
        $this->assertSame(1, $anon->payload['meta']['total']);
        $this->assertSame('public comment', $anon->payload['data'][0]['body']);
        $this->assertNull($anon->payload['data'][0]['author']);
        $this->assertNull($anon->payload['data'][0]['author_aid']);
    }

    public function testCreateOnComm(): void
    {
        $cid = $this->seedComm('STEAM_0:1:9503');
        $token = $this->mintToken();
        $created = $this->rest('POST', '/comms/' . $cid . '/comments', [
            'body' => 'comm comment',
        ], $token);
        $this->assertSame(201, $created->status, json_encode($created->payload));
        $this->assertSame('comm', $created->payload['data']['type']);
        $this->assertSame($cid, $created->payload['data']['parent_id']);
    }

    public function testEmptyBodyIs400(): void
    {
        $bid = $this->seedBan('STEAM_0:1:9504');
        $token = $this->mintToken();
        $response = $this->rest('POST', '/bans/' . $bid . '/comments', [
            'body' => '   ',
        ], $token);
        $this->assertRestError($response, 400, 'validation');
        $this->assertSame('body', $response->payload['error']['field'] ?? null);
    }

    public function testMissingParentIs404(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('POST', '/bans/999999/comments', [
            'body' => 'x',
        ], $token);
        $this->assertRestError($response, 404, 'not_found');
    }

    public function testDeleteRequiresOwner(): void
    {
        $bid = $this->seedBan('STEAM_0:1:9505');
        $ownerToken = $this->mintToken();
        $created = $this->rest('POST', '/bans/' . $bid . '/comments', [
            'body' => 'owner comment',
        ], $ownerToken);
        $this->assertSame(201, $created->status);
        $id = $created->payload['data']['id'];

        $pdo = Fixture::rawPdo();
        $hash = password_hash('other', PASSWORD_BCRYPT);
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, extraflags, immunity, enabled)
             VALUES (?, ?, ?, -1, ?, ?, 0, 1)',
            DB_PREFIX
        ))->execute(['commenter', 'STEAM_0:0:9505', $hash, 'commenter@example.test', ADMIN_ADD_BAN]);
        $aid = (int) $pdo->lastInsertId();
        $otherToken = $this->mintToken($aid);

        $denied = $this->rest('DELETE', '/comments/' . $id, [], $otherToken);
        $this->assertRestError($denied, 403, 'forbidden');
    }

    public function testMissingCommentIs404(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('PATCH', '/comments/999999', ['body' => 'x'], $token);
        $this->assertRestError($response, 404, 'not_found');
    }

    private function seedBan(string $steam): int
    {
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (created, type, ip, authid, name, ends, length, reason, aid, adminIp, admin_name)
             VALUES (UNIX_TIMESTAMP(), 0, ?, ?, ?, UNIX_TIMESTAMP(), 0, ?, ?, "127.0.0.1", ?)',
            DB_PREFIX
        ))->execute(['1.1.1.1', $steam, 'Cheater', 'test', Fixture::adminAid(), 'admin']);
        return (int) $pdo->lastInsertId();
    }

    private function seedComm(string $steam): int
    {
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_comms` (created, type, authid, name, ends, length, reason, aid, adminIp, admin_name)
             VALUES (UNIX_TIMESTAMP(), ?, ?, ?, UNIX_TIMESTAMP(), 0, ?, ?, "127.0.0.1", ?)',
            DB_PREFIX
        ))->execute([1, $steam, 'Player', 'test', Fixture::adminAid(), 'admin']);
        return (int) $pdo->lastInsertId();
    }
}
