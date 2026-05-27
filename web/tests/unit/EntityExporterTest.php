<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Unit;

use BanRemoval;
use BanType;
use PDO;
use PHPUnit\Framework\TestCase;
use Sbpp\Export\EntityExporter;
use Sbpp\Tests\Fixture;

/**
 * Per-entity wire-contract pins for the data export bundle.
 *
 * Each test exercises ONE structural promise the exporter makes —
 * the empty-entity → empty-output shape, the SteamID conversion
 * round-trip, the forbidden-column projection contract, the
 * `mute_kind` enum mapping for comms, the `state` derivation
 * matrix for bans, and the null-not-empty-string rule. Together
 * they form the structural backbone the downstream consumer can
 * key off — drift in any single one is a wire-format break.
 *
 * Scope split with sister test files:
 *   - {@see ManifestBuilderTest}: manifest / DTO / cap math.
 *   - {@see \Sbpp\Tests\Integration\ExportBundleWriterTest}:
 *     full-bundle end-to-end (zip parse + per-entry assertions).
 *   - This file: per-entity row-shape contracts.
 */
final class EntityExporterTest extends TestCase
{
    private EntityExporter $exporter;

    protected function setUp(): void
    {
        parent::setUp();
        Fixture::reset();
        $this->exporter = new EntityExporter(
            dbs:      $GLOBALS['PDO'],
            demosDir: SB_DEMOS,
        );
    }

    /**
     * On a freshly-installed DB the synthetic-fixture rows (admins,
     * settings, mods) exist but bans / comms / banlog / comments /
     * etc. are empty. The empty entities MUST yield zero lines —
     * no trailing newline, no `[]` placeholder, no header row.
     * JSONL's "line per object" contract degenerates to "zero
     * lines" for an empty entity.
     */
    public function testEmptyEntityYieldsZeroLines(): void
    {
        $lines = iterator_to_array($this->exporter->bans());
        $this->assertSame([], $lines, 'empty bans table must yield zero JSONL lines');

        $lines = iterator_to_array($this->exporter->comms());
        $this->assertSame([], $lines);

        $lines = iterator_to_array($this->exporter->comments());
        $this->assertSame([], $lines);

        $lines = iterator_to_array($this->exporter->banlog());
        $this->assertSame([], $lines);
    }

    /**
     * Forbidden columns must never appear in the entity output —
     * neither as JSON keys nor as substring matches against the
     * canonical column names. The fixture seeds the admin row with
     * a real bcrypt hash via `password_hash('admin', PASSWORD_BCRYPT)`,
     * so the grep would catch a regression that silently included
     * the hash by checking the BCRYPT prefix `$2y$` AND the column
     * key `"password"` — either match is a contract break.
     *
     * The settings entity is exercised separately because its
     * forbidden-set lives at the SQL WHERE level (filter by key
     * name, not by column name).
     */
    public function testForbiddenAdminColumnsNeverAppear(): void
    {
        // Seed a row whose forbidden columns carry distinctive values
        // we can grep for — the bcrypt prefix `$2y$` is distinctive,
        // the validate token is a hex hash we can pattern-match, and
        // the srv_password carries a hostile marker that proves the
        // plaintext SM admin server-login credential doesn't leak
        // (the panel stores `:prefix_admins.srv_password` cleartext —
        // see `web/api/handlers/account.php`'s stringwise comparison
        // and the table column type in `web/install/includes/sql/struc.sql`
        // both confirming `varchar(128) default NULL` with no hash).
        $pdo = Fixture::rawPdo();
        $pdo->exec(
            sprintf(
                "INSERT INTO `%s_admins` (user, authid, password, gid, email, validate, srv_password, extraflags, immunity, attempts, lockout_until) VALUES
                ('forbidcheck', 'STEAM_0:0:1', '\$2y\$12\$DUMMYHASHDUMMYHASHDUMMYHASHDUMMYHASHDUMMYHASHDUMMYHASHDU', -1, 'fc@example.test', '5ed533c0ffeec0deDUMMY99887766aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'SRVPLAIN_CLEARTEXT_LEAK_MARKER_DUMMY', 1, 1, 9, NOW() + INTERVAL 1 HOUR)",
                DB_PREFIX,
            )
        );

        $output = implode('', iterator_to_array($this->exporter->admins()));
        $this->assertNotSame('', $output, 'admins() must emit at least one line for the seeded row');

        // Per-column key absence (the JSON shape MUST NOT carry the key).
        foreach (EntityExporter::FORBIDDEN_ADMIN_COLUMNS as $col) {
            $this->assertStringNotContainsString(
                "\"{$col}\"",
                $output,
                "admins() emitted forbidden column key '{$col}' — projection regression",
            );
        }

        // Per-value distinctive marker absence.
        $this->assertStringNotContainsString('$2y$', $output, 'bcrypt hash leaked into admins output');
        $this->assertStringNotContainsString('5ed533c0ffeec0de', $output, 'validate token leaked into admins output');
        $this->assertStringNotContainsString(
            'SRVPLAIN_CLEARTEXT_LEAK_MARKER_DUMMY',
            $output,
            'plaintext srv_password leaked into admins output — the manifest pii_policy.password_hashes="never" '
            . 'attestation is broken if this fires (srv_password is a cleartext SM admin credential, '
            . 'structurally worse than the bcrypt password hash above)'
        );

        // Defence-in-depth: assert FORBIDDEN_ADMIN_COLUMNS itself
        // carries every credential-class field a future contributor
        // might be tempted to re-add to the SELECT. The list is the
        // load-bearing contract that keeps the manifest's
        // pii_policy.password_hashes="never" attestation truthful.
        $this->assertContains('password', EntityExporter::FORBIDDEN_ADMIN_COLUMNS);
        $this->assertContains('srv_password', EntityExporter::FORBIDDEN_ADMIN_COLUMNS);
        $this->assertContains('validate', EntityExporter::FORBIDDEN_ADMIN_COLUMNS);
    }

    /**
     * `:prefix_servers.rcon` is the other half of the forbidden
     * column contract — the RCON password is the keys-to-the-game-
     * server credential. Seed a row with a distinctive RCON value
     * and assert it doesn't leak.
     */
    public function testForbiddenServerColumnsNeverAppear(): void
    {
        $pdo = Fixture::rawPdo();
        $pdo->exec(
            sprintf(
                "INSERT INTO `%s_servers` (ip, port, rcon, modid, enabled) VALUES ('192.0.2.1', 27015, 'RCONFORBIDDEN_DISTINCTIVE_MARKER', 1, 1)",
                DB_PREFIX,
            )
        );

        $output = implode('', iterator_to_array($this->exporter->servers()));
        $this->assertNotSame('', $output);

        // Column key not projected.
        foreach (EntityExporter::FORBIDDEN_SERVER_COLUMNS as $col) {
            $this->assertStringNotContainsString(
                "\"{$col}\"",
                $output,
                "servers() emitted forbidden column key '{$col}'",
            );
        }
        $this->assertStringNotContainsString('RCONFORBIDDEN_DISTINCTIVE_MARKER', $output);
    }

    /**
     * `:prefix_settings` filters the forbidden keys at the SQL
     * WHERE level (defence-in-depth — a future post-fetch helper
     * regression can't add them back). Seed both keys with
     * distinctive values and assert neither appears in the output.
     */
    public function testForbiddenSettingKeysNeverAppear(): void
    {
        $pdo = Fixture::rawPdo();
        $pdo->exec(
            sprintf(
                "INSERT INTO `%s_settings` (setting, value) VALUES ('smtp.pass', 'SMTPPASSDISTINCTIVE_MARKER'), ('telemetry.instance_id', 'TELEMETRYIDDISTINCTIVE_MARKER') ON DUPLICATE KEY UPDATE value = VALUES(value)",
                DB_PREFIX,
            )
        );

        $output = implode('', iterator_to_array($this->exporter->settings()));
        $this->assertNotSame('', $output, 'settings() must emit some rows');
        $this->assertStringNotContainsString('smtp.pass', $output, 'forbidden setting key smtp.pass leaked');
        $this->assertStringNotContainsString('telemetry.instance_id', $output, 'forbidden setting key telemetry.instance_id leaked');
        $this->assertStringNotContainsString('SMTPPASSDISTINCTIVE_MARKER', $output, 'forbidden setting value smtp.pass leaked');
        $this->assertStringNotContainsString('TELEMETRYIDDISTINCTIVE_MARKER', $output, 'forbidden setting value telemetry.instance_id leaked');
    }

    /**
     * SteamID conversion: every authid column round-trips through
     * `SteamID::toSteam64()` → decimal-string Steam64. The output
     * MUST be a quoted JSON string, never a JSON number — Steam64
     * IDs are 17 digits and overflow safe JS `Number` precision
     * (2^53 - 1 = 9007199254740991 ≈ 16 digits). Anything emitting
     * Steam64 as a JSON number silently corrupts downstream
     * consumers parsing with default JS / JSON-decoders. Pinned
     * also at the ExportBundleWriterTest level for belt-and-braces.
     */
    public function testSteamIdAlwaysQuotedDecimalString(): void
    {
        // Seed a ban with a known Steam2 authid; the Steam64
        // equivalent of STEAM_0:0:1 is 76561197960265728 + (2*1) + 0
        // = 76561197960265730 per the standard Steam2→Steam64
        // formula (`base = 76561197960265728; sid64 = base + 2*Z + Y`).
        $aid = Fixture::adminAid();
        $pdo = Fixture::rawPdo();
        $pdo->exec(
            sprintf(
                "INSERT INTO `%s_bans` (ip, authid, name, created, ends, length, reason, aid, adminIp, sid, country, type) VALUES
                ('', 'STEAM_0:0:1', 'fixture-target', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + 3600, 3600, 'test', %d, '127.0.0.1', 0, '', 0)",
                DB_PREFIX,
                $aid,
            )
        );

        $output = implode('', iterator_to_array($this->exporter->bans()));
        $this->assertNotSame('', $output);

        $this->assertStringContainsString(
            '"authid":"76561197960265730"',
            $output,
            'authid must be quoted decimal Steam64 string',
        );
        $this->assertStringContainsString(
            '"authid_steam2":"STEAM_0:0:1"',
            $output,
            'authid_steam2 preserves the source Steam2 form',
        );

        // Authid-as-number sanity gate — the value lives at the
        // JSON-key position, never as a bare numeric literal.
        $this->assertDoesNotMatchRegularExpression(
            '/"authid":\s*\d+(?!")/',
            $output,
            'authid must never appear as a JSON number',
        );
    }

    /**
     * Malformed authid rows MUST yield `null` for both Steam ID
     * fields instead of throwing — the export is a recovery /
     * migration surface, and legacy garbage rows from pre-#1108 /
     * #765 truncation shouldn't break it.
     */
    public function testMalformedAuthidYieldsNull(): void
    {
        $aid = Fixture::adminAid();
        $pdo = Fixture::rawPdo();
        $pdo->exec(
            sprintf(
                "INSERT INTO `%s_bans` (ip, authid, name, created, ends, length, reason, aid, adminIp, sid, country, type) VALUES
                ('', 'GARBAGE_AUTHID', 'fixture-garbage', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + 3600, 3600, 'test', %d, '127.0.0.1', 0, '', 0),
                ('', '',               'fixture-empty',   UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + 3600, 3600, 'test', %d, '127.0.0.1', 0, '', 0)",
                DB_PREFIX,
                $aid,
                $aid,
            )
        );

        $lines = iterator_to_array($this->exporter->bans());
        $this->assertNotEmpty($lines, 'seeded malformed rows must still ship');

        foreach ($lines as $line) {
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $this->assertArrayHasKey('authid', $row);
            $this->assertArrayHasKey('authid_steam2', $row);
            if ($row['name'] === 'fixture-garbage' || $row['name'] === 'fixture-empty') {
                $this->assertNull($row['authid'], 'malformed authid must yield null');
                $this->assertNull($row['authid_steam2'], 'malformed authid must yield null in steam2 slot too');
            }
        }
    }

    /**
     * `comms.mute_kind` is the operator-readable derivation of the
     * raw `:prefix_comms.type` int. The matrix is documented in
     * EntityExporter::commsMuteKind — `1 → mute`, `2 → gag`,
     * `3 → silence`, anything else → `unknown`. Seed one of each
     * and assert.
     */
    public function testCommsMuteKindDerivation(): void
    {
        $aid = Fixture::adminAid();
        $pdo = Fixture::rawPdo();
        $pdo->exec(
            sprintf(
                "INSERT INTO `%s_comms` (authid, name, created, ends, length, reason, aid, adminIp, sid, type) VALUES
                ('STEAM_0:0:10', 'mute_target',     UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + 60, 60, 't', %d, '127.0.0.1', 0, 1),
                ('STEAM_0:0:11', 'gag_target',      UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + 60, 60, 't', %d, '127.0.0.1', 0, 2),
                ('STEAM_0:0:12', 'silence_target',  UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + 60, 60, 't', %d, '127.0.0.1', 0, 3),
                ('STEAM_0:0:13', 'unknown_target',  UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + 60, 60, 't', %d, '127.0.0.1', 0, 9)",
                DB_PREFIX,
                $aid,
                $aid,
                $aid,
                $aid,
            )
        );

        $byName = [];
        foreach ($this->exporter->comms() as $line) {
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $byName[$row['name']] = $row;
        }

        $this->assertSame('mute',    $byName['mute_target']['mute_kind']);
        $this->assertSame('gag',     $byName['gag_target']['mute_kind']);
        $this->assertSame('silence', $byName['silence_target']['mute_kind']);
        $this->assertSame('unknown', $byName['unknown_target']['mute_kind']);

        // Raw int preserved verbatim — a downstream consumer that
        // wants to apply its own classifier MUST be able to get to
        // the source value.
        $this->assertSame(1, $byName['mute_target']['type']);
        $this->assertSame(1, $byName['mute_target']['type_raw']);
        $this->assertSame(9, $byName['unknown_target']['type_raw']);
    }

    /**
     * `bans.state` matrix matches `page.banlist.php`'s classifier:
     *
     *   RemoveType=D (Deleted)  → 'unbanned'
     *   RemoveType=U (Unbanned) → 'unbanned'
     *   RemoveType=E (Expired)  → 'expired'
     *   RemoveType NULL + RemovedBy>0 (#1352 pre-2 admin-lift) → 'unbanned'
     *   length=0                → 'permanent'
     *   ends < now              → 'expired'
     *   default                 → 'active'
     */
    public function testBansStateDerivationMatrix(): void
    {
        $aid    = Fixture::adminAid();
        $now    = time();
        $expIn  = $now + 3600;     // future expiry
        $expOut = $now - 3600;     // past expiry
        $pdo    = Fixture::rawPdo();

        $pdo->exec(
            sprintf(
                "INSERT INTO `%s_bans`
                  (ip, authid, name, created, ends, length, reason, aid, adminIp, sid, country, RemoveType, RemovedBy, type) VALUES
                ('', 'STEAM_0:0:101', 'permanent_row',     %d, %d, 0,    't', %d, '127.0.0.1', 0, '', NULL, NULL, 0),
                ('', 'STEAM_0:0:102', 'active_row',        %d, %d, 3600, 't', %d, '127.0.0.1', 0, '', NULL, NULL, 0),
                ('', 'STEAM_0:0:103', 'expired_row',       %d, %d, 3600, 't', %d, '127.0.0.1', 0, '', NULL, NULL, 0),
                ('', 'STEAM_0:0:104', 'deleted_row',       %d, %d, 3600, 't', %d, '127.0.0.1', 0, '', 'D',  %d,   0),
                ('', 'STEAM_0:0:105', 'unbanned_u_row',    %d, %d, 3600, 't', %d, '127.0.0.1', 0, '', 'U',  %d,   0),
                ('', 'STEAM_0:0:106', 'expired_e_row',     %d, %d, 3600, 't', %d, '127.0.0.1', 0, '', 'E',  NULL, 0),
                ('', 'STEAM_0:0:107', 'pre2_admin_lift',   %d, %d, 3600, 't', %d, '127.0.0.1', 0, '', NULL, %d,   0)",
                DB_PREFIX,
                $now, $now, $aid,
                $now, $expIn, $aid,
                $now, $expOut, $aid,
                $now, $expIn, $aid, $aid,
                $now, $expIn, $aid, $aid,
                $now, $expOut, $aid,
                $now, $expIn, $aid, $aid,
            )
        );

        $byName = [];
        foreach ($this->exporter->bans() as $line) {
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $byName[$row['name']] = $row;
        }

        $this->assertSame('permanent', $byName['permanent_row']['state']);
        $this->assertSame('active',    $byName['active_row']['state']);
        $this->assertSame('expired',   $byName['expired_row']['state']);
        $this->assertSame('unbanned',  $byName['deleted_row']['state']);
        $this->assertSame('unbanned',  $byName['unbanned_u_row']['state']);
        $this->assertSame('expired',   $byName['expired_e_row']['state']);
        $this->assertSame('unbanned',  $byName['pre2_admin_lift']['state']);
    }

    /**
     * Empty / NULL string columns must surface as JSON `null`, never
     * as `""`. Same contract as the helper docblock: "absent" and
     * "empty string" must round-trip indistinguishably. Test bans
     * because it carries the canonical empty-string slots (ip,
     * reason on a bare ban, country).
     */
    public function testNullForAbsentNeverEmptyString(): void
    {
        $aid = Fixture::adminAid();
        $pdo = Fixture::rawPdo();
        $pdo->exec(
            sprintf(
                "INSERT INTO `%s_bans` (ip, authid, name, created, ends, length, reason, aid, adminIp, sid, country, type) VALUES
                ('', 'STEAM_0:0:200', '', UNIX_TIMESTAMP(), 0, 0, '', %d, '127.0.0.1', 0, '', 0)",
                DB_PREFIX,
                $aid,
            )
        );

        $line = null;
        foreach ($this->exporter->bans() as $candidate) {
            $row = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
            if ($row['authid_steam2'] === 'STEAM_0:0:200') {
                $line = $row;
                break;
            }
        }
        $this->assertNotNull($line, 'fixture row must be present');

        $this->assertNull($line['ip'],      'empty-string ip must surface as null');
        $this->assertNull($line['name'],    'empty-string name must surface as null');
        $this->assertNull($line['reason'],  'empty-string reason must surface as null');
        $this->assertNull($line['country'], 'empty-string country must surface as null');

        // Belt + braces: the JSON literal must not contain `""`-only values
        // for those keys.
        $literal = json_encode($line);
        $this->assertIsString($literal);
        $this->assertStringNotContainsString('"ip":""',      $literal);
        $this->assertStringNotContainsString('"reason":""',  $literal);
        $this->assertStringNotContainsString('"country":""', $literal);
    }

    /**
     * `log.level` derivation: `LogType::Message` → `message`,
     * `LogType::Warning` → `warning`, `LogType::Error` → `error`,
     * unknown letter → `unknown`. The raw letter is preserved as
     * `type` so a downstream consumer doesn't lose the source data.
     */
    public function testLogLevelDerivation(): void
    {
        $aid = Fixture::adminAid();
        $pdo = Fixture::rawPdo();
        $pdo->exec(
            sprintf(
                "INSERT INTO `%s_log` (type, title, message, function, query, aid, host, created) VALUES
                ('m', 'msgrow', 'msgmsg', '', '', %d, '127.0.0.1', UNIX_TIMESTAMP()),
                ('w', 'warnrow', 'warnmsg', '', '', %d, '127.0.0.1', UNIX_TIMESTAMP()),
                ('e', 'errrow', 'errmsg', '', '', %d, '127.0.0.1', UNIX_TIMESTAMP())",
                DB_PREFIX,
                $aid,
                $aid,
                $aid,
            )
        );

        $byTitle = [];
        foreach ($this->exporter->log() as $line) {
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            $byTitle[$row['title']] = $row;
        }

        $this->assertSame('message', $byTitle['msgrow']['level']);
        $this->assertSame('warning', $byTitle['warnrow']['level']);
        $this->assertSame('error',   $byTitle['errrow']['level']);

        // Raw letter preserved.
        $this->assertSame('m', $byTitle['msgrow']['type']);
        $this->assertSame('w', $byTitle['warnrow']['type']);
        $this->assertSame('e', $byTitle['errrow']['type']);
    }

    /**
     * Timestamps are always unix-seconds ints — no ISO strings, no
     * millisecond values. Test the canonical path on bans.created.
     */
    public function testTimestampsAreUnixSecondsInteger(): void
    {
        $aid = Fixture::adminAid();
        $now = 1_700_000_000;
        $pdo = Fixture::rawPdo();
        $pdo->exec(
            sprintf(
                "INSERT INTO `%s_bans` (ip, authid, name, created, ends, length, reason, aid, adminIp, sid, country, type) VALUES
                ('', 'STEAM_0:0:300', 'ts_target', %d, 0, 0, 't', %d, '127.0.0.1', 0, '', 0)",
                DB_PREFIX,
                $now,
                $aid,
            )
        );

        foreach ($this->exporter->bans() as $line) {
            $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if ($row['name'] === 'ts_target') {
                $this->assertIsInt($row['created']);
                $this->assertSame($now, $row['created']);
                $this->assertIsInt($row['ends']);
                $this->assertIsInt($row['length']);
                return;
            }
        }
        $this->fail('seeded ts_target row never surfaced');
    }
}
