<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sbpp\Servers\SourceQueryCache;

/**
 * Regression coverage for issue #1311: the public servers page used to
 * fan one anonymous-callable A2S `GetInfo + GetPlayers` UDP query out
 * to each configured server on every page load AND on every per-tile
 * Re-query click. `Sbpp\Servers\SourceQueryCache` is the on-disk cache
 * that absorbs back-to-back probes inside its window so a hand-mash of
 * the refresh button (or a cURL loop hitting `?p=servers`) costs at
 * most ONE socket attempt per `(ip, port)` per ~30s.
 *
 * The tests here drive the cache directly via the test-only probe
 * override, so they never touch the live UDP path. The matching
 * handler-shape tests (that the cached payload still maps to the
 * documented JSON envelope) live alongside the existing per-handler
 * coverage in `web/tests/api/ServersTest.php`.
 */
final class SourceQueryCacheTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = SB_CACHE . 'srvquery/';
        if (is_dir($this->cacheDir)) {
            foreach (scandir($this->cacheDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                @unlink($this->cacheDir . $entry);
            }
        }
        SourceQueryCache::resetSocketAttemptCount();
        SourceQueryCache::setProbeOverrideForTesting(null);
    }

    protected function tearDown(): void
    {
        SourceQueryCache::setProbeOverrideForTesting(null);
        parent::tearDown();
    }

    public function testRepeatedFetchesWithinWindowReuseTheCachedPayload(): void
    {
        // The cache key is `(ip, port)` — multiple panel hits, multiple
        // sids that happen to point at the same game server, and the
        // page-load fan-out + a stray Re-query click should all coalesce
        // into ONE socket attempt while the cache is warm.
        SourceQueryCache::setProbeOverrideForTesting(static function (string $ip, int $port): array {
            return [
                'info'    => ['HostName' => 'TF #1', 'Players' => 3, 'MaxPlayers' => 24, 'Map' => 'cp_dustbowl', 'Os' => 'l', 'Secure' => true],
                'players' => [['Id' => 0, 'Name' => 'foo', 'Frags' => 1, 'Time' => 60, 'TimeF' => '00:01']],
            ];
        });

        for ($i = 0; $i < 10; $i++) {
            $payload = SourceQueryCache::fetch('203.0.113.10', 27015);
            $this->assertNotNull($payload, "iteration $i should hit the cached payload");
            $this->assertSame('TF #1', $payload['info']['HostName']);
        }

        $this->assertSame(
            1,
            SourceQueryCache::socketAttemptCount(),
            'the probe must only fire ONCE while the cache is warm — anything higher is the #1311 amplifier reopening',
        );
    }

    public function testFailedProbeIsAlsoCachedSoOfflineServersDoNotKeepHittingTheSocket(): void
    {
        // Without negative caching, an attacker hammering the refresh
        // button on a host that doesn't answer A2S would still amplify
        // 1:1 (each cache miss attempts the connect with a 1s timeout).
        // The handler turns the cached null into the documented
        // `error: 'connect'` envelope.
        SourceQueryCache::setProbeOverrideForTesting(static fn(): ?array => null);

        for ($i = 0; $i < 5; $i++) {
            $this->assertNull(SourceQueryCache::fetch('203.0.113.20', 27015));
        }

        $this->assertSame(
            1,
            SourceQueryCache::socketAttemptCount(),
            'unreachable servers must be negative-cached so a flapping host costs one probe per window, not one per click',
        );
    }

    public function testStaleEntryRefreshesAfterTtlExpires(): void
    {
        // Drive the TTL boundary by passing a tiny TTL — the second
        // call should miss the cache and re-probe. We can't `sleep()`
        // in CI, but the in-process counter is enough to assert "the
        // socket fired again after the window closed".
        SourceQueryCache::setProbeOverrideForTesting(static function (): array {
            return ['info' => ['HostName' => 'fresh'], 'players' => []];
        });

        $this->assertNotNull(SourceQueryCache::fetch('203.0.113.30', 27015, ttlSeconds: 1));
        $this->assertSame(1, SourceQueryCache::socketAttemptCount());

        // Force the cache file's mtime far enough into the past that the
        // TTL window has lapsed without sleeping.
        $cacheFile = SB_CACHE . 'srvquery/' . sha1('203.0.113.30:27015') . '.json';
        $this->assertFileExists($cacheFile);
        $raw = (string) file_get_contents($cacheFile);
        $entry = json_decode($raw, true);
        $this->assertIsArray($entry);
        $entry['cached_at'] = time() - 60;
        file_put_contents($cacheFile, (string) json_encode($entry));

        SourceQueryCache::fetch('203.0.113.30', 27015, ttlSeconds: 1);
        $this->assertSame(2, SourceQueryCache::socketAttemptCount(),
            'a stale entry must trigger a fresh probe — otherwise the cache would never refresh',
        );
    }

    public function testCacheFileIsValidJsonWithKnownShape(): void
    {
        SourceQueryCache::setProbeOverrideForTesting(static function (): array {
            return [
                'info'    => ['HostName' => 'JSON-shape', 'Players' => 0, 'MaxPlayers' => 32, 'Map' => 'pl_upward', 'Os' => 'l', 'Secure' => true],
                'players' => [],
            ];
        });

        SourceQueryCache::fetch('203.0.113.40', 27015);

        $cacheFile = SB_CACHE . 'srvquery/' . sha1('203.0.113.40:27015') . '.json';
        $this->assertFileExists($cacheFile);

        $entry = json_decode((string) file_get_contents($cacheFile), true);
        $this->assertIsArray($entry);
        $this->assertArrayHasKey('cached_at', $entry);
        $this->assertIsInt($entry['cached_at']);
        $this->assertArrayHasKey('info', $entry);
        $this->assertSame('JSON-shape', $entry['info']['HostName']);
        $this->assertArrayHasKey('players', $entry);
        $this->assertSame([], $entry['players']);
        // No tempfile leak from an interrupted write.
        $strays = glob($this->cacheDir . '*.tmp') ?: [];
        $this->assertSame([], $strays, 'tempfile rename must be atomic; no `.tmp` artifacts should survive');
    }

    public function testInvalidateForcesRefresh(): void
    {
        SourceQueryCache::setProbeOverrideForTesting(static function (): array {
            return ['info' => ['HostName' => 'cycle'], 'players' => []];
        });

        SourceQueryCache::fetch('203.0.113.50', 27015);
        $this->assertSame(1, SourceQueryCache::socketAttemptCount());
        SourceQueryCache::fetch('203.0.113.50', 27015);
        $this->assertSame(1, SourceQueryCache::socketAttemptCount(), 'second call should be a hit');

        SourceQueryCache::invalidate('203.0.113.50', 27015);
        SourceQueryCache::fetch('203.0.113.50', 27015);
        $this->assertSame(2, SourceQueryCache::socketAttemptCount(), 'invalidate() must drop the entry so the next fetch re-probes');
    }

    public function testDifferentEndpointsCacheIndependently(): void
    {
        SourceQueryCache::setProbeOverrideForTesting(static function (string $ip, int $port): array {
            return ['info' => ['HostName' => "$ip:$port"], 'players' => []];
        });

        $a = SourceQueryCache::fetch('203.0.113.60', 27015);
        $b = SourceQueryCache::fetch('203.0.113.60', 27016);
        $c = SourceQueryCache::fetch('203.0.113.61', 27015);

        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertNotNull($c);
        $this->assertSame('203.0.113.60:27015', $a['info']['HostName']);
        $this->assertSame('203.0.113.60:27016', $b['info']['HostName']);
        $this->assertSame('203.0.113.61:27015', $c['info']['HostName']);
        $this->assertSame(3, SourceQueryCache::socketAttemptCount(), 'distinct endpoints must each get their own cache slot');
    }
}
