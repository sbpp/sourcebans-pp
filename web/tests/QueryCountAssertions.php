<?php

namespace Sbpp\Tests;

use PHPUnit\Framework\Assert;

/**
 * Shared assertions for pinning "this surface issues a bounded number
 * of database round trips regardless of row count" contracts.
 *
 * Wall-clock timing assertions are flaky (machine, DB latency, load)
 * and the project's E2E conventions reject them in favor of
 * deterministic markers (see AGENTS.md's "Playwright E2E specifics").
 * Assert the query *shape* instead: the number of
 * `Sbpp\Db\Database::query()` calls a render/handler issues. That
 * number is deterministic on a given codebase revision and dataset
 * size.
 *
 * Two complementary shapes, depending on whether the surface under
 * test can be invoked twice in the same PHP process:
 *
 *  - {@see assertQueryCountAtMost()} — single invocation, absolute
 *    bound. Use for page handlers (`require`'d scripts that declare
 *    top-level functions PHP can't redeclare, so each render needs
 *    its own process via `#[RunInSeparateProcess]`). Combine with a
 *    `#[DataProvider]` supplying several row counts so the SAME bound
 *    is asserted at multiple scales in independent processes.
 *
 *  - {@see assertQueryCountDelta()} — two invocations in the same
 *    process (safe for plain function calls: JSON API handlers,
 *    `UserManager` helpers, etc. — nothing top-level to redeclare).
 *    Seed a small dataset, invoke, seed more, invoke again, and
 *    assert the marginal query cost of the extra rows is small. This
 *    is self-calibrating: no need to guess what the "correct" total
 *    query count should be, only that it doesn't grow with N.
 */
trait QueryCountAssertions
{
    /**
     * Reset the counter, run $render, and assert the total number of
     * `Database::query()` calls it issued is at most $max.
     *
     * @return int the actual count, in case the caller wants to log/assert further
     */
    protected function assertQueryCountAtMost(int $max, callable $render, string $message = ''): int
    {
        \Database::resetQueryCount();
        $render();
        $count = \Database::getQueryCount();

        Assert::assertLessThanOrEqual(
            $max,
            $count,
            ($message !== '' ? $message . ' ' : '')
                . "(issued {$count} queries, expected at most {$max})",
        );

        return $count;
    }

    /**
     * Run $renderBaseline, capture its query count, run $renderGrown
     * (expected to touch strictly more rows), capture its count, and
     * assert the marginal cost ($grown - $baseline) is at most
     * $maxDelta.
     *
     * Both callables run in the SAME process — only safe for
     * invocation shapes that don't declare top-level symbols (plain
     * function calls / API handler dispatch), not `require`'d page
     * scripts.
     *
     * @return array{baseline: int, grown: int, delta: int}
     */
    protected function assertQueryCountDelta(
        int $maxDelta,
        callable $renderBaseline,
        callable $renderGrown,
        string $message = '',
    ): array {
        \Database::resetQueryCount();
        $renderBaseline();
        $baseline = \Database::getQueryCount();

        \Database::resetQueryCount();
        $renderGrown();
        $grown = \Database::getQueryCount();

        $delta = $grown - $baseline;

        Assert::assertLessThanOrEqual(
            $maxDelta,
            $delta,
            ($message !== '' ? $message . ' ' : '')
                . "(baseline={$baseline} queries, grown={$grown} queries, delta={$delta}, expected at most {$maxDelta})",
        );

        return ['baseline' => $baseline, 'grown' => $grown, 'delta' => $delta];
    }
}
