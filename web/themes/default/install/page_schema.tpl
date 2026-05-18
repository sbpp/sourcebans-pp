{*
    SourceBans++ install wizard — step 4 (schema install).
    Pair view: \Sbpp\View\Install\InstallSchemaView (web/includes/View/Install/InstallSchemaView.php).
    Page handler: web/install/pages/page.4.php.
*}
{include file="install/_chrome.tpl"}

<p class="lead">
    Creating the SourceBans++ tables in your database
    <code>{$val_database}</code> (charset <code>{$charset}</code>).
</p>

{if $success}
    <div class="install-alert install-alert--ok"
         role="status"
         data-testid="install-schema-success">
        Created <strong>{$tables_created}</strong> table{if $tables_created != 1}s{/if}
        successfully. Continue to the next step to create your admin
        account.
    </div>
{else}
    <div class="install-alert install-alert--error"
         role="alert"
         data-testid="install-schema-error">
        <strong>Schema creation failed.</strong>
        {$errors_text}
        Go back to the database step and double-check the credentials
        + permissions for your DB user (it needs CREATE / ALTER /
        INDEX / INSERT).
    </div>
{/if}

<form method="post"
      action="?step=5"
      id="install-schema-form"
      data-testid="install-schema-form"
      autocomplete="off">
    <input type="hidden" name="server"   value="{$val_server}">
    <input type="hidden" name="port"     value="{$val_port}">
    <input type="hidden" name="username" value="{$val_username}">
    <input type="hidden" name="password" value="{$val_password}">
    <input type="hidden" name="database" value="{$val_database}">
    <input type="hidden" name="prefix"   value="{$val_prefix}">
    <input type="hidden" name="apikey"   value="{$val_apikey}">
    <input type="hidden" name="sb-email" value="{$val_email}">
    <input type="hidden" name="charset"  value="{$charset}">

    <div class="install-actions">
        <a class="btn btn--ghost" href="?step=2" data-testid="install-schema-back">
            Back to database
        </a>
        {if $success}
            <button class="btn btn--primary"
                    type="submit"
                    data-testid="install-schema-continue">
                Continue
            </button>
        {else}
            <button class="btn btn--primary"
                    type="submit"
                    disabled
                    aria-disabled="true"
                    data-testid="install-schema-continue">
                Fix errors to continue
            </button>
        {/if}
    </div>
</form>

{include file="install/_chrome_close.tpl"}
