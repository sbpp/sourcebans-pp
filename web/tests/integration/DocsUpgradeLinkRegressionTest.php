<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Issue #1474: the in-panel "Anonymous telemetry" help paragraph in
 * `?p=admin&c=settings&section=features` shipped a "See the full
 * payload" link pointing at `https://sbpp.github.io/updating/1.8-to-2.0/#telemetry`,
 * which 404s. Two things were wrong with the URL:
 *
 *   1. The slug was `1.8-to-2.0`. Astro/Starlight derives slugs from
 *      filenames, and the docs file on disk is
 *      `docs/src/content/docs/updating/1-8-to-2-0.mdx` — dots in the
 *      filename become dashes. The deployed URL is `/updating/1-8-to-2-0/`.
 *   2. The anchor was `#telemetry`. The actual heading in the docs
 *      file is `## Anonymous telemetry`, which Starlight auto-slugs
 *      to `#anonymous-telemetry`. `#telemetry` matches no heading.
 *
 * Three sibling sites carried the same broken URL or the matching
 * source-file path drift, all swept in the #1474 fix:
 *
 *   - `web/themes/default/page_admin_settings_features.tpl` line 205
 *     — the actual user-reported broken link in the panel chrome.
 *   - `AGENTS.md` "Build / extend the anonymous opt-out daily
 *     telemetry payload" row pointed at the wrong source file path
 *     (`docs/src/content/docs/updating/1.8-to-2.0.mdx`, which doesn't
 *     exist).
 *   - `CHANGELOG.md` (2.0.0 Privacy subsection) carried the same
 *     broken URL as the template.
 *
 * Contracts pinned
 * ----------------
 *
 * Four complementary gates:
 *
 *   1. `testCanonicalDocsFileExistsAtFixedSlugPath` — the
 *      load-bearing source-of-truth assertion. A rename of the docs
 *      file silently re-breaks every link pointing at the wrong
 *      slug; pinning the canonical filename here makes a future
 *      "let's clean up the slug" PR fail the gate first.
 *   2. `testCanonicalDocsFileCarriesTheAnonymousTelemetryHeading` —
 *      the docs file must contain `## Anonymous telemetry` so the
 *      auto-generated `#anonymous-telemetry` anchor is real. A
 *      future "rename to Telemetry" edit would silently 404 every
 *      panel-side deep-link.
 *   3. `testNoPanelOrDocSourcesUseTheBrokenUpgradeSlug` — broad-scan
 *      sweep. No file under the documented scan roots (templates,
 *      page handlers, includes, scripts, API handlers, AGENTS.md,
 *      CHANGELOG.md) may carry the literal `1.8-to-2.0` string.
 *      Catches both broken URLs (`/updating/1.8-to-2.0/`) and broken
 *      source-file path references
 *      (`docs/src/content/docs/updating/1.8-to-2.0.mdx`) with one
 *      assertion.
 *   4. `testTelemetryHelpLinkUsesCorrectUrlAndAnchor` — the specific
 *      template line that motivated the issue. Pins the exact `href`
 *      so a "drift edit" that points at the right slug but the wrong
 *      anchor (or vice-versa) still fails.
 *
 * Pure file scanning — no DB / session / Smarty bring-up. Extends
 * `PHPUnit\Framework\TestCase` directly (mirrors `DeadJsCallSitesTest`,
 * `IframeChromeAntiFoucBootloaderTest`, `ButtonClassChainTest`).
 */
final class DocsUpgradeLinkRegressionTest extends TestCase
{
    /**
     * The broken slug pattern that motivated the issue. The dot-form
     * (`1.8-to-2.0`) matches the historical naming convention but
     * doesn't survive Astro's filename → slug derivation.
     */
    private const BROKEN_SLUG = '1.8-to-2.0';

    /**
     * The canonical slug — matches the actual filename on disk
     * (`1-8-to-2-0.mdx` → `/updating/1-8-to-2-0/`).
     */
    private const FIXED_SLUG = '1-8-to-2-0';

    /**
     * The corrected telemetry help link. Pinning the full URL (slug +
     * anchor) catches half-fixes that get one half right and the
     * other wrong.
     */
    private const FIXED_TELEMETRY_HELP_URL = 'https://sbpp.github.io/updating/1-8-to-2-0/#anonymous-telemetry';

    /**
     * Repo root resolved from `ROOT` (which points at `web/`). The
     * docs tree lives at `<repo>/docs/`, parallel to `web/`.
     */
    private static function repoRoot(): string
    {
        return dirname(rtrim(ROOT, '/')) . '/';
    }

    /**
     * Path to the canonical docs file. Centralised so the four tests
     * agree on the same source-of-truth path.
     */
    private static function canonicalDocsPath(): string
    {
        return self::repoRoot() . 'docs/src/content/docs/updating/' . self::FIXED_SLUG . '.mdx';
    }

    public function testCanonicalDocsFileExistsAtFixedSlugPath(): void
    {
        $path = self::canonicalDocsPath();
        $this->assertFileExists(
            $path,
            "The docs source file at `$path` must exist — every panel-side "
            . "link to `https://sbpp.github.io/updating/" . self::FIXED_SLUG . "/` depends on "
            . "this file being the source of truth for the deployed slug. A rename here "
            . "(e.g. dropping the version, switching the slug shape) silently re-breaks "
            . "every deep-link from the panel chrome (#1474).",
        );
    }

    public function testCanonicalDocsFileCarriesTheAnonymousTelemetryHeading(): void
    {
        $path = self::canonicalDocsPath();
        $contents = file_get_contents($path);
        $this->assertNotFalse(
            $contents,
            "Could not read $path",
        );

        // Match the heading line tolerant of trailing whitespace. The
        // anchor Astro/Starlight emits is derived from the visible
        // heading text (lowercase, spaces → dashes, punctuation
        // stripped) so `## Anonymous telemetry` → `#anonymous-telemetry`.
        // A future rename to e.g. `## Telemetry` would silently 404
        // every panel-side deep-link to `#anonymous-telemetry`.
        $this->assertMatchesRegularExpression(
            '/^##\s+Anonymous telemetry\s*$/m',
            $contents,
            "The canonical docs file at `$path` must carry an `## Anonymous telemetry` "
            . "heading so the auto-generated `#anonymous-telemetry` anchor is real. The "
            . "in-panel help paragraph + the CHANGELOG entry both deep-link to "
            . "`#anonymous-telemetry`; renaming the heading silently 404s every link "
            . "(#1474).",
        );
    }

    /**
     * Broad-scan sweep. No file under the documented scan roots may
     * carry the literal `1.8-to-2.0` string. This is intentionally a
     * substring scan (not a URL-aware parser) because the same broken
     * literal shows up two ways — as a URL fragment AND as a source-
     * file path reference — and both shapes are bugs of the same
     * class.
     *
     * Scan roots:
     *
     *   - `web/themes/`   — every Smarty template (the canonical
     *                       location for in-panel help links).
     *   - `web/pages/`    — page handlers (no broken slug today; the
     *                       scan is defence-in-depth so a future page
     *                       handler that constructs the URL in PHP
     *                       can't slip past).
     *   - `web/includes/` — View DTOs, Markup renderers, and the
     *                       Telemetry / Announce subsystems all
     *                       potentially reference docs URLs.
     *   - `web/scripts/`  — vanilla JS that might compose a help URL
     *                       client-side (none today, defence in
     *                       depth).
     *   - `web/api/`      — JSON handlers (none today, defence in
     *                       depth — a "redirect to docs" envelope
     *                       would land here).
     *   - `AGENTS.md`     — the rule book; AGENTS.md's "If a rule no
     *                       longer matches the code, the rule is
     *                       wrong" contract demands the docs
     *                       references stay correct.
     *   - `CHANGELOG.md`  — operator-facing release notes; broken
     *                       links here mislead self-hosters reading
     *                       through the v2.0.0 upgrade section.
     *
     * Out of scope:
     *
     *   - The docs tree itself (`docs/`). Docs are deployed as a
     *     separate Astro site and Astro's link-checker (run during
     *     `docs/` CI) is the right gate for cross-doc references.
     *   - `web/tests/` — this test file itself mentions the broken
     *     literal in its docblock + class constant, and PHPUnit
     *     fixtures may legitimately use it as test data in the
     *     future.
     */
    public function testNoPanelOrDocSourcesUseTheBrokenUpgradeSlug(): void
    {
        $hits = [];

        $directories = [
            ROOT . 'themes',
            ROOT . 'pages',
            ROOT . 'includes',
            ROOT . 'scripts',
            ROOT . 'api',
        ];
        foreach ($directories as $dir) {
            $this->assertDirectoryExists(
                $dir,
                "$dir must exist — if you moved the panel layout, update "
                . "DocsUpgradeLinkRegressionTest::testNoPanelOrDocSourcesUseTheBrokenUpgradeSlug "
                . "in the same PR.",
            );
            foreach ($this->iterateFiles($dir) as $file) {
                $contents = file_get_contents($file);
                if ($contents === false) {
                    continue;
                }
                if (str_contains($contents, self::BROKEN_SLUG)) {
                    $hits[] = $this->relativeToRepo($file);
                }
            }
        }

        $rootFiles = [
            self::repoRoot() . 'AGENTS.md',
            self::repoRoot() . 'CHANGELOG.md',
        ];
        foreach ($rootFiles as $file) {
            $this->assertFileExists(
                $file,
                "$file must exist — if you moved or renamed it, update "
                . "DocsUpgradeLinkRegressionTest::testNoPanelOrDocSourcesUseTheBrokenUpgradeSlug "
                . "in the same PR.",
            );
            $contents = file_get_contents($file);
            if ($contents !== false && str_contains($contents, self::BROKEN_SLUG)) {
                $hits[] = $this->relativeToRepo($file);
            }
        }

        $this->assertSame(
            [],
            $hits,
            "Broken upgrade-docs slug `" . self::BROKEN_SLUG . "` found in panel sources "
            . "— Astro derives slugs from filenames, so the deployed URL is "
            . "`/updating/" . self::FIXED_SLUG . "/`, NOT `/updating/" . self::BROKEN_SLUG . "/` "
            . "(which 404s). Same shape applies to docs source-file path references "
            . "(`docs/src/content/docs/updating/" . self::FIXED_SLUG . ".mdx`, not "
            . "`" . self::BROKEN_SLUG . ".mdx`). Issue #1474.\n\nOffending files:\n  - "
                . implode("\n  - ", $hits),
        );
    }

    public function testTelemetryHelpLinkUsesCorrectUrlAndAnchor(): void
    {
        $tplPath = ROOT . 'themes/default/page_admin_settings_features.tpl';
        $tpl = file_get_contents($tplPath);
        $this->assertNotFalse($tpl, "Could not read $tplPath");

        $this->assertStringContainsString(
            'href="' . self::FIXED_TELEMETRY_HELP_URL . '"',
            $tpl,
            "page_admin_settings_features.tpl must point the 'See the full payload' link "
            . "at `" . self::FIXED_TELEMETRY_HELP_URL . "`. The corrected URL has two "
            . "load-bearing halves: the slug (`" . self::FIXED_SLUG . "`, matching the "
            . "filename on disk) AND the anchor (`#anonymous-telemetry`, matching the "
            . "`## Anonymous telemetry` heading in that file). A half-fix that uses the "
            . "right slug but the wrong anchor (or vice-versa) still 404s or scrolls to "
            . "the wrong place (#1474).",
        );
    }

    /**
     * @return iterable<string>
     */
    private function iterateFiles(string $dir): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }
            // Skip vendor / node_modules / compiled-template caches.
            $path = $file->getPathname();
            if (
                str_contains($path, '/vendor/')
                || str_contains($path, '/node_modules/')
                || str_contains($path, '/cache/')
                || str_contains($path, '/templates_c/')
            ) {
                continue;
            }
            yield $path;
        }
    }

    private function relativeToRepo(string $absolute): string
    {
        $root = self::repoRoot();
        if (str_starts_with($absolute, $root)) {
            return substr($absolute, strlen($root));
        }
        return $absolute;
    }
}
