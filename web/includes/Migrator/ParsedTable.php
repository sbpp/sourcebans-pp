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
 * One CREATE TABLE block from struc.sql.
 *
 * `createSql` retains the literal `{prefix}` and `{charset}` placeholders
 * so callers can substitute deployment-specific values without re-parsing.
 */
final class ParsedTable
{
    /** @param list<ParsedColumn> $columns */
    public function __construct(
        public readonly string $shortName,
        public readonly string $createSql,
        public readonly array $columns,
    ) {
    }

    public function column(string $name): ?ParsedColumn
    {
        foreach ($this->columns as $c) {
            if ($c->name === $name) {
                return $c;
            }
        }
        return null;
    }
}
