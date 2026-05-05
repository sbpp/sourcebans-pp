<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Sbpp\View\Perms;

/**
 * Regression suite for `Sbpp\View\Perms::for()` — the helper that
 * snapshots a user's web permissions into a flat `['can_*' => bool, …]`
 * array for splatting into `Sbpp\View\<Page>View` constructors.
 *
 * The helper is the single source of truth for permission booleans
 * across every Phase B/C view in the v2.0.0 theme rollout (#1123 A3).
 * If any of the contracts below regresses, every gated UI element on
 * every page silently flips state, so this test is intentionally
 * exhaustive about the auto-discovery + owner-bypass + null-user
 * semantics.
 */
final class PermsHelperTest extends ApiTestCase
{
    /**
     * No `$userbank` means we're rendering for a request that never
     * reached the auth layer (CSRF reject, hard error). Every gate
     * must fail closed — never let a `null` look like an admin.
     */
    public function testNullUserBankReturnsAllFalse(): void
    {
        $perms = Perms::for(null);

        $this->assertNotEmpty($perms, 'Perms::for(null) must still emit the can_* keys (all false), so View constructors that splat them keep their named-args contract.');
        foreach ($perms as $key => $value) {
            $this->assertFalse($value, "Expected $key=false for null userbank, got " . var_export($value, true));
        }
    }

    /**
     * Logged-out user (aid = -1, what `Auth::verify()` returns when no
     * session exists). `CUserManager::HasAccess()` short-circuits to
     * false on aid <= 0; the helper must propagate that.
     */
    public function testAnonymousUserReturnsAllFalse(): void
    {
        $userbank = new \CUserManager(null);

        $perms = Perms::for($userbank);

        $this->assertNotEmpty($perms);
        foreach ($perms as $key => $value) {
            $this->assertFalse($value, "Expected $key=false for anonymous userbank, got " . var_export($value, true));
        }
    }

    /**
     * Auto-discovery: the helper enumerates every `ADMIN_*` constant
     * `init.php` defines from `web/configs/permissions/web.json`.
     * Adding a row to that JSON should grow the array next request
     * with NO code change here — these assertions pin the discovery
     * shape so a future refactor can't silently drop flags.
     */
    public function testKeysCoverEveryAdminConstant(): void
    {
        $perms = Perms::for(null);

        $expected = [
            // Bin in lockstep with web/configs/permissions/web.json.
            // Adding a new ADMIN_* row to the JSON should add the
            // corresponding can_* key here too.
            'can_list_admins',
            'can_add_admins',
            'can_edit_admins',
            'can_delete_admins',
            'can_list_servers',
            'can_add_server',
            'can_edit_servers',
            'can_delete_servers',
            'can_add_ban',
            'can_edit_own_bans',
            'can_edit_group_bans',
            'can_edit_all_bans',
            'can_ban_protests',
            'can_ban_submissions',
            'can_delete_ban',
            'can_unban',
            'can_ban_import',
            'can_unban_own_bans',
            'can_unban_group_bans',
            'can_list_groups',
            'can_add_group',
            'can_edit_groups',
            'can_delete_groups',
            'can_web_settings',
            'can_list_mods',
            'can_add_mods',
            'can_edit_mods',
            'can_delete_mods',
            'can_notify_sub',
            'can_notify_protest',
            'can_owner',
        ];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $perms, "Missing $key — Perms::for() must auto-discover every ADMIN_* constant.");
        }

        // Anti-leakage: ALL_WEB lives in web.json but is not an
        // ADMIN_* constant; SM_* SourceMod char flags are a different
        // permission system entirely. Neither belongs in the can_*
        // surface — keep both out so consumers don't end up with
        // confusing keys like `can_all_web` or `can_kick`.
        $this->assertArrayNotHasKey('can_all_web', $perms);
        foreach (array_keys($perms) as $key) {
            $this->assertStringStartsWith('can_', (string) $key);
        }
    }

    /**
     * The seeded fixture admin has `extraflags = ADMIN_OWNER` only
     * (see `Fixture::seedAdmin()`). Owner bypass is the convention
     * baked into every `CheckAdminAccess(ADMIN_OWNER|…)` call site in
     * `page-builder.php`; `Perms::for()` mirrors it by OR'ing
     * `ADMIN_OWNER` into every check before hitting `HasAccess()`. So
     * the resulting array must light up `can_*` for every gated
     * action — `{if $can_add_ban}` Just Works for owners without the
     * template having to also check `$can_owner`.
     */
    public function testOwnerSeesEveryCanFlag(): void
    {
        $this->loginAsAdmin();
        /** @var \CUserManager $userbank */
        $userbank = $GLOBALS['userbank'];

        $perms = Perms::for($userbank);

        foreach ($perms as $key => $value) {
            $this->assertTrue($value, "Expected $key=true for ADMIN_OWNER user, got " . var_export($value, true));
        }
    }

    /**
     * Realistic non-owner case: an admin with a narrow flag set. Only
     * the flags they hold should produce true; every other gate stays
     * false. This is the daily-driver test — most admins are NOT
     * owners and rely on per-flag granularity.
     */
    public function testNonOwnerSeesOnlyGrantedFlags(): void
    {
        $mask = ADMIN_ADD_BAN | ADMIN_EDIT_OWN_BANS;
        $aid  = $this->createAdminWithFlags($mask);
        $this->loginAs($aid);
        /** @var \CUserManager $userbank */
        $userbank = $GLOBALS['userbank'];

        $perms = Perms::for($userbank);

        $this->assertTrue($perms['can_add_ban'],       'admin holds ADMIN_ADD_BAN');
        $this->assertTrue($perms['can_edit_own_bans'], 'admin holds ADMIN_EDIT_OWN_BANS');

        $this->assertFalse($perms['can_owner'],       'admin does NOT hold ADMIN_OWNER');
        $this->assertFalse($perms['can_delete_ban'],  'admin does NOT hold ADMIN_DELETE_BAN');
        $this->assertFalse($perms['can_unban'],       'admin does NOT hold ADMIN_UNBAN');
        $this->assertFalse($perms['can_web_settings'], 'admin does NOT hold ADMIN_WEB_SETTINGS');
        $this->assertFalse($perms['can_list_admins'], 'admin does NOT hold ADMIN_LIST_ADMINS');
    }

    /**
     * Insert a non-owner admin row directly via the test PDO so we can
     * exercise an arbitrary `extraflags` mask without going through
     * the `admins.add` JSON handler (which has its own permission
     * checks and would couple this test to that handler's contract).
     * `gid = -1` keeps the LEFT JOIN to `sb_groups` empty so the
     * mask we pass in is exactly what `extraflags` ends up holding.
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
            'STEAM_0:0:' . $mask,
            password_hash('x', PASSWORD_BCRYPT),
            'flagged@example.test',
            $mask,
        ]);
        return (int) $pdo->lastInsertId();
    }
}
