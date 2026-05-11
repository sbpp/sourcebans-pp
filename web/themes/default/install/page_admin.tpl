{*
    SourceBans++ install wizard — step 5 (admin account form half).
    Pair view: \Sbpp\View\Install\InstallAdminView (web/includes/View/Install/InstallAdminView.php).
    Page handler: web/install/pages/page.5.php.
*}
{include file="install/_chrome.tpl"}

<p class="lead">
    Create the first administrator account. This account has the
    <strong>Owner</strong> permission flag &mdash; it can do
    everything in the panel, including creating other admins.
</p>

{if $error !== ''}
    <div class="install-alert install-alert--error"
         role="alert"
         data-testid="install-admin-error"
         style="margin-bottom:1.25rem">
        {$error}
    </div>
{/if}

{*
    Issue #1335 M3 review: pre-review the form carried `novalidate`,
    which switched off the browser's pre-submit checks for
    `required` / `minlength` / `pattern` / `type="email"` and
    shifted the load to the page-tail JS. That violated AGENTS.md's
    install-wizard rule that "the form's native `required` /
    `pattern` attributes must be the load-bearing gate, with JS as
    the UX polish" — and the JS only covered SteamID + email +
    password-match, so empty fields and short passwords still
    bounced server-side and wiped both passwords on re-render.

    Post-review: native attrs are load-bearing again. The browser
    surfaces popovers for empty / short / pattern-mismatch / type-
    mismatch cases before our submit handler runs; the handler
    only covers cross-field password matching (the one validation
    native HTML can't express). Server-side stays the load-bearing
    gate for JS-disabled clients, but the round-trip-with-wiped-
    passwords path is now off the happy path for every input
    failure.
*}
<form method="post"
      action="?step=5"
      id="install-admin-form"
      data-testid="install-admin-form"
      autocomplete="off">
    <input type="hidden" name="postd"    value="1">
    <input type="hidden" name="server"   value="{$val_server}">
    <input type="hidden" name="port"     value="{$val_port}">
    <input type="hidden" name="username" value="{$val_username}">
    <input type="hidden" name="password" value="{$val_password}">
    <input type="hidden" name="database" value="{$val_database}">
    <input type="hidden" name="prefix"   value="{$val_prefix}">
    <input type="hidden" name="apikey"   value="{$val_apikey}">
    <input type="hidden" name="sb-email" value="{$val_sb_email}">
    <input type="hidden" name="charset"  value="{$val_charset}">

    <div class="install-section">
        <h2>Administrator account</h2>
        <div class="install-grid">
            <div>
                <label class="label" for="install-admin-uname">Username</label>
                <input class="input"
                       id="install-admin-uname"
                       name="uname"
                       type="text"
                       value="{$val_uname}"
                       autocomplete="username"
                       data-testid="install-admin-uname"
                       required>
            </div>

            <div>
                <label class="label" for="install-admin-email">Email</label>
                <input class="input"
                       id="install-admin-email"
                       name="email"
                       type="email"
                       value="{$val_email}"
                       autocomplete="email"
                       data-testid="install-admin-email"
                       required>
            </div>

            <div>
                <label class="label" for="install-admin-pass1">Password</label>
                <input class="input"
                       id="install-admin-pass1"
                       name="pass1"
                       type="password"
                       autocomplete="new-password"
                       minlength="8"
                       data-testid="install-admin-pass1"
                       required>
                <p class="text-xs text-muted">At least 8 characters.</p>
            </div>

            <div>
                <label class="label" for="install-admin-pass2">Confirm password</label>
                <input class="input"
                       id="install-admin-pass2"
                       name="pass2"
                       type="password"
                       autocomplete="new-password"
                       minlength="8"
                       data-testid="install-admin-pass2"
                       required>
            </div>

            <div style="grid-column:1/-1">
                <label class="label" for="install-admin-steam">Steam ID</label>
                <input class="input"
                       id="install-admin-steam"
                       name="steam"
                       type="text"
                       value="{$val_steam}"
                       placeholder="STEAM_0:0:1234567"
                       pattern="STEAM_[01]:[01]:[0-9]+"
                       data-testid="install-admin-steam"
                       required>
                <p class="text-xs text-muted">
                    SteamID in <code>STEAM_0:X:NNNNNNN</code> format.
                    Use <a href="https://steamid.io/" target="_blank" rel="noopener">steamid.io</a>
                    if you have a vanity URL or SteamID64.
                </p>
            </div>
        </div>
    </div>

    <div class="install-actions">
        <a class="btn btn--ghost" href="?step=4" data-testid="install-admin-back">
            Back
        </a>
        <button class="btn btn--primary"
                type="submit"
                data-testid="install-admin-continue">
            Create admin

        </button>
    </div>
</form>

{include file="install/_chrome_close.tpl"}

<script>
(function () {
    'use strict';
    // Issue #1335 M3 (post-review): the form is now native-validated
    // (no `novalidate`), so the browser handles empty / short /
    // pattern-mismatch / type-mismatch cases before this handler
    // runs. The one validation native HTML can't express on its
    // own is cross-field password matching (`pass1.value ===
    // pass2.value`), so that's all this handler covers.
    //
    // The reason this still matters: pre-fix, every server-side
    // validation failure wiped both password fields on the
    // re-render (the values can't be echoed back into the template
    // — `autocomplete="new-password"` blocks browser autofill, and
    // bouncing them through a hidden input would leak them through
    // any rendered HTML the operator's browser caches). Catching
    // the mismatch client-side keeps that round-trip off the
    // happy path. Server-side `page.5.php` stays the load-bearing
    // gate for JS-disabled clients.
    var form  = document.getElementById('install-admin-form');
    var pass1 = document.getElementById('install-admin-pass1');
    var pass2 = document.getElementById('install-admin-pass2');
    if (!form || !pass1 || !pass2) return;

    form.addEventListener('submit', function (e) {
        // Native validation has already cleared every other field
        // (required filled, minlength met, pattern matches, type
        // shape valid). If we land here, the only remaining failure
        // mode is the password-match check.
        if (pass1.value !== pass2.value) {
            pass2.setCustomValidity('Passwords do not match.');
            pass2.reportValidity();
            e.preventDefault();
        } else {
            pass2.setCustomValidity('');
        }
    });

    // Clear the custom validity on input so a corrected pass2
    // doesn't keep firing the popover after the user fixes it.
    pass2.addEventListener('input', function () { pass2.setCustomValidity(''); });
})();
</script>
