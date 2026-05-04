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
 * Whole-schema view of `struc.sql` produced by {@see SchemaParser::parseStructure()}.
 * Tables keep `{prefix}_` placeholders so the same parsed value can be
 * compared against any deployment.
 */
final class ParsedSchema
{
    /** @param list<ParsedTable> $tables */
    public function __construct(
        public readonly array $tables,
    ) {
    }

    /** @return list<string> Short (unprefixed) table names, e.g. `admins`. */
    public function tableNames(): array
    {
        return array_map(fn(ParsedTable $t): string => $t->shortName, $this->tables);
    }

    public function table(string $shortName): ?ParsedTable
    {
        foreach ($this->tables as $t) {
            if ($t->shortName === $shortName) {
                return $t;
            }
        }
        return null;
    }
}
