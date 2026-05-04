<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

final class ProtestsTest extends ApiTestCase
{
    /**
     * Insert a single protest. The protest table has a NOT NULL `bid`
     * column referencing :prefix_bans, so we leave bid=0 (the CONSOLE
     * placeholder) which is also what the legacy form code uses for
     * orphan protests.
     */
    private function seedProtest(string $archiv = '0'): int
    {
        $pdo = Fixture::rawPdo();
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_protests`
              (`bid`, `email`, `reason`, `archiv`, `datesubmitted`, `pip`)
             VALUES (0, ?, ?, ?, ?, "127.0.0.1")',
            DB_PREFIX
        ))->execute(['protest@example.test', 'wrong ban', $archiv, time()]);
        return (int)$pdo->lastInsertId();
    }

    public function testRemoveRejectsAnonymous(): void
    {
        $env = $this->api('protests.remove', ['pid' => 1, 'archiv' => '0']);
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('protests/remove_forbidden', $env);
    }

    public function testDeleteProtest(): void
    {
        $this->loginAsAdmin();
        $pid = $this->seedProtest();

        $env = $this->api('protests.remove', ['pid' => $pid, 'archiv' => '0']);
        $this->assertTrue($env['ok']);
        $this->assertNull($this->row('protests', ['pid' => $pid]));
        $this->assertSnapshot('protests/delete_success', $env, ['data.remove']);
    }

    public function testArchiveProtest(): void
    {
        $this->loginAsAdmin();
        $pid = $this->seedProtest();

        $env = $this->api('protests.remove', ['pid' => $pid, 'archiv' => '1']);
        $this->assertTrue($env['ok']);

        $row = $this->row('protests', ['pid' => $pid]);
        $this->assertSame(1, (int)$row['archiv']);
        $this->assertSame(Fixture::adminAid(), (int)$row['archivedby']);
        $this->assertSnapshot('protests/archive_success', $env, ['data.remove']);
    }

    public function testRestoreProtest(): void
    {
        $this->loginAsAdmin();
        $pid = $this->seedProtest(archiv: '1'); // already archived

        $env = $this->api('protests.remove', ['pid' => $pid, 'archiv' => '2']);
        $this->assertTrue($env['ok']);
        $row = $this->row('protests', ['pid' => $pid]);
        $this->assertSame(0, (int)$row['archiv']);
        $this->assertNull($row['archivedby']);
        $this->assertSnapshot('protests/restore_success', $env, ['data.remove']);
    }

    public function testUnknownArchivValueReturnsBadRequest(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('protests.remove', ['pid' => 1, 'archiv' => 'nope']);
        $this->assertEnvelopeError($env, 'bad_request');
        $this->assertSnapshot('protests/bad_request', $env);
    }
}
