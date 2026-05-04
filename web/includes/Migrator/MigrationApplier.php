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
use PDOException;
use Throwable;

/**
 * Applies a {@see MigrationPlan} against a live PDO connection and returns
 * a per-step {@see MigrationResult} record so callers can pretty-print
 * exactly what ran.
 *
 * ## Transaction model
 *
 * MariaDB/MySQL implicitly commit before *and* after every DDL statement,
 * so wrapping CREATE TABLE / ALTER TABLE in a `BEGIN…COMMIT` is a no-op
 * (worse: it silently truncates any preceding DML transaction). The
 * applier therefore:
 *
 *   1. Runs DDL statements one at a time, top-down, fail-fast. Each is
 *      already idempotent (`CREATE TABLE IF NOT EXISTS`) or filtered to
 *      genuinely-missing columns by the differ, so a partial failure
 *      leaves the DB in a state where re-running converges.
 *   2. Wraps the settings INSERT IGNOREs in a single transaction so the
 *      seed updates are atomic relative to themselves. They use INSERT
 *      IGNORE on the `setting` UNIQUE KEY, so a re-run is a no-op even
 *      after a partial DDL failure.
 *
 * Any thrown PDOException stops the apply immediately; the {@see MigrationResult}
 * captures the step that failed and the message so the CLI/UI can surface
 * a precise error rather than a generic "migration failed".
 */
final class MigrationApplier
{
    public function __construct(
        private readonly string $prefix,
    ) {
    }

    public function apply(PDO $pdo, MigrationPlan $plan): MigrationResult
    {
        $steps  = [];
        $error  = null;

        try {
            foreach ($plan->missingTables as $delta) {
                $pdo->exec($delta->sql);
                $steps[] = new MigrationStep(
                    kind:    'table',
                    target:  $delta->table,
                    summary: "CREATE TABLE :prefix_{$delta->table}",
                    sql:     $delta->sql,
                    success: true,
                );
            }

            foreach ($plan->missingColumns as $delta) {
                $pdo->exec($delta->sql);
                $steps[] = new MigrationStep(
                    kind:    'column',
                    target:  "{$delta->table}.{$delta->column}",
                    summary: "ALTER TABLE :prefix_{$delta->table} ADD COLUMN {$delta->column}",
                    sql:     $delta->sql,
                    success: true,
                );
            }

            $this->applySettings($pdo, $plan->missingSettings, $steps);
        } catch (Throwable $e) {
            $error = $e instanceof PDOException
                ? sprintf('PDO error: %s', $e->getMessage())
                : sprintf('%s: %s', $e::class, $e->getMessage());
            // Synthetic failed step so callers see *which* change was in
            // flight when we tripped (the steps array up to here is the
            // actual successful prefix).
            $steps[] = new MigrationStep(
                kind:    'error',
                target:  '',
                summary: 'Apply aborted',
                sql:     '',
                success: false,
                error:   $error,
            );
        }

        return new MigrationResult($steps, $error === null, $error);
    }

    /**
     * @param list<SettingDelta>            $deltas
     * @param list<MigrationStep>           $steps  Mutated in-place with one step per insert.
     */
    private function applySettings(PDO $pdo, array $deltas, array &$steps): void
    {
        if ($deltas === []) {
            return;
        }

        $tableName = $this->prefix . '_settings';

        // identifier interpolation: `$tableName` is built from the
        // operator-controlled prefix constant plus a literal suffix, so
        // there's no user input on this path. PDO can't bind identifiers
        // anyway; the value placeholders below carry the user-provided
        // bits.
        $stmt = $pdo->prepare(sprintf(
            'INSERT IGNORE INTO `%s` (`setting`, `value`) VALUES (:setting, :value)',
            $tableName,
        ));

        $inTx = false;
        try {
            $pdo->beginTransaction();
            $inTx = true;

            foreach ($deltas as $delta) {
                $stmt->execute([
                    ':setting' => $delta->key,
                    ':value'   => $delta->value,
                ]);
                $steps[] = new MigrationStep(
                    kind:    'setting',
                    target:  $delta->key,
                    summary: "INSERT IGNORE :prefix_settings ({$delta->key})",
                    sql:     sprintf("INSERT IGNORE INTO `%s` (setting, value) VALUES ('%s', '%s')",
                        $tableName,
                        addslashes($delta->key),
                        addslashes($delta->value),
                    ),
                    success: true,
                );
            }

            $pdo->commit();
            $inTx = false;
        } catch (Throwable $e) {
            if ($inTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
