{*
    SourceBans++ install wizard — step 3 (requirements check).
    Pair view: \Sbpp\View\Install\InstallRequirementsView (web/includes/View/Install/InstallRequirementsView.php).
    Page handler: web/install/pages/page.3.php.
*}
{include file="install/_chrome.tpl"}

<p class="lead">
    Quick environment check before we touch your database.
    {if $errors > 0}
        <strong>{$errors} blocking issue{if $errors != 1}s{/if}</strong>
        must be fixed before continuing.
    {elseif $warnings > 0}
        <strong>{$warnings} warning{if $warnings != 1}s{/if}</strong>
        &mdash; you can continue, but some features may not work.
    {else}
        Everything looks good!
    {/if}
</p>

{if $errors > 0}
    <div class="install-alert install-alert--error"
         role="alert"
         data-testid="install-requirements-blocked"
         style="margin-bottom:1.25rem">
        Fix the blocking issues marked in red below, then click
        <strong>Re-check</strong>.
    </div>
{elseif $warnings > 0}
    <div class="install-alert install-alert--warn"
         role="status"
         data-testid="install-requirements-warning"
         style="margin-bottom:1.25rem">
        Some recommendations failed. The wizard can still continue.
    </div>
{else}
    <div class="install-alert install-alert--ok"
         role="status"
         data-testid="install-requirements-ok"
         style="margin-bottom:1.25rem">
        All checks passed.
    </div>
{/if}

{foreach from=$groups item=group}
    <div class="install-section">
        <h2>{$group.title}</h2>
        <div class="card">
            <table class="install-table" data-testid="install-requirements-{$group.title|lower|replace:' ':'-'}">
                <thead>
                    <tr>
                        <th>Setting</th>
                        <th>Required</th>
                        <th>Status</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$group.rows item=row}
                        <tr>
                            <td><strong>{$row.label}</strong></td>
                            <td>{$row.required}</td>
                            <td>
                                {if $row.status == 'ok'}
                                    <span class="install-pill install-pill--ok">OK</span>
                                {elseif $row.status == 'warn'}
                                    <span class="install-pill install-pill--warn">Warn</span>
                                {else}
                                    <span class="install-pill install-pill--err">Fail</span>
                                {/if}
                            </td>
                            <td>{$row.detail}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    </div>
{/foreach}

<form method="post"
      action="?step=4"
      id="install-requirements-form"
      data-testid="install-requirements-form"
      autocomplete="off">
    <input type="hidden" name="server"   value="{$val_server}">
    <input type="hidden" name="port"     value="{$val_port}">
    <input type="hidden" name="username" value="{$val_username}">
    <input type="hidden" name="password" value="{$val_password}">
    <input type="hidden" name="database" value="{$val_database}">
    <input type="hidden" name="prefix"   value="{$val_prefix}">
    <input type="hidden" name="apikey"   value="{$val_apikey}">
    <input type="hidden" name="sb-email" value="{$val_email}">

    <div class="install-actions">
        <a class="btn btn--ghost" href="?step=2" data-testid="install-requirements-back">
            Back
        </a>
        <button class="btn btn--secondary"
                type="button"
                data-testid="install-requirements-recheck"
                onclick="window.location.reload()">
            Re-check
        </button>
        {if $can_continue}
            <button class="btn btn--primary"
                    type="submit"
                    data-testid="install-requirements-continue">
                Continue
            </button>
        {else}
            <button class="btn btn--primary"
                    type="submit"
                    disabled
                    aria-disabled="true"
                    data-testid="install-requirements-continue">
                Fix issues to continue
            </button>
        {/if}
    </div>
</form>

{include file="install/_chrome_close.tpl"}
