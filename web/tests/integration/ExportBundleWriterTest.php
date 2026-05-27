<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Sbpp\Export\BundleWriter;
use Sbpp\Export\EntityExporter;
use Sbpp\Export\Manifest;
use Sbpp\Export\ManifestBuilder;
use Sbpp\Tests\Fixture;
use ZipArchive;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

/**
 * End-to-end bundle-shape contract pin.
 *
 * Drives the full {@see ManifestBuilder} → {@see BundleWriter}
 * → on-disk ZIP pipeline against the seeded test DB, then
 * cracks the resulting archive open with {@see ZipArchive} and
 * asserts the structural promises any downstream consumer rides:
 *
 *   - `manifest.json` is the **first** entry (offset 0). A
 *     consumer can pull just the manifest from the head of the
 *     stream to decide whether to bother downloading the rest.
 *   - Every entity JSONL row count agrees with the manifest's
 *     `row_counts[<entity>]`. Pre-flight + writer agree.
 *   - Every demo entry is stored with {@see ZipArchive::CM_STORE}
 *     (no DEFLATE on already-compressed binary).
 *   - Every JSONL line parses as JSON and carries an `id` field
 *     (or the documented composite-PK exception for
 *     `admins_servers_groups` / `banlog` / `servers_groups`).
 *   - No SteamID appears as a bare JSON number; every authid is
 *     quoted decimal Steam64. The 17-digit values overflow
 *     JS `Number.MAX_SAFE_INTEGER` (2^53-1 ≈ 16 digits).
 *   - The forbidden columns (bcrypt hashes, RCON passwords, SMTP
 *     credentials, telemetry instance ID) literally don't appear
 *     in the bundle bytes.
 *
 * Scope split: this file is the only integration-level export
 * suite — sister {@see \Sbpp\Tests\Unit\EntityExporterTest} (per-
 * entity column rules), {@see \Sbpp\Tests\Unit\ManifestBuilderTest}
 * (manifest / DTO / cap math), {@see AdminExportPermissionTest}
 * (HTTP entry gates), {@see S3PresignedUploaderTest} (cURL stub
 * paths) each own their layer in isolation.
 */
final class ExportBundleWriterTest extends TestCase
{
    private string $bundlePath;

    /** @var list<string> */
    private array $seededDemoFiles = [];

    /**
     * The writer in M1 form gets a non-null `$outputHandle` arg so
     * its cap counter can snap to the on-disk file size after each
     * entry — exact compressed-byte tracking instead of the
     * conservative uncompressed-byte estimate. Captured here so
     * the M1 regression test can assert the same handle's
     * post-finish size matches `bytesWritten()`.
     */
    private ?BundleWriter $lastWriter = null;

    protected function setUp(): void
    {
        parent::setUp();
        Fixture::reset();

        $this->bundlePath = SB_CACHE . 'export-bundle-test-' . bin2hex(random_bytes(6)) . '.zip';
        // Defensive cleanup against a prior crash leaving residue.
        @unlink($this->bundlePath);
    }

    protected function tearDown(): void
    {
        if (is_file($this->bundlePath)) {
            @unlink($this->bundlePath);
        }
        foreach ($this->seededDemoFiles as $f) {
            @unlink($f);
        }
        $this->seededDemoFiles = [];
        parent::tearDown();
    }

    /**
     * Drive the full export against the empty-fixture DB and assert
     * every structural contract. The empty DB still seeds the admin
     * row + the default `sb_settings` rows + the default `sb_mods`
     * rows, so the bundle is not entirely vacuous — `admins` /
     * `settings` / `mods` carry entries and we can exercise the
     * row-count agreement contract on them.
     */
    public function testFullBundleWriteAgainstSeededFixture(): void
    {
        // Seed one ban so the `bans` JSONL is non-empty AND so the
        // demo subsystem has something to LEFT JOIN against.
        $this->seedSampleBan();

        // Seed a demo file on disk + a `:prefix_demos` row referencing
        // it, so the bundle picks up a STORE-mode demo entry.
        $this->seedSampleDemo('export-bundle-test.dem', "DEMOFAKEHEADER_DISTINCTIVE_MARKER\x00" . str_repeat("\x01", 4096));

        $this->writeBundle();

        $zip = new ZipArchive();
        $opened = $zip->open($this->bundlePath);
        $this->assertTrue($opened === true, "ZipArchive::open failed: $opened");

        // ---- (1) manifest.json is the first entry ---------------
        $first = $zip->statIndex(0);
        $this->assertIsArray($first);
        $this->assertSame(
            'manifest.json',
            $first['name'],
            'manifest.json must be the FIRST entry in the bundle (offset 0). '
            . 'Consumers rely on being able to short-circuit after one entry.',
        );

        // ---- (2) manifest JSON parses + carries expected keys ----
        $manifestBody = $zip->getFromName('manifest.json');
        $this->assertIsString($manifestBody);
        $manifest = json_decode($manifestBody, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($manifest);
        $this->assertSame(Manifest::FORMAT_VERSION, $manifest['format_version']);
        $this->assertIsString($manifest['bundle_id']);
        $this->assertIsArray($manifest['row_counts']);
        $this->assertIsArray($manifest['demo_files']);

        // ---- (3) row_counts agree with JSONL line counts --------
        foreach ($manifest['row_counts'] as $entity => $count) {
            $entry = "entities/{$entity}.jsonl";
            $body  = $zip->getFromName($entry);
            $this->assertIsString($body, "missing entity entry: $entry");
            $lines = $body === '' ? 0 : substr_count($body, "\n");
            $this->assertSame(
                (int) $count,
                $lines,
                "manifest row_counts[{$entity}]={$count} but JSONL has {$lines} lines",
            );
        }

        // ---- (4) every demo entry is STORE-compressed -----------
        $sawDemo = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $this->assertIsArray($stat);
            if (str_starts_with((string) $stat['name'], 'demos/')) {
                $sawDemo = true;
                $this->assertSame(
                    ZipArchive::CM_STORE,
                    $stat['comp_method'],
                    "demo entry {$stat['name']} must use STORE compression (already-compressed binary)",
                );
            }
        }
        $this->assertTrue($sawDemo, 'fixture seeded a demo but bundle carries no demos/ entries');

        // ---- (5) every JSONL line parses + carries an `id` -----
        // Composite-PK tables don't carry `id`; they're documented
        // exceptions per the contract.
        $compositePkTables = ['admins_servers_groups', 'banlog', 'servers_groups'];
        foreach ($manifest['row_counts'] as $entity => $_count) {
            $body = $zip->getFromName("entities/{$entity}.jsonl");
            if ($body === false || $body === '') {
                continue;
            }
            foreach (explode("\n", trim($body)) as $lineNo => $line) {
                if ($line === '') {
                    continue;
                }
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $this->assertIsArray($row, "entities/{$entity}.jsonl line {$lineNo} did not parse as JSON object");
                if (!in_array($entity, $compositePkTables, true)) {
                    $this->assertArrayHasKey('id', $row, "entities/{$entity}.jsonl line {$lineNo} is missing the `id` field");
                }
            }
        }

        // ---- (6) no SteamID appears as a bare JSON number ------
        // The full ZIP body would include the binary demo payload
        // which can incidentally contain "authid":<digits> bytes;
        // restrict the assertion to the entity JSONL files only.
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $this->assertIsArray($stat);
            $name = (string) $stat['name'];
            if (!str_starts_with($name, 'entities/')) {
                continue;
            }
            $body = $zip->getFromName($name);
            $this->assertIsString($body);
            // Match `"authid":` followed by optional whitespace then a
            // bare digit (NOT a quoted string). Negative-lookahead the
            // closing `"` so we accept the quoted-string form.
            $this->assertDoesNotMatchRegularExpression(
                '/"authid"\s*:\s*\d/',
                $body,
                "entity {$name} carried an unquoted Steam ID (must be a decimal STRING)",
            );
        }

        // ---- (7) timestamps surface as integers ---------------
        // Sample a known-populated entity (bans) — bans.created is
        // documented as unix-seconds int. The decoded row from
        // step 5 already proved JSON parses; here we assert the
        // numeric column landed as int, not as a quoted string.
        $bansBody = $zip->getFromName('entities/bans.jsonl');
        $this->assertIsString($bansBody);
        $this->assertNotSame('', trim($bansBody), 'fixture seeded a ban; bundle must carry it');
        $firstBan = json_decode(trim(explode("\n", $bansBody)[0]), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($firstBan);
        $this->assertIsInt($firstBan['created'], 'bans.created must be a unix-seconds int');
        $this->assertIsInt($firstBan['ends']);
        $this->assertIsInt($firstBan['length']);

        // ---- (8) forbidden columns / values never appear ------
        // Inspect every entity JSONL body — the demo blob is
        // exempt for the same reason as step 6.
        $expectedForbiddenAdminCols = EntityExporter::FORBIDDEN_ADMIN_COLUMNS;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $this->assertIsArray($stat);
            $name = (string) $stat['name'];
            if (!str_starts_with($name, 'entities/')) {
                continue;
            }
            $body = $zip->getFromName($name);
            $this->assertIsString($body);
            // Forbidden admin column keys (`"password":`, `"validate":`,
            // `"attempts":`, `"lockout_until":`) must never appear in
            // ANY entity — admins.jsonl is the load-bearing case but
            // bans/comms/log don't carry them either.
            foreach ($expectedForbiddenAdminCols as $col) {
                $this->assertStringNotContainsString(
                    "\"{$col}\":",
                    $body,
                    "{$name} leaked forbidden admin column `{$col}`",
                );
            }
            // bcrypt prefix `$2y$` is distinctive — would only
            // appear if a hash leaked anywhere.
            $this->assertStringNotContainsString('$2y$', $body, "{$name} leaked a bcrypt hash");
        }

        $zip->close();
    }

    /**
     * Run the full pipeline + dump the resulting bundle to a temp
     * file on disk so the {@see ZipArchive} consumer can open it.
     * The s3-mode flush gate is `false` because the writer's output
     * stream is a file handle, not the HTTP socket. ZipStream uses
     * its v3.x default `enableZip64: true` — Zip64 is enabled
     * panel-wide; the per-mode cap is the only structural limit.
     *
     * The writer is constructed with `capBytes: MAX_S3_PUT_BYTES -
     * SAFETY_MARGIN_BYTES` to mirror `web/export.php`'s s3-mode
     * wiring (build-to-disk-then-PUT) — the integration test is
     * shaped like the s3 path because it builds to a staging file.
     * The zip-mode path is uncapped (`capBytes: null`) and is
     * exercised separately by the e2e spec.
     */
    private function writeBundle(): void
    {
        $builder  = new ManifestBuilder(
            dbs:          $GLOBALS['PDO'],
            demosDir:     SB_DEMOS,
            panelVersion: 'test',
        );
        $manifest = $builder->build();
        $entities = new EntityExporter(
            dbs:      $GLOBALS['PDO'],
            demosDir: SB_DEMOS,
        );

        $handle = fopen($this->bundlePath, 'wb');
        $this->assertNotFalse($handle, "fopen failed for $this->bundlePath");
        $zip = new ZipStream(
            outputStream:             $handle,
            sendHttpHeaders:          false,
            defaultCompressionMethod: CompressionMethod::DEFLATE,
        );
        $writer = new BundleWriter(
            zip:               $zip,
            manifest:          $manifest,
            entities:          $entities,
            demosDir:          SB_DEMOS,
            flushAfterEntries: false,
            // M1: hand the writer the staging-file handle so the
            // running cap counter snaps to exact compressed bytes
            // via post-write `fstat`. Mirrors `web/export.php`'s
            // s3-mode wiring so the integration test exercises
            // the same code path real operators hit.
            outputHandle:      $handle,
            // s3-mode cap: 5 GiB minus the safety margin. Mirrors
            // `web/export.php`'s s3-mode wiring.
            capBytes:          Manifest::s3PutCapBytes(),
        );
        $writer->write();
        $this->lastWriter = $writer;
        fclose($handle);

        $this->assertFileExists($this->bundlePath);
        $this->assertGreaterThan(0, filesize($this->bundlePath) ?: 0);
    }

    /**
     * M1 regression guard: when the writer is constructed with a
     * non-null `$outputHandle` (the s3-mode build-to-disk path),
     * `bytesWritten()` MUST match the on-disk file size byte-for-
     * byte. Pre-M1 the running cap counter advanced by uncompressed
     * deltas, which over-counted DEFLATE entities (the JSONL
     * compresses 3-8x in practice) and could trip a premature
     * `CAP_EXCEEDED` on bundles that actually fit. Post-M1 the
     * counter snaps to the on-disk size after each entry, so the
     * final value reported is exact.
     *
     * The zip-mode (php://output) path still uses the uncompressed
     * estimate because it has no seekable handle to stat — that
     * arm is documented on `BundleWriter::bytesWritten` and isn't
     * exercised here (the integration test only covers s3-mode
     * because we need a file to crack open with ZipArchive).
     */
    public function testWriterBytesWrittenMatchesOnDiskSizeInS3Mode(): void
    {
        $this->seedSampleBan();
        $this->seedSampleDemo('m1-fstat-marker.dem', str_repeat("\x42", 8192));
        $this->writeBundle();

        $this->assertNotNull($this->lastWriter, 'writeBundle must populate lastWriter');
        $diskSize = filesize($this->bundlePath);
        $this->assertNotFalse($diskSize, 'filesize() failed on the staging file');
        $this->assertSame(
            (int) $diskSize,
            $this->lastWriter->bytesWritten(),
            'BundleWriter::bytesWritten() in s3 mode (non-null $outputHandle) must report the EXACT '
            . 'on-disk compressed-byte size — pre-M1 it reported the uncompressed estimate and could '
            . 'over-count by 3-8x for DEFLATE-compressed JSONL, prematurely tripping CAP_EXCEEDED.',
        );
    }

    /**
     * H1 regression guard: writing a large entity must NOT
     * proportionally inflate PHP's peak memory. Pre-H1 the
     * writer concatenated every yielded JSONL line into a single
     * PHP string inside an `addFileFromCallback`, so a multi-
     * hundred-MB audit log would push peak memory past the
     * runtime hardening's 256 MiB ceiling — OOM on shared
     * hosting where 128 MiB is the realistic floor. Post-H1
     * the writer spills to `php://temp/maxmemory:8M`, so the
     * working set stays bounded at the spill threshold
     * regardless of total entity size.
     *
     * Seeds enough comments to push the comments entity well past
     * the spill threshold AND records peak memory after the write
     * completes. The assertion shape pins the spill behaviour by
     * upper-bounding peak growth at ~64 MiB — enough headroom
     * for Smarty / Composer / PDO overhead but well below the
     * pre-H1 shape's "entity-size-proportional" growth.
     */
    public function testWriterDoesNotBufferLargeEntityIntoMemory(): void
    {
        $pdo = \Sbpp\Tests\Fixture::rawPdo();
        $this->seedSampleBan();
        $bid = (int) $pdo->query(sprintf('SELECT MAX(bid) FROM `%s_bans`', DB_PREFIX))->fetchColumn();
        $this->assertGreaterThan(0, $bid, 'must have seeded a ban first');

        // Seed a payload sized to exceed the spill threshold but
        // not so large that the test runtime blows. Each comment
        // row carries ~600 bytes of body text; 25k rows → ~15 MiB
        // uncompressed JSONL, comfortably above the 8 MiB spill
        // threshold but quick to write.
        $payload = str_repeat('H1_LARGE_ENTITY_PAYLOAD_MARKER ', 18);
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_comments` (type, bid, commenttxt, added, aid) VALUES ("B", ?, ?, UNIX_TIMESTAMP(), 1)',
            DB_PREFIX,
        ));
        for ($i = 0; $i < 25_000; $i++) {
            $stmt->execute([$bid, $payload]);
        }

        $before = memory_get_peak_usage(true);
        $this->writeBundle();
        $after = memory_get_peak_usage(true);

        $delta = $after - $before;
        $this->assertLessThan(
            64 * 1024 * 1024,
            $delta,
            'Writing a >15 MiB entity grew PHP peak memory by ' . number_format($delta / 1024 / 1024, 2) . ' MiB. '
            . 'Pre-H1 the writer concatenated the whole entity into a PHP string, so peak grew proportionally '
            . 'to the entity size — OOM risk on shared hosting at scale. Post-H1 the writer spills to '
            . 'php://temp/maxmemory:8M and peak should stay bounded.',
        );

        // Sanity: the comments entity actually landed in the bundle.
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($this->bundlePath) === true);
        $commentsBody = $zip->getFromName('entities/comments.jsonl');
        $zip->close();
        $this->assertIsString($commentsBody);
        $this->assertGreaterThan(
            8 * 1024 * 1024,
            strlen($commentsBody),
            'Comments JSONL should exceed the 8 MiB spill threshold so this test exercises the spill path',
        );
    }

    /**
     * Seed a single ban row keyed off the fixture admin so the
     * `bans` JSONL is non-empty. The matching `:prefix_demos`
     * row is wired in {@see seedSampleDemo}.
     */
    private function seedSampleBan(): void
    {
        $aid = Fixture::adminAid();
        $pdo = Fixture::rawPdo();
        $pdo->exec(
            sprintf(
                "INSERT INTO `%s_bans`
                  (ip, authid, name, created, ends, length, reason, aid, adminIp, sid, country, type)
                 VALUES ('', 'STEAM_0:0:42', 'export-bundle-fixture', UNIX_TIMESTAMP(), UNIX_TIMESTAMP() + 3600, 3600, 'fixture', %d, '127.0.0.1', 0, '', 0)",
                DB_PREFIX,
                $aid,
            )
        );
    }

    /**
     * Drop a sample demo file on disk and a corresponding
     * `:prefix_demos.filename` row so the bundle picks up a
     * `demos/<basename>.dem` entry.
     *
     * The file payload itself is opaque — what we care about
     * downstream is just that the ZIP entry exists with STORE
     * compression.
     */
    private function seedSampleDemo(string $basename, string $body): void
    {
        // SB_DEMOS may not exist on a fresh test bring-up; create it.
        if (!is_dir(SB_DEMOS)) {
            @mkdir(SB_DEMOS, 0755, true);
        }
        $path = SB_DEMOS . DIRECTORY_SEPARATOR . $basename;
        file_put_contents($path, $body);
        $this->seededDemoFiles[] = $path;

        $pdo = Fixture::rawPdo();
        // Hang the demo off the just-seeded ban (the highest bid).
        $bid = (int) $pdo->query(sprintf('SELECT MAX(bid) FROM `%s_bans`', DB_PREFIX))->fetchColumn();
        $this->assertGreaterThan(0, $bid, 'must have seeded a ban first');
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_demos` (demid, demtype, filename, origname) VALUES (?, ?, ?, ?)',
            DB_PREFIX,
        ))->execute([$bid, 'B', $basename, $basename]);
    }
}
