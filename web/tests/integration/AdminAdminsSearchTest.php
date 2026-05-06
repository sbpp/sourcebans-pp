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
     * Structural invariant for #1207 ADM-3: every ToC link in the
     * rendered HTML must point at an anchored `<section>` that's
     * actually in the DOM. The slicing brief calls this out
     * explicitly: "The ToC entries are gated on the same permissions
     * the dispatcher uses (no dead links)." This case locks the
     * happy path (owner sees every entry; every entry has its
     * matching section); the next case locks the gated path (an
     * admin without LIST_ADMINS doesn't see Search/Admins links).
     */
    public function testTocLinksMatchRenderedSectionsForOwner(): void
    {
        $_GET = ['p' => 'admin', 'c' => 'admins'];

        $html = $this->renderAdminsPage();

        $this->assertSame(
            $this->renderedSectionSlugs($html),
            $this->renderedTocLinkSlugs($html),
            'every ToC link must have a matching <section> in the DOM (owner sees all five sections)'
        );
        // Belt-and-braces: confirm the seeded owner does in fact see
        // all five entries — guards against a future refactor that
        // accidentally elides every entry uniformly.
        $this->assertSame(
            ['add-admin', 'add-override', 'admins', 'overrides', 'search'],
            $this->renderedTocLinkSlugs($html),
            'owner sees the full ToC'
        );
    }

    /**
     * #1207 ADM-3 dead-link guard: an admin who holds ADMIN_ADD_ADMINS
     * but NOT ADMIN_LIST_ADMINS lands on this page through the "Add
     * new admin" tab. The Search and Admins sections live inside the
     * `{else}` arm of `{if !$can_list_admins}` in
     * page_admin_admins_list.tpl, so they never reach the DOM for
     * this user. The ToC must elide their links accordingly.
     */
    public function testTocElidesSearchAndAdminsForAddOnlyAdmin(): void
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

        $tocLinks = $this->renderedTocLinkSlugs($html);
        $sections = $this->renderedSectionSlugs($html);

        // The dead-link contract: the ToC must NEVER carry an entry
        // that the dispatcher elided.
        $this->assertSame(
            $sections,
            $tocLinks,
            'ToC and rendered sections must stay in lockstep across permission combinations'
        );

        // And the specific narrowing this test exercises: Search and
        // Admins are out, Add admin / Overrides / Add override are
        // in. The list is sorted (renderedTocLinkSlugs() sorts) so we
        // can compare against a known-good ordering.
        $this->assertSame(['add-admin', 'add-override', 'overrides'], $tocLinks);
        $this->assertStringNotContainsString('admin-admins-toc-link-search', $html);
        $this->assertStringNotContainsString('admin-admins-toc-link-admins"', $html);
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

    private function countAdminRows(string $html): int
    {
        return preg_match_all('/data-testid="admin-row"/', $html);
    }

    /**
     * Extract every ToC link slug (`search`, `admins`, `add-admin`, …)
     * from `[data-testid="admin-admins-toc-link-<slug>"]` attributes
     * in the rendered HTML. Sorted so callers can compare against a
     * fixed ordering without depending on emit order.
     *
     * @return list<string>
     */
    private function renderedTocLinkSlugs(string $html): array
    {
        preg_match_all('/data-testid="admin-admins-toc-link-([a-z-]+)"/', $html, $m);
        $slugs = $m[1];
        sort($slugs);
        return array_values(array_unique($slugs));
    }

    /**
     * Extract every section slug from `[data-testid="admin-admins-section-<slug>"]`.
     * Pair to {@see renderedTocLinkSlugs}.
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
