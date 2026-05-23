// @ts-check
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

JSON API client. Calls /api.php with {action, params}, returns the parsed
envelope. Honours `redirect` automatically (sets window.location). On
network failures returns a synthetic error envelope so callers don't have
to special-case fetch rejections.
*************************************************************************/

(function (global) {
    'use strict';

    /** @type {SbNamespace} */
    const sb = global.sb || (global.sb = /** @type {any} */ ({}));

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? (meta.getAttribute('content') || '') : '';
    }

    // Capture the script's own URL at script-load time —
    // `document.currentScript` is only non-null while the parent
    // <script> tag is executing synchronously (it's null inside
    // promises / async handlers / setTimeout callbacks). The
    // iframe-routed surfaces (`pages/admin.kickit.php`,
    // `pages/admin.blockit.php`) load api.js from `/scripts/api.js`
    // regardless of the iframe document's URL, so resolving
    // `../api.php` against the script's own absolute URL lands on
    // the panel-root `/api.php` for both top-level page renders AND
    // iframe contexts (and subdir installs — see `resolveEndpoint`
    // below).
    //
    // **api.js MUST be loaded via a static `<script src="…">` tag,
    // never via dynamic injection** (`document.createElement('script')`,
    // `<script>document.write(...)</script>`, async loaders). Dynamic
    // injection runs the script with `document.currentScript === null`,
    // which collapses `SCRIPT_SRC` to the empty string and silently
    // falls back to the bare-relative `./api.php` endpoint — i.e.
    // the exact pre-#1433 bug shape. The runtime contract is "the
    // script that's loading is one of the three static `<script>` tags
    // in `core/header.tpl` (top-level panel chrome,
    // `./scripts/api.js`), `page_kickit.tpl` / `page_blockit.tpl`
    // (iframe surfaces, `../scripts/api.js`)". An SVG `<script>`
    // would never load api.js in the first place, so the
    // `HTMLOrSVGScriptElement` union's SVG arm (which lacks `.src`)
    // is unreachable; we cast to `HTMLScriptElement` to read `.src`.
    //
    // Pre-#1433 the endpoint was a bare `./api.php` literal — the
    // browser resolved it against the *document* URL of whichever
    // page was hosting the script. For the iframe-routed surfaces
    // that's `/pages/admin.kickit.php`, so the fetch went to
    // `/pages/api.php` (404 — Apache doesn't rewrite that), the
    // iframe's `KickitLoadServers` call resolved to a 404-shaped
    // `bad_response` envelope, and the load handler's silent
    // early-return left every row at the initial "Waiting..." text
    // forever. Player was never kicked. Same code path on every
    // iframe-routed surface that loads api.js.
    var _cs = /** @type {HTMLScriptElement | null} */ (document.currentScript);
    var SCRIPT_SRC = (_cs && _cs.src) || '';

    function resolveEndpoint() {
        if (SCRIPT_SRC) {
            try { return new URL('../api.php', SCRIPT_SRC).href; }
            catch (_e) { /* malformed URL — fall through to the bare-relative fallback */ }
        }
        return './api.php';
    }

    /** @type {SbApiNamespace} */
    sb.api = {
        endpoint: resolveEndpoint(),

        async call(action, params) {
            let res;
            try {
                res = await fetch(sb.api.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type':  'application/json',
                        'X-CSRF-Token':  csrfToken(),
                        'Accept':        'application/json',
                    },
                    body: JSON.stringify({ action, params: params || {} }),
                });
            } catch (e) {
                const msg = e instanceof Error ? e.message : String(e);
                return { ok: false, error: { code: 'network', message: 'Network error: ' + msg } };
            }

            /** @type {SbApiEnvelope} */
            let envelope;
            try {
                envelope = await res.json();
            } catch (e) {
                return { ok: false, error: { code: 'bad_response', message: 'Server returned invalid JSON (HTTP ' + res.status + ')' } };
            }

            if (envelope && typeof envelope.redirect === 'string') {
                window.location.href = envelope.redirect;
                return envelope;
            }
            return envelope;
        },

        /**
         * Convenience wrapper: shows an sb.message.error() box on failure.
         * Returns the envelope so callers can still inspect `data` on success.
         */
        async callOrAlert(action, params) {
            const res = await sb.api.call(action, params);
            if (res && res.ok === false && res.error) {
                if (sb.message) sb.message.error(res.error.code === 'forbidden' ? 'Access Denied' : 'Error', res.error.message || 'Unknown error');
            }
            return res;
        },
    };
})(typeof window !== 'undefined' ? window : this);
