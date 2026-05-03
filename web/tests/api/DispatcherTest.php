<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;

/**
 * Smoke tests for the Api dispatcher itself: unknown actions, auth gating,
 * envelope shape. These do not require any handler logic to be sound, just
 * the registry + ApiError plumbing.
 */
final class DispatcherTest extends ApiTestCase
{
    public function testUnknownActionReturnsUnknownActionError(): void
    {
        $env = $this->api('this.does.not.exist', []);
        $this->assertEnvelopeError($env, 'unknown_action');
    }

    public function testAdminOnlyActionDeniedForAnonymousUser(): void
    {
        $env = $this->api('admins.generate_password', []);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testAdminOnlyActionAllowedForAdmin(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('admins.generate_password', []);
        $this->assertTrue($env['ok'], 'expected ok envelope, got ' . json_encode($env));
        $this->assertNotEmpty($env['data']['password'] ?? null);
    }

    public function testPublicActionAllowedAnonymously(): void
    {
        // system.check_version is registered as public (it just hits a remote URL).
        // We only assert that the dispatcher allows the call to proceed.
        $env = $this->api('system.check_version', []);
        // External fetch may fail in CI; the envelope should still be OK
        // (the handler gracefully encodes "Error" strings).
        $this->assertTrue($env['ok'], 'expected ok envelope, got ' . json_encode($env));
        $this->assertArrayHasKey('release_latest', $env['data']);
    }
}
