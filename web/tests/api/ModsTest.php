<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;

final class ModsTest extends ApiTestCase
{
    public function testAddCreatesRow(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('mods.add', [
            'name'           => 'Test Mod',
            'folder'         => 'tmod',
            'icon'           => 'icon.png',
            'steam_universe' => 0,
            'enabled'        => true,
        ]);
        $this->assertTrue($env['ok']);
        $this->assertSame('Mod Added', $env['data']['message']['title']);
        $this->assertSnapshot('mods/add_success', $env);

        $row = $this->row('mods', ['modfolder' => 'tmod']);
        $this->assertNotNull($row);
        $this->assertSame('Test Mod', $row['name']);
    }

    public function testAddRejectsAnonymous(): void
    {
        $env = $this->api('mods.add', ['name' => 'x', 'folder' => 'y']);
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('mods/add_forbidden', $env);
    }

    public function testAddRefusesDuplicateFolder(): void
    {
        $this->loginAsAdmin();
        $this->api('mods.add', ['name' => 'A', 'folder' => 'a']);
        $env = $this->api('mods.add', ['name' => 'B', 'folder' => 'a']);
        $this->assertEnvelopeError($env, 'mod_exists');
        $this->assertSnapshot('mods/add_duplicate', $env);
    }

    public function testRemoveDeletesRow(): void
    {
        $this->loginAsAdmin();
        $this->api('mods.add', ['name' => 'Doomed', 'folder' => 'doomed']);
        $row = $this->row('mods', ['name' => 'Doomed']);

        $env = $this->api('mods.remove', ['mid' => $row['mid']]);
        $this->assertTrue($env['ok']);
        $this->assertSame("mid_{$row['mid']}", $env['data']['remove']);
        $this->assertNull($this->row('mods', ['mid' => $row['mid']]));
        // The mid in the response depends on the auto-increment counter,
        // which jumps over the 23 rows seeded by data.sql (mid 0–22).
        // Redact it so the snapshot only locks the message envelope shape.
        $this->assertSnapshot('mods/remove_success', $env, ['data.remove']);
    }

    public function testRemoveRejectsAnonymous(): void
    {
        $env = $this->api('mods.remove', ['mid' => 1]);
        $this->assertEnvelopeError($env, 'forbidden');
    }
}
