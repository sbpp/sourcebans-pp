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
