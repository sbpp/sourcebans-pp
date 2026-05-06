<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Smarty\Smarty;

/**
 * Issue #1207 AUTH-1: the password-recovery page rendered the admin
 * sidebar (Admin Panel, Admins, Servers, Bans, …) when a logged-in
 * admin happened to visit `?p=lostpassword`. A user trying to recover
 * their password is by definition logged out, so this surface should
 * render the public chrome (the same shape the login page uses).
 *
 * The fix is two-pronged:
 *
 *   1. `web/pages/page.lostpassword.php` redirects logged-in visitors
 *      to `index.php` BEFORE rendering the form body, mirroring the
 *      equivalent guard in `page.login.php` (a `<script>window.location
 *      …</script>` + `exit;` shape — `header('Location:')` would no-op
 *      because the chrome above already flushed output). Logged-in
 *      admins simply never see the form. That redirect's runtime shape
 *      isn't directly testable in PHPUnit without process isolation;
 *      the E2E spec (`smoke/routing-truthiness.spec.ts`, the
 *      `AUTH-1: logged-in visitors get bounced off lostpassword`
 *      describe block) covers the runtime observable.
 *   2. The navbar (the chrome's sidebar) gates the admin section on
 *      `$userbank->is_admin()`, so an unauthenticated visitor — which
 *      is the only state where the form actually renders — can never
 *      see the admin links. This test pins THAT invariant: the
 *      `$isAdmin` / `$login` template variables that drive
 *      `core/navbar.tpl` resolve to `false` for an unauthenticated
 *      user, so `{if $isAdmin}…` doesn't render the admin section.
 *
 * If a future refactor breaks this contract (e.g. defaults `$isAdmin`
 * to true, or stops checking `is_admin()` against the request's
 * authentication state) the leak the issue describes comes back even
 * though the page-handler redirect still fires for logged-in users.
 * This test holds the chrome accountable independently.
 */
final class LostPasswordChromeTest extends ApiTestCase
{
    /**
     * Capture every `$theme->assign()` call navbar.php makes against a
     * stubbed Smarty so we can assert the chrome's auth-driven
     * variables without booting the real templates.
     *
     * @return array<string, mixed>
     */
    private function captureNavbarAssigns(): array
    {
        $captured = [];

        // Stub Smarty: we only use assign() (capture) and display() (no-op).
        // Anonymous subclass keeps the type compatible with navbar.php's
        // $theme->display('core/navbar.tpl') call without rendering anything.
        // Method signatures mirror Smarty\Data::assign() and
        // Smarty\Smarty::display() exactly so PHP's signature-compat check
        // passes; the implementations are minimal (capture / no-op).
        $theme = new class extends Smarty {
            /** @var array<string, mixed> */
            public array $captured = [];

            public function assign($tpl_var, $value = null, $nocache = false, $scope = null)
            {
                if (is_array($tpl_var)) {
                    foreach ($tpl_var as $k => $v) {
                        $this->captured[(string) $k] = $v;
                    }
                } else {
                    $this->captured[(string) $tpl_var] = $value;
                }
                return $this;
            }

            // Override display() to a no-op; we don't render the .tpl,
            // we only need the assign() side-effects.
            public function display($template = null, $cache_id = null, $compile_id = null)
            {
                return '';
            }
        };

        global $userbank;
        $userbank = $GLOBALS['userbank'];
        $GLOBALS['theme'] = $theme;

        // navbar.php is a procedural script that reads $userbank from
        // globals and writes via $theme->assign(); include it so we
        // exercise the production code path verbatim. Every test gets
        // a fresh script-include, so we can't `require_once`.
        require ROOT . 'pages/core/navbar.php';

        $captured = $theme->captured;
        unset($GLOBALS['theme']);
        return $captured;
    }

    /**
     * The unauthenticated-visitor case — the only state in which the
     * lostpassword form actually renders. The auth-driven booleans
     * that the chrome gates the admin section on resolve to false,
     * the public navbar excludes the `admin` entry, and the public
     * navbar entries themselves remain so the user can navigate away.
     *
     * `adminbar` itself may still hold data (navbar.php's filtering
     * loop runs only inside `if ($userbank->is_admin())`, so the
     * raw array stays in `$admin` for logged-out users), but
     * `core/navbar.tpl` gates the entire admin section on `$isAdmin`
     * — so an `isAdmin=false` assertion is the load-bearing one.
     */
    public function testUnauthenticatedNavbarHidesAdminSection(): void
    {
        // ApiTestCase::setUp() already wires an unauthenticated
        // userbank (`new CUserManager(null)`). Belt-and-braces:
        $GLOBALS['userbank'] = new \CUserManager(null);

        $assigns = $this->captureNavbarAssigns();

        $this->assertArrayHasKey('isAdmin', $assigns);
        $this->assertArrayHasKey('login',   $assigns);
        $this->assertArrayHasKey('navbar',  $assigns);

        $this->assertFalse($assigns['isAdmin'],
            'isAdmin must be false for an unauthenticated visitor — `core/navbar.tpl` keys the entire admin sidebar group off this boolean (#1207 AUTH-1).');
        $this->assertFalse($assigns['login'],
            'login must be false for an unauthenticated visitor — navbar.tpl uses it to swap the bottom-left "admin / Logout" cluster for a "Login" link.');

        // Public navbar entries still ship so the user can get back to
        // /login or /home; the leak the issue describes was specifically
        // about the *admin* section, not the public links.
        $this->assertNotEmpty($assigns['navbar'],
            'Public navbar entries must remain so the user can navigate away from the lostpassword form.');

        // The `admin` endpoint is filtered out of the public navbar for
        // unauthenticated users — navbar.php gates its `permission`
        // entry on `$userbank->is_admin()`, which is false here.
        $endpoints = array_column($assigns['navbar'], 'endpoint');
        $this->assertNotContains('admin', $endpoints,
            'The `admin` nav entry must be filtered out for unauthenticated users — navbar.php gates it on $userbank->is_admin().');
    }

    /**
     * Anti-confusion guard: a logged-in admin DOES see the admin
     * section. This is the chrome's normal contract; we test it so a
     * future refactor can't accidentally hide the admin section for
     * everyone in an attempt to fix the AUTH-1 leak.
     *
     * (In production a logged-in admin never reaches the lostpassword
     * page handler — the redirect in page.lostpassword.php sends them
     * to `index.php` first. But the chrome layer doesn't know that;
     * its contract is the same on every page.)
     */
    public function testAuthenticatedAdminNavbarIncludesAdminSection(): void
    {
        $this->loginAsAdmin();

        $assigns = $this->captureNavbarAssigns();

        $this->assertTrue($assigns['isAdmin']);
        $this->assertTrue($assigns['login']);
        $this->assertNotEmpty($assigns['adminbar'],
            'Admin sidebar must list at least one sub-route for an ADMIN_OWNER user.');
    }
}
