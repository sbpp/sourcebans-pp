<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * Issue #1421: the "Public pages" toggles on Settings > Main
 * (`config.enableprotest` and `config.enablesubmit`) gated the public
 * `?p=protest` / `?p=submit` forms but NOT the admin moderation queues
 * at `?p=admin&c=bans&section=protests` / `&section=submissions` —
 * sidebar entries stayed live, direct URL access still rendered the
 * queue, and the SELECT against `:prefix_protests` / `:prefix_submissions`
 * still fired. The fix mirrors the long-standing `config.enablegroupbanning`
 * gate on the sibling Group-ban section (added at #1239): drop the
 * sidebar entry, render the disabled-feature stub on direct URL access,
 * skip the disabled section in the smart-default selection.
 *
 * Each test renders `admin.bans.php` in a separate PHP process (the
 * handler defines top-level helpers + reads `$_GET` / `$_SESSION` /
 * `$GLOBALS` straight from the request scope, so process isolation
 * keeps cases independent — same shape `Php82DeprecationsTest` and
 * `SrvAdminsPdoParamTest` use). The stub Smarty captures
 * `display(...)` calls into a buffer; `Renderer::render(...)` echoes
 * its inline output through `display`, so anything the handler emits
 * lands in `ob_get_clean()`.
 */
final class AdminBansFeatureToggleTest extends ApiTestCase
{
    /**
     * Stub Smarty that captures `assign(...)` calls and emits a
     * deterministic marker for each `display(...)` so the tests can
     * assert on what the page handler passed through Smarty without
     * needing the real template engine + theme dir.
     *
     * The sidebar itself is rendered via
     * `$theme->display('core/admin_sidebar.tpl')` after `AdminTabs`
     * assigns the filtered `tabs` array. Pre-fix the tests asserted
     * on `data-testid="admin-tab-<slug>"` markers in the captured
     * stdout, but the stub's `display(...)` returned `''` without
     * echoing — the sidebar HTML never reached the buffer and every
     * positive assertion failed for the wrong reason.
     *
     * The fix: keep `display(...)` a no-op render-wise, but emit a
     * machine-readable marker
     * (`<!--SBPP_DISPLAY:<template>:<slugs-csv>-->`) containing the
     * `slug` of each tab. That's the smallest surface that gives the
     * test the signal it needs without spinning up a real Smarty.
     * The `Php82DeprecationsTest` / `SrvAdminsPdoParamTest` flavour
     * of the stub (assertTrue(true)-only) doesn't need this hook —
     * those tests only care that the render didn't throw.
     */
    private function makeStubTheme(): Smarty
    {
        return new class extends Smarty {
            /** @var array<string, mixed> */
            private array $captured = [];

            /** @phpstan-ignore method.childParameterType */
            public function assign($tpl_var, $value = null, $nocache = false, $scope = null)
            {
                if (is_string($tpl_var)) {
                    $this->captured[$tpl_var] = $value;
                }
                return $this;
            }

            public function display($template = null, $cache_id = null, $compile_id = null)
            {
                $tplName = is_string($template) ? $template : (string) $template;
                $slugs = '';
                $tabs = $this->captured['tabs'] ?? null;
                if (is_array($tabs)) {
                    $list = [];
                    foreach ($tabs as $tab) {
                        if (is_array($tab) && isset($tab['slug']) && is_scalar($tab['slug'])) {
                            $list[] = (string) $tab['slug'];
                        }
                    }
                    $slugs = implode(',', $list);
                }
                echo '<!--SBPP_DISPLAY:' . $tplName . ':' . $slugs . '-->';
                return '';
            }
        };
    }

    /**
     * Wire the globals every page handler under `web/pages/*` reads
     * straight from `$GLOBALS`. Mirrors
     * `SrvAdminsPdoParamTest::bootRenderHarness`.
     */
    private function bootRenderHarness(): Smarty
    {
        $theme = $this->makeStubTheme();
        $GLOBALS['theme']    = $theme;
        $GLOBALS['userbank'] = $GLOBALS['userbank'] ?? new \CUserManager(null);
        $GLOBALS['username'] = $GLOBALS['username'] ?? 'tester';

        return $theme;
    }

    /**
     * Render `admin.bans.php` with a controlled `$_GET` map and return
     * the captured stdout. Tests assert against the returned string —
     * the page handler's inline `echo` blocks land verbatim in the
     * buffer (the stub Smarty makes `display(...)` a no-op so the only
     * thing in the buffer is what the handler emitted directly).
     *
     * @param array<string, string> $get
     */
    private function renderAdminBans(array $get): string
    {
        $this->bootRenderHarness();
        $_SESSION = [];
        $_GET     = array_merge(['p' => 'admin', 'c' => 'bans'], $get);
        // The page handler reads `$_SERVER['REMOTE_ADDR']` in the
        // `importBans` POST branch we never trigger; default it anyway
        // so any future change to that branch doesn't flake the test
        // under stricter `phpunit.xml` settings.
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        ob_start();
        try {
            require ROOT . 'pages/admin.bans.php';
        } finally {
            $out = ob_get_clean();
        }
        return (string) $out;
    }

    /**
     * Mutate one `sb_settings` row + refresh the in-memory cache so
     * subsequent reads see the new value. Mirrors `LoginToggleTest`
     * and `PaletteActionsTest` — `Config::init` is the load-bearing
     * step (the page handler's `Config::getBool(...)` reads from the
     * cache).
     */
    private function setSetting(string $key, string $value): void
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'REPLACE INTO `%s_settings` (`setting`, `value`) VALUES (?, ?)',
            DB_PREFIX,
        ));
        $stmt->execute([$key, $value]);
        \Sbpp\Config::init($GLOBALS['PDO']);
    }

    /**
     * Insert a non-owner admin row with a specific extraflags mask so
     * we can exercise the smart-default cascade for a user who has,
     * say, only `ADMIN_BAN_PROTESTS` (no add-ban, no submissions).
     * Mirrors the helper in PaletteActionsTest.
     */
    private function createAdminWithFlags(int $mask): int
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, validate, extraflags, immunity)
             VALUES (?, ?, ?, -1, ?, NULL, ?, 50)',
            DB_PREFIX,
        ));
        $stmt->execute([
            'flagged-' . $mask,
            'STEAM_0:0:' . (4_000_000 + $mask),
            password_hash('x', PASSWORD_BCRYPT),
            'flagged-' . $mask . '@example.test',
            $mask,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Extract the comma-separated tab slugs from the stub's
     * `<!--SBPP_DISPLAY:core/admin_sidebar.tpl:…-->` marker. Returns
     * an empty list when the sidebar never rendered (which is itself
     * an assertable signal — every test case below either expects
     * the marker present with specific slugs, or expects a specific
     * slug missing from the marker).
     *
     * Non-greedy `(.*?)` capture: slugs themselves carry `-`
     * (`add-ban`, `group-ban`) so the slugs body can't be `[^-]+`;
     * we anchor on the closing `-->` of the comment marker instead.
     *
     * @return list<string>
     */
    private static function sidebarSlugs(string $out): array
    {
        if (preg_match('/<!--SBPP_DISPLAY:core\/admin_sidebar\.tpl:(.*?)-->/', $out, $m) !== 1) {
            return [];
        }
        $csv = trim((string) $m[1]);
        if ($csv === '') {
            return [];
        }
        return array_values(array_filter(explode(',', $csv), static fn($s) => $s !== ''));
    }

    /**
     * Baseline: with both toggles on (the `data.sql` default), the
     * sidebar `tabs` array passed to `core/admin_sidebar.tpl` must
     * carry BOTH the `protests` and `submissions` slugs. Same shape
     * `PaletteActionsTest::testPublicTogglesGateEntries` pins for the
     * sibling palette + navbar surfaces. Without this baseline the
     * negative tests below could pass for a different reason (e.g.
     * the marker format drifted) and the user-reported regression
     * would stay open.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSidebarShowsBothEntriesWhenTogglesAreOn(): void
    {
        $this->loginAsAdmin();

        $out   = $this->renderAdminBans([]);
        $slugs = self::sidebarSlugs($out);

        $this->assertContains('protests', $slugs,
            'Ban protests entry must appear in the sidebar when config.enableprotest is on (baseline for #1421).');
        $this->assertContains('submissions', $slugs,
            'Ban submissions entry must appear in the sidebar when config.enablesubmit is on (baseline for #1421).');
    }

    /**
     * Protest toggle off: sidebar drops the `protests` slug; the
     * `submissions` slug stays (the gates are independent). Mirrors
     * the `config.enablegroupbanning` shape — group-ban disappears
     * from the sidebar entirely when its toggle is off; protests +
     * submissions now follow the same contract.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSidebarHidesProtestsEntryWhenProtestDisabled(): void
    {
        $this->loginAsAdmin();
        $this->setSetting('config.enableprotest', '0');

        $out   = $this->renderAdminBans([]);
        $slugs = self::sidebarSlugs($out);

        $this->assertNotContains('protests', $slugs,
            'Ban protests entry must NOT appear in the sidebar when config.enableprotest=0 (#1421).');
        $this->assertContains('submissions', $slugs,
            'Ban submissions entry must still appear (toggles are independent).');
    }

    /**
     * Symmetric inverse of the protest-disabled case.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSidebarHidesSubmissionsEntryWhenSubmitDisabled(): void
    {
        $this->loginAsAdmin();
        $this->setSetting('config.enablesubmit', '0');

        $out   = $this->renderAdminBans([]);
        $slugs = self::sidebarSlugs($out);

        $this->assertNotContains('submissions', $slugs,
            'Ban submissions entry must NOT appear in the sidebar when config.enablesubmit=0 (#1421).');
        $this->assertContains('protests', $slugs,
            'Ban protests entry must still appear (toggles are independent).');
    }

    /**
     * Direct URL access to `?section=protests` while the toggle is off
     * must render the disabled-feature stub instead of the queue. The
     * stub names the operator-actionable setting key (so they know
     * which toggle to flip) — same shape the `group-ban` branch carries.
     *
     * Also asserts the chip row testid is absent — the chip row only
     * renders when the section's queue paint reaches the post-stub
     * branch, so its absence is the cheapest proof we short-circuited
     * before any SQL fires.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testProtestsSectionRendersDisabledStubOnDirectAccess(): void
    {
        $this->loginAsAdmin();
        $this->setSetting('config.enableprotest', '0');

        $out = $this->renderAdminBans(['section' => 'protests']);

        $this->assertStringContainsString('disabled in', $out,
            'The disabled-feature stub must surface so operators see why the queue is gone.');
        $this->assertStringContainsString('config.enableprotest', $out,
            'The stub names the load-bearing setting key so operators know which toggle to flip.');
        $this->assertStringNotContainsString('data-testid="protests-archive-tabs"', $out,
            'The protests chip row must NOT render when the section short-circuited on the disabled gate — '
            . 'its presence would prove the protest SELECT fired despite the toggle being off (#1421).');
    }

    /**
     * Symmetric: `?section=submissions` + toggle off renders the
     * submissions stub. Distinct check on the chip row's testid to
     * mirror the protests assertion.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSubmissionsSectionRendersDisabledStubOnDirectAccess(): void
    {
        $this->loginAsAdmin();
        $this->setSetting('config.enablesubmit', '0');

        $out = $this->renderAdminBans(['section' => 'submissions']);

        $this->assertStringContainsString('disabled in', $out);
        $this->assertStringContainsString('config.enablesubmit', $out,
            'The stub names config.enablesubmit (note the singular — the public-side gate uses the same key).');
        $this->assertStringNotContainsString('data-testid="submissions-archive-tabs"', $out,
            'The submissions chip row must NOT render when the section short-circuited on the disabled gate (#1421).');
    }

    /**
     * Smart-default cascade skips disabled sections. Construct an
     * admin that holds ONLY `ADMIN_BAN_PROTESTS` + `ADMIN_BAN_SUBMISSIONS`
     * (no add-ban / no owner), turn `config.enableprotest` off, and
     * render with no `?section`. Pre-fix the cascade would land on
     * `protests` because `$canProtests` was true; post-fix the
     * `&& $protestEnabled` paired check skips that arm and falls
     * through to `submissions`.
     *
     * We assert on the chip row testid because it's the cheapest
     * positive signal that the submissions queue actually rendered
     * (the alternative `<table>` row is harder to anchor on cleanly
     * with no submissions seeded).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSmartDefaultSkipsDisabledProtestSection(): void
    {
        $aid = $this->createAdminWithFlags(ADMIN_BAN_PROTESTS | ADMIN_BAN_SUBMISSIONS);
        $this->loginAs($aid);
        $this->setSetting('config.enableprotest', '0');

        $out = $this->renderAdminBans([]);

        $this->assertStringContainsString('data-testid="submissions-archive-tabs"', $out,
            'Smart-default must skip the disabled protests section and land on submissions when the user can reach it (#1421).');
        $this->assertStringNotContainsString('data-testid="protests-archive-tabs"', $out,
            'The protests chip row must NOT render — smart-default must not pick a disabled section.');
    }

    /**
     * Symmetric inverse of the protest cascade: a user with only the
     * BAN_SUBMISSIONS perm + `config.enablesubmit=0` should fall
     * through to nothing-reachable rather than landing on the
     * disabled-submissions arm of the cascade. The user does NOT hold
     * `ADMIN_BAN_PROTESTS`, so `$canProtests` is false; the cascade
     * walks past every toggle-paired arm + import + group-ban and
     * lands on the last-resort `$canSubmissions` arm — the
     * disabled-feature stub for submissions.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSmartDefaultSkipsDisabledSubmissionsSection(): void
    {
        // Admin holds ONLY the submissions permission (no protests
        // perm, no add-ban, no owner). With submissions disabled, the
        // cascade has nothing else to fall back on and lands on the
        // submissions disabled-feature stub (last-resort arm — see
        // testSmartDefaultLandsOnDisabledStubAsLastResortForSinglePermAdmin
        // below for the protests-side mirror of this scenario).
        $aid = $this->createAdminWithFlags(ADMIN_BAN_SUBMISSIONS);
        $this->loginAs($aid);
        $this->setSetting('config.enablesubmit', '0');

        $out = $this->renderAdminBans([]);

        $this->assertStringContainsString('disabled in', $out,
            'Smart-default must land on the disabled-feature stub for a single-perm admin with their only feature toggled off (#1421).');
        $this->assertStringContainsString('config.enablesubmit', $out,
            'The stub must name the operator-actionable submissions setting key.');
        $this->assertStringNotContainsString('data-testid="submissions-archive-tabs"', $out,
            'The submissions queue chip row must NOT render — smart-default must short-circuit on the disabled gate before any SQL fires.');
    }

    /**
     * #1421 review MAJOR: single-perm admin lands on the disabled-
     * feature stub as last-resort, NOT on add-ban's Access denied.
     *
     * Pre-fix the cascade had `&& $protestEnabled` on the
     * `$canProtests` arm and a final `else { $section = 'add-ban'; }`
     * — for an admin holding ONLY `ADMIN_BAN_PROTESTS`, with the
     * toggle off, every arm fell through to `add-ban`, where the
     * View's `permission_addban` check bounced them to "Access
     * denied". They saw "Access denied" on a surface they DO have
     * permission to view — the feature was just off.
     *
     * Post-fix the cascade keeps the toggle-paired arms (so a user
     * with multiple perms still prefers a working surface) but adds
     * bare `$canProtests` / `$canSubmissions` arms AFTER `$canImport`
     * / `$canGroupBan` so the disabled-feature stub wins as the
     * last-resort landing. The stub names the operator-actionable
     * setting key — more honest than the misleading Access-denied.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSmartDefaultLandsOnDisabledStubAsLastResortForSinglePermAdmin(): void
    {
        $aid = $this->createAdminWithFlags(ADMIN_BAN_PROTESTS);
        $this->loginAs($aid);
        $this->setSetting('config.enableprotest', '0');

        $out = $this->renderAdminBans([]);

        $this->assertStringContainsString('disabled in', $out,
            'Single-perm admin must land on the disabled-feature stub when their only feature is toggled off (#1421 MAJOR).');
        $this->assertStringContainsString('config.enableprotest', $out,
            'The stub must name the operator-actionable setting key so the admin knows which toggle to flip.');
        $this->assertStringNotContainsString('Access denied', $out,
            'Pre-fix the cascade fell through to add-ban and the View bounced on permission_addban — that "Access denied" was misleading; '
            . 'the feature exists, just disabled.');
    }

    /**
     * #1421 review MINOR 1: gate-order contract pinned. The feature-
     * flag check runs BEFORE the permission check — verify that an
     * admin WITHOUT `ADMIN_BAN_PROTESTS` who visits `?section=protests`
     * with the toggle off sees the disabled-feature stub (more
     * informative + operator-actionable), NOT "Access denied". An
     * admin with a stripped-down flag set like `ADMIN_LIST_SERVERS`
     * lands on the section via the direct URL (smart-default
     * wouldn't pick it for them — they don't hold the perm — but
     * `?section=protests` in the URL forces the section).
     *
     * The pre-perm-check ordering matters because Access-denied
     * hides the feature's existence ("am I missing a permission?
     * does this even work?") while the disabled-feature stub names
     * the toggle. Both are safe to surface to an authenticated
     * admin reaching the section, so the more informative one wins.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFeatureFlagCheckWinsOverPermissionCheck(): void
    {
        $aid = $this->createAdminWithFlags(ADMIN_LIST_SERVERS);
        $this->loginAs($aid);
        $this->setSetting('config.enableprotest', '0');

        $out = $this->renderAdminBans(['section' => 'protests']);

        $this->assertStringContainsString('disabled in', $out,
            'Feature-disabled stub must win over Access-denied — the toggle is operator-actionable, perm-deny is not (#1421).');
        $this->assertStringContainsString('config.enableprotest', $out,
            'The stub names the load-bearing setting key, not the missing perm.');
        $this->assertStringNotContainsString('Access denied', $out,
            'When the toggle is off, the gate-order contract is: feature-disabled WINS over Access-denied (#1421).');
    }

    /**
     * #1421 review MINOR 1 (symmetric): same gate-order contract for
     * submissions. Mirror of the protest case above.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFeatureFlagCheckWinsOverPermissionCheckForSubmissions(): void
    {
        $aid = $this->createAdminWithFlags(ADMIN_LIST_SERVERS);
        $this->loginAs($aid);
        $this->setSetting('config.enablesubmit', '0');

        $out = $this->renderAdminBans(['section' => 'submissions']);

        $this->assertStringContainsString('disabled in', $out);
        $this->assertStringContainsString('config.enablesubmit', $out);
        $this->assertStringNotContainsString('Access denied', $out);
    }

    /**
     * #1421 review MINOR 2: `?view=archive` short-circuits on the
     * disabled gate too. Pre-fix the gate-order contract could have
     * been masked by the archive sub-view rendering through a
     * different code path; assert the stub paints and the archive
     * chip row is absent (= the SQL against `:prefix_protests`
     * paginated by archive predicate didn't fire either).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testProtestsArchiveViewRendersDisabledStub(): void
    {
        $this->loginAsAdmin();
        $this->setSetting('config.enableprotest', '0');

        $out = $this->renderAdminBans(['section' => 'protests', 'view' => 'archive']);

        $this->assertStringContainsString('disabled in', $out,
            'The disabled stub must short-circuit before the archive view dispatch (#1421).');
        $this->assertStringContainsString('config.enableprotest', $out);
        $this->assertStringNotContainsString('data-testid="protests-archive-tabs"', $out,
            'The archive chip row must NOT render — its presence would prove the protest SELECT fired despite the toggle being off (#1421).');
    }

    /**
     * #1421 review MINOR 2 (symmetric): submissions archive view
     * also short-circuits on the disabled gate.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSubmissionsArchiveViewRendersDisabledStub(): void
    {
        $this->loginAsAdmin();
        $this->setSetting('config.enablesubmit', '0');

        $out = $this->renderAdminBans(['section' => 'submissions', 'view' => 'archive']);

        $this->assertStringContainsString('disabled in', $out);
        $this->assertStringContainsString('config.enablesubmit', $out);
        $this->assertStringNotContainsString('data-testid="submissions-archive-tabs"', $out);
    }

    /**
     * #1421 review MINOR 3: both toggles off simultaneously — the
     * sidebar must drop BOTH entries, not one or the other. The
     * toggles are independent and the conditional `$sections[] = ...`
     * appends in `admin.bans.php` are independent too; this test
     * pins the both-off case so a future refactor that accidentally
     * couples the two toggles (e.g. by extracting a shared
     * `$publicPagesEnabled = $protestEnabled || $submitEnabled`
     * helper) gets caught.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSidebarHidesBothEntriesWhenBothTogglesOff(): void
    {
        $this->loginAsAdmin();
        $this->setSetting('config.enableprotest', '0');
        $this->setSetting('config.enablesubmit', '0');

        $out   = $this->renderAdminBans([]);
        $slugs = self::sidebarSlugs($out);

        $this->assertNotContains('protests', $slugs,
            'Both toggles off: protests entry must be absent from the sidebar (#1421).');
        $this->assertNotContains('submissions', $slugs,
            'Both toggles off: submissions entry must be absent from the sidebar (#1421).');
    }
}
