{*
    SourceBans++ install wizard — step 5 (success page).
    Pair view: \Sbpp\View\Install\InstallDoneView (web/includes/View/Install/InstallDoneView.php).
    Page handler: web/install/pages/page.5.php.

    Rendered after: config.php is written, data.sql is run, the admin
    row is created. From here the operator either (a) deletes the
    install/ folder and logs in, or (b) optionally imports AMXBans
    bans via step 6.
*}
{include file="install/_chrome.tpl"}

<div class="install-alert install-alert--ok"
     role="status"
     data-testid="install-done-success"
     style="margin-bottom:1.5rem">
    <strong>Installation complete!</strong>
    Your SourceBans++ panel is ready to use.
</div>

<div class="install-section">
    <h2>1. Delete the install/ folder</h2>
    <p>
        For security, remove the <code>install/</code> directory now.
        Leaving it accessible lets anyone re-run the wizard against your
        live database.
    </p>
</div>

<div class="install-section">
    <h2>2. Add SourceBans++ to your gameserver</h2>
    <p>
        Paste the snippet below into
        <code>addons/sourcemod/configs/databases.cfg</code> on each
        gameserver, <strong>inside</strong> the
        <code>"Databases" {ldelim} ... {rdelim}</code> block.
    </p>
    <pre class="install-code"
         data-testid="install-done-databases-cfg"><code>{$databases_cfg}</code></pre>

    {if $show_local_warning}
        <div class="install-alert install-alert--info"
             role="status"
             data-testid="install-done-local-warning"
             style="margin-top:0.75rem">
            <strong>Heads up:</strong> you used <code>localhost</code>
            for the database host. That's fine for the panel itself,
            but gameservers on a different machine need a hostname or
            IP they can route to &mdash; update the
            <code>"host"</code> value above for those.
        </div>
    {/if}
</div>

{if !$config_writable}
    <div class="install-section">
        <h2>3. Save config.php manually</h2>
        <div class="install-alert install-alert--warn"
             role="alert"
             data-testid="install-done-config-warn"
             style="margin-bottom:0.75rem">
            <strong>The wizard couldn't write to <code>config.php</code>.</strong>
            Copy the snippet below into the file at the panel root
            before logging in.
        </div>
        <pre class="install-code"
             data-testid="install-done-config-text"><code>{$config_text}</code></pre>
    </div>
{/if}

<div class="install-section">
    <h2>{if $config_writable}3{else}4{/if}. Optional &mdash; import AMXBans</h2>
    <p>
        If you're migrating from AMXBans, the next step copies your
        existing bans into SourceBans++. You can also skip this and
        log in now.
    </p>
</div>

<div class="install-actions">
    <form method="post" action="?step=6" style="display:inline">
        <input type="hidden" name="server"   value="{$val_server}">
        <input type="hidden" name="port"     value="{$val_port}">
        <input type="hidden" name="username" value="{$val_username}">
        <input type="hidden" name="password" value="{$val_password}">
        <input type="hidden" name="database" value="{$val_database}">
        <input type="hidden" name="prefix"   value="{$val_prefix}">
        <button class="btn btn--secondary"
                type="submit"
                data-testid="install-done-import">
            Import AMXBans
        </button>
    </form>
    <a class="btn btn--primary"
       href="../"
       data-testid="install-done-finish">
        Open the panel
    </a>
</div>

{include file="install/_chrome_close.tpl"}
