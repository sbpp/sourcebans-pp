<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Auth\UserManager;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Sbpp\Tests\QueryCountAssertions;

/**
 * Pins that `UserManager::GetAllAdmins()` issues a single query
 * regardless of admin count. It runs the same JOIN `GetUserArray()`
 * uses, minus the per-aid `WHERE` filter, and warms the in-memory
 * admin cache in one round trip.
 *
 * Both invocations run in the same process via
 * {@see QueryCountAssertions::assertQueryCountDelta()} — `UserManager`
 * declares no top-level symbols, so `#[RunInSeparateProcess]` is not
 * needed.
 */
final class UserManagerGetAllAdminsQueryCountTest extends ApiTestCase
{
    use QueryCountAssertions;

    private function seedAdmins(int $count): void
    {
        $pdo  = Fixture::rawPdo();
        $hash = password_hash('x', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, validate, extraflags, immunity)
             VALUES (?, ?, ?, -1, ?, NULL, ?, 0)',
            DB_PREFIX,
        ));

        for ($i = 0; $i < $count; $i++) {
            $stmt->execute([
                'GetAllAdminsFixture' . bin2hex(random_bytes(4)),
                'STEAM_0:1:' . (70000 + $i),
                $hash,
                'getalladmins' . $i . bin2hex(random_bytes(4)) . '@example.test',
                0,
            ]);
        }
    }

    public function testGetAllAdminsQueryCountDoesNotScaleWithAdminCount(): void
    {
        $this->seedAdmins(2);

        $manager = new UserManager(null);

        $result = $this->assertQueryCountDelta(
            0,
            function () use ($manager): void {
                $manager->GetAllAdmins();
            },
            function () use ($manager): void {
                $this->seedAdmins(15);
                $manager->GetAllAdmins();
            },
            'GetAllAdmins() must not scale its query count with admin count',
        );

        $this->assertSame(
            1,
            $result['baseline'],
            'GetAllAdmins() should issue exactly one query for the base admin set',
        );
        $this->assertSame(
            1,
            $result['grown'],
            'GetAllAdmins() should issue exactly one query even after seeding more admins',
        );
    }

    public function testGetAllAdminsReturnsSameShapeAsGetUserArray(): void
    {
        $manager  = new UserManager(null);
        $expected = $manager->GetUserArray(Fixture::adminAid());
        $this->assertIsArray($expected);

        $all = $manager->GetAllAdmins();

        $this->assertArrayHasKey(Fixture::adminAid(), $all);
        $this->assertSame($expected, $all[Fixture::adminAid()]);
    }
}
