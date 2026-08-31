<?php

namespace Sbpp\Tests\Api;

use Sbpp\Rest\PatAuthenticator;
use Sbpp\Rest\RateLimiter;
use Sbpp\Tests\Fixture;

final class RestAuthTest extends RestTestCase
{
    public function testMeRequiresToken(): void
    {
        $response = $this->rest('GET', '/me');
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testCookieJwtDoesNotAuthenticateRest(): void
    {
        $this->loginAsAdmin();
        $response = $this->rest('GET', '/me');
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testMeReturnsCallerAdmin(): void
    {
        $token = $this->mintToken();
        $response = $this->rest('GET', '/me', token: $token);
        $this->assertSame(200, $response->status, json_encode($response->payload));
        $data = $response->payload['data'];
        $this->assertSame(Fixture::adminAid(), $data['id']);
        $this->assertSame('admin', $data['name']);
        $this->assertSame('STEAM_0:0:0', $data['steam']);
        $this->assertSame('76561197960265728', $data['steam64']);
        $this->assertIsString($data['steam64']);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('validate', $data);
        $this->assertArrayNotHasKey('attempts', $data);
        $this->assertArrayNotHasKey('lockout_until', $data);
        $this->assertArrayNotHasKey('srv_password', $data);
    }

    public function testMalformedSecretIsUnauthorized(): void
    {
        $response = $this->rest('GET', '/me', token: 'not-a-pat');
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testUnknownWellFormedSecretIsUnauthorized(): void
    {
        $secret = PatAuthenticator::SECRET_PREFIX . str_repeat('ab', 32);
        $response = $this->rest('GET', '/me', token: $secret);
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testRevokedTokenIsUnauthorized(): void
    {
        $minted = PatAuthenticator::mint(Fixture::adminAid(), 'revoke-me', null);
        $this->assertTrue(PatAuthenticator::revoke(Fixture::adminAid(), $minted['id']));
        $response = $this->rest('GET', '/me', token: $minted['secret']);
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testExpiredTokenIsUnauthorized(): void
    {
        $token = $this->mintToken(expiresAt: time() - 10);
        $response = $this->rest('GET', '/me', token: $token);
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testDisabledAdminTokenIsUnauthorized(): void
    {
        $token = $this->mintToken();
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf('UPDATE `%s_admins` SET enabled = 0 WHERE aid = ?', DB_PREFIX))
            ->execute([Fixture::adminAid()]);
        $response = $this->rest('GET', '/me', token: $token);
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testOpenapiIsPublic(): void
    {
        $response = $this->rest('GET', '/openapi.yaml');
        $this->assertSame(200, $response->status);
        $this->assertNotNull($response->rawBody);
        $this->assertStringContainsString('openapi:', $response->rawBody);
        $this->assertStringContainsString('application/yaml', $response->contentType);
    }

    public function testRateLimitReturns429(): void
    {
        RateLimiter::resetForTests();
        RateLimiter::setLimitForTests(1);
        $token = $this->mintToken();
        $first = $this->rest('GET', '/me', token: $token);
        $this->assertSame(200, $first->status);
        $second = $this->rest('GET', '/me', token: $token);
        $this->assertRestError($second, 429, 'rate_limited');
        $this->assertArrayHasKey('Retry-After', $second->headers);
    }

    public function testUnknownWellFormedPatIs401OnPublicGet(): void
    {
        $secret = PatAuthenticator::SECRET_PREFIX . str_repeat('cd', 32);
        $response = $this->rest('GET', '/bans', token: $secret);
        $this->assertRestError($response, 401, 'unauthorized');
    }

    public function testMalformedBearerStaysAnonymousOnPublicGet(): void
    {
        $response = $this->rest('GET', '/bans', token: 'not-a-pat');
        $this->assertSame(200, $response->status, json_encode($response->payload));
    }

    public function testResolveReturnsNullForGarbage(): void
    {
        $this->assertNull(PatAuthenticator::resolve(''));
        $this->assertNull(PatAuthenticator::resolve('sbpp_pat_short'));
        $this->assertSame(64, strlen(PatAuthenticator::hash('anything')));
    }
}
