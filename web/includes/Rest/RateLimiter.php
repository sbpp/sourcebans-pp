<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Rest;

/**
 * Fixed-window file rate limiter under `SB_CACHE/rest-rl/`.
 * Anonymous callers are keyed by IP. Authenticated callers by token id.
 */
final class RateLimiter
{
    public const DEFAULT_LIMIT = 60;
    public const WINDOW_SECONDS = 60;

    private static ?int $limitOverride = null;

    public static function setLimitForTests(?int $limit): void
    {
        self::$limitOverride = $limit;
    }

    public static function resetForTests(): void
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return;
        }
        $files = glob($dir . '/*.json');
        if ($files === false) {
            return;
        }
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * @return array{ok: bool, remaining: int, retry_after: int, limit: int}
     */
    public static function consume(string $key): array
    {
        $limit = self::$limitOverride ?? self::DEFAULT_LIMIT;
        $now = time();
        $window = intdiv($now, self::WINDOW_SECONDS) * self::WINDOW_SECONDS;
        $retryAfter = $window + self::WINDOW_SECONDS - $now;

        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return ['ok' => true, 'remaining' => $limit, 'retry_after' => $retryAfter, 'limit' => $limit];
        }

        $path = $dir . '/' . hash('sha1', $key) . '.json';
        $count = 0;
        $storedWindow = $window;
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            if (is_string($raw) && $raw !== '') {
                try {
                    $decoded = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        $storedWindow = (int) ($decoded['window'] ?? $window);
                        $count = (int) ($decoded['count'] ?? 0);
                    }
                } catch (\JsonException) {
                    $count = 0;
                    $storedWindow = $window;
                }
            }
        }

        if ($storedWindow !== $window) {
            $count = 0;
        }

        $count++;
        if ($count > $limit) {
            self::write($path, $window, $count);
            return ['ok' => false, 'remaining' => 0, 'retry_after' => $retryAfter, 'limit' => $limit];
        }

        self::write($path, $window, $count);
        return [
            'ok' => true,
            'remaining' => max(0, $limit - $count),
            'retry_after' => $retryAfter,
            'limit' => $limit,
        ];
    }

    private static function write(string $path, int $window, int $count): void
    {
        $payload = json_encode(['window' => $window, 'count' => $count], JSON_THROW_ON_ERROR);
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $payload) === false) {
            return;
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
        }
    }

    private static function dir(): string
    {
        $root = defined('SB_CACHE') ? SB_CACHE : (defined('ROOT') ? ROOT . 'cache/' : sys_get_temp_dir() . '/sbpp-rest-rl/');
        return rtrim(str_replace('\\', '/', $root), '/') . '/rest-rl';
    }
}
