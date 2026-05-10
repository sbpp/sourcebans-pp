<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * #1207 ADM-4 — admin/admins advanced search.
 *
 * The pre-fix box shipped 8 separate Search buttons, one per filter
 * row, that submitted `advType=<key>&advSearch=<value>` (one filter
 * per submit; later ones replaced earlier ones). The redesign
 * collapses them into a single submit that sends every populated
 * field at once and `web/pages/admin.admins.php` ANDs them together.
 *
 * This test locks the new contract end-to-end:
 *
 * 1. **AND semantics** — submitting `name=alice` AND `webgroup=42`
 *    narrows to admins matching BOTH filters; an admin with a
 *    matching login but the wrong web group is excluded. Pre-fix the
 *    handler dropped the second filter on the floor.
 * 2. **Backward compat** — `?advType=name&advSearch=alice` still
 *    works because the legacy shim in admin.admins.php translates it
 *    into the new `?name=alice` shape before the filters are parsed.
 *    This pins the bookmark / external-link guarantee called out in
 *    the slice's PR description.
 * 3. **Unfiltered** — bare `?p=admin&c=admins` returns every seeded
 *    admin row (regression guard against an over-eager filter).
 *
 * The test exercises the page handler in-process: it boots a Smarty
 * `$theme` matching `init.php`'s wiring, captures the rendered HTML
 * via output buffering, and counts `data-testid="admin-row"` rows.
 * That mirrors what the user sees after the form submits and is the
 * cheapest way to lock both the SQL building (#1207 ADM-4 redesign)
 * and the admin.admins.search.php pre-fill behaviour against a
 * single test surface.
 */
final class AdminAdminsSearchTest extends ApiTestCase
{
    /** @var int gid of the "Power Users" web group seeded per case. */
    private int $powerGid = 0;

    /** @var int gid of the "Bare Users" web group seeded per case. */
    private int $bareGid  = 0;

    /** @var int aid of the "alice" admin (web group: power users). */
    private int $aliceAid = 0;

    /** @var int aid of the "bob" admin (web group: bare users). */
    private int $bobAid = 0;

    /** @var int aid of the "charlie" admin (web group: power users). */
    private int $charlieAid = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        $this->seedTestAdmins();
        $this->bootstrapSmartyTheme();
    }

    protected function tearDown(): void
    {
        // Pages set $_GET; clear it so the next case starts clean.
        $_GET = [];
        unset($GLOBALS['theme']);
        parent::tearDown();
    }

    /**
     * Bare `?p=admin&c=admins` returns every admin (seed + 3 test
     * rows = 4). Regression guard: a future filter refactor that
     * accidentally adds an `AND ADM.aid > 0` style narrowing must
     * trip this case before users notice the empty list.
     */
    public function testNoFilterReturnsEveryAdmin(): void
    {
        $_GET = ['p' => 'admin', 'c' => 'admins'];

        $html = $this->renderAdminsPage();

        $this->assertSame(4, $this->extractAdminCount($html), 'unfiltered');
        $this->assertSame(4, $this->countAdminRows($html));
    }

    /**
     * One filter — the modern wire shape (`?name=…`). Locks a
     * single-input substring search against ADM.user.
     */
    public function testSingleFilterByLoginName(): void
    {
        $_GET = ['p' => 'admin', 'c' => 'admins', 'name' => 'alice'];

        $html = $this->renderAdminsPage();

        $this->assertSame(1, $this->extractAdminCount($html));
        $this->assertSame(1, $this->countAdminRows($html));
        $this->assertStringContainsString('>alice<', $html, 'alice row should render');
    }

    /**
     * The headline ADM-4 contract: two filters AND together.
     * `name=alice` matches one row, `webgroup=$powerGid` matches two
     * (alice + charlie) — combined, only alice survives.
     */
    public function testTwoFiltersAndSemantics(): void
    {
        $_GET = [
            'p'        => 'admin',
            'c'        => 'admins',
            'name'     => 'alice',
            'webgroup' => (string) $this->powerGid,
        ];

        $html = $this->renderAdminsPage();

        $this->assertSame(1, $this->extractAdminCount($html), 'name=alice AND webgroup=power');
        $this->assertSame(1, $this->countAdminRows($html));
        $this->assertStringContainsString('>alice<', $html);
        $this->assertStringNotContainsString('>charlie<', $html, 'charlie has the right web group but not the right name — must be excluded by AND.');
    }

    /**
     * `webgroup=power` alone should still return both admins in that
     * group. Counter-test to {@see testTwoFiltersAndSemantics} — it
     * makes the bound on the AND case meaningful (alice and charlie
     * both DO exist; the AND just narrows further).
     */
    public function testWebGroupFilterReturnsAllInGroup(): void
    {
        $_GET = [
            'p'        => 'admin',
            'c'        => 'admins',
            'webgroup' => (string) $this->powerGid,
        ];

        $html = $this->renderAdminsPage();

        $this->assertSame(2, $this->extractAdminCount($html), 'webgroup=power matches alice+charlie');
        $this->assertStringContainsString('>alice<',   $html);
        $this->assertStringContainsString('>charlie<', $html);
        $this->assertStringNotContainsString('>bob<',  $html, 'bob is in bare users, not power.');
    }

    /**
     * Backward-compat: legacy deep links of the form
     * `?advType=name&advSearch=alice` still narrow as expected. The
     * shim in admin.admins.php translates the legacy pair into the
     * modern `?name=alice` shape before the filter loop runs.
     */
    public function testLegacyAdvTypeUrlStillWorks(): void
    {
        $_GET = [
            'p'         => 'admin',
            'c'         => 'admins',
            'advType'   => 'name',
            'advSearch' => 'alice',
        ];

        $html = $this->renderAdminsPage();

        $this->assertSame(1, $this->extractAdminCount($html), 'legacy advType=name&advSearch=alice');
        $this->assertStringContainsString('>alice<', $html);
    }

    /**
     * Backward-compat with a multi-filter user that bookmarked one
     * filter: even when `?advType=…&advSearch=…` ALSO carries a
     * modern field (because the user pasted the bookmark and then
     * appended a new filter), the modern field wins for its slot and
     * the legacy pair fills the rest.
     */
    public function testLegacyShimDoesNotOverwriteModernFields(): void
    {
        $_GET = [
            'p'         => 'admin',
            'c'         => 'admins',
            'advType'   => 'name',
            'advSearch' => 'alice',           // legacy: name=alice
            'webgroup'  => (string) $this->powerGid, // modern: webgroup=…
        ];

        $html = $this->renderAdminsPage();

        $this->assertSame(1, $this->extractAdminCount($html),
            'shim should add name=alice, modern webgroup is honoured, AND narrows to 1.');
    }

    /**
     * Steam-ID exact / partial split. Modern shape uses
     * `steamid=<text>&steam_match=0|1`. The filter is exact when
     * `steam_match` is unset or `0`, partial when `1`.
     */
    public function testSteamIdExactMatchSplit(): void
    {
        $_GET = [
            'p'           => 'admin',
            'c'           => 'admins',
            'steamid'     => 'STEAM_0:0:1001',
            'steam_match' => '0',
        ];
        $exact = $this->renderAdminsPage();
        $this->assertSame(1, $this->extractAdminCount($exact), 'exact match on alice steamid');

        $_GET = [
            'p'           => 'admin',
            'c'           => 'admins',
            'steamid'     => 'STEAM_0:0:10', // partial substring (matches 1001/1002/1003 → all 3)
            'steam_match' => '1',
        ];
        $partial = $this->renderAdminsPage();
        $this->assertSame(3, $this->extractAdminCount($partial), 'partial match on STEAM_0:0:10 substring');
    }

    /**
     * Login-name and E-mail exact / partial split (#1231).
     *
     * Pre-#1231, only SteamID shipped a `<select>` to flip between
     * exact and partial; Login and E-mail silently substring-matched
     * with no way to ask for "give me the row whose login is
     * literally `admin`". The fix mirrors the steam_match shape onto
     * both filters as `name_match` and `admemail_match`.
     *
     * Match-mode default is `'1'` (partial) for both — i.e. when the
     * URL omits the new param, the filter behaves the way it always
     * did. That preserves every legacy bookmark and the
     * `?advType=name&advSearch=…` shim's contract; the new feature is
     * purely opt-in via `…_match=0`.
     */
    public function testLoginAndEmailExactMatchSplit(): void
    {
        // Login name: 'ali' is a substring of 'alice' but not exact.
        // Partial → 1 row (alice). Exact → 0 rows. Then 'alice' exact
        // → 1 row (the literal alice), proving exact mode resolves
        // the "find me the single admin" contract.
        $_GET = [
            'p'          => 'admin',
            'c'          => 'admins',
            'name'       => 'ali',
            'name_match' => '1',
        ];
        $partialName = $this->renderAdminsPage();
        $this->assertSame(1, $this->extractAdminCount($partialName), 'partial name=ali matches alice');

        $_GET = [
            'p'          => 'admin',
            'c'          => 'admins',
            'name'       => 'ali',
            'name_match' => '0',
        ];
        $exactNameMiss = $this->renderAdminsPage();
        $this->assertSame(0, $this->extractAdminCount($exactNameMiss), 'exact name=ali matches no admin (none is literally ali)');

        $_GET = [
            'p'          => 'admin',
            'c'          => 'admins',
            'name'       => 'alice',
            'name_match' => '0',
        ];
        $exactNameHit = $this->renderAdminsPage();
        $this->assertSame(1, $this->extractAdminCount($exactNameHit), 'exact name=alice matches alice');
        $this->assertStringContainsString('>alice<', $exactNameHit);

        // E-mail: every seeded row (admin, alice, bob, charlie) shares
        // '@example.test', so partial 'example.test' returns 4 and
        // exact 'example.test' returns 0; exact 'alice@example.test'
        // narrows back to alice.
        $_GET = [
            'p'              => 'admin',
            'c'              => 'admins',
            'admemail'       => 'example.test',
            'admemail_match' => '1',
        ];
        $partialEmail = $this->renderAdminsPage();
        $this->assertSame(4, $this->extractAdminCount($partialEmail), 'partial admemail=example.test matches all 4 admins');

        $_GET = [
            'p'              => 'admin',
            'c'              => 'admins',
            'admemail'       => 'example.test',
            'admemail_match' => '0',
        ];
        $exactEmailMiss = $this->renderAdminsPage();
        $this->assertSame(0, $this->extractAdminCount($exactEmailMiss), 'exact admemail=example.test matches no admin (none is literally that)');

        $_GET = [
            'p'              => 'admin',
            'c'              => 'admins',
            'admemail'       => 'alice@example.test',
            'admemail_match' => '0',
        ];
        $exactEmailHit = $this->renderAdminsPage();
        $this->assertSame(1, $this->extractAdminCount($exactEmailHit), 'exact admemail=alice@example.test matches alice');
        $this->assertStringContainsString('>alice<', $exactEmailHit);
    }

    /**
     * Multi-select web flag filter accepts the modern `[]` array
     * shape (`?admwebflag[]=ADMIN_OWNER&admwebflag[]=ADMIN_ADD_BAN`).
     * The seed admin and one of our test admins have ADMIN_OWNER —
     * filtering by it should narrow to those two.
     */
    public function testWebFlagMultiFilterArrayShape(): void
    {
        $_GET = [
            'p'          => 'admin',
            'c'          => 'admins',
            'admwebflag' => ['ADMIN_OWNER'],
        ];

        $html = $this->renderAdminsPage();

        // The seeded admin row has ADMIN_OWNER (extraflags=16777216);
        // among our test rows charlie holds ADMIN_OWNER too. Both
        // must show up.
        $this->assertSame(2, $this->extractAdminCount($html), 'admwebflag[]=ADMIN_OWNER → 2 owners');
        $this->assertStringContainsString('>admin<',   $html, 'seeded admin has ADMIN_OWNER');
        $this->assertStringContainsString('>charlie<', $html, 'charlie has ADMIN_OWNER');
    }

    /**
     * #1303 — default render is collapsed.
     *
     * On a bare `?p=admin&c=admins` (no filter populated) the
     * advanced-search disclosure must paint *closed* so the unfiltered
     * admin list reaches above the fold. The contract is
     * `<details data-testid="search-admins-disclosure">` WITHOUT the
     * `open` attribute. We anchor on the testid + the absence of the
     * `[open]` attribute on that exact element rather than scanning
     * for the substring "open" anywhere in the document (every
     * `<details open>` in unrelated chrome would false-positive).
     */
    public function testDisclosureClosedByDefault(): void
    {
        $_GET = ['p' => 'admin', 'c' => 'admins'];

        $html = $this->renderAdminsPage();

        $disclosure = $this->extractDisclosureTag($html);
        $this->assertStringNotContainsString(' open', $disclosure, 'default render must be collapsed');

        // The "N active" badge must NOT render when no filter is
        // populated — verifies the `{if $active_filter_count > 0}`
        // gate in the template.
        $this->assertStringNotContainsString('search-admins-active-count', $html);

        // The active-filter count attribute reads zero on the
        // disclosure root (drives the auto-open + the badge).
        $this->assertStringContainsString('data-active-filter-count="0"', $disclosure);
    }

    /**
     * #1303 — disclosure auto-expands when ANY filter is populated.
     *
     * Post-submit (or anyone landing on a deep-linked URL) the form
     * paints `<details open>` so the filter chrome AND the
     * Clear-filters link stay visible — without this, the user can't
     * tell what narrowed the list and the only escape hatch (Clear)
     * is hidden behind another click.
     */
    public function testDisclosureAutoExpandsWithActiveFilter(): void
    {
        $_GET = ['p' => 'admin', 'c' => 'admins', 'name' => 'alice'];

        $html = $this->renderAdminsPage();

        $disclosure = $this->extractDisclosureTag($html);
        $this->assertStringContainsString(' open', $disclosure, 'disclosure must auto-open when a filter is active');
        $this->assertStringContainsString('data-active-filter-count="1"', $disclosure);

        // Count badge present, with the right value baked into the
        // aria-label.
        $this->assertStringContainsString('search-admins-active-count', $html);
        $this->assertStringContainsString('aria-label="1 active filter"', $html);
        $this->assertStringContainsString('1 active', $html);
    }

    /**
     * #1303 — multi-filter URLs lift the active count to N. Locks the
     * counter's behaviour against the headline ADM-4 contract: every
     * populated value slot counts ONCE, match-mode toggles do NOT.
     *
     * Two text filters (`name`, `steamid`) + one select (`webgroup`) +
     * one multi-select (`admwebflag[]`) → 4 active. The presence of
     * `name_match=0` (a refinement on the `name` filter) must NOT
     * lift the count to 5; same for `steam_match=1` on `steamid`.
     */
    public function testDisclosureCountMatchesPopulatedFilterSlots(): void
    {
        $_GET = [
            'p'          => 'admin',
            'c'          => 'admins',
            'name'       => 'alice',
            'name_match' => '0',
            'steamid'    => 'STEAM_0:0:1001',
            'steam_match' => '1',
            'webgroup'   => (string) $this->powerGid,
            'admwebflag' => ['ADMIN_OWNER'],
        ];

        $html = $this->renderAdminsPage();

        $disclosure = $this->extractDisclosureTag($html);
        $this->assertStringContainsString(' open', $disclosure);
        $this->assertStringContainsString('data-active-filter-count="4"', $disclosure);
        $this->assertStringContainsString('aria-label="4 active filters"', $html);
    }

    /**
     * #1303 — empty multi-select arrays must NOT lift the count. The
     * `admwebflag[]=` shape produces an empty array in `$_GET` when
     * the user submits without picking any flag (some browsers / form
     * libraries do this); the count's job is to surface populated
     * filters, so an empty multi-select counts as zero.
     *
     * Bare URL with an empty `admwebflag` array → 0 active.
     */
    public function testDisclosureIgnoresEmptyMultiSelectArrays(): void
    {
        $_GET = [
            'p'          => 'admin',
            'c'          => 'admins',
            'admwebflag' => [],
            'admsrvflag' => [],
        ];

        $html = $this->renderAdminsPage();

        $disclosure = $this->extractDisclosureTag($html);
        $this->assertStringNotContainsString(' open', $disclosure, 'empty multi-select arrays must not auto-open the disclosure');
        $this->assertStringContainsString('data-active-filter-count="0"', $disclosure);
        $this->assertStringNotContainsString('search-admins-active-count', $html);
    }

    /**
     * #1303 — the `admemail` slot is permission-gated. The page handler
     * (`admin.admins.php`) ignores `?admemail=…` from a user who lacks
     * `EditAdmins | Owner`, AND the search box hides the e-mail input
     * for the same gate. The active-filter count must mirror both —
     * otherwise URL forgery (or a stale tab from a permission downgrade)
     * would paint "1 active" on the disclosure summary while every
     * visible filter row reads empty, and the disclosure would auto-open
     * exposing nothing actionable.
     *
     * Setup: log in as a user with `ADMIN_LIST_ADMINS` only — they can
     * reach the admin/admins list but the e-mail filter is invisible
     * to them. A forged `?admemail=alice` then must NOT lift the count.
     */
    public function testDisclosureCountIgnoresPermissionGatedEmailSlot(): void
    {
        $listOnlyAid = $this->insertAdmin(
            'enid',
            'STEAM_0:0:9101',
            'enid@example.test',
            -1,
            ADMIN_LIST_ADMINS,
        );
        $this->loginAs($listOnlyAid);

        $_GET = ['p' => 'admin', 'c' => 'admins', 'admemail' => 'alice'];

        $html = $this->renderAdminsPage();

        $disclosure = $this->extractDisclosureTag($html);
        $this->assertStringNotContainsString(' open', $disclosure, 'forged admemail must not auto-open the disclosure for a user without EditAdmins | Owner');
        $this->assertStringContainsString('data-active-filter-count="0"', $disclosure);
        $this->assertStringNotContainsString('search-admins-active-count', $html);

        // Sanity: the e-mail input row itself is hidden for this user
        // (the `{if $can_editadmin}` gate in the template), so the
        // count's silence is consistent with the visible form chrome.
        $this->assertStringNotContainsString('data-testid="search-admins-admemail"', $html);
    }

    /**
     * Structural invariant for #1275: the Pattern A sidebar must
     * carry every section the current user can reach. The owner
     * (seeded `admin/admin`) sees all three sections (`admins`,
     * `add-admin`, `overrides`); the dead-link contract is that the
     * sidebar is in lockstep with the dispatcher's permission gates,
     * not that every section's body renders simultaneously (the
     * page only ever renders ONE section per request).
     *
     * Pre-#1275 (Pattern B) every section's body stacked into the
     * same DOM, so this test compared the ToC link list against the
     * `<section>` count. Pattern A renders one body per request, so
     * the assertion shape pivots: enumerate the sidebar links and
     * confirm the union matches the owner's accessible set.
     */
    public function testSidebarCarriesEveryAccessibleSectionForOwner(): void
    {
        $_GET = ['p' => 'admin', 'c' => 'admins'];

        $html = $this->renderAdminsPage();

        $sidebarLinks = $this->renderedSidebarLinkSlugs($html);
        $this->assertSame(
            ['add-admin', 'admins', 'overrides'],
            $sidebarLinks,
            'owner must see every Pattern A admin-admins section in the sidebar'
        );

        // The default render (no `?section=`) lands on the first
        // accessible section — for the owner that's `admins`. The
        // section's body MUST be in the DOM (this test belts the
        // dispatcher's first-accessible default-section logic). The
        // `admins` template embeds the search box above the table so
        // both `admin-admins-section-search` and `…-section-admins`
        // testids land — they're sibling `<div>`s inside the same
        // section, not a separate page render.
        $this->assertSame(['admins', 'search'], $this->renderedSectionSlugs($html));
    }

    /**
     * #1275 dead-link guard: an admin who holds ADMIN_ADD_ADMINS but
     * NOT ADMIN_LIST_ADMINS must NOT see the `admins` link in the
     * sidebar — the dispatcher would render an access-denied stub if
     * they followed it. Pattern A's contract is single-source: the
     * sidebar's `permission` field on each `$sections` entry decides
     * visibility; the dispatcher honours the same gate when picking
     * the default section.
     *
     * Pre-#1275 (Pattern B) the equivalent test compared the ToC
     * link list against the rendered `<section>` count in one DOM.
     * Now we assert the sidebar's link slugs and that the default
     * landing falls back to the first accessible section.
     */
    public function testSidebarElidesAdminsForAddOnlyAdmin(): void
    {
        // Ad-hoc admin without OWNER, without LIST_ADMINS — only
        // ADMIN_ADD_ADMINS. extraflags carries the bit directly so
        // CUserManager::HasAccess returns true for ADD checks and
        // false for LIST checks. gid=-1 short-circuits group-flag
        // inheritance (matches the Fixture seed admin shape) so
        // bareGid/powerGid flags don't bleed into the user's
        // effective permission set and accidentally re-grant
        // LIST_ADMINS.
        $addOnlyAid = $this->insertAdmin(
            'darla',
            'STEAM_0:0:9001',
            'darla@example.test',
            -1,
            ADMIN_ADD_ADMINS,
        );
        $this->loginAs($addOnlyAid);

        $_GET = ['p' => 'admin', 'c' => 'admins'];

        $html = $this->renderAdminsPage();

        $sidebarLinks = $this->renderedSidebarLinkSlugs($html);

        // ADMIN_ADD_ADMINS gates both `add-admin` and `overrides`
        // (the two Pattern A sections that surface the SourceMod
        // override editor + the Add admin form). `admins` is gated
        // on ADMIN_LIST_ADMINS and must be elided.
        $this->assertSame(['add-admin', 'overrides'], $sidebarLinks);
        $this->assertStringNotContainsString('data-testid="admin-tab-admins"', $html);

        // The dispatcher's first-accessible-section default kicks in
        // here: with no `?section=`, the user lands on `add-admin`
        // (the first entry whose permission they hold).
        $this->assertSame(['add-admin'], $this->renderedSectionSlugs($html));
    }

    private function seedTestAdmins(): void
    {
        $pdo = Fixture::rawPdo();

        // `:prefix_groups` has only (gid, type, name, flags) — type=1
        // is the "web group" sentinel admin.admins.search.php filters
        // its `webgroup_list` on.
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_groups` (name, flags, type) VALUES (?, ?, 1)', DB_PREFIX,
        ))->execute(['Power Users', ADMIN_LIST_ADMINS | ADMIN_ADD_BAN]);
        $this->powerGid = (int) $pdo->lastInsertId();

        $pdo->prepare(sprintf(
            'INSERT INTO `%s_groups` (name, flags, type) VALUES (?, ?, 1)', DB_PREFIX,
        ))->execute(['Bare Users', ADMIN_LIST_ADMINS]);
        $this->bareGid = (int) $pdo->lastInsertId();

        $this->aliceAid   = $this->insertAdmin('alice',   'STEAM_0:0:1001', 'alice@example.test',   $this->powerGid, 0);
        $this->bobAid     = $this->insertAdmin('bob',     'STEAM_0:0:1002', 'bob@example.test',     $this->bareGid,  0);
        $this->charlieAid = $this->insertAdmin('charlie', 'STEAM_0:0:1003', 'charlie@example.test', $this->powerGid, ADMIN_OWNER);
    }

    private function insertAdmin(string $user, string $authid, string $email, int $gid, int $extraflags): int
    {
        $pdo  = Fixture::rawPdo();
        $hash = password_hash('x', PASSWORD_BCRYPT);
        $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, validate, extraflags, immunity)
             VALUES (?, ?, ?, ?, ?, NULL, ?, 25)',
            DB_PREFIX,
        ))->execute([$user, $authid, $hash, $gid, $email, $extraflags]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Spin a Smarty `$theme` matching init.php so admin.admins.php
     * (which renders three Views) doesn't crash on `Renderer::render`.
     * No need to wire every plugin — only the ones the rendered
     * templates actually use during this code path.
     */
    private function bootstrapSmartyTheme(): void
    {
        require_once INCLUDES_PATH . '/SmartyCustomFunctions.php';
        require_once INCLUDES_PATH . '/View/View.php';
        require_once INCLUDES_PATH . '/View/Renderer.php';

        // Minimal compile/cache dir so Smarty can write its compiled
        // .tpl.php artefacts. The default cache is `web/cache/` which
        // is owned by root inside the docker image; tests run as the
        // host user, so use a process-private tmp path instead.
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

    /**
     * Run the page handler in-process and return its rendered HTML.
     * Wraps the include in `ob_start` so the templates' direct echos
     * land in the returned string, not on PHPUnit's stdout.
     */
    private function renderAdminsPage(): string
    {
        ob_start();
        try {
            // Each include runs against the same global state; passing
            // through a closure scopes `$theme` / `$userbank` exactly
            // like web/index.php does at request time.
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

    private function extractAdminCount(string $html): int
    {
        if (preg_match('/data-testid="admin-count">\((\d+)\)/', $html, $m)) {
            return (int) $m[1];
        }
        $this->fail('admin-count testid not found in rendered HTML');
    }

    /**
     * Extract the `<details data-testid="search-admins-disclosure" …>`
     * opening tag from the rendered HTML. Asserting against this slice
     * (rather than the whole document) keeps the `[open]` /
     * `data-active-filter-count` checks scoped to the disclosure root
     * even when sibling chrome (`<details open>` accordions, etc.)
     * coexists in the same render.
     */
    private function extractDisclosureTag(string $html): string
    {
        if (preg_match('/<details[^>]*data-testid="search-admins-disclosure"[^>]*>/', $html, $m)) {
            return $m[0];
        }
        $this->fail('search-admins-disclosure testid not found in rendered HTML');
    }

    private function countAdminRows(string $html): int
    {
        return preg_match_all('/data-testid="admin-row"/', $html);
    }

    /**
     * Extract every Pattern A sidebar link slug (`admins`,
     * `add-admin`, `overrides`) from `[data-testid="admin-tab-<slug>"]`
     * attributes in the rendered HTML. Sorted so callers can compare
     * against a fixed ordering without depending on emit order.
     *
     * @return list<string>
     */
    private function renderedSidebarLinkSlugs(string $html): array
    {
        preg_match_all('/data-testid="admin-tab-([a-z-]+)"/', $html, $m);
        $slugs = $m[1];
        sort($slugs);
        return array_values(array_unique($slugs));
    }

    /**
     * Extract every section slug from `[data-testid="admin-admins-section-<slug>"]`.
     * Under Pattern A only ONE section's body renders per request,
     * so callers should expect a single-element list (or empty if
     * the user lacks access to every gated section).
     *
     * @return list<string>
     */
    private function renderedSectionSlugs(string $html): array
    {
        preg_match_all('/data-testid="admin-admins-section-([a-z-]+)"/', $html, $m);
        $slugs = $m[1];
        sort($slugs);
        return array_values(array_unique($slugs));
    }
}
