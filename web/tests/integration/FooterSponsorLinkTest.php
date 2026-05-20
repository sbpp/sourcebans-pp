<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Tests\ApiTestCase;
use Smarty\Smarty;

/**
 * Issue #1417: every panel page paints `core/footer.tpl` at the bottom of
 * the document. The chrome had a single link (the SourceBans++ repo) but
 * no funding affordance — the docs site (sbpp.github.io) carries a
 * "Support SourceBans++" link on every page (`docs/src/components/Footer.astro`)
 * and a topbar heart icon, but self-hosters who land on the panel via
 * the wizard's "Open the panel" CTA never see either. #1417 mirrors the
 * docs surface on the panel side: a small muted link pointing at the
 * canonical `/sponsor/` docs page (#1416's landing surface).
 *
 * This test pins three contracts:
 *
 *   1. The rendered footer carries `data-testid="app-footer-sponsor"`
 *      (the E2E / integration anchor — never assert on visible text).
 *   2. The anchor's `href` is the canonical `https://sbpp.github.io/sponsor/`
 *      URL. Pointing at the docs anchor (not a single funding-platform
 *      URL) is the load-bearing choice that lets future Open Collective
 *      / Patreon / etc. additions ship as a data-only edit on the docs
 *      side — a regression to a hard-coded `https://github.com/sponsors/...`
 *      would silently re-bind the panel to one platform.
 *   3. The external-link contract (`target="_blank"` + `rel="noopener"`)
 *      matches the sibling SourceBans++ repo link.
 *
 * Auth-agnosticism note: the sponsor link is a chrome-level affordance
 * with no `{if}` gate on logged-in / permission state — it's identical
 * for anonymous viewers, regular admins, and owners. The
 * `testFooterSponsorLinkRendersForAnonymousVisitors` arm exercises the
 * unauthenticated path (the only state where `?p=lostpassword` /
 * `?p=login` themselves render); a paired admin arm would be redundant
 * because `core/footer.tpl` doesn't probe `$userbank` and
 * `pages/core/footer.php` reads `$userbank` only to filter the command
 * palette's "Navigate" entries (the JSON blob in
 * `<script id="palette-actions">` — a sibling concern with its own
 * dedicated test in `PaletteActionsTest`). The sponsor link's
 * universality is the contract; this single arm pins it.
 *
 * Render harness mirrors `PublicBanListRegressionTest`'s
 * `bootstrapSmartyTheme()` pattern (real Smarty against the panel's
 * template directory + per-process compile dir + the same plugin
 * registration `init.php` does) so the test exercises the actual
 * production rendering path. Process isolation via
 * `RunInSeparateProcess` + `PreserveGlobalState(false)` keeps the
 * autoloaded Smarty compile dir + the `$GLOBALS` we set from leaking
 * into sibling tests — matches the pattern used by every other test in
 * this directory that exercises a template render.
 */
final class FooterSponsorLinkTest extends ApiTestCase
{
    /**
     * Spin up a real Smarty instance configured the same way
     * `web/init.php` and `PublicBanListRegressionTest::bootstrapSmartyTheme`
     * configure it. Per-process compile dir under `sys_get_temp_dir`
     * because the docker image's `web/cache/` is owned by root and
     * tests run as the host user.
     */
    private function bootstrapTheme(): Smarty
    {
        require_once INCLUDES_PATH . '/SmartyCustomFunctions.php';
        require_once INCLUDES_PATH . '/View/View.php';
        require_once INCLUDES_PATH . '/View/Renderer.php';

        $compileDir = sys_get_temp_dir() . '/sbpp-test-smarty-footer-' . getmypid();
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

        // Sibling templates loaded indirectly via include chains
        // register these plugins on every page; the standalone footer
        // render doesn't reach them, but registering keeps the harness
        // shape identical to `PublicBanListRegressionTest` so a future
        // expansion (e.g. asserting on a partial the footer pulls in)
        // doesn't have to remember to add them.
        $theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'load_template', 'smarty_function_load_template');
        $theme->registerPlugin(Smarty::PLUGIN_FUNCTION, 'csrf_field',    'smarty_function_csrf_field');
        $theme->registerPlugin(Smarty::PLUGIN_BLOCK,    'has_access',    'smarty_block_has_access');
        $theme->registerPlugin('modifier', 'smarty_stripslashes',     'smarty_stripslashes');
        $theme->registerPlugin('modifier', 'smarty_htmlspecialchars', 'smarty_htmlspecialchars');

        return $theme;
    }

    /**
     * Render `core/footer.tpl` standalone. Assigns the variables
     * `pages/core/footer.php` would normally inject (`$git`, `$version`,
     * `$palette_actions_json`, `$theme_url`) so the template doesn't
     * dereference undefined keys under Smarty's strict-variable
     * compile (the footer's `data-version="{$version}"` attribute,
     * the `{$git}` echo, the palette-actions JSON blob, and the
     * `{$theme_url}/js/...` script src lines all need stand-ins).
     *
     * Returns the rendered HTML.
     */
    private function renderFooter(Smarty $theme): string
    {
        // Minimal but complete variable surface — matches what
        // `pages/core/footer.php` assigns at runtime so the template
        // exits cleanly under `setEscapeHtml(true)` + `setForceCompile(true)`.
        $theme->assign('version',              'test');
        $theme->assign('git',                  '');
        $theme->assign('palette_actions_json', '[]');
        $theme->assign('theme_url',            'themes/default');

        ob_start();
        try {
            $theme->display('core/footer.tpl');
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }

    /**
     * The headline regression guard: the rendered footer carries the
     * sponsor anchor with its `data-testid`, its canonical href, and
     * the external-link `target` / `rel` pair. Exercised against an
     * unauthenticated viewer — the only state where the public
     * login / lostpassword surfaces render, AND the worst-case for the
     * "no-`{if}`-gate" contract (a future `{if $isAdmin}` regression
     * would silently hide the link from exactly the visitors most
     * likely to land on the panel without ever reading the docs).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFooterSponsorLinkRendersForAnonymousVisitors(): void
    {
        // ApiTestCase::setUp() already installs an unauthenticated
        // CUserManager(null) into $GLOBALS['userbank']; belt-and-braces:
        $GLOBALS['userbank'] = new \CUserManager(null);

        $theme = $this->bootstrapTheme();
        $html  = $this->renderFooter($theme);

        $this->assertStringContainsString(
            'data-testid="app-footer-sponsor"',
            $html,
            'The rendered footer must carry the `app-footer-sponsor` testid — E2E and '
            . 'integration specs anchor on the testid, never on visible text. A regression '
            . 'that drops the attribute breaks every downstream test even if the link itself '
            . 'still renders (#1417).',
        );

        $this->assertStringContainsString(
            'href="https://sbpp.github.io/sponsor/"',
            $html,
            'The sponsor link must point at the canonical `/sponsor/` docs landing page '
            . '(#1416). Pointing at the docs anchor means future funding-platform additions '
            . '(Open Collective, Patreon, etc.) ship as data-only edits on `docs/src/data/sponsors.json` '
            . '— a regression to a single-platform URL like `https://github.com/sponsors/sbpp` '
            . 'would silently re-bind the panel to one platform and require a panel release '
            . 'to rotate (#1417).',
        );

        // Native external-link contract: matches the sibling SourceBans++
        // repo link on the same footer. `target="_blank"` opens the docs
        // in a new tab so the operator doesn't lose their place in the
        // panel; `rel="noopener"` is the minimum required to prevent the
        // new tab from accessing `window.opener` (a defence-in-depth that
        // also nukes `Referer` leakage on most browsers).
        //
        // Lookahead-based regex so the assertion is order-agnostic on
        // the anchor's attribute list — the template ships the
        // attributes across multiple lines, and a future cosmetic
        // re-ordering shouldn't break this gate. `[^>]*` matches
        // newlines inside an attribute list (PCRE negated character
        // class includes `\n` by default), so the multi-line shape
        // works without the `s` flag.
        $this->assertMatchesRegularExpression(
            '/<a\b(?=[^>]*\bdata-testid="app-footer-sponsor")(?=[^>]*\btarget="_blank")[^>]*>/',
            $html,
            'The sponsor anchor must carry `target="_blank"` so the docs open in a new tab; '
            . 'matches the sibling repo link\'s external-link contract (#1417).',
        );
        $this->assertMatchesRegularExpression(
            '/<a\b(?=[^>]*\bdata-testid="app-footer-sponsor")(?=[^>]*\brel="noopener")[^>]*>/',
            $html,
            'The sponsor anchor must carry `rel="noopener"` so the opened tab cannot access '
            . '`window.opener` — defence-in-depth that also nukes `Referer` leakage on most '
            . 'browsers. Matches the sibling repo link\'s external-link contract (#1417).',
        );

        // Pin the decorative separator's `aria-hidden` half — the
        // visual contract is a `·` between the version string and the
        // sponsor link, and screen readers MUST skip it (they would
        // otherwise announce "middle dot" between two pieces of muted
        // chrome metadata, which is noise). The `aria-hidden` attribute
        // is the load-bearing half; the literal `·` glyph itself is
        // freely changeable (e.g. to `|` or `—`).
        $this->assertMatchesRegularExpression(
            '/<span\b(?=[^>]*\bclass="app-footer__sep")(?=[^>]*\baria-hidden="true")[^>]*>/',
            $html,
            'The `·` separator between the version string and the sponsor link must carry '
            . '`aria-hidden="true"` so screen readers do not announce "middle dot" between '
            . 'two pieces of chrome metadata (#1417).',
        );
    }

    /**
     * Defence-in-depth file-shape contract — pins the template source
     * directly so a regression in the render harness (a Smarty config
     * change that swallows the variable, a typo in the testid the
     * harness's regex still matches loosely, etc.) doesn't paper over
     * a real source-file regression.
     *
     * Mirrors the `AdminServerGroupsServerCardsRenderTest` /
     * `ServerMapImageRenderTest` precedent: read the .tpl directly,
     * regex on the canonical substrings. No Smarty needed; no process
     * isolation needed.
     */
    public function testFooterTemplateCarriesTheSponsorAnchorVerbatim(): void
    {
        $tplPath = ROOT . 'themes/default/core/footer.tpl';
        $tpl     = file_get_contents($tplPath);
        $this->assertNotFalse($tpl, "footer.tpl could not be read at {$tplPath}");

        $this->assertStringContainsString(
            'data-testid="app-footer-sponsor"',
            $tpl,
            'core/footer.tpl must declare the sponsor anchor verbatim (#1417). The render-side '
            . 'test pins the runtime contract; this file-shape test pins the source so a '
            . 'render-harness regression cannot mask a real source regression.',
        );
        $this->assertStringContainsString(
            'href="https://sbpp.github.io/sponsor/"',
            $tpl,
            'core/footer.tpl must point the sponsor link at the canonical /sponsor/ docs '
            . 'landing page so future funding-platform additions stay data-only on the docs '
            . 'side (#1417, #1416).',
        );
        $this->assertStringContainsString(
            'data-lucide="heart"',
            $tpl,
            'core/footer.tpl must wire the Lucide `heart` icon next to the label so the link '
            . 'reads as a CTA hint. Lucide is hydrated by the same theme.js include further '
            . 'down in the template — no new asset request (#1417).',
        );
    }
}
