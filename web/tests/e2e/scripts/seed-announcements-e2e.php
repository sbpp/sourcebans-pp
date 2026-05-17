<?php
/**
 * E2E announcements-cache seeder.
 *
 * The dashboard's announcement strip is sourced from
 * `SB_CACHE/announcements.json`, populated by
 * `Sbpp\Announce\AnnouncementFetcher::tickIfDue()` (a daily upstream
 * fetch). E2E specs need to drive the strip without doing the real
 * HTTPS round-trip — this shim writes the cache file directly so the
 * spec sees a deterministic announcement.
 *
 * The cache file lives under `SB_CACHE` (defined in
 * `web/tests/bootstrap.php` to `<panel-root>/cache/`), shared with
 * the live panel inside the dev container. The spec is responsible
 * for cleaning up after itself via `--clear`; otherwise stale
 * announcements would bleed into other specs.
 *
 * Usage (inside the web container):
 *
 *   echo '<JSON-array>' | php seed-announcements-e2e.php
 *   php seed-announcements-e2e.php --clear
 *
 * The JSON payload is the same shape `docs/public/announcements.json`
 * uses (a list of announcement objects); see
 * `Sbpp\Announce\AnnouncementFetcher::parseEntries` for the
 * field-by-field validation.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "seed-announcements-e2e.php must run on the CLI.\n");
    exit(2);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans_e2e');
    $_ENV['DB_NAME']    = 'sourcebans_e2e';
    $_SERVER['DB_NAME'] = 'sourcebans_e2e';
}

require __DIR__ . '/../../bootstrap.php';

$cacheFile = SB_CACHE . 'announcements.json';

if (in_array('--clear', $argv, true)) {
    @unlink($cacheFile);
    fwrite(STDOUT, "cleared $cacheFile\n");
    exit(0);
}

$payload = stream_get_contents(STDIN);
if ($payload === false || trim($payload) === '') {
    fwrite(STDERR, "seed-announcements-e2e.php: empty stdin payload (use --clear to remove the cache).\n");
    exit(2);
}

$decoded = json_decode($payload, true);
if (!is_array($decoded)) {
    fwrite(STDERR, "seed-announcements-e2e.php: stdin is not a JSON array.\n");
    exit(2);
}

if (!is_dir(SB_CACHE)) {
    @mkdir(SB_CACHE, 0o775, true);
}

if (file_put_contents($cacheFile, $payload) === false) {
    fwrite(STDERR, "seed-announcements-e2e.php: failed to write $cacheFile\n");
    exit(2);
}

fwrite(STDOUT, "seeded " . count($decoded) . " announcement(s) into $cacheFile\n");
