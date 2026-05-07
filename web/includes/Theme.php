<?php
declare(strict_types=1);

namespace Sbpp;

/**
 * Theme-level predicates the page handlers consult before doing
 * non-trivial work whose only consumer might be a third-party theme
 * fork.
 *
 * Background (#1270): the v2.0.0 admin landing redesign (#1146)
 * stopped rendering the legacy v1.x "stat counts" row and the
 * `getDirSize(SB_DEMOS)` demo-storage badge — the new chrome is the
 * 8-card grid in `web/themes/default/page_admin.tpl`. The matching
 * fields on {@see \Sbpp\View\AdminHomeView} (`$demosize`,
 * `$total_*`, `$archived_*`) are kept assignable so themes that
 * forked the pre-v2.0.0 default keep rendering off the same DTO
 * surface, and the `{if false}` parity block in `page_admin.tpl`
 * keeps `SmartyTemplateRule`'s "every assigned property is
 * referenced somewhere in the template tree" cross-check green for
 * the default PHPStan leg.
 *
 * The compute backing those fields (`SELECT COUNT(...)` over nine
 * tables including `:prefix_banlog`, plus a recursive
 * `getDirSize()` walk over `web/demos/`) is the most expensive
 * piece of work `web/pages/page.admin.php` does on every request.
 * The default theme never renders the values; the work was wasted
 * for every operator on the shipped theme.
 *
 * `wantsLegacyAdminCounts()` lets the page handler skip the work
 * when the active theme doesn't need it. Third-party fork themes
 * that DO render the legacy fields opt back in by adding
 *
 *     define('theme_legacy_admin_counts', true);
 *
 * to their `theme.conf.php` (the same file every theme already
 * defines `theme_name` / `theme_author` / `theme_version` in). The
 * shipped default doesn't define the constant, so it's `false` by
 * default and the COUNT query + getDirSize don't fire.
 *
 * Pure helper: no DB, no Smarty. Side-effect-free except for the
 * process-local "did the legacy compute branch fire?" counter
 * `recordLegacyComputePass()` / `legacyComputeCount()` which the
 * regression test reads to assert default-theme installs really do
 * skip the work.
 */
final class Theme
{
    /**
     * Name of the per-theme constant a third-party fork defines in
     * its `theme.conf.php` to opt back into the legacy admin-landing
     * counts compute (#1270). Public so tests can `define()` /
     * `assertSame()` against the same literal the production code
     * gates on.
     */
    public const LEGACY_ADMIN_COUNTS_CONSTANT = 'theme_legacy_admin_counts';

    /**
     * Process-local counter incremented by {@see recordLegacyComputePass()}
     * each time the page handler (`web/pages/page.admin.php`) takes the
     * legacy compute branch — i.e. ran the 9-COUNT subquery + the
     * recursive `getDirSize(SB_DEMOS)` walk. The regression test
     * (`web/tests/integration/AdminHomePerformanceTest.php`) reads it
     * via {@see legacyComputeCount()} to assert a default-theme
     * `?p=admin` request does NOT hit the slow path.
     */
    private static int $legacyComputeCount = 0;

    /**
     * Whether the active theme renders the legacy v1.x admin-landing
     * stat counts (`$total_*`, `$archived_*`, `$demosize`).
     *
     * Default theme: `false` (the constant is undefined).
     * Third-party forks: `true` if `theme.conf.php` defines
     * `theme_legacy_admin_counts` to a truthy value.
     */
    public static function wantsLegacyAdminCounts(): bool
    {
        if (!defined(self::LEGACY_ADMIN_COUNTS_CONSTANT)) {
            return false;
        }

        return (bool) constant(self::LEGACY_ADMIN_COUNTS_CONSTANT);
    }

    /**
     * Increment the process-local "legacy admin-counts compute fired"
     * counter. Called by `page.admin.php` immediately before it runs
     * the 9-COUNT subquery + `getDirSize(SB_DEMOS)`. Tests reset the
     * counter via {@see resetLegacyComputeCount()} and assert against
     * {@see legacyComputeCount()}.
     */
    public static function recordLegacyComputePass(): void
    {
        self::$legacyComputeCount++;
    }

    /**
     * Number of times the legacy admin-counts compute branch has
     * fired in the current PHP process.
     */
    public static function legacyComputeCount(): int
    {
        return self::$legacyComputeCount;
    }

    /**
     * Reset the legacy-compute counter to zero. Test-only helper —
     * production code never resets the counter.
     */
    public static function resetLegacyComputeCount(): void
    {
        self::$legacyComputeCount = 0;
    }
}
