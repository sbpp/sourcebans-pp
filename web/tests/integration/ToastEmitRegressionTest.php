<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use Sbpp\Tests\ApiTestCase;
use Sbpp\View\Toast;

/**
 * Issue #1403 (audit follow-up to #1176): six PHP page handlers used
 * to emit `<script>ShowBox(...)</script>` blobs for user feedback.
 * `ShowBox` lived in `web/scripts/sourcebans.js`, deleted at #1123 D1
 * (v2.0.0), so every legacy caller threw
 * `ReferenceError: ShowBox is not defined` in the modern chrome and
 * silently swallowed the message. Several callers also ran upstream
 * of `PageDie()` (which renders the chrome footer + `exit`s) so the
 * template body was suppressed and the user saw a literally blank
 * page on top of the dropped toast.
 *
 * The lift moved the emission through `Sbpp\View\Toast::emit`, which
 * stashes the payload in a `<script type="application/json"
 * class="sbpp-pending-toast">` block the chrome JS reads on
 * `DOMContentLoaded`. This test is the **static regression guard** that
 * no future PR re-introduces a raw `<script>ShowBox(...)</script>` (or
 * `<script>showBox(...)</script>` — the variant casing the issue body
 * also called out) into the in-scope PHP page handlers. It also pins
 * the helper's wire format so a future "we'll switch to a different
 * JSON shape" PR has to update the consumer in the same commit.
 *
 * # Scope: PHP page handlers only
 *
 * The scan walks `web/pages/` (recursively, every `.php` file).
 * Template-side `<script>ShowBox(...)</script>` blobs — including the
 * AGENTS.md-anti-pattern shape
 * `onclick="if (typeof ShowBox === 'function') ShowBox(...)"` —
 * are out of scope here. Those live under `web/themes/<x>/templates/`
 * and the inline page-tail scripts and are sister #1402's surface
 * (the "rewire dead JS click handlers" sweep that owns the
 * template-side cleanup); the AGENTS.md "Legacy 1.4.11 JS
 * handler names" Anti-patterns entry is the documented contract
 * for that surface. Keeping this gate scoped to PHP handlers
 * keeps the test purpose obvious: it guards the new
 * `Sbpp\View\Toast::emit` contract, not every conceivable
 * `ShowBox` regression vector across the panel. A future audit
 * pass that catches the template surface should land its own
 * regression guard tracked against the relevant issue.
 *
 * Three intentional exceptions stay legal:
 *
 *   1. **`page.login.php`** wraps every legacy `ShowBox(...)` call in
 *      `if (typeof ShowBox === 'function') ShowBox(...)` so the JS
 *      shape is inert in the shipped chrome but kept for any
 *      third-party theme that still wires the legacy helper. The
 *      guard is the entire point of the surface; flag it explicitly
 *      below so the regression scan doesn't false-positive on it.
 *   2. **`page.home.php`** builds `$info['popup']` as a string starting
 *      `ShowBox(...)` but the dashboard template never consumes the
 *      field — it's dead PHP-side data. Sister #1404 owns the
 *      cleanup; flagged here so this test agrees with the
 *      out-of-scope contract.
 *   3. **`admin.settings.php`** has a similar guarded fallback shape
 *      (`else if (typeof ShowBox === 'function') ShowBox(...)`) so
 *      its raw `ShowBox(` token also surfaces — explicitly allowed.
 *
 * The scan reads every `web/pages/*.php` file and ALSO walks
 * subdirectories (admin pages live under `web/pages/admin.*.php` which
 * the glob picks up; we double-check core/ separately). It flags any
 * line that contains the literal `<script>ShowBox(` (or its variant
 * spellings) — that's the exact echo shape `_register.php`'s grep
 * audit caught in the issue body, and the only one a code-review eye
 * would reasonably catch as "a v1.x leftover". Comment lines that
 * mention the historical shape stay legal so docblocks documenting
 * "this used to emit <script>ShowBox(...)</script>" don't trip the
 * gate.
 *
 * The companion runtime tests (`web/tests/e2e/specs/flows/*-toast.spec.ts`)
 * cover the consumer side end-to-end: each spec probes the response
 * body for the `class="sbpp-pending-toast"` blob (the wire-layer
 * contract), then asserts the visible toast paints via
 * `[data-testid="toast"]` (the rendered chrome element). This static
 * gate is the "before runtime" half — a raw
 * `<script>ShowBox(...)</script>` reintroduction would still pass the
 * runtime gate (the chrome would silently fail; the spec would
 * timeout looking for the pending-toast blob) but fails this gate
 * loudly with the exact file + line.
 */
final class ToastEmitRegressionTest extends ApiTestCase
{
    /**
     * Files that legitimately mention the literal `<script>ShowBox(`
     * or `ShowBox(` token because their surface is documented as
     * the keep-the-shape-for-third-party-themes contract. Pinned
     * here as the audit trail so the regression scan stays
     * exhaustive AND a future audit pass can re-evaluate each
     * exemption in one place.
     *
     * @return list<string>
     */
    private function legalLegacyShowBoxFiles(): array
    {
        return [
            // Five guarded `if (typeof ShowBox === 'function') ShowBox(...)`
            // calls — inert in the shipped chrome, kept for third-party
            // themes. See file docblock (lines 22-25).
            'pages/page.login.php',
            // Dead PHP-side data field: `$info['popup']` is built but
            // the dashboard template never consumes it. Sister #1404
            // owns the cleanup.
            'pages/page.home.php',
            // Guarded `else if (typeof ShowBox === 'function')
            // ShowBox(...)` fallback for third-party theme forks of
            // the settings page (see admin.settings.php:549-550).
            'pages/admin.settings.php',
        ];
    }

    /**
     * The headline assertion: every PHP page handler under
     * `web/pages/` that's NOT on the legal-legacy list contains no
     * live `<script>ShowBox(` blob.
     *
     * **Scope is PHP page handlers only** (see file docblock for
     * the rationale). Template-side `<script>ShowBox(...)</script>`
     * blobs under `web/themes/<x>/templates/` are out of scope here
     * — that surface is sister #1402's, gated by the AGENTS.md
     * "Legacy 1.4.11 JS handler names" anti-pattern entry.
     *
     * The scan looks at three variants to stay symmetric with the
     * audit grep that produced #1403's site count (35 across 6
     * files):
     *
     *   - `<script>ShowBox(`        — the canonical legacy shape
     *   - `<script>showBox(`        — variant casing also called out
     *                                 in the issue body
     *   - `<script type="text/javascript">ShowBox(` — the v1.x admin.edit.*
     *                                 variant that used the explicit
     *                                 MIME type
     *
     * Comment lines (PHP `//`, `#`, `/* * /` blocks; Smarty `{* *}`)
     * are allowed to mention the historical shape so docblocks
     * documenting "this used to emit `<script>ShowBox(...)</script>`"
     * don't trip the gate. The heuristic is "the line either starts
     * with a comment marker after leading whitespace, OR the
     * `<script>` blob appears inside a `*` PHP-docblock line". This
     * is the same shape `PaletteActionsTest.php` uses for its
     * "legacy hardcoded NAV_ITEMS" scan.
     */
    public function testNoRawShowBoxBlobsRemainInPhpPageHandlers(): void
    {
        $offenders = [];
        $legal = $this->legalLegacyShowBoxFiles();
        $files = $this->collectPagePhpFiles();
        $this->assertNotEmpty(
            $files,
            'collectPagePhpFiles() found no PHP files under web/pages/ — bootstrap drift?',
        );

        foreach ($files as $relPath => $absPath) {
            if (in_array($relPath, $legal, true)) {
                continue;
            }
            $hits = $this->findLiveShowBoxLines($absPath);
            if ($hits !== []) {
                foreach ($hits as $lineNumber => $lineText) {
                    $offenders[] = sprintf('%s:%d: %s', $relPath, $lineNumber, trim($lineText));
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Live <script>ShowBox(...)</script> blobs found — they throw `ReferenceError: ShowBox is not defined` in the modern chrome (the v1.x helper was deleted at #1123 D1) and silently drop the user's confirmation. Lift each one through `Sbpp\\View\\Toast::emit(...)` per the #1403 contract; see web/includes/View/Toast.php and AGENTS.md.\n"
                . "Offenders:\n  " . implode("\n  ", $offenders),
        );
    }

    /**
     * Pin the helper's wire format. A future PR that "simplifies"
     * the JSON payload (drops the `kind` key, renames `redirect` to
     * `next`, swaps to a different `<script>` selector) has to
     * update the chrome JS consumer in the same commit — this test
     * is the static gate that says "if you changed the wire shape
     * here, you also changed the contract".
     *
     * Captures the helper's output via `ob_start` so we're asserting
     * on the actual `echo`'d HTML, not on a hypothetical builder
     * return value (the helper is `: void` by design — its only
     * meaningful output IS the side effect).
     */
    public function testToastEmitWireFormatStaysStable(): void
    {
        ob_start();
        Toast::emit('success', 'Hello', 'World');
        $out = (string) ob_get_clean();

        $this->assertStringContainsString(
            '<script type="application/json" class="sbpp-pending-toast">',
            $out,
            'Wire format changed: the chrome JS consumer (theme.js `flushPendingToasts`) selects on `script[type="application/json"].sbpp-pending-toast`. Drift either side breaks the contract.',
        );
        $this->assertStringNotContainsString(
            'data-testid=',
            $out,
            'Wire-format `<script>` blob must NOT carry a `data-testid` attribute: a multi-emit response would emit several blocks with the same testid and `getByTestId(...)` strict mode would reject the match. E2E specs should anchor on the painted `[data-testid="toast"]` (chrome-rendered) or `[role="status"]` — NOT on the wire-format block.',
        );
        $this->assertStringContainsString('</script>', $out, 'Script element must close.');

        // Extract the JSON payload from the script body and parse it.
        $start = strpos($out, '">') + 2;
        $end = strrpos($out, '</script>');
        $this->assertNotFalse($end, 'Could not find script close tag in helper output.');
        $json = substr($out, $start, $end - $start);
        $data = json_decode($json, true);
        $this->assertIsArray($data, 'Helper emitted invalid JSON: ' . $json);
        $this->assertSame('success', $data['kind'] ?? null);
        $this->assertSame('Hello', $data['title'] ?? null);
        $this->assertSame('World', $data['body'] ?? null);
        $this->assertArrayNotHasKey(
            'redirect',
            $data,
            'Helper must omit `redirect` when caller passed null/empty — keeps the wire payload tight.',
        );
    }

    /**
     * Companion to the above: when the caller provides a redirect,
     * the helper emits it in the payload. The chrome JS picks it up
     * AFTER the toast paints (~1500ms settle delay so the user can
     * read what just happened). Dropping the redirect key entirely
     * would silently regress the GET-fallback paths on
     * `page.banlist.php` / `page.commslist.php` / `admin.edit.comms.php`
     * (the 35 sites the lift converted) because the chrome would
     * paint the toast and then leave the user staring at the
     * 200-response surface — no navigation, no closure.
     */
    public function testToastEmitIncludesRedirectWhenProvided(): void
    {
        ob_start();
        Toast::emit('error', 'Title', 'Body', 'index.php?p=banlist');
        $out = (string) ob_get_clean();

        $start = strpos($out, '">') + 2;
        $end = strrpos($out, '</script>');
        $json = substr($out, $start, $end - $start);
        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertSame('index.php?p=banlist', $data['redirect'] ?? null);
    }

    /**
     * The JSON encoder uses `JSON_HEX_TAG | JSON_HEX_AMP |
     * JSON_HEX_APOS | JSON_HEX_QUOT` to escape every char that could
     * break out of the `<script>` wrapper. Drop a literal
     * `</script>` into the toast body and assert the encoder
     * escapes it as `\u003C` instead of letting the raw substring
     * land in the output — a hostile caller (or an unsanitised
     * future field) must not be able to terminate the script
     * element prematurely.
     *
     * Same defensiveness shape `web/includes/View/PaletteActions.php`
     * uses for the palette-actions blob (#1304).
     */
    public function testToastEmitJsonEscapingDefendsScriptBreakout(): void
    {
        ob_start();
        Toast::emit('error', '</script><script>alert(1)</script>', "&<>'\"");
        $out = (string) ob_get_clean();

        $this->assertStringNotContainsString(
            '</script><script>alert(1)</script>',
            substr($out, 0, (int) strrpos($out, '</script>')),
            'Encoder must not emit a literal `</script>` inside the JSON payload — it would terminate the script element early and inject the rest as live HTML.',
        );
        $this->assertStringContainsString(
            '\u003C',
            $out,
            'Encoder must hex-escape `<` characters via JSON_HEX_TAG so a hostile payload cannot end the script element.',
        );
    }

    /**
     * Pin the fault-tolerance contract: malformed UTF-8 in the body
     * (the historical Latin-1-on-utf8 truncation shape from
     * pre-#1108 / #765 installs whose plugin-side insert path wrote
     * bytes the post-#1108 migration did not retroactively repair)
     * must NOT raise `JsonException`. Pre-fix the encoder was
     * `JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP |
     * JSON_HEX_APOS | JSON_HEX_QUOT`; the GET-fallback unban / delete
     * paths in `page.banlist.php` / `page.commslist.php` interpolate
     * `$row['name']` into the toast body, so a single row carrying a
     * truncated multi-byte sequence (`"Mc" . "\xC3"` shape — a `\xC3`
     * lead byte with no continuation) would throw — and the unban /
     * delete SQL has ALREADY committed by that point, so the audit
     * log shows the action succeeded while the operator sees a 500.
     * Worse failure mode than the pre-#1403 silent ShowBox throw.
     *
     * With `JSON_INVALID_UTF8_SUBSTITUTE` the offending bytes
     * substitute to U+FFFD (the Unicode REPLACEMENT CHARACTER, JSON-
     * encoded as `\uFFFD`) and the toast paints. Well-formed
     * payloads are unaffected; the substitute fires only on the
     * genuinely broken path.
     *
     * Two probes:
     *   1. The helper does NOT throw on a malformed-UTF-8 body.
     *   2. The encoded output carries the `\uFFFD` substitution
     *      marker (so a downstream JSON parser sees a valid string,
     *      not the raw bytes).
     */
    public function testToastEmitSubstitutesMalformedUtf8InsteadOfThrowing(): void
    {
        $malformed = "Mc\xC3broken\xFFname"; // truncated UTF-8 lead bytes
        ob_start();
        try {
            Toast::emit('error', 'Unban failed', $malformed);
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->fail(
                'Toast::emit raised an exception on malformed UTF-8: '
                . get_class($e) . ': ' . $e->getMessage()
                . "\nThis regression would 500 the GET-fallback unban / delete paths on `page.banlist.php` / `page.commslist.php` when interpolating a `:prefix_bans.name` row carrying the historical Latin-1-on-utf8 truncation shape (#1108 / #765). Add `JSON_INVALID_UTF8_SUBSTITUTE` to the encoder flags.",
            );
        }
        $out = (string) ob_get_clean();

        // PHP's `json_encode` emits the `\uFFFD` escape in lowercase
        // (`\ufffd`); both spellings are valid JSON, the test asserts
        // either case-form to stay PHP-version-stable.
        $this->assertMatchesRegularExpression(
            '/\\\\u(?:FFFD|fffd)/',
            $out,
            'Encoder must substitute malformed UTF-8 to U+FFFD via JSON_INVALID_UTF8_SUBSTITUTE — the body must be a valid JSON string downstream parsers can decode.',
        );

        // Belt-and-braces: the encoded JSON parses cleanly.
        $start = strpos($out, '">') + 2;
        $end = strrpos($out, '</script>');
        $this->assertNotFalse($end);
        $json = substr($out, $start, $end - $start);
        $data = json_decode($json, true);
        $this->assertIsArray($data, "Encoded JSON does not parse: $json");
        $this->assertSame('error', $data['kind'] ?? null);
        $this->assertSame('Unban failed', $data['title'] ?? null);
        $this->assertIsString($data['body'] ?? null);
        $this->assertStringContainsString(
            "\u{FFFD}",
            (string) $data['body'],
            'Decoded body must carry the U+FFFD replacement character substituted for the malformed UTF-8 sequence.',
        );
    }

    /**
     * Pin the redirect-coalescing contract: when several emits in
     * the same response carry a `redirect`, the FIRST one wins.
     * This is the consumer-side contract `theme.js`'s
     * `flushPendingToasts` enforces (`if (redirectTo === null
     * && typeof data.redirect === 'string' && data.redirect !==
     * '') redirectTo = data.redirect;`). Documenting it on the
     * encoder side too keeps the contract single-source.
     *
     * Why FIRST not LAST: a single request never emits more than
     * one redirect in practice (the GET fallback paths bounce
     * back to the same list page regardless of the success/error
     * branch — so there's no actual collision), but FIRST is the
     * safer default if a future caller emits a redirect with a
     * sibling toast first. Reordering the emits is also less
     * likely to be a regression than swapping a "the LAST emit
     * is what runs" assumption.
     */
    public function testToastEmitRedirectFirstWinsContract(): void
    {
        ob_start();
        Toast::emit('info', 'First', 'msg', 'index.php?p=first');
        Toast::emit('error', 'Second', 'msg', 'index.php?p=second');
        Toast::emit('warn', 'Third', 'msg'); // no redirect
        $out = (string) ob_get_clean();

        // Parse every emitted payload and assert the two redirect-
        // carrying ones BOTH appear (so the chrome can iterate them
        // and pick its own winner — the encoder is not allowed to
        // pre-collapse the queue).
        preg_match_all(
            '#<script[^>]*class="sbpp-pending-toast"[^>]*>(.*?)</script>#s',
            $out,
            $matches,
        );
        $payloads = array_map(
            fn (string $json): array => (array) json_decode($json, true),
            $matches[1],
        );
        $this->assertCount(3, $payloads, 'Three Toast::emit calls should emit three wire blocks.');

        $redirects = array_values(array_filter(array_map(
            fn (array $p): ?string => isset($p['redirect']) ? (string) $p['redirect'] : null,
            $payloads,
        )));
        $this->assertSame(
            ['index.php?p=first', 'index.php?p=second'],
            $redirects,
            'Encoder must emit redirects in call order; the chrome consumer picks FIRST wins. Pre-collapsing here would change which URL the chrome navigates to.',
        );
    }

    /**
     * Pin the call-site contract: every page handler the #1403 audit
     * called out must end up containing at least one
     * `\Sbpp\View\Toast::emit(` invocation. This is the structural
     * complement to `testNoRawShowBoxBlobsRemainInPageHandlers()` —
     * removing a `<script>ShowBox(...)</script>` blob without
     * replacing it with a `Toast::emit(...)` call would still pass
     * that scan (and the user would just silently lose the toast).
     *
     * The audit-row file list is hard-coded here as documentation —
     * see the regression table in `ARCHITECTURE.md` / the #1403
     * issue body for the per-file site count. A future regression
     * that strips the `Toast::emit` calls (e.g. an over-eager
     * "remove unused helper" sweep) fails this gate loudly with
     * the file name; the E2E specs catch the user-visible drop too,
     * but the static scan is the fast feedback loop.
     *
     * Process-isolated tests against `page.lostpassword.php` etc.
     * would be the "really execute the handler" complement, but
     * those pages all call `PageDie()` (`exit;` after rendering
     * the footer) which breaks `RunInSeparateProcess`'s serializer.
     * The E2E specs cover the runtime side end-to-end against a
     * real Apache + DB; this static gate covers the codebase-shape
     * side cheaply.
     */
    public function testEveryAuditedPageStillCallsToastEmit(): void
    {
        $expected = [
            'pages/page.lostpassword.php',
            'pages/page.protest.php',
            'pages/page.banlist.php',
            'pages/page.commslist.php',
            'pages/admin.edit.comms.php',
            'pages/page.submit.php',
        ];

        $missing = [];
        foreach ($expected as $rel) {
            $abs = ROOT . $rel;
            $this->assertFileExists(
                $abs,
                "Audited page $rel was renamed or removed — update the #1403 audit-list above.",
            );
            $contents = (string) file_get_contents($abs);
            // Tolerate both fully-qualified and `use`-imported call
            // shapes. The mechanical conversion landed every site
            // as `\Sbpp\View\Toast::emit(` (fully qualified) per
            // the AGENTS.md "Namespacing" convention so the call
            // doesn't require adding a `use` line per file, but a
            // future cleanup that imports + drops the `\` is fine
            // — both shapes satisfy the contract.
            $hasFqn = str_contains($contents, '\\Sbpp\\View\\Toast::emit(');
            $hasShort = str_contains($contents, 'Toast::emit(')
                && (str_contains($contents, 'use Sbpp\\View\\Toast')
                    || str_contains($contents, 'use \\Sbpp\\View\\Toast'));
            if (!$hasFqn && !$hasShort) {
                $missing[] = $rel;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Pages on the #1403 audit list no longer call `\\Sbpp\\View\\Toast::emit(`; the user-feedback path is silently dropped. Either restore the helper call (preferred) or — if the surface was deliberately removed — update the audit list above.\n"
                . "Missing call sites in:\n  " . implode("\n  ", $missing),
        );
    }

    /**
     * Walk `web/pages/*.php` (and any subdirectory under it) and
     * return a `[relativePath => absolutePath]` map. Mirrors the
     * scan shape `PaletteActionsTest.php` uses; keeps the regression
     * suite self-contained (no `git ls-files` shell-out).
     *
     * @return array<string, string>
     */
    private function collectPagePhpFiles(): array
    {
        $files = [];
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(ROOT . 'pages', \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );
        foreach ($iter as $entry) {
            /** @var \SplFileInfo $entry */
            if ($entry->isFile() && $entry->getExtension() === 'php') {
                $abs = $entry->getPathname();
                $rel = substr($abs, strlen(ROOT));
                $files[$rel] = $abs;
            }
        }
        return $files;
    }

    /**
     * Read $file and return `[lineNumber => lineText]` for every
     * line that carries a live `<script>ShowBox(` (or the two
     * variants `<script>showBox(` / `<script type="text/javascript">ShowBox(`)
     * AND is not inside a comment context.
     *
     * "Inside a comment context" means one of:
     *   - line starts with `//` or `#` after leading whitespace (PHP single-line)
     *   - line is part of a `/* … * /` block (we track open/close cheaply)
     *   - line starts with `*` after leading whitespace (mid-block of `/* … * /`)
     *   - line starts with `{*` or contains `{* ... *}` (Smarty comment)
     *
     * This is approximate but covers every comment shape that
     * appears in `web/pages/*.php` today.
     *
     * @return array<int, string>
     */
    private function findLiveShowBoxLines(string $absPath): array
    {
        $contents = (string) file_get_contents($absPath);
        if ($contents === '') {
            return [];
        }
        $lines = preg_split('/\R/', $contents);
        if ($lines === false) {
            return [];
        }

        $hits = [];
        $inBlockComment = false;
        foreach ($lines as $idx => $line) {
            $lineNumber = $idx + 1;
            $trimmed = ltrim($line);

            // Track multi-line `/* ... */` block-comment state. `*/` on
            // the same line as `/*` exits cleanly; we don't worry about
            // multiple comment blocks per line because no live file
            // does that.
            $openedThisLine = false;
            if ($inBlockComment) {
                if (str_contains($line, '*/')) {
                    $inBlockComment = false;
                    // The post-`*/` tail could still carry live code,
                    // but `find` it ourselves on the post-substring
                    // is unnecessary: the only ShowBox shape we're
                    // hunting begins with `<script>` which is its
                    // own statement, and the tail of a `*/` line is
                    // very rarely a `<script>...</script>` echo.
                }
                continue;
            } elseif (str_contains($line, '/*') && !str_contains($line, '*/')) {
                $inBlockComment = true;
                $openedThisLine = true;
                // The pre-`/*` head could carry a live ShowBox, but
                // we don't currently have any file shaped like that.
                continue;
            }

            if ($openedThisLine || $inBlockComment) {
                continue;
            }

            // Single-line comment markers — PHP single-line comments
            // start with `//` or `#`; Smarty single-line comments
            // are `{* ... *}` on a single line; lines starting with
            // `*` are conventionally the middle of a `/* * /` block
            // even when our tracker thinks we're closed (PHP
            // docblock fragments inside arrays / multi-line strings
            // would be unusual but the broad rule matches the
            // codebase's actual conventions).
            if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')
                || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '{*')
            ) {
                continue;
            }

            // Look for the three variants documented in the
            // class-level docblock.
            if (str_contains($line, '<script>ShowBox(')
                || str_contains($line, '<script>showBox(')
                || str_contains($line, '<script type="text/javascript">ShowBox(')
            ) {
                $hits[$lineNumber] = $line;
            }
        }

        return $hits;
    }

    // -----------------------------------------------------------------
    // #1409 — `$duration_ms` parameter contract
    //
    // The 5th parameter on `Sbpp\View\Toast::emit` is the persistent-
    // toast escape hatch — `null` (default) keeps the chrome's
    // SHOWTOAST_DEFAULT_DURATION (~4000ms) timing, `0` disables auto-
    // dismiss entirely (X-button only), `> 0` overrides the timer in
    // milliseconds. The tests below pin every leg of the contract:
    //
    //   - omitted from the wire format when caller passed null
    //   - included on the wire when caller passed 0 or a positive int
    //   - encoded as an integer (not a string) so the consumer's
    //     `typeof data.duration_ms === 'number'` gate passes
    //   - rejected at the PHP boundary on negative input (Fail closed)
    //   - all existing JSON_HEX_* + JSON_INVALID_UTF8_SUBSTITUTE
    //     guarantees preserved when the new field is present
    //   - the 5 NOT-* destructive-action-failed branches in
    //     `page.banlist.php` / `page.commslist.php` use `0` so a
    //     future refactor can't silently regress the persistent
    //     semantic the user explicitly asked for
    //
    // The companion runtime test is
    // `web/tests/e2e/specs/flows/toast-persistent-duration.spec.ts`
    // which drives a NOT-* branch through the chrome end-to-end and
    // asserts the toast outlasts SHOWTOAST_DEFAULT_DURATION.
    // -----------------------------------------------------------------

    /**
     * `$duration_ms` defaults to `null` and is OMITTED from the wire
     * payload in that case — the chrome's `flushPendingToasts`
     * forwards `duration_ms` only when `typeof data.duration_ms ===
     * 'number'`, so omitting the field is the canonical "use the
     * default timer" signal. Emitting `{"duration_ms": null}`
     * instead would technically work (the `typeof null === 'object'`
     * check filters it out) but it wastes bytes and reads as "the
     * server is intentionally signalling no override" which is
     * indistinguishable from the absent case.
     *
     * Pinning the omission here means a future PR that flips to
     * always-serialise has to update the chrome's gate in the same
     * commit OR explicitly justify why every payload should carry
     * a `null` placeholder.
     */
    public function testToastEmitOmitsDurationMsByDefault(): void
    {
        ob_start();
        Toast::emit('info', 'Routine', 'Body');
        $out = (string) ob_get_clean();

        $data = $this->decodeFirstPayload($out);
        $this->assertArrayNotHasKey(
            'duration_ms',
            $data,
            'Wire payload must omit `duration_ms` when the caller did not pass a 5th argument. The chrome consumer\'s `typeof data.duration_ms === \'number\'` gate keeps absence and the chrome\'s SHOWTOAST_DEFAULT_DURATION (~4000ms) as the single source of truth for the default timing.',
        );
    }

    /**
     * Companion to the redirect coalescing contract: when the
     * caller passes a 5th-argument override, the helper emits
     * `duration_ms` in the payload AS AN INTEGER. The consumer's
     * `typeof data.duration_ms === 'number'` gate is what forwards
     * the value to `showToast({durationMs})`; if the encoder
     * emitted it as a JSON string (e.g. via a stringly-typed
     * coercion bug) the gate would skip the override entirely AND
     * the chrome would silently fall back to the default timing —
     * the worst kind of regression because the toast paints
     * normally and only the timing is wrong.
     *
     * `0` is the canonical persistent-toast value the 5 NOT-*
     * call sites use. Pinning the encode shape here means a
     * future refactor that "simplifies" by stringly-typing the
     * field has to update both halves of the gate in the same
     * PR.
     */
    public function testToastEmitIncludesDurationMsWhenSet(): void
    {
        ob_start();
        Toast::emit(
            'error',
            'Ban NOT Deleted',
            "The ban for 'TestPlayer' had an error while being removed.",
            'index.php?p=banlist',
            0,
        );
        $out = (string) ob_get_clean();

        $data = $this->decodeFirstPayload($out);
        $this->assertArrayHasKey(
            'duration_ms',
            $data,
            'Wire payload must include `duration_ms` when the caller passed 0 — the persistent-toast contract depends on the chrome receiving the explicit value.',
        );
        $this->assertSame(
            0,
            $data['duration_ms'] ?? null,
            'Wire payload must carry `duration_ms` as an integer (not a JSON string). The chrome\'s `typeof data.duration_ms === \'number\'` gate filters out string-typed values, which would silently drop the override and fall back to the default timing.',
        );
        $this->assertIsInt(
            $data['duration_ms'],
            'JSON decoder must reify `duration_ms` as a PHP int — same reason as above. A regression to a string-typed encode would parse back as a string here.',
        );

        // Belt-and-braces: the rest of the payload still parses
        // cleanly. The chrome can't render a toast that's missing
        // kind / title.
        $this->assertSame('error', $data['kind'] ?? null);
        $this->assertSame('Ban NOT Deleted', $data['title'] ?? null);
        $this->assertSame('index.php?p=banlist', $data['redirect'] ?? null);
    }

    /**
     * The positive-override leg of the contract: `> 0` is an
     * explicit ms override (currently no in-tree caller uses it,
     * but the contract exists for future surfaces — e.g. a
     * long-form Markdown-rendered toast that needs a longer read
     * window without going fully persistent).
     *
     * The encoder shouldn't special-case the value range — `0` and
     * `8000` ride the same code path. This test pins the
     * stringly-typed-encode regression on the positive side
     * (the `0`-side test above pins the same thing but at zero,
     * which a future bug might special-case "because zero is
     * falsy in JavaScript"; covering both ends keeps the contract
     * symmetric).
     */
    public function testToastEmitIncludesDurationMsForPositiveOverride(): void
    {
        ob_start();
        Toast::emit('info', 'Long form', 'Take your time reading this.', null, 8000);
        $out = (string) ob_get_clean();

        $data = $this->decodeFirstPayload($out);
        $this->assertArrayHasKey('duration_ms', $data);
        $this->assertSame(8000, $data['duration_ms'] ?? null);
        $this->assertIsInt($data['duration_ms']);
        // No redirect was supplied — the helper must still omit
        // that field even when `duration_ms` is present (the two
        // optional fields are independent).
        $this->assertArrayNotHasKey(
            'redirect',
            $data,
            'Helper must keep optional fields independent — passing `$duration_ms` does not justify emitting a `null` redirect.',
        );
    }

    /**
     * Fail closed: negative `$duration_ms` is programmer error
     * (the caller's arithmetic is broken). Coercing via
     * `max(0, $duration_ms)` would silently flip the toast to
     * persistent — the worst-of-both outcome because the
     * developer's bug is masked AND the user gets a toast they
     * have to click X to dismiss. Throwing the
     * `\InvalidArgumentException` surfaces the bug immediately at
     * the call site, which is where the fix needs to land.
     *
     * Pre-existing helpers in this layer don't validate inputs
     * (e.g. `$kind` is loose-typed by convention — passing
     * `'huh'` instead of `'success'` produces a toast with a
     * generic icon, no exception). But `$duration_ms` is
     * different: the chrome side has no clamp / sanity check
     * either (the typeof gate filters non-numbers but happily
     * forwards `-5` through to `setTimeout`, which silently
     * coerces to 1ms on most browsers — auto-dismisses
     * immediately, indistinguishable from "the toast never
     * painted"). The PHP-side throw is the single layer that
     * catches the bug.
     */
    public function testToastEmitRejectsNegativeDuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Capture the side-effect output even when the exception
        // throws — we want to assert NOTHING was echoed before the
        // throw (no partial wire-payload landing in the response
        // body that the chrome would then try to parse).
        ob_start();
        try {
            Toast::emit('error', 'Bad', 'Body', null, -1);
        } finally {
            $out = (string) ob_get_clean();
            $this->assertSame(
                '',
                $out,
                'Helper must validate `$duration_ms` BEFORE echoing the wire payload — a partial emit on the reject path would land a half-formed JSON blob in the response body and the chrome\'s `JSON.parse` would throw on the next page-load.',
            );
        }
    }

    /**
     * Belt-and-braces on the JSON-escape contract: when
     * `duration_ms` is present, all the existing JSON_HEX_* +
     * JSON_INVALID_UTF8_SUBSTITUTE guarantees still apply. A
     * future regression that adds the field via a custom encode
     * step (e.g. string-concatenating the payload manually
     * "because it's just one new field") would silently drop
     * every defensiveness flag.
     *
     * The shape mirrors `testToastEmitJsonEscapingDefendsScriptBreakout`
     * with the addition of `duration_ms: 0` to prove the new
     * field doesn't break the existing contract.
     */
    public function testToastEmitWireFormatStaysHexEscapedWithDurationMs(): void
    {
        ob_start();
        Toast::emit(
            'error',
            '</script><script>alert(1)</script>',
            "&<>'\"" . "\xC3broken", // malformed UTF-8 too
            null,
            0,
        );
        $out = (string) ob_get_clean();

        // The script-breakout defence still fires.
        $this->assertStringNotContainsString(
            '</script><script>alert(1)</script>',
            substr($out, 0, (int) strrpos($out, '</script>')),
            'JSON_HEX_TAG must still escape `<` even when `duration_ms` is present — a hostile payload cannot terminate the script element via either the title or body field.',
        );
        $this->assertStringContainsString(
            '\u003C',
            $out,
            'JSON_HEX_TAG hex-escape contract preserved with `duration_ms` field present.',
        );

        // The UTF-8 substitute defence still fires.
        $this->assertMatchesRegularExpression(
            '/\\\\u(?:FFFD|fffd)/',
            $out,
            'JSON_INVALID_UTF8_SUBSTITUTE substitute contract preserved with `duration_ms` field present.',
        );

        // The new field is intact + correctly typed.
        $data = $this->decodeFirstPayload($out);
        $this->assertSame(0, $data['duration_ms'] ?? null);
        $this->assertIsInt($data['duration_ms']);

        // The rest of the payload is parseable.
        $this->assertSame('error', $data['kind'] ?? null);
        $this->assertIsString($data['title'] ?? null);
        $this->assertIsString($data['body'] ?? null);
    }

    /**
     * Pin the call-site contract for the 5 NOT-* branches the
     * #1409 follow-up converted to persistent toasts. A future
     * refactor that drops the 5th positional argument from any of
     * these branches would silently restore the auto-dismiss
     * timer on a severe-error confirmation the operator was
     * supposed to acknowledge — exactly the regression vector
     * #1409 closes.
     *
     * The branches all share a literal "NOT" (capital N-O-T,
     * with a space) in the toast title — that's the convention
     * the v1.x `ShowBox(..., sticky=true)` callers used to
     * distinguish "this destructive operation FAILED" from "this
     * destructive operation succeeded". The #1403 mechanical
     * lift preserved the casing verbatim; #1409 restores the
     * sticky semantic under a cleaner contract.
     *
     * Each entry is `[relPath, titlePrefix]` — we grep the file
     * for `Toast::emit('error', '<titlePrefix>'` and assert the
     * call site passes a 5th positional argument (whose value is
     * `0`). Pattern is `/Toast::emit\(\s*'error',\s*'<title>',\s*[^,]+,\s*[^,]+,\s*0\s*,?\s*\)/`
     * — five comma-separated args, the last one is the literal
     * `0`. The trailing `,?` accepts the PSR-12-style trailing-
     * comma shape some files use and some don't.
     *
     * Future refactors that move the call sites to a helper that
     * defaults to `duration_ms: 0` (so the literal arg goes
     * away) should update this test in the same PR; the contract
     * is "the persistent semantic is preserved", and a helper
     * call would satisfy that even though the static grep no
     * longer matches.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function notStarBranches(): array
    {
        return [
            ['pages/page.banlist.php',   'Player NOT Unbanned'],
            ['pages/page.banlist.php',   'Ban NOT Deleted'],
            ['pages/page.commslist.php', 'Player NOT UnGagged'], // matches two sites in this file (ungag + unmute branches); the assertion below tolerates multi-match
            ['pages/page.commslist.php', 'Ban NOT Deleted'],
        ];
    }

    #[DataProvider('notStarBranches')]
    public function testNotStarBranchesPassPersistentDurationMs(string $rel, string $titlePrefix): void
    {
        $abs = ROOT . $rel;
        $this->assertFileExists(
            $abs,
            "NOT-* branch source $rel was renamed or removed — update notStarBranches() above.",
        );
        $contents = (string) file_get_contents($abs);

        // Five-arg pattern: kind, title, body, redirect, duration_ms.
        // The body / redirect / first-4-arg shape is intentionally
        // loose — we only care that the 5th arg is `0`. The pattern
        // tolerates multi-line emit calls (most of them ARE multi-
        // line per the codebase's style); `s` flag is mandatory.
        // The title literal is anchored with surrounding quotes so
        // a partial-match like "Player NOT UnGagged" doesn't pick
        // up a hypothetical "Player NOT UnGagged Successfully" too.
        $titleQuoted = preg_quote($titlePrefix, '#');
        $pattern = '#\\\\?Sbpp\\\\View\\\\Toast::emit\\(\\s*'
            . "'error',\\s*"            // kind
            . "'{$titleQuoted}',\\s*"  // title (anchored, exact)
            . '[^,]+?,\\s*'             // body (one comma-free chunk)
            . '[^,]+?,\\s*'             // redirect (one comma-free chunk)
            . '0\\s*,?\\s*\\)#s';        // duration_ms (literal 0, optional trailing comma)

        $found = preg_match_all($pattern, $contents, $matches);
        $this->assertGreaterThan(
            0,
            $found,
            "Call site $rel:'$titlePrefix' does not pass `duration_ms: 0` as the 5th positional arg to `\\Sbpp\\View\\Toast::emit(...)`. The severe-error toast would auto-dismiss after ~4000ms and the operator would miss the failure confirmation — exactly the v1.x `ShowBox(..., sticky=true)` semantic #1409 restored. If the call site was migrated to a helper that defaults to `duration_ms: 0`, update `notStarBranches()` above to remove this branch from the static gate.",
        );

        // Belt-and-braces companion: every NOT-* match must pass
        // `null` for the 4th `$redirect` arg. Persistent +
        // redirect are mutually exclusive — the chrome's
        // `flushPendingToasts` would otherwise navigate ~1500ms
        // after paint, tearing down the toast before the operator
        // can read or dismiss it (defeating the persistent
        // semantic). Pin the call-site half of the contract here;
        // the chrome's defence-in-depth inhibit is exercised
        // end-to-end by
        // `web/tests/e2e/specs/flows/toast-persistent-duration.spec.ts`.
        //
        // The tighter check pins specifically the literal `null`
        // 4th argument (`'null'` would NOT satisfy — that's a
        // string "null" which the chrome would treat as a real
        // redirect target).
        $strictPattern = '#\\\\?Sbpp\\\\View\\\\Toast::emit\\(\\s*'
            . "'error',\\s*"
            . "'{$titleQuoted}',\\s*"
            . '[^,]+?,\\s*'            // body
            . 'null\\s*,\\s*'           // redirect MUST be the literal `null`
            . '0\\s*,?\\s*\\)#s';
        $strictFound = preg_match_all($strictPattern, $contents, $strictMatches);
        $this->assertGreaterThan(
            0,
            $strictFound,
            "Call site $rel:'$titlePrefix' passes `duration_ms: 0` but ALSO passes a non-null `\$redirect` — the two are mutually exclusive per AGENTS.md \"Server-side toast emission\" → \"Duration semantics\". The chrome's `flushPendingToasts` redirect coalescing would navigate ~1500ms after paint, tearing down the persistent toast before the operator can read or dismiss it (the exact #1409 regression vector). Drop the redirect to `null` so the operator stays on the rendered surface and acknowledges the failure via the X button.",
        );
    }

    /**
     * Decode the FIRST `sbpp-pending-toast` JSON blob from the
     * helper's output. Shared between every wire-format test
     * above — encapsulates the parse-the-script-body dance so
     * each test reads as "what does the payload look like".
     *
     * @return array<string, mixed>
     */
    private function decodeFirstPayload(string $out): array
    {
        $start = strpos($out, '">') + 2;
        $end = strrpos($out, '</script>');
        $this->assertNotFalse($end, 'Could not find script close tag in helper output.');
        $json = substr($out, $start, $end - $start);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded, "Helper emitted invalid JSON: $json");
        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
