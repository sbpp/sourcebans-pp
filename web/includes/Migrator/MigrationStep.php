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
 * One executed (or attempted) step. `kind` is one of:
 *   - `table`   — a missing CREATE TABLE that was applied
 *   - `column`  — a missing ADD COLUMN that was applied
 *   - `setting` — a missing INSERT IGNORE that was applied
 *   - `error`   — synthetic, only emitted when the apply is aborted
 */
final class MigrationStep
{
    public function __construct(
        public readonly string $kind,
        public readonly string $target,
        public readonly string $summary,
        public readonly string $sql,
        public readonly bool $success,
        public readonly ?string $error = null,
    ) {
    }
}
