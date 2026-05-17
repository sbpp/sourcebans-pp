<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under Creative Commons Attribution-NonCommercial-ShareAlike 3.0.
// See LICENSE.md for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

declare(strict_types=1);

namespace Sbpp\View;

/**
 * Queue a toast (and optional post-toast redirect) for the chrome
 * (`web/themes/default/js/theme.js`) to surface on
 * `DOMContentLoaded`. Replaces the v1.x-era
 * `<script>ShowBox(…)</script>` blobs — `ShowBox` lived in
 * `web/scripts/sourcebans.js`, which was deleted in #1123 D1 /
 * #1160 (v2.0.0), so every legacy caller throws
 * `ReferenceError: ShowBox is not defined` in the modern chrome and
 * silently swallows the message (#1176, audit follow-up #1403).
 *
 * Several legacy callers also run upstream of `PageDie()` (which
 * renders the chrome footer and `exit`s), so the template body is
 * suppressed too — the user sees a literally blank page on top of
 * the dropped toast. The two failure modes compound in production:
 * a self-hoster resetting their password sees a blank white page
 * while the reset email lands in their inbox, then clicks Reset
 * Password three more times "to make it work" and burns four
 * tokens. Routing the payload through this helper restores the
 * footer + the surfaces the toast.
 *
 * # Wire format
 *
 * The payload is emitted as a `<script type="application/json"
 * class="sbpp-pending-toast">` block, NOT an executable inline
 * script. Three reasons:
 *
 *   1. **Order-of-operations safety.** Page handlers run in the
 *      middle of `build()` — `core/header.tpl`, `core/navbar.tpl`,
 *      and `core/title.tpl` have already flushed; the toast container
 *      (`#toast-stack`) and `window.SBPP.showToast` won't exist
 *      until `theme.js` loads from `core/footer.tpl`. The JSON-blob
 *      shape decouples emission from execution: `theme.js` reads
 *      every blob at boot regardless of when the page handler
 *      `echo`'d it. The inline executable shape (the pre-#1403
 *      `emitSubmitToast` helper) handled this by deferring its
 *      own call onto `DOMContentLoaded`, but that path required
 *      sprinkling JSON-encoded literals into hand-written JS;
 *      every additional caller widens the inline-script attack
 *      surface for no payoff.
 *   2. **CSP friendliness.** A future `script-src 'self'` content
 *      security policy would reject the inline executable shape
 *      outright; `<script type="application/json">` is parsed as
 *      text content and CSP treats it as data, not script. The
 *      same shape is what `core/footer.tpl` already uses for the
 *      `palette-actions` blob (#1304) — single source for the
 *      "embed structured data the chrome consumes at boot"
 *      pattern.
 *   3. **Multi-toast support.** Using a class (not an id) means a
 *      page handler can emit several toasts in one request without
 *      conflicting; `theme.js` iterates the full set. E2E specs
 *      anchor on the *rendered* `[data-testid="toast"]` element
 *      (set by `showToast` in `theme.js`) or `[role="status"]`
 *      — NOT on the wire-format `<script>` block, because we may
 *      emit several blocks per response and a wire-format testid
 *      would collide. Wire-layer specs probe the response body
 *      directly for `class="sbpp-pending-toast"`.
 *
 * Payload shape (post-`json_encode`):
 *
 * ```json
 * {
 *     "kind":  "info" | "success" | "warn" | "error",
 *     "title": "Title text",
 *     "body":  "Body text (plain text — escapeHtml'd at render)",
 *     "redirect": "?p=banlist&page=2"   // optional
 * }
 * ```
 *
 * `redirect` is optional. When present, `theme.js` honours it AFTER
 * the toasts paint (a brief settle delay so the user can see what
 * just happened). If multiple toasts emit redirects in the same
 * request, the FIRST one wins — the rest are ignored. In practice
 * a single request never emits more than one redirect (the GET
 * fallback paths in `page.banlist.php` / `page.commslist.php` /
 * `admin.edit.comms.php` all bounce back to the same list page
 * regardless of the success/error branch).
 *
 * # Encoding
 *
 * `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`
 * mirrors the `palette_actions_json` encoder in `core/footer.php`
 * — every potentially-dangerous char (`<>&'"`) is escaped as
 * `\uXXXX`, so the blob can't break out of its `<script>` wrapper
 * regardless of what a future caller drops into `title` / `body`.
 * `JSON_THROW_ON_ERROR` surfaces malformed input loudly instead of
 * silently emitting `false` (which `echo` would render as the
 * empty string).
 *
 * `JSON_INVALID_UTF8_SUBSTITUTE` is the load-bearing flag for
 * fault tolerance. Player names on `:prefix_bans.name` /
 * `:prefix_comms.name` (interpolated into the toast body on the
 * GET-fallback unban / unmute / delete paths in `page.banlist.php`
 * / `page.commslist.php`) CAN carry malformed UTF-8 — historical
 * Latin-1-on-utf8 truncation shape from pre-#1108 (#765) installs
 * whose plugin-side insert path wrote bytes that the post-#1108
 * utf8mb4 migration did not retroactively repair. Without this
 * flag `JSON_THROW_ON_ERROR` raises `JsonException` on every such
 * row and the user gets a 500 instead of the unban confirmation
 * — and worse, the unban / delete SQL has already committed by
 * the time `Toast::emit` fires, so the audit log shows the action
 * succeeded while the operator sees a server error. With this
 * flag the offending bytes substitute to U+FFFD (the Unicode
 * REPLACEMENT CHARACTER) and the toast paints. Same fault-
 * tolerance shape every modern JSON API uses; the substitute
 * fires only on the genuinely broken path so well-formed payloads
 * are unaffected.
 *
 * # Body is plain text
 *
 * `body` is rendered through `theme.js`'s `escapeHtml` before
 * landing in the DOM, so HTML tags (including line-break tags)
 * surface as visible text. Legacy v1.x callers used line-break tags
 * to split a `ShowBox` body across multiple lines; converting them
 * to spaces at the call site (e.g. via a `preg_replace` against a
 * `br`-tag pattern) is the canonical workaround — see
 * `page.protest.php` and `page.submit.php` for live examples of the
 * shape, including the regex pattern itself which is omitted from
 * this docblock because PHP has no escape sequence for the close-tag
 * token inside a doc-comment.
 *
 * @see web/themes/default/js/theme.js (`flushPendingToasts`,
 *      `showToast`) for the consumer
 * @see web/themes/default/core/footer.tpl for `theme.js` mount + the
 *      sibling `palette-actions` blob this shape mirrors
 * @see web/pages/page.submit.php for the original `emitSubmitToast`
 *      that motivated lifting this helper to a shared surface
 */
final class Toast
{
    /**
     * Emit a queued toast (and optional redirect) into the response.
     *
     * `$kind` is one of `'info' | 'success' | 'warn' | 'error'`,
     * matching the `ToastOpts.kind` typedef in
     * `web/themes/default/js/theme.js`. Picks the icon + accent
     * colour on the rendered toast.
     *
     * `$body` is plain text (the JS chrome `escapeHtml`s it before
     * inserting into the DOM). HTML in the body string renders as
     * visible literal text; convert at the call site if your legacy
     * `ShowBox` payload contained line-break tags (see
     * `page.protest.php` and `page.submit.php` for the canonical
     * `preg_replace` shape).
     *
     * `$redirect` is an optional URL the chrome navigates to after
     * the toast paints. Pass `null` (or omit) when the page
     * re-renders its own form on the same URL — the toast then fires
     * inline on whichever response the browser is already loading.
     *
     * Echoes the JSON blob directly to the response. No return
     * value — the meaningful output IS the side effect, mirroring
     * the pre-#1403 `emitSubmitToast` shape.
     */
    public static function emit(
        string $kind,
        string $title,
        string $body,
        ?string $redirect = null,
    ): void {
        $payload = [
            'kind'  => $kind,
            'title' => $title,
            'body'  => $body,
        ];
        if ($redirect !== null && $redirect !== '') {
            $payload['redirect'] = $redirect;
        }

        $json = json_encode(
            $payload,
            JSON_THROW_ON_ERROR
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT,
        );

        echo '<script type="application/json" class="sbpp-pending-toast">'
            . $json
            . '</script>';
    }
}
