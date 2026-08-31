<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

use Sbpp\Db\Database;
use Sbpp\Auth\UserManager;

/**
 * Personal Access Tokens for `/api/v1`. Hashes only. The plaintext secret
 * is returned once from {@see mint()} and never stored.
 *
 * @phpstan-type TokenRow array{
 *     id: int,
 *     name: string,
 *     token_prefix: string,
 *     created: int,
 *     last_used: int|null,
 *     expires_at: int|null
 * }
 * @phpstan-type Identity array{aid: int, token_id: int}
 */
final class PatAuthenticator
{
    public const SECRET_PREFIX = 'sbpp_pat_';
    public const SECRET_BYTES = 32;
    public const PREFIX_LENGTH = 16;
    public const MAX_PER_ADMIN = 20;
    public const LAST_USED_THROTTLE_SECONDS = 60;

    public static function hash(string $secret): string
    {
        return hash('sha256', $secret);
    }

    public static function generateSecret(): string
    {
        return self::SECRET_PREFIX . bin2hex(random_bytes(self::SECRET_BYTES));
    }

    public static function prefixOf(string $secret): string
    {
        return substr($secret, 0, self::PREFIX_LENGTH);
    }

    public static function isWellFormedSecret(string $secret): bool
    {
        return preg_match('/^' . preg_quote(self::SECRET_PREFIX, '/') . '[0-9a-f]{64}$/D', $secret) === 1;
    }

    /**
     * @return array{id: int, secret: string, prefix: string, name: string, created: int, expires_at: int|null}
     */
    public static function mint(int $aid, string $name, ?int $expiresAt): array
    {
        $pdo = self::db();
        $pdo->query('SELECT COUNT(*) AS c FROM `:prefix_api_tokens` WHERE aid = :aid AND revoked_at IS NULL');
        $pdo->bind(':aid', $aid);
        $row = $pdo->single();
        if (is_array($row) && (int) ($row['c'] ?? 0) >= self::MAX_PER_ADMIN) {
            throw new \Sbpp\Api\ApiError(
                'validation',
                'You already have the maximum number of API tokens.',
                'name',
                400,
            );
        }

        $secret = self::generateSecret();
        $now = time();
        $pdo->query(
            'INSERT INTO `:prefix_api_tokens` (aid, name, token_hash, token_prefix, created, expires_at)'
            . ' VALUES (:aid, :name, :hash, :token_prefix, :created, :expires_at)'
        );
        $pdo->bind(':aid', $aid);
        $pdo->bind(':name', $name);
        $pdo->bind(':hash', self::hash($secret));
        $pdo->bind(':token_prefix', self::prefixOf($secret));
        $pdo->bind(':created', $now);
        $pdo->bind(':expires_at', $expiresAt);
        $pdo->execute();

        return [
            'id' => (int) $pdo->lastInsertId(),
            'secret' => $secret,
            'prefix' => self::prefixOf($secret),
            'name' => $name,
            'created' => $now,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @return list<TokenRow>
     */
    public static function listForAid(int $aid): array
    {
        $pdo = self::db();
        $pdo->query(
            'SELECT id, name, token_prefix, created, last_used, expires_at'
            . ' FROM `:prefix_api_tokens`'
            . ' WHERE aid = :aid AND revoked_at IS NULL'
            . ' ORDER BY created DESC'
        );
        $pdo->bind(':aid', $aid);
        $rows = $pdo->resultset();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'name' => (string) $r['name'],
                'token_prefix' => (string) $r['token_prefix'],
                'created' => (int) $r['created'],
                'last_used' => $r['last_used'] === null ? null : (int) $r['last_used'],
                'expires_at' => $r['expires_at'] === null ? null : (int) $r['expires_at'],
            ];
        }
        return $out;
    }

    public static function revoke(int $aid, int $id): bool
    {
        $pdo = self::db();
        $pdo->query(
            'UPDATE `:prefix_api_tokens` SET revoked_at = :now'
            . ' WHERE id = :id AND aid = :aid AND revoked_at IS NULL'
        );
        $pdo->bind(':now', time());
        $pdo->bind(':id', $id);
        $pdo->bind(':aid', $aid);
        $pdo->execute();
        return $pdo->rowCount() > 0;
    }

    /**
     * @return Identity|null
     */
    public static function resolve(string $secret): ?array
    {
        if (!self::isWellFormedSecret($secret)) {
            return null;
        }

        $pdo = self::db();
        $now = time();
        $pdo->query(
            'SELECT t.id, t.aid, t.last_used, t.expires_at, t.revoked_at, a.enabled'
            . ' FROM `:prefix_api_tokens` t'
            . ' INNER JOIN `:prefix_admins` a ON a.aid = t.aid'
            . ' WHERE t.token_hash = :hash'
            . ' LIMIT 1'
        );
        $pdo->bind(':hash', self::hash($secret));
        $row = $pdo->single();
        if (!is_array($row)) {
            return null;
        }
        if ($row['revoked_at'] !== null) {
            return null;
        }
        if ($row['expires_at'] !== null && (int) $row['expires_at'] <= $now) {
            return null;
        }
        if ((int) ($row['enabled'] ?? 1) !== 1) {
            return null;
        }

        $tokenId = (int) $row['id'];
        $lastUsed = $row['last_used'] === null ? null : (int) $row['last_used'];
        if ($lastUsed === null || $lastUsed < $now - self::LAST_USED_THROTTLE_SECONDS) {
            $pdo->query('UPDATE `:prefix_api_tokens` SET last_used = :now WHERE id = :id');
            $pdo->bind(':now', $now);
            $pdo->bind(':id', $tokenId);
            $pdo->execute();
        }

        return [
            'aid' => (int) $row['aid'],
            'token_id' => $tokenId,
        ];
    }

    /**
     * Cookie JWT is ignored. Only `Authorization: Bearer sbpp_pat_…`.
     *
     * @return Identity|null
     */
    public static function fromRequest(): ?array
    {
        $header = self::authorizationHeader();
        if ($header === '') {
            return null;
        }
        if (preg_match('/^Bearer\s+(\S+)/i', trim($header), $m) !== 1) {
            return null;
        }
        return self::resolve($m[1]);
    }

    /**
     * Replace `$GLOBALS['userbank']` with the PAT identity, or an anonymous
     * UserManager. Never leave the cookie JWT session in place.
     *
     * @return Identity|null
     */
    public static function bindUserbank(): ?array
    {
        $identity = self::fromRequest();
        if ($identity === null) {
            $GLOBALS['userbank'] = new UserManager(null);
            return null;
        }
        $GLOBALS['userbank'] = new UserManager(null, $identity['aid']);
        return $identity;
    }

    /**
     * Apache's apache2handler SAPI does not copy `Authorization` into
     * `$_SERVER['HTTP_AUTHORIZATION']`. `getallheaders()` still sees it.
     */
    public static function authorizationHeader(): string
    {
        $direct = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($direct !== '') {
            return $direct;
        }
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (strtolower((string) $name) === 'authorization') {
                        return (string) $value;
                    }
                }
            }
        }
        return '';
    }

    private static function db(): Database
    {
        $pdo = $GLOBALS['PDO'] ?? null;
        if ($pdo instanceof Database) {
            return $pdo;
        }
        return new Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
    }
}
