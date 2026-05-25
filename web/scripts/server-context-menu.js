// @ts-check
/* ============================================================
   server-context-menu.js — restored right-click context menu

   Pre-v2.0.0 the public servers page rendered a right-click
   context menu on player rows in expanded server cards that
   let admins jump straight from a player name to View Profile /
   Copy SteamID / Kick / Ban / Block Comms surfaces. The supporting
   JS (the MooTools-era contextMenoo helpers in
   `web/scripts/contextMenoo.js` + `sb.contextMenu` +
   `AddContextMenu`) was removed at #1306 because the row markup
   no longer carried SteamIDs (xPaw A2S GetPlayers UDP response
   doesn't include them) and the menu pointed at a feature that
   never landed in v2.0.0.

   This file is the restored implementation. It is NOT a port of
   the legacy helpers — those are intentionally NOT being
   reintroduced (see AGENTS.md Anti-patterns). The contract here:

     1. Vanilla JS, `// @ts-check` + JSDoc, no MooTools, no
        bundler. (AGENTS.md "Frontend".)
     2. Single document-level `contextmenu` listener that filters
        by `closest('[data-context-menu="server-player"]')`. Any
        row without that marker (bots, players the A2S/RCON
        match didn't pair up, surfaces other than the public
        servers list) preserves the native browser menu.
     3. SteamIDs come from an extension to
        `api_servers_host_players` that pairs the A2S player
        list with an RCON `status` round-trip via
        `Sbpp\Servers\RconStatusCache` — sid-keyed, 30s cache,
        permission-gated. The handler is the load-bearing gate
        for the SteamID side-channel; the JS here is the UX
        layer that consumes whatever the response carries.
     4. The kick / ban / block items render conditionally on
        `data-can-ban-player="true"`. View Profile + Copy SteamID
        always render (they don't fan out admin actions, just
        surface public info the player already broadcast).
     5. Keyboard navigation (ArrowUp / ArrowDown / Enter / Escape)
        + focus management so the menu is reachable for users
        without a pointing device, mirroring native menu UX.
     6. Copy SteamID uses the same secure-context-aware shape
        the document-level COPY BUTTONS delegate in theme.js
        uses (`navigator.clipboard` + `window.isSecureContext`
        + `copyFallback`). Toasts fire through
        `window.SBPP.showToast` when present (sb.message
        fallback otherwise).
     7. The menu's open animation honours the global
        `@media (prefers-reduced-motion: reduce)` reset — it's
        motion-of-state, not essential feedback (cf. busy
        spinner / shimmer per AGENTS.md), so collapsing the
        slide-in to 0ms under reduced motion is correct.

   Selector contract (every property emitted by
   `web/scripts/server-tile-hydrate.js`'s `renderPlayers`):

     [data-context-menu="server-player"]   filter for the
                                           document delegate
     data-steamid="<value>"                STEAM_X:Y:Z or
                                           [U:1:N] string
     data-name="<player name>"             label rendered in
                                           the menu header
     data-server-sid="<sid>"               parent tile's sid
                                           (kick/block target)
     data-can-ban-player="true|false"      gate for kick/ban/
                                           block menu items
   ============================================================ */
(function () {
    'use strict';

    /** Anchor for the open menu's root element. `null` when no menu is open. */
    /** @type {HTMLElement | null} */
    var openMenu = null;

    /** The currently focused menu item index (0-based). */
    var focusedIndex = -1;

    /** SteamID64 base — every modern Steam account id is this constant + accountid. */
    var STEAM_BASE = BigInt('76561197960265728');

    /**
     * Convert a SteamID2 / SteamID3 / SteamID64 string to SteamID64.
     * Used to build the `https://steamcommunity.com/profiles/<64>` URL.
     *
     * Implementations:
     *  - `STEAM_X:Y:Z`  -> 76561197960265728 + 2*Z + Y
     *  - `[U:1:N]`      -> 76561197960265728 + N
     *  - 17-digit number -> returned verbatim
     *
     * @param {string} steamid
     * @returns {string | null} null when the input shape is unrecognised
     */
    function steamIdToSteam64(steamid) {
        var s = String(steamid || '').trim();
        if (!s) return null;

        // STEAM_X:Y:Z
        var steam2 = s.match(/^STEAM_[01]:([01]):(\d+)$/);
        if (steam2) {
            var y = BigInt(steam2[1]);
            var z = BigInt(steam2[2]);
            return (STEAM_BASE + (z * BigInt(2)) + y).toString();
        }

        // [U:1:N]
        var steam3 = s.match(/^\[U:1:(\d+)\]$/);
        if (steam3) {
            return (STEAM_BASE + BigInt(steam3[1])).toString();
        }

        // SteamID64 already
        if (/^\d{17}$/.test(s)) {
            return s;
        }

        return null;
    }

    /**
     * Best-effort toast surface — mirrors the pattern the per-page
     * tail scripts use (page_admin_bans_add.tpl, page_comms.tpl).
     *
     * @param {'success'|'error'|'info'} kind
     * @param {string} title
     * @param {string} [body]
     */
    function toast(kind, title, body) {
        var SBPP = /** @type {any} */ (window).SBPP;
        if (SBPP && typeof SBPP.showToast === 'function') {
            SBPP.showToast({ kind: kind, title: title, body: body || '' });
            return;
        }
        var sb = /** @type {any} */ (window).sb;
        if (sb && sb.message) {
            var fn = kind === 'error' ? sb.message.error : sb.message.success;
            if (typeof fn === 'function') fn(title, body || '');
        }
    }

    /**
     * Hidden-textarea + `document.execCommand('copy')` fallback for
     * non-secure contexts. Mirrors the shape `theme.js`'s COPY BUTTONS
     * delegate uses; the two surfaces have to agree because both can
     * fire on the same `[data-copy]` button.
     *
     * @param {string} value
     */
    function copyFallback(value) {
        try {
            var ta = document.createElement('textarea');
            ta.value = value;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            ta.style.top = '0';
            document.body.appendChild(ta);
            ta.select();
            var ok = document.execCommand('copy');
            document.body.removeChild(ta);
            if (ok) {
                toast('success', 'Copied to clipboard', value);
            } else {
                toast('error', "Couldn\u2019t copy SteamID");
            }
        } catch (_e) {
            toast('error', "Couldn\u2019t copy SteamID");
        }
    }

    /**
     * Write the supplied value to the clipboard, honouring the
     * secure-context split documented in theme.js's COPY BUTTONS
     * comment block (the contract has to match — both delegates
     * can fire on the same surface).
     *
     * @param {string} value
     */
    function copyToClipboard(value) {
        if (navigator.clipboard && /** @type {any} */ (window).isSecureContext) {
            void navigator.clipboard.writeText(value).then(
                function () { toast('success', 'SteamID copied', value); },
                function () { copyFallback(value); },
            );
            return;
        }
        copyFallback(value);
    }

    /**
     * Close the currently open menu (if any) and reset focus state.
     */
    function closeMenu() {
        if (!openMenu) return;
        var trigger = /** @type {HTMLElement | null} */ (/** @type {any} */ (openMenu).__sbppTrigger || null);
        try {
            openMenu.parentNode && openMenu.parentNode.removeChild(openMenu);
        } catch (_e) { /* no-op */ }
        openMenu = null;
        focusedIndex = -1;
        // Restore focus to the row that opened the menu so screen
        // readers and keyboard users don't lose their place.
        if (trigger && typeof trigger.focus === 'function') {
            try { trigger.focus(); } catch (_e) { /* no-op */ }
        }
    }

    /**
     * Move keyboard focus to the menu item at the supplied index,
     * clamping at the menu's bounds.
     *
     * @param {number} index
     */
    function focusItem(index) {
        if (!openMenu) return;
        var items = openMenu.querySelectorAll('[role="menuitem"]');
        if (items.length === 0) return;
        if (index < 0) index = items.length - 1;
        if (index >= items.length) index = 0;
        focusedIndex = index;
        var target = /** @type {HTMLElement} */ (items[index]);
        if (typeof target.focus === 'function') {
            target.focus();
        }
    }

    /**
     * Build a single menu row.
     *
     * @param {Object} opts
     * @param {string} opts.label
     * @param {string} opts.icon  Lucide icon name (rendered via data-lucide)
     * @param {string} [opts.href]  When set, the row is an `<a>` (middle-
     *                              click opens in a new tab); when omitted,
     *                              it's a `<button type="button">`.
     * @param {string} [opts.target]
     * @param {string} [opts.rel]
     * @param {string} [opts.testid]
     * @param {(ev: Event) => void} [opts.onActivate]  fired for `<button>` rows
     *                                                 + Enter on either shape
     * @returns {HTMLElement}
     */
    function buildRow(opts) {
        /** @type {HTMLElement} */
        var row;
        if (opts.href) {
            var a = document.createElement('a');
            a.href = opts.href;
            if (opts.target) a.target = opts.target;
            if (opts.rel) a.rel = opts.rel;
            row = a;
        } else {
            var b = document.createElement('button');
            b.type = 'button';
            row = b;
        }
        row.className = 'context-menu__item';
        row.setAttribute('role', 'menuitem');
        row.setAttribute('tabindex', '-1');
        if (opts.testid) row.setAttribute('data-testid', opts.testid);

        var iconEl = document.createElement('i');
        iconEl.setAttribute('data-lucide', opts.icon);
        iconEl.setAttribute('aria-hidden', 'true');
        iconEl.style.width = '14px';
        iconEl.style.height = '14px';
        row.appendChild(iconEl);

        var labelEl = document.createElement('span');
        labelEl.textContent = opts.label;
        row.appendChild(labelEl);

        if (opts.onActivate) {
            row.addEventListener('click', function (ev) {
                ev.preventDefault();
                /** @type {(ev: Event) => void} */ (opts.onActivate)(ev);
                closeMenu();
            });
        } else {
            // Anchors with `href` close the menu themselves on click;
            // the browser handles navigation after the click event
            // bubbles through the document's `mousedown`/`click`
            // close-on-outside-click guard. Mark the row so the
            // document delegate doesn't immediately close before the
            // navigation fires.
            row.addEventListener('click', function () {
                // Defer close so the navigation (or middle-click new-tab
                // open) actually fires before we tear the DOM down.
                setTimeout(closeMenu, 0);
            });
        }

        return row;
    }

    /**
     * Build the menu element for the supplied trigger row.
     *
     * @param {HTMLElement} trigger
     * @returns {HTMLElement | null}
     */
    function buildMenu(trigger) {
        var steamid = trigger.getAttribute('data-steamid') || '';
        var name = trigger.getAttribute('data-name') || '';
        var sid = trigger.getAttribute('data-server-sid') || '';
        var canBan = trigger.getAttribute('data-can-ban-player') === 'true';
        if (!steamid) return null;

        var menu = document.createElement('div');
        menu.className = 'context-menu';
        menu.setAttribute('role', 'menu');
        menu.setAttribute('data-testid', 'server-context-menu');
        menu.setAttribute('aria-label', 'Actions for ' + name);
        // Pin the trigger so closeMenu() can restore focus correctly
        // — `__sbppTrigger` is a private hook the rest of the file
        // reads off the menu element; not part of the public API.
        /** @type {any} */ (menu).__sbppTrigger = trigger;

        // Menu header — server-rendered label echoing the row clicked.
        // Helps screen readers (the menu's `aria-label` already covers
        // the announcement, but the visual header anchors the menu to
        // the row the operator opened it on).
        var header = document.createElement('div');
        header.className = 'context-menu__header';
        header.textContent = name;
        header.setAttribute('aria-hidden', 'true');
        menu.appendChild(header);

        var steam64 = steamIdToSteam64(steamid);
        if (steam64) {
            menu.appendChild(buildRow({
                label: 'View Steam profile',
                icon: 'external-link',
                href: 'https://steamcommunity.com/profiles/' + steam64,
                target: '_blank',
                rel: 'noopener noreferrer',
                testid: 'context-menu-profile',
            }));
        }

        menu.appendChild(buildRow({
            label: 'Copy SteamID',
            icon: 'copy',
            testid: 'context-menu-copy',
            onActivate: function () { copyToClipboard(steamid); },
        }));

        if (canBan && sid !== '') {
            // Kick is the one remaining iframe-routed item — it targets
            // `pages/admin.kickit.php` (a chromeless per-server-card
            // grid that fires the kick RCON command). It legitimately
            // belongs on the iframe path because kick is a one-shot
            // RCON command with no persistent panel surface to land
            // the operator on after the action fires; the page-chrome
            // sidebar / breadcrumb / topbar would be visual noise
            // around what's effectively a "fan a single command out
            // to every enabled server and close" interaction.
            //
            // The `&mode=kick` query param (#1439) tells the
            // chromeless kickit page (and the JSON action it fans out
            // through) that this is a kick-only flow — no ban row
            // exists. The handler then (a) skips the
            // `:prefix_bans` UPDATE that would otherwise re-attribute
            // any of the player's existing active bans to whatever
            // server happened to answer first, and (b) emits the
            // "You have been kicked from this server" rcon message
            // instead of the post-ban "You have been banned by this
            // server, check $domain for more info" (which would lie
            // to a player who's actually free to rejoin — the
            // user-reported #1439 symptom). The post-ban flow on
            // `admin.bans.php` deliberately doesn't pass `mode`, so
            // it falls through to the default 'ban' branch.
            //
            // Ban / Block both route through the panel-chromed
            // smart-default URLs (`?p=admin&c=bans&section=add-ban&steam=…&type=0&name=…`
            // / `?p=admin&c=comms&steam=…&type=0&name=…`) because they
            // populate a multi-field form the admin reviews and
            // submits — that form is a panel surface in its own
            // right (admin.bans.php's `add-ban` section,
            // admin.comms.php's only section). The admin.bans.php /
            // admin.comms.php handlers each carry a smart-default
            // block that allowlists the inbound SteamID/IP shape and
            // pre-populates the View DTO; see
            // `Sbpp\View\AdminBansAddView::prefill_steam` /
            // `Sbpp\View\AdminCommsAddView::prefill_steam`.
            //
            // Pre-#1395 Block routed to the same iframe surface as
            // Kick (`pages/admin.blockit.php?check=…&type=0`), which
            // wasn't the right shape: admin.blockit.php is the
            // post-`Actions.CommsAdd` rcon fan-out iframe (loaded via
            // `<iframe src="pages/admin.blockit.php?…">` inside the
            // Block-Added success message), not a stand-alone
            // operator surface. Hitting it directly rendered the
            // chromeless full-page version and POSTed to a relative
            // `api.php` that resolves to `/pages/api.php` → 404
            // (#1395 reproduction). admin.blockit.php's surviving
            // role is the iframe target — `page_admin_comms_add.tpl`
            // still iframes it from the Block-Added success branch,
            // same as `pages/admin.kickit.php` for the post-BansAdd
            // fan-out.
            //
            // Issue #1440: the `&name=…` arm pre-fills the form's
            // Nickname input alongside the SteamID one. We only
            // append the parameter when `name` is non-empty so the
            // URL stays clean for bot / unmatched-row cases (the
            // hydration helper guards `data-name` against empty
            // values at the row layer — see `server-tile-hydrate.js`'s
            // `renderPlayers` — so this is belt-and-braces, but
            // cheap to enforce here too). `encodeURIComponent` is
            // the right encoder for URL query-parameter values
            // (escapes `&` / `=` / `?` / `#`); the server-side
            // sanitisation contract (strip control chars + bidi
            // overrides, validate UTF-8, cap at 128 codepoints)
            // lives in `Sbpp\Util\PlayerName::sanitisePrefill`.
            // Kick stays nameless: `pages/admin.kickit.php` is
            // a chromeless iframe-only surface with no nickname
            // input, so the parameter would be inert noise.

            menu.appendChild(buildRow({
                label: 'Kick player',
                icon: 'log-out',
                href: 'pages/admin.kickit.php?check=' + encodeURIComponent(steamid) + '&type=0&mode=kick',
                testid: 'context-menu-kick',
            }));

            var nameParam = name !== '' ? '&name=' + encodeURIComponent(name) : '';
            menu.appendChild(buildRow({
                label: 'Ban player',
                icon: 'gavel',
                href: 'index.php?p=admin&c=bans&section=add-ban&steam=' + encodeURIComponent(steamid) + '&type=0' + nameParam,
                testid: 'context-menu-ban',
            }));

            // admin.comms.php is a single-section page (no
            // `?section=…` slug — see the page handler's docblock).
            // The `&type=0` parameter is the bridging value from the
            // sibling Ban URL shape (where 0 = Steam ID); for comms
            // the handler reads 0 as "no pre-selection" and lets the
            // form land on its native first-option default (Mute).
            // Valid block types (1=Mute, 2=Gag, 3=Silence) would
            // pre-select the matching option if a future surface
            // ever supplies them.
            menu.appendChild(buildRow({
                label: 'Block comms',
                icon: 'mic-off',
                href: 'index.php?p=admin&c=comms&steam=' + encodeURIComponent(steamid) + '&type=0' + nameParam,
                testid: 'context-menu-block',
            }));
        }

        return menu;
    }

    /**
     * Position the menu at `(x, y)` clamped to the viewport so it
     * never paints off-screen. Reads the menu's measured size after
     * insertion so the math accounts for the actual item count.
     *
     * @param {HTMLElement} menu
     * @param {number} x
     * @param {number} y
     */
    function positionMenu(menu, x, y) {
        var doc = document.documentElement;
        var vw = doc.clientWidth || window.innerWidth || 0;
        var vh = doc.clientHeight || window.innerHeight || 0;
        var w = menu.offsetWidth || 200;
        var h = menu.offsetHeight || 100;
        var left = x;
        var top = y;
        if (left + w > vw) left = Math.max(0, vw - w - 4);
        if (top + h > vh) top = Math.max(0, vh - h - 4);
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
    }

    /**
     * Open the context menu for the supplied trigger row at the
     * cursor position `(clientX, clientY)`.
     *
     * @param {HTMLElement} trigger
     * @param {number} clientX
     * @param {number} clientY
     */
    function openContextMenu(trigger, clientX, clientY) {
        closeMenu();
        var menu = buildMenu(trigger);
        if (!menu) return;
        document.body.appendChild(menu);
        openMenu = menu;
        positionMenu(menu, clientX, clientY);

        // Re-render Lucide icons inside the menu (the icons are emitted
        // as `<i data-lucide="...">` placeholders that the chrome's
        // Lucide bundle replaces with inline SVGs). Same shape the
        // hydration helper uses after applying icon swaps.
        var lucide = /** @type {any} */ (window).lucide;
        if (lucide && typeof lucide.createIcons === 'function') {
            lucide.createIcons();
        }

        focusItem(0);
    }

    /**
     * Document-level contextmenu delegate. Anchored at `document` (not
     * the players panel) so a single listener covers every server card
     * — including ones rendered after first paint (e.g. a future
     * surface that re-renders the player list without remounting the
     * tile).
     */
    document.addEventListener('contextmenu', function (ev) {
        if (!ev || !ev.target) return;
        var target = /** @type {Element} */ (ev.target);
        if (typeof target.closest !== 'function') return;
        var row = /** @type {HTMLElement | null} */ (target.closest('[data-context-menu="server-player"]'));
        if (!row) return;
        ev.preventDefault();
        openContextMenu(row, ev.clientX, ev.clientY);
    });

    /**
     * Close on any click outside the menu. Clicks inside the menu
     * are handled by the row's own `click` listener.
     */
    document.addEventListener('mousedown', function (ev) {
        if (!openMenu) return;
        var t = /** @type {Element | null} */ (ev.target);
        if (t && typeof t.closest === 'function' && t.closest('.context-menu')) return;
        closeMenu();
    });

    /**
     * Keyboard navigation. The menu listens on `keydown` at the document
     * level so we don't have to wire per-item listeners.
     */
    document.addEventListener('keydown', function (ev) {
        if (!openMenu) return;
        switch (ev.key) {
            case 'Escape':
                ev.preventDefault();
                closeMenu();
                break;
            case 'ArrowDown':
                ev.preventDefault();
                focusItem(focusedIndex + 1);
                break;
            case 'ArrowUp':
                ev.preventDefault();
                focusItem(focusedIndex - 1);
                break;
            case 'Home':
                ev.preventDefault();
                focusItem(0);
                break;
            case 'End':
                ev.preventDefault();
                {
                    var items = openMenu.querySelectorAll('[role="menuitem"]');
                    focusItem(items.length - 1);
                }
                break;
            case 'Enter':
            case ' ':
                // Let the row's own click handler fire — Enter on a
                // focused `<a>` triggers navigation; on a `<button>`
                // it fires a synthesized click. Closing happens in
                // the row's handler.
                break;
            default:
                break;
        }
    });

    /**
     * Close the menu on any layout-disturbing event. Without this, a
     * mid-scroll menu would paint over moved content and the cursor
     * position the menu was anchored to would no longer match.
     */
    window.addEventListener('scroll', closeMenu, /** @type {AddEventListenerOptions} */ ({ capture: true, passive: true }));
    window.addEventListener('resize', closeMenu);
    window.addEventListener('blur', closeMenu);
})();
