<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

declare(strict_types=1);

namespace Sbpp\Migrator;

use PDO;
use Throwable;

/**
 * Argument parsing + pretty-printing for the upgrade CLI. Lives in the
 * Migrator namespace so the autoloader picks it up; the actual entry
 * point is `web/bin/upgrade.php`, which is a one-liner that delegates to
 * {@see CliRunner::main()}.
 *
 * The runner deliberately doesn't include the panel's `init.php`. That
 * file starts a session, builds Smarty, opens GeoIP, etc. — none of
 * which the migrator needs and most of which would fail in a
 * non-installed environment. We load `config.php` directly (after
 * defining the `IN_SB` sentinel it guards on) and connect a bare PDO.
 */
final class CliRunner
{
    private const EXIT_OK         = 0;
    private const EXIT_APPLY_FAIL = 1;
    private const EXIT_INVOCATION = 2;

    /** @param list<string> $argv */
    public static function main(array $argv): int
    {
        $opts = self::parseArgv($argv);

        if ($opts['help']) {
            self::printUsage();
            return self::EXIT_OK;
        }

        if ($opts['error'] !== null) {
            fwrite(STDERR, "upgrade.php: " . $opts['error'] . "\n\n");
            self::printUsage(STDERR);
            return self::EXIT_INVOCATION;
        }

        $webRoot = realpath(__DIR__ . '/../..');
        if ($webRoot === false) {
            fwrite(STDERR, "upgrade.php: cannot resolve web/ root from " . __DIR__ . "\n");
            return self::EXIT_INVOCATION;
        }

        $cfg = self::loadConfig($webRoot);
        if ($cfg === null) {
            return self::EXIT_INVOCATION;
        }

        try {
            $pdo = self::connect($cfg);
        } catch (Throwable $e) {
            fwrite(STDERR, "upgrade.php: cannot connect to MariaDB: " . $e->getMessage() . "\n");
            return self::EXIT_INVOCATION;
        }

        try {
            $migrator = Migrator::fromInstallSql($webRoot, $cfg['prefix'], $cfg['charset']);
            $plan     = $migrator->plan($pdo);
        } catch (Throwable $e) {
            fwrite(STDERR, "upgrade.php: failed to compute plan: " . $e->getMessage() . "\n");
            return self::EXIT_INVOCATION;
        }

        $writer = new CliPrinter($opts['color'], STDOUT);
        $writer->printPlan($plan);

        if (!$opts['apply']) {
            if ($plan->isEmpty()) {
                $writer->infoLine('Nothing to do.');
            } else {
                $writer->infoLine(
                    sprintf('Run with --apply to execute the %d change(s) above.', $plan->totalChanges()),
                );
            }
            return self::EXIT_OK;
        }

        if ($plan->isEmpty()) {
            $writer->infoLine('Nothing to do.');
            return self::EXIT_OK;
        }

        $applier = new MigrationApplier($cfg['prefix']);
        $result  = $applier->apply($pdo, $plan);

        $writer->printResult($result);

        return $result->success ? self::EXIT_OK : self::EXIT_APPLY_FAIL;
    }

    /**
     * @param list<string> $argv
     * @return array{apply: bool, color: bool, help: bool, error: ?string}
     */
    private static function parseArgv(array $argv): array
    {
        $opts = ['apply' => false, 'color' => true, 'help' => false, 'error' => null];

        $args = array_slice($argv, 1);

        // Auto-detect non-TTY stdout so piping the output to a file
        // doesn't carry escape codes. Operators can still force colour
        // off explicitly with --no-color.
        if (function_exists('posix_isatty') && !@posix_isatty(STDOUT)) {
            $opts['color'] = false;
        } elseif (getenv('NO_COLOR') !== false) {
            $opts['color'] = false;
        }

        foreach ($args as $a) {
            switch ($a) {
                case '--apply':
                    $opts['apply'] = true;
                    break;
                case '--no-color':
                    $opts['color'] = false;
                    break;
                case '--color':
                    $opts['color'] = true;
                    break;
                case '-h':
                case '--help':
                    $opts['help'] = true;
                    break;
                default:
                    $opts['error'] = "unknown argument '$a'";
                    return $opts;
            }
        }

        return $opts;
    }

    /**
     * @param resource $stream
     */
    private static function printUsage($stream = STDOUT): void
    {
        $msg = <<<TXT
Usage: php web/bin/upgrade.php [options]

Diffs the live database against web/install/includes/sql/struc.sql + data.sql
and either prints what it would change (default) or applies the changes.

Options:
  --apply       Run the missing CREATE TABLE / ADD COLUMN / INSERT IGNORE
                statements. Without this flag the tool only prints the diff.
  --no-color    Suppress ANSI colour codes (auto-disabled on non-TTY).
  --color       Force ANSI colour codes even when stdout isn't a TTY.
  -h, --help    Show this help message.

Exit codes:
  0  success (nothing to do, or apply completed)
  1  apply failed (a step threw — error printed to stderr)
  2  invalid invocation, missing config, or unreadable SQL file


TXT;
        fwrite($stream, $msg);
    }

    /**
     * Resolve DB connection parameters. Environment variables win so the
     * test harness can point the migrator at `sourcebans_test` without
     * touching `web/config.php`. Falls back to including `config.php`
     * (the production path).
     *
     * @return array{host:string, port:int, name:string, user:string, password:string, prefix:string, charset:string}|null
     */
    private static function loadConfig(string $webRoot): ?array
    {
        $envHost = getenv('DB_HOST');
        if ($envHost !== false && $envHost !== '') {
            return [
                'host'     => $envHost,
                'port'     => (int) (getenv('DB_PORT') ?: 3306),
                'name'     => (string) (getenv('DB_NAME') ?: 'sourcebans'),
                'user'     => (string) (getenv('DB_USER') ?: 'sourcebans'),
                'password' => (string) (getenv('DB_PASS') ?: ''),
                'prefix'   => (string) (getenv('DB_PREFIX') ?: 'sb'),
                'charset'  => (string) (getenv('DB_CHARSET') ?: 'utf8mb4'),
            ];
        }

        $configPath = $webRoot . '/config.php';
        if (!is_file($configPath)) {
            fwrite(STDERR,
                "upgrade.php: $configPath not found.\n"
                . "Either install SourceBans++ first, or pass DB_HOST/DB_NAME/etc. via env.\n",
            );
            return null;
        }

        // config.php guards on `IN_SB`; we satisfy that without dragging
        // in init.php (which would also start a session and instantiate
        // the global $userbank — overkill for a CLI).
        if (!defined('IN_SB')) {
            define('IN_SB', true);
        }
        require_once $configPath;

        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
            fwrite(STDERR, "upgrade.php: $configPath is missing one of DB_HOST/DB_NAME/DB_USER/DB_PASS.\n");
            return null;
        }

        return [
            'host'     => (string) DB_HOST,
            'port'     => defined('DB_PORT')    ? (int) DB_PORT : 3306,
            'name'     => (string) DB_NAME,
            'user'     => (string) DB_USER,
            'password' => (string) DB_PASS,
            'prefix'   => defined('DB_PREFIX')  ? (string) DB_PREFIX  : 'sb',
            'charset'  => defined('DB_CHARSET') ? (string) DB_CHARSET : 'utf8mb4',
        ];
    }

    /**
     * @param array{host:string, port:int, name:string, user:string, password:string, prefix:string, charset:string} $cfg
     */
    private static function connect(array $cfg): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset'],
        );
        return new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }
}
