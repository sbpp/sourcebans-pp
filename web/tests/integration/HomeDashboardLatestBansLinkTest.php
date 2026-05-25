<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * #1442 — the dashboard's "Latest bans" / "Latest blocked attempts" /
 * "Latest comm blocks" row anchors must NOT use the legacy
 * `?advSearch=…&advType=…&Submit` URL shape.
 *
 * Pre-#1442 each `<a class="ban-row" href="{$b.search_link}">` row
 * on `page_dashboard.tpl` stamped the legacy URL. On click the
 * destination banlist's `$banlistAdvancedOpen` predicate (set from
 * `isset($_GET['advSearch']) && (string) $_GET['advSearch'] !== ''`,
 * #1315) flipped the `<details class="filters-details">` disclosure
 * into the `[open]` state, expanding the multi-criterion advanced-
 * search form above the row table. On a 1273×748 viewport this
 * pushed the actual matched ban below the fold — exactly the bug
 * DNA-styx reported. The fix is to ride the simple-bar
 * `?searchText=` URL shim instead: the SQL filter is the same set
 * of `BA.ip` / `BA.authid` / `BA.name` / `BA.reason` LIKE / REGEXP
 * predicates, the matched ban still surfaces in the rowset, but
 * the disclosure stays closed so the matched ban paints above the
 * fold.
 *
 * This test pins the URL shape `page.home.php` emits for all three
 * dashboard row surfaces:
 *
 *   - `players_banned` (Latest bans) — drives `:prefix_bans` rows
 *     for both Steam-type (`type=0`) and IP-type (`type=1`) bans;
 *     the IP-type arm forks per-admin-vs-anonymous viewer (admin
 *     gets `searchText=<ip>`; non-admin gets `searchText=<name>`
 *     because the banlist disables IP search under
 *     `banlist.hideplayerips`).
 *   - `players_blocked` (Latest blocked attempts) — drives the
 *     `:prefix_banlog` join, same fork shape as `players_banned`.
 *   - `players_commed` (Latest comm blocks) — drives `:prefix_comms`
 *     rows; comms always carry an authid (no IP-type comms), so
 *     the URL shape is always `?p=commslist&searchText=<authid>`.
 *
 * Each test runs in its own process for the same reason
 * `HomeDashboardAnnouncementTest` does — `page.home.php` declares
 * the top-level `SbppServerQryHelpers()` helper that PHP can't
 * redeclare in one process. Process isolation also keeps the
 * `$GLOBALS['userbank']` / `$_SESSION` mutations from each test
 * from cross-contaminating siblings.
 *
 * The stub Smarty (lifted from `HomeDashboardAnnouncementTest`)
 * captures the `Renderer::render`-driven `$theme->assign(...)`
 * calls so we can assert the `players_banned` / `players_blocked`
 * / `players_commed` arrays the page handler builds, WITHOUT
 * rendering the actual template. The contract under test is the
 * URL the handler stamps into each row's `search_link`; the
 * template is a thin `<a href="{$b.search_link}">` consumer.
 *
 * See the sister `PublicBanListRegressionTest::test*DisclosureStaysClosedWhenSearchTextSet`
 * methods for the destination half of the contract: a banlist /
 * commslist URL carrying `?searchText=…` (and no `?advSearch=…`)
 * must render the disclosure WITHOUT `[open]`.
 */
final class HomeDashboardLatestBansLinkTest extends ApiTestCase
{
    /**
     * Steam-type ban — the most common shape on the dashboard.
     * The search link must use `?searchText=<authid>` so the
     * banlist's simple-bar branch fires (authid REGEXP match via
     * SteamID::toSearchPattern), leaving the advanced-search
     * disclosure collapsed.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLatestBansLinkUsesSearchTextForSteamTypeBan(): void
    {
        $this->loginAsAdmin();
        $this->seedBan('STEAM_0:1:42424242', 'CheaterAlpha', \BanType::Steam, null);
        $theme = $this->bootRenderHarness();

        $this->renderHome();

        /** @var list<array<string, mixed>> $bans */
        $bans = $theme->captured['players_banned'] ?? [];
        $this->assertNotEmpty($bans, 'seeded :prefix_bans row must surface in $players_banned');

        $link = (string) ($bans[0]['search_link'] ?? '');
        $this->assertSame(
            'index.php?p=banlist&searchText=STEAM_0%3A1%3A42424242',
            $link,
            'Steam-type ban search link must ride the simple-bar `?searchText=<authid>` shim — #1442',
        );
        $this->assertNoLegacyAdvancedSearchParameters($link);
    }

    /**
     * IP-type ban viewed by an admin — the admin sees the actual
     * IP (no `banlist.hideplayerips` redaction) and the link
     * filters by `searchText=<ip>` so the banlist's
     * `BA.ip LIKE %ip%` clause fires.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLatestBansLinkUsesSearchTextForIpTypeBanWhenAdmin(): void
    {
        $this->loginAsAdmin();
        $this->seedBan(null, 'IpAlpha', \BanType::Ip, '203.0.113.7');
        $theme = $this->bootRenderHarness();

        $this->renderHome();

        /** @var list<array<string, mixed>> $bans */
        $bans = $theme->captured['players_banned'] ?? [];
        $this->assertNotEmpty($bans, 'seeded IP-type ban must surface in $players_banned');

        $link = (string) ($bans[0]['search_link'] ?? '');
        $this->assertSame(
            'index.php?p=banlist&searchText=203.0.113.7',
            $link,
            'IP-type ban (admin viewer) link must filter by `searchText=<ip>` — #1442',
        );
        $this->assertNoLegacyAdvancedSearchParameters($link);
    }

    /**
     * IP-type ban viewed by an anonymous (non-admin) viewer — the
     * banlist disables IP search for non-admins when
     * `banlist.hideplayerips` is on (or by convention here:
     * non-admins shouldn't see the IP), so the legacy code
     * fell back to `advType=name`. We preserve that fallback by
     * stamping `searchText=<name>` for the anonymous arm.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLatestBansLinkUsesSearchTextForIpTypeBanWhenAnonymous(): void
    {
        // No loginAsAdmin → ApiTestCase::setUp left $userbank as the
        // anonymous viewer.
        $this->seedBan(null, 'IpAnon', \BanType::Ip, '203.0.113.8');
        $theme = $this->bootRenderHarness();

        $this->renderHome();

        /** @var list<array<string, mixed>> $bans */
        $bans = $theme->captured['players_banned'] ?? [];
        $this->assertNotEmpty($bans, 'seeded IP-type ban must surface in $players_banned even for anonymous viewer');

        $link = (string) ($bans[0]['search_link'] ?? '');
        // The dashboard escapes the name via htmlspecialchars +
        // addslashes BEFORE the urlencode round-trip; the seeded
        // name `IpAnon` has no special chars so the encoded form is
        // the raw string.
        $this->assertSame(
            'index.php?p=banlist&searchText=IpAnon',
            $link,
            'IP-type ban (anonymous viewer) must fall back to `searchText=<name>` to avoid leaking the IP — #1442',
        );
        $this->assertNoLegacyAdvancedSearchParameters($link);
    }

    /**
     * IP-type ban viewed anonymously where the player name contains
     * `&` — locks in the contract that the URL stamps `urlencode`
     * over the RAW `$cleaned_name`, not the htmlspecialchars-escaped
     * `$info['name']`. Pre-fix the loop URL-encoded the escaped
     * form (`Player &amp; Co`), so the destination's
     * `name LIKE '%Player &amp; Co%'` filter would never match a
     * real `Player & Co` row in the DB and the banlist would render
     * the empty-filtered state. New symptom worse than the original
     * #1442 bug ("below the fold") — "not visible at all."
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLatestBansLinkEncodesRawNameNotHtmlEscapedForm(): void
    {
        // No loginAsAdmin → anonymous viewer hits the IP-type-name
        // fallback arm.
        $this->seedBan(null, 'Player & Co', \BanType::Ip, '203.0.113.42');
        $theme = $this->bootRenderHarness();

        $this->renderHome();

        /** @var list<array<string, mixed>> $bans */
        $bans = $theme->captured['players_banned'] ?? [];
        $this->assertNotEmpty($bans);

        $link = (string) ($bans[0]['search_link'] ?? '');

        // `urlencode()` encodes `&` → `%26` and spaces → `+`. If the
        // loop URL-encoded the escaped `$info['name']`
        // (`Player &amp; Co`) instead, the link would contain
        // `Player+%26amp%3B+Co`. The new contract is: encode
        // `$cleaned_name` directly, so the destination banlist SQL
        // filter sees the literal raw bytes that match the `name`
        // column.
        $this->assertSame(
            'index.php?p=banlist&searchText=Player+%26+Co',
            $link,
            'anonymous-viewer IP-type ban must URL-encode raw $cleaned_name (not htmlspecialchars-escaped $info["name"]) — #1442 review finding',
        );
        $this->assertStringNotContainsString(
            'amp%3B',
            $link,
            'encoded `&amp;` (`%26amp%3B`) indicates the loop URL-encoded the escaped form — breaks destination SQL match — #1442 review finding',
        );
    }

    /**
     * Latest blocked attempts (banlog → `$stopped`) — Steam-type
     * blocked attempt mirrors the bans-side Steam shape.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLatestBlockedAttemptsLinkUsesSearchTextForSteamType(): void
    {
        $this->loginAsAdmin();
        $bid = $this->seedBan('STEAM_0:1:99999999', 'BlockedAlpha', \BanType::Steam, null);
        $this->seedBanlogForBid($bid, 'BlockedAlpha');
        $theme = $this->bootRenderHarness();

        $this->renderHome();

        /** @var list<array<string, mixed>> $stopped */
        $stopped = $theme->captured['players_blocked'] ?? [];
        $this->assertNotEmpty($stopped, 'seeded banlog row must surface in $players_blocked');

        $link = (string) ($stopped[0]['search_link'] ?? '');
        $this->assertSame(
            'index.php?p=banlist&searchText=STEAM_0%3A1%3A99999999',
            $link,
            'Steam-type blocked attempt link must ride the simple-bar `?searchText=<authid>` shim — #1442',
        );
        $this->assertNoLegacyAdvancedSearchParameters($link);
    }

    /**
     * Latest blocked attempts where the banlog row points at a
     * BAN THAT WAS LATER DELETED — `:prefix_banlog.bid` references
     * a row that no longer exists in `:prefix_bans`, so the
     * `LEFT JOIN :prefix_bans` returns NULL for `b.type` / `b.ip`
     * / `b.authid`. Pre-#1442-review the urlencode call would have
     * triggered the PHP 8.5 `Deprecated: urlencode(): Passing null
     * to parameter #1 of type string` warning; the `(string)` cast
     * in the `$stopped` loop fixes that. This test promotes the
     * warning to an exception so a regression would fail the gate.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLatestBlockedAttemptsHandlesOrphanBanlogWithoutNullDeprecation(): void
    {
        $this->loginAsAdmin();
        // Seed a banlog row pointing at a `bid` that doesn't exist.
        // `Fixture::install()` resets the DB, so the highest legitimate
        // bid is whatever the fixture seeded; 99999 is comfortably
        // out of range.
        $this->seedBanlogForBid(99999, 'OrphanedBlock');

        // Promote PHP 8.5 null-into-string deprecation warnings to
        // exceptions so the render fails if the urlencode chain
        // accidentally re-introduces the bug. Mirrors the
        // Php82DeprecationsTest pattern.
        set_error_handler(function ($severity, $message, $file, $line) {
            if (str_contains($message, 'Passing null to parameter')) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }
            return false;
        }, E_DEPRECATED | E_USER_DEPRECATED);

        try {
            $theme = $this->bootRenderHarness();
            $this->renderHome();
        } finally {
            restore_error_handler();
        }

        /** @var list<array<string, mixed>> $stopped */
        $stopped = $theme->captured['players_blocked'] ?? [];
        $this->assertNotEmpty($stopped, 'orphan banlog row must surface in $players_blocked');

        // The orphan row's `b.type` is NULL → `(int) NULL === 0` →
        // `BanType::tryFrom(0)` returns Steam → URL is built from
        // `$info['auth']` which is also NULL → cast to ''. The
        // resulting search link is benign (empty searchText) — the
        // load-bearing contract here is "doesn't trip the
        // urlencode(NULL) deprecation."
        $link = (string) ($stopped[0]['search_link'] ?? '');
        $this->assertSame(
            'index.php?p=banlist&searchText=',
            $link,
            'orphan banlog (Steam-type fallback with NULL authid) must produce an empty-searchText URL',
        );
    }

    /**
     * Latest blocked attempts (banlog) — IP-type blocked attempt
     * with an admin viewer. Same admin/anonymous fork as the
     * bans-side IP arm.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLatestBlockedAttemptsLinkUsesSearchTextForIpTypeWhenAdmin(): void
    {
        $this->loginAsAdmin();
        $bid = $this->seedBan(null, 'BlockedIpAlpha', \BanType::Ip, '198.51.100.42');
        $this->seedBanlogForBid($bid, 'BlockedIpAlpha');
        $theme = $this->bootRenderHarness();

        $this->renderHome();

        /** @var list<array<string, mixed>> $stopped */
        $stopped = $theme->captured['players_blocked'] ?? [];
        $this->assertNotEmpty($stopped, 'seeded IP-type banlog row must surface in $players_blocked');

        $link = (string) ($stopped[0]['search_link'] ?? '');
        $this->assertSame(
            'index.php?p=banlist&searchText=198.51.100.42',
            $link,
            'IP-type blocked attempt (admin viewer) link must filter by `searchText=<ip>` — #1442',
        );
        $this->assertNoLegacyAdvancedSearchParameters($link);
    }

    /**
     * Latest comm blocks (`:prefix_comms` → `$players_commed`) —
     * comms always carry an authid (the SourceMod plugin's
     * `sbpp_comms.sp` insert path doesn't write IP-type comms), so
     * the URL is always `?p=commslist&searchText=<authid>`.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLatestCommBlocksLinkUsesSearchText(): void
    {
        $this->loginAsAdmin();
        $this->seedComm('STEAM_0:1:77777777', 'SpammerAlpha');
        $theme = $this->bootRenderHarness();

        $this->renderHome();

        /** @var list<array<string, mixed>> $comms */
        $comms = $theme->captured['players_commed'] ?? [];
        $this->assertNotEmpty($comms, 'seeded :prefix_comms row must surface in $players_commed');

        $link = (string) ($comms[0]['search_link'] ?? '');
        $this->assertSame(
            'index.php?p=commslist&searchText=STEAM_0%3A1%3A77777777',
            $link,
            'Comm block link must ride the simple-bar `?searchText=<authid>` shim on commslist — #1442',
        );
        $this->assertNoLegacyAdvancedSearchParameters($link);
    }

    /**
     * URL-encoding invariant — confirms `urlencode()` correctly
     * escapes `&` / `=` / `;` etc. so a hostile player name cannot
     * smuggle a sibling `&advSearch=…` parameter that would
     * re-open the disclosure on the destination. This is the
     * structural invariant the urlencode chain provides; the test
     * pins it against future refactors that might swap in
     * htmlentities-decoded values without re-encoding.
     *
     * Note: the urlencode chain was already in place pre-#1442
     * (just stamping the legacy `advSearch=` parameter name
     * instead), so this test does not document a new attack
     * vector being closed — it documents the invariant that
     * survives the URL-shape swap.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSearchTextLinkEncodesValueWithUrlencode(): void
    {
        // No loginAsAdmin: cover the anonymous-viewer name arm.
        // Build a hostile player name with characters that would
        // otherwise break out of the URL (`&`, `=`, `#`, `?`).
        $hostileName = 'Crash&advSearch=evil';
        $this->seedBan(null, $hostileName, \BanType::Ip, '203.0.113.99');
        $theme = $this->bootRenderHarness();

        $this->renderHome();

        /** @var list<array<string, mixed>> $bans */
        $bans = $theme->captured['players_banned'] ?? [];
        $this->assertNotEmpty($bans, 'seeded hostile-name IP-type ban must surface in $players_banned');

        $link = (string) ($bans[0]['search_link'] ?? '');

        // The `&` and `=` in the hostile name MUST land URL-encoded
        // (`%26` / `%3D`) so they can't break out of the
        // `searchText=` parameter and smuggle in a sibling
        // `advSearch=` that would re-open the disclosure on the
        // destination page. The literal substring "advSearch" can
        // still appear inside the percent-encoded value (because
        // the player name itself contains those bytes) — the
        // safety property is that those bytes are NOT a separate
        // URL parameter when parsed.
        $this->assertStringContainsString(
            'searchText=',
            $link,
            'link must carry the searchText= param',
        );
        $this->assertStringNotContainsString(
            '&advSearch=',
            $link,
            'an UN-encoded `&advSearch=` boundary in the rendered link would split the URL into two params — #1442',
        );

        // Parse the URL back and verify the searchText param
        // contains the full hostile payload (encoded back through
        // htmlspecialchars + urlencode) and NO sibling advSearch
        // parameter exists. This is the load-bearing assertion:
        // even if the encoded form contains the literal substring
        // "advSearch", PHP's `parse_str` correctly decodes it back
        // as part of the `searchText` value, not as a separate
        // param.
        $query = parse_url($link, PHP_URL_QUERY);
        $this->assertIsString($query);
        parse_str((string) $query, $params);
        $this->assertArrayHasKey('searchText', $params);
        $this->assertArrayNotHasKey(
            'advSearch',
            $params,
            'parsed URL must NOT carry a sibling advSearch parameter even when the player name contains `&advSearch=` — #1442',
        );
        // The decoded value must contain the original hostile
        // bytes (possibly after htmlspecialchars expansion) — i.e.
        // the value round-trips intact through the encoding.
        $this->assertIsString($params['searchText']);
        $this->assertStringContainsString(
            'advSearch=evil',
            (string) $params['searchText'],
            'after urldecode the searchText value must still contain the original bytes — the encoding hides them inside the value, not strips them',
        );
    }

    /**
     * Belt-and-braces: scan the source of `page.home.php` to ensure
     * no `advSearch=` literal survives. Catches a future search-and-
     * replace mishap or a copy-paste from third-party fork patches
     * that re-introduces the legacy URL shape.
     */
    public function testPageHomePhpSourceCarriesNoLegacyAdvSearchUrlShapes(): void
    {
        $source = (string) file_get_contents(ROOT . 'pages/page.home.php');
        $this->assertNotSame(
            '',
            $source,
            'page.home.php must be readable from the test process',
        );

        // The substring `advSearch=` survives only inside the
        // documentation block (`Pre-fix this stamped the legacy
        // ?advSearch=…&advType=…&Submit URL shape`). The
        // executable-code check strips PHP comments via
        // `php_strip_whitespace` so the doc-block reference doesn't
        // false-positive the gate. Same shape `DeadJsCallSitesTest`
        // uses for its forbidden-substring map.
        $sourceNoComments = php_strip_whitespace(ROOT . 'pages/page.home.php');

        $this->assertStringNotContainsString(
            'advSearch=',
            $sourceNoComments,
            'page.home.php executable code must not emit `advSearch=` URL parameters — #1442',
        );
        $this->assertStringNotContainsString(
            'advType=',
            $sourceNoComments,
            'page.home.php executable code must not emit `advType=` URL parameters — #1442',
        );
    }

    /**
     * Defence-in-depth check for the substring `?advSearch=` inside
     * the rendered output. Runs the dashboard against a seeded DB
     * (every row shape that has a `search_link`) and asserts the
     * captured arrays carry no row whose `search_link` resembles
     * the legacy shape.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testNoLatestRowEmitsLegacyAdvancedSearchUrlShape(): void
    {
        $this->loginAsAdmin();
        // Cover every URL-arm: Steam-type ban + IP-type ban + comm.
        $bid1 = $this->seedBan('STEAM_0:1:1', 'A', \BanType::Steam, null);
        $bid2 = $this->seedBan(null, 'B', \BanType::Ip, '203.0.113.10');
        $this->seedComm('STEAM_0:1:2', 'C');
        $this->seedBanlogForBid($bid1, 'A');
        $this->seedBanlogForBid($bid2, 'B');
        $theme = $this->bootRenderHarness();

        $this->renderHome();

        /** @var list<array<string, mixed>> $bans */
        $bans = $theme->captured['players_banned'] ?? [];
        /** @var list<array<string, mixed>> $stopped */
        $stopped = $theme->captured['players_blocked'] ?? [];
        /** @var list<array<string, mixed>> $comms */
        $comms = $theme->captured['players_commed'] ?? [];

        foreach ($bans as $i => $row) {
            $this->assertNoLegacyAdvancedSearchParameters(
                (string) ($row['search_link'] ?? ''),
                "bans[$i]",
            );
        }
        foreach ($stopped as $i => $row) {
            $this->assertNoLegacyAdvancedSearchParameters(
                (string) ($row['search_link'] ?? ''),
                "stopped[$i]",
            );
        }
        foreach ($comms as $i => $row) {
            $this->assertNoLegacyAdvancedSearchParameters(
                (string) ($row['search_link'] ?? ''),
                "comms[$i]",
            );
        }
    }

    // ----- Helpers below this line ----------------------------------

    /**
     * Build the stub-Smarty harness `HomeDashboardAnnouncementTest`
     * established. The page handler's
     * `Renderer::render($theme, $view)` call assigns each public
     * property onto Smarty; the stub captures those assignments
     * so the test can read back what the handler decided.
     */
    private function bootRenderHarness(): object
    {
        $theme = new class extends Smarty {
            /** @var array<string,mixed> */
            public array $captured = [];

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
                return '';
            }
        };

        $GLOBALS['theme']    = $theme;
        $GLOBALS['userbank'] = $GLOBALS['userbank'] ?? new \CUserManager(null);
        $GLOBALS['username'] = $GLOBALS['username'] ?? 'tester';
        return $theme;
    }

    private function renderHome(): void
    {
        $_SESSION = $_SESSION ?? [];
        $_GET     = [];

        ob_start();
        try {
            require ROOT . 'pages/page.home.php';
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Insert a row into `:prefix_bans` and return the auto-assigned
     * `bid`. `$authid` may be null for IP-type bans; `$ip` is
     * null for Steam-type bans. `$banType` is the `BanType` enum
     * value bound via `->value` so the column-typed `tinyint`
     * primitive lands on disk (per AGENTS.md "Backed enums").
     */
    private function seedBan(?string $authid, string $name, \BanType $banType, ?string $ip): int
    {
        $pdo = Fixture::rawPdo();
        $now = time();
        $aid = Fixture::adminAid();

        $insert = $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans` (type, ip, authid, name, created, ends, length, reason, aid)
             VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?)',
            DB_PREFIX,
        ));
        $insert->execute([
            $banType->value,
            $ip,
            $authid ?? '',
            $name,
            $now,
            'integration test ban',
            $aid,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Insert a `:prefix_banlog` row pointing at the given `bid`
     * so the dashboard's "Latest blocked attempts" surface
     * picks it up. The dashboard's banlog query joins on
     * `:prefix_bans` to forward `b.type`, `b.authid`, `b.ip` into
     * the search-link branching.
     */
    private function seedBanlogForBid(int $bid, string $name): void
    {
        $pdo = Fixture::rawPdo();
        // Insert a server row first (banlog.sid → servers.sid join);
        // an arbitrary sid is fine — the dashboard tolerates a NULL
        // server_addr from the LEFT JOIN.
        $sid = 1;
        $insert = $pdo->prepare(sprintf(
            'INSERT INTO `%s_banlog` (sid, time, name, bid) VALUES (?, ?, ?, ?)',
            DB_PREFIX,
        ));
        $insert->execute([$sid, time(), $name, $bid]);
    }

    /**
     * Insert a `:prefix_comms` row so the dashboard's
     * "Latest comm blocks" surface picks it up. Comm blocks
     * are always Steam-type (no IP arm) — the SourceMod plugin
     * insert path always populates `authid`.
     */
    private function seedComm(string $authid, string $name): int
    {
        $pdo = Fixture::rawPdo();
        $now = time();
        $aid = Fixture::adminAid();

        $insert = $pdo->prepare(sprintf(
            'INSERT INTO `%s_comms` (type, authid, name, created, ends, length, reason, aid)
             VALUES (1, ?, ?, ?, 0, 0, ?, ?)',
            DB_PREFIX,
        ));
        $insert->execute([
            $authid,
            $name,
            $now,
            'integration test comm',
            $aid,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Assert a search-link URL does NOT carry any of the legacy
     * advanced-search parameters that would re-open the
     * destination disclosure (#1442).
     */
    private function assertNoLegacyAdvancedSearchParameters(string $link, string $context = ''): void
    {
        $prefix = $context !== '' ? "[$context] " : '';
        $this->assertStringNotContainsString(
            'advSearch=',
            $link,
            $prefix . 'search link must not carry the legacy `advSearch=` parameter (would re-open disclosure on destination, #1442)',
        );
        $this->assertStringNotContainsString(
            'advType=',
            $link,
            $prefix . 'search link must not carry the legacy `advType=` parameter (paired with `advSearch=`, #1442)',
        );
        $this->assertStringNotContainsString(
            '&Submit',
            $link,
            $prefix . 'search link must not carry the legacy `&Submit` form-submission parameter (dead URL cruft, #1442)',
        );
    }
}
