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
 * `ALTER TABLE … ADD COLUMN` statement for a column that exists in
 * struc.sql but not in the live DB. `sql` is fully rendered.
 *
 * We never emit ADD COLUMN IF NOT EXISTS because the differ has already
 * filtered to the genuinely-missing set; the bare ADD COLUMN keeps us
 * portable to MySQL 5.6 (which doesn't support the IF NOT EXISTS suffix).
 */
final class ColumnDelta
{
    public function __construct(
        public readonly string $table,
        public readonly string $column,
        public readonly string $sql,
    ) {
    }
}
