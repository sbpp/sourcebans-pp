{*
    SourceBans++ 2026 — page_admin_servers_list.tpl
    Bound to Sbpp\View\AdminServersListView (validated by SmartyTemplateRule).

    Card grid replacement for the legacy table at
    web/themes/default/page_admin_servers_list.tpl. Each row in the
    server_list array carries: sid, ip, port, icon, enabled, rcon_access
    (always present from admin.servers.php) plus mod_name (added in B15
    so the card can render the mod label without a second query).

    Live host / players / map are left as JS hydration targets — the
    sbpp2026 chrome doesn't load the legacy LoadServerHost helper from
    sourcebans.js, so until a Phase C JS lands, cards display the
    canonical IP:port and a neutral status pill. data-id="{$server.sid}"
    is the deterministic hook that hydration will key off (see the
    "Testability hooks" rule in the B15 plan).

    Remove flow uses Actions.ServersRemove via the inline script at the
    bottom; CSRF is auto-attached by sb.api.call from the
    meta[name=csrf-token] header (see web/scripts/api.js).
*}
<section class="page-section" data-testid="server-list-section">
    {if NOT $permission_list}
        <div class="card" data-testid="server-list-denied">
            <div class="card__body">
                <h3 style="margin:0 0 0.25rem">Access Denied</h3>
                <p class="text-sm text-muted m-0">You don't have permission to list servers.</p>
            </div>
        </div>
    {else}
        <div class="flex items-center justify-between gap-4 mb-4" style="flex-wrap:wrap">
            <div>
                <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">Servers</h1>
                <p class="text-sm text-muted m-0 mt-2">
                    <span data-testid="server-count" id="srvcount">{$server_count}</span>
                    registered {if $server_count == 1}server{else}servers{/if}.
                    Live host / map / player counts hydrate after first paint.
                </p>
            </div>
            {if $permission_addserver}
                <a class="btn btn--primary"
                   href="#addserver"
                   data-testid="server-list-add">
                    Add server
                </a>
            {/if}
        </div>

        {if $server_count == 0}
            <div class="card" data-testid="server-list-empty">
                <div class="card__body text-sm text-muted">
                    No servers configured yet.
                    {if $permission_addserver}Use the form below to add one.{/if}
                </div>
            </div>
        {else}
            <div class="grid gap-4"
                 style="grid-template-columns:repeat(auto-fill,minmax(20rem,1fr))"
                 data-testid="server-grid">
                {foreach from=$server_list item=server}
                    <article class="card{if !$server.enabled} card--disabled{/if}"
                             data-testid="server-tile"
                             data-id="{$server.sid}"
                             id="sid_{$server.sid}"
                             style="{if !$server.enabled}opacity:0.65;{/if}display:flex;flex-direction:column;gap:0.75rem;padding:1rem">
                        <header class="flex items-start gap-3">
                            <span aria-hidden="true"
                                  data-testid="server-tile-modicon"
                                  style="width:36px;height:36px;border-radius:var(--radius-md);background:var(--bg-muted);display:grid;place-items:center;flex-shrink:0;overflow:hidden">
                                {if $server.icon}
                                    <img src="images/games/{$server.icon|escape}"
                                         alt=""
                                         style="width:24px;height:24px;object-fit:contain"
                                         loading="lazy"
                                         onerror="this.style.display='none'">
                                {else}
                                    <span class="text-xs font-semibold text-muted">#{$server.sid}</span>
                                {/if}
                            </span>
                            <div style="flex:1;min-width:0">
                                <div class="font-semibold truncate" data-testid="server-tile-name">
                                    Server #{$server.sid}
                                </div>
                                <div class="font-mono text-xs text-muted truncate" data-testid="server-tile-host">
                                    {$server.ip|escape}:{$server.port}
                                </div>
                            </div>
                            {if !$server.enabled}
                                <span class="pill pill--offline" title="Disabled — hidden from public lists">Disabled</span>
                            {/if}
                        </header>

                        <dl class="text-xs text-muted" style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:0.25rem 0.5rem">
                            <dt style="font-weight:500;color:var(--text)">Mod</dt>
                            <dd style="margin:0">{if isset($server.mod_name)}{$server.mod_name|escape}{else}<span class="text-faint">unknown</span>{/if}</dd>
                            <dt style="font-weight:500;color:var(--text)">Players</dt>
                            <dd style="margin:0" data-hydrate="players">—</dd>
                            <dt style="font-weight:500;color:var(--text)">Map</dt>
                            <dd style="margin:0" data-hydrate="map">—</dd>
                        </dl>

                        <footer class="flex gap-1" style="border-top:1px solid var(--border);padding-top:0.75rem;flex-wrap:wrap">
                            {if $server.rcon_access}
                                <a class="btn btn--secondary btn--sm"
                                   data-testid="server-tile-rcon"
                                   href="index.php?p=admin&c=servers&o=rcon&id={$server.sid|escape:'url'}">
                                    RCON
                                </a>
                            {/if}
                            <a class="btn btn--secondary btn--sm"
                               data-testid="server-tile-admins"
                               href="index.php?p=admin&c=servers&o=admincheck&id={$server.sid|escape:'url'}">
                                Admins
                            </a>
                            {if $permission_editserver}
                                <a class="btn btn--ghost btn--sm"
                                   data-testid="server-tile-edit"
                                   href="index.php?p=admin&c=servers&o=edit&id={$server.sid|escape:'url'}">
                                    Edit
                                </a>
                            {/if}
                            {if $pemission_delserver}
                                <button type="button"
                                        class="btn btn--ghost btn--sm"
                                        data-testid="server-tile-delete"
                                        data-action="server-delete"
                                        data-sid="{$server.sid}"
                                        data-label="{$server.ip|escape}:{$server.port}"
                                        style="color:var(--danger);margin-left:auto">
                                    Delete
                                </button>
                            {/if}
                        </footer>
                    </article>
                {/foreach}
            </div>
        {/if}

        {if $permission_addserver}
            <div class="text-xs text-muted mt-4" data-testid="server-list-mapimg-hint">
                Need to upload a map screenshot? Drop it under
                <code class="font-mono">web/images/maps/</code> using the map name as the filename
                (e.g. <code class="font-mono">de_dust2.jpg</code>).
            </div>
        {/if}
    {/if}
</section>
{* Smarty default delimiters are { and }; the object literals below
   would otherwise be parsed as template tags. {literal}…{/literal}
   keeps the entire script body verbatim. *}
{literal}
<script>
(function () {
    'use strict';
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        var btn = t.closest('[data-action="server-delete"]');
        if (!btn) return;
        e.preventDefault();
        var sid = Number(btn.dataset.sid);
        var label = btn.dataset.label || ('Server #' + sid);
        if (!Number.isFinite(sid) || sid <= 0) return;
        if (!window.confirm('Delete ' + label + '?\n\nThis removes the server entry and any group/admin mappings. Bans logged from it are retained.')) {
            return;
        }
        var api = window.sb && window.sb.api;
        if (!api || !window.Actions) return;
        btn.disabled = true;
        api.call(window.Actions.ServersRemove, { sid: sid }).then(function (r) {
            if (!r || r.ok === false) {
                btn.disabled = false;
                if (r && r.error && window.SBPP && window.SBPP.showToast) {
                    window.SBPP.showToast({ kind: 'error', title: 'Delete failed', body: r.error.message || 'Unknown error' });
                }
                return;
            }
            // The handler returns { remove: 'sid_<id>', counter: { srvcount: <n> } };
            // mirror what applyApiResponse does in sourcebans.js without
            // dragging in the legacy module.
            var d = (r && r.data) || {};
            if (d.remove) {
                var node = document.getElementById(String(d.remove));
                if (node && node.parentNode) node.parentNode.removeChild(node);
            }
            if (d.counter && typeof d.counter.srvcount !== 'undefined') {
                var counter = document.getElementById('srvcount');
                if (counter) counter.textContent = String(d.counter.srvcount);
            }
            if (window.SBPP && window.SBPP.showToast) {
                window.SBPP.showToast({ kind: 'success', title: 'Server deleted', body: label });
            }
        });
    });
})();
</script>
{/literal}
