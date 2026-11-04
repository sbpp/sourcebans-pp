<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Servers\SourceQueryCache;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * Issue #1611: the public "Report a player" form (`?p=submit`) used to
 * open a fresh A2S `GetInfo` socket to every enabled server on every
 * render just to label the server `<select>`. With 20+ servers that
 * took 5-10 seconds, and any anonymous visitor could trigger the fan-out
 * by reloading the page (the amplification shape #1311 closed on the
 * servers page).
 *
 * `page.submit.php` now reads the hostnames through
 * `Sbpp\Servers\SourceQueryCache`, the same per-`(ip, port)` cache the
 * public servers page fills. These tests drive the cache through its
 * test-only probe override, so they never touch the live UDP path.
 *
 * Each test runs in its own process: the page handler is `require`d,
 * and process isolation keeps `$GLOBALS['theme']` / `$_POST` from
 * leaking between tests (mirrors {@see HomeDashboardAnnouncementTest}).
 */
final class SubmitBanServerListCacheTest extends ApiTestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDir = SB_CACHE . 'srvquery/';
        $this->clearCacheDir();
        SourceQueryCache::resetSocketAttemptCount();
        SourceQueryCache::setProbeOverrideForTesting(null);
    }

    protected function tearDown(): void
    {
        SourceQueryCache::setProbeOverrideForTesting(null);
        $this->clearCacheDir();
        parent::tearDown();
    }

    private function clearCacheDir(): void
    {
        if (!is_dir($this->cacheDir)) {
            return;
        }
        foreach (scandir($this->cacheDir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                @unlink($this->cacheDir . $entry);
            }
        }
    }

    private function seedServer(int $sid, string $ip, int $port): void
    {
        Fixture::rawPdo()->prepare(sprintf(
            'INSERT INTO `%s_servers` (sid, ip, port, rcon, modid, enabled) VALUES (?, ?, ?, ?, ?, ?)',
            DB_PREFIX,
        ))->execute([$sid, $ip, $port, '', 0, 1]);
    }

    /**
     * Render `page.submit.php` against a Smarty stub that records the
     * View DTO's assignments instead of rendering the template, and
     * return the `server_list` the handler built.
     *
     * @return list<array<string, mixed>>
     */
    private function renderServerList(): array
    {
        $theme = new class extends Smarty {
            /** @var array<string,mixed> */
            public array $captured = [];

            /** @phpstan-ignore method.childParameterType */
            public function assign($tpl_var, $value = null, $nocache = false, $scope = null)
            {
                if (is_string($tpl_var)) {
                    $this->captured[$tpl_var] = $value;
                }
                return $this;
            }

            public function display($template = null, $cache_id = null, $compile_id = null)
            {
                return '';
            }
        };
        $GLOBALS['theme']    = $theme;
        $GLOBALS['userbank'] = $GLOBALS['userbank'] ?? new \CUserManager(null);
        $_POST = [];
        $_GET  = [];

        ob_start();
        try {
            require ROOT . 'pages/page.submit.php';
        } finally {
            ob_end_clean();
        }

        $this->assertArrayHasKey('server_list', $theme->captured);
        $list = $theme->captured['server_list'];
        $this->assertIsArray($list);

        return $list;
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testServerListReusesTheWarmQueryCache(): void
    {
        $this->seedServer(1, '203.0.113.10', 27015);
        $this->seedServer(2, '203.0.113.11', 27016);

        // Warm the cache the way a visit to the public servers page
        // would.
        SourceQueryCache::setProbeOverrideForTesting(static fn (string $ip, int $port): array => [
            'info'    => ['HostName' => "Server {$ip}:{$port}"],
            'players' => [],
        ]);
        SourceQueryCache::fetch('203.0.113.10', 27015);
        SourceQueryCache::fetch('203.0.113.11', 27016);
        $this->assertSame(2, SourceQueryCache::socketAttemptCount());

        $list = $this->renderServerList();

        $this->assertSame(
            ['Server 203.0.113.10:27015', 'Server 203.0.113.11:27016'],
            array_column($list, 'hostname'),
        );
        $this->assertSame(2, SourceQueryCache::socketAttemptCount(),
            'Rendering the report form inside the cache window must not probe the servers again.');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testColdCacheProbesOncePerServerAndRepeatRendersCoalesce(): void
    {
        $this->seedServer(1, '203.0.113.10', 27015);
        $this->seedServer(2, '203.0.113.11', 27016);

        SourceQueryCache::setProbeOverrideForTesting(static fn (string $ip, int $port): array => [
            'info'    => ['HostName' => "Server {$ip}:{$port}"],
            'players' => [],
        ]);

        $first = $this->renderServerList();
        $this->assertSame(2, SourceQueryCache::socketAttemptCount());

        $second = $this->renderServerList();
        $this->assertSame(2, SourceQueryCache::socketAttemptCount(),
            'A reload inside the cache window must not fan out another round of A2S probes.');
        $this->assertSame(array_column($first, 'hostname'), array_column($second, 'hostname'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testUnreachableServerKeepsTheErrorLabel(): void
    {
        $this->seedServer(1, '203.0.113.10', 27015);

        SourceQueryCache::setProbeOverrideForTesting(static fn (string $ip, int $port): ?array => null);

        $list = $this->renderServerList();

        $this->assertCount(1, $list);
        $this->assertSame(1, (int) $list[0]['sid']);
        $this->assertSame('Error Connecting (203.0.113.10:27015)', $list[0]['hostname']);
    }
}
