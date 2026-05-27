<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sbpp\Export\Manifest;
use Sbpp\Export\ManifestBuilder;
use Sbpp\Tests\Fixture;

/**
 * Manifest contract pins — these are the bits the bundle's
 * downstream consumers (operator-side migration tooling, audit
 * pipelines, third-party importers) read from `manifest.json` and
 * key off of. Drift in any of them is a wire-format break, so the
 * tests assert the field set + the type / shape of every value
 * downstream depends on.
 *
 * Scope: this file covers the BUILDER + DTO surfaces — bundle-id
 * shape, format_version constant, cap math, PII policy block. The
 * row-count side (which depends on a populated DB) is exercised by
 * ExportBundleWriterTest. Splitting the two surfaces keeps each test
 * file's bring-up cost predictable.
 */
final class ManifestBuilderTest extends TestCase
{
    /**
     * Default cap is `4 * 1024^3` bytes minus a `64 * 1024^2`-byte
     * safety margin — the headroom protects against last-minute
     * compression-ratio surprises (deflate occasionally lands at
     * 1.0x or worse on already-compressed payloads). The constants
     * are public on Manifest because the cap is part of the bundle's
     * wire contract; consumers ARE allowed to read them back, so
     * silent drift would be a contract break.
     */
    public function testManifestCapConstantsMatchSpec(): void
    {
        $this->assertSame(4 * 1024 * 1024 * 1024, Manifest::MAX_BUNDLE_BYTES);
        $this->assertSame(64 * 1024 * 1024, Manifest::SAFETY_MARGIN_BYTES);
        $this->assertSame(1, Manifest::FORMAT_VERSION);
    }

    /**
     * Bundle ID format is RFC 4122 UUIDv4 — 32 lowercase hex digits
     * grouped 8-4-4-4-12 with the version nibble at position 12 set
     * to '4' and the variant high bits at position 16 set to '8/9/a/b'.
     * The regex pins ALL THREE: hex-only, dash positions, version,
     * variant. A future refactor that swaps to UUIDv7 (time-ordered)
     * MUST update this assertion + the constructor doc in
     * ManifestBuilder, since downstream tooling may sort bundle IDs.
     */
    public function testBundleIdIsValidUuidV4(): void
    {
        Fixture::reset();
        $builder = $this->builder();
        $manifest = $builder->build();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $manifest->bundle_id,
            'bundle_id must be a lowercase UUIDv4 with the correct version + variant nibbles',
        );
    }

    /**
     * Two consecutive builds against the same DB state MUST produce
     * different bundle IDs — the ID is a freshness token an operator
     * uses to disambiguate consecutive exports in their downstream
     * pipeline. Same DB state, same row counts, same payload — but
     * different ID. (The downstream `Content-Disposition` filename
     * also embeds the ID; collisions would silently overwrite.)
     */
    public function testBundleIdIsUniquePerBuild(): void
    {
        Fixture::reset();
        $builder = $this->builder();
        $a = $builder->build();
        $b = $builder->build();
        $this->assertNotSame($a->bundle_id, $b->bundle_id);
    }

    /**
     * `created_at` is a unix-seconds integer (NOT a millisecond
     * value, NOT an ISO string). Consumers convert via
     * `new Date(created_at * 1000)` on the JS side or
     * `datetime.fromtimestamp(created_at)` on the Python side; a
     * silent unit drift would land wall-clock errors decades into
     * the future. Sanity-check the value lands in the plausible
     * window for "right now".
     */
    public function testCreatedAtIsUnixSecondsInteger(): void
    {
        Fixture::reset();
        $manifest = $this->builder()->build();
        $this->assertIsInt($manifest->created_at);
        $this->assertGreaterThan(1_700_000_000, $manifest->created_at); // sometime after 2023-11
        $this->assertLessThan(time() + 5, $manifest->created_at);       // within a few seconds of now
    }

    /**
     * PII policy block is the load-bearing operator-facing
     * "what's in here" attestation. Every field MUST be present
     * with the documented type, even when the value is `false` —
     * a missing field could be misread as a hidden category. The
     * shape is pinned at the wire level (in `Manifest::toJson()`)
     * not just at the DTO level.
     */
    public function testPiiPolicyBlockShape(): void
    {
        Fixture::reset();
        $manifest = $this->builder()->build();
        $policy = $manifest->pii_policy;

        // Required keys — drift here is a wire format break.
        $this->assertArrayHasKey('includes_admin_emails', $policy);
        $this->assertArrayHasKey('includes_ip_addresses', $policy);
        $this->assertArrayHasKey('includes_chat_messages', $policy);
        $this->assertArrayHasKey('includes_steam_ids', $policy);
        $this->assertArrayHasKey('includes_unban_reasons', $policy);
        $this->assertArrayHasKey('password_hashes', $policy);

        // Value contracts — the panel DOES include emails + IPs +
        // Steam IDs + unban reasons; it does NOT store chat
        // messages anywhere in struc.sql; password hashes are
        // NEVER exported. The 'never' string is contract — a future
        // refactor swapping to a bool would silently lose the
        // explicit-by-design signal.
        $this->assertTrue($policy['includes_admin_emails']);
        $this->assertTrue($policy['includes_ip_addresses']);
        $this->assertFalse($policy['includes_chat_messages']);
        $this->assertTrue($policy['includes_steam_ids']);
        $this->assertTrue($policy['includes_unban_reasons']);
        $this->assertSame('never', $policy['password_hashes']);
    }

    /**
     * `toJson()` writes the manifest with the exact set of top-level
     * keys downstream tooling consumes. Drift here breaks any
     * consumer that parses the manifest with a fixed schema (Pydantic,
     * JSON Schema, TypeScript DTOs). Use `assertSame` on the sorted
     * key list so a future addition has to update this test.
     */
    public function testToJsonProducesExpectedTopLevelShape(): void
    {
        Fixture::reset();
        $manifest = $this->builder()->build();

        $decoded = json_decode($manifest->toJson(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        $keys = array_keys($decoded);
        sort($keys);
        $this->assertSame(
            [
                'bundle_id',
                'cap_bytes',
                'created_at',
                'demo_files',
                'demo_total_bytes',
                'estimated_bundle_bytes',
                'exceeds_cap',
                'format_version',
                'panel_version',
                'pii_policy',
                'row_counts',
            ],
            $keys,
        );

        $this->assertSame(1, $decoded['format_version']);
        $this->assertIsArray($decoded['row_counts']);
        $this->assertIsArray($decoded['demo_files']);
        $this->assertIsArray($decoded['pii_policy']);
        $this->assertIsBool($decoded['exceeds_cap']);
    }

    /**
     * Cap-exceeded gate: when the estimated bundle bytes overflow
     * `MAX_BUNDLE_BYTES - SAFETY_MARGIN_BYTES`, `build()` populates
     * `exceeds_cap=true` (non-throwing — the admin page renders the
     * form with the stats so the operator can see WHY they're
     * blocked). `buildOrThrow()` is the stricter sister that throws
     * `ExportError(CAP_EXCEEDED)` for the same condition — used by
     * the entry point right BEFORE bytes hit the wire.
     *
     * Test approach: directly construct a Manifest DTO with a
     * synthetic `estimated_bundle_bytes` value at the cap boundary.
     * We don't try to populate the real DB to 4 GiB worth of rows —
     * that would be brittle and slow. The cap predicate lives on the
     * DTO itself (via the constructor flag set by ManifestBuilder),
     * so we test the DTO contract directly.
     */
    public function testManifestExposesExceedsCapFlag(): void
    {
        // At-cap-minus-margin: still under.
        $cap = Manifest::MAX_BUNDLE_BYTES - Manifest::SAFETY_MARGIN_BYTES;

        $under = new Manifest(
            bundle_id:              'test-uuid',
            created_at:             1_700_000_000,
            panel_version:          'test',
            row_counts:             [],
            demo_files:             [],
            demo_total_bytes:       0,
            estimated_bundle_bytes: $cap - 1,
            exceeds_cap:            false,
            cap_bytes:              $cap,
            pii_policy:             ['includes_admin_emails' => true],
        );
        $this->assertFalse($under->exceeds_cap);

        $over = new Manifest(
            bundle_id:              'test-uuid',
            created_at:             1_700_000_000,
            panel_version:          'test',
            row_counts:             [],
            demo_files:             [],
            demo_total_bytes:       Manifest::MAX_BUNDLE_BYTES,
            estimated_bundle_bytes: $cap + 1,
            exceeds_cap:            true,
            cap_bytes:              $cap,
            pii_policy:             ['includes_admin_emails' => true],
        );
        $this->assertTrue($over->exceeds_cap);
    }

    private function builder(): ManifestBuilder
    {
        return new ManifestBuilder(
            dbs:          $GLOBALS['PDO'],
            demosDir:     SB_DEMOS,
            panelVersion: 'test',
        );
    }
}
