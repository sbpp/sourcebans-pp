<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Tests\ApiTestCase;
use Smarty\Smarty;

/**
 * Player-context-menu restoration — admin.bans.php `?section=add-ban`
 * smart-default pre-fill.
 *
 * The public servers list's right-click context menu (restored after
 * #1306) drops admins on
 * `?p=admin&c=bans&section=add-ban&steam=<STEAMID>&type=0` to
 * pre-populate the form without firing a JSON action. The pre-fill
 * has to happen server-side via the View DTO (`prefill_steam` /
 * `prefill_type` on `Sbpp\View\AdminBansAddView`) so the form works
 * on the no-JS path that the public-list affordances inherit — the
 * existing `?rebanid=…` shape pre-#PLAYER_CTX_MENU used a JSON
 * action (`Actions.BansPrepareReban`) which only works once the
 * client-side dispatcher has booted.
 *
 * This test pins the contract end-to-end:
 *
 * 1. **Valid STEAM_X:Y:Z pre-fills the `steam` input** with the
 *    inbound value and leaves the `type` <select> on option 0
 *    (Steam ID). The IP input stays empty.
 * 2. **Valid IPv4 + `?type=1` pre-fills the `ip` input** instead,
 *    flips the `type` <select> to option 1, and leaves the Steam
 *    input empty.
 * 3. **`[U:1:<acctid>]` pre-fills the Steam input** — the
 *    SteamID3 shape is what the legacy RCON `status` output
 *    actually returns post-2010 (the SourceMod-aware GoldSrc/Source
 *    branch), so the context menu's "Ban player" item passes
 *    through this exact shape when SteamID3 is what's on the row.
 * 4. **17-digit SteamID64 pre-fills the Steam input** — pasted /
 *    deep-linked from third-party tools.
 * 5. **Hostile / unrecognised content is dropped** so an attacker
 *    can't smuggle markup or non-allowlisted text into the form
 *    via a malformed referrer. Both inputs render with empty
 *    `value=""`.
 * 6. **Bare `?section=add-ban` (no `?steam=`)** keeps both inputs
 *    empty and the `type` <select> on option 0 — regression guard
 *    against an over-eager pre-fill that fires when the smart
 *    default isn't on the URL.
 *
 * The actual server-side validation runs in `Actions.BansAdd` on
 * submit — this surface is the pre-fill filter, not the
 * load-bearing gate. The Smarty auto-escape is the belt-and-braces
 * (the regex already allowlists the inbound shape before it
 * reaches the template).
 *
 * Each test method runs in a separate process: `pages/admin.bans.php`
 * declares top-level helper functions (`bansBuildComments`, etc.)
 * that PHP can't redeclare across in-process repeated includes.
 * Mirrors the Php82DeprecationsTest harness shape.
 */
final class AdminBansAddSmartDefaultTest extends ApiTestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidSteamIdPrefillsSteamInputAndKeepsTypeZero(): void
    {
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'       => 'admin',
            'c'       => 'bans',
            'section' => 'add-ban',
            'steam'   => 'STEAM_0:1:23498765',
        ];

        $html = $this->renderAddBanPage();

        $this->assertSame('STEAM_0:1:23498765', $this->extractInputValue($html, 'addban-steam'));
        $this->assertSame('',                   $this->extractInputValue($html, 'addban-ip'));
        $this->assertTrue(
            $this->isOptionSelected($html, 'addban-type', '0'),
            'type select must default to option 0 (Steam ID) when no `?type=` smart-default is on the URL',
        );
        $this->assertFalse($this->isOptionSelected($html, 'addban-type', '1'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidIpv4WithTypeOnePrefillsIpInputAndFlipsType(): void
    {
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'       => 'admin',
            'c'       => 'bans',
            'section' => 'add-ban',
            'steam'   => '203.0.113.10',
            'type'    => '1',
        ];

        $html = $this->renderAddBanPage();

        $this->assertSame('203.0.113.10', $this->extractInputValue($html, 'addban-ip'));
        $this->assertSame('',             $this->extractInputValue($html, 'addban-steam'));
        $this->assertTrue(
            $this->isOptionSelected($html, 'addban-type', '1'),
            'type select must flip to option 1 (IP Address) when `?type=1` is on the URL',
        );
        $this->assertFalse($this->isOptionSelected($html, 'addban-type', '0'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSteamId3PrefillsSteamInput(): void
    {
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'       => 'admin',
            'c'       => 'bans',
            'section' => 'add-ban',
            'steam'   => '[U:1:46997531]',
        ];

        $html = $this->renderAddBanPage();

        $this->assertSame('[U:1:46997531]', $this->extractInputValue($html, 'addban-steam'),
            'SteamID3 must round-trip into the Steam input verbatim — RCON status emits this shape on modern Source branches',
        );
        $this->assertSame('', $this->extractInputValue($html, 'addban-ip'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSteamId64PrefillsSteamInput(): void
    {
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'       => 'admin',
            'c'       => 'bans',
            'section' => 'add-ban',
            'steam'   => '76561198007263259',
        ];

        $html = $this->renderAddBanPage();

        $this->assertSame('76561198007263259', $this->extractInputValue($html, 'addban-steam'),
            'SteamID64 (17 digits) must round-trip into the Steam input verbatim — admin.bans.php normalises on Actions.BansAdd, not here',
        );
        $this->assertSame('', $this->extractInputValue($html, 'addban-ip'));
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
            'p'       => 'admin',
            'c'       => 'bans',
            'section' => 'add-ban',
            'steam'   => $hostileValue,
        ];

        $html = $this->renderAddBanPage();

        $this->assertSame('', $this->extractInputValue($html, 'addban-steam'),
            "hostile pre-fill ({$why}) must be dropped from the Steam input — `value=\"\"` is the contract",
        );
        $this->assertSame('', $this->extractInputValue($html, 'addban-ip'),
            "hostile pre-fill ({$why}) must be dropped from the IP input — `value=\"\"` is the contract",
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBareAddBanSectionLeavesFormEmpty(): void
    {
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = ['p' => 'admin', 'c' => 'bans', 'section' => 'add-ban'];

        $html = $this->renderAddBanPage();

        $this->assertSame('', $this->extractInputValue($html, 'addban-steam'));
        $this->assertSame('', $this->extractInputValue($html, 'addban-ip'));
        $this->assertTrue(
            $this->isOptionSelected($html, 'addban-type', '0'),
            'type select must default to option 0 (Steam ID) on a bare add-ban page',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testInvalidTypeFallsBackToSteam(): void
    {
        // `?type=2` is outside the allowlist (0 or 1). The handler
        // must coerce it to 0 (Steam ID) — anything else would
        // silently land the user on an unreachable form state.
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'       => 'admin',
            'c'       => 'bans',
            'section' => 'add-ban',
            'steam'   => 'STEAM_0:1:23498765',
            'type'    => '2',
        ];

        $html = $this->renderAddBanPage();

        $this->assertSame('STEAM_0:1:23498765', $this->extractInputValue($html, 'addban-steam'));
        $this->assertSame('',                   $this->extractInputValue($html, 'addban-ip'));
        $this->assertTrue(
            $this->isOptionSelected($html, 'addban-type', '0'),
            'unrecognised `?type=2` must coerce back to 0 (Steam ID); ?type=1 is the only non-default value',
        );
    }

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
        $theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'help_icon',     'smarty_function_help_icon');
        $theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'sb_button',     'smarty_function_sb_button');
        $theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'load_template', 'smarty_function_load_template');
        $theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'csrf_field',    'smarty_function_csrf_field');
        $theme->registerPlugin(Smarty::PLUGIN_BLOCK,    'has_access',    'smarty_block_has_access');
        $theme->registerPlugin('modifier', 'smarty_stripslashes',     'smarty_stripslashes');
        $theme->registerPlugin('modifier', 'smarty_htmlspecialchars', 'smarty_htmlspecialchars');

        $GLOBALS['theme']    = $theme;
        $GLOBALS['username'] = 'admin';
    }

    private function renderAddBanPage(): string
    {
        ob_start();
        try {
            (function (): void {
                global $userbank, $theme;
                $userbank = $GLOBALS['userbank'];
                $theme    = $GLOBALS['theme'];
                require ROOT . 'pages/admin.bans.php';
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
