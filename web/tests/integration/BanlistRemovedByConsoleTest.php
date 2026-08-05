<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * Pins that the banlist / commslist RemovedBy name batch includes
 * administrator ID 0 (CONSOLE). PHP's empty(0) is true, so a
 * `!empty($row['RemovedBy'])` collector silently drops PruneBans /
 * CONSOLE lifts from the IN (...) lookup.
 */
final class BanlistRemovedByConsoleTest extends ApiTestCase
{
    private function bootCapturingTheme(): object
    {
        $theme = new class extends Smarty {
            /** @var array<string, mixed> */
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
        $GLOBALS['username'] = $GLOBALS['username'] ?? 'tester';

        return $theme;
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistResolvesConsoleRemovedByName(): void
    {
        $this->loginAsAdmin();
        $theme = $this->bootCapturingTheme();

        $now = time();
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (type, ip, authid, name, created, ends, length, reason, ureason, aid, RemovedBy, RemovedOn, RemoveType)
             VALUES (0, NULL, ?, ?, ?, ?, ?, ?, NULL, ?, 0, ?, ?)',
            DB_PREFIX,
        ))->execute([
            'STEAM_0:1:88001',
            'ConsoleRemovedByBan',
            $now - 7200,
            $now - 3600,
            3600,
            'expired for console removedby',
            Fixture::adminAid(),
            $now - 3600,
            \BanRemoval::Expired->value,
        ]);

        $_GET     = ['p' => 'banlist', 'state' => 'expired'];
        $_SESSION = $_SESSION ?? [];

        ob_start();
        try {
            require ROOT . 'pages/page.banlist.php';
        } finally {
            ob_end_clean();
        }

        $banList = $theme->captured['ban_list'] ?? null;
        $this->assertIsArray($banList);
        $this->assertNotSame([], $banList);

        $match = null;
        foreach ($banList as $ban) {
            if (($ban['steam'] ?? '') === 'STEAM_0:1:88001') {
                $match = $ban;
                break;
            }
        }

        $this->assertNotNull($match, 'expired ban with RemovedBy=0 must appear under ?state=expired');
        $this->assertSame('CONSOLE', $match['removedby'] ?? null);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCommslistResolvesConsoleRemovedByName(): void
    {
        $this->loginAsAdmin();
        $theme = $this->bootCapturingTheme();

        $now = time();
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_comms` (type, authid, name, created, ends, length, reason, ureason, aid, RemovedBy, RemovedOn, RemoveType)
             VALUES (1, ?, ?, ?, ?, ?, ?, NULL, ?, 0, ?, ?)',
            DB_PREFIX,
        ))->execute([
            'STEAM_0:1:88002',
            'ConsoleRemovedByComm',
            $now - 7200,
            $now - 3600,
            3600,
            'expired for console removedby',
            Fixture::adminAid(),
            $now - 3600,
            \BanRemoval::Expired->value,
        ]);

        $_GET     = ['p' => 'commslist'];
        $_SESSION = $_SESSION ?? [];
        unset($_SESSION['hideinactive']);

        ob_start();
        try {
            require ROOT . 'pages/page.commslist.php';
        } finally {
            ob_end_clean();
        }

        $banList = $theme->captured['ban_list'] ?? null;
        $this->assertIsArray($banList);
        $this->assertNotSame([], $banList);

        $match = null;
        foreach ($banList as $comm) {
            if (($comm['steam'] ?? '') === 'STEAM_0:1:88002') {
                $match = $comm;
                break;
            }
        }

        $this->assertNotNull($match, 'expired comm with RemovedBy=0 must appear under ?state=expired');
        $this->assertSame('CONSOLE', $match['removedby'] ?? null);
    }
}
