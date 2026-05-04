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
 * Outcome of {@see MigrationApplier::apply()}. `success` is true exactly
 * when every queued step executed; `error` carries the message the
 * exception raised on the first failure.
 */
final class MigrationResult
{
    /** @param list<MigrationStep> $steps */
    public function __construct(
        public readonly array $steps,
        public readonly bool $success,
        public readonly ?string $error,
    ) {
    }

    /**
     * Compact JSON-friendly shape suitable for the JSON API + UI.
     *
     * @return array{
     *     ok: bool,
     *     error: ?string,
     *     steps: list<array{
     *         kind: string, target: string, summary: string,
     *         success: bool, error: ?string
     *     }>
     * }
     */
    public function toArray(): array
    {
        return [
            'ok'    => $this->success,
            'error' => $this->error,
            'steps' => array_map(
                fn(MigrationStep $s): array => [
                    'kind'    => $s->kind,
                    'target'  => $s->target,
                    'summary' => $s->summary,
                    'success' => $s->success,
                    'error'   => $s->error,
                ],
                $this->steps,
            ),
        ];
    }
}
