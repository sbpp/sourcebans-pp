{*
    SourceBans++ install wizard — auto-submit handoff page.
    Pair view: \Sbpp\View\Install\InstallDatabaseHandoffView
              (web/includes/View/Install/InstallDatabaseHandoffView.php).
    Renderers: page.2.php (post DB validate), page.4.php (post schema apply).
*}
{include file="install/_chrome.tpl"}

<div class="card" style="text-align:center">
    <div class="card__body" style="padding:2rem 1rem">
        <p class="lead" style="margin:0 0 1rem">
            <strong>{$step_title}.</strong> Continuing to step {$next_step}&hellip;
        </p>

        <form method="post"
              action="?step={$next_step}"
              id="install-handoff-form"
              data-testid="install-handoff-form"
              autocomplete="off">
            <input type="hidden" name="server"   value="{$val_server}">
            <input type="hidden" name="port"     value="{$val_port}">
            <input type="hidden" name="username" value="{$val_username}">
            <input type="hidden" name="password" value="{$val_password}">
            <input type="hidden" name="database" value="{$val_database}">
            <input type="hidden" name="prefix"   value="{$val_prefix}">
            <input type="hidden" name="apikey"   value="{$val_apikey}">
            <input type="hidden" name="sb-email" value="{$val_email}">

            <noscript>
                <p class="text-sm text-muted" style="margin-bottom:1rem">
                    JavaScript is disabled &mdash; click the button to continue.
                </p>
            </noscript>

            <button class="btn btn--primary"
                    type="submit"
                    data-testid="install-handoff-continue">
                Continue
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';
    var form = document.getElementById('install-handoff-form');
    if (form) {
        // Defer to next tick so any in-flight form-restoration
        // by the browser settles first.
        setTimeout(function () { form.submit(); }, 50);
    }
})();
</script>

{include file="install/_chrome_close.tpl"}
