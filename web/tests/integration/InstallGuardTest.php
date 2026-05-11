<?php

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PDOException;
use PHPUnit\Framework\TestCase;

/**
 * Issue #1335 C1 + C2: post-install panel-takeover paths.
 *
 * Two separate findings, one shared theme: the install wizard and
 * the panel's first-boot guard didn't share a security model
 * post-#1332, so anyone reaching `/install/` after a successful
 * install OR anyone hitting the panel runtime with a `localhost`
 * Host header could re-run the wizard / bypass the
 * "delete install/ directory" warning.
 *
 * The fix splits the guard logic into pure functions
 * (`sbpp_check_install_guard()`, `sbpp_install_is_already_installed()`)
 * so the verdict is testable without a full wizard / panel boot.
 * The render path stays inline (HTML + CSS, no Sbpp\… deps,
 * mirrors `web/install/recovery.php`'s contract); a separate
 * runtime smoke test against the live docker stack covers the
 * end-to-end "guard fires → page renders" loop, but the unit
 * shape pinned here is the gate.
 *
 * The four test methods cover:
 *
 *   1. {@see testLocalhostHostHeaderDoesNotBypassInstallGuard} — C1:
 *      the install/-presence guard fires regardless of `Host` header.
 *      Pre-fix the `HTTP_HOST != "localhost"` exemption silently let
 *      the panel boot when the directory was on disk.
 *   2. {@see testIsUpdateExemptionStillSkipsGuard} — the IS_UPDATE
 *      escape hatch is preserved; the updater itself defines this so
 *      it can run while updater/ is on disk.
 *   3. {@see testDevKeepInstallExemptionSkipsGuard} — the new
 *      SBPP_DEV_KEEP_INSTALL escape hatch (replaces the localhost
 *      bypass) skips the guard for the docker dev stack.
 *   4. {@see testWizardRefusesToStartOverInstalledPanel} — C2: the
 *      "is panel already installed?" check returns true when
 *      config.php exists.
 *   5. {@see testPdoErrorTranslationCoversCommonCodes} — m4: the
 *      step-2 PDOException translator emits human-readable messages
 *      for the common connect-error codes (1045, 2002, 1049, 1044)
 *      and falls back to the raw message for unrecognised codes.
 *   6. {@see testFilesystemCheckEmitsDistinctRemediations} — M2
 *      review: the step-3 filesystem-check helper pairs each
 *      failure shape (missing vs not-writable) with the right
 *      remediation. Pre-fix the same chmod hint was glued onto
 *      both branches, even though chmod can't fix a directory
 *      that doesn't exist.
 *
 * No DB needed — these tests are purely about the guard verdicts,
 * not the rendered HTML or live panel boot. Extending TestCase
 * (not ApiTestCase) so setUp doesn't run Fixture::reset(); this
 * keeps the class fast and DB-independent.
 */
final class InstallGuardTest extends TestCase
{
    /**
     * Load the panel-side recovery helper. Idempotent — reaches for
     * `function_exists()` so `setUp` can be called once per test
     * method without redeclaration errors. The file is plain
     * function declarations + a `: never`-return helper; it has zero
     * `Sbpp\…` dependencies and runs upstream of Composer (mirror
     * of `recovery.php`'s contract).
     */
    private function loadInitRecovery(): void
    {
        if (!function_exists('sbpp_check_install_guard')) {
            require_once ROOT . 'init-recovery.php';
        }
    }

    /**
     * Load the wizard-side already-installed helper. Same shape as
     * the init-side loader above.
     */
    private function loadAlreadyInstalled(): void
    {
        if (!function_exists('sbpp_install_is_already_installed')) {
            require_once ROOT . 'install/already-installed.php';
        }
    }

    /**
     * Load the wizard's step-handler helpers (prefix validation, DB
     * probe, KeyValues escape, PDO-error translation). Lives under
     * `web/install/includes/helpers.php` because it's
     * post-vendor-only — `sbpp_install_open_db` instantiates
     * `\Database`. The PDO-error translator
     * (`sbpp_install_translate_pdo_error`) doesn't need vendor at
     * all, so loading the file directly under PHPUnit (which
     * already wires Composer) is fine.
     */
    private function loadInstallHelpers(): void
    {
        if (!function_exists('sbpp_install_translate_pdo_error')) {
            require_once ROOT . 'install/includes/helpers.php';
        }
    }

    /**
     * Make a temp directory whose contents tickle the panel guard.
     * The function reads files under `$root/install` and
     * `$root/updater` — we create them as sentinel directories so
     * the guard's `file_exists()` call returns true.
     *
     * @return array{root: string, install: string, updater: string}
     */
    private function makeTempPanelRoot(): array
    {
        $root = sys_get_temp_dir() . '/sbpp_1335_guard_' . bin2hex(random_bytes(4));
        mkdir($root, 0o775, true);
        $install = $root . '/install';
        $updater = $root . '/updater';
        return ['root' => $root, 'install' => $install, 'updater' => $updater];
    }

    /**
     * Recursive teardown for the temp panel root. Tests create at
     * most an `install/` and an `updater/` directory; we don't risk
     * accidentally walking into a real worktree because the path
     * lives under `sys_get_temp_dir()`.
     */
    private function rmTempRoot(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }
        foreach (['install', 'updater'] as $dir) {
            $path = $root . '/' . $dir;
            if (is_dir($path)) {
                @rmdir($path);
            }
        }
        @rmdir($root);
    }

    /**
     * Issue #1335 C1: the headline regression. Pre-fix `web/init.php`
     * had:
     *
     *     if ($_SERVER['HTTP_HOST'] != "localhost" && !defined("IS_UPDATE")) {
     *         if (file_exists(ROOT."/install")) { die('...'); }
     *     }
     *
     * which silently exempted any panel reachable via a `Host:
     * localhost` header from the install/-presence guard
     * (port-forward, SSH tunnel, ngrok, Cloudflare Tunnel, local
     * iteration). The post-fix function ignores `Host` entirely.
     *
     * The test creates a temp `install/` directory + asserts the
     * guard fires regardless of whether the (now-irrelevant) Host
     * header was `localhost` or anything else. The function
     * signature carries no Host param post-fix — but the test still
     * pins the contract that the verdict doesn't depend on
     * `$_SERVER` shape.
     */
    public function testLocalhostHostHeaderDoesNotBypassInstallGuard(): void
    {
        $this->loadInitRecovery();
        $tmp = $this->makeTempPanelRoot();
        try {
            mkdir($tmp['install'], 0o775);

            // Even if a future regression re-introduces a Host check,
            // this test is the canary — set HTTP_HOST to localhost
            // (the historical bypass value) and assert the guard
            // STILL fires. The check function takes no Host param,
            // so this is an extra-belt assertion that the function
            // signature itself is right (no implicit `$_SERVER`
            // reads inside the function body).
            $_SERVER['HTTP_HOST'] = 'localhost';

            $verdict = sbpp_check_install_guard($tmp['root'], false, false);

            $this->assertSame('install', $verdict,
                'C1 regression: install/-presence guard must fire even when ' .
                'HTTP_HOST=localhost. Pre-#1335 the localhost-Host bypass was a ' .
                'panel-takeover path on any panel reachable via a localhost Host ' .
                'header (port-forward, SSH tunnel, ngrok, Cloudflare Tunnel).');
        } finally {
            $this->rmTempRoot($tmp['root']);
        }
    }

    /**
     * The `IS_UPDATE` escape hatch is preserved. The updater itself
     * defines this constant before requiring init.php so it can run
     * while `updater/` is still on disk. Without the exemption, the
     * panel would refuse to run the upgrade scripts that delete
     * `updater/` — circular failure.
     */
    public function testIsUpdateExemptionStillSkipsGuard(): void
    {
        $this->loadInitRecovery();
        $tmp = $this->makeTempPanelRoot();
        try {
            mkdir($tmp['updater'], 0o775);

            $verdict = sbpp_check_install_guard($tmp['root'], true, false);

            $this->assertNull($verdict,
                'IS_UPDATE exemption must still bypass the guard so the updater ' .
                'can run while updater/ is on disk.');
        } finally {
            $this->rmTempRoot($tmp['root']);
        }
    }

    /**
     * The `SBPP_DEV_KEEP_INSTALL` escape hatch (new in #1335) skips
     * the guard for the docker dev stack — the bind-mounted worktree
     * carries `install/` + `updater/` from git, so the guard would
     * otherwise refuse to boot the dev panel. Production panels
     * MUST NOT define this constant; the helper file's docblock
     * spells out the contract.
     */
    public function testDevKeepInstallExemptionSkipsGuard(): void
    {
        $this->loadInitRecovery();
        $tmp = $this->makeTempPanelRoot();
        try {
            mkdir($tmp['install'], 0o775);

            $verdict = sbpp_check_install_guard($tmp['root'], false, true);

            $this->assertNull($verdict,
                'SBPP_DEV_KEEP_INSTALL exemption must bypass the guard so the ' .
                'docker dev stack boots without manual install/ deletion.');
        } finally {
            $this->rmTempRoot($tmp['root']);
        }
    }

    /**
     * Issue #1335 C2: pre-fix `web/install/index.php` had no
     * "is the panel already installed?" gate. After a successful
     * wizard run, re-visiting `/install/` re-rendered step 1 and
     * let the operator walk the entire flow again — including
     * overwriting `config.php` (when writable), creating a new
     * admin account, and re-pointing the panel at a different DB.
     * That's a complete panel-takeover path.
     *
     * The function simply checks for `config.php` in the panel
     * root. The test creates a stub config.php in a temp panel
     * root and asserts the function returns true; absent file
     * returns false.
     */
    public function testWizardRefusesToStartOverInstalledPanel(): void
    {
        $this->loadAlreadyInstalled();
        $tmp = $this->makeTempPanelRoot();
        try {
            $this->assertFalse(sbpp_install_is_already_installed($tmp['root'] . '/'),
                'Empty panel root should not look installed.');

            file_put_contents($tmp['root'] . '/config.php', '<?php // stub');

            $this->assertTrue(sbpp_install_is_already_installed($tmp['root'] . '/'),
                'C2 regression: the wizard must refuse to start when config.php ' .
                'exists in the panel root. Pre-#1335 the wizard had no ' .
                '"already installed?" gate; combined with C1 (or with any operator ' .
                'who simply forgot to delete install/), this was a panel-takeover ' .
                'path.');

            @unlink($tmp['root'] . '/config.php');
        } finally {
            @unlink($tmp['root'] . '/config.php');
            $this->rmTempRoot($tmp['root']);
        }
    }

    /**
     * Issue #1335 m4: the step-2 PDOException translator emits
     * friendlier messages for the connect-error codes
     * non-technical operators are most likely to hit. Pre-fix the
     * wizard surfaced the raw PDO message verbatim
     * (`SQLSTATE[HY000] [1045] Access denied for user
     * 'sourcebans'@'192.168.96.5' (using password: YES)`), which
     * is gibberish to non-DBAs and includes a minor
     * information-disclosure detail (the panel-as-seen-by-DB
     * internal IP).
     *
     * The translator pattern-matches the four error codes the
     * issue calls out (1045 access denied, 2002 host unreachable,
     * 1049 unknown DB, 1044 denied for user on database) and
     * falls back to the raw message for unrecognised codes.
     */
    public function testPdoErrorTranslationCoversCommonCodes(): void
    {
        $this->loadInstallHelpers();

        // 1045 — access denied; message should NOT echo the raw
        // PDO `SQLSTATE` or the panel's internal IP, and SHOULD
        // hint at the credential typo.
        $err1045 = new PDOException(
            "SQLSTATE[HY000] [1045] Access denied for user 'sourcebans'@'192.168.96.5' (using password: YES)"
        );
        $msg1045 = sbpp_install_translate_pdo_error(
            $err1045, 'localhost', 'sourcebans', 'sourcebans'
        );
        $this->assertStringContainsStringIgnoringCase('username or password is wrong', $msg1045);
        $this->assertStringNotContainsString('SQLSTATE', $msg1045);
        $this->assertStringNotContainsString('192.168.96.5', $msg1045);

        // 2002 — host unreachable.
        $err2002 = new PDOException(
            'SQLSTATE[HY000] [2002] No such file or directory'
        );
        $msg2002 = sbpp_install_translate_pdo_error(
            $err2002, 'badhost', 'user', 'db'
        );
        $this->assertStringContainsString('badhost', $msg2002);
        $this->assertStringNotContainsString('SQLSTATE', $msg2002);

        // 1049 — unknown DB.
        $err1049 = new PDOException(
            "SQLSTATE[HY000] [1049] Unknown database 'sourcebans'"
        );
        $msg1049 = sbpp_install_translate_pdo_error(
            $err1049, 'localhost', 'user', 'sourcebans'
        );
        $this->assertStringContainsString('"sourcebans"', $msg1049);
        $this->assertStringContainsStringIgnoringCase("doesn't exist", $msg1049);

        // 1044 — denied for user on database.
        $err1044 = new PDOException(
            "SQLSTATE[HY000] [1044] Access denied for user 'sourcebans'@'%' to database 'sb_prod'"
        );
        $msg1044 = sbpp_install_translate_pdo_error(
            $err1044, 'localhost', 'sourcebans', 'sb_prod'
        );
        $this->assertStringContainsString('"sourcebans"', $msg1044);
        $this->assertStringContainsString('"sb_prod"', $msg1044);
        $this->assertStringContainsStringIgnoringCase('permission', $msg1044);

        // Unrecognised code — message should fall back to the raw
        // string so debugging stays possible.
        $errOther = new PDOException(
            'SQLSTATE[HY000] [1234] some completely novel error nobody has ever seen before'
        );
        $msgOther = sbpp_install_translate_pdo_error(
            $errOther, 'localhost', 'user', 'db'
        );
        $this->assertStringContainsString('Could not connect to the database', $msgOther);
        $this->assertStringContainsString('completely novel error', $msgOther);
    }

    /**
     * Issue #1335 M2 review: pre-review the same writable hint
     * (`set permissions to 0775 ... via chmod`) was appended to
     * BOTH the `Missing:` and `Not writable:` branches in
     * `web/install/pages/page.3.php`. For a missing directory
     * the operator can't chmod something that doesn't exist —
     * they need to re-upload from the release zip or `mkdir`.
     * The release tarball ships a placeholder for every required
     * folder (`web/demos/.gitkeep`, `web/cache/`, the bundled
     * `web/images/games/*.png` and `web/images/maps/*` files), so
     * a `Missing:` status indicates a partial / broken upload,
     * not a permission problem.
     *
     * Post-review: `sbpp_install_describe_filesystem_check()`
     * pairs each failure shape with the right remediation. This
     * test pins:
     *   - missing → re-upload / mkdir hint (no chmod mention)
     *   - not-writable → chmod 0775 hint (no re-upload mention)
     *   - exists + writable → bare 'Writable' (no hint)
     */
    public function testFilesystemCheckEmitsDistinctRemediations(): void
    {
        $this->loadInstallHelpers();

        $missing = sbpp_install_describe_filesystem_check(
            '/some/panel/demos', false, false
        );
        $this->assertStringContainsString('Missing: /some/panel/demos', $missing);
        $this->assertStringContainsStringIgnoringCase('re-upload', $missing);
        $this->assertStringNotContainsStringIgnoringCase('chmod', $missing,
            'M2 regression: the missing-folder hint must NOT mention chmod — ' .
            'the operator cannot chmod a directory that does not exist. Pre-review ' .
            'both branches glued the same chmod hint on; the fix splits the two ' .
            'cases so each gets the actionable remediation.');

        $notWritable = sbpp_install_describe_filesystem_check(
            '/some/panel/demos', true, false
        );
        $this->assertStringContainsString('Not writable: /some/panel/demos', $notWritable);
        $this->assertStringContainsStringIgnoringCase('chmod', $notWritable);
        $this->assertStringNotContainsStringIgnoringCase('re-upload', $notWritable,
            'The not-writable branch must NOT suggest re-uploading; the directory ' .
            'is on disk, the operator just needs to fix permissions.');

        $ok = sbpp_install_describe_filesystem_check(
            '/some/panel/demos', true, true
        );
        $this->assertSame('Writable', $ok,
            'The OK branch must emit the bare "Writable" string with no hint — ' .
            'the row already shows a green check, no remediation needed.');
    }
}
