<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1419: production Docker image (`docker/Dockerfile.prod` →
 * `ghcr.io/sbpp/sourcebans-pp:*`) ships a hardening Apache conf
 * (`docker/apache/sbpp-prod.conf`) that uses `<FilesMatch>` /
 * `<Files>` to deny access to root-level config files
 * (`composer.json`, `phpstan.*`, `phpunit.xml*`, `package.json`,
 * `tsconfig.json`, `config.php`).
 *
 * Pre-fix the union regex carried `api-contract\..*` in the
 * `<FilesMatch>` alternation, intended to shield some never-existed
 * `api-contract.*` config artifact at the panel root. `<FilesMatch>`
 * matches the **basename** regardless of which directory the URL
 * resolves under, so the regex also matched the published
 * `web/scripts/api-contract.js` — the chrome JS `core/header.tpl`
 * loads on every page render to define the `Actions.*` / `Perms.*`
 * namespaces that every `sb.api.call(Actions.PascalName, …)` site
 * in the panel JS depends on.
 *
 * With it 403'd:
 *
 *   - The login form's submit handler (`page_login.tpl`) calls
 *     `sb.api.call(Actions.AuthLogin, …)` — `Actions` is undefined,
 *     the call throws, but the handler already ran `e.preventDefault()`
 *     and `setBusy(submitBtn, true)`. The success / failure `.then`
 *     branches never fire, the spinner runs forever, the form never
 *     navigates. Reporter's exact symptom on #1419.
 *   - Every panel chrome action button that drives a JSON action
 *     (Notes pane, ban/comm unblock, admin/mod delete, group-ban
 *     dispatcher, server refresh, comment edit, …) is dead-on-arrival.
 *
 * Steam login is unaffected because the Steam OpenID round-trip is
 * server-side redirects with no JS dependency on `Actions.*`.
 *
 * The bug only surfaces under the production Docker image — the
 * `zz-sbpp-prod.conf` is loaded by `docker/Dockerfile.prod` only;
 * the dev compose stack uses a different Apache config without
 * the deny union, and tarball installs don't ship Apache at all.
 *
 * Test contracts
 * --------------
 *
 * Three test methods, all pure file-scanning (no DB / Smarty / vendor
 * bring-up). Extends `PHPUnit\Framework\TestCase` directly (not
 * `ApiTestCase`) so test discovery + CI scheduling stay cheap.
 *
 *   1. {@see testFilesMatchRegexesDoNotShadowPublishedBrowserAssets}
 *      — the structural gate. Every `<FilesMatch>` regex extracted
 *      from the conf is run via PCRE against every published
 *      browser-asset basename under `web/scripts/` and
 *      `web/themes/default/js/`. A match on any is the regression
 *      class #1419 belongs to and fails the build with the offending
 *      regex + asset basename pair named in the message.
 *   2. {@see testFilesExactNamesDoNotShadowPublishedBrowserAssets}
 *      — sister gate for the `<Files "exact-name">` shape. The conf
 *      currently uses one (`<Files "config.php">`); a future
 *      contributor adding `<Files "api-contract.js">` directly would
 *      bypass the FilesMatch gate but hit this one.
 *   3. {@see testHistoricalApiContractDenyPatternStaysOut} —
 *      forward-looking spot-check pinning the literal substring
 *      `api-contract` doesn't reappear inside any `<FilesMatch>` /
 *      `<Files>` directive. Narrower than (1) + (2); reads as a
 *      direct #1419 reference at PR-review time.
 *
 * Plus {@see testIntendedDenyTargetsStillMatch} as a sanity check —
 * a future refactor of the union regex must not silently delete the
 * actual hardening (composer.json leaking deps, phpstan.neon leaking
 * static-analysis config). Same bug class, opposite direction.
 */
final class ProdApacheConfigTest extends TestCase
{
    /**
     * `ROOT` (defined in `tests/bootstrap.php`) points at `web/`. The
     * conf file lives at the repo root under `docker/apache/`, one
     * level up from `web/`. PHP's filesystem functions resolve `..`
     * components fine; the path stays a literal for clarity.
     */
    private const CONF_PATH = ROOT . '../docker/apache/sbpp-prod.conf';

    /**
     * Pull every `<FilesMatch "REGEX">` block out of the conf file and
     * return the inner regex string.
     *
     * Apache's `<FilesMatch>` directive accepts a PCRE-compatible
     * regex with the same syntax PHP's `preg_match` uses, so each
     * extracted regex can be wrapped in delimiters and run directly
     * against candidate basenames. `(?i)` inline modifiers are
     * supported on both engines.
     *
     * @return list<string>
     */
    private static function readFilesMatchRegexes(): array
    {
        $contents = (string) file_get_contents(self::CONF_PATH);
        if ($contents === '') {
            return [];
        }
        $count = preg_match_all('/<FilesMatch\s+"([^"]+)"\s*>/i', $contents, $m);
        if ($count === false || $count === 0) {
            return [];
        }
        return $m[1];
    }

    /**
     * Pull every `<Files "exact-name">` block (literal basename match,
     * not regex). The conf currently uses one — `<Files "config.php">`
     * — but a future addition that lands an exact-name deny on a
     * published asset basename would be the same bug class.
     *
     * @return list<string>
     */
    private static function readFilesExactNames(): array
    {
        $contents = (string) file_get_contents(self::CONF_PATH);
        if ($contents === '') {
            return [];
        }
        // `<Files` not followed by `Match` (which the FilesMatch
        // extractor handles separately).
        $count = preg_match_all('/<Files(?!Match)\s+"([^"]+)"\s*>/i', $contents, $m);
        if ($count === false || $count === 0) {
            return [];
        }
        return $m[1];
    }

    /**
     * Discover every published browser-asset basename the chrome loads
     * via `<script src="...">`. Two source dirs:
     *
     *   - `web/scripts/*.js` — chrome JS (`api.js`, `sb.js`, the
     *     autogenerated `api-contract.js`, `comment-actions.js`,
     *     `server-tile-hydrate.js`, `server-context-menu.js`,
     *     `banlist.js`).
     *   - `web/themes/default/js/*.js` — theme-side chrome JS
     *     (`theme.js`, `lucide.min.js`).
     *
     * `*.d.ts` files are excluded by the `.js` suffix gate — those
     * are TypeScript type-defs for `tsc --checkJs`, not
     * browser-loaded assets.
     *
     * Discovered dynamically (rather than hard-coded) so the test
     * doesn't go stale when a new chrome JS file lands; new files
     * are automatically protected against the bug class.
     *
     * @return list<string>
     */
    private static function publishedBrowserAssetBasenames(): array
    {
        $names = [];
        foreach ([
            ROOT . 'scripts',
            ROOT . 'themes/default/js',
        ] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $entries = scandir($dir);
            if ($entries === false) {
                continue;
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                if (!str_ends_with($entry, '.js')) {
                    continue;
                }
                $names[] = $entry;
            }
        }
        sort($names);
        return $names;
    }

    public function testConfFileExists(): void
    {
        $this->assertFileExists(
            self::CONF_PATH,
            'docker/apache/sbpp-prod.conf must exist (the prod Docker image ships it via a2enconf zz-sbpp-prod).',
        );
    }

    /**
     * The structural regression gate — run every extracted FilesMatch
     * regex against every published asset basename. A match anywhere
     * is the bug class #1419 belongs to.
     */
    public function testFilesMatchRegexesDoNotShadowPublishedBrowserAssets(): void
    {
        $regexes = self::readFilesMatchRegexes();
        $this->assertNotEmpty(
            $regexes,
            'sbpp-prod.conf should declare at least one <FilesMatch> deny rule (composer.json / phpstan.* / etc.).',
        );

        $assets = self::publishedBrowserAssetBasenames();
        $this->assertNotEmpty(
            $assets,
            'web/scripts/ and web/themes/default/js/ should contain at least one .js file (the chrome JS).',
        );

        $hits = [];
        foreach ($regexes as $regex) {
            // `#` delimiters; escape any literal `#` inside the regex
            // (none today, but future-proofing). The Apache regex is
            // case-mode-controlled by the `(?i)` inline flag, so no
            // additional `i` modifier on the PHP side.
            $compiled = '#' . str_replace('#', '\\#', $regex) . '#';
            foreach ($assets as $basename) {
                if (preg_match($compiled, $basename) === 1) {
                    $hits[] = sprintf(
                        '<FilesMatch %s> would deny published browser asset "%s"',
                        var_export($regex, true),
                        $basename,
                    );
                }
            }
        }

        $this->assertSame(
            0,
            count($hits),
            "docker/apache/sbpp-prod.conf <FilesMatch> regex shadows one or more published browser assets — see"
            . " #1419 for the login-spinner / dead-action-button bug class. Use <Files \"exact-name\"> for root-only"
            . " configs that have a unique basename, or a path-anchored <LocationMatch> when the basename collides"
            . " with a published asset:\n - "
                . implode("\n - ", $hits),
        );
    }

    /**
     * Sister gate to (1) for the `<Files "exact-name">` shape. The
     * conf today only carries `<Files "config.php">`; this test
     * catches a future contributor who lands `<Files "api-contract.js">`
     * directly (which would bypass the regex-based gate above).
     */
    public function testFilesExactNamesDoNotShadowPublishedBrowserAssets(): void
    {
        $names = self::readFilesExactNames();
        $assets = self::publishedBrowserAssetBasenames();

        $hits = [];
        foreach ($names as $name) {
            if (in_array($name, $assets, true)) {
                $hits[] = $name;
            }
        }

        $this->assertSame(
            0,
            count($hits),
            "docker/apache/sbpp-prod.conf <Files \"...\"> exact-name deny shadows a published browser asset — see"
            . " #1419 for the bug class:\n - "
                . implode("\n - ", $hits),
        );
    }

    /**
     * Forward-looking spot-check pinning the specific historical bug
     * pattern out. Narrower than the structural gates above; reads
     * as a direct #1419 reference at PR-review time and catches the
     * specific copy-paste shape without depending on the asset
     * discovery.
     */
    public function testHistoricalApiContractDenyPatternStaysOut(): void
    {
        $contents = (string) file_get_contents(self::CONF_PATH);
        $this->assertNotEmpty($contents, 'docker/apache/sbpp-prod.conf must be readable.');

        // Match `<FilesMatch "...api-contract...">` or `<Files "...api-contract...">`.
        $count = preg_match_all('/<Files(?:Match)?\s+"[^"]*api-contract[^"]*"/i', $contents);

        $this->assertSame(
            0,
            $count === false ? -1 : $count,
            "docker/apache/sbpp-prod.conf re-introduces the #1419 deny pattern shadowing `api-contract.*`."
            . " That pattern matches the published `web/scripts/api-contract.js` (loaded by `core/header.tpl`)"
            . " and breaks every `sb.api.call(Actions.PascalName, …)` site in the panel chrome. The login"
            . " spinner runs forever; admin row actions are dead-on-arrival under the prod Docker image.",
        );
    }

    /**
     * Sanity check: the intended hardening targets stay matched.
     * Without this, a future refactor of the union regex could
     * silently delete a deny rule and leak `composer.json` /
     * `phpstan.neon` / etc. to the web — same bug class, opposite
     * direction.
     */
    public function testIntendedDenyTargetsStillMatch(): void
    {
        $regexes = self::readFilesMatchRegexes();

        // Each row pins one root-level config file the conf's
        // documented intent denies. The list mirrors the union in
        // the conf's <FilesMatch> regex AND the comment block above
        // it; new entries land in the same PR as the conf change.
        //
        // Out of scope here (conf doesn't currently deny them, and
        // widening the deny list is a separate concern):
        //   - `phpstan-baseline.neon` / `phpstan-bootstrap.php` —
        //     sensitive (suppressed-warning map / autoload glue)
        //     but not covered by the `phpstan\.` prefix-and-dot
        //     regex; would need `phpstan[-.].*`.
        //   - `web/api-contract.json` etc. — no such file exists
        //     today; the published asset is `scripts/api-contract.js`,
        //     and `<FilesMatch>` matches the basename regardless of
        //     directory (which is exactly the #1419 trap).
        $intendedTargets = [
            'composer.json',
            'composer.lock',
            'phpstan.neon',
            'phpstan.dist.neon',
            'phpunit.xml',
            'phpunit.xml.dist',
            'package.json',
            'tsconfig.json',
        ];

        $missing = [];
        foreach ($intendedTargets as $name) {
            $matched = false;
            foreach ($regexes as $regex) {
                $compiled = '#' . str_replace('#', '\\#', $regex) . '#';
                if (preg_match($compiled, $name) === 1) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $missing[] = $name;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "docker/apache/sbpp-prod.conf no longer denies one or more intended root-level config files."
            . " If the file is intentionally renamed / removed from the deny list, update both the conf"
            . " AND ProdApacheConfigTest::testIntendedDenyTargetsStillMatch in the same PR.",
        );
    }
}
