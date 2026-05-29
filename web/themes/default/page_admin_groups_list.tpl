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
{* #1266 — outer `.p-6` removed; the 1.5rem page inset now lives on
   `.admin-sidebar-shell` (the AdminTabs grid host). The `space-y-6`
   stays so the per-section vertical rhythm holds; the `max-width`
   cap is also unnecessary here because the outer shell already
   clamps to 1400px. *}
<div class="space-y-6">

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
               the body copy without the link they couldn't follow.

               #1239 cross-PR fix: target `&section=add` (not the
               `#add-group` anchor) — the add-group form is now its
               own ?section= route. *}
            <div class="empty-state" data-testid="admin-groups-empty-web" data-filtered="false">
                <span class="empty-state__icon" aria-hidden="true">
                    <i data-lucide="users-round" style="width:18px;height:18px"></i>
                </span>
                <h2 class="empty-state__title">No web admin groups yet</h2>
                <p class="empty-state__body">Web admin groups bundle panel permissions for a set of admins. Create one to assign multiple admins the same flags at once.</p>
                {if $permission_addgroup}
                    <div class="empty-state__actions">
                        <a class="btn btn--primary btn--sm"
                           href="?p=admin&amp;c=groups&amp;section=add"
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
                                    onclick="SbppGroupsDelete({$selected_group.gid}, '{$selected_group.name|escape:'javascript'}', this);">Delete group</button>
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
                                {* #1258: `data-testid="flag-bitmask"` lets the page-tail JS
                                   below (and future E2E specs) anchor on the contract instead
                                   of visible copy. SSR is the source of truth for the initial
                                   paint; the listener re-folds the OR-sum on each `change`. *}
                                <span class="text-xs text-muted" data-testid="flag-bitmask">{$selected_group.flags} bitmask</span>
                            </div>
                            {* #1258: per-flag rows are bare `<label class="flex items-center
                               gap-2">` — no inline border / background / radius — so the grid
                               reads as a list of toggle rows inside the parent `<form
                               class="card">`, matching the inline-checkbox shape from
                               page_admin_settings_settings.tpl's "Enable debug mode" row. *}
                            <div class="grid gap-2 admin-groups-flag-grid"
                                 style="grid-template-columns:repeat(auto-fill,minmax(13rem,1fr))"
                                 data-testid="flag-grid">
                                {foreach from=$all_flags item="flag"}
                                    <label class="flex items-center gap-2">
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
               admins with `ADMIN_OWNER | ADMIN_ADD_GROUP`.

               #1239 cross-PR fix: target `&section=add` (not the
               `#add-group` anchor) — the add-group form is now its
               own ?section= route. *}
            <div class="empty-state" data-testid="admin-groups-empty-server-admin" data-filtered="false">
                <span class="empty-state__icon" aria-hidden="true">
                    <i data-lucide="shield-check" style="width:18px;height:18px"></i>
                </span>
                <h2 class="empty-state__title">No server admin groups yet</h2>
                <p class="empty-state__body">Server admin groups carry SourceMod char-flags and immunity. Create one to grant in-game admin powers to a set of admins.</p>
                {if $permission_addgroup}
                    <div class="empty-state__actions">
                        <a class="btn btn--primary btn--sm"
                           href="?p=admin&amp;c=groups&amp;section=add"
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
                                    <button type="button" class="btn btn--ghost btn--sm" onclick="SbppServerGroupsDelete({$group.id}, '{$group.name|escape:'javascript'}', 'srv', this);">Delete</button>
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
               `permission_addgroup` gate as the two empties above.

               #1239 cross-PR fix: target `&section=add` (not the
               `#add-group` anchor) — the add-group form is now its
               own ?section= route. *}
            <div class="empty-state" data-testid="admin-groups-empty-server" data-filtered="false">
                <span class="empty-state__icon" aria-hidden="true">
                    <i data-lucide="server-cog" style="width:18px;height:18px"></i>
                </span>
                <h2 class="empty-state__title">No server groups yet</h2>
                <p class="empty-state__body">Server groups bundle game servers together so you can assign admins to many servers at once. Create one to start grouping your servers.</p>
                {if $permission_addgroup}
                    <div class="empty-state__actions">
                        <a class="btn btn--primary btn--sm"
                           href="?p=admin&amp;c=groups&amp;section=add"
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
                                    <button type="button" class="btn btn--ghost btn--sm" onclick="SbppServerGroupsDelete({$group.gid}, '{$group.name|escape:'javascript'}', 'server', this);">Delete</button>
                                {/if}
                            </div>
                        </div>
                        {*
                            #1406: per-group server-card stack. Re-introduces
                            the per-card hydration surface dropped at #1404
                            (which removed the literal "Servers populate via
                            the legacy LoadServerHostPlayersList hook."
                            placeholder + the sibling `<div id="servers_{gid}">`
                            slot fed by a dead `<script>` echo). The modern
                            shape ships one `[data-testid="server-tile"]` per
                            bound server inside a `[data-server-hydrate="auto"]`
                            container; the shared
                            `web/scripts/server-tile-hydrate.js` helper
                            auto-runs on first paint, fires
                            `Actions.ServersHostPlayers` per tile, and patches
                            the live hostname into the inner
                            `[data-testid="server-host"]` slot via
                            `sb.setHTML`. The bare `IP:port` stays as the SSR
                            / cache-cold / no-JS fallback (also the
                            `data-fallback` attribute the helper re-paints on
                            probe failure).

                            Minimal-integration shape (mirror of the dashboard
                            widget #1375): the card body only ships the
                            hostname slot — the rest of the helper's surface
                            (status pill / map / players / players bar /
                            map-img / refresh / toggle / players panel) is
                            feature-detected and silently no-ops on tiles
                            that don't ship those testid hooks. SourceQueryCache
                            on the server side coalesces back-to-back probes
                            per (ip, port) for ~30s, so the extra per-card
                            round-trips are absorbed cheaply even on a panel
                            with many groups.

                            `data-trunchostname="40"` caps the live hostname
                            server-side so it fits this cramped per-group
                            card body. (The dashboard widget dropped its cap
                            to `0` in #1487 — its column is fluid and CSS
                            sizes the cut; the public servers list runs at
                            70 on its full-width cards.)
                        *}
                        <div class="card__body">
                            {* Deliberate deviation from the shared `.empty-state` chrome
                               documented under "Empty states" in AGENTS.md: the cramped
                               per-card body (this lives inside a 20rem-min grid column,
                               not a full-width page region) doesn't have the vertical
                               room for the icon + title + body + CTA stack. The shared
                               chrome is right for page-level empties; an inline one-liner
                               is right for an empty CELL inside a populated card. #1406. *}
                            {if empty($group.servers)}
                                <p class="text-xs text-muted m-0" data-testid="server-group-empty">
                                    <em>No servers bound to this group yet.</em>
                                </p>
                            {else}
                                <ul class="space-y-2"
                                    style="list-style:none;padding:0;margin:0"
                                    data-testid="server-group-server-list"
                                    data-server-hydrate="auto"
                                    data-trunchostname="40">
                                    {*
                                        Disabled servers stay visible — the bound-but-disabled
                                        relationship is useful operator context — but ride the
                                        `data-server-skip="1"` short-circuit so the shared
                                        hydration helper's `loadTile()` returns early instead of
                                        firing a pointless `Actions.ServersHostPlayers` round-trip
                                        against a server the panel already knows is offline by
                                        config. Mirrors `page_admin_servers_list.tpl`'s contract.
                                        The visible "Disabled" pill (testid
                                        `server-disabled-tag`) is the affordance that explains
                                        why no hostname appears on the row — without it the row
                                        would silently stay at the SSR `IP:port` fallback and an
                                        admin would reasonably wonder whether the probe failed.
                                        The pill is also gated server-side, NOT a CSS-only badge
                                        — a third-party theme stripping the rule still surfaces
                                        the affordance because the markup is in the DOM.
                                    *}
                                    {foreach from=$group.servers item="server"}
                                        <li class="flex items-center gap-2"
                                            data-testid="server-tile"
                                            data-id="{$server.sid}"
                                            {if !$server.enabled}data-server-skip="1"{/if}
                                            style="padding:0.375rem 0.5rem;border-radius:var(--radius-md);background:var(--bg-muted);min-width:0{if !$server.enabled};opacity:0.65{/if}">
                                            <i data-lucide="server" aria-hidden="true" style="width:14px;height:14px;flex-shrink:0;color:var(--text-faint)"></i>
                                            <div class="flex-1" style="min-width:0">
                                                <div class="font-medium text-sm truncate"
                                                     data-testid="server-host"
                                                     data-fallback="{$server.ip|escape}:{$server.port}">{$server.ip|escape}:{$server.port}</div>
                                                <div class="text-xs text-faint font-mono truncate">{$server.ip|escape}:{$server.port}</div>
                                            </div>
                                            {if !$server.enabled}
                                                <span class="pill pill--offline"
                                                      data-testid="server-disabled-tag"
                                                      title="Disabled — hidden from public lists and skipped by the per-card hydration probe"
                                                      style="flex-shrink:0">Disabled</span>
                                            {/if}
                                        </li>
                                    {/foreach}
                                </ul>
                            {/if}
                        </div>
                    </article>
                {/foreach}
            </div>
            {*
                #1406: per-tile A2S hydration for the Server Groups
                card stacks. The shared helper auto-runs on first
                paint for every `[data-server-hydrate="auto"]`
                container above, fires `Actions.ServersHostPlayers`
                per tile, and patches the live hostname into each
                row's `[data-testid="server-host"]` slot. The helper
                feature-detects every optional `[data-testid]` cell,
                so a tile that only ships the hostname slot silently
                hydrates that one cell (same minimal-integration
                shape the dashboard Servers widget rides — see
                page_dashboard.tpl for the precedent). `defer` lets
                the rest of the page paint before the helper boots;
                auto-run still fires once it does (the helper branches
                on document.readyState).
            *}
            <script src="./scripts/server-tile-hydrate.js" defer></script>
        {/if}
    </section>
</div>

<script>
{literal}
// --- Master-detail save / delete (B12) ---
// Vanilla JS; binds to the form's submit + the per-row delete buttons.
// All wire calls go through the existing `sb.api.call(Actions.Groups*)`
// surface; no new API handlers are introduced for this redesign.

/**
 * OR-fold every checked `input[name="flags[]"]` under `root` and return
 * the unsigned 32-bit interpretation of the result.
 *
 * JS bitwise operators (`|=`, `&`, `^`, `<<`, `>>`) coerce both operands
 * to signed Int32 via `ToInt32` (ECMAScript), so the moment any flag
 * value has bit 31 set (e.g. `ADMIN_UNBAN_GROUP_BANS = 2147483648`,
 * `ALL_WEB = 4294966783` — see `web/configs/permissions/web.json`) the
 * OR-fold result is interpreted as negative. The `>>> 0` (unsigned
 * right-shift by zero) idiom converts that signed Int32 back to its
 * unsigned 32-bit interpretation in one of the few JS-spec-blessed
 * paths to a true uint32. Issue #1272.
 *
 * Both compute sites — `SbppGroupsSave` (the actual save path) AND the
 * grid's `change` listener (the live preview) — go through this helper
 * so they can never drift on the coercion rule.
 *
 * @param {Element} root
 * @returns {number} Unsigned 32-bit OR-sum of the checked flags.
 */
function SbppFoldFlags(root) {
    var bitmask = 0;
    var checks = root.querySelectorAll('input[name="flags[]"]:checked');
    for (var i = 0; i < checks.length; i++) {
        var input = /** @type {HTMLInputElement} */ (checks[i]);
        bitmask |= Number(input.dataset.flagValue || input.value);
    }
    return bitmask >>> 0;
}

/**
 * Inline replacement for the legacy `applyApiResponse` global from
 * `web/scripts/sourcebans.js` (deleted at #1123 D1, see AGENTS.md
 * "Anti-patterns"). Mirrors the shape from `page_admin_groups_add.tpl`'s
 * `SbppGroupsAdd` callback (#1310): error toast on `r.ok === false`,
 * success toast from `r.data.message`, auto-reload on `r.data.reload`,
 * and — for handlers whose envelope only carries `message.redir` (e.g.
 * `groups.remove`, which does NOT set `data.reload`) — navigate there
 * after the toast so the master-detail editor stops pointing at the
 * row that just got deleted.
 *
 * `sb.api.call` already honours `r.redirect` (top-level navigation
 * envelope) by setting `window.location.href` itself, so we just bail
 * if the envelope had it set.
 *
 * @param {object|null|undefined} r The envelope from `sb.api.call`.
 * @param {{defaultTitle?: string}} [opts]
 */
function SbppGroupsApplyResponse(r, opts) {
    if (!r) return;
    if (r.redirect) return;

    var fallback = (opts && opts.defaultTitle) || 'Done';

    if (r.ok === false) {
        var em = (r.error && r.error.message) || 'Unknown error';
        if (window.SBPP && typeof window.SBPP.showToast === 'function') {
            window.SBPP.showToast({ kind: 'error', title: 'Error', body: em });
        } else if (window.sb && window.sb.message) {
            window.sb.message.error('Error', em);
        }
        return;
    }

    var data = r.data || {};
    var msg = data.message || {};
    var title = msg.title || fallback;
    var body = msg.body || '';

    if (window.SBPP && typeof window.SBPP.showToast === 'function') {
        window.SBPP.showToast({ kind: 'success', title: title, body: body });
    } else if (window.sb && window.sb.message) {
        window.sb.message.success(title, body, msg.redir || '');
    }

    if (data.reload) {
        setTimeout(function () { window.location.reload(); }, 1500);
    } else if (msg.redir) {
        setTimeout(function () { window.location.href = msg.redir; }, 1500);
    }
}

// Local wrapper around window.SBPP.setBusy that falls back to `disabled`
// so a stripped-down theme still gates against double-clicks.
function SbppGroupsSetBusy(btn, busy) {
    if (!btn) return;
    var S = window.SBPP;
    if (S && typeof S.setBusy === 'function') S.setBusy(btn, busy);
    else btn.disabled = busy === undefined ? true : !!busy;
}

function SbppGroupsSave(event) {
    event.preventDefault();
    var form = event.target;
    var gid = Number(form.querySelector('input[name="gid"]').value);
    var name = form.querySelector('input[name="name"]').value;
    var bitmask = SbppFoldFlags(form);
    var submitBtn = form.querySelector('[data-testid="group-save"]');
    SbppGroupsSetBusy(submitBtn, true);
    sb.api.call(Actions.GroupsEdit, {
        gid: gid,
        name: name,
        web_flags: bitmask,
        srv_flags: '',
        type: 'web'
    }).then(function (r) {
        SbppGroupsSetBusy(submitBtn, false);
        SbppGroupsApplyResponse(r, { defaultTitle: 'Group updated' });
    });
    return false;
}

function SbppGroupsDelete(gid, name, btn) {
    if (!confirm('Delete group "' + name + '"?')) return;
    SbppGroupsSetBusy(btn, true);
    sb.api.call(Actions.GroupsRemove, { gid: Number(gid), type: 'web' })
        .then(function (r) {
            // Leave the button busy on success — the apply handler reloads /
            // navigates within 1.5s and re-enabling it would let the operator
            // queue a second delete on the now-stale row.
            if (r && r.ok && (r.data && (r.data.reload || (r.data.message && r.data.message.redir)))) {
                SbppGroupsApplyResponse(r, { defaultTitle: 'Group deleted' });
                return;
            }
            SbppGroupsSetBusy(btn, false);
            SbppGroupsApplyResponse(r, { defaultTitle: 'Group deleted' });
        });
}

function SbppServerGroupsDelete(gid, name, type, btn) {
    if (!confirm('Delete group "' + name + '"?')) return;
    SbppGroupsSetBusy(btn, true);
    sb.api.call(Actions.GroupsRemove, { gid: Number(gid), type: String(type) })
        .then(function (r) {
            if (r && r.ok && (r.data && (r.data.reload || (r.data.message && r.data.message.redir)))) {
                SbppGroupsApplyResponse(r, { defaultTitle: 'Group deleted' });
                return;
            }
            SbppGroupsSetBusy(btn, false);
            SbppGroupsApplyResponse(r, { defaultTitle: 'Group deleted' });
        });
}

// --- Live bitmask preview (#1258) ---
// Re-fold the OR-sum of the grid's checked `data-flag-value`s on every
// `change` and write it into `[data-testid="flag-bitmask"]`. SSR stays
// the source of truth for the initial paint; the listener only mirrors
// what `SbppGroupsSave` already does at submit time so the operator
// sees the new value before saving (no Save + reload round-trip).
//
// No `// @ts-check` here because the file is rendered by Smarty;
// ts-check only runs against `.js` sources in `web/scripts`. The
// shape mirrors the inline handlers in `page_comms.tpl` /
// `page_admin_bans_submissions.tpl`.
(function () {
    'use strict';

    var grid = document.querySelector('[data-testid="flag-grid"]');
    var preview = document.querySelector('[data-testid="flag-bitmask"]');
    if (!grid || !preview) return;

    grid.addEventListener('change', function (event) {
        var target = /** @type {HTMLInputElement|null} */ (event.target);
        if (!target || !target.matches || !target.matches('input[name="flags[]"]')) return;

        preview.textContent = SbppFoldFlags(grid) + ' bitmask';
    });
})();
{/literal}
</script>
{/if}
