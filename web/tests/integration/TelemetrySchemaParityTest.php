<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Telemetry\Schema1;
use Sbpp\Telemetry\Telemetry;
use Sbpp\Tests\ApiTestCase;

/**
 * #1126 — the panel's extractor set must deep-equal the vendored
 * schema's leaf field set in BOTH directions.
 *
 *   - Lock file → extractors: every typed slot in
 *     `web/includes/Telemetry/schema-1.lock.json` has a
 *     corresponding key in `Telemetry::collect()`'s output. Adding
 *     a slot in cf-analytics → next sync → this test fails until
 *     an extractor is added.
 *   - Extractors → lock file: every key in `collect()`'s output
 *     appears in the lock file. Drift in the other direction (a
 *     panel-only field that was never blessed by the cross-repo
 *     contract) fails just as loudly.
 *
 * `Schema1::payloadFieldNames()` is the single source of truth for
 * the lock side; the recursive flattening of `Telemetry::collect()`
 * is the source of truth for the extractor side. Both sets are
 * sorted before `assertEquals` so the test doesn't gate on
 * declaration order — only on membership.
 */
final class TelemetrySchemaParityTest extends ApiTestCase
{
    public function testCollectMatchesLockFileInBothDirections(): void
    {
        Telemetry::resetInstanceIdMemoForTests();
        Schema1::resetCache();

        $payloadFields = self::flatten(Telemetry::collect());
        $schemaFields  = Schema1::payloadFieldNames();

        sort($payloadFields);
        sort($schemaFields);

        $this->assertSame(
            $schemaFields,
            $payloadFields,
            'Telemetry::collect() output and the vendored schema lock file must agree '
            . 'on the leaf field set (in both directions). If you intentionally added a '
            . 'field, sync the lock file via `make sync-telemetry-schema` and add the '
            . 'matching extractor in `Sbpp\\Telemetry\\Telemetry::collect()`.'
        );
    }

    /**
     * Sanity check: the schema-side flattener must produce the
     * documented field count. Pinning this catches a silent shape
     * regression in `Schema1::flattenProperties()` that
     * `assertEquals` between the same broken function and an
     * equally broken `flatten` would miss.
     */
    public function testSchemaPayloadFieldNamesIsExpectedSet(): void
    {
        $expected = [
            'env.db_engine',
            'env.db_version',
            'env.os_family',
            'env.php',
            'env.web_server',
            'features.adminrehashing',
            'features.comms',
            'features.exportpublic',
            'features.friendsbanning',
            'features.geoip_present',
            'features.groupbanning',
            'features.kickit',
            'features.normallogin',
            'features.protest',
            'features.publiccomments',
            'features.smtp_configured',
            'features.steam_api_key_set',
            'features.steamlogin',
            'features.submit',
            'instance_id',
            'panel.dev',
            'panel.git',
            'panel.theme',
            'panel.version',
            'scale.admins',
            'scale.bans_active',
            'scale.bans_total',
            'scale.comms_active',
            'scale.comms_total',
            'scale.protests_30d',
            'scale.servers_enabled',
            'scale.submissions_30d',
            'schema',
        ];
        $actual = Schema1::payloadFieldNames();
        sort($actual);
        $this->assertSame($expected, $actual);
    }

    /**
     * Recursively flatten an associative array into dot-paths,
     * matching `Schema1::payloadFieldNames()`'s shape.
     *
     * @param array<string, mixed> $node
     * @return list<string>
     */
    private static function flatten(array $node, string $prefix = ''): array
    {
        $names = [];
        foreach ($node as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $path = $prefix === '' ? $key : ($prefix . '.' . $key);
            if (is_array($value) && self::isAssoc($value)) {
                foreach (self::flatten($value, $path) as $childPath) {
                    $names[] = $childPath;
                }
                continue;
            }
            $names[] = $path;
        }
        return $names;
    }

    private static function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        foreach (array_keys($arr) as $k) {
            if (!is_string($k)) {
                return false;
            }
        }
        return true;
    }
}
