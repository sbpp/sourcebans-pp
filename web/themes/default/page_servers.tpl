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
    `data-status` and the shared hydration helper
    (`web/scripts/server-tile-hydrate.js`) walks
    `[data-testid="server-tile"]` inside the `[data-server-hydrate]`
    wrapper. `sb.api.call` and the `Actions.*` constants come from
    the chrome's api.js + api-contract.js (loaded by core/header.tpl).
    The helper auto-runs on first paint, so the page-level body needs
    no inline `<script>` block of its own — it just renders the markup
    contract the helper reads. The same helper also drives the admin
    Server Management list (#1313) so both surfaces share the contract.

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
        {*
            Admin right-click context-menu hint. Restored after the
            #1306 removal — the supporting JS now lives at
            `web/scripts/server-context-menu.js` (event-delegate
            pattern, NOT a port of the legacy MooTools `sb.contextMenu`
            helpers), and the SteamIDs the menu keys off come from an
            extension to `api_servers_host_players` that does a
            cached RCON `status` round-trip per server (see
            `Sbpp\Servers\RconStatusCache`). Gated on
            `can_use_context_menu` so anonymous viewers and admins
            without `ADMIN_OWNER | ADMIN_ADD_BAN` don't see hint
            copy describing a feature they can't reach. See
            `web/scripts/server-context-menu.js` for the menu's full
            data-attribute contract.
        *}
        {if $can_use_context_menu}
            {* Hint copy describes a right-click gesture, so we hide it
               on touch-only devices (`pointer: coarse` AND `hover: none`)
               via `.servers-rcon-hint` in theme.css. The menu itself is
               desktop-only on mobile Safari / most Android browsers (no
               `contextmenu` event from a long-press); users with a
               Bluetooth mouse on a tablet still see it because their
               primary pointer reports `hover: hover`. Keeping the
               element in the DOM rather than gating server-side keeps
               the visibility responsive to a paired mouse/keyboard
               that connects mid-session (some Android tablets fire a
               `pointer: fine` change event when a Bluetooth mouse
               attaches). *}
            <p class="text-xs text-muted m-0 servers-rcon-hint"
               data-testid="servers-rcon-hint"
               style="max-width:24rem;text-align:right">
                Right-click a player on an expanded card to view their
                profile, copy their SteamID, or kick / ban / block them.
                Kick / ban / block actions only show on servers you have
                RCON access to.
            </p>
        {/if}
    </header>

    {if $server_list|@count == 0}
        {* #1207 PUB-3 + empty-state unification: first-run state.
           The body copy stays terse; the primary CTA is gated on
           `can_add_server` (Perms::for $userbank), so a logged-out
           visitor / admin without ADMIN_ADD_SERVER sees the copy
           but no link to the admin form they couldn't use anyway. *}
        <div class="card" data-testid="servers-empty">
            <div class="empty-state">
                <span class="empty-state__icon" aria-hidden="true">
                    <i data-lucide="server" style="width:18px;height:18px"></i>
                </span>
                <h2 class="empty-state__title">No servers configured yet</h2>
                <p class="empty-state__body">
                    Once you add a server, players, status, and live
                    counts will appear here for visitors.
                </p>
                {if $can_add_server}
                    <div class="empty-state__actions">
                        <a class="btn btn--primary btn--sm"
                           href="?p=admin&amp;c=servers"
                           data-testid="servers-empty-add">
                            <i data-lucide="plus" style="width:13px;height:13px"></i>
                            Add a server
                        </a>
                    </div>
                {/if}
            </div>
        </div>
    {else}
    {*
        data-server-hydrate="auto" tells web/scripts/server-tile-hydrate.js
        to walk every `[data-testid="server-tile"]` inside this container
        and fire `Actions.ServersHostPlayers` per tile after first paint.
        data-opened-index threads through the deep-link auto-expand contract
        from page.servers.php's `?p=servers&s=<n>` handler — the helper
        flips the matching tile open once its hydration response lands.

        `.servers-grid` (theme.css, #1316) is shared with
        `page_admin_servers_list.tpl` so both surfaces look consistent
        and a theme fork can override the column min-width in one
        place. The class supersedes the pre-#1316 inline
        `grid-template-columns` style; see the theme.css comment above
        the `.servers-grid` rule for the breakpoint reasoning.
    *}
    <div class="servers-grid"
         data-testid="servers-list"
         data-server-hydrate="auto"
         data-opened-index="{$opened_server}"
         data-trunchostname="70">
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
                            aria-label="Refresh server status"
                            disabled>
                        <i data-lucide="refresh-cw" style="width:13px;height:13px"></i>
                    </button>
                </div>
            </div>

            <div id="server-players-{$server.sid}"
                 class="server-tile__players"
                 data-testid="server-players-panel"
                 hidden
                 style="border-top:1px solid var(--border);padding:0.75rem 1.25rem">
                {*
                    Map preview thumbnail. The legacy v1.x server card carried
                    an inline `<img id="mapimg_{$server.sid}">` here; the
                    #1123 D1 redesign dropped it but the API handler still
                    returns `mapimg` (web/api/handlers/servers.php →
                    api_servers_host_players → GetMapImage). The shared
                    hydration helper at web/scripts/server-tile-hydrate.js
                    (#1313) patches `src` from the live response via
                    `applyData()`'s `[data-testid="server-map-img"]` lookup,
                    unhides the element on `load`, and KEEPS it hidden on
                    `error` (file missing or 404 / nomap.jpg also missing)
                    so we never paint a broken-image icon. The helper is
                    feature-detected, so the admin Server Management list
                    (which does NOT ship this slot) silently no-ops.
                    `alt=""` is intentional: the map name is already
                    rendered as text in the `[data-testid="server-map"]`
                    row above, so the image is decorative confirmation
                    rather than independent content (#1312).

                    Layout (#1375):
                    -----------------
                    The bundled map thumbnails under `web/images/maps/`
                    are mostly 340×255 (4:3 landscape — `de_dust2.jpg`,
                    `nomap.jpg`, the CS / TF2 set), with a handful of
                    newer 16:9 MvM screenshots (800×450) thrown in.
                    Pre-#1375 the slot ran `width:100%;max-height:140px;
                    object-fit:cover` — but on a 28rem grid track (~448px
                    painted card minus padding) that resolved to a ~400×140
                    box (~2.86:1), so `object-fit:cover` cropped the
                    middle horizontal band of a 4:3 source and the visible
                    strip read as a horizontally-stretched fragment of the
                    map (e.g. `de_dust2.jpg`'s windows visibly squashed).
                    The fix is `max-width: 340px` (the natural source
                    width — no upscaling blur on smaller maps) + `height:
                    auto` so the rendered box matches the source aspect
                    ratio exactly. Cards wider than 340px get the
                    thumbnail centered (`margin: 0 auto`); narrower cards
                    fall back to `width:100%` and scale proportionally.
                    `object-fit` is dropped because `height:auto` already
                    yields a box that matches the source aspect — there's
                    no mismatch left for `cover` / `contain` to resolve.
                *}
                <img src=""
                     alt=""
                     data-testid="server-map-img"
                     class="server-tile__mapimg"
                     hidden
                     style="display:block;width:100%;max-width:340px;height:auto;border-radius:var(--radius-sm);margin:0 auto 0.5rem;background:var(--bg-muted)">
                <p class="text-xs text-muted m-0" data-empty-message>No players currently connected.</p>
                <ul class="m-0" data-player-list style="list-style:none;padding:0;display:none"></ul>
            </div>
        </article>
        {/foreach}
    </div>
    {/if}
</div>

{*
    Per-tile hydration is driven by web/scripts/server-tile-hydrate.js
    (#1313). The helper auto-runs on first paint for every container
    marked `data-server-hydrate="auto"` (see the `<div class="grid …">`
    above), walks `[data-testid="server-tile"]` children, fires one
    `Actions.ServersHostPlayers` call per tile and patches the live
    cells (status pill, map, players, host, players bar). The script
    file lives under web/scripts/ rather than inline so the admin
    Server Management list (page_admin_servers_list.tpl, #1313) can
    pull the same helper without copy-pasting ~200 lines of JS.
*}
<script src="./scripts/server-tile-hydrate.js" defer></script>
{*
    Right-click context menu on player rows in expanded server cards.
    Only loaded when the viewer can actually use the menu — anonymous
    visitors and admins without ADMIN_OWNER | ADMIN_ADD_BAN don't
    get the SteamID side-channel from the JSON handler either, so
    loading the script for them would be a no-op + a wasted byte.
*}
{if $can_use_context_menu}
<script src="./scripts/server-context-menu.js" defer></script>
{/if}
