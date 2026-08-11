<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

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
 * Pins that batched admin list pages keep a bounded Database::query
 * count as row volume grows.
 */
final class AdminPagesQueryCountTest extends ApiTestCase
{
    use QueryCountAssertions;

    private const MAX_ADMINS_QUERIES = 7;
    private const MAX_PROTESTS_QUERIES = 6;
    private const MAX_SUBMISSIONS_QUERIES = 7;
    private const MAX_GROUPS_QUERIES = 6;

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
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private function renderPage(string $relativePath): void
    {
        ob_start();
        try {
            (function () use ($relativePath): void {
                global $userbank, $theme;
                $userbank = $GLOBALS['userbank'];
                $theme    = $GLOBALS['theme'];
                require ROOT . $relativePath;
            })();
        } finally {
            ob_end_clean();
        }
    }

    /** @return array<string, array{int}> */
    public static function rowCountProvider(): array
    {
        return [
            '3 rows'  => [3],
            '15 rows' => [15],
        ];
    }

    private function seedExtraAdmins(int $count): void
    {
        $pdo  = Fixture::rawPdo();
        $hash = password_hash('x', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, validate, extraflags, immunity)
             VALUES (?, ?, ?, -1, ?, NULL, ?, 0)',
            DB_PREFIX,
        ));
        $banStmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (type, ip, authid, name, created, ends, length, reason, aid)
             VALUES (0, NULL, ?, ?, ?, 0, 0, ?, ?)',
            DB_PREFIX,
        ));
        $now = time();
        for ($i = 0; $i < $count; $i++) {
            $stmt->execute([
                'AdminQcFixture' . $i,
                'STEAM_0:1:' . (81000 + $i),
                $hash,
                'adminqc' . $i . '@example.test',
                0,
            ]);
            $aid = (int) $pdo->lastInsertId();
            $banStmt->execute([
                'STEAM_0:1:' . (91000 + $i),
                'AdminQcBan' . $i,
                $now - $i,
                'seed',
                $aid,
            ]);
        }
    }

    private function seedProtests(int $count): void
    {
        $pdo = Fixture::rawPdo();
        $now = time();
        $aid = Fixture::adminAid();
        $banInsert = $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (type, ip, authid, name, created, ends, length, reason, aid)
             VALUES (0, NULL, ?, ?, ?, 0, 0, ?, ?)',
            DB_PREFIX,
        ));
        $protestInsert = $pdo->prepare(sprintf(
            'INSERT INTO `%s_protests` (bid, datesubmitted, reason, email, archiv, archivedby, pip)
             VALUES (?, ?, ?, ?, 0, NULL, ?)',
            DB_PREFIX,
        ));
        for ($i = 0; $i < $count; $i++) {
            $banInsert->execute([
                'STEAM_0:1:' . (92000 + $i),
                'ProtestQcBan' . $i,
                $now - $i,
                'seed',
                $aid,
            ]);
            $bid = (int) $pdo->lastInsertId();
            $protestInsert->execute([
                $bid,
                $now - $i,
                'protest reason ' . $i,
                'protest' . $i . '@example.test',
                '127.0.0.1',
            ]);
        }
    }

    private function seedSubmissions(int $count): void
    {
        $pdo = Fixture::rawPdo();
        $now = time();
        $insert = $pdo->prepare(sprintf(
            'INSERT INTO `%s_submissions` (submitted, SteamId, name, email, ModID, reason, ip, subname, sip, archiv, archivedby)
             VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, 0, NULL)',
            DB_PREFIX,
        ));
        for ($i = 0; $i < $count; $i++) {
            $insert->execute([
                $now - $i,
                'STEAM_0:1:' . (93000 + $i),
                'SubmissionQc' . $i,
                'sub' . $i . '@example.test',
                'reason ' . $i,
                '127.0.0.' . (($i % 200) + 1),
                'reporter' . $i,
                '10.0.0.' . (($i % 200) + 1),
            ]);
        }
    }

    private function seedWebGroups(int $count): void
    {
        $pdo = Fixture::rawPdo();
        $groupInsert = $pdo->prepare(sprintf(
            'INSERT INTO `%s_groups` (type, name, flags)
             VALUES (1, ?, ?)',
            DB_PREFIX,
        ));
        $hash = password_hash('x', PASSWORD_BCRYPT);
        $adminInsert = $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, validate, extraflags, immunity)
             VALUES (?, ?, ?, ?, ?, NULL, ?, 0)',
            DB_PREFIX,
        ));
        for ($i = 0; $i < $count; $i++) {
            $groupInsert->execute(['GroupQc' . $i, (string) (1 << ($i % 8))]);
            $gid = (int) $pdo->lastInsertId();
            $adminInsert->execute([
                'GroupQcAdmin' . $i,
                'STEAM_0:1:' . (94000 + $i),
                $hash,
                $gid,
                'groupqc' . $i . '@example.test',
                0,
            ]);
        }
    }

    #[DataProvider('rowCountProvider')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAdminAdminsListQueryCountStaysBounded(int $rowCount): void
    {
        $this->loginAsAdmin();
        $this->bootRenderHarness();
        $this->seedExtraAdmins($rowCount);

        $_GET     = ['p' => 'admin', 'c' => 'admins', 'section' => 'admins'];
        $_SESSION = $_SESSION ?? [];

        $this->assertQueryCountAtMost(
            self::MAX_ADMINS_QUERIES,
            fn () => $this->renderPage('pages/admin.admins.php'),
            "admin admins list with {$rowCount} extra admins must not scale queries with row count",
        );
    }

    #[DataProvider('rowCountProvider')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAdminProtestsQueryCountStaysBounded(int $rowCount): void
    {
        $this->loginAsAdmin();
        $this->bootRenderHarness();
        $this->seedProtests($rowCount);

        $_GET     = ['p' => 'admin', 'c' => 'bans', 'section' => 'protests'];
        $_SESSION = $_SESSION ?? [];

        $this->assertQueryCountAtMost(
            self::MAX_PROTESTS_QUERIES,
            fn () => $this->renderPage('pages/admin.bans.php'),
            "admin protests with {$rowCount} rows must not scale queries with row count",
        );
    }

    #[DataProvider('rowCountProvider')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAdminSubmissionsQueryCountStaysBounded(int $rowCount): void
    {
        $this->loginAsAdmin();
        $this->bootRenderHarness();
        $this->seedSubmissions($rowCount);

        $_GET     = ['p' => 'admin', 'c' => 'bans', 'section' => 'submissions'];
        $_SESSION = $_SESSION ?? [];

        $this->assertQueryCountAtMost(
            self::MAX_SUBMISSIONS_QUERIES,
            fn () => $this->renderPage('pages/admin.bans.php'),
            "admin submissions with {$rowCount} rows must not scale queries with row count",
        );
    }

    #[DataProvider('rowCountProvider')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAdminGroupsListQueryCountStaysBounded(int $rowCount): void
    {
        $this->loginAsAdmin();
        $this->bootRenderHarness();
        $this->seedWebGroups($rowCount);

        $_GET     = ['p' => 'admin', 'c' => 'groups', 'section' => 'list'];
        $_SESSION = $_SESSION ?? [];

        $this->assertQueryCountAtMost(
            self::MAX_GROUPS_QUERIES,
            fn () => $this->renderPage('pages/admin.groups.php'),
            "admin groups list with {$rowCount} extra groups must not scale queries with row count",
        );
    }
}
