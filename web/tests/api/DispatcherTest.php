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

    /**
     * Regression: a handler registered with all defaults (no perm, not
     * requireAdmin, not public) used to fall through to the handler with
     * no auth check. Verify the dispatcher now enforces a logged-in
     * baseline for every non-public action.
     *
     * account.check_password is exactly such a registration.
     */
    public function testNonPublicActionRejectsAnonymousByDefault(): void
    {
        $env = $this->api('account.check_password', ['aid' => 1, 'password' => 'x']);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    /**
     * The HTTP-boundary gates (method, JSON body, CSRF) live in
     * Api::handle(). The default `api()` helper goes via Api::invoke()
     * and bypasses them — so without these tests the CSRF code path has
     * zero coverage.
     */
    public function testDispatcherRejectsGetRequests(): void
    {
        [$status, $env] = \Api::handle('GET', '', 'tok');
        $this->assertSame(405, $status);
        $this->assertSame('method_not_allowed', $env['error']['code']);
    }

    public function testDispatcherRejectsInvalidJsonBody(): void
    {
        [$status, $env] = \Api::handle('POST', 'this is not JSON', 'tok');
        $this->assertSame(400, $status);
        $this->assertSame('bad_request', $env['error']['code']);
    }

    public function testDispatcherRejectsMissingAction(): void
    {
        [$status, $env] = \Api::handle('POST', json_encode(['params' => []]) ?: '', 'tok');
        $this->assertSame(400, $status);
        $this->assertSame('bad_request', $env['error']['code']);
    }

    public function testDispatcherRejectsBadCsrfToken(): void
    {
        $body = json_encode(['action' => 'system.check_version', 'params' => []]) ?: '';
        [$status, $env] = \Api::handle('POST', $body, 'definitely-not-the-session-token');
        $this->assertSame(403, $status);
        $this->assertSame('csrf', $env['error']['code']);
    }

    public function testDispatcherAcceptsCsrfTokenFromBodyField(): void
    {
        $sessionToken = \CSRF::token();
        $this->assertNotSame('', $sessionToken, 'CSRF::init() should have seeded the session');

        $body = json_encode([
            'action'     => 'system.check_version',
            'params'     => [],
            'csrf_token' => $sessionToken,
        ]) ?: '';
        // Header token is wrong, body token is right — body should win.
        [$status, $env] = \Api::handle('POST', $body, 'wrong-token');
        $this->assertSame(200, $status);
        $this->assertTrue($env['ok'] ?? false, 'expected ok envelope, got: ' . json_encode($env));
    }
}
