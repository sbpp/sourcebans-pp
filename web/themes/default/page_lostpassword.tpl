{*
    SourceBans++ 2026 — page_lostpassword.tpl

    Public lost-password form. Mirrors the credential block in
    handoff/pages/login.tpl (centred card, .label / .input / .btn--primary)
    so the recovery flow visually rhymes with the eventual B redesign of
    the login page itself.

    The form posts via sb.api.call(Actions.AuthLostPassword, {email})
    rather than a plain HTML POST: the only server-side handler for this
    page is the JSON action, exactly as the legacy default theme did
    (web/themes/default/page_lostpassword.tpl). sb.api.call automatically
    attaches the X-CSRF-Token header from the <meta name="csrf-token">
    in the chrome, but {csrf_field} is included anyway per AGENTS.md's
    "every state-changing form" rule so the form would still validate if
    JS were ever disabled and a future PR wired up a POST fallback.

    Pair: web/includes/View/LostPasswordView.php (declares no properties;
    the form is static and the API response is rendered client-side).

    Response handling is inline rather than reaching for the legacy
    web/scripts/sourcebans.js applyApiResponse helper because the new
    theme intentionally drops sourcebans.js (#1123 D1). The handler
    funnels both the success envelope (`data.message`) and the error
    envelope (`error.message`) into window.SBPP.showToast(...) which
    theme.js (vendored at A1) exposes globally.

    Testability hooks per the issue's "Testability hooks" rule:
      - email input:    data-testid="lostpw-email"
      - submit button:  data-testid="lostpw-submit"
*}
<div class="lostpw">
    <div class="card lostpw__card">
        <div class="card__header">
            <div>
                <h3>Forgot your password?</h3>
                <p>Enter the email address associated with your admin account and we&rsquo;ll send you a link to reset your password.</p>
            </div>
        </div>
        <div class="card__body">
            <form id="lostpw-form" class="space-y-3" method="post" action="index.php?p=lostpassword" novalidate>
                {csrf_field}
                <div>
                    <label class="label" for="lostpw-email">Email address</label>
                    <input id="lostpw-email"
                           class="input"
                           type="email"
                           name="email"
                           autocomplete="email"
                           required
                           data-testid="lostpw-email">
                </div>
                <button id="lostpw-submit"
                        class="btn btn--primary"
                        type="submit"
                        style="width:100%;margin-top:0.5rem"
                        data-testid="lostpw-submit">
                    <i data-lucide="mail"></i> Send reset link
                </button>
            </form>
            <p class="text-xs text-muted mt-4" style="text-align:center">
                <a href="index.php?p=login" style="color:var(--accent)">&larr; Back to sign in</a>
            </p>
        </div>
    </div>
</div>

{literal}
<style>
    .lostpw { display:flex; align-items:flex-start; justify-content:center; padding:2.5rem 1rem; }
    .lostpw__card { width:100%; max-width:24rem; }
</style>

<script>
(function () {
    'use strict';
    var form = document.getElementById('lostpw-form');
    var emailEl = document.getElementById('lostpw-email');
    var submitEl = document.getElementById('lostpw-submit');
    if (!form || !emailEl || !submitEl) return;

    function toast(kind, title, body) {
        if (window.SBPP && typeof window.SBPP.showToast === 'function') {
            window.SBPP.showToast({ kind: kind, title: title, body: body || '' });
        }
    }
    /**
     * Flip the busy / loading state on a triggered action button. Calls
     * window.SBPP.setBusy when present (theme.js owns the spinner CSS
     * contract) and falls back to plain `disabled` so third-party themes
     * that strip theme.js still gate against double-clicks.
     */
    function setBusy(btn, busy) {
        if (!btn) return;
        var S = window.SBPP;
        if (S && typeof S.setBusy === 'function') S.setBusy(btn, busy);
        else btn.disabled = busy === undefined ? true : !!busy;
    }

    function mapKind(k) {
        if (k === 'red') return 'error';
        if (k === 'green') return 'success';
        if (k === 'blue') return 'info';
        return k || 'info';
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!emailEl.value) {
            toast('warn', 'Email required', 'Please enter your email address.');
            return;
        }
        setBusy(submitEl, true);
        sb.api.call(Actions.AuthLostPassword, { email: emailEl.value }).then(function (res) {
            setBusy(submitEl, false);
            if (!res || res.redirect) return;
            if (res.ok === false) {
                var emsg = (res.error && res.error.message) || 'Unknown error';
                toast('error', 'Error', emsg);
                return;
            }
            var msg = (res.data || {}).message;
            if (msg) {
                toast(mapKind(msg.kind), msg.title || 'Done', msg.body || '');
            }
        });
    });
}());
</script>
{/literal}
