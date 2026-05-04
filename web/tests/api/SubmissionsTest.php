<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

final class SubmissionsTest extends ApiTestCase
{
    /** Insert a single submission and return its sid. */
    private function seedSubmission(string $name = 'Bob', string $steamId = 'STEAM_0:1:1', string $archiv = '0'): int
    {
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_submissions`
              (`name`, `SteamId`, `email`, `reason`, `archiv`, `submitted`, `ModID`, `ip`, `server`)
             VALUES (?, ?, ?, ?, ?, ?, 0, "127.0.0.1", 0)',
            DB_PREFIX
        ))->execute([$name, $steamId, "$name@example.test", 'cheating', $archiv, time()]);
        return (int)$pdo->lastInsertId();
    }

    public function testRemoveRequiresPermission(): void
    {
        $env = $this->api('submissions.remove', ['sid' => 1, 'archiv' => '0']);
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('submissions/remove_forbidden', $env);
    }

    public function testArchiveSubmission(): void
    {
        $this->loginAsAdmin();
        $sid = $this->seedSubmission();

        $env = $this->api('submissions.remove', ['sid' => $sid, 'archiv' => '1']);
        $this->assertTrue($env['ok']);

        $row = $this->row('submissions', ['subid' => $sid]);
        $this->assertSame(1, (int)$row['archiv']);
        $this->assertSame(Fixture::adminAid(), (int)$row['archivedby']);
        $this->assertSnapshot('submissions/archive_success', $env, [
            'data.remove', // contains the dynamic sid
        ]);
    }

    public function testDeleteSubmission(): void
    {
        $this->loginAsAdmin();
        $sid = $this->seedSubmission();

        $env = $this->api('submissions.remove', ['sid' => $sid, 'archiv' => '0']);
        $this->assertTrue($env['ok']);
        $this->assertNull($this->row('submissions', ['subid' => $sid]));
        $this->assertSnapshot('submissions/delete_success', $env, ['data.remove']);
    }

    public function testRestoreSubmission(): void
    {
        $this->loginAsAdmin();
        $sid = $this->seedSubmission(archiv: '1'); // already archived

        $env = $this->api('submissions.remove', ['sid' => $sid, 'archiv' => '2']);
        $this->assertTrue($env['ok']);
        $row = $this->row('submissions', ['subid' => $sid]);
        $this->assertSame(0, (int)$row['archiv']);
        $this->assertNull($row['archivedby']);
        $this->assertSnapshot('submissions/restore_success', $env, ['data.remove']);
    }

    public function testUnknownArchivValueReturnsBadRequest(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('submissions.remove', ['sid' => 1, 'archiv' => 'oops']);
        $this->assertEnvelopeError($env, 'bad_request');
        $this->assertSnapshot('submissions/bad_request', $env);
    }
}
