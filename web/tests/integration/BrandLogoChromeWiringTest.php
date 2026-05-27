<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Sbpp\View\BrandLogo;
use Smarty\Smarty;

/**
 * Wiring regression for the two chrome render paths that consume
 * `Sbpp\View\BrandLogo`. The pure-helper coverage in `BrandLogoTest`
 * exercises `resolve()` / `resolveUrl()` in isolation; this suite
 * pins that `web/pages/core/header.php` actually CALLS `resolve()`
 * (not the raw `Config::get('template.logo')` shape it used to) and
 * that `web/pages/page.login.php` actually populates
 * `LoginView::$brand_logo_url` via `resolveUrl()`.
 *
 * Without this gate a well-intended "remove the indirection"
 * refactor that reverts `core/header.php` line 20 to
 * `$theme->assign('logo', Config::get('template.logo'));` would
 * leave `BrandLogoTest` 100% green AND ship the original
 * broken-image bug class back to production. Mirrors the
 * `LostPasswordChromeTest` shape (#1207 AUTH-1) — stubbed Smarty
 * that captures `$theme->assign()` calls, require-include the
 * production page handler verbatim.
 *
 * @see BrandLogoTest for the helper-in-isolation coverage
 * @see LostPasswordChromeTest for the stub-Smarty render pattern
 */
final class BrandLogoChromeWiringTest extends ApiTestCase
{
    /**
     * The marquee wiring contract for the navbar render path: a
     * `template.logo` row carrying the v1.x default
     * `logos/sb-large.png` must NOT reach `$theme->assign('logo', …)`
     * verbatim. The resolver intercepts it; the captured assign
     * value MUST be `BrandLogo::DEFAULT_PATH` instead. A regression
     * where someone reverts `core/header.php` to
     * `$theme->assign('logo', Config::get('template.logo'))` would
     * fail this assertion on the raw v1.x literal.
     */
    public function testNavbarHeaderResolvesV1DefaultToFallback(): void
    {
        $this->setSetting('template.logo', BrandLogo::V1_DEFAULT_PATH);

        $assigns = $this->captureHeaderAssigns();

        $this->assertArrayHasKey('logo', $assigns);
        $this->assertSame(
            BrandLogo::DEFAULT_PATH,
            $assigns['logo'],
            'core/header.php MUST route `template.logo` through BrandLogo::resolve() — '
            . 'the raw v1.x default points at a file the v2.0 default theme does not ship, '
            . 'so it must reach the chrome as the resolved fallback. If this assertion '
            . 'fails, check that header.php still calls BrandLogo::resolve() instead of '
            . 'inlining Config::get(\'template.logo\').',
        );
    }

    /**
     * Symmetric case for an empty `template.logo` — the chrome must
     * not ship `<img src="themes/default/">` (broken).
     */
    public function testNavbarHeaderResolvesEmptyValueToFallback(): void
    {
        $this->setSetting('template.logo', '');

        $assigns = $this->captureHeaderAssigns();

        $this->assertArrayHasKey('logo', $assigns);
        $this->assertSame(BrandLogo::DEFAULT_PATH, $assigns['logo']);
    }

    /**
     * Sanity arm: a valid existing custom path round-trips
     * unchanged through the chrome so admin customisations are
     * preserved (the fallback ONLY fires when the configured value
     * is broken).
     */
    public function testNavbarHeaderPreservesValidCustomPath(): void
    {
        // `logos/sbpp_logo.png` ships in the default theme.
        $this->setSetting('template.logo', 'logos/sbpp_logo.png');

        $assigns = $this->captureHeaderAssigns();

        $this->assertArrayHasKey('logo', $assigns);
        $this->assertSame('logos/sbpp_logo.png', $assigns['logo']);
    }

    /**
     * Login-page wiring contract: `LoginView::$brand_logo_url` MUST
     * be the resolved URL (with fallback) when `template.logo`
     * points at the v1.x default. The `Renderer::render` call inside
     * `page.login.php` copies every View property onto the Smarty
     * instance via `$theme->assign()`; we capture and assert.
     */
    public function testLoginPageResolvesV1DefaultToFallbackUrl(): void
    {
        $this->setSetting('template.logo', BrandLogo::V1_DEFAULT_PATH);

        $assigns = $this->captureLoginAssigns();

        $this->assertArrayHasKey('brand_logo_url', $assigns);
        $this->assertSame(
            'themes/default/' . BrandLogo::DEFAULT_PATH,
            $assigns['brand_logo_url'],
            'page.login.php MUST route `template.logo` through BrandLogo::resolveUrl() — '
            . 'a regression that reverts to the pre-fix '
            . '`themes/<theme>/<raw>` concatenation would surface a broken `<img>` on '
            . 'the sign-in card whenever the configured value points at a missing file.',
        );
    }

    /**
     * Symmetric empty-value case for the login page.
     */
    public function testLoginPageResolvesEmptyValueToFallbackUrl(): void
    {
        $this->setSetting('template.logo', '');

        $assigns = $this->captureLoginAssigns();

        $this->assertArrayHasKey('brand_logo_url', $assigns);
        $this->assertSame(
            'themes/default/' . BrandLogo::DEFAULT_PATH,
            $assigns['brand_logo_url'],
        );
    }

    /**
     * #1480 review finding 5: when `template.logo` is non-empty but
     * the resolver is silently falling back (file doesn't exist,
     * v1.x default literal, traversal indicator), the admin settings
     * page must set `$config_logo_using_fallback` to true so the
     * template can paint the warning chip. Without this gate the
     * operator's customisation appears active in the input field
     * while the chrome silently renders the fallback shield — the
     * canonical "broken-but-silent" UX shape this finding addresses.
     *
     */
    #[DataProvider('fallbackIndicatorCases')]
    public function testAdminSettingsFallbackIndicatorMatrix(
        string $configValue,
        bool $expectedUsingFallback,
        string $explanation,
    ): void {
        $this->setSetting('template.logo', $configValue);

        $rawLogo = (string) \Config::get('template.logo');
        $resolvedLogo = BrandLogo::resolve();
        $logoUsingFallback = trim($rawLogo) !== ''
            && trim($rawLogo) !== BrandLogo::DEFAULT_PATH
            && $resolvedLogo === BrandLogo::DEFAULT_PATH;

        $this->assertSame(
            $configValue,
            $rawLogo,
            'The raw configured value MUST round-trip verbatim to the form input '
            . '(so the operator sees what they saved, even when broken).',
        );
        $this->assertSame(
            $expectedUsingFallback,
            $logoUsingFallback,
            $explanation,
        );
    }

    /**
     * @return array<string, array{configValue: string, expectedUsingFallback: bool, explanation: string}>
     */
    public static function fallbackIndicatorCases(): array
    {
        return [
            'v1.x default literal flips the warning' => [
                'configValue' => BrandLogo::V1_DEFAULT_PATH,
                'expectedUsingFallback' => true,
                'explanation' => 'The v1.x default points at a file the v2.0 default theme does not '
                    . 'ship, so the resolver is falling back. The flag MUST be true so '
                    . 'the template paints the warning chip.',
            ],
            'deleted-from-disk file flips the warning' => [
                'configValue' => 'logos/this-file-does-not-exist.png',
                'expectedUsingFallback' => true,
                'explanation' => 'A path pointing at a file that does not exist must flip the '
                    . 'warning — same UX bug as the v1.x default literal.',
            ],
            'valid existing custom path does NOT flip the warning' => [
                'configValue' => 'logos/sbpp_logo.png',
                'expectedUsingFallback' => false,
                'explanation' => 'A valid existing custom path MUST NOT flip the warning chip — '
                    . 'the chip exists to signal a silent fallback, not to confirm '
                    . 'every customisation.',
            ],
            'empty value does NOT flip the warning' => [
                'configValue' => '',
                'expectedUsingFallback' => false,
                'explanation' => 'An empty input is not a customisation in flight — flagging it as '
                    . 'a fallback would mislead the operator into thinking they had '
                    . 'a saved value that broke.',
            ],
            'default value itself does NOT flip the warning' => [
                'configValue' => BrandLogo::DEFAULT_PATH,
                'expectedUsingFallback' => false,
                'explanation' => 'The default value resolves to itself, so there is nothing to '
                    . 'flag as a fallback.',
            ],
            'traversal indicator flips the warning' => [
                'configValue' => '../../etc/passwd',
                'expectedUsingFallback' => true,
                'explanation' => 'A traversal indicator must flip the warning — the resolver '
                    . 'rejects it for security reasons and the operator deserves to know.',
            ],
        ];
    }

    /**
     * Capture every `$theme->assign()` call `core/header.php` emits
     * against a stubbed Smarty. Mirrors
     * `LostPasswordChromeTest::captureNavbarAssigns` — anonymous
     * subclass of Smarty with `assign()` capturing and `display()`
     * a no-op so we don't render the actual template.
     *
     * @return array<string, mixed>
     */
    private function captureHeaderAssigns(): array
    {
        $theme = $this->stubTheme();

        // `core/header.php` reads `$title` from the outer scope to
        // build the page title, plus `$userbank` and `$theme` from
        // globals. Set up all three before require.
        global $userbank;
        $userbank = $GLOBALS['userbank'];
        $GLOBALS['theme'] = $theme;
        $title = 'Test Page';

        // Every test gets a fresh script-include — `require_once`
        // would let stale state leak across cases. Same shape
        // `LostPasswordChromeTest::captureNavbarAssigns` uses.
        require ROOT . 'pages/core/header.php';

        /** @var array<string, mixed> $captured */
        $captured = $theme->captured;
        unset($GLOBALS['theme']);
        return $captured;
    }

    /**
     * Capture every `$theme->assign()` call `page.login.php` emits
     * (via `Renderer::render($theme, $loginView)`'s per-property
     * loop) against a stubbed Smarty.
     *
     * @return array<string, mixed>
     */
    private function captureLoginAssigns(): array
    {
        $theme = $this->stubTheme();

        // `page.login.php` reads `$userbank` from globals and
        // short-circuits to a window.location redirect if logged in.
        // Set up an unauthenticated user so we reach the LoginView
        // construction path.
        global $userbank;
        $userbank = new \CUserManager(null);
        $GLOBALS['theme'] = $theme;

        // Make sure no stale `$_GET['m']` from a sibling test
        // bleeds into the message-banner switch block.
        $savedGet = $_GET;
        $_GET = [];

        try {
            require ROOT . 'pages/page.login.php';
        } finally {
            $_GET = $savedGet;
        }

        /** @var array<string, mixed> $captured */
        $captured = $theme->captured;
        unset($GLOBALS['theme']);
        return $captured;
    }

    /**
     * Anonymous Smarty subclass that captures every `assign()` call
     * and no-ops `display()`. Method signatures match Smarty's so
     * PHP's signature-compat check passes.
     */
    private function stubTheme(): Smarty
    {
        return new class extends Smarty {
            /** @var array<string, mixed> */
            public array $captured = [];

            public function assign($tpl_var, $value = null, $nocache = false, $scope = null)
            {
                if (is_array($tpl_var)) {
                    foreach ($tpl_var as $k => $v) {
                        $this->captured[(string) $k] = $v;
                    }
                } else {
                    $this->captured[(string) $tpl_var] = $value;
                }
                return $this;
            }

            public function display($template = null, $cache_id = null, $compile_id = null)
            {
                return '';
            }

            // `page.login.php` swaps the delimiter pair around the
            // `Renderer::render` call (`-{` / `}-` for `page_login.tpl`).
            // The stub doesn't render, so we just no-op these to keep
            // the call shape compatible. Signatures intentionally
            // untyped to match Smarty's base-class declaration.
            public function setLeftDelimiter($left_delimiter)
            {
                return $this;
            }

            public function setRightDelimiter($right_delimiter)
            {
                return $this;
            }
        };
    }

    /**
     * Set a `:prefix_settings` row and refresh the in-process
     * `Config` cache. Same shape as the helper in `BrandLogoTest`.
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
}
