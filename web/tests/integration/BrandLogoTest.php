<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Sbpp\View\BrandLogo;

/**
 * Regression suite for `Sbpp\View\BrandLogo` — the single resolver
 * the navbar render path (`web/pages/core/header.php`) and the
 * login page (`web/pages/page.login.php`) consume for the
 * operator-configurable `template.logo` setting.
 *
 * Pre-fix the chrome read the raw `Config::get('template.logo')`
 * and concatenated it into `<img src="themes/<theme>/<value>">`.
 * That ships a broken `<img>` on three reachable shapes:
 *
 *   1. Value equals the v1.x default `logos/sb-large.png`, which
 *      never shipped in the v2.0 default theme. The forward-conversion
 *      migration (`web/updater/data/809.php`) catches this for the
 *      vast majority of upgrade paths, but the gate that pins it is
 *      a defense-in-depth resolver-side check — if an install
 *      somehow missed the migration (RC version skipping the
 *      hop, partial restore from backup, hand-edited setting), the
 *      chrome still doesn't render a broken image.
 *   2. Value is empty / `null`. The settings page accepts an empty
 *      string into the input; an upgrade path that lost the row
 *      would also surface as `Config::get` returning `null`.
 *   3. Value is a non-existent custom path (admin typo, file
 *      deleted from disk during a theme refresh, etc.).
 *
 * The resolver checks `is_file()` against `SB_THEMES.<theme>/<value>`
 * and falls back to `BrandLogo::DEFAULT_PATH` on any miss. The
 * settings input itself stays raw so the operator can see and fix a
 * bad pointer.
 *
 * Tests are intentionally exhaustive — the fix is one of those
 * "no observable bug for the lucky majority but devastating for the
 * minority who hit it" shapes, so we lock every branch.
 */
final class BrandLogoTest extends ApiTestCase
{
    public function testDefaultPathConstantPointsAtTheCanonicalShield(): void
    {
        // The constant is the single source of truth — `data.sql`
        // seeds this value, `web/updater/data/809.php` converts to
        // this value, and the resolver falls back to this value. If
        // the literal changes, every paired surface needs the same
        // edit; pin it here so the drift fails the gate at PR time.
        $this->assertSame('images/favicon.svg', BrandLogo::DEFAULT_PATH);

        // And the file actually exists on disk in the default theme
        // — without this the fallback itself would be broken.
        $this->assertFileExists(
            SB_THEMES . 'default/' . BrandLogo::DEFAULT_PATH,
            'BrandLogo::DEFAULT_PATH must point at a file that ships in the default theme.',
        );
    }

    /**
     * Fresh install: `data.sql` seeds `template.logo` =
     * `images/favicon.svg`. The resolver should pass that through
     * unchanged because the file exists on disk.
     */
    public function testFreshInstallDefaultRoundTripsUnchanged(): void
    {
        $this->setSetting('template.logo', 'images/favicon.svg');

        $this->assertSame('images/favicon.svg', BrandLogo::resolve());
        $this->assertSame('themes/default/images/favicon.svg', BrandLogo::resolveUrl());
    }

    /**
     * The marquee bug: an install whose `template.logo` row is
     * STILL the v1.x default `logos/sb-large.png` (either because
     * the 809 migration didn't run for some edge-case install, or
     * because a backup-restore pulled the value forward from a v1.x
     * dump). The v2.0 default theme doesn't ship this file —
     * rendering `<img src="themes/default/logos/sb-large.png">`
     * is a broken image. Resolver MUST refuse and fall back.
     */
    public function testV1DefaultPathFallsBackToShield(): void
    {
        $this->setSetting('template.logo', 'logos/sb-large.png');

        $this->assertSame(BrandLogo::DEFAULT_PATH, BrandLogo::resolve());
        $this->assertSame(
            'themes/default/' . BrandLogo::DEFAULT_PATH,
            BrandLogo::resolveUrl(),
        );
    }

    /**
     * Operator wiped the input and saved — the REPLACE INTO accepts
     * an empty string into `:prefix_settings.value`. Pre-fix the
     * chrome rendered `<img src="themes/default/">`; resolver must
     * fall back instead.
     */
    public function testEmptyConfiguredValueFallsBackToShield(): void
    {
        $this->setSetting('template.logo', '');

        $this->assertSame(BrandLogo::DEFAULT_PATH, BrandLogo::resolve());
    }

    /**
     * Whitespace-only values shouldn't sneak past the empty check
     * by virtue of being non-empty strings. The `trim()` inside
     * `resolve()` covers this — pin so a future refactor that drops
     * the trim doesn't re-open the gap.
     */
    public function testWhitespaceOnlyValueFallsBackToShield(): void
    {
        $this->setSetting('template.logo', '   ');

        $this->assertSame(BrandLogo::DEFAULT_PATH, BrandLogo::resolve());
    }

    /**
     * The row was deleted entirely (upgrade path that wiped it,
     * hand-edited DB, fresh install before `data.sql` ran). `Config::get`
     * returns `null`; casting to string yields `""`; resolver must
     * still fall back cleanly without emitting a PHP deprecation
     * warning ("Passing null to parameter #1 of type string").
     */
    public function testMissingRowFallsBackToShield(): void
    {
        $this->deleteSetting('template.logo');

        $this->assertSame(BrandLogo::DEFAULT_PATH, BrandLogo::resolve());
    }

    /**
     * Admin typo — `logos/my-server-logo.png` was correct yesterday
     * but the file was removed during a theme refresh. The chrome
     * can't tell the difference between "operator hasn't customised"
     * and "operator's customisation points at a deleted file"
     * without checking disk.
     */
    public function testNonExistentCustomPathFallsBackToShield(): void
    {
        $this->setSetting('template.logo', 'logos/this-file-definitely-does-not-exist.png');

        $this->assertSame(BrandLogo::DEFAULT_PATH, BrandLogo::resolve());
    }

    /**
     * A configured value that DOES exist on disk should round-trip
     * unchanged. `web/themes/default/logos/sbpp_logo.png` ships in
     * the repo as the larger raster shield (the settings page even
     * names it in its help copy).
     */
    public function testValidCustomPathRoundTripsUnchanged(): void
    {
        $this->setSetting('template.logo', 'logos/sbpp_logo.png');

        $this->assertSame('logos/sbpp_logo.png', BrandLogo::resolve());
        $this->assertSame(
            'themes/default/logos/sbpp_logo.png',
            BrandLogo::resolveUrl(),
        );
    }

    /**
     * An admin who types `/logos/sbpp_logo.png` (thinking
     * "theme root") still gets the right resolution — the leading
     * slash is stripped before the existence check. Mirrors what
     * the pre-fix `page.login.php` did inline with `ltrim`.
     */
    public function testLeadingSlashIsStripped(): void
    {
        $this->setSetting('template.logo', '/logos/sbpp_logo.png');

        $this->assertSame('logos/sbpp_logo.png', BrandLogo::resolve());
    }

    /**
     * Path traversal: `../` in the configured value must NOT
     * resolve outside the theme tree. Pre-fix the chrome happily
     * rendered `<img src="themes/default/../../../etc/passwd">`,
     * which the browser would route to `/etc/passwd` (or its 404).
     * Even though the browser would refuse to render the image,
     * the request fires — a footprint the resolver should refuse
     * to leave. Matches the path-sanitisation posture every other
     * admin-supplied-path surface in the panel takes (export
     * presigned URLs, theme-conf reader, etc.).
     */
    public function testPathTraversalFallsBackToShield(): void
    {
        $this->setSetting('template.logo', '../../etc/passwd');

        $this->assertSame(BrandLogo::DEFAULT_PATH, BrandLogo::resolve());
    }

    /**
     * Windows-style backslash separators are also rejected — a
     * Windows operator pasting `logos\sbpp_logo.png` should NOT
     * have their pointer silently accepted (it'd render correctly
     * on Linux servers because the existence check would resolve
     * with `\\`, but it'd render broken on a forked Windows IIS
     * deployment because `is_file` interprets the separator
     * differently). Falling back to the shield gives consistent
     * behavior; the operator should use forward slashes
     * regardless of host OS to mirror the URL contract.
     */
    public function testBackslashSeparatorFallsBackToShield(): void
    {
        $this->setSetting('template.logo', 'logos\\sbpp_logo.png');

        $this->assertSame(BrandLogo::DEFAULT_PATH, BrandLogo::resolve());
    }

    /**
     * `resolveUrl()` joins the active theme name into the prefix.
     * Most surfaces in the panel hard-code the `default` theme name
     * via `config.theme` not actually being switchable in v2.0, but
     * the resolver must honour a non-default value if one is set —
     * otherwise a future theme switch silently breaks the brand
     * mark URL.
     */
    public function testResolveUrlHonoursActiveThemeName(): void
    {
        $this->setSetting('config.theme', 'default');
        $this->setSetting('template.logo', 'images/favicon.svg');

        $this->assertSame('themes/default/images/favicon.svg', BrandLogo::resolveUrl());

        // A configured-but-doesn't-exist theme still slots into the
        // URL prefix — the existence check is on the FILE, not the
        // theme directory itself (the chrome stays consistent so an
        // admin troubleshooting "the brand mark is wrong" can see
        // the theme name in the rendered HTML).
        $this->setSetting('config.theme', 'someforktheme');
        $this->assertSame(
            'themes/someforktheme/' . BrandLogo::DEFAULT_PATH,
            BrandLogo::resolveUrl(),
            'A non-existent theme directory still names the URL prefix; the file fall-back kicks in via the resolver.',
        );
    }

    /**
     * Set a `:prefix_settings` row and refresh the in-process
     * `Config` cache. Mirrors `LoginToggleTest::setSetting` — the
     * canonical way to flip a setting from a PHPUnit test.
     */
    private function setSetting(string $key, string $value): void
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'REPLACE INTO `%s_settings` (`setting`, `value`) VALUES (?, ?)',
            DB_PREFIX,
        ));
        $stmt->execute([$key, $value]);
        \Config::init($GLOBALS['PDO']);
    }

    private function deleteSetting(string $key): void
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'DELETE FROM `%s_settings` WHERE `setting` = ?',
            DB_PREFIX,
        ));
        $stmt->execute([$key]);
        \Config::init($GLOBALS['PDO']);
    }
}
