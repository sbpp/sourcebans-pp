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
            'INSERT INTO `%ssubmissions` (`name`, `SteamId`, `email`, `reason`, `archiv`) VALUES (?, ?, ?, ?, "0")',
            DB_PREFIX
        ))->execute(['Bob', 'STEAM_0:1:1', 'b@b', 'cheating']);
        $sid = (int)$pdo->lastInsertId();

        $env = $this->api('submissions.remove', ['sid' => $sid, 'archiv' => '1']);
        $this->assertTrue($env['ok']);

        $row = $this->row('submissions', ['subid' => $sid]);
        $this->assertSame('1', $row['archiv']);
        $this->assertSame((string)Fixture::adminAid(), $row['archivedby']);
    }
}
