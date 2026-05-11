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

<form method="post"
      action="?step=5"
      id="install-admin-form"
      data-testid="install-admin-form"
      autocomplete="off"
      novalidate>
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
    // Issue #1335 M3: pre-fix, every server-side validation failure
    // (bad SteamID format, bad email, mismatched passwords, short
    // password) wiped both password fields on the re-render. The
    // password values can't be echoed back into the template
    // (`autocomplete="new-password"` blocks browser autofill, and
    // bouncing them through a hidden input would leak them through
    // any rendered HTML the operator's browser caches). Shifting the
    // common validations client-side keeps the round-trip-with-
    // wiped-passwords path off the happy path. Server-side validation
    // (page.5.php) stays as the load-bearing gate for the cases
    // where JS is disabled.
    var form  = document.getElementById('install-admin-form');
    var pass1 = document.getElementById('install-admin-pass1');
    var pass2 = document.getElementById('install-admin-pass2');
    var steam = document.getElementById('install-admin-steam');
    var email = document.getElementById('install-admin-email');
    if (!form || !pass1 || !pass2 || !steam || !email) return;

    var STEAM_RE = /^STEAM_[01]:[01]:[0-9]+$/;

    form.addEventListener('submit', function (e) {
        var failed = false;

        // Mismatched passwords — pin the original v2.0 client-side
        // contract. setCustomValidity + reportValidity surfaces the
        // browser's native validation popover anchored to the input.
        if (pass1.value !== pass2.value) {
            pass2.setCustomValidity('Passwords do not match.');
            pass2.reportValidity();
            failed = true;
        } else {
            pass2.setCustomValidity('');
        }

        // SteamID shape — server-side check on page.5.php is the
        // backstop, but failing it client-side avoids the wiped-
        // passwords re-render. Allow STEAM_0:X:NNNNNNN and
        // STEAM_1:X:NNNNNNN (the page handler normalises STEAM_1
        // to STEAM_0 before insert).
        if (!failed && !STEAM_RE.test(steam.value)) {
            steam.setCustomValidity('Steam ID must be in STEAM_0:X:NNNNNNN format.');
            steam.reportValidity();
            failed = true;
        } else {
            steam.setCustomValidity('');
        }

        // Email shape — `<input type="email">` already gates this
        // before submit, but call out explicitly so the JS contract
        // doesn't depend on the browser implementing the type. The
        // `validity.typeMismatch` check is the standard way to ask
        // the browser "is this a syntactically valid email?".
        if (!failed && email.validity.typeMismatch) {
            email.setCustomValidity('Email address is invalid.');
            email.reportValidity();
            failed = true;
        } else {
            email.setCustomValidity('');
        }

        if (failed) {
            e.preventDefault();
        }
    });

    // Clear the custom validity on input so a corrected value
    // doesn't keep firing the popover. Each field gets its own
    // listener so cross-field clears (e.g. fixing the SteamID
    // doesn't reset the password validity) stay independent.
    pass2.addEventListener('input', function () { pass2.setCustomValidity(''); });
    steam.addEventListener('input', function () { steam.setCustomValidity(''); });
    email.addEventListener('input', function () { email.setCustomValidity(''); });
})();
</script>
