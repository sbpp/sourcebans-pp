<?php

namespace Sbpp\Tests\Api;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

final class ModsTest extends ApiTestCase
{
    public function testAddCreatesRow(): void
    {
        $this->loginAsAdmin();
        $env = $this->api('mods.add', [
            'name'           => 'Test Mod',
            'folder'         => 'tmod',
            'icon'           => 'icon.png',
            'steam_universe' => 0,
            'enabled'        => true,
        ]);
        $this->assertTrue($env['ok']);
        $this->assertSame('Mod Added', $env['data']['message']['title']);
        $this->assertSnapshot('mods/add_success', $env);

        $row = $this->row('mods', ['modfolder' => 'tmod']);
        $this->assertNotNull($row);
        $this->assertSame('Test Mod', $row['name']);
    }

    public function testAddRejectsAnonymous(): void
    {
        $env = $this->api('mods.add', ['name' => 'x', 'folder' => 'y']);
        $this->assertEnvelopeError($env, 'forbidden');
        $this->assertSnapshot('mods/add_forbidden', $env);
    }

    public function testAddRefusesDuplicateFolder(): void
    {
        $this->loginAsAdmin();
        $this->api('mods.add', ['name' => 'A', 'folder' => 'a']);
        $env = $this->api('mods.add', ['name' => 'B', 'folder' => 'a']);
        $this->assertEnvelopeError($env, 'mod_exists');
        $this->assertSnapshot('mods/add_duplicate', $env);
    }

    public function testRemoveDeletesRow(): void
    {
        $this->loginAsAdmin();
        $this->api('mods.add', ['name' => 'Doomed', 'folder' => 'doomed']);
        $row = $this->row('mods', ['name' => 'Doomed']);

        $env = $this->api('mods.remove', ['mid' => $row['mid']]);
        $this->assertTrue($env['ok']);
        $this->assertSame("mid_{$row['mid']}", $env['data']['remove']);
        $this->assertNull($this->row('mods', ['mid' => $row['mid']]));
        // The mid in the response depends on the auto-increment counter,
        // which jumps over the 23 rows seeded by data.sql (mid 0–22).
        // Redact it so the snapshot only locks the message envelope shape.
        $this->assertSnapshot('mods/remove_success', $env, ['data.remove']);
    }

    public function testRemoveRejectsAnonymous(): void
    {
        $env = $this->api('mods.remove', ['mid' => 1]);
        $this->assertEnvelopeError($env, 'forbidden');
    }

    /**
     * #1397 — when the admin supplied a reason via the confirm-dialog
     * textarea, the audit-log body must include the `Reason: …` suffix
     * so the moderation trail captures why the mod was deleted.
     * Mirrors the canonical pattern in {@see AdminsTest::testRemoveAppendsReasonToAuditLog}.
     */
    public function testRemoveAppendsReasonToAuditLog(): void
    {
        $this->loginAsAdmin();
        $this->api('mods.add', ['name' => 'ReasonTarget', 'folder' => 'reasontarget']);
        $row = $this->row('mods', ['name' => 'ReasonTarget']);

        $env = $this->api('mods.remove', ['mid' => $row['mid'], 'ureason' => 'retired game']);
        $this->assertTrue($env['ok'], json_encode($env));
        $this->assertNull($this->row('mods', ['mid' => $row['mid']]));

        $message = $this->latestLogMessage('MOD Deleted');
        $this->assertSame(
            'MOD (ReasonTarget) has been deleted. Reason: retired game',
            $message
        );
    }

    /**
     * #1397 — empty / whitespace `ureason` falls through to the bare audit
     * body. Keeps the audit log readable on the no-JS path (where the
     * dialog wouldn't run) and on the dispatcher-missing fallback (where
     * the JS would skip the param entirely). Mirrors the canonical
     * pattern in {@see AdminsTest::testRemoveOmitsReasonSuffixWhenEmpty}.
     */
    public function testRemoveOmitsReasonSuffixWhenEmpty(): void
    {
        $this->loginAsAdmin();
        $this->api('mods.add', ['name' => 'EmptyReason', 'folder' => 'emptyreason']);
        $row = $this->row('mods', ['name' => 'EmptyReason']);

        // Whitespace-only reason: the trim path strips it back to '' and
        // the audit suffix is omitted.
        $env = $this->api('mods.remove', ['mid' => $row['mid'], 'ureason' => "   \n\t   "]);
        $this->assertTrue($env['ok'], json_encode($env));

        $message = $this->latestLogMessage('MOD Deleted');
        $this->assertSame(
            'MOD (EmptyReason) has been deleted.',
            $message
        );
    }

    /**
     * #1397 — omitted `ureason` (no key in the params bag) is treated the
     * same as empty. Pins the back-compat for the snapshot regression
     * (`remove_success.json`) and the `data-fallback-href` no-JS path
     * which doesn't surface the reason field at all.
     */
    public function testRemoveAcceptsMissingReasonParam(): void
    {
        $this->loginAsAdmin();
        $this->api('mods.add', ['name' => 'NoParam', 'folder' => 'noparam']);
        $row = $this->row('mods', ['name' => 'NoParam']);

        $env = $this->api('mods.remove', ['mid' => $row['mid']]);
        $this->assertTrue($env['ok'], json_encode($env));

        $message = $this->latestLogMessage('MOD Deleted');
        $this->assertSame(
            'MOD (NoParam) has been deleted.',
            $message
        );
    }

    /**
     * Pull the most recent audit-log message for a given title, ordered
     * by lid (auto-increment, monotonic). Mirrors the helper in
     * {@see AdminsTest::latestLogMessage} — the {@see ApiTestCase::row()}
     * helper has no ORDER BY surface so the lookup is inlined.
     */
    private function latestLogMessage(string $title): string
    {
        $pdo  = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'SELECT message FROM `%s_log` WHERE `title` = ? ORDER BY lid DESC LIMIT 1',
            DB_PREFIX
        ));
        $stmt->execute([$title]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertIsArray($row, 'Expected an audit-log entry titled "' . $title . '"');
        return (string) $row['message'];
    }
}
