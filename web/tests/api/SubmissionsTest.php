<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

final class SubmissionsTest extends ApiTestCase
{
    public function testRemoveRequiresPermission(): void
    {
        $env = $this->api('submissions.remove', ['sid' => 1, 'archiv' => '0']);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    public function testArchiveSubmission(): void
    {
        $this->loginAsAdmin();
        // Insert a submission directly via PDO
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_submissions`
              (`name`, `SteamId`, `email`, `reason`, `archiv`, `submitted`, `ModID`, `ip`, `server`)
             VALUES (?, ?, ?, ?, "0", ?, 0, "127.0.0.1", 0)',
            DB_PREFIX
        ))->execute(['Bob', 'STEAM_0:1:1', 'b@b', 'cheating', time()]);
        $sid = (int)$pdo->lastInsertId();

        $env = $this->api('submissions.remove', ['sid' => $sid, 'archiv' => '1']);
        $this->assertTrue($env['ok']);

        $row = $this->row('submissions', ['subid' => $sid]);
        $this->assertSame(1, (int)$row['archiv']);
        $this->assertSame(Fixture::adminAid(), (int)$row['archivedby']);
    }
}
