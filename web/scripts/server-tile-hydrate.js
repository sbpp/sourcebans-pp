// @ts-check
/* ============================================================
   server-tile-hydrate.js — per-tile UDP-probe hydration

   Shared hydration helper for the two card-grid surfaces that
   render `:prefix_servers` rows and want live A2S data:

     - public servers list (page_servers.tpl)
     - admin Server Management list (page_admin_servers_list.tpl, #1313)

   Both surfaces emit `[data-testid="server-tile"]` cards with
   placeholder values for map / players / hostname; this helper
   walks the cards, fires `Actions.ServersHostPlayers` per tile,
   and patches the response into the testid'd cells. The admin
   list's pre-#1313 markup carried `[data-hydrate="map"]` /
   `[data-hydrate="players"]` placeholders with no script behind
   them and the tile values stayed at the em-dash forever; #1313
   renamed those hooks to match the public surface and routed
   the call here so both surfaces share the contract.

   ----------------------------------------------------------------
   Selector contract per tile
   ----------------------------------------------------------------
   Required (every tile)
     [data-testid="server-tile"]              outer card
     data-id="<sid>"                          :prefix_servers.sid

   Required for status pill rendering
     [data-testid="server-status"]            pill element
     [data-status-label]      (inside pill)   label text node
     <i> child            (inside pill)       Lucide icon (swapped via data-lucide)

   Required for the live cells (cells without these testids stay at the placeholder)
     [data-testid="server-map"]               map name cell
     [data-testid="server-players"]           "N/M" cell

   Optional
     [data-testid="server-host"]              hostname cell
       data-fallback="<text>"                 shown when the UDP probe fails
       id (auto-assigned if missing)          target for sb.setHTML
     [data-players-bar]                       width-driven progress bar
     [data-testid="server-refresh"]           re-query button
     [data-testid="server-toggle"]            expand/collapse player list (public-only)
     [data-testid="server-players-panel"]     player list panel (public-only)
     [data-testid="server-map-img"]           map preview thumbnail (#1312, public-only).
                                              <img hidden> slot the helper patches
                                              `src` into from `d.mapimg` and unhides
                                              on `load`; on `error` it stays hidden
                                              so the empty `src=""` placeholder never
                                              paints a broken-image icon.
     data-server-skip="1"                     skip hydration for this tile
                                              (admin uses this on disabled
                                               servers — there's no point
                                               poking a server marked
                                               "disabled" in the panel)

   ----------------------------------------------------------------
   Container-level options (read off the wrapping `[data-server-hydrate]`)
   ----------------------------------------------------------------
     data-server-hydrate="auto"               auto-hydrate on DOMContentLoaded
     data-opened-index="<n>"                  auto-expand the matching tile
                                              after its hydration response lands
                                              (public-only; matches the
                                              `?p=servers&s=<n>` deep link
                                              behaviour from page.servers.php)
     data-trunchostname="<n>"                 hostname truncation forwarded
                                              to the JSON action (default 70)

   ----------------------------------------------------------------
   Public API (window.SBPP)
   ----------------------------------------------------------------
     hydrateServerTiles(opts?)                hydrate every tile inside
                                              `opts.container`. When called
                                              with no arguments, picks up
                                              every `[data-server-hydrate]`
                                              container in the document.

   The helper is feature-detected for every optional element, so a tile
   that doesn't carry (e.g.) `[data-players-bar]` still hydrates the cells
   it does carry. That's how one helper covers both the public surface
   (which has player-list expansion + an auto-expand index) and the admin
   surface (which has neither).

   Per AGENTS.md "Frontend": vanilla JS only, no bundler, `// @ts-check`
   + JSDoc on every public function. The helper depends on api.js (sb.api)
   and api-contract.js (Actions / Perms); both load from `core/header.tpl`.
   ============================================================ */
(function () {
    'use strict';

    /**
     * @typedef {Object} HydrateOptions
     * @property {ParentNode} [container] - tile-bearing wrapper (default: every `[data-server-hydrate]` in the doc).
     * @property {number} [openedIndex] - auto-expand the tile whose `data-index` matches this value (default: -1, i.e. don't auto-expand).
     * @property {number} [trunchostname] - hostname truncation forwarded to the JSON action (default 70).
     */

    /**
     * @typedef {Object} HostPlayersData
     * @property {string} [hostname]
     * @property {number} [players]
     * @property {number} [maxplayers]
     * @property {string} [map]
     * @property {string} [mapimg]
     * @property {Array<{ id?: number, name: string, frags: number, time?: number, time_f: string, steamid?: string }>} [player_list]
     * @property {boolean} [can_ban_player]
     * @property {string} [error]
     * @property {string} [ip]
     * @property {string|number} [port]
     */

    /** Online-tile counter, bumped from setStatus(); rendered into [data-online-num]. */
    var onlineDelta = 0;

    /**
     * @param {Element | null} root
     * @returns {HTMLElement | null}
     */
    function summaryNode(root) {
        var doc = root || document;
        return /** @type {HTMLElement | null} */ (
            doc instanceof Element || doc instanceof DocumentFragment
                ? doc.querySelector('[data-testid="servers-summary"]')
                : document.querySelector('[data-testid="servers-summary"]')
        );
    }

    /**
     * Bumps the online counter shown in `[data-testid="servers-summary"]`
     * (public-only header copy). No-op on surfaces that don't render
     * the summary node — the admin list, for instance.
     *
     * @param {ParentNode} container
     * @param {number} delta
     */
    function updateOnlineCount(container, delta) {
        onlineDelta += delta;
        var summary = summaryNode(/** @type {Element | null} */ (container));
        if (!summary) return;
        var num = summary.querySelector('[data-online-num]');
        if (num) num.textContent = String(Math.max(0, onlineDelta));
    }

    /**
     * @param {HTMLElement} tile
     * @param {string} status loading|online|offline
     * @param {string} label
     * @param {ParentNode} container
     */
    function setStatus(tile, status, label, container) {
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
        if (prev !== 'online' && status === 'online') updateOnlineCount(container, +1);
        if (prev === 'online' && status !== 'online') updateOnlineCount(container, -1);
    }

    /**
     * Render the players panel for the public-list surface. Skipped on
     * tiles without a `[data-testid="server-players-panel"]` child (e.g.
     * the admin list).
     *
     * Each `<li data-testid="server-player">` carries the per-row data
     * attributes that the right-click context menu (`server-context-menu.js`)
     * keys off:
     *
     *   data-context-menu="server-player"   marker for the document-level
     *                                       contextmenu delegate
     *   data-steamid="<value>"              SteamID2 / SteamID3 string
     *                                       from the API's RCON-fronted
     *                                       lookup (only when present —
     *                                       bots / unmatched names skip)
     *   data-name="<player name>"           UI label for the menu header
     *   data-server-sid="<sid>"             parent tile's data-id (used
     *                                       to build the per-server
     *                                       kick / block URLs)
     *   data-can-ban-player="true|false"    server-side gate result; the
     *                                       menu skips kick/ban/block
     *                                       items when "false" (anonymous
     *                                       viewer, partial-permission
     *                                       admin, admin without per-
     *                                       server RCON access)
     *
     * The visual `cursor: context-menu` is applied via a CSS class
     * (`.context-menu-target`) keyed on `[data-context-menu]` rather
     * than inline styles so dark-mode / responsive tweaks live in
     * theme.css next to the menu's own rules.
     *
     * @param {HTMLElement} tile
     */
    function renderPlayers(tile) {
        var panel = tile.querySelector('[data-testid="server-players-panel"]');
        if (!panel) return;
        var ul = panel.querySelector('[data-player-list]');
        var empty = panel.querySelector('[data-empty-message]');
        var list = (/** @type {any} */ (tile).__sbppPlayers || []);
        var canBanPlayer = (/** @type {any} */ (tile).__sbppCanBanPlayer === true);
        var sid = tile.getAttribute('data-id') || '';
        if (!ul) return;
        if (!list.length) {
            /** @type {HTMLElement} */ (ul).style.display = 'none';
            if (empty instanceof HTMLElement) empty.style.display = '';
            return;
        }
        if (empty instanceof HTMLElement) empty.style.display = 'none';
        var ulEl = /** @type {HTMLElement} */ (ul);
        ulEl.style.display = '';
        ulEl.innerHTML = '';
        list.forEach(function (/** @type {{ name: string, frags?: number, time_f?: string, steamid?: string }} */ p) {
            var li = document.createElement('li');
            li.style.display = 'flex';
            li.style.alignItems = 'center';
            li.style.justifyContent = 'space-between';
            li.style.gap = '0.5rem';
            li.style.padding = '0.25rem 0';
            li.style.borderBottom = '1px solid var(--border)';
            li.setAttribute('data-testid', 'server-player');

            // Wire the context-menu hooks only when a SteamID is
            // present. Bots and players whose A2S name didn't match
            // the RCON status output stay un-marked; the document
            // delegate filters by `closest('[data-context-menu]')`
            // so the native context menu fires on those rows.
            if (p.steamid) {
                li.setAttribute('data-context-menu', 'server-player');
                li.setAttribute('data-steamid', String(p.steamid));
                li.setAttribute('data-name', String(p.name || ''));
                li.setAttribute('data-server-sid', sid);
                li.setAttribute('data-can-ban-player', canBanPlayer ? 'true' : 'false');
                li.classList.add('context-menu-target');
            }

            var name = document.createElement('span');
            name.className = 'truncate text-sm';
            name.textContent = String(p.name || '');
            li.appendChild(name);

            var meta = document.createElement('span');
            meta.className = 'text-xs text-muted font-mono tabular-nums';
            meta.textContent = (p.frags != null ? String(p.frags) : '0') + ' \u00B7 ' + String(p.time_f || '');
            li.appendChild(meta);

            ulEl.appendChild(li);
        });
    }

    /**
     * Public-only expand/collapse of the player list panel. Tiles
     * without a panel get a no-op.
     *
     * @param {HTMLElement} tile
     * @param {boolean} open
     */
    function setExpanded(tile, open) {
        tile.setAttribute('data-expanded', open ? 'true' : 'false');
        var toggle = tile.querySelector('[data-testid="server-toggle"]');
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        var panel = tile.querySelector('[data-testid="server-players-panel"]');
        if (panel instanceof HTMLElement) {
            if (open) panel.removeAttribute('hidden');
            else panel.setAttribute('hidden', '');
        }
    }

    /**
     * @param {HTMLElement} tile
     * @param {HostPlayersData} d
     * @param {ParentNode} container
     */
    function applyData(tile, d, container) {
        if (d.error === 'connect') {
            setStatus(tile, 'offline', 'Offline', container);
            var hostFb = tile.querySelector('[data-testid="server-host"]');
            if (hostFb) hostFb.textContent = (hostFb.getAttribute('data-fallback') || '');
            var toggleOff = tile.querySelector('[data-testid="server-toggle"]');
            if (toggleOff instanceof HTMLButtonElement) toggleOff.disabled = true;
            // Re-enable the per-tile Re-query button so the operator can
            // try again — the server-side cache (#1311) absorbs back-to-
            // back probes inside its window, and we still want a visible
            // affordance to retry once the window expires.
            var refreshOff = tile.querySelector('[data-testid="server-refresh"]');
            if (refreshOff instanceof HTMLButtonElement) refreshOff.disabled = false;
            return;
        }

        setStatus(tile, 'online', 'Online', container);

        var host = tile.querySelector('[data-testid="server-host"]');
        if (host && d.hostname) {
            // d.hostname is htmlspecialchars()'d server-side
            // (web/api/handlers/servers.php → api_servers_host_players),
            // so setHTML mirrors the legacy LoadServerHost() behaviour.
            var hostId = host.id || (host.id = 'server-host-' + tile.getAttribute('data-id'));
            sb.setHTML(hostId, d.hostname);
        }

        var mapEl = tile.querySelector('[data-testid="server-map"]');
        if (mapEl) mapEl.textContent = d.map || '';

        // Map preview thumbnail (#1312). Feature-detected via the
        // testid — public servers list ships the `<img>` slot inside
        // `[data-testid="server-players-panel"]`; the admin Server
        // Management list does not, so the lookup returns null on
        // that surface and the whole block silently no-ops. The
        // handler returns `images/maps/<map>.jpg` (or
        // `images/maps/nomap.jpg` if the file is missing) — show the
        // <img> only after the network round-trip succeeds, because
        // `nomap.jpg` itself can be missing on forks / bare
        // deployments and we treat any load error as a signal to
        // keep the slot hidden rather than painting a broken-image
        // icon.
        var mapImg = tile.querySelector('[data-testid="server-map-img"]');
        if (mapImg instanceof HTMLImageElement) {
            // Pin the narrowed binding for the onload/onerror closures —
            // tsc loses the `instanceof` narrowing across closure
            // boundaries (the parent scope's `mapImg` could in principle
            // be reassigned before the closure fires), so we capture a
            // local non-null reference here.
            var imgEl = mapImg;
            if (d.mapimg) {
                imgEl.onload = function () { imgEl.removeAttribute('hidden'); };
                imgEl.onerror = function () { imgEl.setAttribute('hidden', ''); };
                imgEl.src = String(d.mapimg);
            } else {
                imgEl.setAttribute('hidden', '');
                imgEl.removeAttribute('src');
            }
        }

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
        // Re-enable the Re-query button now that the in-flight probe
        // landed (#1311). Mirrors the toggle gate above.
        var refreshOn = tile.querySelector('[data-testid="server-refresh"]');
        if (refreshOn instanceof HTMLButtonElement) refreshOn.disabled = false;

        // Cache the player list on the tile for cheap re-render on toggle.
        // The `can_ban_player` flag drives the context-menu visibility
        // for each player row (see `renderPlayers`); cache it on the
        // tile so a subsequent expand/collapse cycle doesn't have to
        // re-derive it from the in-flight response.
        if (Array.isArray(d.player_list)) {
            /** @type {any} */ (tile).__sbppPlayers = d.player_list;
            /** @type {any} */ (tile).__sbppCanBanPlayer = d.can_ban_player === true;
            renderPlayers(tile);
        }
    }

    /**
     * @param {HTMLElement} tile
     * @param {HydrateOptions} opts
     * @param {ParentNode} container
     */
    function loadTile(tile, opts, container) {
        if (tile.getAttribute('data-server-skip') === '1') {
            // Admin list marks disabled servers `data-server-skip="1"` so
            // we don't poke a UDP socket pointlessly. Leave the cells at
            // their server-rendered placeholder text.
            return;
        }
        var sid = Number(tile.getAttribute('data-id'));
        if (!sid) return;
        if (typeof sb === 'undefined' || !sb || !sb.api || typeof Actions === 'undefined') {
            // Chrome stripped api.js / api-contract.js. Leave the
            // server-rendered placeholders alone — the page is still
            // usable, the live cells just stay empty.
            return;
        }
        // Coalesce back-to-back invocations (#1311). Without this, the
        // hand-mash of the per-tile Re-query button or the auto-fire on
        // page load + a stray click translated each click into a fresh
        // `Actions.ServersHostPlayers` POST — the server-side cache
        // absorbs the bulk of that traffic, but the redundant XHRs are
        // still wasted bandwidth and a needless UX vector. We mirror
        // the toggle button's "disabled while loading" gate on the
        // refresh button below; this in-JS guard handles the case
        // where the click handler fires faster than the disabled
        // attribute settles in the DOM.
        var anyTile = /** @type {any} */ (tile);
        if (anyTile.__sbppLoading) return;
        anyTile.__sbppLoading = true;

        setStatus(tile, 'loading', 'Loading', container);
        var refresh = tile.querySelector('[data-testid="server-refresh"]');
        if (refresh instanceof HTMLButtonElement) refresh.disabled = true;

        var trunc = (opts && opts.trunchostname) || 70;
        var openedIndex = (opts && typeof opts.openedIndex === 'number') ? opts.openedIndex : -1;
        sb.api.call(Actions.ServersHostPlayers, { sid: sid, trunchostname: trunc }).then(function (r) {
            anyTile.__sbppLoading = false;
            if (!r || !r.ok || !r.data) {
                setStatus(tile, 'offline', 'Offline', container);
                if (refresh instanceof HTMLButtonElement) refresh.disabled = false;
                return;
            }
            applyData(tile, /** @type {HostPlayersData} */ (r.data), container);
            var idx = Number(tile.getAttribute('data-index'));
            if (openedIndex >= 0 && openedIndex === idx && tile.getAttribute('data-status') === 'online') {
                setExpanded(tile, true);
            }
        }, function () {
            anyTile.__sbppLoading = false;
            setStatus(tile, 'offline', 'Offline', container);
            if (refresh instanceof HTMLButtonElement) refresh.disabled = false;
        });
    }

    /**
     * Hydrate every tile inside `opts.container` (or every
     * `[data-server-hydrate]` container in the document if none was
     * supplied). Wires the optional toggle / refresh affordances and
     * triggers the JSON action per tile.
     *
     * @param {HydrateOptions} [opts]
     */
    function hydrateAll(opts) {
        opts = opts || {};
        /** @type {ParentNode} */
        var container = /** @type {any} */ (opts.container || document.querySelector('[data-server-hydrate]') || document);

        var tiles = container.querySelectorAll('[data-testid="server-tile"]');
        if (tiles.length === 0) return;

        // Container-level overrides — only honoured when the caller
        // didn't pass an explicit value.
        /** @type {Element | null} */
        var containerEl = container instanceof Element ? container : null;
        var openedIndex = (typeof opts.openedIndex === 'number')
            ? opts.openedIndex
            : (containerEl ? Number(containerEl.getAttribute('data-opened-index') || -1) : -1);
        var trunc = (typeof opts.trunchostname === 'number' && opts.trunchostname > 0)
            ? opts.trunchostname
            : (containerEl ? Number(containerEl.getAttribute('data-trunchostname') || 70) : 70);

        Array.prototype.forEach.call(tiles, function (/** @type {HTMLElement} */ tile) {
            loadTile(tile, { openedIndex: openedIndex, trunchostname: trunc }, container);

            var toggle = tile.querySelector('[data-testid="server-toggle"]');
            if (toggle && !(/** @type {any} */ (toggle).__sbppWired)) {
                /** @type {any} */ (toggle).__sbppWired = true;
                toggle.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    if (toggle instanceof HTMLButtonElement && toggle.disabled) return;
                    var open = tile.getAttribute('data-expanded') !== 'true';
                    setExpanded(tile, open);
                });
            }

            var refresh = tile.querySelector('[data-testid="server-refresh"]');
            if (refresh && !(/** @type {any} */ (refresh).__sbppWired)) {
                /** @type {any} */ (refresh).__sbppWired = true;
                refresh.addEventListener('click', function (ev) {
                    ev.preventDefault();
                    // Belt-and-braces against rapid clicks that race the
                    // `disabled` attribute settling in the DOM (#1311).
                    // `loadTile`'s `__sbppLoading` flag is the canonical
                    // gate; this short-circuit just avoids the no-op
                    // round-trip through `loadTile`.
                    if (refresh instanceof HTMLButtonElement && refresh.disabled) return;
                    loadTile(tile, { openedIndex: openedIndex, trunchostname: trunc }, container);
                });
            }
        });

        // Re-render Lucide icons we just swapped via setAttribute('data-lucide').
        // Both surfaces ship Lucide via theme.js; no-op when absent.
        var lucide = /** @type {any} */ (window).lucide;
        if (lucide && typeof lucide.createIcons === 'function') {
            lucide.createIcons();
        }
    }

    /** Auto-hydrate on first paint for every container that opts in. */
    function autoHydrate() {
        var containers = document.querySelectorAll('[data-server-hydrate="auto"]');
        Array.prototype.forEach.call(containers, function (/** @type {HTMLElement} */ el) {
            hydrateAll({ container: el });
        });
    }

    // Stash the public entry point on window.SBPP (the same namespace
    // theme.js uses for showToast / openDrawer). Also exposed under the
    // per-helper name so a test harness or third-party theme can
    // re-trigger it without depending on the auto-hydrate.
    /** @type {any} */ (window).SBPP = /** @type {any} */ (window).SBPP || {};
    /** @type {any} */ (window).SBPP.hydrateServerTiles = hydrateAll;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', autoHydrate);
    } else {
        autoHydrate();
    }
})();
