<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * #1397 — admin-mods delete-button confirm-modal wiring.
 *
 * Pre-fix the trash-can button on each mod row carried
 * `onclick="RemoveMod(this.dataset.modName, this.dataset.modId);"`,
 * an UNGUARDED reference to a JS helper deleted at #1123 D1
 * (sourcebans.js). Unlike the admins-delete sister (#1352) the
 * mods button lacked even the defensive `typeof X === 'function'`
 * guard, so every click threw a loud
 * `ReferenceError: RemoveMod is not defined` instead of failing
 * silently. The fix mirrors the canonical admins-delete shape:
 *
 *   1. Replace the inline `onclick` with the canonical `data-action`
 *      shape (`data-action="mod-delete"` + `data-mid` + `data-name`
 *      + `data-fallback-href`) so the page-tail JS dispatcher picks
 *      it up.
 *   2. Render a confirm + optional-reason `<dialog id="mod-delete-dialog">`
 *      below the table — same shape as `#admins-delete-dialog`
 *      (#1352).
 *   3. Wire `Actions.ModsRemove` in the page-tail script.
 *
 * This test renders the admin-mods page in-process and locks the
 * generated markup against future regressions:
 *
 *   - The dead `RemoveMod()` onclick is GONE.
 *   - Every row's delete button carries the correct testids +
 *     data-action + data-mid + data-name + data-fallback-href.
 *   - The confirm dialog is rendered exactly once at page level (not
 *     per-row, which would clash on `id=`).
 *   - The dialog markup matches the canonical shape: `<dialog hidden>`
 *     containing a `<form method="dialog">` carrying a textarea with
 *     `aria-required="false"` (NOT native `required`, per the
 *     "Native `required` on the textarea inside a confirm + reason
 *     <dialog> form" anti-pattern), plus Cancel + Delete buttons.
 *   - The page-tail script references `Actions.ModsRemove` (the
 *     PascalCase from `api-contract.js`), NOT a string literal.
 *   - The mod-count badge carries `data-testid="mod-count"` so the
 *     page-tail script (and E2E spec) can read / decrement it.
 *
 * Mirrors {@see AdminsDeleteDialogTest} exactly — same in-process
 * Smarty bootstrap, same `$_GET = []` teardown, same per-attribute
 * regression coverage modulo the renamed surface.
 */
final class ModsDeleteDialogTest extends ApiTestCase
{
    /** @var int mid of a mod row we can target with the delete button. */
    private int $targetMid = 0;

    /** @var string name of the seeded target mod (used to assert data-name). */
    private string $targetName = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        $this->seedTargetMod();
        $this->bootstrapSmartyTheme();
    }

    protected function tearDown(): void
    {
        $_GET = [];
        unset($GLOBALS['theme']);
        parent::tearDown();
    }

    /**
     * Headline regression guard: the inline
     * `onclick="RemoveMod(this.dataset.modName, this.dataset.modId);"`
     * is GONE from the delete-button markup. The guarded sister
     * shape `if (typeof RemoveMod === 'function') RemoveMod(...)` is
     * also banned defensively in case a future copy-paste from a
     * third-party theme reintroduces the helper-guard pattern.
     */
    public function testDeleteButtonNoLongerCallsDeadRemoveModHelper(): void
    {
        $html = $this->renderModsPage();

        $this->assertStringNotContainsString('RemoveMod(', $html,
            'Delete button must no longer reference the deleted RemoveMod() helper.');
        $this->assertStringNotContainsString('typeof RemoveMod', $html,
            'The defensive `typeof X === "function"` guard pattern is also a no-op anti-pattern.');
    }

    /**
     * The replacement wiring on each row: data-action + data-mid +
     * data-name + data-fallback-href + the testid the E2E spec
     * anchors on.
     */
    public function testDeleteButtonCarriesDataActionWiring(): void
    {
        $html = $this->renderModsPage();

        // The seeded target mod must have a delete button.
        $this->assertMatchesRegularExpression(
            '/<button [^>]*data-action="mod-delete"[^>]*data-mid="' . $this->targetMid . '"[^>]*data-testid="deletemod-btn"/',
            $html,
            'Target mod row should carry the data-action="mod-delete" wiring.'
        );

        // Verify the supporting attributes are all present.
        $this->assertStringContainsString('data-name="' . $this->targetName . '"', $html);
        $this->assertStringContainsString('data-fallback-href="index.php?p=admin&amp;c=mods"', $html,
            'The fallback URL lands the no-JS / no-dispatcher operator on the mods list ' .
            '(no legacy GET handler exists for `o=remove`).');
    }

    /**
     * The confirm + reason dialog renders exactly once on the page
     * (not per-row, which would break HTML id uniqueness).
     */
    public function testConfirmDialogRendersOncePerPage(): void
    {
        $html = $this->renderModsPage();

        // Count the number of <dialog> opening tags carrying our id;
        // the inline page-tail JS also references the id by string,
        // so a naive substring search would over-count by 1.
        $matches = preg_match_all('/<dialog[^>]*id="mod-delete-dialog"/', $html);
        $this->assertSame(1, $matches,
            'The confirm dialog must be rendered exactly once at page level (id collisions ' .
            'would otherwise break the document.getElementById lookup in the page-tail JS).');

        $this->assertStringContainsString('data-testid="mod-delete-dialog"', $html);
        $this->assertStringContainsString('data-testid="mod-delete-form"',   $html);
        $this->assertStringContainsString('data-testid="mod-delete-cancel"', $html);
        $this->assertStringContainsString('data-testid="mod-delete-submit"', $html);
        $this->assertStringContainsString('data-testid="mod-delete-error"',  $html);
        $this->assertStringContainsString('data-testid="mod-delete-target"', $html);
        $this->assertStringContainsString('data-testid="mod-delete-reason"', $html);
    }

    /**
     * AGENTS.md anti-pattern: "Native `required` on the textarea
     * inside a confirm + reason `<dialog>` form". The browser's own
     * pre-submit validation popover would fire BEFORE our page-tail
     * `submit` handler runs, swallowing the inline error UX. The
     * canonical shape uses `aria-required` only — even though the
     * delete-mod reason is OPTIONAL (`aria-required="false"`), the
     * attribute MUST be there as documentation that this surface
     * deliberately doesn't reach for native `required`.
     */
    public function testReasonTextareaUsesAriaRequiredNotNativeRequired(): void
    {
        $html = $this->renderModsPage();

        // Find the textarea opening tag for the reason field.
        $matched = preg_match(
            '/<textarea[^>]*id="mod-delete-reason"[^>]*>/',
            $html,
            $m
        );
        $this->assertSame(1, $matched, 'mod-delete-reason textarea must render');
        $textareaTag = $m[0];

        $this->assertStringNotContainsString(' required', $textareaTag,
            'The native `required` attribute MUST NOT be present on the reason textarea ' .
            '(see AGENTS.md "Native `required` on the textarea inside a confirm + reason ' .
            '<dialog> form" anti-pattern).');
        $this->assertStringContainsString('aria-required="false"', $textareaTag,
            'The reason field is optional for the delete-mod surface (vs required for ' .
            'bans-unban / comms-unblock); aria-required="false" documents that contract.');
    }

    /**
     * The dialog must be rendered with `hidden` so a JS failure
     * leaves it dormant — the delete affordance gracefully degrades
     * to "no JS, no delete" rather than presenting the operator with
     * an always-visible modal.
     */
    public function testDialogIsHiddenOnFirstPaint(): void
    {
        $html = $this->renderModsPage();

        // Match the full opening tag of the dialog and assert `hidden`
        // is present as a bare attribute. Smarty wraps multi-attribute
        // tags across lines, so we use a multi-line pattern.
        $matched = preg_match(
            '/<dialog[^>]*id="mod-delete-dialog"[^>]*>/s',
            $html,
            $m
        );
        $this->assertSame(1, $matched, 'mod-delete-dialog opening tag must render');
        $this->assertStringContainsString(' hidden', $m[0],
            'The dialog must be rendered with the `hidden` attribute so a JS failure ' .
            'doesn\'t leave it visible from first paint.');
    }

    /**
     * The page-tail script wires `Actions.ModsRemove` (the
     * autogenerated PascalCase symbol from `api-contract.js`) — never
     * the raw string literal `'mods.remove'`. AGENTS.md "Conventions
     * / JSON API" rule.
     */
    public function testPageTailScriptUsesActionsConstant(): void
    {
        $html = $this->renderModsPage();

        $this->assertStringContainsString('A.ModsRemove', $html,
            'The script must reference Actions.ModsRemove (the PascalCase symbol ' .
            'from api-contract.js), not a string literal.');
        // Sanity-check: we should NOT find the raw dotted string.
        $this->assertStringNotContainsString("'mods.remove'", $html,
            'String literal action names are forbidden — see AGENTS.md anti-patterns.');
    }

    /**
     * The mod count badge must carry `data-testid="mod-count"` so the
     * page-tail script can decrement it after a delete and the E2E
     * spec can read it as the pre / post-delete oracle. Without the
     * testid the badge is unreachable in a theme-agnostic way (the
     * surrounding `<p>… configured</p>` shape is too brittle to
     * regex against).
     */
    public function testCountBadgeCarriesTestid(): void
    {
        $html = $this->renderModsPage();

        $this->assertMatchesRegularExpression(
            '/<span[^>]*data-testid="mod-count"[^>]*>\s*\d+\s*<\/span>/',
            $html,
            'The mod count number must be wrapped in <span data-testid="mod-count">.'
        );
    }

    private function seedTargetMod(): void
    {
        $pdo = Fixture::rawPdo();

        // Use a name that's safe through htmlspecialchars and survives
        // the data-name attribute round-trip without escaping noise so
        // the data-name assertion stays readable.
        $this->targetName = 'DeleteTargetMod';

        $pdo->prepare(sprintf(
            'INSERT INTO `%s_mods` (name, icon, modfolder, steam_universe, enabled) VALUES (?, ?, ?, ?, ?)',
            DB_PREFIX,
        ))->execute([$this->targetName, 'default.png', 'deletetargetmod', 0, 1]);
        $this->targetMid = (int) $pdo->lastInsertId();
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

    private function renderModsPage(): string
    {
        $_GET = ['p' => 'admin', 'c' => 'mods'];
        ob_start();
        try {
            (function (): void {
                global $userbank, $theme;
                $userbank = $GLOBALS['userbank'];
                $theme    = $GLOBALS['theme'];
                require ROOT . 'pages/admin.mods.php';
            })();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }
}
