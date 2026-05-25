<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Util;

/**
 * Helpers for accepting player display names as URL-driven inputs
 * (`?name=…` smart-default pre-fills, deep-links from out-of-band
 * surfaces like the public servers list's right-click context menu).
 *
 * Issue #1440 introduced the first user of `sanitisePrefill()`:
 * `web/scripts/server-context-menu.js`'s "Ban player" / "Block comms"
 * items thread the player's display name through
 * `?p=admin&c=bans&section=add-ban&steam=…&name=…` (and the
 * sibling `?p=admin&c=comms&…&name=…`). Both target pages
 * (`web/pages/admin.bans.php` + `web/pages/admin.comms.php`) consume
 * the URL parameter through this single sanitiser so the contract
 * stays single-source as future deep-link surfaces (e.g., a future
 * "Ban from rcon transcript" affordance) join the family.
 *
 * Sanitation contract:
 *
 *  1. `trim()` leading/trailing whitespace — operators paste names
 *     with surrounding spaces all the time; the visible cell shows
 *     the trimmed form and the pre-fill should match.
 *
 *  2. Strip a curated set of dangerous-display Unicode codepoints.
 *     The full set is in {@see SANITISE_STRIP_REGEX}; the design
 *     intent is "kill spoofing vectors without breaking legitimate
 *     names":
 *
 *     - **C0 + DEL controls** (`U+0000`..`U+001F`, `U+007F`): never
 *       in a legit player name; historically seed log-injection +
 *       response-splitting attacks.
 *     - **C1 controls** (`U+0080`..`U+009F`): includes `U+0085` (NEL)
 *       which some renderers treat as a line break; the rest are
 *       deprecated control codes with no display meaning.
 *     - **`U+00AD` (soft hyphen)**: invisible in most renderings —
 *       lets one player impersonate another by inserting it inside
 *       what looks like the target's name.
 *     - **`U+2028` (LS) / `U+2029` (PS)**: paragraph/line separators
 *       — break the input field's single-line constraint and surface
 *       as multi-line nicknames downstream.
 *     - **`U+200B` (ZWSP)**: zero-width space — pure spoofing vector;
 *       `ad​min` (with a ZWSP between `ad` and `min`) renders as
 *       `admin` but is a different string.
 *     - **`U+202A`..`U+202E` (LRE/RLE/PDF/LRO/RLO)**: bidi format /
 *       override codepoints — `evil\u{202E}xobj` renders as
 *       `evilfboxe`. RLO/LRO are the canonical RTL-spoofing
 *       weapons.
 *     - **`U+2066`..`U+2069` (LRI/RLI/FSI/PDI)**: bidi isolate
 *       codepoints — same spoofing class as the bidi formatters.
 *     - **`U+FEFF` (BOM / ZWNBSP)**: invisible at the start; a
 *       second use that historically bypassed naive byte-comparison
 *       gates.
 *
 *     Intentionally NOT stripped (legitimate Unicode that some
 *     classes of player names DO carry):
 *
 *     - **`U+200C` (ZWNJ) / `U+200D` (ZWJ)**: required for proper
 *       rendering of complex emoji (skin-tone modifiers, ZWJ
 *       sequences like `👨‍👩‍👦`) and certain script-shaping rules
 *       (Persian / Devanagari). Stripping ZWJ would break emoji
 *       names; stripping ZWNJ would break Persian.
 *     - **`U+200E` (LRM) / `U+200F` (RLM)**: bidi marks. Legitimate
 *       in mixed-direction text where the implicit Unicode bidi
 *       algorithm gets the direction wrong. The bidi format and
 *       isolate codepoints (`U+202A..2E`, `U+2066..69`) handle the
 *       weaponised classes; LRM/RLM are far more often legitimate.
 *
 *     The `/u` modifier on the regex literal is load-bearing —
 *     without it the regex engine treats the string as Latin-1
 *     and can split a multi-byte UTF-8 sequence mid-codepoint,
 *     leaving the string malformed for the downstream
 *     `mb_check_encoding` gate (or worse, slipping through it as
 *     well-formed and trip `JSON_THROW_ON_ERROR` on the eventual
 *     audit-log emission).
 *
 *  3. `mb_check_encoding($candidate, 'UTF-8')`: reject the entire
 *     value when the bytes don't decode as UTF-8. A hostile
 *     `?name=%FF%FE…` payload would otherwise carry malformed
 *     bytes into the column AND surface as `false` from the
 *     downstream `json_encode` (audit log writes, toast emissions)
 *     unless every consumer remembers `JSON_INVALID_UTF8_SUBSTITUTE`.
 *     Dropping the value at the gate is the cheaper invariant.
 *
 *  4. `mb_substr($candidate, 0, 128, 'UTF-8')`: cap at 128
 *     codepoints. Matches the schema width of both call sites'
 *     target columns:
 *       - `:prefix_bans.name varchar(128)` (see
 *         `web/install/includes/sql/struc.sql`)
 *       - `:prefix_comms.name varchar(128)` (same file)
 *     The codepoint-aware truncation (4th arg `'UTF-8'`) is what
 *     prevents splitting a multi-byte UTF-8 sequence in half. The
 *     varchar contract on a utf8mb4 column counts characters, not
 *     bytes, so 128 codepoints lands cleanly even when the
 *     name is composed entirely of 4-byte sequences (emoji).
 *
 *  5. Empty result returns `''` — the caller treats that as "no
 *     pre-fill" (drop the `value="…"` to the View DTO's default
 *     empty string).
 *
 * Smarty's global auto-escape (`$theme->setEscapeHtml(true)` in
 * `init.php`) handles the `value="…"` HTML-attribute escape on
 * the consuming side — a name containing `<` / `&` / `"` lands as
 * the entity-escaped form in the input value and never breaks out
 * of the attribute. The server-side strip is defence-in-depth: it
 * closes the loop on attackers supplying invalid-UTF-8 or
 * control-character payloads that the escape layer would happily
 * emit verbatim.
 *
 * This helper is for the UX-side pre-fill ONLY. Real load-bearing
 * validation on submit lives in `Actions.BansAdd` / `Actions.CommsAdd`
 * (see `web/api/handlers/bans.php` / `comms.php`). A future
 * follow-up may extend this helper (or one beside it) with stricter
 * normalisation rules for the audit-log surface — but for the
 * pre-fill case the contract is "scrub dangerous bytes, trust the
 * escape layer for everything else".
 */
final class PlayerName
{
    /**
     * Curated strip set documented in the class docblock. Single
     * source so the integration tests, the page handlers, and any
     * future call site can't drift on which codepoints survive.
     *
     * Categories (in order of appearance in the character class):
     *   - `\x00-\x1F`     C0 controls (incl. NUL, BEL, CR, LF, TAB, ESC)
     *   - `\x7F-\x9F`     DEL + C1 controls
     *   - `\x{00AD}`      soft hyphen
     *   - `\x{200B}`      zero-width space (ZWSP)
     *   - `\x{2028}-\x{2029}` LINE / PARAGRAPH SEPARATOR
     *   - `\x{202A}-\x{202E}` LRE / RLE / PDF / LRO / RLO (bidi format)
     *   - `\x{2066}-\x{2069}` LRI / RLI / FSI / PDI (bidi isolates)
     *   - `\x{FEFF}`      BOM / zero-width no-break space
     *
     * The `D` modifier is NOT applied — there's no `$` anchor here.
     * The `u` modifier IS applied — multi-byte UTF-8 awareness is
     * load-bearing per the class docblock.
     */
    public const SANITISE_STRIP_REGEX
        = '/[\x00-\x1F\x7F-\x9F\x{00AD}\x{200B}\x{2028}-\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u';

    /**
     * Maximum codepoint count for the sanitised value. Matches the
     * `varchar(128)` schema width of both `:prefix_bans.name` and
     * `:prefix_comms.name` so the value round-trips through the
     * eventual INSERT without tripping MariaDB strict mode's
     * `Data too long for column 'name'`. Codepoints, not bytes —
     * a utf8mb4 `varchar(128)` is 128 characters wide.
     */
    public const MAX_CODEPOINTS = 128;

    /**
     * Sanitise a user-supplied display name for use as a smart-default
     * pre-fill `value="…"` attribute on a server-rendered form input.
     *
     * Returns `''` for every "give up" branch (empty input, all-control
     * input that strips to empty, invalid UTF-8). Callers treat empty
     * as "no pre-fill" — the View DTO defaults to `''`, and a Smarty
     * `value=""` is the same shape as omitting the attribute entirely
     * (browsers render an empty input either way).
     *
     * @see self::SANITISE_STRIP_REGEX for the curated strip set.
     * @see self::MAX_CODEPOINTS       for the codepoint cap.
     */
    public static function sanitisePrefill(string $raw): string
    {
        if ($raw === '') {
            return '';
        }
        $candidate = trim($raw);
        $candidate = (string) preg_replace(self::SANITISE_STRIP_REGEX, '', $candidate);
        if ($candidate === '' || !mb_check_encoding($candidate, 'UTF-8')) {
            return '';
        }
        return mb_substr($candidate, 0, self::MAX_CODEPOINTS, 'UTF-8');
    }
}
