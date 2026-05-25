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
 * 7. **Issue #1440 — `?name=<player>` pre-fills the Nickname input.**
 *    Sanitisation lives in `Sbpp\Util\PlayerName::sanitisePrefill`
 *    (single-source contract across add-ban + add-comms). Strips
 *    ASCII controls (`\x00-\x1F\x7F`), C1 controls (`\x80-\x9F`),
 *    soft hyphen (U+00AD), ZWSP (U+200B), line / paragraph
 *    separators (U+2028 / U+2029), bidi formatting (U+202A-U+202E),
 *    bidi isolates (U+2066-U+2069), and BOM (U+FEFF) — the full
 *    "display-spoofing" character class. Rejects invalid UTF-8,
 *    caps at 128 codepoints (matches `:prefix_bans.name varchar(128)`,
 *    which counts codepoints under utf8mb4 — so the cap survives
 *    4-byte emoji round-trips through the column). Smarty auto-escape
 *    handles the HTML-attribute escape — `<script>` becomes
 *    `&lt;script&gt;` and never breaks out of `value="…"`.
 *    Unicode names (CJK, emoji) round-trip verbatim.
 *
 *    `?name=` is intentionally decoupled from `?steam=` — a hand-
 *    typed deep-link with only `?name=` (or with a malformed
 *    `?steam=` that falls off the allowlist) still pre-fills the
 *    Nickname input. The context menu always emits the pair, but
 *    the page handler treats them as orthogonal smart-defaults.
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
    public function testValidNamePrefillsNicknameInput(): void
    {
        // Issue #1440 — `?name=<player>` smart-default pre-fills
        // the Nickname input alongside the Steam ID.
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'       => 'admin',
            'c'       => 'bans',
            'section' => 'add-ban',
            'steam'   => 'STEAM_0:1:23498765',
            'name'    => 'CoolPlayer123',
        ];

        $html = $this->renderAddBanPage();

        $this->assertSame(
            'CoolPlayer123',
            $this->extractInputValue($html, 'addban-nickname'),
            'Nickname must pre-fill from `?name=…`',
        );
        $this->assertSame('STEAM_0:1:23498765', $this->extractInputValue($html, 'addban-steam'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testUnicodeNamePrefillsNicknameInput(): void
    {
        // Steam supports Unicode (CJK / emoji); a strict ASCII-only
        // allowlist would lock out the players who most need the
        // pre-fill. Smarty auto-escape handles the HTML escape;
        // the page handler's `mb_substr` codepoint cap is what
        // protects against multi-byte truncation.
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'       => 'admin',
            'c'       => 'bans',
            'section' => 'add-ban',
            'steam'   => 'STEAM_0:1:23498765',
            'name'    => '日本語プレイヤー',
        ];

        $html = $this->renderAddBanPage();

        $this->assertSame(
            '日本語プレイヤー',
            $this->extractInputValue($html, 'addban-nickname'),
            'Unicode (CJK) names must round-trip into the Nickname input',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHtmlInNameIsHtmlEscapedNotDropped(): void
    {
        // The contract for names is "scrub dangerous bytes, trust the
        // escape layer for everything else" — unlike Steam IDs which
        // are strict-allowlisted. `<script>alert(1)</script>` is a
        // legitimate-looking-if-rude Steam name; the contract is
        // that Smarty's auto-escape turns it into the inert
        // `&lt;script&gt;…&lt;/script&gt;` inside `value="…"`. This
        // test pins the contract — a future "drop all HTML"
        // tightening would be a regression.
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'       => 'admin',
            'c'       => 'bans',
            'section' => 'add-ban',
            'steam'   => 'STEAM_0:1:23498765',
            'name'    => '<script>alert(1)</script>',
        ];

        $html = $this->renderAddBanPage();

        // `extractInputValue` returns the literal contents of the
        // `value="…"` attribute as the browser would see them on
        // the wire — i.e. the entity-encoded form (the parser
        // decodes them back to the raw codepoints on render).
        $this->assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            $this->extractInputValue($html, 'addban-nickname'),
            'HTML in the name must be entity-escaped inside the value attribute (Smarty auto-escape contract)',
        );
        // No raw `<script` tag should appear anywhere in the
        // rendered HTML attribute context — a parser would
        // interpret it as an open script tag if the escape failed.
        // We check for the specific dangerous sequence rather than
        // the entire name, since legit content elsewhere might
        // contain the substring (e.g. an SRI hash in a header
        // <script src>).
        $this->assertStringNotContainsString(
            'value="<script>',
            $html,
            'raw `<script>` must never appear inside an HTML attribute value',
        );
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function hostileNamePrefillProvider(): array
    {
        // Each row: raw `?name=` value, expected sanitised value
        // landing in the nickname input, human-readable label.
        // Mirrors `Sbpp\Util\PlayerName::SANITISE_STRIP_REGEX` —
        // every codepoint class in the strip set has at least one
        // representative row here.
        return [
            ["\x00\x01\x02hidden",                'hidden',  'NUL/SOH/STX prefix stripped (C0)'],
            ["bob\nlogin",                        'boblogin', 'newline (log-injection vector) stripped'],
            ["bob\rfoo",                          'bobfoo',  'carriage return stripped'],
            ["bob\tfoo",                          'bobfoo',  'tab stripped'],
            ["bob\x7Ffoo",                        'bobfoo',  'DEL (0x7F) stripped'],
            ["bob\u{0085}foo",                    'bobfoo',  'U+0085 NEXT LINE (C1) stripped'],
            ["bob\u{009F}foo",                    'bobfoo',  'U+009F APPLICATION PROGRAM COMMAND (C1) stripped'],
            ["bob\u{00AD}foo",                    'bobfoo',  'U+00AD SOFT HYPHEN stripped (invisible split spoof)'],
            ["bob\u{200B}foo",                    'bobfoo',  'U+200B ZERO WIDTH SPACE stripped'],
            ["bob\u{2028}foo",                    'bobfoo',  'U+2028 LINE SEPARATOR stripped'],
            ["bob\u{2029}foo",                    'bobfoo',  'U+2029 PARAGRAPH SEPARATOR stripped'],
            ["bob\u{202A}foo",                    'bobfoo',  'U+202A LEFT-TO-RIGHT EMBEDDING (bidi) stripped'],
            ["bob\u{202E}foo",                    'bobfoo',  'U+202E RIGHT-TO-LEFT OVERRIDE (bidi spoof) stripped'],
            ["bob\u{2066}foo",                    'bobfoo',  'U+2066 LEFT-TO-RIGHT ISOLATE (bidi) stripped'],
            ["bob\u{2069}foo",                    'bobfoo',  'U+2069 POP DIRECTIONAL ISOLATE (bidi) stripped'],
            ["\u{FEFF}bob",                       'bob',     'U+FEFF BYTE ORDER MARK stripped'],
            ['   bob   ',                         'bob',     'leading / trailing whitespace trimmed'],
            [str_repeat('a', 200),                str_repeat('a', 128), 'truncated to 128 codepoints'],
            [str_repeat('日', 200),               str_repeat('日', 128), '3-byte multi-byte truncated to 128 codepoints (not bytes)'],
            [str_repeat("\u{1F600}", 200),        str_repeat("\u{1F600}", 128), '4-byte emoji truncated to 128 codepoints (mb_substr codepoint semantics)'],
        ];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[DataProvider('hostileNamePrefillProvider')]
    public function testHostileNameIsSanitised(string $rawName, string $expectedSanitised, string $why): void
    {
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'       => 'admin',
            'c'       => 'bans',
            'section' => 'add-ban',
            'steam'   => 'STEAM_0:1:23498765',
            'name'    => $rawName,
        ];

        $html = $this->renderAddBanPage();

        $this->assertSame(
            $expectedSanitised,
            $this->extractInputValue($html, 'addban-nickname'),
            "hostile name ({$why}) must sanitise to the documented shape",
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testInvalidUtf8NameIsDropped(): void
    {
        // A hostile referrer can pass `?name=%FF%FE…` which decodes
        // to bytes that aren't valid UTF-8 (`mb_check_encoding`
        // returns false). The pre-fill must drop the value entirely
        // — emitting invalid UTF-8 into the column would later trip
        // `JSON_THROW_ON_ERROR` on the audit log / toast emission
        // surfaces (cf. AGENTS.md "JSON_INVALID_UTF8_SUBSTITUTE"
        // discussion).
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'       => 'admin',
            'c'       => 'bans',
            'section' => 'add-ban',
            'steam'   => 'STEAM_0:1:23498765',
            'name'    => "\xFF\xFE\xFDinvalid utf8",
        ];

        $html = $this->renderAddBanPage();

        $this->assertSame(
            '',
            $this->extractInputValue($html, 'addban-nickname'),
            'invalid UTF-8 must drop the prefill entirely; the empty `value=""` lets the form behave as if no `?name=` was on the URL',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBareAddBanWithoutNameLeavesNicknameEmpty(): void
    {
        // Regression guard against an over-eager pre-fill that fires
        // when `?name=` isn't on the URL. The other tests cover the
        // bare-URL case for the steam input; this one is the explicit
        // anchor for the nickname input.
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = ['p' => 'admin', 'c' => 'bans', 'section' => 'add-ban'];

        $html = $this->renderAddBanPage();

        $this->assertSame(
            '',
            $this->extractInputValue($html, 'addban-nickname'),
            'Nickname must default to empty value when no `?name=` is on the URL',
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testNameWithoutSteamPrefillsNicknameOnly(): void
    {
        // The context menu always emits `&steam=` AND `&name=`
        // together, but the page handler treats them as orthogonal
        // smart-defaults: a hand-typed deep-link carrying only
        // `?name=Alice` still pre-fills the Nickname input even
        // though the Steam ID input stays empty. Documented under
        // "Issue #1440 — `?name=<player>` smart-default companion to
        // `?steam=…`" in `admin.bans.php`'s smart-default block;
        // this test pins the orthogonality.
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'       => 'admin',
            'c'       => 'bans',
            'section' => 'add-ban',
            'name'    => 'Alice',
        ];

        $html = $this->renderAddBanPage();

        $this->assertSame(
            'Alice',
            $this->extractInputValue($html, 'addban-nickname'),
            'Nickname must pre-fill from `?name=` even without `?steam=` — they are orthogonal smart-defaults',
        );
        $this->assertSame('', $this->extractInputValue($html, 'addban-steam'),
            'Steam input must stay empty when `?steam=` is missing',
        );
        $this->assertSame('', $this->extractInputValue($html, 'addban-ip'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidNameWithInvalidSteamPrefillsNicknameOnly(): void
    {
        // Sibling-to-the-above orthogonality contract: a malformed
        // `?steam=` falls off the SteamID/IP allowlist and lands as
        // empty, but the valid `?name=` survives. Without this
        // contract, a typo in the Steam ID half of a deep-link would
        // silently wipe out the operator's pre-typed nickname too.
        $this->loginAsAdmin();
        $this->bootstrapSmartyTheme();

        $_GET = [
            'p'       => 'admin',
            'c'       => 'bans',
            'section' => 'add-ban',
            'steam'   => '<script>alert(1)</script>',
            'name'    => 'Alice',
        ];

        $html = $this->renderAddBanPage();

        $this->assertSame(
            'Alice',
            $this->extractInputValue($html, 'addban-nickname'),
            'Nickname must survive even when `?steam=` falls off the allowlist',
        );
        $this->assertSame('', $this->extractInputValue($html, 'addban-steam'),
            'hostile `?steam=` must still be dropped',
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
