<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Permission contract for the "Full data export" admin surface.
 *
 * The feature exposes PII for every account on the panel (admin emails,
 * banned-player IPs, SteamIDs, ban / comm-block reasons, the full
 * audit log). Per the feature's plan + AGENTS.md "Sub-paged admin
 * routes", access is gated EXCLUSIVELY on `ADMIN_OWNER`
 * (`WebPermission::Owner`). The constraint is non-negotiable: even an
 * admin with every other web flag must be blocked.
 *
 * The gate fires at three sites — defence-in-depth so a single
 * refactor that misses one site can't quietly open the surface:
 *
 *   1. **Page-builder routing table** (`web/includes/page-builder.php`,
 *      `$adminRoutes['export']['permission']`) — the chrome-side gate;
 *      a non-owner who navigates to `?p=admin&c=export` 302s at this
 *      layer BEFORE the page handler runs.
 *   2. **Page handler** (`web/pages/admin.export.php`,
 *      `CheckAdminAccess(ADMIN_OWNER)`) — defence-in-depth at the
 *      page-include layer. Survives a hypothetical refactor of the
 *      routing table that drops the per-route `permission` field.
 *   3. **Streaming entry point** (`web/export.php`,
 *      `HasAccess(WebPermission::Owner)`) — the load-bearing security
 *      gate. A direct curl POST to `/export.php` by a partial-permission
 *      admin hits THIS check, not the chrome's. Bypasses 1 + 2 would
 *      still get blocked here.
 *
 * # Test surface decomposition
 *
 * Two sibling test files cover this contract:
 *
 * 1. **`AdminExportPermissionTest`** (this file) — static-shape
 *    assertions. Pins the gate-call shapes by grepping the source
 *    files. Catches a future refactor that loosens any single site
 *    (someone swapping `WebPermission::Owner` for `ALL_WEB`, dropping
 *    the `CheckAdminAccess` call entirely, etc.). Same shape as
 *    `ApiJsEndpointResolutionTest`'s static gate. Fast — no DB I/O,
 *    no process isolation, just `file_get_contents` + regex.
 *
 * 2. **`AdminExportRuntimePermissionTest`** (sibling file) — runtime
 *    page-handler tests. Each test requires `pages/admin.export.php`
 *    in a fresh PHP process under `#[RunInSeparateProcess]` and
 *    asserts the `CheckAdminAccess` gate's actual runtime behaviour
 *    (302 redirect to `?p=login&m=no_access` for non-owners; no
 *    redirect for owners). Splitting the runtime arm into a sibling
 *    file is the canonical shape PHPUnit's class-per-file discovery
 *    expects — a second class in this file would silently fail to
 *    enumerate at test-list time.
 *
 * The streaming entry point (`web/export.php`) is NOT runtime-tested
 * because its `require_once __DIR__ . '/init.php'` conflicts with
 * `bootstrap.php`'s already-loaded constants (init.php re-defines
 * `SB_VERSION`, `DB_CHARSET`, etc.). The static-shape gates here
 * cover the gate shape; the happy-path bundle output is covered
 * end-to-end by `ExportBundleWriterTest` which drives `BundleWriter`
 * directly. The combination is equivalent coverage.
 *
 * # Why the page-handler runtime branch uses `CheckAdminAccess` not
 *   `ApiError::permission`
 *
 * `CheckAdminAccess` is the panel-wide procedural helper for every
 * admin page handler under `web/pages/admin.*.php`. On a permission
 * miss it does `header('Location: index.php?p=login&m=no_access');
 * exit;` — a 302 to login, NOT a 403. The plan's "Form GET → 403 for
 * non-owners" is correctly interpreted as "the page surface is not
 * reachable for non-owners"; the codebase implements that via the
 * 302-to-login pattern that every other admin page handler uses
 * (e.g. `admin.bans.php`, `admin.servers.php`, `admin.settings.php`).
 * Locking the 302-to-login shape in the sibling test mirrors what's
 * actually shipping; expecting a 403 would gate against an unrelated
 * panel convention.
 */
final class AdminExportPermissionTest extends TestCase
{
    // -----------------------------------------------------------------
    //  Static-shape gate assertions (no DB, no process isolation)
    // -----------------------------------------------------------------

    /**
     * The streaming entry point must reject non-POST requests with
     * a 405. POST is the only method the form sends; GET / HEAD /
     * etc. are hostile probes / address-bar typos. The body must
     * carry `Allow: POST` so a polite client can correct itself.
     */
    public function testEntryPointEnforcesPostOnly(): void
    {
        $src = $this->entryPointSource();
        $this->assertMatchesRegularExpression(
            '/REQUEST_METHOD.*\!==\s*[\'"]POST[\'"]/s',
            $src,
            'export.php must reject non-POST methods with a 405. The form posts; a direct GET is a hostile probe or address-bar typo.',
        );
        $this->assertStringContainsString(
            '405 Method Not Allowed',
            $src,
            'The non-POST branch must surface a 405, not a generic 403/500 (a 405 with Allow: POST is the polite shape).',
        );
        $this->assertStringContainsString(
            'Allow: POST',
            $src,
            'The 405 response must carry an Allow: POST header per RFC 7231 §6.5.5.',
        );
    }

    /**
     * The streaming entry point must call `CSRF::rejectIfInvalid()`
     * BEFORE any DB access / permission check / shared-host hardening
     * fires. A stale tab from yesterday MUST NOT be able to trigger
     * a PII-flush export — the CSRF gate is what enforces "the user
     * meant to do this in this session".
     */
    public function testEntryPointCallsCsrfRejectIfInvalidBeforePermissionGate(): void
    {
        $src      = $this->entryPointSource();
        $csrfPos  = strpos($src, 'CSRF::rejectIfInvalid');
        $hasPerm  = strpos($src, '$userbank->HasAccess(WebPermission::Owner)');

        $this->assertNotFalse(
            $csrfPos,
            'export.php must call CSRF::rejectIfInvalid() — this is the load-bearing gate against cross-site form submission of a PII export.',
        );
        $this->assertNotFalse(
            $hasPerm,
            'export.php must call HasAccess(WebPermission::Owner) — the owner-only gate.',
        );
        $this->assertLessThan(
            $hasPerm,
            $csrfPos,
            'CSRF::rejectIfInvalid must run BEFORE HasAccess(WebPermission::Owner). Order matters: the CSRF gate is a structural contract (any state-changing request), while the owner gate is feature-specific. Inverting the order would let a forged-session attacker who happens to also be an owner bypass CSRF.',
        );
    }

    /**
     * The streaming entry point's permission gate must use
     * `WebPermission::Owner` exclusively — not `ALL_WEB`, not
     * `ADMIN_OWNER | ADMIN_SOME_OTHER_FLAG`, not a string-coerced
     * lookup. Every PII category on the panel is in scope; granular
     * delegation was deliberately deferred per the plan.
     */
    public function testEntryPointGatesOnOwnerExclusively(): void
    {
        $src = $this->entryPointSource();
        $this->assertStringContainsString(
            '$userbank->HasAccess(WebPermission::Owner)',
            $src,
            'export.php must gate on WebPermission::Owner — the entire dataset is PII so per-flag delegation does not apply.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/HasAccess\([^)]*ALL_WEB[^)]*\)/',
            $src,
            'export.php must NOT use ALL_WEB — that would let ANY admin (e.g. a Mod-listing-only operator) trigger a full PII export.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/WebPermission::(?!Owner)\w+/',
            $src,
            'export.php must NOT reference any non-Owner WebPermission case — Owner is the exclusive gate per the plan.',
        );
    }

    /**
     * The non-owner branch must log a warning to the audit log
     * BEFORE returning the 403. A forged POST without permission
     * is exactly the sort of thing operators want to triage
     * after-the-fact — silently swallowing the rejection means
     * an attacker can iteratively probe the form without leaving
     * a forensic trail.
     */
    public function testEntryPointLogsNonOwnerAttempt(): void
    {
        $src = $this->entryPointSource();
        $this->assertMatchesRegularExpression(
            '/Log::add\s*\(\s*LogType::Warning\s*,\s*[\'"]Data Export[\'"]/s',
            $src,
            'export.php must Log::add(LogType::Warning, "Data Export", ...) when a non-owner POST hits the permission gate. Without this every probe is silently swallowed.',
        );
    }

    /**
     * Defence-in-depth: the page handler must re-check the
     * permission via `CheckAdminAccess(ADMIN_OWNER)` even though
     * the routing table already gated `?p=admin&c=export` on
     * `ADMIN_OWNER`. A future refactor of the routing table that
     * drops the per-route `permission` field would silently open
     * the page surface; this re-check is the safety net.
     *
     * `CheckAdminAccess(ADMIN_OWNER)` — not
     * `CheckAdminAccess(ADMIN_OWNER | ADMIN_ANYTHING)`. The
     * defence-in-depth must match the routing-table gate
     * byte-for-byte; otherwise the two surfaces would disagree
     * on what counts as access.
     */
    public function testPageHandlerCallsCheckAdminAccessOwner(): void
    {
        $src = $this->pageHandlerSource();
        $this->assertMatchesRegularExpression(
            '/CheckAdminAccess\s*\(\s*ADMIN_OWNER\s*\)/',
            $src,
            'admin.export.php must call CheckAdminAccess(ADMIN_OWNER) as a defence-in-depth re-check of the routing-table gate.',
        );
    }

    /**
     * The routing table in `page-builder.php` must gate the
     * `export` route on `ADMIN_OWNER`. This is the chrome-side
     * gate that catches non-owners BEFORE the page handler runs.
     */
    public function testRoutingTableGatesOnOwner(): void
    {
        $src = (string) @file_get_contents(ROOT . 'includes/page-builder.php');
        $this->assertNotEmpty(
            $src,
            'page-builder.php must exist — the routing table lives there.',
        );

        // Pin a small window around the 'export' entry so the
        // regex stays anchored even when adjacent rows shift.
        $this->assertMatchesRegularExpression(
            "/['\"]export['\"]\s*=>\s*\[[^\]]*ADMIN_OWNER[^\]]*\]/s",
            $src,
            "page-builder.php's \$adminRoutes['export'] must gate on ADMIN_OWNER. Loosening this to ALL_WEB or dropping the gate would let any admin reach the chrome surface.",
        );
    }

    /**
     * The navbar's admin section must gate the Export entry on
     * `ADMIN_OWNER`. UX-side gating only — the chrome-side rule
     * that hides the link from non-owners; the security gate is
     * the routing table + page handler + entry point above.
     *
     * The catalog's entries are arrays with sibling `endpoint` /
     * `permission` keys on separate lines, so the regex anchors
     * on a ~5-line window around the `'endpoint' => 'export'`
     * line and asserts the window mentions `ADMIN_OWNER` exactly
     * (no `|`-OR with any other flag — Owner-only).
     */
    public function testNavbarGatesOnOwner(): void
    {
        $src = (string) @file_get_contents(ROOT . 'pages/core/navbar.php');
        $this->assertNotEmpty(
            $src,
            'navbar.php must exist — the admin chrome navigation entries live there.',
        );

        $window = $this->catalogEntryWindow($src, "'endpoint' => 'export'");
        $this->assertNotNull(
            $window,
            "navbar.php must register an 'endpoint' => 'export' entry for the Data Export route.",
        );
        $this->assertMatchesRegularExpression(
            "/'permission'\s*=>\s*ADMIN_OWNER(?!\s*\|)/",
            $window,
            "navbar.php's 'export' entry must gate exclusively on ADMIN_OWNER (no OR-with-other-flags — Owner is the exclusive gate). Window: $window",
        );
    }

    /**
     * The command palette's "Data export" entry must gate on
     * `ADMIN_OWNER`. The chrome-side rule: the palette must
     * agree with the navbar (both are UX surfaces; neither is
     * load-bearing security). Per AGENTS.md "Filtered chrome
     * navigation surfaces", these two surfaces are sister
     * surfaces — when adding a new entry to either, the other
     * gets the matching change in the same PR.
     *
     * PaletteActions wraps the constant in
     * `defined('ADMIN_OWNER') ? (int) constant('ADMIN_OWNER') : 0`
     * to stay safe under test bootstraps that don't load the
     * permission constants (mirrors the `ALL_WEB` sibling for
     * the "Admin panel" entry). The 0-fallback is harmless: an
     * unauthenticated user already fails the `for()` filter's
     * `is_admin()` short-circuit, and a logged-in user can never
     * legitimately have a permission mask of 0 (the seeded
     * admin row carries `extraflags=16777216`).
     */
    public function testPaletteEntryGatesOnOwner(): void
    {
        $src = (string) @file_get_contents(ROOT . 'includes/View/PaletteActions.php');
        $this->assertNotEmpty(
            $src,
            'PaletteActions.php must exist — the palette catalog lives there.',
        );

        $this->assertStringContainsString(
            '?p=admin&c=export',
            $src,
            'PaletteActions must surface the data-export entry via its href.',
        );

        $window = $this->catalogEntryWindow($src, '?p=admin&c=export');
        $this->assertNotNull(
            $window,
            'Could not locate the export entry in PaletteActions.php.',
        );

        // Either the modern enum form or the legacy ADMIN_OWNER
        // constant is acceptable — both yield the same gate
        // value. What we DON'T accept is a different / weaker
        // permission (ALL_WEB, a per-flag OR, etc.).
        $hasOwnerConstant = (bool) preg_match(
            "/'permission'\s*=>\s*[^,]*\bADMIN_OWNER\b/",
            $window,
        );
        $hasOwnerEnum = (bool) preg_match(
            "/'permission'\s*=>\s*[^,]*WebPermission::Owner/",
            $window,
        );
        $this->assertTrue(
            $hasOwnerConstant || $hasOwnerEnum,
            "Palette export entry must gate on ADMIN_OWNER (or WebPermission::Owner). Window: $window",
        );
        // And NOT on any other flag — Owner is the exclusive
        // gate. Reject e.g. `ADMIN_OWNER|ADMIN_LIST_ADMINS`.
        $this->assertDoesNotMatchRegularExpression(
            '/ADMIN_OWNER\s*\|\s*ADMIN_/',
            $window,
            "Palette export entry must NOT OR ADMIN_OWNER with any other flag — the surface is Owner-only. Window: $window",
        );
    }

    /**
     * Locate a catalog entry (an array literal) containing the
     * given anchor substring, and return a ~7-line window
     * centered on it. Returns null if no line carries the anchor.
     *
     * Catalog rows in `navbar.php` / `page-builder.php` /
     * `PaletteActions.php` are multi-line array literals; a
     * single-line regex against a key/value pair would miss the
     * sibling keys. The window is wide enough to capture every
     * sibling key (icon / label / href / endpoint / permission /
     * config) and narrow enough not to grab the next array
     * element's keys (which would let a sibling Owner row pass
     * the assertion when our row is wrong).
     */
    private function catalogEntryWindow(string $src, string $anchor): ?string
    {
        $lines = explode("\n", $src);
        foreach ($lines as $idx => $line) {
            if (str_contains($line, $anchor)) {
                return implode(
                    "\n",
                    array_slice($lines, max(0, $idx - 3), 7),
                );
            }
        }
        return null;
    }

    /**
     * Forbidden cross-check: neither web.json nor api-contract.js
     * may carry a permission flag specific to the data-export
     * feature. The plan explicitly forbids new flags here —
     * gating is exclusively on the existing `ADMIN_OWNER`. A
     * future PR that drifts toward "let's add a granular flag"
     * fails this gate first.
     */
    public function testNoNewPermissionFlagAddedForExport(): void
    {
        $webJson = (string) @file_get_contents(ROOT . 'configs/permissions/web.json');
        $this->assertNotEmpty(
            $webJson,
            'web.json must exist — the permission catalog lives there.',
        );
        $this->assertStringNotContainsString(
            'ADMIN_EXPORT',
            $webJson,
            'web.json must NOT carry an ADMIN_EXPORT* flag — the feature is exclusively gated on ADMIN_OWNER per the plan.',
        );

        $contract = (string) @file_get_contents(ROOT . 'scripts/api-contract.js');
        if ($contract !== '') {
            $this->assertStringNotContainsString(
                'EXPORT_DATA',
                $contract,
                'api-contract.js must NOT carry an EXPORT_DATA permission — the contract should be unchanged by this feature.',
            );
            $this->assertStringNotContainsString(
                'AdminExport',
                $contract,
                'api-contract.js must NOT carry an AdminExport action — the feature uses a top-level streaming entry point, not a JSON handler.',
            );
        }
    }

    // -----------------------------------------------------------------
    //  Source-file readers (single quote-friendly grep targets)
    // -----------------------------------------------------------------

    private function entryPointSource(): string
    {
        $src = (string) @file_get_contents(ROOT . 'export.php');
        $this->assertNotEmpty(
            $src,
            'web/export.php must exist — the streaming entry point lives there.',
        );
        return $src;
    }

    private function pageHandlerSource(): string
    {
        $src = (string) @file_get_contents(ROOT . 'pages/admin.export.php');
        $this->assertNotEmpty(
            $src,
            'web/pages/admin.export.php must exist — the page handler lives there.',
        );
        return $src;
    }
}
