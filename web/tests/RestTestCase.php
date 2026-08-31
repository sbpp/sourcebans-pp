<?php

namespace Sbpp\Tests\Api;

use Sbpp\Rest\FrontController;
use Sbpp\Rest\PatAuthenticator;
use Sbpp\Rest\RateLimiter;
use Sbpp\Rest\Response;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * In-process REST `/api/v1` caller. Sets `$_SERVER` and calls
 * `FrontController::dispatch()` so tests never hit `Response::send()`.
 */
abstract class RestTestCase extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::setLimitForTests(100000);
        RateLimiter::resetForTests();
    }

    protected function tearDown(): void
    {
        RateLimiter::setLimitForTests(null);
        RateLimiter::resetForTests();
        parent::tearDown();
    }

    protected function mintToken(?int $aid = null, string $name = 'phpunit', ?int $expiresAt = null): string
    {
        $minted = PatAuthenticator::mint($aid ?? Fixture::adminAid(), $name, $expiresAt);
        return $minted['secret'];
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, mixed> $query
     */
    protected function rest(
        string $method,
        string $path,
        ?array $body = null,
        ?string $token = null,
        array $query = [],
    ): Response {
        $prevServer = $_SERVER;
        $prevGet = $_GET;
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['PATH_INFO'] = $path;
        $_SERVER['REQUEST_URI'] = '/api/v1.php' . $path;
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
        if ($token !== null) {
            $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }
        $_GET = $query;
        try {
            $raw = $body === null ? null : json_encode($body, JSON_THROW_ON_ERROR);
            return FrontController::dispatch($raw);
        } finally {
            $_SERVER = $prevServer;
            $_GET = $prevGet;
        }
    }

    protected function assertRestError(Response $response, int $status, string $code): void
    {
        $this->assertSame($status, $response->status, json_encode($response->payload));
        $this->assertSame($code, $response->payload['error']['code'] ?? null, json_encode($response->payload));
    }
}
