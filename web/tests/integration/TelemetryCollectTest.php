<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Telemetry\Telemetry;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * #1126 — `Telemetry::collect()` against the Fixture-seeded DB.
 *
 * Two contracts gate the payload:
 *
 *   1. **Shape & values**. Every leaf in the schema is populated;
 *      every count is the integer the SQL says it should be against
 *      the synthetic rows this test seeds.
 *   2. **No PII**. The serialised JSON form of the payload contains
 *      none of the seeded admin names, SteamIDs, hostnames, or ban
 *      reasons. This is the regression guard against the issue's
 *      "anonymous by design, not anonymous-if-you-trust-us" rule.
 */
final class TelemetryCollectTest extends ApiTestCase
{
    /** Seeded SteamIDs we then probe for in the serialised payload. */
    private const PII_STEAMIDS = [
        'STEAM_0:1:777',
        'STEAM_0:1:888',
        'STEAM_0:1:999',
    ];

    /** Seeded ban reasons. */
    private const PII_BAN_REASONS = [
        'aimbot-canary-A1B2',
        'wallhack-canary-C3D4',
    ];

    /** Seeded server hostname / mute reason / admin name. */
    private const PII_OTHER = [
        'srv-canary-host.example.test',
        'mute-canary-reason-Q1Q2',
        'PII-CANARY-ADMIN',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Telemetry::resetInstanceIdMemoForTests();
    }

    public function testCollectShapeMatchesSchemaTreeAndCountsAreCorrect(): void
    {
        $this->seedSyntheticUniverse();

        $payload = Telemetry::collect();

        $this->assertSame(1, $payload['schema']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $payload['instance_id']);

        $panel = $payload['panel'];
        $this->assertIsArray($panel);
        $this->assertArrayHasKey('version', $panel);
        $this->assertArrayHasKey('git', $panel);
        $this->assertArrayHasKey('dev', $panel);
        $this->assertArrayHasKey('theme', $panel);
        $this->assertSame('default', $panel['theme'], 'shipped default theme is reported as "default", not "custom"');
        $this->assertIsBool($panel['dev']);
        // sbpp/cf-analytics#6. Region depends on the runner's
        // timezone, so pin the domain only.
        $this->assertContains($panel['region'], ['af', 'as', 'eu', 'na', 'oc', 'sa', null]);
        $this->assertFalse($panel['exported_30d'], 'no Data Export rows in the fixture log');

        $env = $payload['env'];
        $this->assertIsArray($env);
        $this->assertMatchesRegularExpression('/^\d+\.\d+$/', (string) $env['php']);
        $this->assertContains($env['db_engine'], ['mariadb', 'mysql']);
        $this->assertMatchesRegularExpression('/^\d+\.\d+$/', (string) $env['db_version']);
        $this->assertContains(
            $env['web_server'],
            ['apache', 'nginx', 'litespeed', 'iis', 'caddy', 'other']
        );
        $this->assertContains(
            $env['os_family'],
            ['linux', 'windows', 'mac', 'bsd', 'other']
        );
        // sbpp/cf-analytics#3 host fingerprint. Values depend on the
        // runner's PHP build, so pin types (the analytics side bit-packs
        // the booleans and treats the ints as doubles).
        $this->assertSame(PHP_SAPI, $env['sapi']);
        $this->assertTrue(
            $env['memory_limit_mb'] === null || (is_int($env['memory_limit_mb']) && $env['memory_limit_mb'] >= 0),
            'env.memory_limit_mb must be a non-negative int or null'
        );
        $this->assertIsInt($env['max_execution_time']);
        $this->assertIsInt($env['disable_functions_count']);
        foreach ([
            'zts', 'php_64bit', 'open_basedir_set', 'allow_url_fopen',
            'opcache_loaded', 'suhosin_loaded', 'posix_available',
            'host_panel_cpanel', 'host_panel_plesk', 'host_panel_directadmin',
            'docroot_user_home', 'sapi_per_user',
        ] as $flag) {
            $this->assertArrayHasKey($flag, $env, "env.$flag missing from payload");
            $this->assertIsBool($env[$flag], "env.$flag must be a bool");
        }

        $scale = $payload['scale'];
        $this->assertIsArray($scale);
        $this->assertGreaterThanOrEqual(2, $scale['admins'], 'CONSOLE + the seeded admin and the canary');
        $this->assertSame(2, $scale['servers_enabled'], 'two enabled, one disabled');
        $this->assertSame(2, $scale['bans_active'], 'two active bans seeded');
        $this->assertSame(4, $scale['bans_total'], 'four bans total (active + expired + removed)');
        $this->assertSame(1, $scale['comms_active']);
        $this->assertSame(2, $scale['comms_total']);
        $this->assertSame(2, $scale['submissions_30d'], 'recent submissions inside the 30-day window');
        $this->assertSame(1, $scale['protests_30d']);
        $this->assertSame(4, $scale['bans_30d'], 'every seeded ban is inside the 30-day window');
        $this->assertIsInt($scale['admins_active_30d']);
        $this->assertSame(0, $scale['ban_history_months'], 'oldest seeded ban is ~2 days old');
        $this->assertFalse($scale['web_bans_majority_30d'], 'every seeded ban came from sid 1');
        $this->assertSame(
            ['tf' => true, 'cstrike' => false, 'csgo' => false, 'garrysmod' => false, 'left4dead2' => false, 'other' => false],
            $scale['games'],
            'only TF2 (modid 12) servers are enabled'
        );
        $this->assertSame(
            ['web_groups' => false, 'server_groups' => false, 'overrides' => false, 'immunity' => true],
            $scale['perms'],
            'fixture admin carries immunity 100; no groups or overrides seeded'
        );

        $features = $payload['features'];
        $this->assertIsArray($features);
        foreach ([
            'submit', 'protest', 'comms', 'kickit', 'exportpublic',
            'publiccomments', 'steamlogin', 'normallogin', 'groupbanning',
            'friendsbanning', 'adminrehashing', 'smtp_configured',
            'steam_api_key_set', 'geoip_present',
        ] as $feature) {
            $this->assertArrayHasKey($feature, $features, "features.$feature missing from payload");
            $this->assertIsBool($features[$feature], "features.$feature must be a bool");
        }
    }

    public function testCollectedPayloadContainsNoSeededPii(): void
    {
        $this->seedSyntheticUniverse();

        $payload = Telemetry::collect();
        $serialised = (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        foreach (self::PII_STEAMIDS as $steam) {
            $this->assertStringNotContainsString(
                $steam,
                $serialised,
                "Telemetry payload leaked seeded SteamID '$steam'"
            );
        }
        foreach (self::PII_BAN_REASONS as $reason) {
            $this->assertStringNotContainsString(
                $reason,
                $serialised,
                "Telemetry payload leaked seeded ban reason '$reason'"
            );
        }
        foreach (self::PII_OTHER as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $serialised,
                "Telemetry payload leaked seeded PII string '$needle'"
            );
        }
    }

    /**
     * sbpp/cf-analytics#6 slots against a panel shaped differently
     * from the main universe: mixed games, mostly web bans, a long
     * ban history, groups + overrides, and a recent export.
     */
    public function testScaleActivityGameMixPermissionShapeAndExport(): void
    {
        $pdo = Fixture::rawPdo();
        $now = time();

        $pdo->prepare(sprintf('UPDATE `%s_admins` SET `lastvisit` = ? WHERE `aid` = ?', DB_PREFIX))
            ->execute([$now - 100, Fixture::adminAid()]);
        $hash = password_hash('whatever', PASSWORD_BCRYPT);
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (`user`, `authid`, `password`, `gid`, `email`, `validate`, `extraflags`, `immunity`, `lastvisit`)
             VALUES (?, ?, ?, -1, ?, NULL, 0, 0, ?)',
            DB_PREFIX
        ))->execute(['stale-admin', 'STEAM_0:1:444', $hash, 'stale@example.test', $now - 40 * 86400]);

        // CS:GO (21) + HL2DM (1, folds into "other") enabled; L4D2 (16) disabled.
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_servers` (`ip`, `port`, `rcon`, `modid`, `enabled`) VALUES (?, ?, ?, ?, ?)',
            DB_PREFIX
        ));
        $stmt->execute(['10.0.1.1', 27015, '', 21, 1]);
        $stmt->execute(['10.0.1.2', 27015, '', 1, 1]);
        $stmt->execute(['10.0.1.3', 27015, '', 16, 0]);

        // Last 30 days: 3 web bans (sid 0) + 1 server ban. Plus one
        // 400-day-old ban (13 calendar months whatever the date) and a
        // `created = 0` legacy row that must not read as 1970.
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (`ip`, `authid`, `name`, `created`, `ends`, `length`, `reason`, `aid`, `adminIp`, `sid`, `type`)
             VALUES (?, ?, ?, ?, 0, 0, ?, 1, ?, ?, 0)',
            DB_PREFIX
        ));
        $stmt->execute(['', 'STEAM_0:1:1', 'a', $now - 100, 'r', '127.0.0.1', 0]);
        $stmt->execute(['', 'STEAM_0:1:2', 'b', $now - 200, 'r', '127.0.0.1', 0]);
        $stmt->execute(['', 'STEAM_0:1:3', 'c', $now - 300, 'r', '127.0.0.1', 0]);
        $stmt->execute(['', 'STEAM_0:1:4', 'd', $now - 400, 'r', '127.0.0.1', 1]);
        $stmt->execute(['', 'STEAM_0:1:5', 'e', $now - 400 * 86400, 'r', '127.0.0.1', 1]);
        $stmt->execute(['', 'STEAM_0:1:6', 'f', 0, 'r', '127.0.0.1', 1]);

        $pdo->prepare(sprintf('INSERT INTO `%s_groups` (`type`, `name`, `flags`) VALUES (1, ?, 0)', DB_PREFIX))
            ->execute(['web-group']);
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_srvgroups` (`flags`, `immunity`, `name`, `groups_immune`) VALUES (?, 0, ?, ?)',
            DB_PREFIX
        ))->execute(['b', 'srv-group', ' ']);
        $pdo->prepare(sprintf('INSERT INTO `%s_overrides` (`type`, `name`, `flags`) VALUES (?, ?, ?)', DB_PREFIX))
            ->execute(['command', 'sm_ban', 'd']);

        $this->insertLog('m', 'Data Export', $now - 1000);

        $payload = Telemetry::collect();
        $scale   = $payload['scale'];

        $this->assertTrue($payload['panel']['exported_30d']);
        $this->assertSame(4, $scale['bans_30d']);
        $this->assertSame(1, $scale['admins_active_30d'], 'fixture admin active, stale admin 40 days out, CONSOLE never');
        $this->assertSame(13, $scale['ban_history_months']);
        $this->assertTrue($scale['web_bans_majority_30d'], '3 of 4 recent bans are web bans');
        $this->assertSame(
            ['tf' => false, 'cstrike' => false, 'csgo' => true, 'garrysmod' => false, 'left4dead2' => false, 'other' => true],
            $scale['games']
        );
        $this->assertSame(
            ['web_groups' => true, 'server_groups' => true, 'overrides' => true, 'immunity' => true],
            $scale['perms']
        );
    }

    public function testBanHistoryIsNullWithoutBans(): void
    {
        $payload = Telemetry::collect();
        $this->assertNull($payload['scale']['ban_history_months']);
        $this->assertFalse($payload['scale']['web_bans_majority_30d']);
        $this->assertArrayNotHasKey(
            'ban_history_months',
            Telemetry::withoutNulls($payload)['scale'],
            'a null leaf must be dropped from the wire body, not sent as null'
        );
    }

    /**
     * Only a successful export inside the window counts: failures log
     * as `e`, and an export from 31 days ago is out of range.
     */
    public function testExportedFlagIgnoresFailuresAndOldExports(): void
    {
        $now = time();
        $this->insertLog('e', 'Data Export', $now - 1000);
        $this->insertLog('w', 'Data Export', $now - 1000);
        $this->insertLog('m', 'Data Export', $now - 31 * 86400);
        $this->insertLog('m', 'Group Created', $now - 1000);

        $this->assertFalse(Telemetry::collect()['panel']['exported_30d']);
    }

    private function insertLog(string $type, string $title, int $created): void
    {
        Fixture::rawPdo()->prepare(sprintf(
            'INSERT INTO `%s_log` (`type`, `title`, `message`, `function`, `query`, `aid`, `host`, `created`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX
        ))->execute([$type, $title, 'test', 'test', '', 0, '', $created]);
    }

    /**
     * Calling `collect()` twice in the same request must NOT mint a
     * new instance ID — the persisted value (or the freshly-minted +
     * memo'd value) is what the second call sees.
     */
    public function testInstanceIdIsStableAcrossCollectCalls(): void
    {
        $first  = Telemetry::collect();
        $second = Telemetry::collect();
        $this->assertSame($first['instance_id'], $second['instance_id']);
    }

    /**
     * `instance_id` is empty by default in the fresh fixture
     * (`telemetry.instance_id = ''` from data.sql); the first
     * `collect()` mints one, persists it to `:prefix_settings`, and
     * the row is observable via raw SQL.
     */
    public function testInstanceIdMintedAndPersistedOnFirstCollect(): void
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(
            sprintf('SELECT `value` FROM `%s_settings` WHERE `setting` = ?', DB_PREFIX)
        );
        $stmt->execute(['telemetry.instance_id']);
        $before = (string) $stmt->fetchColumn();
        $this->assertSame('', $before, 'fixture starts with an empty instance_id');

        $payload = Telemetry::collect();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', (string) $payload['instance_id']);

        $stmt = $pdo->prepare(
            sprintf('SELECT `value` FROM `%s_settings` WHERE `setting` = ?', DB_PREFIX)
        );
        $stmt->execute(['telemetry.instance_id']);
        $after = (string) $stmt->fetchColumn();
        $this->assertSame($payload['instance_id'], $after, 'persisted ID matches payload');
    }

    /**
     * Seed a synthetic universe whose row counts the per-test
     * assertions can pin against. Every PII-shaped value carries
     * a recognisable canary token so the no-PII test can probe
     * for the literal string.
     */
    private function seedSyntheticUniverse(): void
    {
        $pdo = Fixture::rawPdo();
        $now = time();

        // One additional admin row alongside the seeded admin + CONSOLE.
        $hash = password_hash('whatever', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (`user`, `authid`, `password`, `gid`, `email`, `validate`, `extraflags`, `immunity`)
             VALUES (?, ?, ?, -1, ?, NULL, ?, 0)',
            DB_PREFIX
        ));
        $stmt->execute(['PII-CANARY-ADMIN', 'STEAM_0:1:777', $hash, 'pii@example.test', 0]);

        // Servers: two enabled, one disabled. Hostnames carry a
        // canary the no-PII probe asserts is never echoed back.
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_servers` (`ip`, `port`, `rcon`, `modid`, `enabled`) VALUES (?, ?, ?, ?, ?)',
            DB_PREFIX
        ));
        $stmt->execute(['srv-canary-host.example.test', 27015, 'rcon-canary', 12, 1]);
        $stmt->execute(['10.0.0.2', 27016, 'rcon-canary', 12, 1]);
        $stmt->execute(['10.0.0.3', 27017, 'rcon-canary', 12, 0]);

        // Bans: 2 active (one with explicit ends in the future, one
        // permanent length=0), 1 expired, 1 removed.
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (`ip`, `authid`, `name`, `created`, `ends`, `length`, `reason`, `aid`, `adminIp`, `sid`, `RemovedBy`, `RemoveType`, `RemovedOn`, `type`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX
        ));
        // Active permanent
        $stmt->execute(['', 'STEAM_0:1:888', 'pii-canary-name-one', $now - 100, 0, 0, 'aimbot-canary-A1B2', 1, '127.0.0.1', 1, null, null, null, 0]);
        // Active timed
        $stmt->execute(['', 'STEAM_0:1:999', 'pii-canary-name-two', $now - 100, $now + 86400, 1440, 'wallhack-canary-C3D4', 1, '127.0.0.1', 1, null, null, null, 0]);
        // Expired
        $stmt->execute(['', 'STEAM_0:1:111', 'pii-canary-name-three', $now - 200000, $now - 100000, 1440, 'expired-reason', 1, '127.0.0.1', 1, null, null, null, 0]);
        // Removed
        $stmt->execute(['', 'STEAM_0:1:222', 'pii-canary-name-four', $now - 200, 0, 0, 'removed-reason', 1, '127.0.0.1', 1, 1, 'U', $now - 100, 0]);

        // Comms: 1 active permanent, 1 expired.
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_comms` (`authid`, `name`, `created`, `ends`, `length`, `reason`, `aid`, `adminIp`, `sid`, `type`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX
        ));
        $stmt->execute(['STEAM_0:1:888', 'pii-canary-name-five', $now - 50, 0, 0, 'mute-canary-reason-Q1Q2', 1, '127.0.0.1', 1, 1]);
        $stmt->execute(['STEAM_0:1:999', 'pii-canary-name-six', $now - 200000, $now - 100000, 1440, 'gag-expired', 1, '127.0.0.1', 1, 2]);

        // Submissions in the last 30 days (2 inside the cutoff, 1 way
        // outside). 30-day cutoff is `now - 2592000`, so the seeded
        // timestamps are deliberately on either side of that boundary.
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_submissions` (`submitted`, `ModID`, `SteamId`, `name`, `email`, `reason`, `ip`)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            DB_PREFIX
        ));
        $stmt->execute([$now - 86400,  1, 'STEAM_0:1:111', 'submitter-A', 'a@example.test', 'sub-reason-1', '10.0.0.4']);
        $stmt->execute([$now - 1000,   1, 'STEAM_0:1:333', 'submitter-C', 'c@example.test', 'sub-reason-3', '10.0.0.6']);
        $stmt->execute([$now - 5000000, 1, 'STEAM_0:1:222', 'submitter-B', 'b@example.test', 'sub-reason-2', '10.0.0.5']);

        // Protests: 1 inside the 30-day window, 1 way outside.
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_protests` (`bid`, `datesubmitted`, `reason`, `email`, `pip`)
             VALUES (?, ?, ?, ?, ?)',
            DB_PREFIX
        ));
        $stmt->execute([1, $now - 86400, 'protest-reason-1', 'p@example.test', '10.0.0.7']);
        $stmt->execute([1, $now - 5000000, 'protest-reason-2', 'q@example.test', '10.0.0.8']);
    }
}
