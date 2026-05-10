<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PDOException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Db\Database;
use Sbpp\Tests\ApiTestCase;
use Smarty\Smarty;

/**
 * Issue #1314: `web/pages/admin.srvadmins.php` issued a query that
 * mentioned the named placeholder `:sid` twice (one in the outer
 * `WHERE server_id = :sid`, one in the inner subquery's
 * `WHERE server_id = :sid`) but called `bind(':sid', ...)` only
 * ONCE. Under emulated prepares (PDO's pre-#1124 default), client-side
 * substitution rewrote every `:sid` occurrence to the literal value
 * before the SQL hit MariaDB, so the duplicate-name pattern Just
 * Worked. After #1124 / #1167 flipped `PDO::ATTR_EMULATE_PREPARES`
 * to `false` (so `LIMIT '0','30'` would stop tripping MariaDB strict
 * mode), the MySQL driver started expanding each `:name` occurrence
 * into its own positional `?` slot in the prepared statement —
 * `bind()`-ing one occurrence leaves the others unbound and
 * `execute()` raises `SQLSTATE[HY093] Invalid parameter number`.
 *
 * The fix renames the inner placeholder to `:sid_inner` and binds
 * both. The two test methods here pin both halves of the contract:
 *
 *   1. {@see testReusedNamedPlaceholderUnderNativePreparesIsRejected}
 *      — a small standalone query against `Sbpp\Db\Database` that
 *      reproduces the offending shape (one `bind()` for two `:sid`
 *      slots) and asserts it throws `HY093`. This documents WHY the
 *      rename in `admin.srvadmins.php` matters; if a future
 *      contributor "tidies up" the SQL back into `:sid` + a single
 *      bind, this test fails before the page does in production.
 *      It also doubles as a regression guard if anyone re-flips
 *      `EMULATE_PREPARES` back to `true` (which would silently
 *      reintroduce the original masking).
 *
 *   2. {@see testAdminSrvadminsPageRendersWithoutPdoException} — the
 *      end-to-end shape: requires the page handler with `$_GET['id']`
 *      set, asserts the SELECT prepares + executes cleanly through
 *      the global `$PDO` wrapper. Mirrors the
 *      Php82DeprecationsTest stub-Smarty + process-isolation pattern
 *      so the page handler's top-level helper declarations don't
 *      collide across cases and `$_GET` / `$_SESSION` state stays
 *      clean per case.
 */
final class SrvAdminsPdoParamTest extends ApiTestCase
{
    /**
     * Capture-only Smarty stub. The page handler calls
     * `$theme->assign(...)` and `$theme->display(...)`; we don't care
     * about the rendered output, only that the PHP code path
     * (including the SELECT under test) runs without raising.
     * Mirrors `Php82DeprecationsTest::makeStubTheme()`.
     */
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

    /**
     * Wire the globals every page handler under `web/pages/*` reads
     * straight from `$GLOBALS` (`$theme`, `$userbank`, `$PDO`). The
     * production `init.php` does this on every real request; for the
     * test we mirror what `Php82DeprecationsTest::bootRenderHarness()`
     * sets up so the require'd page handler finds the same names.
     */
    private function bootRenderHarness(): Smarty
    {
        $theme = $this->makeStubTheme();
        $GLOBALS['theme']    = $theme;
        $GLOBALS['userbank'] = $GLOBALS['userbank'] ?? new \CUserManager(null);
        $GLOBALS['username'] = $GLOBALS['username'] ?? 'tester';

        return $theme;
    }

    /**
     * Standalone contract pin: under the panel's production PDO
     * options (`EMULATE_PREPARES => false`, set in
     * `Sbpp\Db\Database::__construct`), reusing the same `:name`
     * placeholder more than once with a SINGLE `bind()` call leaves
     * the second slot unbound and `PDOStatement::execute()` raises
     * `SQLSTATE[HY093] Invalid parameter number`.
     *
     * Constructing a fresh `Database` (rather than reaching for
     * `$GLOBALS['PDO']`) keeps the test self-contained — and proves
     * the assertion is about `Database`'s configuration (the
     * `EMULATE_PREPARES => false` option in the constructor), not
     * about whatever stale state another test might have left on
     * the global wrapper.
     *
     * The `:prefix_admins` table is one of the tables `Fixture` seeds
     * by default (the admin row from `seedAdmin`), so the SELECT
     * itself has something to query — the assertion is purely on
     * the parameter-binding mismatch, not on row presence.
     */
    public function testReusedNamedPlaceholderUnderNativePreparesIsRejected(): void
    {
        $db = new Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);

        // Two `:sid` occurrences, one `bind()` call. This is the
        // exact failure mode `admin.srvadmins.php` exhibited before
        // the fix.
        $db->query('SELECT 1 FROM `:prefix_admins` WHERE aid = :sid OR aid = :sid LIMIT 1');
        $db->bind(':sid', 1);

        $this->expectException(PDOException::class);
        $this->expectExceptionMessageMatches('/HY093|Invalid parameter number/i');

        $db->resultset();
    }

    /**
     * The headline regression: hitting `?p=admin&c=servers&o=admincheck&id=<sid>`
     * pre-fix raised `PDOException` from the SELECT in
     * `admin.srvadmins.php` (line 38 in the issue trace). The test
     * requires the page file with `$_GET['id']` set so the same
     * code path runs, and asserts no exception escapes.
     *
     * Process-isolation matches `Php82DeprecationsTest`: the page
     * handler defines top-level helpers / variables PHP can't
     * redeclare in a single process across multiple test cases, and
     * we want a clean `$_GET` / `$_SESSION` / `$GLOBALS` per case.
     *
     * `$_GET['id']` is set to `0` deliberately — the seeded test DB
     * has no `:prefix_admins_servers_groups` rows, so any server
     * id (real or sentinel `0`) returns an empty result set. The
     * regression is on the SELECT's PREPARE + EXECUTE step, which
     * runs whether the result set is empty or not. Setting `0`
     * also avoids needing to seed a server row just for the test.
     *
     * `checkMultiplePlayers($sid, ...)` is gated on
     * `count($admsteam) > 0`; with an empty result set the call is
     * skipped, so we don't risk a real RCON socket open against an
     * arbitrary IP.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAdminSrvadminsPageRendersWithoutPdoException(): void
    {
        $this->loginAsAdmin();
        $this->bootRenderHarness();

        $_SESSION = [];
        $_GET     = ['p' => 'admin', 'c' => 'servers', 'o' => 'admincheck', 'id' => '0'];

        ob_start();
        try {
            require ROOT . 'pages/admin.srvadmins.php';
        } finally {
            ob_end_clean();
        }

        $this->assertTrue(true,
            'admin.srvadmins.php must prepare + execute its admin-list SELECT '
            . 'without raising PDOException HY093 (issue #1314).');
    }
}
