<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;

final class ServersTest extends ApiTestCase
{
    public function testAddRejectsAnonymous(): void
    {
        $env = $this->api('servers.add', ['ip' => '1.1.1.1', 'port' => '27015', 'mod' => 1, 'group' => '0']);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testAddValidatesIp(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('servers.add', ['ip' => '', 'port' => '27015', 'mod' => 1, 'group' => '0']);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('address', $env['error']['field']);
    }

    public function testAddValidatesPort(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('servers.add', ['ip' => '1.1.1.1', 'port' => 'notanumber', 'mod' => 1, 'group' => '0']);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('port', $env['error']['field']);
    }

    public function testAddRequiresModSelection(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('servers.add', ['ip' => '1.1.1.1', 'port' => '27015', 'mod' => -2, 'group' => '0']);
        $this->assertEnvelopeError($env, 'validation');
        $this->assertSame('mod', $env['error']['field']);
    }
}
