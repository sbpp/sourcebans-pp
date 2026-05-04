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

/**
 * Public façade for the 1.x → 2.0 schema migrator.
 *
 * Orchestrates the three concrete pieces:
 *   1. {@see SchemaParser}    — reads `struc.sql` + `data.sql`.
 *   2. {@see SchemaDiffer}    — compares the parsed structure to a live PDO.
 *   3. {@see MigrationApplier} — runs whatever the diff produced.
 *
 * Used by:
 *   - `web/bin/upgrade.php`   — CLI (operator runs it from the host).
 *   - `web/api/handlers/system.php` — JSON API (admin runs it from the panel).
 *   - `web/tests/integration/UpgradeRunnerTest.php` — convergence test.
 *
 * The façade has no dependency on `init.php` so it can run unbootstrapped
 * (the CLI) or under the panel's bootstrap (the API handler).
 */
final class Migrator
{
    public function __construct(
        public readonly string $strucSqlPath,
        public readonly string $dataSqlPath,
        public readonly string $prefix,
        public readonly string $charset,
    ) {
    }

    /**
     * Construct a migrator wired against the canonical files shipped in
     * `web/install/includes/sql/`. `$prefix` and `$charset` come from the
     * caller's environment (DB_PREFIX / DB_CHARSET).
     */
    public static function fromInstallSql(string $webRoot, string $prefix, string $charset): self
    {
        return new self(
            strucSqlPath: rtrim($webRoot, '/') . '/install/includes/sql/struc.sql',
            dataSqlPath:  rtrim($webRoot, '/') . '/install/includes/sql/data.sql',
            prefix:       $prefix,
            charset:      $charset,
        );
    }

    /**
     * Compute the diff between the on-disk SQL and the live database.
     *
     * Throws {@see \RuntimeException} when struc.sql or data.sql isn't
     * readable — both are part of the release tarball, so a missing file
     * means something is fundamentally wrong with the deployment.
     */
    public function plan(PDO $pdo): MigrationPlan
    {
        $struc = $this->readSqlFile($this->strucSqlPath);
        $data  = $this->readSqlFile($this->dataSqlPath);

        $schema = SchemaParser::parseStructure($struc);
        $seeds  = SchemaParser::parseSettings($data);

        $differ = new SchemaDiffer();
        return $differ->diff($pdo, $schema, $seeds, $this->prefix, $this->charset);
    }

    /**
     * Compute the plan and immediately apply it. Returns the same result
     * shape the CLI/panel render — the `MigrationPlan` is preserved on the
     * applier output via {@see MigrationApplier::apply()}'s step list.
     */
    public function apply(PDO $pdo): MigrationResult
    {
        $plan    = $this->plan($pdo);
        $applier = new MigrationApplier($this->prefix);
        return $applier->apply($pdo, $plan);
    }

    private function readSqlFile(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException("Migrator: cannot read SQL file at $path");
        }
        $content = (string) file_get_contents($path);
        if ($content === '') {
            throw new \RuntimeException("Migrator: $path is empty");
        }
        return $content;
    }
}
