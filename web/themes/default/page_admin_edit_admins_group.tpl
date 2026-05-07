{*
    SourceBans++ 2026 — admin/admins edit groups

    Pair: web/pages/admin.edit.admingroup.php and
    web/includes/View/EditAdminGroupView.php.

    The handler gates entry on ADMIN_OWNER | ADMIN_EDIT_ADMINS before
    reaching this template, so there is no per-template access boolean.
    The admin id rides the URL via $smarty.get.id rather than a View
    property so this template stays compatible with the unmodified
    handler (which never assigned $aid).

    The cross-page tab nav (Details / Group / Servers / Permissions)
    keeps the URL bar honest about which sub-page you're on; the
    data-testid hooks match the issue's edit-form-tabs contract.
*}
<div class="card-tab page-section" id="Edit Admin Groups">
    <div class="mb-4">
        <h1 style="font-size:var(--fs-xl);font-weight:600;margin:0">Edit admin · {$group_admin_name|escape}</h1>
        <p class="text-sm text-muted m-0 mt-2">Move <strong>{$group_admin_name|escape}</strong> between web and server admin groups.</p>
    </div>

    <nav class="flex gap-2 mb-4" role="tablist" aria-label="Edit admin sections">
        <a class="btn btn--ghost btn--sm" role="tab"
           href="?p=admin&c=admins&o=editdetails&id={$smarty.get.id|escape:'url'}"
           data-testid="admin-tab-details">Details</a>
        <a class="btn btn--secondary btn--sm" role="tab" aria-current="page"
           href="?p=admin&c=admins&o=editgroup&id={$smarty.get.id|escape:'url'}"
           data-testid="admin-tab-group">Group</a>
        <a class="btn btn--ghost btn--sm" role="tab"
           href="?p=admin&c=admins&o=editservers&id={$smarty.get.id|escape:'url'}"
           data-testid="admin-tab-servers">Servers</a>
        <a class="btn btn--ghost btn--sm"
           href="?p=admin&c=admins&o=editpermissions&id={$smarty.get.id|escape:'url'}">Permissions</a>
    </nav>

    <form method="post" action="" class="space-y-4">
        {csrf_field}

        <div class="card">
            <div class="card__header">
                <div>
                    <h3>Web admin group</h3>
                    <p>Group that controls access to the web panel.</p>
                </div>
            </div>
            <div class="card__body">
                <label class="label" for="wg">Web admin group</label>
                <select class="select" id="wg" name="wg" data-testid="edit-admin-webgroup">
                    <option value="-1">No group</option>
                    <optgroup label="Groups" style="font-weight:bold">
                        {foreach $web_lst as $wg}
                            <option value="{$wg.gid}" {if $wg.gid == $group_admin_id}selected{/if}>{$wg.name|escape}</option>
                        {/foreach}
                    </optgroup>
                </select>
                <div id="wgroup.msg" class="text-xs" style="color:var(--danger);margin-top:0.25rem"></div>
            </div>
        </div>

        <div class="card">
            <div class="card__header">
                <div>
                    <h3>Server admin group</h3>
                    <p>SourceMod group that controls in-game admin permissions.</p>
                </div>
            </div>
            <div class="card__body">
                <label class="label" for="sg">Server admin group</label>
                <select class="select" id="sg" name="sg" data-testid="edit-admin-srvgroup">
                    <option value="-1">No group</option>
                    <optgroup label="Groups" style="font-weight:bold">
                        {foreach $group_lst as $sg}
                            <option value="{$sg.id}" {if $sg.id == $server_admin_group_id}selected{/if}>{$sg.name|escape}</option>
                        {/foreach}
                    </optgroup>
                </select>
                <div id="sgroup.msg" class="text-xs" style="color:var(--danger);margin-top:0.25rem"></div>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <button type="button" class="btn btn--ghost btn--sm"
                    onclick="history.go(-1);">Back</button>
            <button type="submit" class="btn btn--primary btn--sm" id="agroups"
                    data-testid="edit-admin-group-save"><i data-lucide="save"></i> Save changes</button>
        </div>
    </form>
</div>
