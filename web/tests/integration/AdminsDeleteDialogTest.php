<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * #1352 — admin-admins delete-button confirm-modal wiring.
 *
 * Pre-fix the trash-can button on each admin row carried
 * `onclick="if (typeof RemoveAdmin === 'function') RemoveAdmin(...)"`,
 * a defensive guard whose long-deleted JS function (sourcebans.js,
 * removed at #1123 D1) made every click a silent no-op. The fix:
 *
 *   1. Replace the inline `onclick` with the canonical `data-action`
 *      shape (`data-action="admins-delete"` + `data-aid` + `data-name`
 *      + `data-fallback-href`) so the page-tail JS dispatcher picks
 *      it up.
 *   2. Render a confirm + reason `<dialog id="admins-delete-dialog">`
 *      below the table — same shape as `#bans-unban-dialog` /
 *      `#comms-unblock-dialog` (#1301).
 *   3. Wire `Actions.AdminsRemove` in the page-tail script.
 *
 * This test renders the admin-admins page in-process and locks the
 * generated markup against future regressions:
 *
 *   - The dead `RemoveAdmin()` onclick is GONE.
 *   - Every row's delete button carries the correct testids +
 *     data-action + data-aid + data-name + data-fallback-href.
 *   - The confirm dialog is rendered exactly once at page level (not
 *     per-row, which would clash on `id=`).
 *   - The dialog markup matches the canonical shape: `<dialog hidden>`
 *     containing a `<form method="dialog">` carrying a textarea with
 *     `aria-required="false"` (NOT native `required`, per the
 *     "Native `required` on the textarea inside a confirm + reason
 *     <dialog> form" anti-pattern), plus Cancel + Delete buttons.
 *   - The page-tail script references `Actions.AdminsRemove` (the
 *     PascalCase from `api-contract.js`), NOT a string literal.
 *
 * Mirrors the in-process page-handler bootstrap from
 * {@see AdminAdminsSearchTest} — same Smarty wiring, same theme dir,
 * same `$_GET = []` teardown.
 */
final class AdminsDeleteDialogTest extends ApiTestCase
{
    /** @var int aid of a non-owner admin we can target with the delete button. */
    private int $targetAid = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        $this->seedTargetAdmin();
        $this->bootstrapSmartyTheme();
    }

    protected function tearDown(): void
    {
        $_GET = [];
        unset($GLOBALS['theme']);
        parent::tearDown();
    }

    /**
     * Headline regression guard: the inline `onclick="if (typeof
     * RemoveAdmin === 'function') RemoveAdmin(...)"` is GONE from the
     * delete-button markup. Future copy-paste from a third-party
     * theme that brings the helper-guard pattern back must trip this
     * test before the regression reaches users.
     */
    public function testDeleteButtonNoLongerCallsDeadRemoveAdminHelper(): void
    {
        $html = $this->renderAdminsPage();

        $this->assertStringNotContainsString('RemoveAdmin(', $html,
            'Delete button must no longer reference the deleted RemoveAdmin() helper.');
        $this->assertStringNotContainsString('typeof RemoveAdmin', $html,
            'The defensive `typeof X === "function"` guard pattern is also a no-op anti-pattern.');
    }

    /**
     * The replacement wiring on each row: data-action + data-aid +
     * data-name + data-fallback-href + the testid the E2E spec
     * anchors on.
     */
    public function testDeleteButtonCarriesDataActionWiring(): void
    {
        $html = $this->renderAdminsPage();

        // The seeded target admin must have a delete button.
        $this->assertMatchesRegularExpression(
            '/<button [^>]*data-action="admins-delete"[^>]*data-aid="' . $this->targetAid . '"[^>]*data-testid="admin-action-delete"/',
            $html,
            'Target admin row should carry the data-action="admins-delete" wiring.'
        );

        // Verify the supporting attributes are all present.
        $this->assertStringContainsString('data-name="DeleteTarget"', $html);
        $this->assertStringContainsString('data-fallback-href="index.php?p=admin&amp;c=admins"', $html,
            'The fallback URL lands the no-JS / no-dispatcher operator on the admins list ' .
            '(no legacy GET handler exists for `o=remove`).');
    }

    /**
     * The confirm + reason dialog renders exactly once on the page
     * (not per-row, which would break HTML id uniqueness).
     */
    public function testConfirmDialogRendersOncePerPage(): void
    {
        $html = $this->renderAdminsPage();

        // Count the number of <dialog> opening tags carrying our id;
        // the inline page-tail JS also references the id by string,
        // so a naive substring search would over-count by 1.
        $matches = preg_match_all('/<dialog[^>]*id="admins-delete-dialog"/', $html);
        $this->assertSame(1, $matches,
            'The confirm dialog must be rendered exactly once at page level (id collisions ' .
            'would otherwise break the document.getElementById lookup in the page-tail JS).');

        $this->assertStringContainsString('data-testid="admins-delete-dialog"', $html);
        $this->assertStringContainsString('data-testid="admins-delete-form"',   $html);
        $this->assertStringContainsString('data-testid="admins-delete-cancel"', $html);
        $this->assertStringContainsString('data-testid="admins-delete-submit"', $html);
        $this->assertStringContainsString('data-testid="admins-delete-error"',  $html);
        $this->assertStringContainsString('data-testid="admins-delete-target"', $html);
        $this->assertStringContainsString('data-testid="admins-delete-reason"', $html);
    }

    /**
     * AGENTS.md anti-pattern: "Native `required` on the textarea
     * inside a confirm + reason `<dialog>` form". The browser's own
     * pre-submit validation popover would fire BEFORE our page-tail
     * `submit` handler runs, swallowing the inline error UX. The
     * canonical shape uses `aria-required` only — even though the
     * delete-admin reason is OPTIONAL (`aria-required="false"`), the
     * attribute MUST be there as documentation that this surface
     * deliberately doesn't reach for native `required`.
     */
    public function testReasonTextareaUsesAriaRequiredNotNativeRequired(): void
    {
        $html = $this->renderAdminsPage();

        // Find the textarea opening tag for the reason field.
        $matched = preg_match(
            '/<textarea[^>]*id="admins-delete-reason"[^>]*>/',
            $html,
            $m
        );
        $this->assertSame(1, $matched, 'admins-delete-reason textarea must render');
        $textareaTag = $m[0];

        $this->assertStringNotContainsString(' required', $textareaTag,
            'The native `required` attribute MUST NOT be present on the reason textarea ' .
            '(see AGENTS.md "Native `required` on the textarea inside a confirm + reason ' .
            '<dialog> form" anti-pattern).');
        $this->assertStringContainsString('aria-required="false"', $textareaTag,
            'The reason field is optional for the delete-admin surface (vs required for ' .
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
        $html = $this->renderAdminsPage();

        // Match the full opening tag of the dialog and assert `hidden`
        // is present as a bare attribute. Smarty wraps multi-attribute
        // tags across lines, so we use a multi-line pattern.
        $matched = preg_match(
            '/<dialog[^>]*id="admins-delete-dialog"[^>]*>/s',
            $html,
            $m
        );
        $this->assertSame(1, $matched, 'admins-delete-dialog opening tag must render');
        $this->assertStringContainsString(' hidden', $m[0],
            'The dialog must be rendered with the `hidden` attribute so a JS failure ' .
            'doesn\'t leave it visible from first paint.');
    }

    /**
     * The page-tail script wires `Actions.AdminsRemove` (the
     * autogenerated PascalCase symbol from `api-contract.js`) — never
     * the raw string literal `'admins.remove'`. AGENTS.md "Conventions
     * / JSON API" rule.
     */
    public function testPageTailScriptUsesActionsConstant(): void
    {
        $html = $this->renderAdminsPage();

        $this->assertStringContainsString('A.AdminsRemove', $html,
            'The script must reference Actions.AdminsRemove (the PascalCase symbol ' .
            'from api-contract.js), not a string literal.');
        // Sanity-check: we should NOT find the raw dotted string.
        $this->assertStringNotContainsString("'admins.remove'", $html,
            'String literal action names are forbidden — see AGENTS.md anti-patterns.');
    }

    private function seedTargetAdmin(): void
    {
        $pdo  = Fixture::rawPdo();
        $hash = password_hash('x', PASSWORD_BCRYPT);
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, validate, extraflags, immunity)
             VALUES (?, ?, ?, ?, ?, NULL, 0, 0)',
            DB_PREFIX,
        ))->execute(['DeleteTarget', 'STEAM_0:0:7531', $hash, -1, 'delete@target.test']);
        $this->targetAid = (int) $pdo->lastInsertId();
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

    private function renderAdminsPage(): string
    {
        $_GET = ['p' => 'admin', 'c' => 'admins'];
        ob_start();
        try {
            (function (): void {
                global $userbank, $theme;
                $userbank = $GLOBALS['userbank'];
                $theme    = $GLOBALS['theme'];
                require ROOT . 'pages/admin.admins.php';
            })();
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }
}
