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
 * One missing `:prefix_settings` row that data.sql ships and the live DB
 * is missing. The applier wraps these in a transaction together because
 * they're plain DML and rolling back is cheap.
 */
final class SettingDelta
{
    public function __construct(
        public readonly string $key,
        public readonly string $value,
    ) {
    }
}
