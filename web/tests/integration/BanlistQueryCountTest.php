<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Sbpp\Tests\QueryCountAssertions;
use Smarty\Smarty;

/**
 * Pins that `page.banlist.php` issues a bounded number of database
 * queries regardless of how many bans are on the page. Lookups that
 * need per-row data (duplicate-ban check, banlog, comments,
 * RemovedBy admin name, GeoIP country write-back) run as
 * `WHERE ... IN (...)` batches for the whole page.
 *
 * `#[DataProvider]` asserts the same query-count bound at several
 * row counts. Each case runs in its own process because
 * `page.banlist.php` declares top-level functions PHP can't
 * redeclare in a single process (`#[RunInSeparateProcess]`, same
 * shape as `Php82DeprecationsTest`).
 */
final class BanlistQueryCountTest extends ApiTestCase
{
    use QueryCountAssertions;

    /**
     * Headroom above the expected fixed shape of a correctly-batched
     * render: one paginated list SELECT, one COUNT for pagination,
     * and a small constant number of `WHERE ... IN (...)` batched
     * lookups (removed-by admin names, active-sibling counts, banlog,
     * comments, GeoIP write-back) plus session/permission bootstrap
     * queries. None of those should multiply by row count; the actual
     * fixed cost measures at 11 queries regardless of row count.
     */
    private const MAX_EXPECTED_QUERIES = 13;

    private function makeStubTheme(): Smarty
    {
        return new class extends Smarty {
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
    }

    private function bootRenderHarness(): void
    {
        $GLOBALS['theme']    = $this->makeStubTheme();
        $GLOBALS['userbank'] = $GLOBALS['userbank'] ?? new \CUserManager(null);
        $GLOBALS['username'] = $GLOBALS['username'] ?? 'tester';
    }

    private function renderBanlistPage(): void
    {
        ob_start();
        try {
            require ROOT . 'pages/page.banlist.php';
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Seed $count active permanent bans with distinct authids/names
     * so each row is a genuinely separate lookup target (not
     * deduplicated by any cache keyed on a repeated value).
     */
    private function seedBans(int $count): void
    {
        $pdo    = Fixture::rawPdo();
        $now    = time();
        $aid    = Fixture::adminAid();
        $insert = $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (type, ip, authid, name, created, ends, length, reason, ureason, aid, RemovedBy, RemovedOn, RemoveType)
             VALUES (0, NULL, ?, ?, ?, 0, 0, ?, NULL, ?, NULL, NULL, NULL)',
            DB_PREFIX,
        ));

        for ($i = 0; $i < $count; $i++) {
            $insert->execute([
                'STEAM_0:1:' . (60000 + $i),
                'QueryCountFixture' . $i,
                $now - $i,
                'reason ' . $i,
                $aid,
            ]);
        }
    }

    /** @return array<string, array{int}> */
    public static function rowCountProvider(): array
    {
        return [
            '3 bans'  => [3],
            '10 bans' => [10],
            '25 bans' => [25],
        ];
    }

    #[DataProvider('rowCountProvider')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistQueryCountStaysBoundedRegardlessOfRowCount(int $rowCount): void
    {
        $this->loginAsAdmin();
        $this->bootRenderHarness();
        $this->seedBans($rowCount);

        $_GET     = ['p' => 'banlist'];
        $_SESSION = $_SESSION ?? [];

        $this->assertQueryCountAtMost(
            self::MAX_EXPECTED_QUERIES,
            fn () => $this->renderBanlistPage(),
            "banlist with {$rowCount} bans must not scale its query count with row count",
        );
    }
}
