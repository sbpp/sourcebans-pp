<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use Sbpp\Tests\ApiTestCase;
use Sbpp\Tests\Fixture;

/**
 * Runtime permission gate for the "Full data export" surface,
 * exercising the underlying primitives the entry point + page
 * handler rely on (`CSRF::validate(CSRF::fromRequest())` +
 * `CUserManager::HasAccess(WebPermission::Owner->value)`).
 *
 * Sister of `AdminExportPermissionTest` (static-shape) — the
 * split mirrors `Php82DeprecationsTest`'s "static + runtime"
 * decomposition for the same reason: the static arm pins the
 * call shape ("the source file LITERALLY contains
 * `CheckAdminAccess(ADMIN_OWNER)`") while the runtime arm pins
 * the primitive behaviour ("when called with a non-owner, that
 * primitive actually returns false"). Both halves matter: the
 * static gate catches "someone removed the gate call", the
 * runtime gate catches "someone subtly broke the primitive the
 * gate relies on" (a permission-bitmask refactor, a CSRF token
 * shape change, etc.).
 *
 * Why sibling FILE not sibling CLASS in the same file: PHPUnit's
 * class-from-file discovery picks up exactly one class per
 * `*Test.php` matching the filename. A sibling class in the same
 * file silently fails to enumerate at `--list-tests` time (which
 * is the kind of failure mode that ships green to CI and fails
 * closed only when someone adds a regression test that exercises
 * a class the test suite isn't actually loading).
 *
 * Why NOT a `require pages/admin.export.php` runtime test under
 * `#[RunInSeparateProcess]`: the page handler's gate is
 * `CheckAdminAccess(ADMIN_OWNER)`, which on miss calls
 * `header('Location: …'); exit;`. PHP's `exit` is uncatchable
 * (try/catch can't intercept it — it propagates synchronously
 * through any frames in scope and terminates the runtime), so
 * under `RunInSeparateProcess` the child process dies cleanly
 * BEFORE PHPUnit can serialise a result back to the parent and
 * the test runner reports "Test was run in child process and
 * ended unexpectedly". The primitive tests below test the same
 * gate's underlying behaviour without fighting `exit`'s
 * uncatchable shape; the static gate in the sister file pins
 * "the call is in place"; the Playwright spec
 * (`data-export.spec.ts`) drives the gate end-to-end through a
 * real HTTP request. Three layers — sufficient.
 *
 * The entry point (`web/export.php`) is even less `require`-able
 * under PHPUnit: its own `require_once __DIR__ . '/init.php'`
 * conflicts with `bootstrap.php`'s already-defined constants
 * (`ROOT`, `DB_PREFIX`, `ADMIN_OWNER`, …). Same shape, same
 * resolution — exercise the primitives directly.
 */
final class AdminExportRuntimePermissionTest extends ApiTestCase
{
    /**
     * The streaming entry point's CSRF gate
     * (`CSRF::rejectIfInvalid()` → `CSRF::validate(self::fromRequest())`)
     * must reject a missing token. Without this gate, a hostile
     * cross-origin `<form>` POST against `export.php` could
     * trigger a full panel export against an authenticated
     * owner's session. The owner-only permission gate sits
     * downstream of the CSRF gate (per the static-shape
     * contract `testEntryPointCallsCsrfRejectIfInvalidBeforePermissionGate`),
     * so a missing-token attempt never even reaches the
     * permission check.
     */
    public function testCsrfGatePrimitiveRejectsMissingToken(): void
    {
        \CSRF::init();
        $token = \CSRF::token();
        $this->assertNotSame('', $token, 'CSRF::init must mint a session token.');

        // Don't supply the token via $_POST / $_GET / header.
        $_POST = [];
        $_GET  = [];
        unset($_SERVER['HTTP_X_CSRF_TOKEN']);

        $this->assertFalse(
            \CSRF::validate(\CSRF::fromRequest()),
            'CSRF::validate(fromRequest()) must return false when no token is supplied — the gate the entry point relies on.',
        );
    }

    /**
     * Sister to the above: when the correct token IS supplied,
     * the gate passes. Pins the bidirectional contract — the gate
     * isn't just "always-fails"; it accepts legitimate tokens
     * via either of the two transport mechanisms the panel
     * supports ($_POST field for form submits, X-CSRF-Token
     * header for `sb.api.call` JSON requests).
     */
    public function testCsrfGatePrimitiveAcceptsValidToken(): void
    {
        \CSRF::init();
        $token = \CSRF::token();

        // Form-submit path (what the data-export form's `<form
        // method="post">` POST exercises via the `{csrf_field}`
        // Smarty function in `page_admin_export.tpl`).
        $_POST = [\CSRF::FIELD_NAME => $token];
        unset($_SERVER[\CSRF::HEADER_NAME]);
        $this->assertTrue(
            \CSRF::validate(\CSRF::fromRequest()),
            'CSRF::validate(fromRequest()) must return true when the session-bound token is supplied via $_POST.',
        );

        // Header-supplied path (what `sb.api.call` sets
        // automatically for JSON requests — not exercised by the
        // export form today but the gate accepts both transports
        // by design, and a future JSON-driven export surface
        // would ride this path).
        $_POST = [];
        $_SERVER[\CSRF::HEADER_NAME] = $token;
        $this->assertTrue(
            \CSRF::validate(\CSRF::fromRequest()),
            'CSRF::validate(fromRequest()) must accept the token via the X-CSRF-Token header.',
        );
    }

    /**
     * Permission-primitive sister: the gate the entry point's
     * `if (!$userbank->HasAccess(WebPermission::Owner->value))`
     * block AND the page handler's `CheckAdminAccess(ADMIN_OWNER)`
     * both rely on. Three branches:
     *
     * - Anonymous: fails closed.
     * - Non-owner with every other web flag set EXCEPT Owner:
     *   still fails. ADMIN_OWNER is the EXCLUSIVE gate per the
     *   plan — every other web flag combined must be insufficient.
     *   This is the strongest expression of "owner-only" — if
     *   `(ALL_WEB & ~ADMIN_OWNER)` gets through, every weaker
     *   non-owner mask gets through too.
     * - Owner: passes. (The seeded `admin/admin` fixture carries
     *   ADMIN_OWNER per `Fixture::adminAid()`'s contract.)
     */
    public function testOwnerPermissionPrimitive(): void
    {
        $anon = new \CUserManager(null);
        $this->assertFalse(
            $anon->HasAccess(\WebPermission::Owner->value),
            'Anonymous user (CUserManager(null)) must NOT have Owner — fail-closed default.',
        );

        $nonOwnerMask = ALL_WEB & ~ADMIN_OWNER;
        $aid = $this->createAdminWithFlags($nonOwnerMask);
        $this->loginAs($aid);
        /** @var \CUserManager $nonOwner */
        $nonOwner = $GLOBALS['userbank'];
        $this->assertFalse(
            $nonOwner->HasAccess(\WebPermission::Owner->value),
            'Non-owner admin (ALL_WEB & ~ADMIN_OWNER) must NOT have Owner — the bypass is exclusive to ADMIN_OWNER. If this fails, the gate has been silently weakened and every non-owner with the right combination of other flags can hit the export.',
        );

        $this->loginAsAdmin();
        /** @var \CUserManager $owner */
        $owner = $GLOBALS['userbank'];
        $this->assertTrue(
            $owner->HasAccess(\WebPermission::Owner->value),
            'The seeded admin/admin row must have Owner — every gate downstream relies on this.',
        );
    }

    /**
     * Belt-and-suspenders: the SourceMod char-flag form of
     * `HasAccess` (the codebase's variadic permission shape that
     * also accepts strings like `"z"` for the root char) MUST
     * NOT let a SourceMod-root-without-web-owner caller through.
     * The web `ADMIN_OWNER` flag and the SourceMod `z` char are
     * conceptually orthogonal — a SourceMod root admin who is
     * NOT a web owner must still be blocked.
     */
    public function testSourceModRootCharAloneDoesNotGrantWebOwner(): void
    {
        // SourceMod char-flag bypass is via the `srv_flags`
        // column on `:prefix_admins`, NOT `extraflags`. A row
        // with `srv_flags = 'z'` (sm root) but no web Owner bit
        // must still fail the web-side HasAccess check.
        $pdo = Fixture::rawPdo();
        $stmt = $pdo->prepare(sprintf(
            'INSERT INTO `%s_admins` (user, authid, password, gid, email, validate, extraflags, srv_flags, immunity)
             VALUES (?, ?, ?, -1, ?, NULL, 0, ?, 99)',
            DB_PREFIX,
        ));
        $stmt->execute([
            'export-smroot-no-owner',
            'STEAM_0:0:9000001',
            password_hash('x', PASSWORD_BCRYPT),
            'export-smroot@example.test',
            'z',
        ]);
        $aid = (int) $pdo->lastInsertId();
        $this->loginAs($aid);

        /** @var \CUserManager $smRoot */
        $smRoot = $GLOBALS['userbank'];
        $this->assertFalse(
            $smRoot->HasAccess(\WebPermission::Owner->value),
            'A SourceMod-root admin without web ADMIN_OWNER must NOT pass the web Owner gate — the two flag spaces are orthogonal and the export feature is web-side only.',
        );
    }

    // -----------------------------------------------------------------
    //  Test-only helpers
    // -----------------------------------------------------------------

    /**
     * Insert a non-owner admin row directly via the test PDO so
     * we can exercise an arbitrary `extraflags` mask without
     * going through the `admins.add` JSON handler. The handler
     * would itself reject a non-owner caller from creating a row
     * with a permission mask the caller can't grant — for tests
     * exercising "what does a non-owner see", we need the raw
     * insert path.
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
            'export-flagged-' . $mask,
            'STEAM_0:0:' . (4_000_000 + ($mask & 0xFFFFF)),
            password_hash('x', PASSWORD_BCRYPT),
            'export-flagged@example.test',
            $mask,
        ]);
        return (int) $pdo->lastInsertId();
    }
}
