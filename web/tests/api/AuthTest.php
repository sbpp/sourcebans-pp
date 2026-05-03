<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;

final class AuthTest extends ApiTestCase
{
    public function testLostPasswordRejectsUnknownEmail(): void
    {
        $env = $this->api('auth.lost_password', ['email' => 'nobody@example.test']);
        $this->assertEnvelopeError($env, 'not_registered');
    }

    public function testLoginActionIsPublic(): void
    {
        // Hitting login while not authenticated must reach the handler;
        // the handler then redirects on bad creds.
        $env = $this->api('auth.login', ['username' => 'admin', 'password' => 'wrong']);
        $this->assertFalse($env['ok'] ?? true);
        $this->assertSame('?p=login&m=failed', $env['redirect'] ?? null);
    }
}
