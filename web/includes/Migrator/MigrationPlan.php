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

/**
 * What the migrator wants to do, once.
 *
 * Produced by {@see SchemaDiffer::diff()} and consumed by
 * {@see MigrationApplier::apply()}. The same value is also handed to the
 * panel's dry-run preview verbatim — the UI shouldn't render any state
 * the CLI doesn't see.
 *
 * All SQL strings inside the deltas are deployment-ready — `{prefix}` and
 * `{charset}` have already been substituted — so the applier only has to
 * `prepare()` and `execute()`.
 */
final class MigrationPlan
{
    /**
     * @param list<TableDelta>   $missingTables
     * @param list<ColumnDelta>  $missingColumns
     * @param list<SettingDelta> $missingSettings
     */
    public function __construct(
        public readonly array $missingTables,
        public readonly array $missingColumns,
        public readonly array $missingSettings,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->missingTables === []
            && $this->missingColumns === []
            && $this->missingSettings === [];
    }

    public function totalChanges(): int
    {
        return count($this->missingTables)
            + count($this->missingColumns)
            + count($this->missingSettings);
    }

    /**
     * Compact JSON-friendly shape suitable for the JSON API + UI.
     *
     * @return array{
     *     ok: bool,
     *     total: int,
     *     tables: list<array{name: string, sql: string}>,
     *     columns: list<array{table: string, column: string, sql: string}>,
     *     settings: list<array{key: string, value: string}>,
     * }
     */
    public function toArray(): array
    {
        return [
            'ok'      => $this->isEmpty(),
            'total'   => $this->totalChanges(),
            'tables'  => array_map(
                fn(TableDelta $t): array => ['name' => $t->table, 'sql' => $t->sql],
                $this->missingTables,
            ),
            'columns' => array_map(
                fn(ColumnDelta $c): array => [
                    'table'  => $c->table,
                    'column' => $c->column,
                    'sql'    => $c->sql,
                ],
                $this->missingColumns,
            ),
            'settings' => array_map(
                fn(SettingDelta $s): array => ['key' => $s->key, 'value' => $s->value],
                $this->missingSettings,
            ),
        ];
    }
}
