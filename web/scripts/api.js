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

    const sb = global.sb || (global.sb = {});

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    sb.api = {
        endpoint: './api.php',

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
                return { ok: false, error: { code: 'network', message: 'Network error: ' + (e && e.message) } };
            }

            let envelope;
            try {
                envelope = await res.json();
            } catch (e) {
                return { ok: false, error: { code: 'bad_response', message: 'Server returned invalid JSON (HTTP ' + res.status + ')' } };
            }

            if (envelope && typeof envelope.redirect === 'string') {
                window.location = envelope.redirect;
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
