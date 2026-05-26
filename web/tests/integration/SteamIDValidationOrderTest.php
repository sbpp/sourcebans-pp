<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Issue #1420 follow-up #2: page-handler form-POST surfaces that
 * called `SteamID::toSteam2()` BEFORE validating the raw Steam ID
 * shape via `SteamID::isValidID()`.
 *
 * The bug class is the same as the JSON handler side (#1420 proper),
 * but the failure mode is different: where the JSON handlers turned
 * the converter's exception into a 500-coded `{error: "unknown",
 * message: "Invalid SteamID input!"}` envelope, the page handlers
 * surfaced the exception as an uncaught PHP error, the panel chrome
 * rendered a stack trace (display_errors=On) or a blank white page
 * (display_errors=Off), the inline per-field error path NEVER ran,
 * and the operator was stranded without any actionable feedback.
 *
 * The fix flips the order to "validate raw shape → only convert on
 * pass". The shape gate is `SteamID::isValidID()`, which after
 * follow-up #1 is strictly anchored against the shared `ID_PATTERNS`
 * table. When the shape gate fails:
 *
 *   - `admin.edit.ban.php` pushes the error into `$validationErrors`
 *     ('steam' key) and re-renders the form with the operator's raw
 *     input preserved (Option B per AGENTS.md "Page-handler form-POST
 *     surfaces" — picks up the existing pattern for empty / duplicate
 *     SteamID).
 *   - `admin.edit.comms.php` pushes the error into `$errorFields[]`
 *     as the legacy ['steam.msg', '...'] tuple and re-renders (Option
 *     B; matches the established pattern for missing-nickname / empty
 *     SteamID).
 *   - `admin.edit.admindetails.php` pushes the error into
 *     `$validationErrors['steam']` and re-renders (Option B; matches
 *     the established pattern for duplicate-name / empty-Steam ID).
 *   - `page.submit.php` doesn't call `toSteam2()` at all — it only
 *     validates via `isValidID()`. The library tightening in
 *     follow-up #1 closes the bypass on this surface; the
 *     paired template change adds `pattern="…"` so the browser
 *     blocks submission pre-flight on a typo'd Steam ID.
 *
 * # Why static-shape tests (not process-isolated runtime tests)
 *
 * Pattern lifted from the sister `AdminEditCommsCheckOrderTest`
 * (#1410) — which carries the same reasoning at length. Tl;dr:
 *
 * All four page handlers run inside the panel's chrome-render
 * pipeline: validation → either re-render the form (calls
 * `Renderer::render` → Smarty → `echo`) or run the write path →
 * `PageDie()`. PHPUnit's `runInSeparateProcess` mode relies on the
 * test method returning normally so the child process can serialise
 * its result back; `exit;` mid-test breaks the serializer and PHPUnit
 * reports the test as errored regardless of the actual assertion
 * outcome.
 *
 * The static-shape pins below catch the bug directly: each test
 * asserts that the `SteamID::isValidID(...)` call appears BEFORE the
 * `SteamID::toSteam2(...)` call in the file's text. Reverse the order
 * and the test fails before the page does in production.
 *
 * The runtime side of the contract is exercised end-to-end by the
 * existing `comms-add SteamID validation` Playwright spec
 * (`web/tests/e2e/specs/flows/comms-add-validation.spec.ts`) — the
 * strict-regex contract is shared across the JSON + form surfaces, so
 * the existing E2E coverage exercises the same library boundary the
 * page handlers now route through.
 */
final class SteamIDValidationOrderTest extends TestCase
{
    /**
     * @param string $relative Path relative to `web/`. Resolves against the
     *                         test bootstrap's `ROOT` constant.
     */
    private function fileContents(string $relative): string
    {
        $contents = (string) @file_get_contents(ROOT . $relative);
        $this->assertNotEmpty(
            $contents,
            "Expected `web/{$relative}` to exist and be readable; the regression guard is meaningless otherwise.",
        );
        return $contents;
    }

    /**
     * The load-bearing assertion for `admin.edit.ban.php`. Pre-fix
     * this handler called `\SteamID\SteamID::toSteam2(...)` as the
     * FIRST statement inside `if (isset($_POST['name']))`; the
     * converter raised `Exception('Invalid SteamID input!')` on any
     * non-empty input that failed `resolveInputID()`'s shape check,
     * the exception escaped the page handler unhandled, and the user
     * got a 500 page render instead of the inline "Please enter a
     * valid Steam ID or Community ID" message on the form. The fix
     * inverts the order: validate raw shape via `isValidID()` first;
     * convert only on a pass.
     */
    public function testAdminEditBanValidatesBeforeConverting(): void
    {
        $contents = $this->fileContents('pages/admin.edit.ban.php');

        $isValidPos  = strpos($contents, '\\SteamID\\SteamID::isValidID(');
        $toSteam2Pos = strpos($contents, '\\SteamID\\SteamID::toSteam2(');

        $this->assertNotFalse(
            $isValidPos,
            'Expected `\\SteamID\\SteamID::isValidID(` call in admin.edit.ban.php — was the validate-before-convert fix reverted?',
        );
        $this->assertNotFalse(
            $toSteam2Pos,
            'Expected `\\SteamID\\SteamID::toSteam2(` call in admin.edit.ban.php — the conversion is still load-bearing for the DB write path.',
        );

        $this->assertLessThan(
            $toSteam2Pos,
            $isValidPos,
            '#1420 follow-up #2: `SteamID::isValidID()` MUST be called BEFORE '
                . '`SteamID::toSteam2()` in admin.edit.ban.php. Reversing the order is '
                . 'the bug class: `toSteam2()` raises Exception("Invalid SteamID input!") '
                . 'on any input that fails the shape check, the exception escapes the '
                . 'page handler unhandled, and the operator gets a 500 page render '
                . 'instead of the inline per-field "Please enter a valid Steam ID or '
                . 'Community ID" message on the form.',
        );
    }

    /**
     * Same load-bearing assertion for `admin.edit.comms.php`. Pre-fix
     * this handler also called `toSteam2()` as the first statement
     * inside `if (isset($_POST['name']))`.
     */
    public function testAdminEditCommsValidatesBeforeConverting(): void
    {
        $contents = $this->fileContents('pages/admin.edit.comms.php');

        $isValidPos  = strpos($contents, '\\SteamID\\SteamID::isValidID(');
        $toSteam2Pos = strpos($contents, '\\SteamID\\SteamID::toSteam2(');

        $this->assertNotFalse(
            $isValidPos,
            'Expected `\\SteamID\\SteamID::isValidID(` call in admin.edit.comms.php.',
        );
        $this->assertNotFalse(
            $toSteam2Pos,
            'Expected `\\SteamID\\SteamID::toSteam2(` call in admin.edit.comms.php.',
        );

        $this->assertLessThan(
            $toSteam2Pos,
            $isValidPos,
            '#1420 follow-up #2: `SteamID::isValidID()` MUST be called BEFORE '
                . '`SteamID::toSteam2()` in admin.edit.comms.php. See the docblock on '
                . '`testAdminEditBanValidatesBeforeConverting` for the bug class.',
        );
    }

    /**
     * Same load-bearing assertion for `admin.edit.admindetails.php`.
     * Pre-fix this handler called `toSteam2()` while building
     * `$resolvedSteam` at the top of the POST branch; the converter's
     * exception escaped through the same uncaught-exception path.
     */
    public function testAdminEditAdminDetailsValidatesBeforeConverting(): void
    {
        $contents = $this->fileContents('pages/admin.edit.admindetails.php');

        $isValidPos  = strpos($contents, '\\SteamID\\SteamID::isValidID(');
        $toSteam2Pos = strpos($contents, '\\SteamID\\SteamID::toSteam2(');

        $this->assertNotFalse(
            $isValidPos,
            'Expected `\\SteamID\\SteamID::isValidID(` call in admin.edit.admindetails.php.',
        );
        $this->assertNotFalse(
            $toSteam2Pos,
            'Expected `\\SteamID\\SteamID::toSteam2(` call in admin.edit.admindetails.php.',
        );

        $this->assertLessThan(
            $toSteam2Pos,
            $isValidPos,
            '#1420 follow-up #2: `SteamID::isValidID()` MUST be called BEFORE '
                . '`SteamID::toSteam2()` in admin.edit.admindetails.php. See the docblock '
                . 'on `testAdminEditBanValidatesBeforeConverting` for the bug class.',
        );
    }

    /**
     * `page.submit.php` is the public form. It NEVER calls
     * `SteamID::toSteam2()` — it just validates via `isValidID()` and
     * stores the raw value in `:prefix_submissions`. This test pins
     * that asymmetry: the file must contain the `isValidID()` gate
     * but must NOT regress to a `toSteam2()` call (a future
     * "normalise the SteamID before storage" refactor would
     * reintroduce the bug class because `toSteam2()` raises on
     * invalid shape).
     */
    public function testPageSubmitUsesIsValidIdNotToSteam2(): void
    {
        $contents = $this->fileContents('pages/page.submit.php');

        $this->assertStringContainsString(
            '\\SteamID\\SteamID::isValidID(',
            $contents,
            '#1420 follow-up #2: page.submit.php must validate the Steam ID via '
                . '`SteamID::isValidID()`. Removing the gate reopens the validation '
                . 'bypass.',
        );

        $this->assertStringNotContainsString(
            '\\SteamID\\SteamID::toSteam2(',
            $contents,
            '#1420 follow-up #2: page.submit.php must NOT call `SteamID::toSteam2()`. '
                . 'The submission flow stores the raw user input in :prefix_submissions '
                . 'verbatim; introducing a normalising conversion here would reintroduce '
                . 'the bug class because `toSteam2()` raises Exception on invalid input '
                . 'and the page handler has no try/catch surface. If normalisation is '
                . 'genuinely needed in the future, wire it through a validate-then-convert '
                . 'ladder like the admin.edit.* handlers do.',
        );
    }

    /**
     * The `STEAM_0:` sentinel that `page.submit.php` used to re-emit
     * for empty Steam IDs was dropped in the same commit that added
     * the strict `pattern="…"` attribute to the form input. Leaving
     * the sentinel in place would have failed the pattern check AND
     * blocked submission for the legitimate IP-only path. This test
     * pins both halves of the contract: the page handler must NOT
     * re-emit the sentinel, and the template must NOT carry the dead
     * client-side `STEAM_0:` defense in its `isEmpty()` helper.
     */
    public function testPageSubmitDroppedSteamZeroSentinel(): void
    {
        $contents = $this->fileContents('pages/page.submit.php');

        $this->assertStringNotContainsString(
            '"STEAM_0:" : $SteamID',
            $contents,
            '#1420 follow-up #2: page.submit.php must NOT re-emit the `STEAM_0:` '
                . 'sentinel for empty Steam IDs in the View constructor. The sentinel '
                . 'was pre-fill UX that broke the moment the template grew a strict '
                . '`pattern="STEAM_[01]:[01]:\\d+|…"` attribute — the partial sentinel '
                . 'fails the pattern check and the browser blocks submission for legit '
                . 'IP-only flows. The `placeholder="STEAM_0:0:12345"` on the template '
                . 'is the modern shape for "show the operator what we expect".',
        );

        $this->assertStringNotContainsString(
            '$SteamID == "STEAM_0:"',
            $contents,
            '#1420 follow-up #2: page.submit.php must NOT defensively collapse the '
                . '`STEAM_0:` sentinel back to "" in the write path. With the sentinel '
                . 'removed at the View construction, the input handler ensures '
                . '`$SteamID === ""` by the time the write runs.',
        );

        $tpl = $this->fileContents('themes/default/page_submitban.tpl');
        $this->assertStringNotContainsString(
            "v === 'STEAM_0:'",
            $tpl,
            '#1420 follow-up #2: page_submitban.tpl must NOT carry the dead '
                . '`STEAM_0:` empty-sentinel defense in its `isEmpty()` helper. The '
                . 'sentinel was dropped from the page handler in the same commit; the '
                . 'defense is unreachable code now and the comment misleads future '
                . 'maintainers about the contract.',
        );
    }

    /**
     * Pin the strict `pattern="…"` attribute on each of the four
     * Steam ID inputs across the page-handler form templates. The
     * pattern mirrors the server-side `SteamID::isValidID()`
     * allowlist (Steam2 / bracketed Steam3 / 17-digit Steam64) so the
     * browser blocks submission pre-flight on a typo — the operator
     * doesn't pay the round-trip.
     *
     * Anchored against the literal regex string so a future
     * loosening that drops the `[01]` strict character class or
     * widens the quantifier from `\d+` to `\d*` fails the gate.
     */
    public function testFormTemplatesCarryStrictSteamPattern(): void
    {
        $expected = 'pattern="STEAM_[01]:[01]:\\d+|\\[U:1:\\d+\\]|\\d{17}"';

        $templates = [
            'themes/default/page_admin_edit_ban.tpl',
            'themes/default/page_admin_edit_comms.tpl',
            'themes/default/page_admin_edit_admins_details.tpl',
            'themes/default/page_submitban.tpl',
        ];

        foreach ($templates as $relative) {
            $contents = $this->fileContents($relative);
            $this->assertStringContainsString(
                $expected,
                $contents,
                "#1420 follow-up #2: {$relative} must carry the strict Steam ID "
                    . "pattern: `{$expected}`. The pattern mirrors the server-side "
                    . "`SteamID::isValidID()` allowlist; loosening it (dropping `[01]` "
                    . "for `[0-9]`, widening `\\d+` to `\\d*`, removing the anchors) "
                    . "would reintroduce the substring-bypass class of #1420 on the "
                    . "client side and shift the burden entirely to the server-side "
                    . "library.",
            );
        }
    }

    /**
     * Pin the `title="..."` companion attribute on each Steam ID
     * input. `title` is what the browser surfaces on the
     * pattern-mismatch popover; without it the popover reads
     * "Please match the requested format." which gives the operator
     * no actionable feedback — they have to read the page's help
     * line to figure out what's wrong.
     */
    public function testFormTemplatesCarrySteamPatternTitle(): void
    {
        $expectedTitle = 'title="Enter a Steam ID (STEAM_0:1:23498765), Steam3 ID ([U:1:23498765]), or 17-digit SteamID64."';

        $templates = [
            'themes/default/page_admin_edit_ban.tpl',
            'themes/default/page_admin_edit_comms.tpl',
            'themes/default/page_admin_edit_admins_details.tpl',
            'themes/default/page_submitban.tpl',
        ];

        foreach ($templates as $relative) {
            $contents = $this->fileContents($relative);
            $this->assertStringContainsString(
                $expectedTitle,
                $contents,
                "#1420 follow-up #2: {$relative} must carry the actionable `title` "
                    . "attribute on the Steam ID input. Without it the browser's "
                    . "pattern-mismatch popover reads `Please match the requested "
                    . "format.` which is useless to the operator.",
            );
        }
    }

    /**
     * Pin that `page_submitban.tpl` does NOT carry `novalidate` on
     * the form. Pre-#1420 the form had `novalidate` which suppressed
     * native validation entirely — the strict `pattern="…"` added in
     * this PR would never fire client-side. Removing `novalidate` is
     * what makes the pattern attribute load-bearing.
     */
    public function testPageSubmitFormDoesNotCarryNovalidate(): void
    {
        $contents = $this->fileContents('themes/default/page_submitban.tpl');

        // Slice the form tag — the `novalidate` attribute is only
        // meaningful when it sits on the `<form>` element itself. A
        // `novalidate` substring elsewhere in the template (in a
        // comment, in a sibling helper) is harmless.
        $formOpen = strpos($contents, '<form');
        $formClose = $formOpen !== false ? strpos($contents, '>', $formOpen) : false;

        $this->assertNotFalse($formOpen, 'Expected `<form` opener in page_submitban.tpl.');
        $this->assertNotFalse($formClose, 'Expected matching `>` for the `<form` opener.');

        $formTag = substr($contents, $formOpen, $formClose - $formOpen + 1);

        $this->assertStringNotContainsString(
            'novalidate',
            $formTag,
            '#1420 follow-up #2: the `<form>` tag in page_submitban.tpl must NOT '
                . 'carry `novalidate`. `novalidate` suppresses native validation, '
                . 'including the strict `pattern="…"` attribute on the Steam ID '
                . 'input — so adding the pattern without removing `novalidate` would '
                . 'be silently dead client-side. The cross-field "one of Steam ID OR '
                . 'IP" guard that native HTML cannot express stays in the page-tail '
                . 'JS, which runs AFTER native validation passes.',
        );
    }

    /**
     * `admin.bans.php`'s `importBans` POST handler reads the
     * operator-uploaded `banned_user.cfg` file line by line and
     * called `SteamID::toSteam2($line[2])` on the third whitespace
     * token of every `banid …` line. Pre-fix a single malformed
     * line (typo'd Steam ID, legacy format the library no longer
     * accepts, garbage trailing tokens) raised
     * `Exception('Invalid SteamID input!')` from `resolveInputID()`;
     * the import aborted mid-file with a 500 page render and the
     * operator had no signal as to which line broke or how many of
     * the preceding inserts committed (no transaction wrapper).
     *
     * The library tightening in follow-up #1 made the throw stricter,
     * which made the abort strictly MORE frequent for any cfg
     * carrying legacy/typo'd rows. This test pins the
     * validate-before-convert order so a future refactor doesn't
     * silently regress the import to "fails-loudly on first bad line".
     */
    public function testAdminBansImportValidatesBeforeConverting(): void
    {
        $contents = $this->fileContents('pages/admin.bans.php');

        $importStart = strpos($contents, "importBans");
        $this->assertNotFalse(
            $importStart,
            'Expected the `importBans` POST branch in admin.bans.php — was the import surface deleted?',
        );

        // Slice the import branch out of the full file (the
        // surrounding `admin.bans.php` carries many other
        // `SteamID::*` references in sibling sections that we
        // don\'t want to disturb).
        $importEnd = strpos($contents, '}', $importStart);
        // Walk up to the end of the importBans branch — the
        // enclosing `if (isset($_POST[action]) ...)` block. The
        // matching `}` is the FIRST top-level brace closer after the
        // helper's last `echo "<script>...";` — slice generously
        // and let the strpos checks below assert ordering inside.
        $importBlock = substr($contents, $importStart, 5000);

        $isValidPos  = strpos($importBlock, '\\SteamID\\SteamID::isValidID(');
        $toSteam2Pos = strpos($importBlock, '\\SteamID\\SteamID::toSteam2(');

        $this->assertNotFalse(
            $isValidPos,
            '#1420 follow-up #2: importBans MUST call `\\SteamID\\SteamID::isValidID(` to gate the `banid` branch before converting. Was the validate-before-convert fix reverted?',
        );
        $this->assertNotFalse(
            $toSteam2Pos,
            'Expected `\\SteamID\\SteamID::toSteam2(` in the importBans branch — the conversion is still load-bearing for the DB write path.',
        );

        $this->assertLessThan(
            $toSteam2Pos,
            $isValidPos,
            '#1420 follow-up #2: in importBans, `SteamID::isValidID()` MUST be called '
                . 'BEFORE `SteamID::toSteam2()`. Reversing the order is the bug class: '
                . 'a single typo\'d Steam ID in the operator\'s banned_user.cfg aborts '
                . 'the entire import with a 500 page render (the converter raises '
                . 'Exception on invalid input, the exception escapes the page handler, '
                . 'and the foreach inserts are not wrapped in a transaction so the '
                . 'operator has no idea how many of the preceding lines committed).',
        );
    }

    /**
     * The per-handler `preg_match(...)` defense-in-depth pinned in
     * the original #1420 PR was kept. Same load-bearing reasoning:
     * the library tightening in follow-up #1 is the primary gate,
     * the per-handler regex is the second line of defense that
     * catches any regression in the library (or in any future
     * caller that bypasses the library entirely). Both layers must
     * agree on the allowlist; this test pins the "both still exist"
     * half of the contract.
     *
     * Post-#1423 follow-up #4 the regex is sourced from
     * `SteamID::HANDLER_STRICT_REGEX` (a class constant on the
     * library) instead of three hand-rolled copies — so the two
     * layers cannot drift on the modifier set. Pre-#1423 follow-up
     * #4 the hand-rolled handler regexes silently missed the `D`
     * modifier; the `STEAM_0:0:1\n` newline-bypass slipped past
     * the handler gate AND THEN failed `SteamID::toSteam2()`
     * (which DOES carry the modifier post-#1423 follow-up #1) →
     * generic 500 envelope. The single-source-of-truth shape pinned
     * here closes that bug class permanently.
     */
    public function testJsonHandlersUseSingleSourceOfTruthRegex(): void
    {
        $handlers = [
            'api/handlers/admins.php',
            'api/handlers/bans.php',
            'api/handlers/comms.php',
        ];

        foreach ($handlers as $relative) {
            $contents = $this->fileContents($relative);
            $this->assertStringContainsString(
                'SteamID::HANDLER_STRICT_REGEX',
                $contents,
                "#1423 follow-up #4: {$relative} MUST source its strict-regex gate "
                    . "from `SteamID::HANDLER_STRICT_REGEX` (the library's class constant), "
                    . "NOT a hand-rolled copy of the regex literal. Single-source-of-truth "
                    . "is the contract that prevents the two layers from drifting on "
                    . "modifier-set tweaks (the pre-#1423 hand-rolled regexes missed the "
                    . "`D` modifier, causing the `STEAM_0:0:1\\n` newline-bypass to slip "
                    . "past the handler gate AND THEN 500-envelope on `toSteam2()`).",
            );
            $this->assertStringContainsString(
                'preg_match(SteamID::HANDLER_STRICT_REGEX',
                $contents,
                "#1423 follow-up #4: {$relative} must invoke the library constant via "
                    . "`preg_match(SteamID::HANDLER_STRICT_REGEX, ...)`. The class constant "
                    . "is the contract; a sibling shape (`SteamID::HANDLER_STRICT_REGEX . '…'` "
                    . "concatenation, or a copy into a local variable) defeats the purpose.",
            );
        }
    }
}
