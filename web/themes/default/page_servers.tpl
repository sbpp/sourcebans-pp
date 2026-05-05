{*
    SourceBans++ 2026 — page_servers.tpl

    Public servers list. Replaces the A1 stub. Pair:
    web/pages/page.servers.php + web/includes/View/ServersView.php
    (#1123 B5).

    Why this differs from handoff/pages/servers.tpl
    -----------------------------------------------
    The handoff card grid is treated as the visual reference for the
    layout shell (mod-letter avatar, hostname + ip:port, status pill,
    players bar, map row), not as a 1:1 markup port:

      * The handoff renders an "Add server" button and per-row "RCON" /
        "Players" admin shortcuts gated on SourceMod char-flags
        (`$user.srv_flags|strpos:'z'`). The legacy public servers page
        carries neither — both belong on the admin servers list page
        (#1123 B15). Reproducing them here would require Perms / SM
        char-flag plumbing the public View doesn't need today, so
        they're intentionally cut.
      * The handoff's status (online/offline) is set server-side from a
        precomputed `$s.online`. We don't have that cheaply: each card
        starts in a loading state and progressively flips to
        online/offline as the inline `sb.api.call(
        Actions.ServersHostPlayers, …)` per-card response lands. This
        mirrors the legacy `LoadServerHost(...)` UDP-poll flow.

    Live data flow
    --------------
    SourceQuery (xpaw/php-source-query-class) is a UDP probe — the
    server may be down, behind NAT, or rate-limiting. The handler
    returns `{ error: 'connect', ip, port, is_owner }` on failure
    instead of throwing, so each card has to handle three terminal
    states: `loading` (initial), `online` (data came back clean),
    `offline` (handler returned `error: 'connect'` or the call itself
    failed). Status is persisted in the `data-status` attribute so
    Playwright (and humans) can assert state without reading computed
    CSS — see #1123 issue body's "State in attributes, not just
    styling" rule.

    Auto-expand
    -----------
    Mirrors the legacy `?p=servers&s={index}` pattern: when the page
    handler sets `opened_server >= 0`, the matching card auto-expands
    its player list once the live response lands. The expansion fetches
    no additional data — the same `Actions.ServersHostPlayers`
    response already includes `player_list[]`.

    Per-row JS without ./scripts/sourcebans.js
    ------------------------------------------
    sourcebans.js is dropped at #1123 D1, so we can't use the legacy
    `LoadServerHost()` / `InitAccordion()` helpers; they'd be undefined
    here. The card markup carries `data-sid` / `data-index` /
    `data-status` and the inline `<script>` walks
    `[data-testid="server-tile"]` directly. `sb.api.call` and the
    `Actions.*` constants come from the chrome's
    api.js + api-contract.js (loaded by core/header.tpl).

    Testability hooks (per #1123 issue + B5 brief)
    ----------------------------------------------
      data-testid="servers-list"   — outer container
      data-testid="server-tile"    — each card (one per server)
      data-testid="server-toggle"  — expand/collapse trigger
      data-testid="server-connect" — steam:// connect button
      data-id=$server.sid          — stable integer (NOT the index)
      data-status="loading|online|offline" — terminal state on each card
      data-index=$server.index     — 0-based offset for the dashboard
                                     ?p=servers&s=N deep link
*}
<div class="p-6 space-y-4" style="max-width:1400px;margin:0 auto;width:100%">

    <header class="flex items-end justify-between gap-3" style="flex-wrap:wrap">
        <div>
            <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">Servers</h1>
            <p class="text-sm text-muted m-0 mt-2"
               data-testid="servers-summary"
               data-total="{$server_list|@count}">
                {$server_list|@count} configured
                <span data-online-count>&middot; <span data-online-num>0</span> online</span>
            </p>
        </div>
        {if $IN_SERVERS_PAGE && $access_bans}
            <p class="text-xs text-muted m-0"
               role="note"
               data-testid="servers-rcon-hint"
               style="max-width:24rem;text-align:right">
                <i data-lucide="info" style="width:12px;height:12px;vertical-align:-2px"></i>
                Right-click a player on an expanded card to kick, ban, or message them.
            </p>
        {/if}
    </header>

    {if $server_list|@count == 0}
        <div class="card" data-testid="servers-empty">
            <div class="card__body text-sm text-muted">
                No servers are configured yet.
            </div>
        </div>
    {else}
    <div class="grid gap-4"
         data-testid="servers-list"
         data-opened-index="{$opened_server}"
         style="grid-template-columns:repeat(auto-fill,minmax(20rem,1fr))">
        {foreach $server_list as $server}
        <article class="card"
                 data-testid="server-tile"
                 data-id="{$server.sid}"
                 data-index="{$server.index}"
                 data-status="loading"
                 data-expanded="false">
            <div class="card__body">
                <div class="flex items-start gap-3">
                    <span class="server-tile__mod"
                          aria-hidden="true"
                          style="width:36px;height:36px;border-radius:var(--radius-sm);background:var(--bg-muted);display:grid;place-items:center;flex-shrink:0;overflow:hidden">
                        {if $server.icon}
                            <img src="images/games/{$server.icon}"
                                 alt=""
                                 width="20"
                                 height="20"
                                 style="display:block">
                        {else}
                            <i data-lucide="server" style="width:18px;height:18px;color:var(--text-muted)"></i>
                        {/if}
                    </span>
                    <div style="flex:1;min-width:0">
                        <div class="font-semibold truncate"
                             data-testid="server-host"
                             data-fallback="{$server.ip}:{$server.port}"
                             title="{$server.ip}:{$server.port}">
                            {$server.ip}:{$server.port}
                        </div>
                        <div class="font-mono text-xs text-faint truncate" style="margin-top:0.125rem">
                            {if $server.mod}{$server.mod} &middot; {/if}{$server.ip}:{$server.port}
                        </div>
                    </div>
                    <span class="pill pill--offline"
                          data-testid="server-status"
                          aria-live="polite">
                        <i data-lucide="loader" style="width:10px;height:10px"></i>
                        <span data-status-label>Loading</span>
                    </span>
                </div>

                <dl class="grid gap-2 text-xs"
                    style="grid-template-columns:auto 1fr;align-items:center;margin-top:1rem;margin-bottom:0">
                    <dt class="text-muted flex items-center gap-1" style="margin:0">
                        <i data-lucide="map" style="width:13px;height:13px"></i> Map
                    </dt>
                    <dd class="font-mono text-faint" data-testid="server-map" style="margin:0">&mdash;</dd>

                    <dt class="text-muted flex items-center gap-1" style="margin:0">
                        <i data-lucide="users" style="width:13px;height:13px"></i> Players
                    </dt>
                    <dd class="tabular-nums font-medium"
                        data-testid="server-players"
                        style="margin:0;color:var(--text)">&mdash;</dd>
                </dl>

                <div class="server-tile__bar"
                     aria-hidden="true"
                     style="margin-top:0.5rem;height:6px;border-radius:var(--radius-full);background:var(--bg-muted);overflow:hidden">
                    <div data-players-bar
                         style="height:100%;background:var(--brand-600);width:0%;transition:width .25s ease"></div>
                </div>

                <div class="flex gap-2"
                     style="margin-top:1rem;border-top:1px solid var(--border);padding-top:0.75rem">
                    <a class="btn btn--primary btn--sm"
                       data-testid="server-connect"
                       href="steam://connect/{$server.dns}:{$server.port}">
                        <i data-lucide="play" style="width:13px;height:13px"></i>
                        Connect
                    </a>
                    <button type="button"
                            class="btn btn--secondary btn--sm"
                            data-testid="server-toggle"
                            data-action="toggle-players"
                            aria-expanded="false"
                            aria-controls="server-players-{$server.sid}"
                            disabled>
                        <i data-lucide="chevron-down" style="width:13px;height:13px"></i>
                        Players
                    </button>
                    <button type="button"
                            class="btn btn--ghost btn--sm"
                            data-testid="server-refresh"
                            data-action="refresh"
                            title="Re-query this server"
                            aria-label="Refresh server status">
                        <i data-lucide="refresh-cw" style="width:13px;height:13px"></i>
                    </button>
                </div>
            </div>

            <div id="server-players-{$server.sid}"
                 class="server-tile__players"
                 data-testid="server-players-panel"
                 hidden
                 style="border-top:1px solid var(--border);padding:0.75rem 1.25rem">
                <p class="text-xs text-muted m-0" data-empty-message>No players currently connected.</p>
                <ul class="m-0" data-player-list style="list-style:none;padding:0;display:none"></ul>
            </div>
        </article>
        {/foreach}
    </div>
    {/if}
</div>

{*
    Inline initializer — finds every server tile, fires one
    sb.api.call per card, and patches the DOM in place. No external
    deps beyond the chrome's api.js + sb.js. We avoid touching
    web/scripts/* per the Phase B "NEVER touch" list, so the wiring
    lives in the template that owns it.

    Variables interpolated by Smarty are restricted to numeric
    `opened_server` (cast to int by the View) and the `Actions`
    JS-side namespace from api-contract.js — no user input flows
    through this <script>. Wrapped in {literal} so Smarty doesn't try
    to parse the JS object literals.
*}
<script>
{literal}
(function () {
    'use strict';
    if (typeof sb === 'undefined' || !sb || !sb.api || typeof Actions === 'undefined') {
        return;
    }

    /** @type {NodeListOf<HTMLElement>} */
    var tiles = document.querySelectorAll('[data-testid="server-tile"]');
    if (tiles.length === 0) return;

    var listEl = document.querySelector('[data-testid="servers-list"]');
    var summary = document.querySelector('[data-testid="servers-summary"]');
    var openedIndex = listEl ? Number(listEl.getAttribute('data-opened-index')) : -1;
    var onlineCount = 0;

    function updateOnlineCount(delta) {
        onlineCount += delta;
        if (summary) {
            var num = summary.querySelector('[data-online-num]');
            if (num) num.textContent = String(onlineCount);
        }
    }

    /**
     * @param {HTMLElement} tile
     * @param {string} status loading|online|offline
     * @param {string} label
     */
    function setStatus(tile, status, label) {
        var prev = tile.getAttribute('data-status');
        tile.setAttribute('data-status', status);
        var pill = tile.querySelector('[data-testid="server-status"]');
        if (pill) {
            pill.classList.remove('pill--online', 'pill--offline');
            pill.classList.add(status === 'online' ? 'pill--online' : 'pill--offline');
            var lbl = pill.querySelector('[data-status-label]');
            if (lbl) lbl.textContent = label;
            var icon = pill.querySelector('i');
            if (icon) {
                icon.setAttribute('data-lucide',
                    status === 'online' ? 'check-circle-2'
                    : status === 'offline' ? 'x-circle'
                    : 'loader');
            }
        }
        if (prev !== 'online' && status === 'online') updateOnlineCount(+1);
        if (prev === 'online' && status !== 'online') updateOnlineCount(-1);
    }

    /**
     * @param {HTMLElement} tile
     * @param {{hostname?: string, players?: number, maxplayers?: number, map?: string, player_list?: Array<{name: string, frags: number, time_f: string}>, error?: string}} d
     */
    function applyData(tile, d) {
        if (d.error === 'connect') {
            setStatus(tile, 'offline', 'Offline');
            var hostFb = tile.querySelector('[data-testid="server-host"]');
            if (hostFb) hostFb.textContent = (hostFb.getAttribute('data-fallback') || '');
            var toggleOff = tile.querySelector('[data-testid="server-toggle"]');
            if (toggleOff instanceof HTMLButtonElement) toggleOff.disabled = true;
            return;
        }

        setStatus(tile, 'online', 'Online');

        var host = tile.querySelector('[data-testid="server-host"]');
        if (host && d.hostname) {
            // d.hostname is htmlspecialchars()'d server-side
            // (web/api/handlers/servers.php → api_servers_host_players),
            // so setHTML mirrors the legacy LoadServerHost() behaviour.
            sb.setHTML(host.id || (host.id = 'server-host-' + tile.getAttribute('data-id')), d.hostname);
        }

        var mapEl = tile.querySelector('[data-testid="server-map"]');
        if (mapEl) mapEl.textContent = d.map || '';

        var players = Number(d.players || 0);
        var maxp = Number(d.maxplayers || 0);
        var pl = tile.querySelector('[data-testid="server-players"]');
        if (pl) pl.textContent = players + '/' + maxp;
        var bar = tile.querySelector('[data-players-bar]');
        if (bar instanceof HTMLElement && maxp > 0) {
            var pct = Math.min(100, Math.max(0, (players / maxp) * 100));
            bar.style.width = pct + '%';
        }

        var toggle = tile.querySelector('[data-testid="server-toggle"]');
        if (toggle instanceof HTMLButtonElement) toggle.disabled = false;

        // Cache the player list on the tile for cheap re-render on toggle.
        if (Array.isArray(d.player_list)) {
            tile.__sbppPlayers = d.player_list;
            renderPlayers(tile);
        }
    }

    /**
     * @param {HTMLElement} tile
     */
    function renderPlayers(tile) {
        var panel = tile.querySelector('[data-testid="server-players-panel"]');
        if (!panel) return;
        var ul = panel.querySelector('[data-player-list]');
        var empty = panel.querySelector('[data-empty-message]');
        var list = (tile.__sbppPlayers || []);
        if (!ul) return;
        if (!list.length) {
            ul.style.display = 'none';
            if (empty instanceof HTMLElement) empty.style.display = '';
            return;
        }
        if (empty instanceof HTMLElement) empty.style.display = 'none';
        ul.style.display = '';
        ul.innerHTML = '';
        list.forEach(function (p) {
            var li = document.createElement('li');
            li.style.display = 'flex';
            li.style.alignItems = 'center';
            li.style.justifyContent = 'space-between';
            li.style.gap = '0.5rem';
            li.style.padding = '0.25rem 0';
            li.style.borderBottom = '1px solid var(--border)';
            li.setAttribute('data-testid', 'server-player');

            var name = document.createElement('span');
            name.className = 'truncate text-sm';
            name.textContent = String(p.name || '');
            li.appendChild(name);

            var meta = document.createElement('span');
            meta.className = 'text-xs text-muted font-mono tabular-nums';
            meta.textContent = (p.frags != null ? String(p.frags) : '0') + ' \u00B7 ' + String(p.time_f || '');
            li.appendChild(meta);

            ul.appendChild(li);
        });
    }

    /**
     * @param {HTMLElement} tile
     * @param {boolean} open
     */
    function setExpanded(tile, open) {
        tile.setAttribute('data-expanded', open ? 'true' : 'false');
        var toggle = tile.querySelector('[data-testid="server-toggle"]');
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        var panel = tile.querySelector('[data-testid="server-players-panel"]');
        if (panel instanceof HTMLElement) {
            if (open) panel.removeAttribute('hidden'); else panel.setAttribute('hidden', '');
        }
    }

    /**
     * @param {HTMLElement} tile
     */
    function loadTile(tile) {
        var sid = Number(tile.getAttribute('data-id'));
        if (!sid) return;
        setStatus(tile, 'loading', 'Loading');
        sb.api.call(Actions.ServersHostPlayers, { sid: sid, trunchostname: 70 }).then(function (r) {
            if (!r || !r.ok || !r.data) {
                setStatus(tile, 'offline', 'Offline');
                return;
            }
            applyData(tile, r.data);
            var idx = Number(tile.getAttribute('data-index'));
            if (openedIndex >= 0 && openedIndex === idx && tile.getAttribute('data-status') === 'online') {
                setExpanded(tile, true);
            }
        }, function () {
            setStatus(tile, 'offline', 'Offline');
        });
    }

    Array.prototype.forEach.call(tiles, function (tile) {
        loadTile(tile);

        var toggle = tile.querySelector('[data-testid="server-toggle"]');
        if (toggle) {
            toggle.addEventListener('click', function (ev) {
                ev.preventDefault();
                if (toggle instanceof HTMLButtonElement && toggle.disabled) return;
                var open = tile.getAttribute('data-expanded') !== 'true';
                setExpanded(tile, open);
            });
        }

        var refresh = tile.querySelector('[data-testid="server-refresh"]');
        if (refresh) {
            refresh.addEventListener('click', function (ev) {
                ev.preventDefault();
                loadTile(tile);
            });
        }
    });

    // Re-render Lucide icons we just swapped via setAttribute('data-lucide').
    if (typeof window.lucide !== 'undefined' && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
})();
{/literal}
</script>
