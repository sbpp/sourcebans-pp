<?php

namespace Sbpp\Tests\Api;

final class RestModsTest extends RestTestCase
{
    public function testListRequiresToken(): void
    {
        $response = $this->rest('GET', '/mods');
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testListCreateGetDelete(): void
    {
        $token = $this->mintToken();
        $list = $this->rest('GET', '/mods', token: $token);
        $this->assertSame(200, $list->status, json_encode($list->payload));
        $this->assertGreaterThan(0, $list->payload['meta']['total']);
        $this->assertArrayNotHasKey('rcon', $list->payload['data'][0]);

        $created = $this->rest('POST', '/mods', [
            'name' => 'Rest Test Mod',
            'folder' => 'resttmod',
            'enabled' => true,
        ], $token);
        $this->assertSame(201, $created->status, json_encode($created->payload));
        $mod = $created->payload['data'];
        $this->assertSame('Rest Test Mod', $mod['name']);
        $this->assertSame('resttmod', $mod['folder']);
        $this->assertTrue($mod['enabled']);

        $got = $this->rest('GET', '/mods/' . $mod['id'], token: $token);
        $this->assertSame(200, $got->status);
        $this->assertSame($mod['id'], $got->payload['data']['id']);

        $deleted = $this->rest('DELETE', '/mods/' . $mod['id'], ['ureason' => 'retired'], $token);
        $this->assertSame(200, $deleted->status, json_encode($deleted->payload));
        $missing = $this->rest('GET', '/mods/' . $mod['id'], token: $token);
        $this->assertRestError($missing, 404, 'not_found');
    }

    public function testDuplicateIs409(): void
    {
        $token = $this->mintToken();
        $body = ['name' => 'Dup Mod', 'folder' => 'dupmodrest'];
        $first = $this->rest('POST', '/mods', $body, $token);
        $this->assertSame(201, $first->status, json_encode($first->payload));
        $second = $this->rest('POST', '/mods', $body, $token);
        $this->assertRestError($second, 409, 'mod_exists');
    }

    public function testCreateRequiresToken(): void
    {
        $response = $this->rest('POST', '/mods', ['name' => 'x', 'folder' => 'y']);
        $this->assertRestError($response, 401, 'unauthorized');
    }
}
