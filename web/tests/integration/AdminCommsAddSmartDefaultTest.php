<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Tests\ApiTestCase;
use Smarty\Smarty;

/**
 * Player-context-menu wiring — admin.comms.php `?steam=…&type=…`
 * smart-default pre-fill (sibling of `AdminBansAddSmartDefaultTest`).
 *
 * #1395 routed the public servers list's right-click context menu
 * "Block comms" item from the chromeless iframe surface
 * (`pages/admin.blockit.php?check=…&type=0` — which actually serves
 * the post-`Actions.CommsAdd` rcon-fan-out iframe, NOT a stand-alone
 * operator page; its relative `api.php` POST resolved to
 * `/pages/api.php` → 404) onto the panel-chromed
 * `?p=admin&c=comms&steam=…&type=0` URL.
 *
 * The pre-fill happens server-side via the View DTO
 * (`prefill_steam` / `prefill_type` on `Sbpp\View\AdminCommsAddView`)
 * so the form works on the no-JS path that public-list affordances
 * inherit — the existing `?rebanid=…` shape uses a JSON action
 * (`Actions.CommsPrepareReblock`) which only works once the
 * client-side dispatcher has booted.
 *
 * This test pins the contract end-to-end (mirrors
 * `AdminBansAddSmartDefaultTest`'s shape; per-test-method process
 * isolation because `pages/admin.comms.php` declares top-level
 * helper functions that PHP can't redeclare across in-process
 * repeated includes):
 *
 * 1. **Valid STEAM_X:Y:Z pre-fills the `steam` input** and leaves
 *    the `type` <select> on its native first-option default (Mute,
 *    option value="1") with NO `selected` attribute on any option
 *    when `?type=` is absent or 0.
 * 2. **Valid `[U:1:N]` SteamID3 pre-fills the `steam` input** —
 *    this is the shape RCON `status` emits on modern Source
 *    branches, so the context menu's "Block comms" item passes
 *    through this verbatim.
 * 3. **17-digit SteamID64 pre-fills the `steam` input** — pasted /
 *    deep-linked from third-party tools.
 * 4. **IPv4 pre-fills the `steam` input** — the allowlist mirrors
 *    `admin.bans.php`'s regex for symmetry (one allowlist to
 *    maintain), even though comms doesn't ban by IP. The form's
 *    server-side validation on submit via `Actions.CommsAdd` is
 *    the load-bearing reject for an IPv4 value reaching this
 *    surface; this test pins that the pre-fill itself round-trips.
 * 5. **`?type=1|2|3` pre-selects the matching Block type option**
 *    (Mute / Gag / Silence — the `:prefix_comms.type tinyint`
 *    domain).
 * 6. **`?type=0` and other invalid values fall back to "no
 *    pre-selection"** — no `<option selected>` lands anywhere on
 *    the type select; the browser's first-option default takes
 *    over (Mute). The menu's `?type=0` bridging value (sourced
 *    from the bans-menu URL where 0=Steam ID) lands here.
 * 7. **Hostile / unrecognised SteamID content is dropped**: both
 *    the steam input value and the type select default render as
 *    if no smart-default were on the URL.
 * 8. **Bare `?p=admin&c=comms` (no smart-default args)** keeps the
 *    steam input empty + leaves the type select at its native
 *    default — regression guard against an over-eager pre-fill
 *    that fires when the smart default isn't on the URL.
 */
final class AdminCommsAddSmartDefaultTest extends ApiTestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidSteamIdPrefillsSteamInputAndLeavesTypeDefault(): void
    {
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'     => 'admin',
            'c'     => 'comms',
            'steam' => 'STEAM_0:1:23498765',
        ];

        $html = $this->renderAddCommsPage();

        $this->assertSame('STEAM_0:1:23498765', $this->extractInputValue($html, 'addcomm-steam'));
        $this->assertFalse(
            $this->isOptionSelected($html, 'addcomm-type', '1'),
            'type select must NOT mark Mute (option 1) as `selected` — first-option default fires naturally; an explicit selected attribute would diverge from the form\'s pre-#1395 shape',
        );
        $this->assertFalse($this->isOptionSelected($html, 'addcomm-type', '2'));
        $this->assertFalse($this->isOptionSelected($html, 'addcomm-type', '3'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSteamId3PrefillsSteamInput(): void
    {
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'     => 'admin',
            'c'     => 'comms',
            'steam' => '[U:1:46997531]',
        ];

        $html = $this->renderAddCommsPage();

        $this->assertSame(
            '[U:1:46997531]',
            $this->extractInputValue($html, 'addcomm-steam'),
            'SteamID3 must round-trip into the Steam input verbatim — RCON status emits this shape on modern Source branches',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSteamId64PrefillsSteamInput(): void
    {
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'     => 'admin',
            'c'     => 'comms',
            'steam' => '76561198007263259',
        ];

        $html = $this->renderAddCommsPage();

        $this->assertSame(
            '76561198007263259',
            $this->extractInputValue($html, 'addcomm-steam'),
            'SteamID64 (17 digits) must round-trip into the Steam input verbatim',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testIpv4PrefillsSteamInput(): void
    {
        // The allowlist mirrors admin.bans.php's regex verbatim
        // (single allowlist to maintain across both menu-target
        // surfaces). Comms doesn't ban by IP, but the value still
        // round-trips into the form's only address input — server-
        // side validation on submit via Actions.CommsAdd is the
        // load-bearing reject. This test is the contract pin that
        // the regex stays in sync with bans.
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'     => 'admin',
            'c'     => 'comms',
            'steam' => '203.0.113.10',
        ];

        $html = $this->renderAddCommsPage();

        $this->assertSame('203.0.113.10', $this->extractInputValue($html, 'addcomm-steam'));
    }

    /**
     * @return list<array{0: int, 1: string}>
     */
    public static function validBlockTypeProvider(): array
    {
        return [
            [1, 'Mute (voice)'],
            [2, 'Gag (chat)'],
            [3, 'Silence (chat & voice)'],
        ];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[DataProvider('validBlockTypeProvider')]
    public function testValidTypePreselectsMatchingOption(int $type, string $label): void
    {
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'     => 'admin',
            'c'     => 'comms',
            'steam' => 'STEAM_0:1:23498765',
            'type'  => (string) $type,
        ];

        $html = $this->renderAddCommsPage();

        $this->assertSame('STEAM_0:1:23498765', $this->extractInputValue($html, 'addcomm-steam'));
        $this->assertTrue(
            $this->isOptionSelected($html, 'addcomm-type', (string) $type),
            "type select must mark option {$type} ({$label}) as `selected` when `?type={$type}` is on the URL",
        );
        // Exactly one option is selected — the other two must not be.
        foreach ([1, 2, 3] as $other) {
            if ($other === $type) {
                continue;
            }
            $this->assertFalse(
                $this->isOptionSelected($html, 'addcomm-type', (string) $other),
                "option {$other} must NOT be `selected` when `?type={$type}` is on the URL",
            );
        }
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function invalidTypeProvider(): array
    {
        return [
            ['0',  'bridging value from the bans-menu URL shape (0=Steam ID over there)'],
            ['4',  'out-of-range comms type'],
            ['-1', 'negative integer'],
            ['99', 'wildly out-of-range integer'],
            ['',   'empty string (?type= without a value)'],
        ];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[DataProvider('invalidTypeProvider')]
    public function testInvalidTypeFallsBackToNoPreselection(string $invalidType, string $why): void
    {
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'     => 'admin',
            'c'     => 'comms',
            'steam' => 'STEAM_0:1:23498765',
            'type'  => $invalidType,
        ];

        $html = $this->renderAddCommsPage();

        $this->assertSame('STEAM_0:1:23498765', $this->extractInputValue($html, 'addcomm-steam'));
        foreach ([1, 2, 3] as $opt) {
            $this->assertFalse(
                $this->isOptionSelected($html, 'addcomm-type', (string) $opt),
                "option {$opt} must NOT be `selected` when `?type=` is invalid ({$why}); first-option native default fires instead",
            );
        }
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function hostilePrefillProvider(): array
    {
        return [
            ['<script>alert(1)</script>',                 'inline script tag'],
            ['STEAM_0:1:23498765"><script>alert(1)</',    'attribute escape attempt'],
            ['javascript:alert(1)',                       'javascript: URL'],
            ['STEAM_2:1:23498765',                        'wrong universe digit (Z=2)'],
            ['STEAM_0:2:23498765',                        'wrong instance digit (Y=2)'],
            ['[U:2:46997531]',                            'wrong SteamID3 universe (U:2)'],
            ['203.0.113.10 OR 1=1',                       'SQL-injection-shaped IP'],
            ['9999999999999999999999',                    'over-long digit string'],
            ['',                                          'empty string'],
            ['   ',                                       'whitespace-only'],
        ];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[DataProvider('hostilePrefillProvider')]
    public function testHostilePrefillIsDropped(string $hostileValue, string $why): void
    {
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'     => 'admin',
            'c'     => 'comms',
            'steam' => $hostileValue,
        ];

        $html = $this->renderAddCommsPage();

        $this->assertSame(
            '',
            $this->extractInputValue($html, 'addcomm-steam'),
            "hostile pre-fill ({$why}) must be dropped from the Steam input — `value=\"\"` is the contract",
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBareCommsPageLeavesFormEmpty(): void
    {
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = ['p' => 'admin', 'c' => 'comms'];

        $html = $this->renderAddCommsPage();

        $this->assertSame('', $this->extractInputValue($html, 'addcomm-steam'));
        foreach ([1, 2, 3] as $opt) {
            $this->assertFalse(
                $this->isOptionSelected($html, 'addcomm-type', (string) $opt),
                "option {$opt} must NOT be `selected` on a bare ?p=admin&c=comms — no smart-default pre-fill is active",
            );
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTypeWithoutSteamLeavesFormEmpty(): void
    {
        // `?type=2` without `?steam=` — the prefill block gates the
        // type allowlisting on a non-empty steam value reaching the
        // regex, so a bare `?type=…` doesn't pre-select anything.
        // This is the regression guard against a future refactor
        // splitting the two assignments and letting type drift
        // through without an accompanying steam value.
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'    => 'admin',
            'c'    => 'comms',
            'type' => '2',
        ];

        $html = $this->renderAddCommsPage();

        $this->assertSame('', $this->extractInputValue($html, 'addcomm-steam'));
        foreach ([1, 2, 3] as $opt) {
            $this->assertFalse(
                $this->isOptionSelected($html, 'addcomm-type', (string) $opt),
                "option {$opt} must NOT be `selected` when `?type=` is on the URL but `?steam=` isn't — the two are gated together",
            );
        }
    }

    /**
     * Smarty bootstrap — mirrors AdminBansAddSmartDefaultTest's
     * shape (same compile dir convention, same plugin registrations,
     * same auto-escape contract). Kept inline rather than extracted
     * to a shared trait because (a) both tests want their own
     * compileDir to avoid cross-test cache poisoning and (b) the
     * factored-out helper would obscure the per-test entry point.
     */
    private function bootstrapSmartyTheme(): void
    {
        require_once INCLUDES_PATH . '/SmartyCustomFunctions.php';
        require_once INCLUDES_PATH . '/View/View.php';
        require_once INCLUDES_PATH . '/View/Renderer.php';

        $compileDir = sys_get_temp_dir() . '/sbpp-test-smarty-' . getmypid();
        if (!is_dir($compileDir)) {
            mkdir($compileDir, 0o775, true);
        }

        $theme = new Smarty();
        $theme->setUseSubDirs(false);
        $theme->setCompileId('default');
        $theme->setCaching(Smarty::CACHING_OFF);
        $theme->setForceCompile(true);
        $theme->setTemplateDir(SB_THEMES . SB_THEME);
        $theme->setCompileDir($compileDir);
        $theme->setCacheDir($compileDir);
        $theme->setEscapeHtml(true);
        $theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'load_template', 'smarty_function_load_template');
        $theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'csrf_field',    'smarty_function_csrf_field');
        $theme->registerPlugin(Smarty::PLUGIN_BLOCK,    'has_access',    'smarty_block_has_access');
        $theme->registerPlugin('modifier', 'smarty_stripslashes',     'smarty_stripslashes');
        $theme->registerPlugin('modifier', 'smarty_htmlspecialchars', 'smarty_htmlspecialchars');

        $GLOBALS['theme']    = $theme;
        $GLOBALS['username'] = 'admin';
    }

    private function renderAddCommsPage(): string
    {
        ob_start();
        try {
            (function (): void {
                global $userbank, $theme;
                $userbank = $GLOBALS['userbank'];
                $theme    = $GLOBALS['theme'];
                require ROOT . 'pages/admin.comms.php';
            })();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    /**
     * Pull the `value="…"` attribute off a `<input data-testid="…">`
     * occurrence in the rendered HTML. Returns the literal attribute
     * contents — Smarty auto-escape already runs on the variable, so
     * a sanitized value (e.g. `STEAM_0:1:23498765`) lands here
     * verbatim and a hostile value (anything not on the allowlist)
     * lands as the empty string.
     */
    private function extractInputValue(string $html, string $testid): string
    {
        $quoted = preg_quote($testid, '/');
        if (preg_match('/<input[^>]*data-testid="' . $quoted . '"[^>]*\bvalue="([^"]*)"/', $html, $m)) {
            return $m[1];
        }
        // Anchor the failure on a missing input rather than silently
        // returning '' — that would make every assertion pass even
        // when the testid was renamed off the template.
        $this->fail("input with data-testid=\"{$testid}\" not found in rendered HTML");
    }

    private function isOptionSelected(string $html, string $selectTestid, string $optionValue): bool
    {
        $quotedTestid = preg_quote($selectTestid, '/');
        if (!preg_match(
            '/<select[^>]*data-testid="' . $quotedTestid . '"[^>]*>([\s\S]*?)<\/select>/',
            $html,
            $m,
        )) {
            $this->fail("select with data-testid=\"{$selectTestid}\" not found in rendered HTML");
        }
        $body        = $m[1];
        $quotedValue = preg_quote($optionValue, '/');
        return preg_match(
            '/<option[^>]*\bvalue="' . $quotedValue . '"[^>]*\bselected/',
            $body,
        ) === 1;
    }
}
