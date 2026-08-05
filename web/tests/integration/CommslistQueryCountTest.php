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
 * Pins that `page.commslist.php` issues a bounded number of database
 * queries regardless of how many comm blocks are on the page.
 * Lookups that need per-row data (RemovedBy admin name, active
 * sibling-block count, comments) run as `WHERE ... IN (...)` batches
 * for the whole page.
 *
 * Same shape as `BanlistQueryCountTest`: `#[DataProvider]` +
 * `#[RunInSeparateProcess]` because `page.commslist.php` declares a
 * top-level `setPostKey()` function.
 */
final class CommslistQueryCountTest extends ApiTestCase
{
    use QueryCountAssertions;

    /**
     * Headroom above the expected fixed shape of a correctly-batched
     * render: one paginated list SELECT, one COUNT for pagination,
     * a small constant number of `WHERE ... IN (...)` batched
     * lookups (removed-by admin names, active-sibling counts,
     * comments), and the enabled-servers filter dropdown query, plus
     * session/permission bootstrap queries. None of those should
     * multiply by row count.
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

    private function renderCommslistPage(): void
    {
        ob_start();
        try {
            require ROOT . 'pages/page.commslist.php';
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Seed $count active permanent mute blocks with distinct authids/
     * names so each row is a genuinely separate lookup target.
     */
    private function seedComms(int $count): void
    {
        $pdo    = Fixture::rawPdo();
        $now    = time();
        $aid    = Fixture::adminAid();
        $insert = $pdo->prepare(sprintf(
            'INSERT INTO `%s_comms` (type, authid, name, created, ends, length, reason, ureason, aid, RemovedBy, RemovedOn, RemoveType)
             VALUES (1, ?, ?, ?, 0, 0, ?, NULL, ?, NULL, NULL, NULL)',
            DB_PREFIX,
        ));

        for ($i = 0; $i < $count; $i++) {
            $insert->execute([
                'STEAM_0:1:' . (61000 + $i),
                'QueryCountCommFixture' . $i,
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
            '3 comms'  => [3],
            '10 comms' => [10],
            '25 comms' => [25],
        ];
    }

    #[DataProvider('rowCountProvider')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCommslistQueryCountStaysBoundedRegardlessOfRowCount(int $rowCount): void
    {
        $this->loginAsAdmin();
        $this->bootRenderHarness();
        $this->seedComms($rowCount);

        $_GET     = ['p' => 'commslist'];
        $_SESSION = $_SESSION ?? [];

        $this->assertQueryCountAtMost(
            self::MAX_EXPECTED_QUERIES,
            fn () => $this->renderCommslistPage(),
            "commslist with {$rowCount} comms must not scale its query count with row count",
        );
    }
}
