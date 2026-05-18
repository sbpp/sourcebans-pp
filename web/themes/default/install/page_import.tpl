{*
    SourceBans++ install wizard — step 6 (optional AMXBans import).
    Pair view: \Sbpp\View\Install\InstallImportView (web/includes/View/Install/InstallImportView.php).
    Page handler: web/install/pages/page.6.php.
*}
{include file="install/_chrome.tpl"}

<p class="lead">
    Migrate bans from an existing AMXBans database into your new
    SourceBans++ install. Skip this if you don't have an AMXBans
    install to import from.
</p>

{*
    Issue #1335 m6: pre-fix this form had no field-level helper
    text — same fields as page 2's database form but without the
    "default for MySQL is 3306" / "usually localhost" hints. The
    AMXBans-specific top-of-form hint points operators at
    `addons/amxmodx/configs/sql.cfg` (the canonical source for
    these values on a SourceMod gameserver running AMXBans).
*}
<div class="install-alert install-alert--info"
     role="status"
     data-testid="install-import-source-hint"
     style="margin-bottom:1.25rem">
    Look in <code>addons/amxmodx/configs/sql.cfg</code> on your
    AMXBans gameserver for the source values to paste below.
</div>

{if $error !== ''}
    <div class="install-alert install-alert--error"
         role="alert"
         data-testid="install-import-error"
         style="margin-bottom:1.25rem">
        {$error}
    </div>
{/if}

{if $result_text !== ''}
    <div class="install-alert install-alert--ok"
         role="status"
         data-testid="install-import-result"
         style="margin-bottom:1.25rem">
        {* nofilter: $result_text is built by sbpp_install_amxbans_import in page.6.php from controlled status strings; never embeds raw user input. *}
        {$result_text nofilter}
    </div>
{/if}

<form method="post"
      action="?step=6"
      id="install-import-form"
      data-testid="install-import-form"
      autocomplete="off"
      novalidate>
    <input type="hidden" name="postd" value="1">
    <input type="hidden" name="server"   value="{$val_server}">
    <input type="hidden" name="port"     value="{$val_port}">
    <input type="hidden" name="username" value="{$val_username}">
    <input type="hidden" name="password" value="{$val_password}">
    <input type="hidden" name="database" value="{$val_database}">
    <input type="hidden" name="prefix"   value="{$val_prefix}">

    <div class="install-section">
        <h2>AMXBans database connection</h2>
        <div class="install-grid">
            <div>
                <label class="label" for="install-amx-server">Hostname</label>
                <input class="input"
                       id="install-amx-server"
                       name="amx_server"
                       type="text"
                       value="{$val_amx_server}"
                       placeholder="localhost"
                       data-testid="install-amx-server"
                       required>
                <p class="text-xs text-muted">Usually <code>localhost</code> on shared hosting.</p>
            </div>

            <div>
                <label class="label" for="install-amx-port">Port</label>
                <input class="input"
                       id="install-amx-port"
                       name="amx_port"
                       type="number"
                       min="1"
                       max="65535"
                       value="{$val_amx_port}"
                       data-testid="install-amx-port"
                       required>
                <p class="text-xs text-muted">Default for MySQL / MariaDB is <code>3306</code>.</p>
            </div>

            <div>
                <label class="label" for="install-amx-username">Username</label>
                <input class="input"
                       id="install-amx-username"
                       name="amx_username"
                       type="text"
                       value="{$val_amx_username}"
                       autocomplete="username"
                       data-testid="install-amx-username"
                       required>
                <p class="text-xs text-muted">The DB user that owns the AMXBans tables (read access is enough).</p>
            </div>

            <div>
                <label class="label" for="install-amx-password">Password</label>
                <input class="input"
                       id="install-amx-password"
                       name="amx_password"
                       type="password"
                       autocomplete="new-password"
                       data-testid="install-amx-password">
                <p class="text-xs text-muted">Leave blank if your AMXBans DB user has no password set.</p>
            </div>

            <div>
                <label class="label" for="install-amx-database">Database name</label>
                <input class="input"
                       id="install-amx-database"
                       name="amx_database"
                       type="text"
                       value="{$val_amx_database}"
                       data-testid="install-amx-database"
                       required>
                <p class="text-xs text-muted">The AMXBans schema you want to import bans from.</p>
            </div>

            <div>
                <label class="label" for="install-amx-prefix">Table prefix</label>
                <input class="input"
                       id="install-amx-prefix"
                       name="amx_prefix"
                       type="text"
                       value="{$val_amx_prefix}"
                       maxlength="9"
                       pattern="[A-Za-z0-9_]+"
                       data-testid="install-amx-prefix"
                       required>
                <p class="text-xs text-muted">Up to 9 letters / digits / underscores. AMXBans default: <code>amx</code>.</p>
            </div>
        </div>
    </div>

    <div class="install-actions">
        <a class="btn btn--ghost" href="?step=5" data-testid="install-import-back">
            Back
        </a>
        <a class="btn btn--ghost" href="../" data-testid="install-import-skip">
            Skip &amp; open the panel
        </a>
        <button class="btn btn--primary"
                type="submit"
                data-testid="install-import-run">
            Import bans

        </button>
    </div>
</form>

{include file="install/_chrome_close.tpl"}
