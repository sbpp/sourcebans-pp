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
 * `CREATE TABLE` statement for a table that exists in struc.sql but not in
 * the live DB. `sql` is fully rendered (prefix + charset substituted) and
 * idempotent (`CREATE TABLE IF NOT EXISTS …`).
 */
final class TableDelta
{
    public function __construct(
        public readonly string $table,
        public readonly string $sql,
    ) {
    }
}
