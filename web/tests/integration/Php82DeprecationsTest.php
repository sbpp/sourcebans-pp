<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use ErrorException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;
use Smarty\Smarty;

/**
 * Issue #1273: PHP 8.1 deprecated implicit `null` -> scalar coercion for
 * internal functions (`strlen($null)`, `trim($null)`, `substr($null,...)`,
 * etc.). PHP 9 will turn the deprecation into a `TypeError`. Per
 * `web/composer.json`, the panel requires `php >= 8.5`, so we're firmly
 * inside the deprecation window.
 *
 * Naming note: the class + filename keep their `Php82` prefix because
 * that's the panel's PHP floor at the time the gate was added (#1273).
 * The actual surface this test validates is the null-into-scalar
 * coercion that PHP 8.1 deprecated and PHP 9 will fatal on — the
 * deprecation predates our floor and outlives any single floor bump
 * (we landed `>=8.5` in #1289 and the gate is unchanged). Renaming to
 * `NullIntoScalarDeprecationsTest` would be more descriptive but the
 * `git mv` churn isn't worth it; the AGENTS.md "Where to find what"
 * row at "Trap PHP 8.1 null-into-scalar deprecations at runtime"
 * already describes what the test does, not when the floor was.
 *
 * The PHPStan deprecation-rules plugin (#1273) is the static gate that
 * blocks new offenders inside the analyzable codebase. This test is the
 * runtime gate — PHPStan can't see `web/includes/auth/openid.php` (it's
 * excluded), and a row whose runtime value is `null` despite the column
 * looking `NOT NULL` to the type system can still slip through. The
 * trap below promotes any `E_DEPRECATED` raised while a marquee page
 * renders into a thrown `ErrorException`, so the test fails with the
 * exact site (file + line) instead of silently printing into
 * `display_errors` like it does today.
 *
 * Each test method runs in a separate process because the page handlers
 * declare top-level helper functions like `setPostKey()` that PHP can't
 * redeclare inside one process. Process isolation also guarantees each
 * test starts from a clean error-handler stack and `$_SESSION` /
 * `$_GET` / `$GLOBALS` state so the deprecation trap only sees errors
 * from the page under test.
 */
final class Php82DeprecationsTest extends ApiTestCase
{
    /**
     * Promote `E_DEPRECATED` / `E_USER_DEPRECATED` into thrown
     * `ErrorException`s so the failing PHPUnit assertion points at the
     * exact `<file>:<line>` that triggered the deprecation. Other
     * severities fall through to PHPUnit's default handler so we don't
     * accidentally swallow real warnings or errors.
     *
     * Bumps `error_reporting()` to `E_ALL` first because PHPUnit's
     * bootstrap (and `init.php` in non-dev mode) leaves
     * `E_NOTICE | E_DEPRECATED` masked off. Without the bump, the new
     * error handler would never fire on a deprecation.
     */
    private static function trapDeprecations(): void
    {
        error_reporting(E_ALL);
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                throw new ErrorException($message, 0, $severity, $file, $line);
            }
            // Let PHPUnit's default handler take everything else.
            return false;
        });
    }

    /**
     * Capture-only Smarty stub. Page handlers reach for
     * `$theme->assign(...)`, `$theme->display(...)`, and
     * `Sbpp\View\Renderer::render($theme, $view)` (which type-hints
     * `Smarty $theme`). Subclassing Smarty keeps the type compatible
     * with the production signatures; both methods are no-ops because
     * the contract under test is the PHP code path, not the Smarty
     * render itself. Mirrors the LostPasswordChromeTest pattern.
     */
    private function makeStubTheme(): Smarty
    {
        return new class extends Smarty {
            /** @phpstan-ignore method.childParameterType */
            public function assign($tpl_var, $value = null, $nocache = false, $scope = null)
            {
                return $this;
            }

            public function display($template = null, $cache_id = null, $compile_id = null)
            {
                return '';
            }
        };
    }

    /**
     * Wire the globals every page handler under `web/pages/*` reads
     * straight from `$GLOBALS` (`$theme`, `$userbank`, `$PDO`). Page
     * handlers each open with their own `global $theme, $userbank;`
     * declaration, which then resolves against `$GLOBALS[...]`, so we
     * only need to set the entries — no `global` declaration here.
     * Returns the stub Smarty so callers can keep a reference if they
     * want to assert against captured calls (we don't here).
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
     * The headline regression: `page.banlist.php:724` reads
     * `$row['ban_ip']` and feeds it to `strlen(...)`. The column is
     * declared `varchar(32) default NULL` (see
     * `web/install/includes/sql/struc.sql`), so a web-only ban that
     * was created without an IP will yield `null` — and pre-#1273
     * the page rendered exactly one deprecation per such row.
     *
     * The fixture inserts one ban with `ip = NULL` so the foreach
     * over the result set actually exercises the cast path; without
     * a row the page short-circuits into the empty-state branch and
     * the regression goes uncaught.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testBanlistRendersWithoutDeprecationsForNullIpRow(): void
    {
        $this->loginAsAdmin();
        $this->seedBanWithNullIp();
        $this->bootRenderHarness();

        $_SESSION = [];
        $_GET     = [];

        self::trapDeprecations();
        ob_start();
        try {
            require ROOT . 'pages/page.banlist.php';
        } finally {
            ob_end_clean();
            restore_error_handler();
        }

        $this->assertTrue(true, 'page.banlist.php rendered without raising any deprecation notice.');
    }

    /**
     * commslist mirrors banlist's pagination + filter shape; the
     * `$prev` / `$next` strings used to be checked with `strlen(...)`
     * and the `$_GET['searchText']` / `$_GET['advSearch']` values
     * with `trim(...)` (see #1273 issue body). Render with default
     * GET state to exercise the empty-pagination branch + the
     * filter-empty branch in one pass.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCommsListRendersWithoutDeprecations(): void
    {
        $this->loginAsAdmin();
        $this->bootRenderHarness();

        $_SESSION = [];
        $_GET     = [];

        self::trapDeprecations();
        ob_start();
        try {
            require ROOT . 'pages/page.commslist.php';
        } finally {
            ob_end_clean();
            restore_error_handler();
        }

        $this->assertTrue(true, 'page.commslist.php rendered without raising any deprecation notice.');
    }

    /**
     * admin.bans.php has the worst per-file count (8 sites in #1273's
     * `rg -c` table), all on the `$prev`/`$next` pagination shape
     * across four nested sections (current protests / archive /
     * current submissions / archive). Rendering the surface end-to-end
     * exercises every one of them in a single pass.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAdminBansRendersWithoutDeprecations(): void
    {
        $this->loginAsAdmin();
        $this->bootRenderHarness();

        $_SESSION = [];
        $_GET     = [];
        // admin.bans.php reads `$_SERVER['REMOTE_ADDR']` for the
        // import-bans branch; the bootstrap already defaults it but
        // belt-and-braces in case a previous test left it cleared.
        $_SERVER['REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        self::trapDeprecations();
        ob_start();
        try {
            require ROOT . 'pages/admin.bans.php';
        } finally {
            ob_end_clean();
            restore_error_handler();
        }

        $this->assertTrue(true, 'admin.bans.php rendered without raising any deprecation notice.');
    }

    /**
     * admin.admins.php has the same `$prev`/`$next` strlen pattern
     * (lines 362/365 pre-fix) plus a couple of `preg_match` calls
     * over `$_GET['advSearch']` shimmed into the modern filter shape.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAdminAdminsRendersWithoutDeprecations(): void
    {
        $this->loginAsAdmin();
        $this->bootRenderHarness();

        $_SESSION = [];
        $_GET     = [];

        self::trapDeprecations();
        ob_start();
        try {
            require ROOT . 'pages/admin.admins.php';
        } finally {
            ob_end_clean();
            restore_error_handler();
        }

        $this->assertTrue(true, 'admin.admins.php rendered without raising any deprecation notice.');
    }

    /**
     * The OpenID 2.0 vendored library (`web/includes/auth/openid.php`)
     * is excluded from PHPStan, so the per-call deprecation surface
     * is not statically gated. The fix at #1273 casts `$value` and
     * `$_SERVER['REQUEST_URI']` at the entry points; this test pins
     * the contract by constructing the LightOpenID object the same
     * way `web/pages/page.login.php` does (Steam OpenID with default
     * `$_SERVER` state) so a future regression in `set()` or the
     * constructor's `$_SERVER` reads trips the deprecation trap.
     *
     * We intentionally don't dispatch a real Steam request — that
     * would require network egress. Construction + the `set('realm', …)`
     * write path is enough to hit every `(string)` cast added at
     * #1273 in this file (the constructor's `parse_url` + the magic
     * setter's `trim((string) $value)`).
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testOpenIdConstructionDoesNotEmitDeprecations(): void
    {
        require_once ROOT . 'includes/auth/openid.php';

        // Mirror the fields page.login.php's Steam branch sets — the
        // only thing the constructor actually needs is HTTP_HOST.
        $_SERVER['HTTP_HOST']      = 'localhost';
        $_SERVER['REQUEST_URI']    = '/index.php?p=login';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        self::trapDeprecations();
        try {
            $openid = new \LightOpenID('localhost');
            // The magic setter for `realm` / `trustRoot` was the
            // second site that took a non-string `$value`; pin both
            // assignments so a regression in the cast tracks back to
            // this test.
            $openid->realm     = 'http://localhost/';
            $openid->trustRoot = 'http://localhost/';
        } finally {
            restore_error_handler();
        }

        $this->assertSame('http://localhost/', $openid->trustRoot,
            'LightOpenID::set() should still propagate the trustRoot value through the (string) cast.');
    }

    /**
     * Insert one minimal ban row whose `ip` column is `NULL`. Keeps
     * the SQL string short and ASCII-only so test output stays
     * readable when this fails. Foreign keys / constraints on
     * `:prefix_bans` are nominal; only `name`, `reason` are NOT NULL
     * (see struc.sql) so we only need to populate those + the ones
     * the banlist SELECT reads back.
     */
    private function seedBanWithNullIp(): void
    {
        $pdo  = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_bans`
                (ip, authid, name, created, ends, length, reason, aid, adminIp, sid, country, type)
             VALUES (NULL, ?, ?, UNIX_TIMESTAMP(), 0, 0, ?, 0, ?, 0, NULL, 0)',
            DB_PREFIX
        ));
        $stmt->execute([
            'STEAM_0:0:1',
            'NullIpPlayer',
            'no-ip ban (deprecation regression fixture for #1273)',
            '127.0.0.1',
        ]);
    }
}
