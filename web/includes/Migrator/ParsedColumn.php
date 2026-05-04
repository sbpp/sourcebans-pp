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
 * One column declaration from a CREATE TABLE body.
 *
 * `definition` is the everything-after-`name` substring with whitespace
 * collapsed (e.g. `int(11) NOT NULL default '0'`). It still carries any
 * `{charset}` placeholder so the differ can substitute it just before
 * synthesising an ADD COLUMN statement.
 */
final class ParsedColumn
{
    public function __construct(
        public readonly string $name,
        public readonly string $definition,
    ) {
    }
}
