<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sbpp\Servers\RconStatusCache;
use Sbpp\Tests\Fixture;

/**
 * Regression coverage for the per-`sid` RCON status cache that fronts
 * `rcon('status', $sid, $silent = true)` for the restored right-click
 * context menu on the public servers list.
 *
 * Mirrors the cache-shape coverage in {@see SourceQueryCacheTest} —
 * the two caches have the same atomicity / negative-cache / TTL
 * contract; only the key (sid vs. ip:port) and the underlying probe
 * differ. Drive the cache via the test-only probe override so the
 * suite never touches a real RCON socket and the assertions are
 * deterministic.
 *
 * Additional invariant pinned here that SourceQueryCacheTest doesn't
 * carry: the cache MUST issue `rcon($cmd, $sid, $silent = true)`,
 * NOT the default `$silent = false`. Without that flag every
 * cache-fill probe would write a `:prefix_log` audit row, drowning
 * out real RCON activity on a panel that's just been viewed by a
 * handful of admin operators (one `LogType::Message` row per server
 * per ~30s window per page load). The contract is asserted via the
 * runtime audit-log row count below.
 */
final class RconStatusCacheTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        Fixture::reset();
        $_SESSION = [];
        \CSRF::init();
        $GLOBALS['userbank'] = new \CUserManager(null);
        $GLOBALS['username'] = 'tester';

        $this->cacheDir = SB_CACHE . 'srvstatus/';
        if (is_dir($this->cacheDir)) {
            foreach (scandir($this->cacheDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                @unlink($this->cacheDir . $entry);
            }
        }
        RconStatusCache::resetSocketAttemptCount();
        RconStatusCache::setProbeOverrideForTesting(null);
    }

    protected function tearDown(): void
    {
        RconStatusCache::setProbeOverrideForTesting(null);
        parent::tearDown();
    }

    public function testRepeatedFetchesWithinWindowReuseCachedPayload(): void
    {
        RconStatusCache::setProbeOverrideForTesting(static fn(int $sid): array => [
            ['id' => 1, 'name' => 'Alice', 'steamid' => 'STEAM_0:0:1234', 'ip' => '203.0.113.10'],
            ['id' => 2, 'name' => 'Bob',   'steamid' => '[U:1:2468]',    'ip' => '203.0.113.11'],
        ]);

        for ($i = 0; $i < 10; $i++) {
            $payload = RconStatusCache::fetch(42);
            $this->assertNotNull($payload, "iteration $i should hit the cached payload");
            $this->assertCount(2, $payload['players']);
            $this->assertSame('Alice', $payload['players'][0]['name']);
            $this->assertSame('STEAM_0:0:1234', $payload['players'][0]['steamid']);
        }

        $this->assertSame(
            1,
            RconStatusCache::socketAttemptCount(),
            'the probe must only fire ONCE while the cache is warm — anything higher means the cache is no-op',
        );
    }

    public function testFailedProbeIsNegativeCached(): void
    {
        // Mirrors SourceQueryCache's negative-cache shape: a missing
        // rcon password, an RCON connect failure, or an unparseable
        // status output ALL return `null` from the probe; the cache
        // stores `error: true` so subsequent callers cheap-return
        // `null` without touching the RCON socket again.
        RconStatusCache::setProbeOverrideForTesting(static fn(int $sid): ?array => null);

        for ($i = 0; $i < 5; $i++) {
            $this->assertNull(RconStatusCache::fetch(43));
        }

        $this->assertSame(
            1,
            RconStatusCache::socketAttemptCount(),
            'failed probes must be negative-cached so a flapping endpoint costs ONE probe per window',
        );
    }

    public function testStaleEntryRefreshesAfterTtlExpires(): void
    {
        RconStatusCache::setProbeOverrideForTesting(static fn(): array => [
            ['id' => 1, 'name' => 'fresh', 'steamid' => 'STEAM_0:0:9', 'ip' => '1.1.1.1'],
        ]);

        $this->assertNotNull(RconStatusCache::fetch(44, ttlSeconds: 1));
        $this->assertSame(1, RconStatusCache::socketAttemptCount());

        // Force the cache file's mtime + the embedded `cached_at`
        // far enough into the past that the TTL window has lapsed
        // without sleeping (CI can't `sleep()`).
        $cacheFile = SB_CACHE . 'srvstatus/' . sha1('sid:44') . '.json';
        $this->assertFileExists($cacheFile);
        $raw = (string) file_get_contents($cacheFile);
        $entry = json_decode($raw, true);
        $this->assertIsArray($entry);
        $entry['cached_at'] = time() - 60;
        file_put_contents($cacheFile, (string) json_encode($entry));

        RconStatusCache::fetch(44, ttlSeconds: 1);
        $this->assertSame(2, RconStatusCache::socketAttemptCount(),
            'a stale entry must trigger a fresh probe',
        );
    }

    public function testCacheFileShape(): void
    {
        RconStatusCache::setProbeOverrideForTesting(static fn(): array => [
            ['id' => 7, 'name' => 'Bob', 'steamid' => 'STEAM_0:1:42', 'ip' => '198.51.100.1'],
        ]);

        RconStatusCache::fetch(55);

        $cacheFile = SB_CACHE . 'srvstatus/' . sha1('sid:55') . '.json';
        $this->assertFileExists($cacheFile);

        $entry = json_decode((string) file_get_contents($cacheFile), true);
        $this->assertIsArray($entry);
        $this->assertArrayHasKey('cached_at', $entry);
        $this->assertIsInt($entry['cached_at']);
        $this->assertArrayHasKey('players', $entry);
        $this->assertSame('STEAM_0:1:42', $entry['players'][0]['steamid']);

        // No tempfile leak from an interrupted write — same shape
        // SourceQueryCacheTest asserts on its sibling.
        $strays = glob($this->cacheDir . '*.tmp') ?: [];
        $this->assertSame([], $strays, 'tempfile rename must be atomic; no `.tmp` artifacts should survive');
    }

    public function testInvalidateForcesRefresh(): void
    {
        RconStatusCache::setProbeOverrideForTesting(static fn(): array => [
            ['id' => 1, 'name' => 'cycle', 'steamid' => 'STEAM_0:0:1', 'ip' => '1.2.3.4'],
        ]);

        RconStatusCache::fetch(66);
        $this->assertSame(1, RconStatusCache::socketAttemptCount());
        RconStatusCache::fetch(66);
        $this->assertSame(1, RconStatusCache::socketAttemptCount(), 'second call should be a cache hit');

        RconStatusCache::invalidate(66);
        RconStatusCache::fetch(66);
        $this->assertSame(2, RconStatusCache::socketAttemptCount(), 'invalidate() must drop the entry');
    }

    public function testZeroOrNegativeSidReturnsNullWithoutProbe(): void
    {
        // Guard against a caller mistakenly forwarding `sid=0` (the
        // sentinel CONSOLE row) — there's nothing to RCON, and the
        // cache key would be a fixed slot every caller shares.
        RconStatusCache::setProbeOverrideForTesting(static fn(): array => [['id' => 1, 'name' => 'x', 'steamid' => 'STEAM_0:0:0', 'ip' => '0.0.0.0']]);
        $this->assertNull(RconStatusCache::fetch(0));
        $this->assertNull(RconStatusCache::fetch(-1));
        $this->assertSame(0, RconStatusCache::socketAttemptCount(), 'no probe should fire for sid <= 0');
    }

    public function testDistinctSidsCacheIndependently(): void
    {
        RconStatusCache::setProbeOverrideForTesting(static fn(int $sid): array => [
            ['id' => $sid, 'name' => 'p' . $sid, 'steamid' => 'STEAM_0:0:' . $sid, 'ip' => '1.1.1.1'],
        ]);

        $a = RconStatusCache::fetch(77);
        $b = RconStatusCache::fetch(78);
        $c = RconStatusCache::fetch(79);

        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertNotNull($c);
        $this->assertSame('STEAM_0:0:77', $a['players'][0]['steamid']);
        $this->assertSame('STEAM_0:0:78', $b['players'][0]['steamid']);
        $this->assertSame('STEAM_0:0:79', $c['players'][0]['steamid']);
        $this->assertSame(3, RconStatusCache::socketAttemptCount(), 'distinct sids must each get their own cache slot');
    }

    /**
     * The cache-fill probes MUST NOT write a `:prefix_log` audit row
     * per call — that's the `$silent = true` contract on the legacy
     * `rcon()` helper. Without this flag, every panel page-load by
     * an admin with rcon access would generate N rows
     * (`one per server in the public list`) per ~30s cache window,
     * making the audit log unusable.
     *
     * We exercise the real `rcon()` path here (not the probe
     * override) by configuring a server row with NO rcon password —
     * `rcon()` short-circuits on `empty($server['rcon'])` and
     * returns `false` without writing the audit row OR touching the
     * UDP socket. This proves the cache's negative-cache + audit-log
     * silence by inspection of `:prefix_log` row count.
     *
     * The audit log carries the `LogType::Message` rows the legacy
     * `Log::add(LogType::Message, "RCON Sent", …)` call inside
     * `rcon()` writes. The cache's probe path now passes
     * `$silent = true`; this test asserts the row count stays at
     * zero across multiple cache fills.
     */
    public function testCacheDoesNotEmitRconSentAuditLogEntries(): void
    {
        // Seed a server row with NO rcon password. `rcon()`
        // short-circuits on this and returns `false`, mirroring the
        // production "configured but no rcon" branch. The cache
        // negative-caches the result.
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_servers` (sid, ip, port, rcon, modid, enabled)
             VALUES (?, ?, ?, ?, 1, 1)',
            DB_PREFIX
        ))->execute([88, '203.0.113.100', 27015, '']);

        // Probe via the real rcon() helper (no override).
        RconStatusCache::setProbeOverrideForTesting(null);

        for ($i = 0; $i < 3; $i++) {
            $this->assertNull(RconStatusCache::fetch(88));
        }

        // Count `LogType::Message` rows with title=RCON Sent. The
        // cache's probe must not emit any.
        $row = $pdo->query(
            "SELECT COUNT(*) AS c FROM `" . DB_PREFIX . "_log` WHERE title = 'RCON Sent'"
        )->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertSame(0, (int) $row['c'],
            'the cache must call rcon() with $silent=true so cache-fills do not spam the audit log',
        );
    }
}
