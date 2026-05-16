<?php
declare(strict_types=1);

// SourceBans++ tiny DB helper for the production entrypoint (#1381).
//
// The runtime image deliberately does NOT ship `default-mysql-client`
// (HIGH-3 of the #1381 review: the spec called it out, the worker
// shipped it anyway at ~22MB on top of an already 250MB image). This
// helper covers the operations the entrypoint historically reached
// for `mysqladmin ping` and `mysql` to do, using PHP's bundled PDO
// driver — which the panel itself already requires and the runtime
// image already ships (`docker-php-ext-install pdo_mysql`).
//
// Subcommands:
//
//   ping                — open a PDO connection; exit 0 if it
//                         succeeds, non-zero otherwise. Replaces
//                         `mysqladmin ping`.
//
//   has-version-row     — return 0 if `:prefix_settings.config.version`
//                         row exists, 1 otherwise. Used as the
//                         "is the panel installed?" sentinel
//                         (MED-3 of the review; see the docblock on
//                         first_boot_install in prod-entrypoint.sh
//                         for why this beats the prior "does
//                         {prefix}_admins exist" check).
//
//   exec                — read SQL from STDIN, split on top-level
//                         `;` while respecting string literals and
//                         comments, and execute each statement via
//                         PDO. Fails loud (exit 1, stderr diagnostic)
//                         on the first error. Replaces `mysql <
//                         schema.sql`.
//
// Connection details come from env vars (DB_HOST, DB_PORT, DB_NAME,
// DB_USER, DB_PASS, DB_CHARSET) — same names the panel runtime reads
// via config.php; the entrypoint exports them eagerly for the
// lifetime of the boot loop. We don't reach for `web/init.php` or
// `web/includes/Db/Database.php` because the runtime tree may be
// mid-`rm -rf install/+updater/` and we want this helper to stay
// self-contained.

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "sb-db.php: refuses to run outside CLI (got SAPI={" . PHP_SAPI . "})\n");
    exit(2);
}

$cmd = $argv[1] ?? '';
if ($cmd === '') {
    fwrite(STDERR, "usage: sb-db.php <ping|has-version-row PREFIX|exec>\n");
    exit(2);
}

/**
 * Open a PDO connection using the entrypoint's env vars.
 *
 * `dbname=` is included in the DSN by default because the panel's
 * DB user is typically scoped to a single database (the compose
 * stack pre-creates it via mariadb's `MYSQL_DATABASE` env var; the
 * vast majority of managed-DB grants are scoped to one db). For the
 * `ping` subcommand pass `$includeDbName=false` so we only need
 * server-level auth — useful when the panel's DB user has been
 * pre-provisioned but the database is created out-of-band on
 * first boot.
 *
 * @throws \PDOException
 */
function sb_open_pdo(bool $includeDbName = true): PDO
{
    $host    = getenv('DB_HOST')    ?: 'db';
    $port    = (int) (getenv('DB_PORT') ?: '3306');
    $name    = getenv('DB_NAME')    ?: 'sourcebans';
    $user    = getenv('DB_USER')    ?: 'sourcebans';
    $pass    = (string) getenv('DB_PASS');
    $charset = getenv('DB_CHARSET') ?: 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};charset={$charset}";
    if ($includeDbName) {
        $dsn .= ";dbname={$name}";
    }

    // PHP 8.5 deprecated the bare `PDO::MYSQL_ATTR_INIT_COMMAND`
    // form in favour of the namespaced `Pdo\Mysql::ATTR_INIT_COMMAND`;
    // the panel's composer floor is PHP 8.5 so the new shape is
    // safe. Falling back to the legacy constant via `defined()` so
    // an operator running this helper standalone against an older
    // PHP image (e.g. for diagnostics) doesn't faceplant.
    $initAttr = defined('Pdo\\Mysql::ATTR_INIT_COMMAND')
        ? \Pdo\Mysql::ATTR_INIT_COMMAND
        : PDO::MYSQL_ATTR_INIT_COMMAND;

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT          => 5,
        PDO::ATTR_EMULATE_PREPARES => false,
        $initAttr                  => "SET NAMES {$charset}",
    ]);
}

switch ($cmd) {
    case 'ping':
        try {
            // No need to do anything beyond instantiate — PDO::__construct
            // performs the actual auth+connect, and EXCEPTION mode
            // throws on failure. Server-level connect (no `dbname=`)
            // so the ping succeeds on a freshly-provisioned DB user
            // before the database itself is created on first boot.
            sb_open_pdo(false);
            exit(0);
        } catch (\Throwable $e) {
            // Quiet on stderr — the entrypoint loops on this 60 times
            // and stderr-spamming each failure is pure noise. Only the
            // final "DB never came up" line from the entrypoint
            // matters for diagnosis.
            exit(1);
        }
        // unreachable
        break;

    case 'has-version-row':
        $prefix = $argv[2] ?? '';
        // Mirror entrypoint's `validate_identifiers` contract — DB_PREFIX
        // must be `[A-Za-z0-9_]+`. The entrypoint always validates
        // before reaching here, but defending the helper too means it
        // can be called standalone safely (e.g. for ops debugging).
        if ($prefix === '' || preg_match('/^[A-Za-z0-9_]+$/', $prefix) !== 1) {
            fwrite(STDERR, "sb-db.php has-version-row: PREFIX must match [A-Za-z0-9_]+\n");
            exit(2);
        }
        try {
            $pdo = sb_open_pdo();
            $stmt = $pdo->query("SELECT 1 FROM `{$prefix}_settings` WHERE setting = 'config.version' LIMIT 1");
            $row  = $stmt ? $stmt->fetch(PDO::FETCH_NUM) : false;
            exit($row ? 0 : 1);
        } catch (\Throwable $e) {
            // Two pathologies both signal "not installed":
            //   - PDOException 42S02 (table missing) — fresh DB, the
            //     schema bootstrap hasn't run yet.
            //   - Anything else (auth, network, etc.) — we want the
            //     entrypoint to handle it via the regular failure
            //     path, not silently skip the install. Signal "not
            //     present" so the entrypoint's wait_for_db /
            //     first_boot_install pair handles the underlying
            //     failure with the right surface.
            exit(1);
        }
        // unreachable
        break;

    case 'exec':
        $sql = stream_get_contents(STDIN);
        if ($sql === false || trim($sql) === '') {
            fwrite(STDERR, "sb-db.php exec: empty STDIN — refusing to no-op silently\n");
            exit(2);
        }
        try {
            $pdo = sb_open_pdo();
        } catch (\Throwable $e) {
            fwrite(STDERR, 'sb-db.php exec: PDO connect failed: ' . $e->getMessage() . "\n");
            exit(1);
        }
        $statements = sb_split_sql($sql);
        if ($statements === []) {
            fwrite(STDERR, "sb-db.php exec: STDIN contained no executable statements\n");
            exit(2);
        }
        foreach ($statements as $i => $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (\Throwable $e) {
                $preview = preg_replace('/\s+/', ' ', $stmt) ?? $stmt;
                if (strlen($preview) > 160) {
                    $preview = substr($preview, 0, 157) . '...';
                }
                fwrite(STDERR, sprintf(
                    "sb-db.php exec: statement #%d failed: %s\n  -> %s\n",
                    $i + 1,
                    $e->getMessage(),
                    $preview
                ));
                exit(1);
            }
        }
        exit(0);
        // unreachable
        break;

    default:
        fwrite(STDERR, "sb-db.php: unknown command '{$cmd}' (expected: ping | has-version-row | exec)\n");
        exit(2);
}

/**
 * Split a SQL script on top-level `;` while respecting single-quoted,
 * double-quoted, and backtick-quoted strings (including `\\`-escaped
 * quote-in-string), MySQL `--`-to-EOL line comments, MySQL `#`-to-EOL
 * line comments, and `/* … *\/` block comments. The output is a list
 * of trimmed, non-empty statements ready for `PDO::exec()`.
 *
 * Conservative parser; not a full SQL grammar. The schema files we
 * ship (`install/includes/sql/struc.sql`, `install/includes/sql/data.sql`)
 * are well-formed, but values inside string literals can contain
 * `;` and the naive `explode(';', $sql)` would split mid-literal
 * (the install wizard's page.5.php uses `explode(';', $sql)` against
 * the seed data.sql today and gets away with it because data.sql
 * happens not to contain `;` inside any string literal — fragile).
 * This parser is the load-bearing replacement for the previous
 * `mysql < schema.sql` shape, so it has to be right.
 *
 * @return list<string>
 */
function sb_split_sql(string $sql): array
{
    $out = [];
    $buf = '';
    $n   = strlen($sql);
    $i   = 0;
    /** @var null|string $inStr   The active string-delimiter, or null. */
    $inStr = null;
    while ($i < $n) {
        $ch = $sql[$i];
        if ($inStr !== null) {
            $buf .= $ch;
            // `\` inside a string escapes the next character — including
            // the delimiter. SQL passwords with `\'` survive this.
            if ($ch === '\\' && $i + 1 < $n) {
                $buf .= $sql[$i + 1];
                $i += 2;
                continue;
            }
            if ($ch === $inStr) {
                $inStr = null;
            }
            $i++;
            continue;
        }
        // Line comment `-- …\n`. MySQL requires whitespace after `--`
        // but we accept the bare-`--` form too for robustness.
        if ($ch === '-' && $i + 1 < $n && $sql[$i + 1] === '-') {
            while ($i < $n && $sql[$i] !== "\n") {
                $buf .= $sql[$i++];
            }
            continue;
        }
        // `#` line comment (MySQL extension).
        if ($ch === '#') {
            while ($i < $n && $sql[$i] !== "\n") {
                $buf .= $sql[$i++];
            }
            continue;
        }
        // Block comment `/* … */`. Standard SQL; doesn't nest.
        if ($ch === '/' && $i + 1 < $n && $sql[$i + 1] === '*') {
            $buf .= '/*';
            $i += 2;
            while ($i + 1 < $n && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                $buf .= $sql[$i++];
            }
            if ($i + 1 < $n) {
                $buf .= '*/';
                $i += 2;
            }
            continue;
        }
        if ($ch === '"' || $ch === "'" || $ch === '`') {
            $inStr = $ch;
            $buf  .= $ch;
            $i++;
            continue;
        }
        if ($ch === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') {
                $out[] = $stmt;
            }
            $buf = '';
            $i++;
            continue;
        }
        $buf .= $ch;
        $i++;
    }
    $tail = trim($buf);
    if ($tail !== '') {
        $out[] = $tail;
    }
    return $out;
}
