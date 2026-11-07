<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * Issue #1614: the panel no longer ships MaxMind's GeoLite2-Country
 * database. The GeoLite EULA forbids redistribution and requires every
 * copy to be replaced within 30 days of each MaxMind release, which a
 * file in the repo and the release zip can't do. Operators download it
 * themselves (geoipupdate) and point `MMDB_PATH` at it.
 *
 * This test pins the "feature is off without the database" contract:
 *
 *   1. No `.mmdb` is tracked in the repository, and `web/data/` carries
 *      a deny-all `.htaccess` so an operator-supplied copy dropped there
 *      is never served (serving it would itself be redistribution).
 *   2. `GeoIpAvailable()` reports false for a missing / empty path and
 *      true for a readable file; `FetchIp()` short-circuits to "zz".
 *   3. With country lookup enabled but no database, the banlist neither
 *      looks up nor writes "zz" back into `:prefix_bans.country`, so the
 *      rows resolve once the operator adds a database later.
 *   4. The footer's MaxMind attribution renders only when a database is
 *      present.
 */
final class GeoIpOptionalTest extends ApiTestCase
{
    public function testRepositoryDoesNotShipAGeoLiteDatabase(): void
    {
        $repoRoot = dirname(ROOT);
        $out      = [];
        $code     = 1;
        @exec('git -C ' . escapeshellarg($repoRoot) . ' ls-files 2>/dev/null', $out, $code);
        if ($code !== 0 || $out === []) {
            // The dev container mounts web/ without .git; fall back to
            // the legacy shipped location.
            $out = array_map(
                static fn (string $f): string => substr($f, strlen(ROOT)),
                glob(ROOT . 'data/*.mmdb') ?: [],
            );
        }

        $mmdb = array_values(array_filter(
            $out,
            static fn (string $f): bool => str_ends_with(strtolower($f), '.mmdb'),
        ));
        $this->assertSame(
            [],
            $mmdb,
            'MaxMind GeoLite databases must not be committed or shipped (#1614). '
            . 'Operators download them under the GeoLite EULA themselves.',
        );
    }

    public function testDataDirectoryDeniesWebAccess(): void
    {
        $htaccess = ROOT . 'data/.htaccess';
        $this->assertFileExists($htaccess);
        $this->assertStringContainsString('Require all denied', (string) file_get_contents($htaccess));

        foreach (['sbpp-prod.conf', 'sbpp-dev.conf'] as $conf) {
            $path = dirname(ROOT) . '/docker/apache/' . $conf;
            if (!is_file($path)) {
                // The dev container bind-mounts docker/ read-only; CI
                // without it still has the .htaccess assertion above.
                continue;
            }
            $this->assertMatchesRegularExpression(
                '#<DirectoryMatch "\^\$\{APACHE_DOCUMENT_ROOT\}/\(?[a-z|]*data[a-z|]*\)?\(/\|\$\)">#',
                (string) file_get_contents($path),
                "{$conf} must deny direct access to data/ (#1614).",
            );
        }
    }

    public function testGeoIpAvailableReflectsTheFileOnDisk(): void
    {
        $this->assertFalse(GeoIpAvailable(''));
        $this->assertFalse(GeoIpAvailable(sys_get_temp_dir() . '/sbpp-no-such-' . bin2hex(random_bytes(4)) . '.mmdb'));

        $tmp = tempnam(sys_get_temp_dir(), 'sbpp-mmdb-');
        $this->assertIsString($tmp);
        try {
            $this->assertTrue(GeoIpAvailable($tmp));
        } finally {
            @unlink($tmp);
        }
    }

    public function testFetchIpReturnsUnknownWithoutADatabase(): void
    {
        if (GeoIpAvailable()) {
            $this->markTestSkipped('A GeoLite database is installed at MMDB_PATH on this host.');
        }
        $this->assertSame('zz', FetchIp('8.8.8.8'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistSkipsLookupAndWriteBackWithoutADatabase(): void
    {
        if (GeoIpAvailable()) {
            $this->markTestSkipped('A GeoLite database is installed at MMDB_PATH on this host.');
        }

        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            "REPLACE INTO `%s_settings` (`setting`, `value`) VALUES ('banlist.nocountryfetch', '0')",
            DB_PREFIX,
        ))->execute();
        \Config::init($GLOBALS['PDO']);

        $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (type, ip, authid, name, created, ends, length, reason, aid, country)
             VALUES (1, ?, ?, ?, ?, 0, 0, ?, ?, NULL)',
            DB_PREFIX,
        ))->execute(['203.0.113.7', '', 'GeoIpFixture', time(), 'geoip', Fixture::adminAid()]);
        $bid = (int) $pdo->lastInsertId();

        $this->loginAsAdmin();
        $GLOBALS['theme'] = new class extends Smarty {
            /** @phpstan-ignore method.childParameterType */
            public function assign($tpl_var, $value = null, $nocache = false, $scope = null)
            {
                return $this;
            }

            public function display($template = null, $cache_id = null, $compile_id = null)
            {
                return '';
            }
        };
        $GLOBALS['username'] = $GLOBALS['username'] ?? 'tester';

        ob_start();
        try {
            require ROOT . 'pages/page.banlist.php';
        } finally {
            ob_end_clean();
        }

        $stmt = $pdo->prepare(sprintf('SELECT country FROM `%s_bans` WHERE bid = ?', DB_PREFIX));
        $stmt->execute([$bid]);
        $this->assertNull(
            $stmt->fetchColumn(),
            'Without a GeoLite database the banlist must not cache "zz" as the country (#1614); '
            . 'otherwise rows stay "unknown" after the operator installs a database.',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFooterAttributionFollowsDatabasePresence(): void
    {
        $this->assertStringNotContainsString('data-testid="app-footer-geoip"', $this->renderFooter(false));

        $html = $this->renderFooter(true);
        $this->assertStringContainsString('data-testid="app-footer-geoip"', $html);
        $this->assertStringContainsString('GeoLite Data created by MaxMind', $html);
        $this->assertStringContainsString('href="https://www.maxmind.com"', $html);
    }

    private function renderFooter(bool $attribution): string
    {
        require_once INCLUDES_PATH . '/SmartyCustomFunctions.php';

        $compileDir = sys_get_temp_dir() . '/sbpp-test-smarty-geoip-' . getmypid();
        if (!is_dir($compileDir)) {
            mkdir($compileDir, 0o775, true);
        }

        $theme = new Smarty();
        $theme->setUseSubDirs(false);
        $theme->setCompileId('default');
        $theme->setCaching(Smarty::CACHING_OFF);
        $theme->setForceCompile(true);
        $theme->setTemplateDir(SB_THEMES . SB_THEME);
        $theme->setCompileDir($compileDir);
        $theme->setCacheDir($compileDir);
        $theme->setEscapeHtml(true);

        $theme->assign('version',              'test');
        $theme->assign('git',                  '');
        $theme->assign('palette_actions_json', '[]');
        $theme->assign('theme_url',            'themes/default');
        $theme->assign('geoip_attribution',    $attribution);

        ob_start();
        try {
            $theme->display('core/footer.tpl');
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }
}
