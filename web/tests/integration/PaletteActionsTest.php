<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Sbpp\View\PaletteActions;

/**
 * Issue #1304: the command palette (Ctrl/Cmd-K, `<dialog id="palette-root">`)
 * leaked admin-only navigation entries (`Admin panel`, `Add ban`) to
 * unauthenticated and partial-permission users because its `NAV_ITEMS`
 * was a hardcoded JS array in `web/themes/default/js/theme.js` with no
 * permission filter.
 *
 * The fix server-renders the entry set via `Sbpp\View\PaletteActions::for()`
 * and emits it as a JSON blob inside `<script type="application/json"
 * id="palette-actions">` in `core/footer.tpl`; theme.js reads that blob
 * at boot. This test holds the helper accountable: the same per-(user,
 * permission) gating the sibling sidebar (`web/pages/core/navbar.php` +
 * `core/navbar.tpl`) already enforced now also gates the palette.
 *
 * Each subtest pins the contract for one of the four user shapes the
 * panel actually serves:
 *   - null userbank (CSRF reject path / unhandled error before auth ran)
 *   - anonymous (logged out)
 *   - partial-permission admin (e.g. only ADMIN_LIST_SERVERS)
 *   - owner (full bypass via ADMIN_OWNER)
 *
 * The player-search half of the palette (`bans.search`) is intentionally
 * public and stays accessible regardless — the issue's leak was strictly
 * the navigation entries.
 */
final class PaletteActionsTest extends ApiTestCase
{
    /**
     * Null userbank → public entries only, zero admin entries. The CSRF
     * reject path / hard-error path can reach the chrome without an
     * authenticated user; null must be treated identically to logged-out
     * (fail closed, never let null look like an admin).
     */
    public function testNullUserBankSeesOnlyPublicEntries(): void
    {
        $entries = PaletteActions::for(null);

        $labels = self::labelsOf($entries);
        $this->assertNotContains('Admin panel', $labels,
            'Admin panel must be hidden when userbank is null (CSRF reject / error path).');
        $this->assertNotContains('Add ban', $labels,
            'Add ban must be hidden when userbank is null.');

        // Public entries that should always render. Comm blocks /
        // Submit / Appeals are also public but each rides a
        // `config.enable*` toggle — they're asserted in
        // testPublicTogglesGateEntries below.
        $this->assertContains('Dashboard', $labels);
        $this->assertContains('Servers',   $labels);
        $this->assertContains('Ban list',  $labels);
    }

    /**
     * Anonymous user (aid = -1, what `Auth::verify()` returns when the
     * cookie is missing). `CUserManager::HasAccess()` short-circuits on
     * aid <= 0; the helper must propagate that closed gate to admin
     * entries while keeping public entries reachable.
     */
    public function testAnonymousUserSeesOnlyPublicEntries(): void
    {
        $userbank = new \CUserManager(null);

        $entries = PaletteActions::for($userbank);
        $labels = self::labelsOf($entries);

        $this->assertNotContains('Admin panel', $labels,
            'Admin panel must be hidden for unauthenticated visitors (#1304 — primary leak in the issue).');
        $this->assertNotContains('Add ban', $labels,
            'Add ban must be hidden for unauthenticated visitors (#1304 — primary leak in the issue).');

        $this->assertContains('Dashboard', $labels);
        $this->assertContains('Servers',   $labels);
        $this->assertContains('Ban list',  $labels);
    }

    /**
     * The owner sees every entry (admin bypass). Mirrors the convention
     * baked into every `CheckAdminAccess(ADMIN_OWNER|…)` call site in
     * `web/includes/page-builder.php`: an owner trumps every per-flag
     * check. The helper OR's `ADMIN_OWNER` into each admin entry's
     * mask before calling `HasAccess`, so an owner sees every gated
     * entry without the catalog having to track the bypass.
     */
    public function testOwnerSeesEveryEntry(): void
    {
        $this->loginAsAdmin();
        /** @var \CUserManager $userbank */
        $userbank = $GLOBALS['userbank'];

        $entries = PaletteActions::for($userbank);
        $labels = self::labelsOf($entries);

        $this->assertContains('Admin panel', $labels,
            'Owner must see Admin panel — ADMIN_OWNER bypass.');
        $this->assertContains('Add ban', $labels,
            'Owner must see Add ban — ADMIN_OWNER bypass.');
        $this->assertContains('Dashboard', $labels);
        $this->assertContains('Servers',   $labels);
        $this->assertContains('Ban list',  $labels);
    }

    /**
     * Partial-permission admin: holds ADMIN_LIST_SERVERS but NOT
     * ADMIN_OWNER and NOT ADMIN_ADD_BAN. The "Add ban" palette entry
     * is gated on `ADMIN_OWNER | ADMIN_ADD_BAN` — must stay hidden.
     * The "Admin panel" entry is gated on ALL_WEB (any web flag); a
     * holder of any single flag IS an admin per `is_admin()` so the
     * panel landing IS reachable for them — surfacing the entry in
     * the palette matches the sidebar (`navbar.php` ships the
     * "Admin Panel" entry to every `is_admin()` user).
     */
    public function testPartialPermissionAdminHidesAddBan(): void
    {
        $aid = $this->createAdminWithFlags(ADMIN_LIST_SERVERS);
        $this->loginAs($aid);
        /** @var \CUserManager $userbank */
        $userbank = $GLOBALS['userbank'];

        $entries = PaletteActions::for($userbank);
        $labels = self::labelsOf($entries);

        $this->assertNotContains('Add ban', $labels,
            'A non-owner admin lacking ADMIN_ADD_BAN must NOT see the Add ban palette entry (#1304).');

        // Admin panel surfaces because ALL_WEB matches any web flag —
        // the user IS an admin (sidebar's same gate).
        $this->assertContains('Admin panel', $labels,
            'Any admin (any web flag) reaches the admin landing — entry surfaces in the palette to match navbar.tpl.');

        // Public entries unaffected.
        $this->assertContains('Dashboard', $labels);
        $this->assertContains('Servers',   $labels);
    }

    /**
     * Comm blocks / Submit / Appeals each ride a `config.enable*`
     * boolean in `sb_settings` — same gate `web/pages/core/navbar.php`
     * already honours so the sidebar drops them on installs that
     * disabled the surface. The palette must agree (otherwise a
     * disabled-comms install would still surface "Comm blocks" in the
     * palette and land the user on a blank route).
     *
     * `Config::init` reads `sb_settings` at bootstrap; this test
     * mutates the row, re-inits the cache, and asserts each toggle
     * gates the corresponding entry. Order intentionally tests one
     * toggle at a time so a regression in one branch doesn't mask the
     * others.
     */
    public function testPublicTogglesGateEntries(): void
    {
        $userbank = new \CUserManager(null);

        // Baseline: every config.enable* defaults to truthy from
        // data.sql (Fixture::reset re-seeds it).
        $labels = self::labelsOf(PaletteActions::for($userbank));
        $this->assertContains('Comm blocks',   $labels);
        $this->assertContains('Submit a ban',  $labels);
        $this->assertContains('Appeals',       $labels);

        $this->setSetting('config.enablecomms', '0');
        \Sbpp\Config::init($GLOBALS['PDO']);
        $labels = self::labelsOf(PaletteActions::for($userbank));
        $this->assertNotContains('Comm blocks', $labels,
            'config.enablecomms=0 must drop Comm blocks from the palette (mirrors navbar.tpl).');

        $this->setSetting('config.enablesubmit', '0');
        \Sbpp\Config::init($GLOBALS['PDO']);
        $labels = self::labelsOf(PaletteActions::for($userbank));
        $this->assertNotContains('Submit a ban', $labels,
            'config.enablesubmit=0 must drop Submit a ban from the palette.');

        $this->setSetting('config.enableprotest', '0');
        \Sbpp\Config::init($GLOBALS['PDO']);
        $labels = self::labelsOf(PaletteActions::for($userbank));
        $this->assertNotContains('Appeals', $labels,
            'config.enableprotest=0 must drop Appeals from the palette.');
    }

    /**
     * The wire-format contract the JSON blob promises: every entry is
     * an `{icon, label, href}` triple — no extra keys leak (e.g. the
     * raw `permission` mask, which the client must never use as a
     * gate). The server is the single source of truth for visibility.
     */
    public function testEntryShapeIsExactlyIconLabelHref(): void
    {
        $entries = PaletteActions::for(null);

        $this->assertNotEmpty($entries);
        foreach ($entries as $entry) {
            $this->assertSame(['icon', 'label', 'href'], array_keys($entry),
                'Each entry must expose exactly icon/label/href to the wire — no permission/config keys (the client must never gate on them; the gate is server-side).');
            $this->assertIsString($entry['icon']);
            $this->assertIsString($entry['label']);
            $this->assertIsString($entry['href']);
            $this->assertNotSame('', $entry['icon']);
            $this->assertNotSame('', $entry['label']);
            $this->assertNotSame('', $entry['href']);
        }
    }

    /**
     * Direct rendering check: `web/pages/core/footer.php` builds the
     * blob via PaletteActions and json-encodes it with
     * JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT so
     * the content can never break out of its `<script>` wrapper. This
     * test asserts the encoded shape round-trips through json_decode
     * for every user state — defensive against a future PaletteActions
     * edit that introduced a non-encodable value.
     */
    public function testJsonEncodingRoundTripsForEveryUserShape(): void
    {
        // null userbank
        $this->assertRoundTrips(PaletteActions::for(null));

        // anonymous
        $this->assertRoundTrips(PaletteActions::for(new \CUserManager(null)));

        // owner
        $this->loginAsAdmin();
        /** @var \CUserManager $owner */
        $owner = $GLOBALS['userbank'];
        $this->assertRoundTrips(PaletteActions::for($owner));
    }

    /**
     * @param list<array{icon: string, label: string, href: string}> $entries
     * @return list<string>
     */
    private static function labelsOf(array $entries): array
    {
        $labels = [];
        foreach ($entries as $entry) {
            $labels[] = $entry['label'];
        }
        return $labels;
    }

    /**
     * Verify the entries survive json_encode (with the same flag set
     * footer.php uses) + json_decode and decode to the same array.
     *
     * @param list<array{icon: string, label: string, href: string}> $entries
     */
    private function assertRoundTrips(array $entries): void
    {
        $json = json_encode(
            $entries,
            JSON_THROW_ON_ERROR
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT,
        );

        $this->assertIsString($json);
        // Defense-in-depth: the JSON_HEX_* flags must keep the
        // canonical "</script>" sentinel (and `<` / `>` / `&` / `'` /
        // `"` in general) out of the encoded blob.
        $this->assertStringNotContainsString('</script', (string) $json);
        $this->assertStringNotContainsString('<', (string) $json);
        $this->assertStringNotContainsString('>', (string) $json);

        $this->assertSame($entries, json_decode((string) $json, true));
    }

    /**
     * Insert a non-owner admin row directly via the test PDO so we can
     * exercise an arbitrary `extraflags` mask without going through the
     * `admins.add` JSON handler. Mirrors the helper in PermsHelperTest.
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
            'palette-flagged-' . $mask,
            'STEAM_0:0:' . (3_000_000 + $mask),
            password_hash('x', PASSWORD_BCRYPT),
            'palette-flagged@example.test',
            $mask,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Update a single sb_settings row directly via the test PDO. We
     * write the literal string the column expects (the column is
     * `text NOT NULL`; Config::getBool casts to bool at read-time).
     */
    private function setSetting(string $key, string $value): void
    {
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'UPDATE `%s_settings` SET value = ? WHERE setting = ?',
            DB_PREFIX,
        ));
        $stmt->execute([$value, $key]);
    }
}
