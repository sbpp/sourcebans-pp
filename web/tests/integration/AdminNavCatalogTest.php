<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Sbpp\View\AdminNavCatalog;

/**
 * Issue #1490: Pattern A section catalogs for the main-sidebar
 * accordion. Pins endpoint coverage, slug uniqueness, and the
 * feature-toggle gates on bans children.
 */
final class AdminNavCatalogTest extends ApiTestCase
{
    public function testKnownEndpointsExposeExpectedSlugs(): void
    {
        $this->assertSame(
            ['admins', 'add-admin', 'overrides'],
            array_column(AdminNavCatalog::sectionsFor('admins'), 'slug'),
        );
        $this->assertSame(
            ['list', 'add'],
            array_column(AdminNavCatalog::sectionsFor('servers'), 'slug'),
        );
        $this->assertSame(
            ['list', 'add'],
            array_column(AdminNavCatalog::sectionsFor('groups'), 'slug'),
        );
        $this->assertSame(
            ['settings', 'features', 'logs', 'themes'],
            array_column(AdminNavCatalog::sectionsFor('settings'), 'slug'),
        );
        $this->assertSame(
            ['list', 'add'],
            array_column(AdminNavCatalog::sectionsFor('mods'), 'slug'),
        );
        $this->assertSame([], AdminNavCatalog::sectionsFor('comms'));
        $this->assertSame([], AdminNavCatalog::sectionsFor('export'));
        $this->assertSame([], AdminNavCatalog::sectionsFor('unknown'));
    }

    public function testEverySectionCarriesUrlSlugAndPermission(): void
    {
        foreach (['admins', 'servers', 'groups', 'settings', 'mods', 'bans'] as $endpoint) {
            foreach (AdminNavCatalog::sectionsFor($endpoint) as $tab) {
                $this->assertNotSame('', $tab['slug']);
                $this->assertNotSame('', $tab['name']);
                $this->assertNotSame('', $tab['url']);
                $this->assertStringContainsString('section=' . $tab['slug'], $tab['url']);
                $this->assertIsInt($tab['permission']);
            }
        }
    }

    public function testBansCatalogHonoursFeatureToggles(): void
    {
        $this->setSetting('config.enableprotest', '0');
        $this->setSetting('config.enablesubmit', '0');
        $this->setSetting('config.enablegroupbanning', '0');
        \Sbpp\Config::init($GLOBALS['PDO']);

        $slugsOff = array_column(AdminNavCatalog::sectionsFor('bans'), 'slug');
        $this->assertSame(['add-ban', 'import'], $slugsOff);

        $this->setSetting('config.enableprotest', '1');
        $this->setSetting('config.enablesubmit', '1');
        $this->setSetting('config.enablegroupbanning', '1');
        \Sbpp\Config::init($GLOBALS['PDO']);

        $slugsOn = array_column(AdminNavCatalog::sectionsFor('bans'), 'slug');
        $this->assertSame(
            ['add-ban', 'protests', 'submissions', 'import', 'group-ban'],
            $slugsOn,
        );
    }

    public function testFilterForUserDropsInaccessibleChildren(): void
    {
        $this->loginAsAdmin();
        /** @var \CUserManager $owner */
        $owner = $GLOBALS['userbank'];
        $filtered = AdminNavCatalog::filterForUser(
            AdminNavCatalog::sectionsFor('servers'),
            $owner,
        );
        $this->assertSame(['list', 'add'], array_column($filtered, 'slug'));

        $aid = $this->createAdminWithFlags(ADMIN_LIST_SERVERS);
        $this->loginAs($aid);
        /** @var \CUserManager $listOnly */
        $listOnly = $GLOBALS['userbank'];
        $listFiltered = AdminNavCatalog::filterForUser(
            AdminNavCatalog::sectionsFor('servers'),
            $listOnly,
        );
        $this->assertSame(['list'], array_column($listFiltered, 'slug'));

        $aidEmpty = $this->createAdminWithFlags(ADMIN_LIST_ADMINS);
        $this->loginAs($aidEmpty);
        /** @var \CUserManager $noServerFlags */
        $noServerFlags = $GLOBALS['userbank'];
        $empty = AdminNavCatalog::filterForUser(
            AdminNavCatalog::sectionsFor('servers'),
            $noServerFlags,
        );
        $this->assertSame([], $empty);
    }

    private function setSetting(string $key, string $value): void
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'REPLACE INTO `%s_settings` (`setting`, `value`) VALUES (?, ?)',
            DB_PREFIX,
        ));
        $stmt->execute([$key, $value]);
        \Sbpp\Config::init($GLOBALS['PDO']);
    }

    private function createAdminWithFlags(int $mask): int
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, validate, extraflags, immunity)
             VALUES (?, ?, ?, -1, ?, NULL, ?, 50)',
            DB_PREFIX,
        ));
        $stmt->execute([
            'navcat-flagged-' . $mask,
            'STEAM_0:0:' . (4_000_000 + $mask),
            password_hash('x', PASSWORD_BCRYPT),
            'navcat-flagged@example.test',
            $mask,
        ]);
        return (int) $pdo->lastInsertId();
    }
}
