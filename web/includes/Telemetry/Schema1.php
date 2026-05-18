<?php
declare(strict_types=1);

namespace Sbpp\Telemetry;

use RuntimeException;

/**
 * Reads the vendored Draft-7 JSON Schema at
 * `web/includes/Telemetry/schema-1.lock.json` and exposes its
 * recursively-flattened leaf field set.
 *
 * The lock file is the canonical wire-format contract for the
 * panel's daily anonymous telemetry ping (#1126). It's vendored
 * byte-for-byte from the cf-analytics companion repo via
 * `make sync-telemetry-schema`. The extractor parity test gates it:
 *
 *   - `TelemetrySchemaParityTest` deep-equals the lock file's leaf
 *     field set against the recursively-flattened output of
 *     `Telemetry::collect()`. Adding a typed slot in cf-analytics →
 *     next sync → panel parity test fails until an extractor lands.
 *
 * The lock file is also the single source of truth for anyone who
 * wants the field-by-field breakdown — no human-readable mirror is
 * kept anywhere in the repo. A previous README mirror + paired
 * `TelemetryReadmeParityTest` was removed because the duplication
 * paid for the drift risk it created.
 *
 * `payloadFieldNames()` returns dot-paths in the natural order the
 * payload tree declares them (top-level `schema` first, then
 * `instance_id`, then each nested object's properties in
 * declaration order). Tests should sort both sides before
 * comparing if they don't care about order; the issue's
 * "additive — new optional field within `schema: 1`" rule is the
 * load-bearing one, not "fields appear in this exact order".
 */
final class Schema1
{
    /**
     * Default location of the vendored lock file. The class accepts
     * a custom path for tests; production callers go through
     * `payloadFieldNames()` which uses this default.
     */
    public const LOCK_FILE = __DIR__ . '/schema-1.lock.json';

    /** Process-local cache so repeated calls don't re-parse the file. */
    private static ?array $cachedFieldNames = null;

    /**
     * Disallow instantiation — this class is a static helper.
     */
    private function __construct()
    {
    }

    /**
     * The recursively-flattened leaf field set of the vendored
     * schema.
     *
     * @return list<string>
     */
    public static function payloadFieldNames(): array
    {
        if (self::$cachedFieldNames !== null) {
            return self::$cachedFieldNames;
        }

        // The lock file's `properties` tree IS the wire payload today —
        // there are no storage-only fields to filter back out. If a
        // future cf-analytics-side sync introduces a documentation-only
        // block (an `extras` / `panel_features_bits` storage container
        // that the panel never sends), reintroduce a `STORAGE_ONLY_FIELDS`
        // const + an array_filter pass here.
        $schema = self::loadSchema(self::LOCK_FILE);
        $names  = self::flattenProperties($schema, '');

        self::$cachedFieldNames = $names;
        return $names;
    }

    /**
     * Reset the process-local cache. Tests that mutate the lock
     * file in-place call this between assertions; production never
     * does.
     */
    public static function resetCache(): void
    {
        self::$cachedFieldNames = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function loadSchema(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            throw new RuntimeException("Telemetry schema lock file missing or unreadable: {$path}");
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("Telemetry schema lock file is not valid JSON: {$path}");
        }
        return $decoded;
    }

    /**
     * Recursively walk a JSON Schema's `properties` map, emitting a
     * dot-path per leaf. A property whose own `type` is `object`
     * (and which carries its own nested `properties`) is descended
     * into; everything else is a leaf.
     *
     * @param array<string, mixed> $node
     * @return list<string>
     */
    private static function flattenProperties(array $node, string $prefix): array
    {
        $properties = $node['properties'] ?? null;
        if (!is_array($properties)) {
            return [];
        }

        $names = [];
        foreach ($properties as $key => $sub) {
            if (!is_string($key) || !is_array($sub)) {
                continue;
            }
            $path = $prefix === '' ? $key : ($prefix . '.' . $key);
            $type = $sub['type'] ?? null;
            if ($type === 'object' && isset($sub['properties']) && is_array($sub['properties'])) {
                foreach (self::flattenProperties($sub, $path) as $childPath) {
                    $names[] = $childPath;
                }
                continue;
            }
            $names[] = $path;
        }
        return $names;
    }
}
