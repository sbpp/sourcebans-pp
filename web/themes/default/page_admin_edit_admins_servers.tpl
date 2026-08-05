{*
    SourceBans++ 2026 — admin/admins edit server access

    Pair: web/pages/admin.edit.adminservers.php and
    web/includes/View/EditAdminServersView.php.

    The handler gates entry on ADMIN_OWNER | ADMIN_EDIT_ADMINS before
    reaching this template, so there is no per-template access boolean.
    The admin id rides the URL via $smarty.get.id rather than a View
    property so this template stays compatible with the unmodified
    handler (which never assigned $aid).

    The cross-page tab nav (Details / Group / Servers / Permissions)
    keeps the URL bar honest about which sub-page you're on; the
    data-testid hooks match the issue's edit-form-tabs contract.

    Server groups + individual servers ride data-multiselect (same
    shape as page_admin_admins_add.tpl). Wire values stay g{gid} /
    s{sid} so the native form POST to admin.edit.adminservers.php
    is unchanged. Individual-server option labels hydrate via the
    page-tail Actions.ServersHostPlayers loop.
*}
<div class="card-tab page-section" id="Edit Admin Server Access">
    <div class="mb-4">
        <h1 style="font-size:var(--fs-xl);font-weight:600;margin:0">Edit admin server access</h1>
        <p class="text-sm text-muted m-0 mt-2">Pick the servers and server groups this admin can administer in-game.</p>
    </div>

    <nav class="flex gap-2 mb-4 items-center" role="tablist" aria-label="Edit admin sections">
        <a class="btn btn--ghost btn--sm" role="tab"
           href="?p=admin&c=admins&o=editdetails&id={$smarty.get.id|escape:'url'}"
           data-testid="admin-tab-details">Details</a>
        <a class="btn btn--ghost btn--sm" role="tab"
           href="?p=admin&c=admins&o=editgroup&id={$smarty.get.id|escape:'url'}"
           data-testid="admin-tab-group">Group</a>
        <a class="btn btn--secondary btn--sm" role="tab" aria-current="page"
           href="?p=admin&c=admins&o=editservers&id={$smarty.get.id|escape:'url'}"
           data-testid="admin-tab-servers">Servers</a>
        <a class="btn btn--ghost btn--sm" role="tab"
           href="?p=admin&c=admins&o=editpermissions&id={$smarty.get.id|escape:'url'}"
           data-testid="admin-tab-permissions">Permissions</a>
        <a class="btn btn--ghost btn--sm admin-tabs__back"
           href="index.php?p=admin&amp;c=admins"
           data-testid="admin-tab-back">
            <i data-lucide="arrow-left"></i> Back
        </a>
    </nav>

    {if $row_count < 1}
        <div class="card">
            <div class="card__body">
                <p class="text-sm text-muted m-0"><em>You need to add a server or a server group before you can set up admin server permissions.</em></p>
            </div>
        </div>
    {else}
        <form method="post" action="" class="space-y-4">
            {csrf_field}
            <input type="hidden" name="editadminserver" value="1">

            {if $group_list}
                <div class="card">
                    <div class="card__header">
                        <div>
                            <h3>Server groups</h3>
                            <p>Granting a group covers every server in that group.</p>
                        </div>
                    </div>
                    <div class="card__body">
                        <label class="label" for="edit-admin-server-groups">Server groups</label>
                        <select class="select"
                                id="edit-admin-server-groups"
                                name="group[]"
                                multiple
                                data-multiselect
                                data-placeholder="Select server groups&hellip;"
                                data-testid="edit-admin-server-groups">
                            {foreach $group_list as $group}
                                <option value="g{$group.gid}"
                                        data-testid="edit-admin-server-group"
                                        {foreach $assigned_servers as $asrv}{if $asrv.srv_group_id == $group.gid}selected{/if}{/foreach}>{$group.name|escape}</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
            {/if}

            {if $server_list}
                <div class="card">
                    <div class="card__header">
                        <div>
                            <h3>Individual servers</h3>
                            <p>One-off access for servers that aren't part of a granted group.</p>
                        </div>
                    </div>
                    <div class="card__body">
                        <label class="label" for="edit-admin-servers">Individual servers</label>
                        <select class="select"
                                id="edit-admin-servers"
                                name="servers[]"
                                multiple
                                data-multiselect
                                data-placeholder="Select servers&hellip;"
                                data-testid="edit-admin-servers">
                            {foreach $server_list as $server}
                                <option value="s{$server.sid}"
                                        data-sid="{$server.sid}"
                                        data-ip="{$server.ip}"
                                        data-port="{$server.port}"
                                        data-server-host
                                        data-testid="edit-admin-server"
                                        {foreach $assigned_servers as $asrv}{if $asrv.server_id == $server.sid}selected{/if}{/foreach}>Loading&hellip; ({$server.ip}:{$server.port})</option>
                            {/foreach}
                        </select>
                    </div>
                </div>
            {/if}

            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn--ghost btn--sm"
                        onclick="history.go(-1);">Back</button>
                <button type="submit" class="btn btn--primary btn--sm" id="editadminserver"
                        data-testid="edit-admin-servers-save"><i data-lucide="save"></i> Save changes</button>
            </div>
        </form>
    {/if}
</div>

<script>
{literal}
(function () {
    'use strict';
    if (typeof sb === 'undefined' || !sb || !sb.api || typeof Actions === 'undefined') {
        return;
    }
    var serverSel = document.getElementById('edit-admin-servers');
    if (!(serverSel instanceof HTMLSelectElement)) return;
    Array.prototype.forEach.call(
        serverSel.querySelectorAll('option[data-server-host]'),
        function (opt) {
            var sid = Number(opt.getAttribute('data-sid'));
            var ip = opt.getAttribute('data-ip') || '';
            var port = opt.getAttribute('data-port') || '';
            if (!sid) return;
            sb.api.call(Actions.ServersHostPlayers, {
                sid: sid,
                trunchostname: 70,
            }).then(function (r) {
                if (!r || !r.ok || !r.data) {
                    opt.textContent = 'Offline (' + ip + ':' + port + ')';
                    return;
                }
                var d = r.data;
                if (d.error === 'connect') {
                    opt.textContent = 'Offline (' + ip + ':' + port + ')';
                    return;
                }
                opt.textContent = (d.hostname || (ip + ':' + port))
                    + ' (' + ip + ':' + port + ')';
            }, function () {
                opt.textContent = 'Offline (' + ip + ':' + port + ')';
            });
        },
    );
})();
{/literal}
</script>
