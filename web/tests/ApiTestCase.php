<?php

namespace Sbpp\Tests;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use PHPUnit\Framework\TestCase;

/**
 * Common helpers for API contract tests. Each test starts on a clean DB
 * (Fixture::reset()) and re-installs the global $userbank as an
 * unauthenticated user. Call $this->loginAs() to switch identities.
 */
abstract class ApiTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Fixture::reset();
        // Reset session so CSRF tokens don't leak across tests.
        $_SESSION = [];
        \CSRF::init();
        $GLOBALS['userbank'] = new \CUserManager(null);
        $GLOBALS['username'] = 'tester';
    }

    protected function loginAs(int $aid): void
    {
        // Re-issue a JWT with the requested aid claim. CUserManager only
        // looks at the aid claim, so we don't have to mint a real Auth flow.
        $key    = InMemory::plainText(str_repeat('x', 32));
        $config = Configuration::forSymmetricSigner(new Sha256(), $key);
        $token  = $config->builder()
            ->withClaim('aid', $aid)
            ->getToken($config->signer(), $config->signingKey());

        $GLOBALS['userbank'] = new \CUserManager($token);
        $GLOBALS['username'] = $GLOBALS['userbank']->GetProperty('user') ?? 'tester';
    }

    protected function loginAsAdmin(): void
    {
        $this->loginAs(Fixture::adminAid());
    }

    /**
     * Invoke a handler in-process and return the JSON envelope as an array
     * exactly the way the dispatcher would serialise it. Auth/permission
     * checks run identically to a real HTTP request.
     */
    protected function api(string $action, array $params = []): array
    {
        try {
            $result = \Api::invoke($action, $params);
            if (isset($result['__redirect']) && is_string($result['__redirect'])) {
                return ['ok' => false, 'redirect' => $result['__redirect']];
            }
            return ['ok' => true, 'data' => $result];
        } catch (\ApiError $e) {
            $err = ['code' => $e->errorCode, 'message' => $e->getMessage()];
            if ($e->field !== null) $err['field'] = $e->field;
            return ['ok' => false, 'error' => $err];
        }
    }

    protected function row(string $table, array $where): ?array
    {
        $pdo = Fixture::rawPdo();
        $clauses = [];
        $vals = [];
        foreach ($where as $k => $v) {
            $clauses[] = "`$k` = ?";
            $vals[] = $v;
        }
        $sql = sprintf('SELECT * FROM `%s_%s` WHERE %s LIMIT 1', DB_PREFIX, $table, implode(' AND ', $clauses));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($vals);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    protected function rows(string $table, array $where = []): array
    {
        $pdo = Fixture::rawPdo();
        if ($where) {
            $clauses = [];
            $vals = [];
            foreach ($where as $k => $v) {
                $clauses[] = "`$k` = ?";
                $vals[] = $v;
            }
            $sql = sprintf('SELECT * FROM `%s_%s` WHERE %s', DB_PREFIX, $table, implode(' AND ', $clauses));
            $stmt = $pdo->prepare($sql);
            $stmt->execute($vals);
        } else {
            $sql = sprintf('SELECT * FROM `%s_%s`', DB_PREFIX, $table);
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    protected function assertEnvelopeError(array $env, string $code): void
    {
        $this->assertFalse($env['ok'] ?? true, 'expected error envelope, got: ' . json_encode($env));
        $this->assertSame($code, $env['error']['code'] ?? null,
            'expected error.code=' . $code . ', got: ' . json_encode($env));
    }

    /**
     * Compare an envelope against a checked-in snapshot under
     * web/tests/api/__snapshots__/. The snapshot files document the
     * exact wire format of every state-changing handler so themes and
     * external integrations are protected from accidental drift across
     * the 2.0 break (#1112).
     *
     * Set the env var `UPDATE_SNAPSHOTS=1` to (re)write the file. CI
     * never sets it, so a missing or stale snapshot fails the gate.
     *
     * `$redact` is a list of dot-paths inside the envelope whose values
     * should be replaced with the literal string `<*>` before comparing.
     * Use it for values that legitimately vary across runs (autoincrement
     * IDs that depend on insertion order, UNIX timestamps, fixture-seeded
     * aids, ...) so the snapshot still locks the rest of the shape.
     *
     * @param array<string, mixed> $envelope
     * @param list<string>         $redact
     */
    protected function assertSnapshot(string $name, array $envelope, array $redact = []): void
    {
        $normalized = $envelope;
        foreach ($redact as $path) {
            $normalized = self::redactPath($normalized, explode('.', $path));
        }

        $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->fail('failed to JSON-encode envelope: ' . json_last_error_msg());
        }
        $json .= "\n";

        $file = ROOT . 'tests/api/__snapshots__/' . $name . '.json';
        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            $dir = dirname($file);
            if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
                $this->fail("could not create snapshot dir $dir");
            }
            file_put_contents($file, $json);
        }

        $this->assertFileExists(
            $file,
            "Snapshot $name is missing. Re-run with UPDATE_SNAPSHOTS=1 to create it."
        );
        $this->assertSame(
            file_get_contents($file),
            $json,
            "Snapshot $name out of date. If the change is intentional, re-run with UPDATE_SNAPSHOTS=1."
        );
    }

    /**
     * Replace the value at $path inside $arr with the literal `<*>`.
     * Walks through nested arrays; missing keys are no-ops so callers
     * don't have to special-case optional fields.
     *
     * @param array<string|int, mixed> $arr
     * @param list<string>             $path
     * @return array<string|int, mixed>
     */
    private static function redactPath(array $arr, array $path): array
    {
        $key = array_shift($path);
        if ($key === null || !array_key_exists($key, $arr)) {
            return $arr;
        }
        if ($path === []) {
            $arr[$key] = '<*>';
            return $arr;
        }
        if (is_array($arr[$key])) {
            $arr[$key] = self::redactPath($arr[$key], $path);
        }
        return $arr;
    }
}
