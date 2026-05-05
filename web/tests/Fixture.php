<?php

namespace Sbpp\Tests;

/**
 * Renders the install/sql templates into the configured test DB and seeds
 * the same admin row docker/db-init does. Idempotent: install() drops and
 * recreates everything; reset() truncates between tests so each case
 * starts identical to a fresh `./sbpp.sh up`.
 */
class Fixture
{
    private static bool $installed = false;
    private static int $adminAid   = 0;

    public static function install(): void
    {
        if (self::$installed) {
            return;
        }

        // Connect without a database so we can drop + recreate it.
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
        try {
            $pdo = new \PDO($dsn, DB_USER, DB_PASS, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                "Cannot connect to test DB at {$dsn}: " . $e->getMessage()
                . "\nSet DB_HOST/DB_PORT/DB_USER/DB_PASS/DB_NAME env vars or run via ./sbpp.sh test."
            );
        }

        $pdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', DB_NAME));
        $pdo->exec(sprintf('CREATE DATABASE `%s` CHARACTER SET %s', DB_NAME, DB_CHARSET));
        $pdo->exec(sprintf('USE `%s`', DB_NAME));

        $struc = self::renderSql(ROOT . 'install/includes/sql/struc.sql');
        $data  = self::renderSql(ROOT . 'install/includes/sql/data.sql');

        self::executeBatch($pdo, $struc);
        self::executeBatch($pdo, $data);
        self::seedAdmin($pdo);

        // Wire the global PDO/Database wrapper the handlers rely on.
        $GLOBALS['PDO'] = new \Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);

        \Config::init($GLOBALS['PDO']);
        \SteamID\SteamID::init($GLOBALS['PDO']);
        \Auth::init($GLOBALS['PDO']);
        \Log::init($GLOBALS['PDO'], new \CUserManager(null));

        \Api::bootstrap();
        self::$installed = true;
    }

    public static function reset(): void
    {
        self::install();
        self::truncateAndReseed();
    }

    /**
     * Truncate every table in the configured DB and re-seed defaults
     * + the admin row, WITHOUT going through install()'s DROP DATABASE +
     * CREATE DATABASE cycle.
     *
     * Used by the e2e DB shim (`web/tests/e2e/scripts/reset-e2e-db.php`),
     * which is invoked from a fresh PHP process per call. install()'s
     * static `$installed` flag is process-local, so calling reset()
     * from a fresh process (the shim does this every time) would try
     * to DROP+CREATE on every invocation and race with parallel
     * Playwright workers on the same DB. truncateOnly() opens a single
     * connection, truncates, re-seeds, and is safe to call multiple
     * times within one process.
     *
     * Caller responsibility: the schema must already exist (i.e.
     * Fixture::install() was run earlier — the e2e harness does this
     * once in `fixtures/global-setup.ts`).
     */
    public static function truncateOnly(): void
    {
        if (!isset($GLOBALS['PDO'])) {
            $GLOBALS['PDO'] = new \Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX, DB_CHARSET);
        }
        self::truncateAndReseed();
    }

    private static function truncateAndReseed(): void
    {
        $pdo = self::rawPdo();
        $tables = $pdo->query(
            sprintf("SELECT table_name FROM information_schema.tables WHERE table_schema = '%s'", DB_NAME)
        )->fetchAll(\PDO::FETCH_COLUMN);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $t) {
            $pdo->exec("TRUNCATE TABLE `$t`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        // Re-seed the rows data.sql provides (settings, mods, ...) so
        // tests that read Config (auth.maxlife, config.enablesteamlogin,
        // ...) see the same defaults as a freshly-installed panel.
        $data = self::renderSql(ROOT . 'install/includes/sql/data.sql');
        self::executeBatch($pdo, $data);

        // And the admin row.
        self::seedAdmin($pdo);

        // Settings cache lives in Config's static array; re-read so
        // post-truncate tests see the re-seeded values.
        \Config::init($GLOBALS['PDO']);
    }

    public static function rawPdo(): \PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        return new \PDO($dsn, DB_USER, DB_PASS, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    }

    public static function adminAid(): int { return self::$adminAid; }

    private static function renderSql(string $path): string
    {
        $sql = (string)@file_get_contents($path);
        if ($sql === '') {
            throw new \RuntimeException("Cannot read $path");
        }
        return strtr($sql, ['{prefix}' => DB_PREFIX, '{charset}' => DB_CHARSET]);
    }

    private static function executeBatch(\PDO $pdo, string $sql): void
    {
        // Naive splitter: SQL files in this repo are vanilla DDL with no
        // procedure bodies, so splitting on `;` followed by newline is safe.
        $stmts = preg_split('/;\s*\n/', $sql) ?: [];
        foreach ($stmts as $stmt) {
            // Strip any leading `-- …` line comments + blank lines. The
            // splitter groups a documentation block with the following
            // statement when no `;\n` separates them (e.g. the comment
            // above `:prefix_notes` in struc.sql). Without this strip,
            // `str_starts_with($stmt, '--')` below would skip the whole
            // chunk — table and all — and the e2e/PHPUnit DB would silently
            // be missing the table whose comment we wrote.
            $stmt = preg_replace('/^(?:\s*--[^\n]*\n)+/', '', $stmt) ?? $stmt;
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) continue;
            $pdo->exec($stmt);
        }
    }

    private static function seedAdmin(\PDO $pdo): void
    {
        $hash = password_hash('admin', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, validate, extraflags, immunity)
             VALUES (?, ?, ?, -1, ?, NULL, ?, 100)', DB_PREFIX
        ));
        $stmt->execute(['admin', 'STEAM_0:0:0', $hash, 'admin@example.test', 16777216]);
        self::$adminAid = (int)$pdo->lastInsertId();
    }
}
