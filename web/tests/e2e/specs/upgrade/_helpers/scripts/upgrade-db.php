<?php
/**
 * Upgrade-harness DB lifecycle helper (#1269).
 *
 * Multi-command CLI driver for the upgrade-spec sidecar databases. Runs
 * inside the web container, called by the TypeScript helpers under
 * `web/tests/e2e/specs/upgrade/_helpers/`. Talks to the dev MariaDB
 * service as `root` (mirrors `./sbpp.sh e2e`'s grant pattern) so we can
 * drop / create the throwaway upgrade-test schemas without touching
 * the panel-user grants in `docker/db-init/`.
 *
 * The harness is dev-only: every command requires the target DB name to
 * begin with `sourcebans_upgrade_`. Refusal guard mirrors the dev
 * synthesizer's "refuse anything but `sourcebans`" stance — same
 * reasoning, different scope.
 *
 * Subcommands:
 *
 *   reset-upgrade-db <db> <fixture-path>
 *     Drop + re-create <db>, then load the gunzipped 1.x fixture from
 *     <fixture-path> into it. Idempotent — safe to call between specs.
 *
 *   install-fresh <db>
 *     Drop + re-create <db>, render web/install/includes/sql/struc.sql
 *     and data.sql against it the same way Sbpp\Tests\Fixture does for
 *     the PHPUnit `sourcebans_test` DB. Used as the reference shape the
 *     post-upgrade schema is compared against.
 *
 *   dump-schema <db>
 *     Emit a normalized JSON dump of the schema (tables, columns,
 *     indexes) on stdout. Sorted by table → column → index name so
 *     two snapshots taken against equivalent schemas hash-match
 *     regardless of InnoDB's internal column / index ordering.
 *
 *   dump-settings <db>
 *     Emit a JSON map of `:prefix_settings` (setting → value) on stdout,
 *     sorted by setting name.
 *
 *   render-config <config-path> <db-name>
 *     Rewrite the panel's config.php at <config-path> to point at <db>
 *     and DROP any `SB_SECRET_KEY` line so /upgrade.php exercises the
 *     append-the-key branch on the next request. Used by the spec's
 *     beforeEach to put the panel into "fresh-1.x-install" mode.
 *
 *   restore-config <config-path> <stash-path>
 *     Restore the panel's config.php from a stashed copy. Trap-friendly
 *     — works even if the stash already matches the live file.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "upgrade-db.php must run on the CLI.\n");
    exit(2);
}

const DB_NAME_PREFIX = 'sourcebans_upgrade_';

$cmd = $argv[1] ?? '';
$args = array_slice($argv, 2);

try {
    switch ($cmd) {
        case 'reset-upgrade-db':
            cmdResetUpgradeDb($args[0] ?? '', $args[1] ?? '');
            break;
        case 'install-fresh':
            cmdInstallFresh($args[0] ?? '');
            break;
        case 'dump-schema':
            cmdDumpSchema($args[0] ?? '');
            break;
        case 'dump-settings':
            cmdDumpSettings($args[0] ?? '');
            break;
        case 'render-config':
            cmdRenderConfig($args[0] ?? '', $args[1] ?? '');
            break;
        case 'restore-config':
            cmdRestoreConfig($args[0] ?? '', $args[1] ?? '');
            break;
        default:
            throw new InvalidArgumentException(
                "unknown subcommand '$cmd'. Run with one of: reset-upgrade-db, install-fresh, dump-schema, dump-settings, render-config, restore-config."
            );
    }
} catch (Throwable $e) {
    fwrite(STDERR, "upgrade-db.php $cmd failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}

// ----------------------------------------------------------------------------
// Subcommand bodies.

function cmdResetUpgradeDb(string $db, string $fixturePath): void
{
    requireUpgradeDb($db);
    if ($fixturePath === '' || !is_file($fixturePath)) {
        throw new RuntimeException("fixture not found at '$fixturePath'");
    }

    $root = rootPdo();
    $root->exec("DROP DATABASE IF EXISTS `$db`");
    $root->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4");
    grantPanelUser($root, $db);

    // Load the gunzip'd .sql.gz dump. PHP's mysql/PDO interface can't
    // execute multi-statement SQL via a single `exec()`, so we shell
    // out to the bundled mysql client (faster than splitting in PHP).
    // The fixture dump uses `INSERT INTO ... VALUES (...),(...);`
    // extended-insert syntax which both PDO and the mysql client
    // accept.
    // --skip-ssl: the bundled mariadb client (11.x in the dev image)
    // negotiates TLS by default; the dev `db` service ships the
    // upstream `mariadb:10.11` image which doesn't have TLS configured,
    // so the handshake fails with "SSL is required, but the server
    // does not support it". Disabling SSL on the client side is the
    // documented workaround for plain-text dev stacks.
    $cmd = sprintf(
        'gunzip -c %s | mariadb --skip-ssl -h %s -P %d -u root -proot %s 2>&1',
        escapeshellarg($fixturePath),
        escapeshellarg('db'),
        3306,
        escapeshellarg($db)
    );
    $output = [];
    $rc = 0;
    exec($cmd, $output, $rc);
    if ($rc !== 0) {
        throw new RuntimeException("fixture load failed (rc=$rc): " . implode("\n", $output));
    }

    fwrite(STDOUT, "loaded $fixturePath into $db\n");
}

function cmdInstallFresh(string $db): void
{
    requireUpgradeDb($db);

    $root = rootPdo();
    $root->exec("DROP DATABASE IF EXISTS `$db`");
    $root->exec("CREATE DATABASE `$db` CHARACTER SET utf8mb4");
    grantPanelUser($root, $db);

    $pdo = new PDO("mysql:host=db;port=3306;dbname=$db;charset=utf8mb4", 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $strucPath = '/var/www/html/web/install/includes/sql/struc.sql';
    $dataPath  = '/var/www/html/web/install/includes/sql/data.sql';

    foreach ([$strucPath, $dataPath] as $sqlFile) {
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new RuntimeException("cannot read $sqlFile");
        }
        $sql = strtr($sql, ['{prefix}' => 'sb', '{charset}' => 'utf8mb4']);
        executeBatch($pdo, $sql);
    }

    fwrite(STDOUT, "installed fresh struc+data into $db\n");
}

function cmdDumpSchema(string $db): void
{
    // Reads only, but a typo (`dump-schema sourcebans`) would silently
    // dump the dev DB and produce a misleading parity diff. The
    // refusal guard mirrors the destructive commands' shape so any
    // dump path is also `sourcebans_upgrade_*`-only.
    requireUpgradeDb($db);

    $pdo = new PDO("mysql:host=db;port=3306;dbname=$db;charset=utf8mb4", 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $tables = $pdo->query(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = " . $pdo->quote($db) . "
         ORDER BY TABLE_NAME"
    )->fetchAll(PDO::FETCH_COLUMN);

    $schema = [];
    foreach ($tables as $tbl) {
        $columns = $pdo->query(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
                    EXTRA, CHARACTER_SET_NAME, COLLATION_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = " . $pdo->quote($db) . "
             AND TABLE_NAME = " . $pdo->quote($tbl) . "
             ORDER BY COLUMN_NAME"
        )->fetchAll();

        // Indexes — group by INDEX_NAME, sorted by SEQ_IN_INDEX so a
        // composite index doesn't collapse into the wrong column order.
        $indexRows = $pdo->query(
            "SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE, INDEX_TYPE, SEQ_IN_INDEX
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = " . $pdo->quote($db) . "
             AND TABLE_NAME = " . $pdo->quote($tbl) . "
             ORDER BY INDEX_NAME, SEQ_IN_INDEX"
        )->fetchAll();
        $indexes = [];
        foreach ($indexRows as $r) {
            $name = $r['INDEX_NAME'];
            if (!isset($indexes[$name])) {
                $indexes[$name] = [
                    'name'     => $name,
                    'unique'   => (int) $r['NON_UNIQUE'] === 0,
                    'type'     => $r['INDEX_TYPE'],
                    'columns'  => [],
                ];
            }
            $indexes[$name]['columns'][] = $r['COLUMN_NAME'];
        }
        ksort($indexes);

        // Pick out engine + default charset on the table itself so
        // utf8 vs utf8mb4 drift surfaces in the diff.
        $tblMeta = $pdo->query(
            "SELECT ENGINE, TABLE_COLLATION
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = " . $pdo->quote($db) . "
             AND TABLE_NAME = " . $pdo->quote($tbl)
        )->fetch();

        $schema[$tbl] = [
            'engine'    => $tblMeta['ENGINE'] ?? null,
            'collation' => $tblMeta['TABLE_COLLATION'] ?? null,
            'columns'   => $columns,
            'indexes'   => array_values($indexes),
        ];
    }

    // JSON_PRETTY_PRINT for a stable line-by-line diff. Sort table keys
    // already done above (ORDER BY TABLE_NAME); JSON_UNESCAPED_SLASHES
    // keeps SteamID-like values readable in the diff output.
    fwrite(STDOUT, json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}

function cmdDumpSettings(string $db): void
{
    // Same refusal-guard reasoning as cmdDumpSchema: a typo
    // (`dump-settings sourcebans`) would silently read the dev DB
    // and produce a misleading parity comparison.
    requireUpgradeDb($db);

    $pdo = new PDO("mysql:host=db;port=3306;dbname=$db;charset=utf8mb4", 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Hardcoded `sb_settings` (rather than threading {prefix} → sb
    // through this command like cmdInstallFresh does for struc/data).
    // All current upgrade fixtures — `fixtures/upgrade/1.7.0.sql.gz`,
    // `1.8.4.sql.gz`, plus the dev seed — ship with `DB_PREFIX=sb`,
    // and capture.sh hardcodes the same prefix when dumping. If a
    // future fixture ever ships with a non-default `DB_PREFIX`, lift
    // the literal to a `--prefix=` arg (or honour the env var the
    // wrapper already passes through) and update cmdInstallFresh's
    // `{prefix}` substitution to match.
    $rows = $pdo->query("SELECT setting, value FROM `sb_settings` ORDER BY setting")->fetchAll();
    $map = [];
    foreach ($rows as $r) {
        $map[$r['setting']] = $r['value'];
    }
    fwrite(STDOUT, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}

function cmdRenderConfig(string $configPath, string $dbName): void
{
    requireUpgradeDb($dbName);
    if ($configPath === '' || !is_file($configPath)) {
        throw new RuntimeException("config.php not found at '$configPath'");
    }

    $content = file_get_contents($configPath);
    if ($content === false) {
        throw new RuntimeException("cannot read $configPath");
    }

    // Swap DB_NAME to the upgrade DB. Build the pattern from a quote
    // class so embedded single + double quotes don't have to fight
    // PHP's single-quoted-string escape rules.
    $q = '[' . "'" . '"' . ']';
    $notq = '[^' . "'" . '"' . ']';
    $dbNamePattern = '/define\s*\(\s*' . $q . 'DB_NAME' . $q . '\s*,\s*' . $q . $notq . '*' . $q . '\s*\)\s*;/';
    $content = preg_replace(
        $dbNamePattern,
        sprintf("define('DB_NAME', %s);", var_export($dbName, true)),
        $content,
        1,
        $count
    );
    if ($count === 0) {
        throw new RuntimeException("config.php at $configPath has no DB_NAME line to swap");
    }

    // Drop SB_SECRET_KEY entirely so /upgrade.php hits the
    // append-the-key branch (the script "exit"s if the constant is
    // already defined — see web/upgrade.php). The trailing PHP close
    // tag (if present) survives the strip; the entrypoint-rendered
    // config never emits one.
    $secretKeyPattern = '/^\s*define\s*\(\s*' . $q . 'SB_SECRET_KEY' . $q . '\s*,\s*' . $q . $notq . '*' . $q . '\s*\)\s*;.*?\R?/m';
    $content = preg_replace($secretKeyPattern, '', $content);

    if (file_put_contents($configPath, $content) === false) {
        throw new RuntimeException("cannot write $configPath");
    }
    // Make config.php writable by Apache (www-data, uid 33). The
    // bind-mounted file is owned by the host user (root inside the
    // container, since `docker compose cp` ran as the daemon's
    // user); /upgrade.php's append-the-key branch only fires if
    // is_writable() returns true. Without this chmod the upgrade
    // spec would only ever exercise the "config.php is read-only"
    // fallback branch — useful coverage, but not the dominant
    // operator path the issue calls out.
    //
    // Dev-container-only: this code path runs under `./sbpp.sh
    // upgrade-e2e` against the throwaway bind-mounted config.php in
    // the dev container; production never reaches this script (the
    // refusal guard above gates the whole command on a
    // `sourcebans_upgrade_*` DB name).
    @chmod($configPath, 0666);

    // KNOWN ISSUE (#1269 follow-up candidate): Smarty's default
    // compile dir is `./templates_c/` relative to CWD. Apache's CWD
    // for `/updater/index.php` is `/var/www/html/web/updater/`, so
    // the updater needs `web/updater/templates_c/` to exist; init.php
    // never sets a fixed compile dir. The dev panel only ever runs
    // from `/var/www/html/web/index.php` (CWD=web/) so the gap
    // doesn't surface in the regular dev loop. We create the dir
    // defensively so this slice can drive the migrator end-to-end;
    // the right fix is `$theme->setCompileDir(SB_CACHE)` in init.php
    // (or equivalent) and lives in its own PR per the issue's
    // "small, sequential PRs" rule.
    $updaterTplC = dirname($configPath) . '/updater/templates_c';
    if (!is_dir($updaterTplC)) {
        @mkdir($updaterTplC, 0777, true);
    }
    @chmod($updaterTplC, 0777);

    fwrite(STDOUT, "rendered $configPath -> DB_NAME=$dbName, SB_SECRET_KEY removed\n");
}

function cmdRestoreConfig(string $configPath, string $stashPath): void
{
    if ($configPath === '' || $stashPath === '') {
        throw new RuntimeException('restore-config: <config-path> <stash-path> required');
    }
    if (!is_file($stashPath)) {
        // Idempotent: nothing to restore.
        fwrite(STDOUT, "no stash at $stashPath; nothing to restore\n");
        return;
    }
    $content = file_get_contents($stashPath);
    if ($content === false) {
        throw new RuntimeException("cannot read stash $stashPath");
    }
    if (file_put_contents($configPath, $content) === false) {
        throw new RuntimeException("cannot write $configPath");
    }
    fwrite(STDOUT, "restored $configPath from $stashPath\n");
}

// ----------------------------------------------------------------------------
// Helpers.

function requireDbName(string $db): void
{
    if ($db === '') {
        throw new RuntimeException('DB name required');
    }
}

function requireUpgradeDb(string $db): void
{
    requireDbName($db);
    if (!str_starts_with($db, DB_NAME_PREFIX)) {
        // The harness is dev-only and operates exclusively on
        // throwaway DBs. A typo or stray env var must NOT be allowed
        // to drop the dev `sourcebans` DB or the e2e/test DBs.
        throw new RuntimeException(
            "refusing to operate on '$db': DB name must begin with '" . DB_NAME_PREFIX . "'."
        );
    }
}

function rootPdo(): PDO
{
    return new PDO('mysql:host=db;port=3306;charset=utf8mb4', 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

function grantPanelUser(PDO $root, string $db): void
{
    // Mirror the GRANT pattern `./sbpp.sh e2e` does for the
    // `sourcebans_e2e` schema. The dev `sourcebans` user only has
    // privileges on `sourcebans` + the explicitly-granted test/e2e
    // DBs by default — without this grant, the panel (running as
    // `sourcebans@%`) hits `1142 SELECT command denied` the first
    // time it talks to a freshly-created upgrade schema.
    $root->exec("GRANT ALL PRIVILEGES ON `$db`.* TO 'sourcebans'@'%'");
    $root->exec('FLUSH PRIVILEGES');
}

function executeBatch(PDO $pdo, string $sql): void
{
    // Mirrors Sbpp\Tests\Fixture::executeBatch — same naive splitter
    // (vanilla DDL, no procedure bodies) so the upgrade harness sees
    // exactly what PHPUnit / Playwright see when the same SQL files
    // get loaded for the regular `sourcebans_test` / `sourcebans_e2e`
    // schemas. Don't tighten the splitter without updating Fixture
    // too — diverging here breaks the parity invariant the harness
    // is built to assert.
    $stmts = preg_split('/;\s*\n/', $sql) ?: [];
    foreach ($stmts as $stmt) {
        $stmt = preg_replace('/^(?:\s*--[^\n]*\n)+/', '', $stmt) ?? $stmt;
        $stmt = trim($stmt);
        if ($stmt === '' || str_starts_with($stmt, '--') || str_starts_with($stmt, '/*')) {
            continue;
        }
        $pdo->exec($stmt);
    }
}
