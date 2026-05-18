<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Sbpp\Tests\ApiTestCase;
use Sbpp\View\NotFoundView;

/**
 * Issue #1207 ADM-1: `route()` (`web/includes/page-builder.php`) used
 * to fall through `?p=admin&c=<unknown>` to the admin landing, so a
 * typo'd or stale-bookmarked URL silently rendered a different page
 * with no signal that the request was wrong.
 *
 * The fix returns the 404 page (`page.404.php` + `Sbpp\View\NotFoundView`)
 * and sets the HTTP status to 404 for any unrecognised admin sub-route,
 * while keeping the bare `?p=admin` landing intact for the seven valid
 * `c=…` values.
 *
 * The tests load `page-builder.php` directly and exercise `route()` —
 * the dispatcher entry point — rather than spinning a real HTTP
 * request, so we can assert the (title, page-file, http-status) triple
 * without booting Smarty or rendering the chrome.
 */
final class RoutingTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
        // route() shells CSRF::rejectIfInvalid() on POST; stay on GET so
        // the routing branch under test is the one we're asserting.
        $_SERVER['REQUEST_METHOD'] = 'GET';
        require_once ROOT . 'includes/system-functions.php';
        require_once ROOT . 'includes/page-builder.php';

        // route() reads $userbank from globals.
        global $userbank;
        $userbank = $GLOBALS['userbank'];

        // Reset the response code between cases. http_response_code(0)
        // is interpreted as "current code" in PHP 8.0+, so we use 200
        // explicitly instead.
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        unset($_GET['p'], $_GET['c'], $_GET['o']);
        http_response_code(200);
        parent::tearDown();
    }

    /**
     * The bare admin landing keeps working: a user lands on
     * `index.php?p=admin` and `route()` returns the AdminHomeView page.
     */
    public function testBareAdminLandingStillRoutesToAdminHome(): void
    {
        $_GET['p'] = 'admin';

        [$title, $file] = route(0);

        $this->assertSame('Administration', $title);
        $this->assertSame('/page.admin.php', $file);
        $this->assertSame(200, http_response_code(),
            'Bare admin landing must NOT set a 404 status code.');
    }

    /**
     * The seven valid `c=…` values still resolve to their per-area
     * pages. This is the regression guard that keeps the 404 branch
     * from accidentally swallowing real routes — every entry below is
     * a `c=` value that ships in production today.
     *
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function validAdminCategories(): iterable
    {
        yield 'admins'   => ['admins',   '/admin.admins.php'];
        yield 'groups'   => ['groups',   '/admin.groups.php'];
        yield 'servers'  => ['servers',  '/admin.servers.php'];
        yield 'bans'     => ['bans',     '/admin.bans.php'];
        yield 'comms'    => ['comms',    '/admin.comms.php'];
        yield 'mods'     => ['mods',     '/admin.mods.php'];
        yield 'settings' => ['settings', '/admin.settings.php'];
        yield 'audit'    => ['audit',    '/admin.audit.php'];
    }

    #[DataProvider('validAdminCategories')]
    public function testValidAdminCategoriesRouteToTheirPage(string $category, string $expectedFile): void
    {
        $_GET['p'] = 'admin';
        $_GET['c'] = $category;

        [, $file] = route(0);

        $this->assertSame($expectedFile, $file,
            "Valid admin category '$category' must route to its expected page file.");
        $this->assertSame(200, http_response_code(),
            "Valid admin category '$category' must NOT set a 404 status code.");
    }

    /**
     * The CC-1 bug: `?p=admin&c=overrides` (the "Overrides" admin card's
     * pre-fix href) used to silently fall through to the admin landing.
     * Now it 404s — pinning this exact URL because it's the one a user
     * would land on if they followed the pre-#1207 card href, or have
     * a stale bookmark.
     */
    public function testUnknownOverridesCategory404s(): void
    {
        $_GET['p'] = 'admin';
        $_GET['c'] = 'overrides';

        [$title, $file] = route(0);

        $this->assertSame('Page not found', $title);
        $this->assertSame('/page.404.php', $file);
        $this->assertSame(404, http_response_code(),
            'Unknown ?c= must set the HTTP status to 404 so crawlers / monitoring see the right signal.');
    }

    /**
     * Generalisation: any unknown `c=…` 404s, not just the one URL the
     * audit screenshot used. A typo'd `bnas` should be just as visible
     * as a stale `overrides`.
     *
     * @return iterable<string, array{0: string}>
     */
    public static function unknownAdminCategories(): iterable
    {
        yield 'overrides'      => ['overrides'];
        yield 'typo bnas'      => ['bnas'];
        yield 'old kickit'     => ['kickit'];
        yield 'random gibberish' => ['xyz123'];
    }

    #[DataProvider('unknownAdminCategories')]
    public function testAnyUnknownCategory404s(string $unknownCategory): void
    {
        $_GET['p'] = 'admin';
        $_GET['c'] = $unknownCategory;

        [$title, $file] = route(0);

        $this->assertSame('Page not found', $title);
        $this->assertSame('/page.404.php', $file);
        $this->assertSame(404, http_response_code());
    }

    /**
     * The 404 page is bound to a typed View DTO — pin it so a future
     * refactor that swaps the template (or removes the View) trips
     * this guard before users see a missing page slot.
     */
    public function testNotFoundViewIsBoundToPage404Template(): void
    {
        $this->assertSame('page_404.tpl', NotFoundView::TEMPLATE);
    }

    /**
     * Empty `c=` (e.g. `?p=admin&c=`) is treated as "no category"
     * rather than "unknown category" — so the user lands on the admin
     * home, not a 404. This matches the pre-fix behaviour for the
     * naked-admin case and avoids 404'ing on a benign trailing
     * separator.
     */
    public function testEmptyCategoryStillRoutesToAdminHome(): void
    {
        $_GET['p'] = 'admin';
        $_GET['c'] = '';

        [, $file] = route(0);

        $this->assertSame('/page.admin.php', $file);
        $this->assertSame(200, http_response_code());
    }

    /**
     * #1290 regression guard: the fallback dispatch (`route($fallback)`
     * when `?p=…` doesn't match any top-level slug) is fed by
     * `Config::get('config.defaultpage')`, which reads `sb_settings.value`
     * — a `text NOT NULL` column. The persisted value is therefore a
     * STRING ("1", "2", …), not an int.
     *
     * The pre-#1290 code dispatched on a loose-`==` `switch`, so `'1'`
     * matched `case 1`. The mechanical sweep replaced it with `match`,
     * which is strict (`===`), and `'1' === 1` is false — every
     * non-zero string default silently fell through to the Dashboard
     * arm.
     *
     * The fix casts `$fallback` to int at the dispatch boundary; this
     * test pins both the per-arm string-input behaviour AND the
     * string-vs-int parity that `match` broke.
     *
     * The fallback path mutates `$_GET['p']` (so downstream code sees
     * the canonical slug), so each case has to re-arm the unknown-slug
     * sentinel before calling `route()` again — otherwise the second
     * call resolves the now-canonical slug via the top-level `match`
     * and never hits the fallback dispatch under test.
     *
     * @return iterable<string, array{0: int|string, 1: string, 2: string}>
     */
    public static function fallbackCases(): iterable
    {
        yield 'string "1" → Ban List'           => ['1',      'Ban List',      '/page.banlist.php'];
        yield 'int 1 → Ban List (parity)'       => [1,        'Ban List',      '/page.banlist.php'];
        yield 'string "2" → Server Info'        => ['2',      'Server Info',   '/page.servers.php'];
        yield 'string "3" → Submit a Ban'       => ['3',      'Submit a Ban',  '/page.submit.php'];
        yield 'string "4" → Protest a Ban'      => ['4',      'Protest a Ban', '/page.protest.php'];
        yield 'string "0" → Dashboard'          => ['0',      'Dashboard',     '/page.home.php'];
        yield 'int 0 → Dashboard'               => [0,        'Dashboard',     '/page.home.php'];
        yield 'non-numeric "banana" → Dashboard' => ['banana', 'Dashboard',     '/page.home.php'];
    }

    #[DataProvider('fallbackCases')]
    public function testRouteFallbackAcceptsStringIntegers(int|string $fallback, string $expectedTitle, string $expectedTemplate): void
    {
        $_GET['p'] = 'unknown-slug-that-falls-through';

        [$title, $template] = route($fallback);

        $this->assertSame($expectedTitle, $title);
        $this->assertSame($expectedTemplate, $template);
    }
}
