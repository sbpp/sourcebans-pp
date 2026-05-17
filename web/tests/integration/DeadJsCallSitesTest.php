<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1404: grep-guard against the dead PHP-side data fields +
 * `<script>` blobs that survived the v2.0 `sourcebans.js` deletion
 * (#1123 D1). Five files carried two cleanup shapes:
 *
 *   - **Inert dead fields** (`web/pages/page.banlist.php`,
 *     `page.commslist.php`, `page.home.php`) — built `$data['friend_ban_link']`
 *     / `$data['unban_link']` / `$data['delete_link']` / `$info['popup']`
 *     for templates that no longer consume them. Modern row-action
 *     buttons (`.row-actions` cells with `data-action="bans-unban"`
 *     / `data-action="bans-delete"` / `data-action="comms-unblock"` /
 *     `data-action="comms-delete"`) replace the legacy
 *     `CreateLinkR`-built `<a onclick=…>` blobs entirely; the dashboard
 *     reads `short_name` / `search_link` / `bid` / `sname` /
 *     `blocked_human` instead of `popup`.
 *   - **Live dead `<script>` blobs** (`admin.admins.php`,
 *     `admin.groups.php`) — emitted `<script>LoadServerHost(...)</script>`
 *     / `<script>LoadServerHostPlayersList(...)</script>` per row into
 *     the live DOM. The helpers were deleted with `sourcebans.js` at
 *     #1123 D1, so every Add Admin / Groups List page load raised
 *     `ReferenceError: LoadServerHost… is not defined` once per
 *     server / server group. Admin.groups.php also left a literal
 *     "Servers populate via the legacy LoadServerHostPlayersList
 *     hook." placeholder copy admin-facing forever.
 *
 * AGENTS.md's "Anti-patterns" block already flags the legacy 1.4.11
 * JS handler names (`LoadServerHost`, `LoadServerHostPlayersList`,
 * `BanFriendsProcess`, `UnbanBan`, `RemoveBan`, `UnGag`, `UnMute`,
 * `RemoveBlock`, `ShowBox`) as forbidden. This test pins the
 * cleanup so a future "convenient" reintroduction (a fork pasting
 * back the v1.x row-action shape) is caught at PR time rather than
 * landing as another silent `ReferenceError` flood.
 *
 * Comment-stripping discipline
 * ----------------------------
 *
 * The grep runs against the **comment-stripped** source of each PHP
 * file (`php_strip_whitespace()`) and the **`{* … *}`-stripped**
 * source of each Smarty template. Without this step the gate
 * false-fires on:
 *
 *   - The cleanup `// #1404 — ...` markers this PR itself added next
 *     to each deletion, which name the dead helpers in passing
 *     ("...the helpers `BanFriendsProcess` / `UnbanBan` /
 *     `RemoveBan` were deleted with sourcebans.js at #1123 D1...").
 *   - Pre-existing legitimate references to the legacy helper names
 *     inside historical-context comments (e.g. `page.banlist.php`
 *     line 45 explains the `?a=unban&id=…` GET fallback that
 *     superseded `UnbanBan()`).
 *
 * Stripping comments rather than line-matching by leading `//` lets
 * the gate stay file-level and survive future code re-flow without
 * tweaking the test.
 *
 * Scope notes (intentional non-matches)
 * -------------------------------------
 *
 * The guard scans only the five page handlers the issue listed —
 * not the whole repo — so legitimate sister-site references stay
 * unaffected:
 *
 *   - `admin.bans.search.php` / `admin.admins.search.php` /
 *     `admin.comms.search.php` legitimately use `$serverscript`
 *     to build a MODERN `sb.api.call(Actions.ServersHostPlayers,…)`
 *     blob — same variable name, completely different shape (no
 *     `LoadServerHost(` call literal). Those files are out of scope.
 *   - `page.servers.php` legitimately calls `__sbppLoadServerHost(`
 *     (the double-underscored vanilla replacement helper). The
 *     forbidden form is the BARE `LoadServerHost(` (no `__sbpp` prefix);
 *     `str_contains` would match the prefixed form too, so the
 *     handler isn't in the per-file map.
 *
 * No DB / session / Smarty bring-up needed; pure file scanning.
 * Extends `PHPUnit\Framework\TestCase` directly (no `ApiTestCase`)
 * so test discovery + CI scheduling stay cheap.
 */
final class DeadJsCallSitesTest extends TestCase
{
    /**
     * Map of forbidden substrings, keyed by the page handler that
     * carried them pre-#1404. Each entry pins one specific dead
     * pollution shape — assignment literal, embedded JS call form, or
     * (for the wizard-side `LoadServerHostPlayersList` echo) the
     * `<script>...` blob fragment that landed in the live DOM.
     *
     * Values are arrays so a single file can pin multiple patterns
     * (`page.banlist.php` and `page.commslist.php` both carried
     * two-or-three sibling assignments). The literals are deliberately
     * specific so the test never false-matches code-shaped
     * structures elsewhere in the file: `$data['…']` and `$info['…']`
     * assignments are the unambiguous v1.x `CreateLinkR` shape.
     *
     * @return array<string, list<string>>
     */
    private static function forbiddenPatternsByFile(): array
    {
        return [
            'page.banlist.php' => [
                "\$data['friend_ban_link']",
                "\$data['unban_link']",
                "\$data['delete_link']",
                'BanFriendsProcess(',
                'UnbanBan(',
                // Bare `RemoveBan(` (not `Sb`-prefixed). Pre-#1404
                // page.banlist.php emitted `RemoveBan('<bid>', ...)` as
                // the `<a onclick=…>` payload; sister #1402 may
                // reintroduce a Smarty helper that calls
                // `SbppRemoveBan` or similar — those wouldn't false-match.
                "RemoveBan('",
            ],
            'page.commslist.php' => [
                "\$data['unban_link']",
                "\$data['delete_link']",
                "UnGag('",
                "UnMute('",
                "RemoveBlock('",
            ],
            'page.home.php' => [
                "\$info['popup']",
                // `ShowBox(` was the toast helper the popup string
                // called into. Sister #1403 covers the broader
                // ShowBox-to-window.SBPP.showToast rewrite across
                // OTHER files in `web/pages/`; #1404's scope is just
                // this one assignment in `page.home.php`. The
                // forbidden-list is per-file (not repo-wide), so this
                // pattern only gates the home dashboard.
                'ShowBox(',
            ],
            'admin.admins.php' => [
                // The bare `<script>` blob tag that wrapped the dead
                // `LoadServerHost(...)` per-server emission. Modern
                // sister code (`admin.admins.search.php`) builds
                // `'<script>(function(){…})();</script>'` instead;
                // pinning the exact `"<script type=\"text/javascript\">"`
                // prelude scopes the gate to the deleted shape only.
                '<script type="text/javascript">',
                // The bare JS call literal. The modern replacement is
                // `__sbppLoadServerHost(` (the vanilla helper in
                // `page.servers.php` / `page.home.php`); the bare form
                // is what sourcebans.js used to provide.
                "LoadServerHost('",
            ],
            'admin.groups.php' => [
                'LoadServerHostPlayersList(',
                // The `echo "<script>";` opening tag for the
                // per-group blob. Scoped to this exact shape so the
                // gate doesn't false-match the (legitimate) inline
                // `<script>` block at the end of the file.
                'echo "<script>";',
            ],
        ];
    }

    /**
     * Strip every PHP comment + whitespace via the engine's own
     * tokenizer so my own `// #1404 — ...` explanatory comments (and
     * pre-existing historical-context comments like
     * page.banlist.php's "v1.x prompted via sourcebans.js's
     * UnbanBan() helper" note at line 45) don't false-match the
     * forbidden literals. `php_strip_whitespace` is the canonical
     * way to do this — it tokenises with the engine and drops
     * `T_COMMENT` / `T_DOC_COMMENT` / `T_WHITESPACE`. Anything that
     * survives is actual executable code.
     */
    private static function stripPhpComments(string $path): string
    {
        return php_strip_whitespace($path);
    }

    /**
     * Strip Smarty `{* ... *}` comments (greedy, multiline) before
     * grepping. Same defensiveness reason as `stripPhpComments` — my
     * own cleanup markers shouldn't false-match. Inline HTML
     * `<!-- ... -->` comments are NOT stripped (Smarty doesn't treat
     * them as comments; they survive the render and reach the
     * browser); the forbidden patterns here aren't HTML-comment-shaped
     * so this is fine.
     */
    private static function stripSmartyComments(string $contents): string
    {
        return (string) preg_replace('/\{\*.*?\*\}/s', '', $contents);
    }

    /**
     * Single-method pin. Each forbidden substring is checked against
     * the comment-stripped file contents with `str_contains` and the
     * assertion message names the exact file + pattern that fired,
     * so a regression reads as a single `assertSame(0, $hits, …)`
     * instead of cascading per-pattern failures.
     *
     * The literal expected hit count is `0` per the issue body.
     */
    public function testDeadJsCallSitesStayDeleted(): void
    {
        $pagesDir = ROOT . 'pages';
        $this->assertDirectoryExists($pagesDir, 'pages/ must live next to tests/ for the scan to find it');

        $hits = [];
        foreach (self::forbiddenPatternsByFile() as $relativePath => $patterns) {
            $fullPath = $pagesDir . '/' . $relativePath;
            $this->assertFileExists(
                $fullPath,
                "$relativePath must exist — if it was renamed/deleted, update DeadJsCallSitesTest::forbiddenPatternsByFile() in the same PR.",
            );
            $stripped = self::stripPhpComments($fullPath);

            foreach ($patterns as $pattern) {
                if (str_contains($stripped, $pattern)) {
                    $hits[] = "$relativePath contains forbidden literal " . var_export($pattern, true);
                }
            }
        }

        $this->assertSame(
            0,
            count($hits),
            "Dead JS call sites resurrected in web/pages/. See AGENTS.md \"Anti-patterns\" (legacy 1.4.11 JS handler names) + #1404 for the cleanup rationale.\n\nOffending lines:\n - "
                . implode("\n - ", $hits),
        );
    }

    /**
     * Sibling cross-check: the template halves of the two live
     * regressions stay dropped. `admin.admins.php`'s `$serverscript`
     * fed a `{$server_script nofilter}` echo at the tail of
     * `page_admin_admins_add.tpl`; `admin.groups.php`'s echoed
     * `<script>` blobs targeted a per-row `<div id="servers_{gid}">`
     * with a literal "Servers populate via the legacy
     * LoadServerHostPlayersList hook." placeholder above it. The PHP
     * half going dead without the template half going with it would
     * leave SmartyTemplateRule's "unused property" check unhappy
     * (admin.admins side) and the operator-facing placeholder copy
     * stranded (admin.groups side). Pin both.
     */
    public function testDeadTemplateSidesStayDropped(): void
    {
        $hits = [];

        $addTpl = ROOT . 'themes/default/page_admin_admins_add.tpl';
        $this->assertFileExists($addTpl);
        $addContents = self::stripSmartyComments((string) file_get_contents($addTpl));
        // `{$server_script}` / `{$server_script nofilter}` — the View
        // no longer declares this property; reintroducing the echo
        // would fail SmartyTemplateRule, but the rule's verdict only
        // surfaces under PHPStan and this gate catches the literal
        // shape immediately.
        if (preg_match('/\{\s*\$server_script\b/', $addContents)) {
            $hits[] = 'themes/default/page_admin_admins_add.tpl re-emits {$server_script}';
        }

        $groupsTpl = ROOT . 'themes/default/page_admin_groups_list.tpl';
        $this->assertFileExists($groupsTpl);
        $groupsContents = self::stripSmartyComments((string) file_get_contents($groupsTpl));
        // The literal hydration-target slot.
        if (preg_match('/<div\s+id\s*=\s*"servers_\{\$group\.gid\}"/', $groupsContents)) {
            $hits[] = 'themes/default/page_admin_groups_list.tpl re-introduces <div id="servers_{$group.gid}"> hydration slot';
        }
        // The operator-facing placeholder copy.
        if (str_contains($groupsContents, 'Servers populate via the legacy')) {
            $hits[] = 'themes/default/page_admin_groups_list.tpl re-introduces the "Servers populate via the legacy ..." placeholder copy';
        }

        $this->assertSame(
            0,
            count($hits),
            "Dead template halves resurrected. The PHP + template halves of #1404 ship together:\n - "
                . implode("\n - ", $hits),
        );
    }

    /**
     * View-DTO cross-check: `Sbpp\View\AdminAdminsAddView` no longer
     * declares a `server_script` constructor parameter. If a future
     * PR reintroduces the property without restoring the template
     * echo, SmartyTemplateRule fails on "unused public property"; if
     * it reintroduces BOTH halves, the previous two tests fire. This
     * one pins the View-side independently so the failure message
     * points at the View directly instead of cascading.
     */
    public function testAdminAdminsAddViewDoesNotCarryServerScriptProperty(): void
    {
        $viewPath = ROOT . 'includes/View/AdminAdminsAddView.php';
        $this->assertFileExists($viewPath);
        $stripped = self::stripPhpComments($viewPath);

        $this->assertDoesNotMatchRegularExpression(
            '/\$server_script\b/',
            $stripped,
            'AdminAdminsAddView must not declare a `server_script` property — the per-server LoadServerHost(...) hydration echo it fed was deleted at #1404 (see AGENTS.md "Anti-patterns" for the LoadServerHost entry).',
        );
    }
}
