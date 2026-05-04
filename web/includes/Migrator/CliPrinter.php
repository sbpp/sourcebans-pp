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
 * Tiny ANSI-aware formatter the CLI pipes plan + result output through.
 * Lives next to {@see CliRunner} because it's a presentation detail
 * nothing outside the CLI surface uses.
 *
 * The colour codes are deliberately the 8-colour set so the output stays
 * readable on terminals that don't support 256-colour escapes.
 */
final class CliPrinter
{
    /** @param resource $stream */
    public function __construct(
        private readonly bool $color,
        private $stream,
    ) {
    }

    public function printPlan(MigrationPlan $plan): void
    {
        $this->writeln($this->bold('SourceBans++ schema migrator'));
        $this->writeln('');

        if ($plan->isEmpty()) {
            $this->writeln($this->ok('  ✓ Schema is up to date.'));
            $this->writeln('');
            return;
        }

        if ($plan->missingTables !== []) {
            $this->writeln($this->bold(sprintf('Missing tables (%d):', count($plan->missingTables))));
            foreach ($plan->missingTables as $delta) {
                $this->writeln('  + :prefix_' . $delta->table);
            }
            $this->writeln('');
        }

        if ($plan->missingColumns !== []) {
            $this->writeln($this->bold(sprintf('Missing columns (%d):', count($plan->missingColumns))));
            foreach ($plan->missingColumns as $delta) {
                $this->writeln(sprintf('  + :prefix_%s.%s', $delta->table, $delta->column));
            }
            $this->writeln('');
        }

        if ($plan->missingSettings !== []) {
            $this->writeln($this->bold(sprintf('Missing settings keys (%d):', count($plan->missingSettings))));
            foreach ($plan->missingSettings as $delta) {
                $this->writeln(sprintf('  + %s = %s', $delta->key, $this->truncate($delta->value, 60)));
            }
            $this->writeln('');
        }

        $this->writeln($this->bold(sprintf('Total: %d change(s).', $plan->totalChanges())));
        $this->writeln('');
    }

    public function printResult(MigrationResult $result): void
    {
        $this->writeln($this->bold('Apply results:'));
        foreach ($result->steps as $step) {
            $marker = $step->success ? $this->ok('  ✓') : $this->fail('  ✗');
            $this->writeln(sprintf('%s  %s', $marker, $step->summary));
            if (!$step->success && $step->error !== null) {
                $this->writeln('     ' . $this->fail($step->error));
            }
        }
        $this->writeln('');

        if ($result->success) {
            $this->writeln($this->ok('Apply completed.'));
        } else {
            $this->writeln($this->fail('Apply FAILED — re-run after addressing the error above.'));
            if ($result->error !== null) {
                $this->writeln($this->fail($result->error));
            }
        }
        $this->writeln('');
    }

    public function infoLine(string $msg): void
    {
        $this->writeln($msg);
        $this->writeln('');
    }

    private function writeln(string $msg): void
    {
        fwrite($this->stream, $msg . "\n");
    }

    private function bold(string $s): string  { return $this->color ? "\033[1m$s\033[0m"   : $s; }
    private function ok(string $s): string    { return $this->color ? "\033[32m$s\033[0m"  : $s; }
    private function fail(string $s): string  { return $this->color ? "\033[31m$s\033[0m"  : $s; }

    private function truncate(string $s, int $max): string
    {
        if (strlen($s) <= $max) {
            return $s;
        }
        return substr($s, 0, $max - 1) . '…';
    }
}
