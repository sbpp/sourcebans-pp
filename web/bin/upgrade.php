<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

CLI front-end for the 1.x → 2.0 schema migrator.

Reads the live database (using the credentials in `web/config.php`) and
the canonical schema from `web/install/includes/sql/struc.sql`, prints a
diff summary, and — with `--apply` — runs the missing changes inside a
single transaction wherever the storage engine allows it.

Usage:
  php web/bin/upgrade.php             # dry run (default)
  php web/bin/upgrade.php --apply     # actually apply the changes
  php web/bin/upgrade.php --no-color  # suppress ANSI colour codes
  php web/bin/upgrade.php --help      # this message

Exit codes:
  0  success — either nothing to do, or apply completed cleanly
  1  apply failed (one of the steps threw); message printed to stderr
  2  invalid invocation / missing config / unreadable SQL file

The implementation lives in `web/includes/Migrator/` (autoloaded under
the `Sbpp\Migrator` namespace) so the same code path also serves the
`system.upgrade_diff` / `system.upgrade_apply` JSON API actions and the
`?p=admin&c=upgrade` panel page.
*************************************************************************/

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "upgrade.php: must be run from the command line\n");
    exit(2);
}

$autoload = __DIR__ . '/../includes/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR,
        "upgrade.php: composer autoload not found at $autoload.\n"
        . "Run `composer install` from web/ first.\n",
    );
    exit(2);
}
require_once $autoload;

exit(\Sbpp\Migrator\CliRunner::main($argv));
