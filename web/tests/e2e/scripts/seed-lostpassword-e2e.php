<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

/**
 * E2E lostpassword + mailer seeder.
 *
 * The happy-path "your password has been reset and emailed" branch
 * in `web/pages/page.lostpassword.php` needs three preconditions
 * the default e2e fixture does not provide:
 *
 *   1. SMTP settings pointing at the dev stack's mailpit container.
 *      Default `data.sql` ships `smtp.host` / `smtp.user` /
 *      `smtp.pass` as blank strings, which causes `Mailer::create()`
 *      to return `null` and `Mail::send` to short-circuit to false —
 *      the page handler then branches into the "Could not send the
 *      new password" error toast instead of the success toast.
 *   2. A non-empty `config.mail.from_email`. Without this the From
 *      header is blank and Symfony's mailer rejects the message at
 *      send time. The `SB_EMAIL` constant fallback exists but isn't
 *      defined in the e2e wrapper either, so we seed the explicit
 *      config knob.
 *   3. A known `:prefix_admins.validate` token on the seeded admin
 *      row. The page handler's success branch requires
 *      `email = :email AND validate = :validate` to match a row;
 *      `Fixture::seedAdmin` leaves `validate` NULL by default.
 *
 * The shim writes all three idempotently and outputs the token +
 * email as JSON so the spec can drive the request with the right
 * URL parameters. Mailpit cleanup (clearing the inbox between
 * tests) happens spec-side via Playwright's `request` API hitting
 * the mailpit HTTP API directly.
 *
 * Mailpit hostname: this script writes `smtp.host = 'mailpit'` (the
 * docker-compose service alias) because the panel under test runs
 * inside the web container where mailpit is reachable as
 * `mailpit:1025`. The default port `1025` matches the parent
 * `docker-compose.yml`'s mailpit service. The worktree-local
 * `docker-compose.override.yml` only renames containers and remaps
 * host-published ports; the internal service alias + port stay
 * `mailpit:1025`.
 *
 * Usage (inside the web container):
 *
 *   php seed-lostpassword-e2e.php
 *   # → {"email":"admin@example.test","token":"<16-hex-chars>"}
 *
 * The output is JSON on stdout; consumed by `seedLostpasswordE2e`
 * in `web/tests/e2e/fixtures/db.ts`.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "seed-lostpassword-e2e.php must run on the CLI.\n");
    exit(2);
}

if (!getenv('DB_NAME')) {
    putenv('DB_NAME=sourcebans_e2e');
    $_ENV['DB_NAME']    = 'sourcebans_e2e';
    $_SERVER['DB_NAME'] = 'sourcebans_e2e';
}

if (getenv('DB_NAME') === 'sourcebans_test' || getenv('DB_NAME') === 'sourcebans') {
    fwrite(STDERR, "refusing to seed lostpassword against DB_NAME=" . getenv('DB_NAME')
        . ": this script must target a dedicated e2e DB (default sourcebans_e2e).\n");
    exit(2);
}

require __DIR__ . '/../../bootstrap.php';

// Open a PDO connection against the existing e2e DB without
// touching the schema. Mirrors `seed-comms-e2e.php` — bootstrap.php
// loads the autoloader + globals but does not establish the
// PDO connection by itself.
if (!isset($GLOBALS['PDO'])) {
    $GLOBALS['PDO'] = new \Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
}

/** @var \Database $pdo */
$pdo = $GLOBALS['PDO'];

// Mailpit accepts AUTH PLAIN/LOGIN with any credentials by default
// (it's a dev tool — there's no configured auth backend). We pass
// `e2e` / `e2e` because Symfony's mailer DSN parser requires a
// user:pass pair when the URL carries `@` (and `Mailer::create`
// refuses to build the mailer at all if `smtp.user` / `smtp.pass`
// are blank — see `Mailer::create()` line 107).
$settings = [
    'smtp.host'              => 'mailpit',
    'smtp.port'              => '1025',
    'smtp.user'              => 'e2e',
    'smtp.pass'              => 'e2e',
    'smtp.verify_peer'       => '0',
    'config.mail.from_email' => 'noreply@example.test',
    'config.mail.from_name'  => 'SourceBans++ E2E',
];
foreach ($settings as $key => $value) {
    $pdo->query('UPDATE `:prefix_settings` SET value = :value WHERE setting = :setting');
    $pdo->bind(':value', $value);
    $pdo->bind(':setting', $key);
    $pdo->execute();
}

// Use a 16-char hex token — the page handler's length>=10 guard
// (line 53) accepts anything 10+ chars, but the column is
// `varchar(255)` and the legacy `Mail::send(PasswordReset)` flow
// rolls 16-char tokens. Match that shape so the seed reads as
// realistic.
$token = bin2hex(random_bytes(8));
$email = 'admin@example.test';

$pdo->query('UPDATE `:prefix_admins` SET validate = :validate WHERE email = :email');
$pdo->bind(':validate', $token);
$pdo->bind(':email', $email);
$pdo->execute();

$rowCount = (int) $pdo->rowCount();
if ($rowCount === 0) {
    fwrite(STDERR, "seed-lostpassword-e2e.php: no admin row matched email=$email — fixture drift.\n");
    exit(2);
}

fwrite(STDOUT, json_encode([
    'email' => $email,
    'token' => $token,
], JSON_THROW_ON_ERROR) . "\n");
