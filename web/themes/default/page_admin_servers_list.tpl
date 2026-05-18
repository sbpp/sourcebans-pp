{*
    SourceBans++ 2026 — page_admin_servers_list.tpl
    Bound to Sbpp\View\AdminServersListView (validated by SmartyTemplateRule).

    Card grid replacement for the legacy table at
    web/themes/default/page_admin_servers_list.tpl. Each row in the
    server_list array carries: sid, ip, port, icon, enabled, rcon_access
    (always present from admin.servers.php) plus mod_name (added in B15
    so the card can render the mod label without a second query).

    Live host / players / map are hydrated client-side by
    web/scripts/server-tile-hydrate.js (#1313). The helper walks
    every server-tile inside the data-server-hydrate="auto" grid and
    fires Actions.ServersHostPlayers per tile, patching the same
    data-testid cells the public page uses (server-status,
    server-map, server-players, server-host, plus data-players-bar).
    Tiles for disabled servers carry data-server-skip="1" so the helper
    leaves them at the server-rendered placeholder — there's no point
    poking a UDP socket for a server the panel just told you is offline
    by config.

    Pre-#1313 the cells carried inert data-hydrate="map" /
    data-hydrate="players" placeholders with no script behind them and
    the values stayed at the em-dash forever; the testid rename plus
    the script include is the load-bearing fix. The deterministic hook
    the hydration helper keys off is data-id="..." (see the "Testability
    hooks" rule in #1123).

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
                   href="index.php?p=admin&amp;c=servers&amp;section=add"
                   data-testid="server-list-add">
                    Add server
                </a>
            {/if}
        </div>

        {if $server_count == 0}
            <div class="card" data-testid="server-list-empty">
                <div class="card__body text-sm text-muted">
                    No servers configured yet.
                    {if $permission_addserver}<a href="index.php?p=admin&amp;c=servers&amp;section=add">Add one</a> to get started.{/if}
                </div>
            </div>
        {else}
            {*
                data-server-hydrate="auto" opts the grid into the shared
                hydration helper (web/scripts/server-tile-hydrate.js).
                The helper auto-runs on first paint, walks every
                [data-testid="server-tile"] child, and patches the
                live cells (status pill / map / players / hostname /
                players bar) per the response from
                Actions.ServersHostPlayers.

                `.servers-grid` (theme.css, #1316) is shared with
                `page_servers.tpl` so the public + admin Server
                Management surfaces look consistent and a theme fork
                can override the column min-width in one place. The
                class supersedes the pre-#1316 inline
                `grid-template-columns` style; see the theme.css
                comment above the `.servers-grid` rule for the
                breakpoint reasoning.
            *}
            <div class="servers-grid"
                 data-testid="server-grid"
                 data-server-hydrate="auto"
                 data-trunchostname="70">
                {foreach from=$server_list item=server}
                    <article class="card{if !$server.enabled} card--disabled{/if}"
                             data-testid="server-tile"
                             data-id="{$server.sid}"
                             {if !$server.enabled}data-server-skip="1"{else}data-status="loading"{/if}
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
                                {*
                                    server-tile-name carries the live hostname once
                                    the A2S probe lands. server-tile-hydrate.js
                                    patches the inner [data-testid="server-host"]
                                    via sb.setHTML; the JSON action
                                    htmlspecialchars()'s the value server-side
                                    (web/api/handlers/servers.php), so setHTML is
                                    safe. The fallback text + data-fallback attr
                                    is the canonical IP:port — restored when the
                                    UDP probe fails so the card stays readable.
                                *}
                                <div class="font-semibold truncate"
                                     data-testid="server-tile-name">
                                    <span data-testid="server-host"
                                          data-fallback="{$server.ip|escape}:{$server.port}">{$server.ip|escape}:{$server.port}</span>
                                </div>
                                <div class="font-mono text-xs text-muted truncate" data-testid="server-tile-host">
                                    {$server.ip|escape}:{$server.port}
                                </div>
                            </div>
                            {if $server.enabled}
                                {*
                                    Status pill mirrors the public servers-list
                                    contract: starts at "Loading" with a loader
                                    icon, flips to online (check-circle-2) /
                                    offline (x-circle) once
                                    server-tile-hydrate.js gets a response. The
                                    pill is also the deterministic anchor the e2e
                                    spec keys off (no hover-only chrome).
                                *}
                                <span class="pill pill--offline"
                                      data-testid="server-status"
                                      aria-live="polite">
                                    <i data-lucide="loader" style="width:10px;height:10px"></i>
                                    <span data-status-label>Loading</span>
                                </span>
                            {else}
                                <span class="pill pill--offline" title="Disabled — hidden from public lists">Disabled</span>
                            {/if}
                        </header>

                        <dl class="text-xs text-muted" style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:0.25rem 0.5rem">
                            <dt style="font-weight:500;color:var(--text)">Mod</dt>
                            <dd style="margin:0">{if isset($server.mod_name)}{$server.mod_name|escape}{else}<span class="text-faint">unknown</span>{/if}</dd>
                            <dt style="font-weight:500;color:var(--text)">Players</dt>
                            <dd style="margin:0" data-testid="server-players">—</dd>
                            <dt style="font-weight:500;color:var(--text)">Map</dt>
                            <dd style="margin:0" data-testid="server-map">—</dd>
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
                            {if $server.enabled}
                                {*
                                    Refresh button is wired by
                                    web/scripts/server-tile-hydrate.js — clicking it
                                    re-fires Actions.ServersHostPlayers for this
                                    tile. Hidden on disabled servers (the helper
                                    skips them anyway). Starts `disabled` so a
                                    click before the helper boots is a no-op
                                    and the bootstrap probe (#1311) can re-enable
                                    on settle — same gate the public servers list
                                    uses.
                                *}
                                <button type="button"
                                        class="btn btn--ghost btn--sm"
                                        data-testid="server-refresh"
                                        data-action="refresh"
                                        title="Re-query this server"
                                        aria-label="Refresh server status"
                                        disabled>
                                    <i data-lucide="refresh-cw" style="width:13px;height:13px"></i>
                                </button>
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
{*
    Per-tile A2S hydration (#1313): the shared helper in
    web/scripts/server-tile-hydrate.js auto-runs on first paint for
    the [data-server-hydrate="auto"] grid above and patches the
    live cells (status pill / map / players / hostname / refresh).
    The defer attribute lets the rest of the page paint before the
    helper boots; auto-run still fires once it does (the helper
    branches on document.readyState).
*}
<script src="./scripts/server-tile-hydrate.js" defer></script>
{* Smarty default delimiters are { and }; the object literals below
   would otherwise be parsed as template tags. {literal}…{/literal}
   keeps the entire script body verbatim. *}
{literal}
<script>
(function () {
    'use strict';
    /**
     * Flip the busy / loading state on a triggered action button. Calls
     * window.SBPP.setBusy when present (theme.js owns the spinner CSS
     * contract) and falls back to plain `disabled` so third-party themes
     * that strip theme.js still gate against double-clicks.
     */
    function setBusy(btn, busy) {
        if (!btn) return;
        var S = window.SBPP;
        if (S && typeof S.setBusy === 'function') S.setBusy(btn, busy);
        else btn.disabled = busy === undefined ? true : !!busy;
    }
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
        setBusy(btn, true);
        api.call(window.Actions.ServersRemove, { sid: sid }).then(function (r) {
            if (!r || r.ok === false) {
                setBusy(btn, false);
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
