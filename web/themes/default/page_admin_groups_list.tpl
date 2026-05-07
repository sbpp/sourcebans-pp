{*
    SourceBans++ 2026 — page_admin_groups_list.tpl

    Marquee surface for admin groups (#1123 B12). The web-admin section
    is a master-detail flag grid: left rail lists groups, right pane
    SSR-renders one checkbox per web-permission flag (`$all_flags`,
    sourced from web/configs/permissions/web.json) pre-checked against
    the focused group's bitmask. Save posts back via
    `sb.api.call(Actions.GroupsEdit, …)` — no new API handlers needed.

    The two secondary sections (server admin groups + server groups)
    keep parity with the legacy default template's data exposure so
    `Sbpp\View\AdminGroupsListView` stays a clean union of
    sbpp2026/default needs without `phpstan-baseline.neon` carve-outs.
*}
{if NOT $permission_listgroups}
    <div class="card"><div class="card__body"><p class="text-muted m-0">Access denied.</p></div></div>
{else}
<div class="p-6 space-y-6" style="max-width:1400px">

    {* ------------------------------------------------------------ *}
    {* Master-detail: Web admin groups                              *}
    {* ------------------------------------------------------------ *}
    <section data-testid="web-groups-section">
        <div class="flex items-center justify-between gap-4 mb-4" style="flex-wrap:wrap">
            <div>
                <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">Web admin groups</h1>
                <p class="text-sm text-muted m-0 mt-2">Permission flags and immunity for web panel groups. Total: {$web_group_count}.</p>
            </div>
        </div>

        {if $web_group_count == 0}
            {* #1228 + empty-state unification: first-run state. The CTA
               is gated on `permission_addgroup` (ADMIN_OWNER |
               ADMIN_ADD_GROUP) — the same flag the dispatcher gates the
               `Add a group` form on — so a user without that flag sees
               the body copy without the link they couldn't follow. *}
            <div class="empty-state" data-testid="admin-groups-empty-web" data-filtered="false">
                <span class="empty-state__icon" aria-hidden="true">
                    <i data-lucide="users-round" style="width:18px;height:18px"></i>
                </span>
                <h2 class="empty-state__title">No web admin groups yet</h2>
                <p class="empty-state__body">Web admin groups bundle panel permissions for a set of admins. Create one to assign multiple admins the same flags at once.</p>
                {if $permission_addgroup}
                    <div class="empty-state__actions">
                        <a class="btn btn--primary btn--sm"
                           href="?p=admin&amp;c=groups#add-group"
                           data-testid="admin-groups-empty-web-add">
                            <i data-lucide="plus" style="width:13px;height:13px"></i>
                            Add a web admin group
                        </a>
                    </div>
                {/if}
            </div>
        {else}
        <div class="grid gap-4 admin-groups-master-detail" style="grid-template-columns:minmax(16rem,1fr) 2fr">
            {* Left rail: clickable group list. The per-row member count
               and member preview both come from the parallel
               `$web_admins[index]` / `$web_admins_list[index]` arrays
               the page handler builds (same shape the legacy default
               template consumes; #1123 D1 collapses the two access
               styles when the master-detail layout becomes default). *}
            <div class="card" style="overflow:hidden" data-testid="group-list">
                {foreach from=$web_group_list item="group" name="web_group"}
                    <a class="admin-groups-master-detail__row flex items-center gap-3 p-4"
                       style="border-bottom:1px solid var(--border){if $selected_group && $selected_group.gid == $group.gid};background:var(--bg-muted){/if}"
                       href="?p=admin&c=groups&gid={$group.gid}"
                       data-testid="group-row"
                       data-id="{$group.gid}"
                       {if $selected_group && $selected_group.gid == $group.gid}aria-current="true"{/if}>
                        <div class="avatar"
                             style="width:2.25rem;height:2.25rem;background:var(--brand-600);font-size:var(--fs-base)">{$group.name|truncate:1:'':true|upper|escape}</div>
                        <div style="flex:1;min-width:0">
                            <div class="font-medium text-sm truncate">{$group.name|escape}</div>
                            <div class="text-xs text-muted">{$web_admins[$smarty.foreach.web_group.index]} member{if $web_admins[$smarty.foreach.web_group.index] != 1}s{/if}</div>
                            {if $web_admins_list[$smarty.foreach.web_group.index]}
                                <div class="text-xs text-faint truncate" style="margin-top:0.125rem">
                                    {foreach from=$web_admins_list[$smarty.foreach.web_group.index] item="web_admin" name="web_admin"}{if $smarty.foreach.web_admin.index > 0}, {/if}{if $smarty.foreach.web_admin.index < 3}{$web_admin.user|escape}{elseif $smarty.foreach.web_admin.index == 3}&hellip;{/if}{/foreach}
                                </div>
                            {/if}
                        </div>
                    </a>
                {/foreach}
            </div>

            {* Right pane: master-detail editor. *}
            {if $selected_group}
                <form class="card"
                      method="post"
                      action="?p=admin&c=groups&gid={$selected_group.gid}"
                      data-testid="group-detail"
                      onsubmit="return SbppGroupsSave(event);">
                    {csrf_field}
                    <input type="hidden" name="gid" value="{$selected_group.gid}">
                    <input type="hidden" name="type" value="web">
                    <div class="card__header">
                        <div>
                            <h3>{$selected_group.name|escape}</h3>
                            <p>{$selected_group.member_count} member{if $selected_group.member_count != 1}s{/if}</p>
                        </div>
                        {if $permission_deletegroup}
                            <button type="button"
                                    class="btn btn--ghost btn--sm"
                                    data-testid="group-delete"
                                    onclick="SbppGroupsDelete({$selected_group.gid}, '{$selected_group.name|escape:'javascript'}');">Delete group</button>
                        {/if}
                    </div>
                    <div class="card__body space-y-4">
                        {* Immunity input intentionally omitted for web admin groups:
                           `:prefix_groups` has no `immunity` column and
                           `api_groups_edit` (type=web) ignores the field. SourceMod
                           admin groups (`:prefix_srvgroups`) keep their immunity
                           surface on the per-group cards below. *}
                        <div>
                            <label class="label" for="group-name-input">Group name</label>
                            <input class="input"
                                   id="group-name-input"
                                   name="name"
                                   data-testid="group-name"
                                   value="{$selected_group.name|escape}"
                                   {if NOT $permission_editgroup}disabled{/if}>
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <label class="label m-0">Permission flags</label>
                                <span class="text-xs text-muted">{$selected_group.flags} bitmask</span>
                            </div>
                            <div class="grid gap-2 admin-groups-flag-grid"
                                 style="grid-template-columns:repeat(auto-fill,minmax(13rem,1fr))"
                                 data-testid="flag-grid">
                                {foreach from=$all_flags item="flag"}
                                    <label class="flex items-center gap-2 p-2"
                                           style="border:1px solid var(--border);border-radius:var(--radius-md);background:var(--bg-surface)">
                                        <input type="checkbox"
                                               name="flags[]"
                                               value="{$flag.value}"
                                               data-testid="flag-{$flag.name}"
                                               data-flag-value="{$flag.value}"
                                               {if ($selected_group.flags & $flag.value) == $flag.value}checked{/if}
                                               {if NOT $permission_editgroup}disabled{/if}>
                                        <span class="font-mono text-xs"
                                              style="background:var(--bg-muted);padding:0 0.25rem;border-radius:var(--radius-sm)">{$flag.name|escape}</span>
                                        <span class="text-xs truncate" title="{$flag.label|escape}">{$flag.label|escape}</span>
                                    </label>
                                {/foreach}
                            </div>
                        </div>

                        {if $permission_editgroup}
                            <div class="flex justify-end gap-2" style="border-top:1px solid var(--border);padding-top:0.75rem">
                                <a class="btn btn--ghost" href="?p=admin&c=groups">Discard</a>
                                <button class="btn btn--primary"
                                        type="submit"
                                        data-testid="group-save">Save changes</button>
                            </div>
                        {/if}
                    </div>
                </form>
            {else}
                <div class="card"><div class="card__body"><p class="text-muted m-0">Select a group on the left to edit its flags.</p></div></div>
            {/if}
        </div>
        {/if}
    </section>

    {* ------------------------------------------------------------ *}
    {* Server admin groups (SourceMod char-flag groups)             *}
    {* ------------------------------------------------------------ *}
    <section data-testid="server-admin-groups-section">
        <div class="flex items-center justify-between gap-4 mb-4" style="flex-wrap:wrap">
            <div>
                <h2 style="font-size:var(--fs-xl);font-weight:600;margin:0">Server admin groups</h2>
                <p class="text-sm text-muted m-0 mt-2">SourceMod admin groups (in-game flags). Total: {$server_admin_group_count}.</p>
            </div>
        </div>

        {if $server_admin_group_count == 0}
            {* #1228 + empty-state unification: first-run state. Same
               `permission_addgroup` gate as the web-admin-groups empty
               above — the dispatcher only allows `groups.add` for
               admins with `ADMIN_OWNER | ADMIN_ADD_GROUP`. *}
            <div class="empty-state" data-testid="admin-groups-empty-server-admin" data-filtered="false">
                <span class="empty-state__icon" aria-hidden="true">
                    <i data-lucide="shield-check" style="width:18px;height:18px"></i>
                </span>
                <h2 class="empty-state__title">No server admin groups yet</h2>
                <p class="empty-state__body">Server admin groups carry SourceMod char-flags and immunity. Create one to grant in-game admin powers to a set of admins.</p>
                {if $permission_addgroup}
                    <div class="empty-state__actions">
                        <a class="btn btn--primary btn--sm"
                           href="?p=admin&amp;c=groups#add-group"
                           data-testid="admin-groups-empty-server-admin-add">
                            <i data-lucide="plus" style="width:13px;height:13px"></i>
                            Add a server admin group
                        </a>
                    </div>
                {/if}
            </div>
        {else}
            <div class="grid gap-3" style="grid-template-columns:repeat(auto-fill,minmax(20rem,1fr))">
                {foreach from=$server_group_list item="group" name="server_admin_group"}
                    <article class="card" data-testid="server-admin-group-row" data-id="{$group.id}">
                        <div class="card__header">
                            <div>
                                <h3>{$group.name|escape}</h3>
                                <p>{$server_admins[$smarty.foreach.server_admin_group.index]} member{if $server_admins[$smarty.foreach.server_admin_group.index] != 1}s{/if} &middot; immunity {$group.immunity}</p>
                            </div>
                            <div class="flex gap-1">
                                {if $permission_editgroup}
                                    <a class="btn btn--ghost btn--sm" href="index.php?p=admin&c=groups&o=edit&type=srv&id={$group.id|escape:'url'}">Edit</a>
                                {/if}
                                {if $permission_deletegroup}
                                    <button type="button" class="btn btn--ghost btn--sm" onclick="SbppServerGroupsDelete({$group.id}, '{$group.name|escape:'javascript'}', 'srv');">Delete</button>
                                {/if}
                            </div>
                        </div>
                        <div class="card__body space-y-3">
                            <div>
                                <div class="text-xs font-semibold text-muted mb-2">Permissions</div>
                                {if $group.permissions}
                                    <div class="flex gap-1" style="flex-wrap:wrap">
                                        {foreach from=$group.permissions item=permission}
                                            <span class="chip">{$permission|escape}</span>
                                        {/foreach}
                                    </div>
                                {else}
                                    <p class="text-xs text-muted m-0"><em>None</em></p>
                                {/if}
                            </div>
                            {if $server_admins_list[$smarty.foreach.server_admin_group.index]}
                                <div>
                                    <div class="text-xs font-semibold text-muted mb-2">Members</div>
                                    <ul style="list-style:none;padding:0;margin:0" class="space-y-3">
                                        {foreach from=$server_admins_list[$smarty.foreach.server_admin_group.index] item="server_admin"}
                                            <li class="flex items-center justify-between gap-2 text-sm">
                                                <span class="truncate">{$server_admin.user|escape}</span>
                                                {if $permission_editadmin}
                                                    <a class="btn btn--ghost btn--sm" href="index.php?p=admin&c=admins&o=editgroup&id={$server_admin.aid|escape:'url'}">Edit</a>
                                                {/if}
                                            </li>
                                        {/foreach}
                                    </ul>
                                </div>
                            {/if}
                            {if $server_overrides_list[$smarty.foreach.server_admin_group.index]}
                                <div>
                                    <div class="text-xs font-semibold text-muted mb-2">Overrides</div>
                                    <ul style="list-style:none;padding:0;margin:0" class="space-y-3 text-xs">
                                        {foreach from=$server_overrides_list[$smarty.foreach.server_admin_group.index] item="override"}
                                            <li class="flex items-center justify-between gap-2">
                                                <span class="font-mono">{$override.type|escape}</span>
                                                <span class="truncate">{$override.name|escape}</span>
                                                <span class="font-mono text-muted">{$override.access|escape}</span>
                                            </li>
                                        {/foreach}
                                    </ul>
                                </div>
                            {/if}
                        </div>
                    </article>
                {/foreach}
            </div>
        {/if}
    </section>

    {* ------------------------------------------------------------ *}
    {* Server groups (groupings of game servers)                    *}
    {* ------------------------------------------------------------ *}
    <section data-testid="server-groups-section">
        <div class="flex items-center justify-between gap-4 mb-4" style="flex-wrap:wrap">
            <div>
                <h2 style="font-size:var(--fs-xl);font-weight:600;margin:0">Server groups</h2>
                <p class="text-sm text-muted m-0 mt-2">Groupings of game servers (no permission flags). Total: {$server_group_count}.</p>
            </div>
        </div>

        {if $server_group_count == 0}
            {* #1228 + empty-state unification: first-run state. Same
               `permission_addgroup` gate as the two empties above. *}
            <div class="empty-state" data-testid="admin-groups-empty-server" data-filtered="false">
                <span class="empty-state__icon" aria-hidden="true">
                    <i data-lucide="server-cog" style="width:18px;height:18px"></i>
                </span>
                <h2 class="empty-state__title">No server groups yet</h2>
                <p class="empty-state__body">Server groups bundle game servers together so you can assign admins to many servers at once. Create one to start grouping your servers.</p>
                {if $permission_addgroup}
                    <div class="empty-state__actions">
                        <a class="btn btn--primary btn--sm"
                           href="?p=admin&amp;c=groups#add-group"
                           data-testid="admin-groups-empty-server-add">
                            <i data-lucide="plus" style="width:13px;height:13px"></i>
                            Add a server group
                        </a>
                    </div>
                {/if}
            </div>
        {else}
            <div class="grid gap-3" style="grid-template-columns:repeat(auto-fill,minmax(20rem,1fr))">
                {foreach from=$server_list item="group" name="server_group"}
                    <article class="card" data-testid="server-group-row" data-id="{$group.gid}">
                        <div class="card__header">
                            <div>
                                <h3>{$group.name|escape}</h3>
                                <p>{$server_counts[$smarty.foreach.server_group.index]} server{if $server_counts[$smarty.foreach.server_group.index] != 1}s{/if}</p>
                            </div>
                            <div class="flex gap-1">
                                {if $permission_editgroup}
                                    <a class="btn btn--ghost btn--sm" href="index.php?p=admin&c=groups&o=edit&type=server&id={$group.gid|escape:'url'}">Edit</a>
                                {/if}
                                {if $permission_deletegroup}
                                    <button type="button" class="btn btn--ghost btn--sm" onclick="SbppServerGroupsDelete({$group.gid}, '{$group.name|escape:'javascript'}', 'server');">Delete</button>
                                {/if}
                            </div>
                        </div>
                        <div class="card__body">
                            <div class="text-xs text-muted">Servers populate via the legacy <code>LoadServerHostPlayersList</code> hook.</div>
                            <div id="servers_{$group.gid}" class="text-xs mt-2"></div>
                        </div>
                    </article>
                {/foreach}
            </div>
        {/if}
    </section>
</div>

<script>
{literal}
// --- Master-detail save / delete (B12) ---
// Vanilla JS; binds to the form's submit + the per-row delete buttons.
// All wire calls go through the existing `sb.api.call(Actions.Groups*)`
// surface; no new API handlers are introduced for this redesign.
function SbppGroupsSave(event) {
    event.preventDefault();
    var form = event.target;
    var gid = Number(form.querySelector('input[name="gid"]').value);
    var name = form.querySelector('input[name="name"]').value;
    var bitmask = 0;
    var checks = form.querySelectorAll('input[name="flags[]"]:checked');
    for (var i = 0; i < checks.length; i++) {
        bitmask |= Number(checks[i].value);
    }
    sb.api.call(Actions.GroupsEdit, {
        gid: gid,
        name: name,
        web_flags: bitmask,
        srv_flags: '',
        type: 'web'
    }).then(function (r) { applyApiResponse(r); });
    return false;
}

function SbppGroupsDelete(gid, name) {
    if (!confirm('Delete group "' + name + '"?')) return;
    sb.api.call(Actions.GroupsRemove, { gid: Number(gid), type: 'web' })
        .then(function (r) { applyApiResponse(r); });
}

function SbppServerGroupsDelete(gid, name, type) {
    if (!confirm('Delete group "' + name + '"?')) return;
    sb.api.call(Actions.GroupsRemove, { gid: Number(gid), type: String(type) })
        .then(function (r) { applyApiResponse(r); });
}
{/literal}
</script>
{/if}
