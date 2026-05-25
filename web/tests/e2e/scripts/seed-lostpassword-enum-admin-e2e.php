<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

/**
 * E2E lostpassword form-POST seeder (#1456).
 *
 * The form-POST flow specs at the tail of
 * `web/tests/e2e/specs/flows/lostpassword-toast.spec.ts` need a
 * "known email" that exists in the admin table so the
 * `api_auth_lost_password` handler hits its match branch
 * (validate-token roll → Mail::send call → generic envelope).
 *
 * They CANNOT reuse the `admin@example.test` row the marquee
 * #1403 happy-path test seeds via
 * `seed-lostpassword-e2e.php`. Two reasons:
 *
 *   1. The marquee test seeds `admin@example.test`'s `validate`
 *      column to a known token then `GET`s a URL keyed on it.
 *      If a sibling project (chromium ⇄ mobile-chromium) is
 *      running the form-POST test concurrently, the handler's
 *      `UPDATE :prefix_admins SET validate = :validate WHERE
 *      email = :email` overwrites the seed mid-flight and the
 *      marquee test's `goto` lands on the "validation string
 *      does not match" branch — a cross-project flake the
 *      file-level `test.describe.configure({ mode: 'serial' })`
 *      does NOT mitigate (serial mode is within-project; the
 *      two projects still run in parallel by default).
 *   2. The marquee test relies on a deterministic `validate`
 *      value to drive its `?validation=…` URL; the form-POST
 *      test does not need any guarantee about the post-call
 *      value — it just needs the row to EXIST so the handler
 *      hits the match branch.
 *
 * The clean fix: a dedicated admin row whose `validate` column
 * can be freely rolled by the form-POST handler without
 * affecting any other test. Seeded idempotently via
 * `INSERT IGNORE` so re-runs are no-ops.
 *
 * The seeded user has the lowest possible privilege (no
 * `srv_group` / `web_flags` / `srv_flags`) since the form-POST
 * tests never log in as this user — they just need
 * `SELECT aid, user FROM :prefix_admins WHERE email = ?` to
 * return a row. The password is set to a SHA-256 of a random
 * value so the row can't be used to log in (defense in depth;
 * the e2e DB is dev-only and the tests run in a sandboxed
 * docker stack, but a footgun-resistant default is free).
 *
 * Usage (inside the web container):
 *
 *   php seed-lostpassword-enum-admin-e2e.php
 *   # → {"email":"lostpw-enum-known@example.test"}
 *
 * The output is JSON on stdout; consumed by
 * `seedLostpasswordEnumAdminE2e` in
 * `web/tests/e2e/fixtures/db.ts`.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "seed-lostpassword-enum-admin-e2e.php must run on the CLI.\n");
    exit(2);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans_e2e');
    $_ENV['DB_NAME']    = 'sourcebans_e2e';
    $_SERVER['DB_NAME'] = 'sourcebans_e2e';
}

if (getenv('DB_NAME') === 'sourcebans_test' || getenv('DB_NAME') === 'sourcebans') {
    fwrite(STDERR, "refusing to seed against DB_NAME=" . getenv('DB_NAME')
        . ": this script must target a dedicated e2e DB (default sourcebans_e2e).\n");
    exit(2);
}

require __DIR__ . '/../../bootstrap.php';

if (!isset($GLOBALS['PDO'])) {
    $GLOBALS['PDO'] = new \Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
}

/** @var \Database $pdo */
$pdo = $GLOBALS['PDO'];

$email = 'lostpw-enum-known@example.test';
// Unguessable random password — the row exists solely so the
// SELECT branch finds it; nobody ever logs in as this user.
$password = hash('sha256', bin2hex(random_bytes(16)));

// `INSERT IGNORE` swallows the duplicate-key error on re-runs
// so the shim is idempotent. The `users` table uses `user` as
// the unique key, so seeding the same `user` twice is a no-op.
$pdo->query(
    'INSERT IGNORE INTO `:prefix_admins` (user, authid, password, gid, email, validate, extraflags, immunity)
     VALUES (:user, :authid, :password, -1, :email, NULL, 0, 0)'
);
$pdo->bind(':user', 'lostpw-enum-known');
$pdo->bind(':authid', 'STEAM_0:0:9999999');
$pdo->bind(':password', $password);
$pdo->bind(':email', $email);
$pdo->execute();

fwrite(STDOUT, json_encode([
    'email' => $email,
], JSON_THROW_ON_ERROR) . "\n");
