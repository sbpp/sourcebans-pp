<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1410: `web/pages/admin.edit.comms.php` ran the permission check
 * BEFORE the not-found guard, so a non-Owner admin who hit the page
 * with a `?id=` pointing at a non-existent cid:
 *
 *   1. Saw the wrong toast — the misleading "You don't have access to
 *      this!" body instead of the correct "There was an error getting
 *      details. Maybe the block has been deleted?" body. The user was
 *      then confused about WHAT went wrong (perm vs deleted-block),
 *      and the perm-flavored body also worked as a side-channel for
 *      "does this cid exist?" enumeration (the perm check fires for
 *      both "you don't own a real block" AND "the block doesn't
 *      exist", so an unauthorised user can't tell the two apart).
 *   2. Triggered a PHP 8.x `E_WARNING: Trying to access array offset
 *      on value of type bool` on every such request. The perm check
 *      reads `$res['aid']` / `$res['gid']`; PHP 8.x evaluates
 *      `false['aid']` to `null` but emits the warning. PHP 9 will
 *      turn the warning into a `TypeError` — the bug shape is on a
 *      paths-to-fatal trajectory.
 *
 * Root cause: the original code ran the perm check at the top of the
 * file (after the SELECT) but the `if (!$res)` guard was 130 lines
 * further down, AFTER the POST-handling block. The fix moves the
 * not-found guard to immediately after the SELECT, BEFORE the perm
 * check, so `$res` is known-truthy by the time the perm check runs.
 *
 * # Why static-shape tests (not process-isolated `require`)
 *
 * The bug condition is "perm check runs before not-found guard on a
 * bad-cid request". All three runtime paths that exercise the bug
 * (bad cid + admin / bad cid + non-Owner / good cid + non-Owner)
 * unconditionally call `PageDie()` (`exit;` after rendering the
 * footer). PHPUnit's `RunInSeparateProcess` mode relies on the test
 * method returning normally so the subprocess can write its result
 * back; `exit` mid-test breaks the serializer and PHPUnit reports
 * the test as errored. Sister `ToastEmitRegressionTest.php`
 * documents the same constraint (lines 425-432) and reaches for
 * static-grep tests for exactly the same reason.
 *
 * The static-shape pins below directly catch the bug:
 *
 *   - {@see testNotFoundGuardRunsBeforePermissionCheck}      — the
 *     load-bearing assertion. If a future refactor moves the
 *     `if (!$res)` block back down below the perm check, this test
 *     fails before the page does in production.
 *   - {@see testNotFoundBranchEmitsNotFoundToastBody}         — pins
 *     the user-visible toast body the not-found branch surfaces.
 *   - {@see testPermissionDeniedBranchEmitsNoAccessToastBody} — pins
 *     the user-visible toast body the perm-denied branch surfaces.
 *     This is the regression guard for "the fix mustn't accidentally
 *     also weaken the perm check"; a future cleanup that drops the
 *     perm check entirely would fail here.
 *   - {@see testNotFoundGuardCallsPageDieAfterToast}          — pins
 *     that the not-found branch terminates via `PageDie()` after the
 *     toast emit, so the buggy `$res['aid']` read in the perm check
 *     (and the inline-script string-builds further down) never run
 *     on the bad-cid path.
 *
 * The runtime side is covered by the existing
 * `web/tests/e2e/specs/flows/admin-edit-comms-toast.spec.ts` (E2E
 * suite, which doesn't have PHPUnit's serializer constraint) — that
 * spec drives the page end-to-end and asserts the visible toast
 * paints. This static-shape gate is the fast-feedback complement.
 *
 * The bug surfaced during the #1403 toast-lift adversarial review.
 */
final class AdminEditCommsCheckOrderTest extends TestCase
{
    /** Path resolved at runtime — `ROOT` is defined by the test bootstrap. */
    private function pageContents(): string
    {
        $contents = (string) @file_get_contents(ROOT . 'pages/admin.edit.comms.php');
        $this->assertNotEmpty(
            $contents,
            'admin.edit.comms.php must exist and be readable; #1410 regression guard is meaningless otherwise.',
        );
        return $contents;
    }

    /**
     * The load-bearing assertion. The `if (!$res)` not-found guard
     * MUST come before the Owner|EditAllBans permission check in
     * `admin.edit.comms.php`. Reversing the order is the bug
     * (#1410): `$res['aid']` / `$res['gid']` reads in the perm
     * check evaluate to `null` on a falsy `$res`, the user gets
     * the misleading "You don't have access" toast, AND PHP 8.x
     * emits an `E_WARNING`.
     */
    public function testNotFoundGuardRunsBeforePermissionCheck(): void
    {
        $contents = $this->pageContents();

        // Anchor on substrings unique to each guard. The not-found
        // anchor is the literal `if (!$res) {` opener — the only
        // occurrence in the file. The perm-check anchor is the
        // `HasAccess(WebPermission::mask(WebPermission::Owner, …))`
        // call, also unique (no other site combines Owner with
        // EditAllBans).
        $notFoundPos = strpos($contents, 'if (!$res) {');
        $permCheckPos = strpos(
            $contents,
            'HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::EditAllBans))',
        );

        $this->assertNotFalse(
            $notFoundPos,
            'Expected `if (!$res) {` not-found guard in admin.edit.comms.php — was it renamed or removed?',
        );
        $this->assertNotFalse(
            $permCheckPos,
            'Expected the `HasAccess(WebPermission::mask(WebPermission::Owner, WebPermission::EditAllBans))` perm check in admin.edit.comms.php — was it renamed or removed?',
        );

        $this->assertLessThan(
            $permCheckPos,
            $notFoundPos,
            "#1410: The `if (!\$res)` not-found guard MUST come BEFORE the "
                . "Owner|EditAllBans permission check in admin.edit.comms.php. "
                . "Reversing the order is the bug: on a request for a non-existent "
                . "cid `\$res` is false, the perm check then reads `\$res['aid']` / "
                . "`\$res['gid']` which PHP 8.x evaluates to null while emitting "
                . "E_WARNING (PHP 9 will fatal), and the user sees the misleading "
                . "\"You don't have access to this!\" toast instead of the correct "
                . "\"There was an error getting details. Maybe the block has been "
                . "deleted?\" toast.",
        );
    }

    /**
     * Pin the user-visible toast body the not-found branch surfaces.
     * A future refactor that "tidies up" the message (e.g. drops the
     * "Maybe the block has been deleted?" hint, or genericises the
     * body to "Not found") would regress the UX without firing any
     * of the structural gates. The body is what the user reads to
     * understand what just happened.
     */
    public function testNotFoundBranchEmitsNotFoundToastBody(): void
    {
        $contents = $this->pageContents();

        $this->assertStringContainsString(
            'There was an error getting details. Maybe the block has been deleted?',
            $contents,
            '#1410: The not-found branch must surface a body explaining the most '
                . 'likely cause (cid was deleted / stale link). Dropping the second '
                . 'sentence loses the actionable hint; renaming the toast loses the '
                . 'continuity with the corresponding branch on sister '
                . '`admin.edit.ban.php` which uses the same phrasing.',
        );
    }

    /**
     * Pin the user-visible toast body the perm-denied branch surfaces.
     * This is the regression guard for "the fix mustn't accidentally
     * also weaken the perm check" (the task description called this
     * out explicitly). A future PR that removes the perm check
     * entirely — or genericises the body to share with the not-found
     * branch — would silently regress security by surfacing the
     * benign "block has been deleted" message even when the user is
     * genuinely unauthorized to edit an existing block.
     */
    public function testPermissionDeniedBranchEmitsNoAccessToastBody(): void
    {
        $contents = $this->pageContents();

        $this->assertStringContainsString(
            "You don't have access to this!",
            $contents,
            '#1410: The perm-denied branch must still emit the "no access" toast. '
                . 'Removing it would silently surface the benign "block has been '
                . 'deleted" message to a user who is genuinely unauthorized to edit '
                . 'an existing block — security regression.',
        );

        // Defensive: the perm check itself must still be present.
        // String-matching the full ternary is brittle, so we just
        // assert the `HasAccess(WebPermission::EditOwnBans)` /
        // `HasAccess(WebPermission::EditGroupBans)` arms survive as
        // the per-aid / per-gid scope gates. Drop both arms and the
        // perm check collapses to "owner-only".
        $this->assertStringContainsString(
            'HasAccess(WebPermission::EditOwnBans)',
            $contents,
            '#1410: The perm check\'s EditOwnBans arm must survive — without '
                . 'it a non-Owner admin can\'t edit their own blocks even when '
                . 'they have the per-row flag.',
        );
        $this->assertStringContainsString(
            'HasAccess(WebPermission::EditGroupBans)',
            $contents,
            '#1410: The perm check\'s EditGroupBans arm must survive — without '
                . 'it a non-Owner admin can\'t edit their group\'s blocks even '
                . 'when they have the per-row flag.',
        );
    }

    /**
     * Pin that the not-found branch terminates via `PageDie()` after
     * the `Toast::emit` call. Without the terminator the perm check
     * (which reads `$res['aid']` / `$res['gid']`) AND the Renderer +
     * inline `<script>` (which read `$res['name']` / `$res['authid']` /
     * `$res['length']` / `$res['type']` / `$res['reason']`) all run
     * against a falsy `$res`, emitting one PHP `E_WARNING` per
     * dereference — and the warnings land literally inside the
     * `<script>` body via the `selectLengthTypeReason('<?=...?>', ...)`
     * short-echo block, producing a JavaScript "Invalid or unexpected
     * token" parse error that kills the page-tail hydration. The
     * `PageDie()` is what stops the cascade.
     */
    public function testNotFoundGuardCallsPageDieAfterToast(): void
    {
        $contents = $this->pageContents();

        // Slice the file from the `if (!$res) {` opener to the next
        // closing `}` at column 0 (the canonical "end of guard block"
        // shape in this file). The slice must contain BOTH the
        // Toast::emit call AND a PageDie() call.
        $start = strpos($contents, 'if (!$res) {');
        $this->assertNotFalse($start, 'Expected `if (!$res) {` block opener.');

        // Find the matching `}` — the file uses one-line `}` at
        // column 0 to close each guard block; grab the first such
        // occurrence after the opener.
        $end = strpos($contents, "\n}", $start);
        $this->assertNotFalse($end, 'Expected matching `}` for the not-found guard.');

        $slice = substr($contents, $start, $end - $start);

        $this->assertStringContainsString(
            'Toast::emit(',
            $slice,
            '#1410: The not-found branch must emit a toast (the user feedback).',
        );
        $this->assertStringContainsString(
            'PageDie()',
            $slice,
            '#1410: The not-found branch MUST terminate via `PageDie()` after '
                . 'the `Toast::emit` call. Without termination the perm check and '
                . 'the page-tail script run against `$res === false`, each '
                . 'dereference emits a PHP warning, and the warnings land literally '
                . 'inside the inline `<script>` body producing a JavaScript parse '
                . 'error. PageDie cuts the render before any of that runs.',
        );

        // Ordering: the toast must emit BEFORE the PageDie (otherwise
        // PageDie's `exit;` fires before `echo` and the user sees
        // nothing).
        $toastPos = strpos($slice, 'Toast::emit(');
        $diePos = strpos($slice, 'PageDie()');
        $this->assertNotFalse($toastPos);
        $this->assertNotFalse($diePos);
        $this->assertLessThan(
            $diePos,
            $toastPos,
            '#1410: `Toast::emit(...)` must run BEFORE `PageDie()` so the toast '
                . 'echoes onto the response before the chrome footer flushes + '
                . '`exit;` terminates the request.',
        );
    }
}
