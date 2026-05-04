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
 * Compares the live database against a {@see ParsedSchema} (from struc.sql)
 * and a list of {@see SettingSeed} rows (from data.sql) and returns a
 * {@see MigrationPlan}.
 *
 * The diff is intentionally additive-only:
 *
 *   - Missing tables → emit a `CREATE TABLE IF NOT EXISTS …` derived from
 *     the canonical CREATE TABLE block.
 *   - Missing columns on existing tables → emit `ALTER TABLE … ADD COLUMN …`.
 *   - Missing settings keys → emit `INSERT IGNORE INTO :prefix_settings …`.
 *
 * Any extra tables/columns/keys in the live DB are *ignored*. We never drop
 * anything: an admin who created a `sb_my_custom` audit column should not
 * have it deleted by an upgrade. The migrator is for additive 1.x → 2.0
 * changes; destructive migrations would belong to a different tool.
 *
 * The differ is also stateless — every method takes the live PDO + parsed
 * inputs as arguments. That makes it trivial to test without booting
 * `init.php`.
 */
final class SchemaDiffer
{
    /**
     * Compute what the migrator would do against `$pdo`.
     *
     * `$prefix` and `$charset` are substituted into the canonical SQL
     * before it lands in the plan, so the produced statements are ready
     * for `prepare()`/`execute()`.
     *
     * @param list<SettingSeed> $settingSeeds
     */
    public function diff(
        PDO $pdo,
        ParsedSchema $schema,
        array $settingSeeds,
        string $prefix,
        string $charset,
    ): MigrationPlan {
        $existingTables  = self::listExistingTables($pdo, $prefix);
        $existingColumns = []; // table-short-name => map<columnName, true>
        $missingTables   = [];
        $missingColumns  = [];

        foreach ($schema->tables as $table) {
            $fullName = $prefix . '_' . $table->shortName;
            if (!isset($existingTables[$fullName])) {
                $missingTables[] = new TableDelta(
                    table: $table->shortName,
                    sql:   self::renderDdl($table->createSql, $prefix, $charset),
                );
                continue;
            }

            // Table exists — diff its column set. Cache the live columns so
            // the next `column` lookup in the same loop iteration doesn't
            // re-query.
            if (!isset($existingColumns[$table->shortName])) {
                $existingColumns[$table->shortName] = self::listExistingColumns($pdo, $fullName);
            }
            $live = $existingColumns[$table->shortName];

            foreach ($table->columns as $col) {
                if (isset($live[strtolower($col->name)])) {
                    continue;
                }
                $rawDefinition = self::renderDdl($col->definition, $prefix, $charset);
                $missingColumns[] = new ColumnDelta(
                    table:  $table->shortName,
                    column: $col->name,
                    sql:    sprintf(
                        'ALTER TABLE `%s` ADD COLUMN `%s` %s',
                        $fullName,
                        $col->name,
                        $rawDefinition,
                    ),
                );
            }
        }

        $missingSettings = self::diffSettings($pdo, $settingSeeds, $prefix);

        return new MigrationPlan(
            missingTables:   $missingTables,
            missingColumns:  $missingColumns,
            missingSettings: $missingSettings,
        );
    }

    /**
     * Substitute the `{prefix}` / `{charset}` placeholders inside a parsed
     * SQL fragment. Kept private to the differ so the rendering rules live
     * in one place. Both placeholders are common DDL idioms in this repo.
     */
    private static function renderDdl(string $sql, string $prefix, string $charset): string
    {
        return strtr($sql, [
            '{prefix}'  => $prefix,
            '{charset}' => $charset,
        ]);
    }

    /**
     * List every table whose name starts with `$prefix_`. Returns a map
     * keyed by full table name for O(1) presence checks.
     *
     * We use `information_schema.tables` rather than `SHOW TABLES LIKE` so
     * the LIKE wildcard escaping doesn't trip on a prefix that contains
     * `_` (it does — DB_PREFIX is literally `sb`, and the rendered table
     * names are `sb_admins`, `sb_bans`, etc.). information_schema also
     * gives us a stable plan target across MySQL/MariaDB versions.
     *
     * @return array<string, true>
     */
    private static function listExistingTables(PDO $pdo, string $prefix): array
    {
        $stmt = $pdo->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE :pattern'
        );
        $stmt->bindValue(':pattern', addcslashes($prefix, '_%') . '\\_%');
        $stmt->execute();

        /** @var array<string, true> $tables */
        $tables = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $tables[(string) $name] = true;
        }
        return $tables;
    }

    /**
     * @return array<string, true> Lower-cased column names → true.
     */
    private static function listExistingColumns(PDO $pdo, string $fullTableName): array
    {
        // `SHOW COLUMNS FROM :ident` doesn't accept bound identifiers, but
        // information_schema does. Going through there keeps us safe even
        // if a deployment uses a weird prefix character set.
        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :name'
        );
        $stmt->bindValue(':name', $fullTableName);
        $stmt->execute();

        /** @var array<string, true> $cols */
        $cols = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $col) {
            $cols[strtolower((string) $col)] = true;
        }
        return $cols;
    }

    /**
     * Compute which settings rows from data.sql are missing in `:prefix_settings`.
     *
     * If the table itself is missing we return *every* seed — the planner
     * already queued the CREATE TABLE for it, and the applier will run the
     * inserts after the DDL succeeds.
     *
     * @param list<SettingSeed> $seeds
     * @return list<SettingDelta>
     */
    private static function diffSettings(PDO $pdo, array $seeds, string $prefix): array
    {
        $tableName = $prefix . '_settings';

        // Quick existence check — avoids a `SELECT setting FROM nonexistent`
        // throw before the CREATE TABLE has run.
        $exists = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :name'
        );
        $exists->bindValue(':name', $tableName);
        $exists->execute();
        $tableExists = (bool) $exists->fetchColumn();

        $present = [];
        if ($tableExists) {
            // identifier interpolation is fine here: `$tableName` is built
            // from the DB_PREFIX constant + literal `_settings`, both
            // operator-controlled. PDO won't bind table names anyway.
            // PDO is configured with ERRMODE_EXCEPTION upstream, so query()
            // throws on failure rather than returning false (phpstan reads
            // the typed signature; we don't need to re-check here).
            $stmt = $pdo->query(sprintf('SELECT `setting` FROM `%s`', $tableName));
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $key) {
                $present[(string) $key] = true;
            }
        }

        $missing = [];
        foreach ($seeds as $seed) {
            if (isset($present[$seed->key])) {
                continue;
            }
            $missing[] = new SettingDelta($seed->key, $seed->value);
        }
        return $missing;
    }
}
