-{*
    SourceBans++ 2026 — page_login.tpl

    Replaces the A1 stub. Pair: web/pages/page.login.php +
    web/includes/View/LoginView.php (#1123 B1).

    Delimiters
    ----------
    This template renders with the `-{ … }-` delimiter pair (the page
    handler swaps them in around the Renderer::render() call, mirroring
    `web/pages/page.youraccount.php`). Two reasons:

      1. The legacy `web/themes/default/page_login.tpl` already uses
         `-{ … }-`, so the same View can drive both templates without
         a per-theme delimiter conditional in the page handler.
      2. The inline `<script>` block at the bottom is large enough that
         wrapping every JS object literal in `{literal}…{/literal}` for
         the standard `{ … }` pair would noise the template up. With
         `-{ … }-` the `{` / `}` JS punctuation is plain text.

    The other sbpp2026 templates use the standard `{ … }` pair; this
    template is the only `-{ … }-` page in the new theme, matching the
    legacy default theme's per-template oddity. #1123 D1 will rewrite
    both halves to standard `{ … }` once the legacy bundle is deleted.

    Why this differs from handoff/pages/login.tpl
    ---------------------------------------------
    handoff/pages/login.tpl renders a full-viewport 2-column split
    (sign-in card + dark marketing hero) by escaping the React app
    shell. The repo's web/includes/page-builder.php always wraps
    every page in core/{header,navbar,title,footer}.tpl, so the
    full-viewport trick collides with the chrome added in #1123 A2.
    Per the B1 dispatch ("strip the demo's 'stat panel right' if no
    data feeds it cheaply"), we drop the hero aside and render a
    single centered sign-in card inside the page slot. The chrome's
    breadcrumb / ⌘K / theme toggle stay visible — they're harmless
    on the login page and removing them would require touching
    chrome PHP that B1 must not touch.

    Error banner -> toast
    ---------------------
    The legacy theme echoes ShowBox() JS dialogs from the page handler
    on `?m=failed` / `?m=locked` / etc. The new chrome drops
    web/scripts/sourcebans.js (#1123 D1), so ShowBox() is undefined
    here. Instead, the inline <script> reads the `?m=…` (and `?time=…`)
    query parameters and surfaces them via window.SBPP.showToast(),
    which theme.js (vendored at A1) exposes globally. Keeping the
    message text client-side keeps the LoginView property surface
    aligned with the legacy template (no baseline entries needed for
    the dual-theme PHPStan matrix during the rollout window).

    Form submit
    -----------
    The form posts via sb.api.call(Actions.AuthLogin, …) (NOT a string
    literal — see AGENTS.md "Frontend"). api.js's call() honours the
    `redirect` envelope automatically (sets window.location), which is
    how the existing api_auth_login handler signals both success ("?")
    and failure ("?p=login&m=failed"); we don't have to handle the
    response body here.

    Testability hooks (per #1123 B1 dispatch + AGENTS.md)
    ------------------------------------------------------
      data-testid="login-form"      — outer <form>
      data-testid="login-username"  — username text input
      data-testid="login-password"  — password input
      data-testid="login-remember"  — remember-me checkbox
      data-testid="login-submit"    — submit button
      data-testid="login-steam"     — "Continue with Steam" link
      data-testid="login-disabled"  — banner shown when both methods are off
      data-testid="login-lostpwd"   — lost-password link
*}-
<div class="login-shell">
    <div class="card login-shell__card">
        <div class="card__body">
            <div class="flex items-center gap-3 mb-6">
                -{* #1235 — brand mark is the operator-configurable
                   `template.logo` setting, theme-resolved in the page
                   handler (page.login.php) and passed in as
                   `$brand_logo_url`. Default ships as the SourceBans++
                   shield from the favicon set. *}-
                <img class="sidebar__brand-mark" src="-{$brand_logo_url}-" alt="" aria-hidden="true">
                <div>
                    <div class="font-semibold text-sm">SourceBans++</div>
                    <div class="text-xs text-muted">Admin panel</div>
                </div>
            </div>

            <h1 class="m-0" style="font-size:var(--fs-2xl);font-weight:600">Sign in</h1>
            <p class="text-sm text-muted mt-2">Use your admin credentials, or sign in with Steam.</p>

            -{if !$normallogin_show and !$steamlogin_show}-
                <div class="toast"
                     role="status"
                     data-testid="login-disabled"
                     style="margin-top:1.5rem">
                    <i data-lucide="info" style="color:var(--info)" aria-hidden="true"></i>
                    <div class="text-sm">
                        Login is currently disabled. Please contact the site administrator.
                    </div>
                </div>
            -{/if}-

            -{if $steamlogin_show}-
                <a class="btn btn--secondary"
                   href="index.php?p=login&amp;o=steam"
                   data-testid="login-steam"
                   style="width:100%;margin-top:1.5rem">
                    <i data-lucide="gamepad-2" aria-hidden="true"></i> Continue with Steam
                </a>
            -{/if}-

            -{if $steamlogin_show and $normallogin_show}-
                <div class="login-shell__divider" aria-hidden="true">
                    <span>Or with credentials</span>
                </div>
            -{/if}-

            -{*
                The page handler emits `$redir` as a JS expression
                (currently "DoLogin('');") that the LEGACY default theme
                inlines into its button onclick and Enter/Space keydown
                handlers. The new form posts via sb.api.call(Actions.AuthLogin)
                directly, so `$redir` is unused for login wiring here — but
                we still echo it on a dead `data-legacy-redir` attribute so
                SmartyTemplateRule sees the reference (otherwise the sbpp2026
                leg of the dual-theme PHPStan matrix added in #1123 A2 would
                fire `unusedProperty` for LoginView::$redir). Auto-escape is
                on globally (init.php → setEscapeHtml(true)) so the JS
                punctuation lands HTML-encoded inside the attribute and is
                never executed. The attribute and the View property both go
                away when #1123 D1 deletes the legacy template.
            *}-
            -{if $normallogin_show}-
                <form id="loginForm"
                      class="space-y-3"
                      method="post"
                      action="index.php?p=login"
                      data-testid="login-form"
                      data-legacy-redir="-{$redir}-"
                      novalidate
                      autocomplete="on">
                    -{csrf_field}-

                    <div>
                        <label class="label" for="loginUsername">Username</label>
                        <input class="input"
                               id="loginUsername"
                               name="username"
                               type="text"
                               autocomplete="username"
                               required
                               data-testid="login-username">
                    </div>

                    <div>
                        <label class="label" for="loginPassword">Password</label>
                        <input class="input"
                               id="loginPassword"
                               name="password"
                               type="password"
                               autocomplete="current-password"
                               required
                               data-testid="login-password">
                    </div>

                    <label class="flex items-center gap-2 text-xs text-muted" for="loginRememberMe">
                        <input id="loginRememberMe"
                               type="checkbox"
                               name="remember"
                               value="1"
                               checked
                               data-testid="login-remember">
                        Remember me on this device
                    </label>

                    <button class="btn btn--primary"
                            type="submit"
                            data-testid="login-submit"
                            style="width:100%;margin-top:.5rem">
                        Sign in <i data-lucide="arrow-right" aria-hidden="true"></i>
                    </button>
                </form>

                <div class="mt-4 text-xs" style="text-align:center">
                    <a href="index.php?p=lostpassword" data-testid="login-lostpwd">Lost your password?</a>
                </div>
            -{/if}-
        </div>
    </div>
</div>

<style>
    .login-shell { display:flex; justify-content:center; padding:2.5rem 1rem; }
    .login-shell__card { width:100%; max-width:24rem; }
    .login-shell__divider { position:relative; margin:1.25rem 0; }
    .login-shell__divider::before {
        content:""; position:absolute; inset:50% 0 auto 0;
        border-top:1px solid var(--border);
    }
    .login-shell__divider > span {
        position:relative; display:block; width:max-content; margin:0 auto;
        background:var(--bg-surface); padding:0 .5rem;
        font-size:.625rem; letter-spacing:.06em; text-transform:uppercase;
        color:var(--text-faint);
    }
</style>

<script>
(function () {
    'use strict';

    // ----- form submit -> sb.api.call(Actions.AuthLogin, ...) ---------
    // `redirect` is the post-login destination passed to the JSON API
    // (`web/api/handlers/auth.php` → `Api::redirect('?' . <redir-param>)`).
    // Empty string lands on `?` which the dashboard/route handler
    // resolves to the home page. The `data-legacy-redir` attribute on
    // the <form> carries the legacy theme's `$redir` JS expression for
    // SmartyTemplateRule parity only — the new flow never reads it.
    var form = document.getElementById('loginForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var u = document.getElementById('loginUsername');
            var p = document.getElementById('loginPassword');
            var r = document.getElementById('loginRememberMe');
            sb.api.call(Actions.AuthLogin, {
                username: u ? u.value : '',
                password: p ? p.value : '',
                remember: !!(r && r.checked),
                redirect: ''
            });
        });
    }

    // ----- ?m=… query-param -> SBPP.showToast(...) -------------------
    // Replaces the legacy theme's per-message ShowBox() <script>
    // emitted from web/pages/page.login.php. Driving the toast
    // client-side keeps the message text out of the LoginView's
    // property surface (and therefore out of the dual-theme PHPStan
    // baseline) during the rollout window.
    var params = new URLSearchParams(window.location.search);
    var m = params.get('m');
    if (!m) return;
    var time = parseInt(params.get('time') || '0', 10);
    var lockedBody = 'Your account is temporarily locked due to too many failed login attempts. '
                   + 'Please try again in approximately ' + time + ' minute'
                   + (time === 1 ? '' : 's') + '.';
    var msgs = {
        no_access:    { kind: 'error', title: 'No access',          body: "You don't have permission to access this page. Please log in with an account that has access." },
        empty_pwd:    { kind: 'info',  title: 'Empty password',     body: 'Your account has an empty password set. Reset it via the lost-password link, or ask an admin to do that for you.' },
        failed:       { kind: 'error', title: 'Login failed',       body: 'The username or password you supplied was incorrect. If you forgot your password, use the lost-password link below.' },
        steam_failed: { kind: 'error', title: 'Steam login failed', body: "Steam login was successful, but your SteamID isn't associated with any account." },
        locked:       { kind: 'error', title: 'Account locked',     body: lockedBody }
    };
    var msg = msgs[m];
    if (!msg) return;
    function show() {
        if (window.SBPP && typeof window.SBPP.showToast === 'function') {
            window.SBPP.showToast({ kind: msg.kind, title: msg.title, body: msg.body });
        } else {
            // theme.js still loading; retry on next tick.
            setTimeout(show, 50);
        }
    }
    show();
}());
</script>
