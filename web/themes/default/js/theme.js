// @ts-check
/* ============================================================
   theme.js — SourceBans++ 2026 panel JS (vanilla, no jQuery dep)
   Wires: theme toggle, command palette, drawer, toasts, mobile menu.
   Light/dark preference is localStorage-only — there is no
   per-user theme column server-side.
   ============================================================ */
(function () {
  'use strict';

  // ---- THEME TOGGLE ----------------------------------------
  const THEME_KEY = 'sbpp-theme';

  /**
   * Apply one of 'light' | 'dark' | 'system' to <html>.
   * @param {string} mode
   * @returns {void}
   */
  function applyTheme(mode) {
    const dark = mode === 'dark' || (mode === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
    try { localStorage.setItem(THEME_KEY, mode); } catch (e) { /* localStorage unavailable; ignore */ }
  }

  /**
   * Read the persisted theme preference; default to 'system'.
   * @returns {string}
   */
  function currentTheme() {
    try { return localStorage.getItem(THEME_KEY) || 'system'; } catch (e) { return 'system'; }
  }

  applyTheme(currentTheme());

  document.addEventListener('click', (/** @type {MouseEvent} */ e) => {
    const target = /** @type {Element | null} */ (e.target);
    const t = target && target.closest('[data-theme-toggle]');
    if (!t) return;
    const cur = currentTheme();
    const next = cur === 'light' ? 'dark' : cur === 'dark' ? 'system' : 'light';
    applyTheme(next);
  });

  matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (currentTheme() === 'system') applyTheme('system');
  });

  // ---- MOBILE SIDEBAR --------------------------------------
  // The hamburger trigger ([data-mobile-menu] in core/title.tpl)
  // toggles the off-canvas drawer; a click-dismiss
  // [data-sidebar-backdrop] is rendered on demand and Escape also
  // closes the drawer. Fixes #1178 — the original handler only
  // ever added `is-open`, leaving the user no way to dismiss the
  // drawer short of navigating away. `data-mobile-open` mirrors
  // the class state on every open/close so tests and CSS sibling
  // selectors can read state without probing the class chain
  // (#1179).

  /** @type {HTMLElement | null} */
  let sidebarBackdrop = null;

  /**
   * Lazily create (or reuse) the click-dismiss backdrop appended
   * to the body. The element survives across open/close cycles so
   * we don't churn the DOM on every toggle.
   * @returns {HTMLElement}
   */
  function ensureSidebarBackdrop() {
    if (sidebarBackdrop && sidebarBackdrop.isConnected) return sidebarBackdrop;
    let el = /** @type {HTMLElement | null} */ (document.querySelector('[data-sidebar-backdrop]'));
    if (!el) {
      el = document.createElement('div');
      el.className = 'sidebar-backdrop';
      el.setAttribute('data-sidebar-backdrop', '');
      document.body.appendChild(el);
    }
    sidebarBackdrop = el;
    return el;
  }

  /** @returns {void} */
  function openMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    sidebar.classList.add('is-open');
    sidebar.dataset.mobileOpen = 'true';
    ensureSidebarBackdrop().dataset.visible = 'true';
  }

  /** @returns {void} */
  function closeMobileSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    sidebar.classList.remove('is-open');
    sidebar.dataset.mobileOpen = 'false';
    if (sidebarBackdrop) sidebarBackdrop.dataset.visible = 'false';
  }

  document.addEventListener('click', (/** @type {MouseEvent} */ e) => {
    const target = /** @type {Element | null} */ (e.target);
    if (!target) return;
    if (target.closest('[data-mobile-menu]')) {
      const sidebar = document.getElementById('sidebar');
      if (!sidebar) return;
      if (sidebar.classList.contains('is-open')) closeMobileSidebar();
      else openMobileSidebar();
      return;
    }
    if (target.closest('[data-sidebar-backdrop]')) closeMobileSidebar();
  });

  // ---- COMMAND PALETTE -------------------------------------
  // Markup ships from web/themes/sbpp2026/core/footer.tpl. The dialog
  // is the deterministic "closed" state on first paint (`hidden` boolean
  // attribute) so a JS failure leaves the palette unreachable rather than
  // silently broken-open. Visibility is mirrored to
  // `[data-palette-open="true"|"false"]` so e2e selectors don't have to
  // probe the `hidden` property directly.
  const PALETTE_DEBOUNCE_MS = 200;
  const PALETTE_LIMIT = 8;
  const PALETTE_MIN_QUERY = 2;

  const palette = /** @type {HTMLDialogElement | null} */ (document.getElementById('palette-root'));
  const paletteInput = /** @type {HTMLInputElement | null} */ (document.getElementById('palette-input'));
  const paletteResults = document.getElementById('palette-results');
  const paletteSupportsDialog = !!(palette && typeof palette.showModal === 'function');

  /**
   * Mirror palette visibility into a data-attribute for tests + CSS.
   * @param {boolean} open
   * @returns {void}
   */
  function setPaletteOpenAttr(open) {
    if (!palette) return;
    palette.dataset.paletteOpen = open ? 'true' : 'false';
  }

  /** @returns {void} */
  function openPalette() {
    if (!palette) return;
    palette.hidden = false;
    if (paletteSupportsDialog && !palette.open) {
      try { palette.showModal(); } catch (_e) { /* already open or unsupported state */ }
    }
    setPaletteOpenAttr(true);
    setTimeout(() => {
      if (paletteInput) {
        paletteInput.value = '';
        paletteInput.focus();
      }
    }, 10);
    void renderPaletteResults('');
  }

  /** @returns {void} */
  function closePalette() {
    if (!palette) return;
    if (paletteSupportsDialog && palette.open) {
      try { palette.close(); } catch (_e) { /* already closed */ }
    }
    palette.hidden = true;
    setPaletteOpenAttr(false);
    delete palette.dataset.loading;
  }

  document.addEventListener('keydown', (/** @type {KeyboardEvent} */ e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      if (palette && palette.hidden) openPalette(); else closePalette();
    } else if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
      handlePaletteCopyShortcut(e);
    } else if (e.key === 'Escape') {
      closePalette();
      closeDrawer();
      closeMobileSidebar();
    }
  });

  /**
   * #1207 DET-2: in the open palette, Ctrl/Cmd+Enter on a focused
   * player result row copies the row's SteamID to the clipboard and
   * surfaces a confirmation toast. The kbd hint group rendered into
   * each row by `renderPaletteResults` advertises this affordance
   * server-side ("Ctrl+Enter to copy steamid"); this handler is the
   * paired behaviour. No-op when the palette is closed or when the
   * focused element isn't a player-kind palette result, so the
   * binding is scoped to the surface the hint advertises and won't
   * hijack Ctrl+Enter elsewhere (e.g. notes textarea, edit-ban form).
   * @param {KeyboardEvent} e
   * @returns {void}
   */
  function handlePaletteCopyShortcut(e) {
    if (!palette || palette.hidden) return;
    const active = document.activeElement;
    if (!(active instanceof HTMLElement)) return;
    const row = active.closest('[data-testid="palette-result"][data-result-kind="ban"]');
    if (!(row instanceof HTMLElement)) return;
    const value = row.dataset.steamid;
    if (!value) return;
    e.preventDefault();
    if (!navigator.clipboard) {
      showToast({ kind: 'error', title: 'Clipboard unavailable' });
      return;
    }
    // #1207 DET-2 follow-up (review finding 2): an IP-only ban
    // (`type === 1`, empty `authid`) lands here with `data-steamid`
    // actually holding the IP — bans.search returns `steam` (= authid)
    // and `ip` separately, and `renderPaletteResults` falls back to the
    // IP when authid is empty. Title the toast based on the value's
    // shape so the user sees the right label in the rare IP-only case.
    // The regex matches both Steam2 (`STEAM_X:Y:Z`) and Steam3
    // (`[U:1:N]`) forms — anything that isn't a SteamID is treated as
    // an IP for labelling purposes.
    const isSteam = /^(STEAM_|\[U:)/.test(value);
    const titleSuccess = isSteam ? 'SteamID copied' : 'IP copied';
    const titleError = isSteam ? "Couldn\u2019t copy SteamID" : "Couldn\u2019t copy IP";
    void navigator.clipboard.writeText(value).then(
      () => showToast({ kind: 'success', title: titleSuccess, body: value }),
      () => showToast({ kind: 'error', title: titleError }),
    );
  }

  document.addEventListener('click', (/** @type {MouseEvent} */ e) => {
    const target = /** @type {Element | null} */ (e.target);
    // `data-palette-open` does double duty: the topbar trigger button
    // (core/title.tpl) carries it as the open hook, and the dialog itself
    // carries it as a `"true"|"false"` open-state mirror for tests + CSS.
    // Without the `!.palette` guard, a click on the input or any result
    // row inside the open dialog would re-trigger openPalette(), which
    // wipes the input mid-search via the focus-then-clear setTimeout.
    if (target && target.closest('[data-palette-open]') && !target.closest('.palette')) openPalette();
    // The close-handler does NOT need a `!.palette` guard: the X button
    // sits inside `.palette` and exposes `data-palette-close` /
    // `data-testid="palette-close"` precisely so it can close. Backdrop
    // clicks (`target === palette`) are already covered by the dialog's
    // own handler below, so excluding `.palette` here just made the X
    // dead — the contract `data-testid="palette-close"` advertises has
    // to actually close the dialog or future Playwright tests would
    // silently pass against a no-op.
    if (target && target.closest('[data-palette-close]')) closePalette();
  });

  // <dialog>'s native backdrop swallows clicks until they reach the dialog
  // itself; if the click lands on the dialog (i.e. the backdrop area) and
  // not on the inner panel, treat it as a close request.
  if (palette) {
    palette.addEventListener('click', (/** @type {MouseEvent} */ e) => {
      const target = /** @type {Element | null} */ (e.target);
      if (target === palette) closePalette();
    });
    palette.addEventListener('cancel', (/** @type {Event} */ e) => {
      // ESC inside <dialog> fires `cancel` instead of bubbling keydown out.
      e.preventDefault();
      closePalette();
    });
  }

  /** @type {ReturnType<typeof setTimeout> | null} */
  let paletteTimer = null;
  /** Monotonic call counter so a stale fetch can't overwrite a newer one. */
  let paletteCallSeq = 0;
  if (paletteInput) {
    paletteInput.addEventListener('input', (/** @type {Event} */ e) => {
      if (paletteTimer) clearTimeout(paletteTimer);
      const value = /** @type {HTMLInputElement} */ (e.target).value;
      paletteTimer = setTimeout(() => { void renderPaletteResults(value); }, PALETTE_DEBOUNCE_MS);
    });
  }

  /** @typedef {{icon: string, label: string, href: string}} NavItem */

  /**
   * Parse the server-rendered, permission-filtered palette nav set
   * out of `<script type="application/json" id="palette-actions">`.
   *
   * Pre-#1304 this list was a hardcoded `NAV_ITEMS` array right here
   * in JS, so every visitor (logged-out, partial-permission admins)
   * saw the admin-only entries (`Admin panel`, `Add ban`) alongside
   * the public ones, then got bounced off the "you must be logged
   * in" / 403 surface when they clicked one. The blob is now built
   * by `Sbpp\View\PaletteActions::for($userbank)` server-side
   * (see `web/pages/core/footer.php` + `core/footer.tpl`); this
   * function is just the consumer.
   *
   * Falls back to an empty list when:
   *   - the script tag is missing (chrome-only render in test
   *     contexts where the chrome was injected without a page
   *     handler having run footer.php — defensive, the palette
   *     just renders the player-search half),
   *   - the JSON is malformed (a broken catalog entry would
   *     otherwise silently nuke the palette; surfacing as empty
   *     keeps the player-search half working).
   *
   * @returns {NavItem[]}
   */
  function loadNavItems() {
    const blob = document.getElementById('palette-actions');
    if (!blob) return [];
    try {
      const parsed = JSON.parse(blob.textContent || '[]');
      if (!Array.isArray(parsed)) return [];
      // Defensive shape filter — drop entries missing any of the
      // three keys theme.js renders. Server-side PaletteActions
      // always emits the full triple, but the wire format is the
      // contract and a future PR can't accidentally trim a key
      // without breaking the tests too.
      return parsed.filter((n) =>
        n && typeof n.icon === 'string'
          && typeof n.label === 'string'
          && typeof n.href === 'string'
      );
    } catch (_e) {
      return [];
    }
  }

  /** @type {NavItem[]} */
  const NAV_ITEMS = loadNavItems();

  /**
   * Render filtered navigation + player search results into the palette.
   * Player search hits `bans.search` once `q.length >= PALETTE_MIN_QUERY`
   * so the very first keypress doesn't fire a request. Stale calls are
   * dropped via a monotonic sequence counter.
   * @param {string} q
   * @returns {Promise<void>}
   */
  async function renderPaletteResults(q) {
    if (!paletteResults) return;
    const ql = (q || '').toLowerCase();
    const nav = NAV_ITEMS.filter((n) => !ql || n.label.toLowerCase().includes(ql));

    const sectionStyle = 'padding:4px 8px;font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-faint);font-weight:600';
    const navHtml = nav.length
      ? '<div style="' + sectionStyle + '">Navigate</div>'
        + nav.map((n) => '<a href="' + n.href + '" class="sidebar__link" data-testid="palette-result" data-result-kind="nav"><i data-lucide="' + n.icon + '"></i><span>' + escapeHtml(n.label) + '</span></a>').join('')
      : '';

    // Render nav immediately so the palette feels responsive while the
    // network call (if any) is in flight.
    paletteResults.innerHTML = navHtml;
    if (window.lucide) window.lucide.createIcons();

    if (ql.length < PALETTE_MIN_QUERY) {
      if (palette) delete palette.dataset.loading;
      return;
    }

    const seq = ++paletteCallSeq;
    if (palette) palette.dataset.loading = 'true';
    /** @type {SbApiEnvelope | null} */
    let envelope = null;
    try {
      // Actions.BansSearch is autogenerated from
      // web/api/handlers/_register.php — never inline 'bans.search' as
      // a string literal here, the api-contract gate exists to catch that.
      envelope = await sb.api.call(Actions.BansSearch, { q: ql, limit: PALETTE_LIMIT });
    } catch (_err) {
      envelope = null;
    }

    // Bail out if a newer keypress already kicked off another fetch.
    if (seq !== paletteCallSeq) return;
    if (palette) delete palette.dataset.loading;

    /** @type {Array<{bid:number,name:string,steam:string,ip:string,type:number}>} */
    let bans = [];
    if (envelope && envelope.ok && envelope.data && Array.isArray(envelope.data.bans)) {
      bans = envelope.data.bans;
    }

    // #1207 DET-2: each player result row carries:
    //   - data-drawer-bid="<bid>"  → bare Enter / click hands off to the
    //     existing `[data-drawer-bid]` click delegate (loadDrawer + close
    //     the palette so the drawer isn't stacked behind it),
    //   - data-steamid="<steam>"   → the Ctrl/Cmd+Enter handler reads this
    //     and copies it via navigator.clipboard.writeText,
    //   - a `.palette__row-hints` kbd group surfacing both keys so the
    //     interactions are discoverable. The kbds are server-rendered
    //     in the non-Mac form ("Enter", "Ctrl"); applyPlatformHints()
    //     swaps `[data-enterkey]` → ⏎ and `[data-modkey]` → ⌘ at boot
    //     and on every render below so Mac users see the platform-native
    //     glyphs without re-rendering (#1184).
    //
    // The href is preserved as a graceful-degradation fallback —
    // middle-click / Cmd+click still navigates to a name-filtered banlist
    // for users who want to expand the result rather than open the drawer.
    const playersHtml = bans.length
      ? '<div style="' + sectionStyle + ';margin-top:8px">Players</div>'
        + bans.map((b) => {
            const href = '?p=banlist&advType=name&advSearch=' + encodeURIComponent(b.name);
            const steamid = b.steam || b.ip || '';
            return '<a href="' + href + '"'
              + ' class="sidebar__link palette__row"'
              + ' data-testid="palette-result"'
              + ' data-result-kind="ban"'
              + ' data-id="' + escapeHtml(String(b.bid)) + '"'
              + ' data-drawer-bid="' + escapeHtml(String(b.bid)) + '"'
              + ' data-steamid="' + escapeHtml(steamid) + '"'
              + ' style="height:auto;padding:8px">'
              +   '<i data-lucide="user" style="flex-shrink:0"></i>'
              +   '<div class="palette__row-meta">'
              +     '<div class="text-sm truncate">' + escapeHtml(b.name || '(no name)') + '</div>'
              +     '<div class="font-mono text-xs text-muted truncate">' + escapeHtml(steamid) + '</div>'
              +   '</div>'
              +   '<div class="palette__row-hints" data-testid="palette-row-hints" aria-hidden="true">'
              +     '<span class="palette__row-hint">'
              +       '<kbd data-enterkey>Enter</kbd>'
              +       '<span class="palette__row-hint-label"> to open drawer</span>'
              +     '</span>'
              +     '<span class="palette__row-hint" data-palette-hint="copy">'
              +       '<kbd data-modkey>Ctrl</kbd>+<kbd data-enterkey>Enter</kbd>'
              +       '<span class="palette__row-hint-label"> to copy steamid</span>'
              +     '</span>'
              +   '</div>'
              + '</a>';
          }).join('')
      : '<div class="text-xs text-muted" style="padding:8px">No matching bans.</div>';

    paletteResults.innerHTML = navHtml + playersHtml;
    if (window.lucide) window.lucide.createIcons();
    // Result rows render after first paint, so the boot-time
    // applyPlatformHints() pass missed the dynamically-created kbds
    // inside `.palette__row-hints`. Re-run the swap so Mac users see
    // ⏎ / ⌘ glyphs instead of the server-rendered "Enter" / "Ctrl"
    // text on every re-render (debounce + fetch round-trip).
    applyPlatformHints();
  }

  // ---- DRAWER ----------------------------------------------
  // Click target convention: any element with `[data-drawer-bid="N"]`
  // opens the drawer for ban N (focal kind = 'ban'); any element with
  // `[data-drawer-cid="N"]` opens it for comm-block N (focal kind =
  // 'comm'). For backwards-compat with the handoff template, the
  // legacy `[data-drawer-href]` attribute is also honoured — its
  // `id=N` query param is read as a bid when the URL says `p=banlist`
  // and as a cid when the URL says `p=commslist`. The state attribute
  // `data-drawer-open="true"` on the container is the testability hook —
  // CI can observe deterministic open/close without chasing CSS
  // visibility heuristics. `data-loading="true"` is set while the
  // fetch is in flight so an integration test can wait on the
  // post-load shape without a sleep.
  const drawerRoot = /** @type {HTMLElement | null} */ (document.getElementById('drawer-root'));

  /**
   * Show the drawer container with the given inner HTML. Caller is
   * responsible for the inner HTML being safe (built from this file's
   * builders, which run every dynamic value through escapeHtml(), or
   * from a trusted server-rendered partial).
   * @param {string} html
   * @returns {void}
   */
  function showDrawer(html) {
    if (!drawerRoot) return;
    drawerRoot.hidden = false;
    drawerRoot.dataset.drawerOpen = 'true';
    drawerRoot.innerHTML = '<div class="drawer-backdrop" data-drawer-close></div><div class="drawer" role="dialog" aria-label="Player details">' + html + '</div>';
    if (window.lucide) window.lucide.createIcons();
  }

  /** @returns {void} */
  function closeDrawer() {
    if (!drawerRoot) return;
    drawerRoot.hidden = true;
    drawerRoot.dataset.drawerOpen = 'false';
    delete drawerRoot.dataset.loading;
    drawerRoot.innerHTML = '';
    drawerDetail = null;
    drawerKind = null;
  }

  /**
   * Public entry point preserved for legacy callers (window.SBPP.openDrawer).
   * @param {string} html
   * @returns {void}
   */
  function openDrawer(html) { showDrawer(html); }

  /**
   * @typedef {{kind: 'ban', id: number} | {kind: 'comm', id: number}} DrawerKey
   */

  /**
   * Resolve a click target into a focal key (kind + id), returning null
   * when the trigger doesn't carry one. Accepts:
   *   - `data-drawer-bid="123"`               (ban focal — preferred form)
   *   - `data-drawer-cid="123"`               (comm focal — preferred form)
   *   - `data-drawer-href="?p=banlist&id=123"`   (handoff template, ban)
   *   - `data-drawer-href="?p=commslist&id=123"` (handoff template, comm)
   * Mixed `bid` + `cid` attributes are not allowed; the bid wins to
   * preserve the historical contract for legacy templates.
   * @param {Element} el
   * @returns {DrawerKey | null}
   */
  function keyFromTrigger(el) {
    const dataset = /** @type {HTMLElement} */ (el).dataset;
    const bidAttr = dataset.drawerBid;
    if (bidAttr && /^\d+$/.test(bidAttr)) {
      return { kind: 'ban', id: parseInt(bidAttr, 10) };
    }
    const cidAttr = dataset.drawerCid;
    if (cidAttr && /^\d+$/.test(cidAttr)) {
      return { kind: 'comm', id: parseInt(cidAttr, 10) };
    }
    const href = dataset.drawerHref;
    if (typeof href === 'string') {
      const idMatch = href.match(/[?&]id=(\d+)/);
      if (idMatch) {
        const id = parseInt(idMatch[1], 10);
        // Disambiguate ban vs. comm focal by the `p=` segment of the
        // legacy handoff URL. Default to ban when the URL is malformed
        // so old links keep working.
        const pageMatch = href.match(/[?&]p=([a-z]+)/i);
        const page = pageMatch ? pageMatch[1].toLowerCase() : 'banlist';
        if (page === 'commslist') return { kind: 'comm', id };
        return { kind: 'ban', id };
      }
    }
    return null;
  }

  /**
   * @param {string | null | undefined} mode
   * @returns {string}
   */
  function stateLabel(mode) {
    switch (mode) {
      case 'permanent': return 'Permanent';
      case 'active':    return 'Active';
      case 'expired':   return 'Expired';
      case 'unbanned':  return 'Unbanned';
      case 'unmuted':   return 'Unmuted';
      default:          return 'Unknown';
    }
  }

  /**
   * Cached `bans.detail` or `comms.detail` payload for the currently-
   * open drawer. Pane builders (`renderHistoryPane`, `renderCommsPane`,
   * `renderNotesPane`) read this for context (the player's steam_id to
   * look up history / comms / notes against). Cleared on
   * `closeDrawer()`.
   * @type {Object | null}
   */
  let drawerDetail = null;

  /**
   * Focal kind for the open drawer ('ban' | 'comm'). Drives the header
   * label ("Ban #N" vs "Comm #N") and the Overview row builder; the
   * History / Comms tabs are SHARED — both fetch via the player's
   * authid (steam_id), not the focal record id, so a player whose comm
   * was opened still sees their full ban history under the History
   * tab. Cleared on `closeDrawer()`.
   * @type {'ban' | 'comm' | null}
   */
  let drawerKind = null;

  /**
   * Build the rendered drawer HTML for a successful bans.detail OR
   * comms.detail envelope. The drawer is a four-tab UI per #1165:
   *   Overview — id grid + (ban|block) grid + comments
   *   History  — sibling bans for the same player    (lazy, by authid)
   *   Comms    — gags/mutes for the same player       (lazy, by authid)
   *   Notes    — admin-only scratchpad               (lazy, gated by data.notes_visible)
   *
   * Focal kind is read from the module-scope `drawerKind` (set by
   * `loadDrawer`) and drives the header chip ("Ban #N" vs "Comm #N")
   * + the Overview row builder. The History / Comms tabs are SHARED
   * across both focal kinds because both lazy fetches now key on the
   * player's authid, not the focal record id.
   *
   * Every value that ends up in innerHTML is funnelled through
   * escapeHtml(); the only literal HTML is the static layout we author
   * here. The History/Comms/Notes panes start empty (a single
   * `[data-pane-empty]` child) and are populated on first activation
   * by `loadPaneIfNeeded()`.
   * @param {Object} data
   * @returns {string}
   */
  function renderDrawerBody(data) {
    const player = (data && data.player) || {};
    const notesVisible = !!(data && data.notes_visible);
    const isComm = drawerKind === 'comm';
    const focalLabel = isComm ? 'Comm' : 'Ban';
    const focalId = isComm ? data.cid : data.bid;

    const headerHtml =
      '<header class="drawer__header" style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;border-bottom:1px solid var(--border)">'
      +   '<div>'
      +     '<div class="text-xs text-faint" style="text-transform:uppercase;letter-spacing:0.06em">' + focalLabel + ' #' + escapeHtml(String(focalId)) + '</div>'
      +     '<h2 class="font-semibold" style="margin:0.125rem 0 0;font-size:1.125rem">' + escapeHtml(player.name || '(unknown)') + '</h2>'
      +   '</div>'
      +   '<button class="btn btn--ghost btn--icon" type="button" data-drawer-close aria-label="Close">'
      +     '<i data-lucide="x"></i>'
      +   '</button>'
      + '</header>';

    /** @type {Array<{id: string, label: string}>} */
    const tabDefs = [
      { id: 'overview', label: 'Overview' },
      { id: 'history',  label: 'History'  },
      { id: 'comms',    label: 'Comms'    },
    ];
    if (notesVisible) tabDefs.push({ id: 'notes', label: 'Notes' });

    const tablistHtml =
      '<div role="tablist" aria-label="Player drawer sections" class="drawer__tabs" data-testid="drawer-tablist" style="display:flex;gap:0;border-bottom:1px solid var(--border);padding:0 0.75rem;overflow-x:auto">'
      + tabDefs.map((tab, i) => {
          const selected = i === 0;
          return '<button type="button"'
            + ' role="tab"'
            + ' id="drawer-tab-' + tab.id + '"'
            + ' data-testid="drawer-tab-' + tab.id + '"'
            + ' data-drawer-tab="' + tab.id + '"'
            + ' aria-controls="drawer-panel-' + tab.id + '"'
            + ' aria-selected="' + (selected ? 'true' : 'false') + '"'
            + ' tabindex="' + (selected ? '0' : '-1') + '"'
            + ' style="appearance:none;background:none;border:0;padding:0.625rem 0.875rem;font:inherit;color:inherit;cursor:pointer;border-bottom:2px solid ' + (selected ? 'var(--accent)' : 'transparent') + ';font-weight:' + (selected ? '600' : '400') + '">'
            + escapeHtml(tab.label)
            + '</button>';
        }).join('')
      + '</div>';

    const overviewHtml =
      '<div role="tabpanel"'
      + ' id="drawer-panel-overview"'
      + ' data-testid="drawer-panel-overview"'
      + ' data-drawer-panel="overview"'
      + ' aria-labelledby="drawer-tab-overview"'
      + ' tabindex="0"'
      + ' style="padding:1rem 1.25rem;display:flex;flex-direction:column;gap:1rem;overflow-y:auto;flex:1">'
      +   renderOverviewPane(data)
      + '</div>';

    /**
     * Build a hidden, empty tabpanel placeholder. The lazy loader fills
     * it in on first activation. `hidden` keeps the panel out of the
     * a11y tree until we open it.
     *
     * Note: inactive panels start with `display:none` rather than
     * `display:flex`. Inline `display:flex` would otherwise override the
     * `[hidden]` UA rule (`display:none`) and every panel would render
     * stacked on top of the active one. `activateDrawerTab` flips both
     * attributes together when a tab is activated.
     *
     * Pre-fix the placeholder was a single muted `Loading…` text node.
     * That worked but read as static text — the user never saw "the
     * panel is doing something" beyond the eight-character label.
     * `renderPaneSkeleton()` (below) wraps the same `[data-pane-empty]`
     * + `aria-busy` testability contract around two `.skel` shimmer
     * rows so History / Comms / Notes all share the visual vocabulary
     * the drawer header uses for its own loading state.
     *
     * @param {string} id
     * @returns {string}
     */
    const lazyPanel = (id) =>
      '<div role="tabpanel"'
      + ' id="drawer-panel-' + id + '"'
      + ' data-testid="drawer-panel-' + id + '"'
      + ' data-drawer-panel="' + id + '"'
      + ' aria-labelledby="drawer-tab-' + id + '"'
      + ' tabindex="0"'
      + ' hidden'
      + ' style="padding:1rem 1.25rem;display:none;flex-direction:column;gap:0.75rem;overflow-y:auto;flex:1">'
      +   renderPaneSkeleton()
      + '</div>';

    let panelsHtml = overviewHtml + lazyPanel('history') + lazyPanel('comms');
    if (notesVisible) panelsHtml += lazyPanel('notes');

    return headerHtml + tablistHtml + panelsHtml;
  }

  /**
   * Render the Overview pane content (id grid + focal-record grid +
   * comments). Extracted from the pre-#1165 single-body drawer so the
   * new tabbed UI can drop it into the Overview panel; the focal-record
   * grid is now kind-aware so a comm-focal drawer renders the
   * comm-block fields under `[data-testid="drawer-block"]` instead of
   * the ban fields under `[data-testid="drawer-ban"]`. Both shapes
   * carry the same field vocabulary (state / reason / length / when)
   * so the visual treatment is consistent across the two focal kinds.
   *
   * @param {Object} data
   * @returns {string}
   */
  function renderOverviewPane(data) {
    const player = (data && data.player) || {};
    const admin  = (data && data.admin)  || {};
    const server = (data && data.server) || {};
    const comments = Array.isArray(data && data.comments) ? data.comments : [];
    const commentsVisible = !!(data && data.comments_visible);
    const isComm = drawerKind === 'comm';
    const focal  = isComm
      ? ((data && data.block) || {})
      : ((data && data.ban) || {});

    /** @type {Array<[string, string]>} */
    const idRows = [];
    if (player.steam_id)     idRows.push(['SteamID',    String(player.steam_id)]);
    if (player.steam_id_3)   idRows.push(['Steam3',     String(player.steam_id_3)]);
    if (player.community_id) idRows.push(['Community',  String(player.community_id)]);
    if (player.ip)           idRows.push(['IP',         String(player.ip)]);
    if (player.country)      idRows.push(['Country',    String(player.country)]);

    const idHtml = idRows.length === 0 ? '' :
      '<dl class="drawer__ids" data-testid="drawer-ids" style="display:grid;grid-template-columns:6rem 1fr;gap:0.25rem 0.75rem;margin:0;font-size:0.8125rem">'
      + idRows.map((row) =>
          '<dt class="text-faint">' + escapeHtml(row[0]) + '</dt>'
          + '<dd class="font-mono" style="margin:0">' + escapeHtml(row[1])
          + '<button class="btn btn--ghost btn--icon btn--xs" type="button" data-copy="' + escapeHtml(row[1]) + '" title="Copy" style="margin-left:0.25rem">'
          +   '<i data-lucide="copy" style="width:12px;height:12px"></i>'
          + '</button>'
          + '</dd>'
        ).join('')
      + '</dl>';

    /** @type {Array<[string, string]>} */
    const focalRows = [];
    if (isComm && focal.type_label) focalRows.push(['Type', String(focal.type_label)]);
    focalRows.push(['State', stateLabel(/** @type {string} */ (focal.state))]);
    focalRows.push(['Reason', String(focal.reason || '(none)')]);
    focalRows.push(['Length', String(focal.length_human || '')]);
    if (isComm) {
      focalRows.push(['Started', String(focal.started_at_human || '')]);
    } else {
      focalRows.push(['Banned',  String(focal.banned_at_human || '')]);
    }
    if (focal.expires_at_human) focalRows.push(['Expires', String(focal.expires_at_human)]);
    if (focal.removed_at_human) focalRows.push(['Removed', String(focal.removed_at_human)]);
    if (focal.removed_by)       focalRows.push(['Removed by', String(focal.removed_by)]);
    const unbanReasonField = isComm ? focal.unblock_reason : focal.unban_reason;
    if (unbanReasonField) focalRows.push([isComm ? 'Unblock reason' : 'Unban reason', String(unbanReasonField)]);
    if (admin.name)  focalRows.push(['Admin', String(admin.name)]);
    if (server.name) focalRows.push(['Server', String(server.name)]);

    const focalTestid = isComm ? 'drawer-block' : 'drawer-ban';
    const focalClass  = isComm ? 'drawer__block' : 'drawer__ban';
    const focalHtml =
      '<dl class="' + focalClass + '" data-testid="' + focalTestid + '" style="display:grid;grid-template-columns:6rem 1fr;gap:0.25rem 0.75rem;margin:0;font-size:0.8125rem">'
      + focalRows.map((row) =>
          '<dt class="text-faint">' + escapeHtml(row[0]) + '</dt>'
          + '<dd style="margin:0">' + escapeHtml(row[1]) + '</dd>'
        ).join('')
      + '</dl>';

    let commentsHtml = '';
    if (commentsVisible) {
      commentsHtml = '<section data-testid="drawer-comments" style="margin-top:0.5rem">'
        + '<h3 class="text-xs text-faint" style="text-transform:uppercase;letter-spacing:0.06em;margin:0 0 0.5rem">Comments</h3>'
        + (comments.length === 0
          ? '<p class="text-sm text-muted" style="margin:0">No comments.</p>'
          : '<ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0.625rem">'
            + comments.map((c) =>
                '<li style="border:1px solid var(--border);border-radius:var(--radius-md);padding:0.625rem 0.75rem;background:var(--bg-surface)">'
                + '<div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--text-muted);margin-bottom:0.25rem">'
                +   '<span class="font-medium">' + escapeHtml(c.author || 'unknown') + '</span>'
                +   '<span>' + escapeHtml(c.added_human || '') + '</span>'
                + '</div>'
                + '<div class="text-sm" style="white-space:pre-wrap">' + escapeHtml(c.text || '') + '</div>'
                + '</li>'
              ).join('')
            + '</ul>')
        + '</section>';
    }

    return idHtml + focalHtml + commentsHtml;
  }

  /**
   * Build the lazy-pane loading placeholder. Mirrors the drawer
   * header's `renderDrawerLoading()` shape with `.skel` shimmer rows
   * so a user opening History / Comms / Notes sees the same
   * "something's loading" vocabulary as the initial drawer open.
   *
   * The `data-pane-empty` attribute is the existing contract for the
   * unloaded placeholder (the `refreshNotesPane` reset path keys off
   * it implicitly when it overwrites innerHTML); `aria-busy="true"`
   * announces the busy state to AT users.
   *
   * Why NO `data-skeleton` here
   * ---------------------------
   * The page-level waiter in `web/tests/e2e/pages/_base.ts` blocks
   * until `'[data-loading="true"], [data-skeleton]:not([hidden])'`
   * returns no nodes. The lazy panels start with `hidden` on the
   * tabpanel *parent*, but `:not([hidden])` only checks the matched
   * element's own attribute — a `data-skeleton` block nested inside
   * a `hidden` tabpanel would still match and stall every page-load
   * wait that runs after the drawer opens. Confine `[data-skeleton]`
   * to surfaces where the marker itself (or its direct container)
   * carries the visibility toggle (the drawer header skeleton lives
   * under `#drawer-root[data-loading="true"]`, which IS a terminal
   * marker, so it's safe there).
   *
   * @returns {string}
   */
  function renderPaneSkeleton() {
    return '<div data-pane-empty aria-busy="true" aria-label="Loading\u2026" style="display:flex;flex-direction:column;gap:0.625rem">'
      +   '<div class="skel" style="height:0.875rem;width:40%"></div>'
      +   '<div class="skel" style="height:0.875rem"></div>'
      +   '<div class="skel" style="height:0.875rem;width:70%"></div>'
      + '</div>';
  }

  /**
   * Render the History pane content from a `bans.player_history`
   * envelope. Empty state matches the issue's acceptance criteria
   * verbatim ("No prior bans on file") so the e2e spec can latch on.
   * @param {Array<any>} items
   * @returns {string}
   */
  function renderHistoryPane(items) {
    const heading = '<h3 data-testid="drawer-history-heading" class="text-xs text-faint" style="text-transform:uppercase;letter-spacing:0.06em;margin:0 0 0.5rem">Other bans on file</h3>';
    if (!items.length) {
      return heading + '<p class="text-sm text-muted" style="margin:0">No prior bans on file.</p>';
    }
    return heading
      + '<ul data-testid="drawer-history-list" style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0.625rem">'
      + items.map((b) =>
          '<li data-testid="drawer-history-item" data-bid="' + escapeHtml(String(b.bid)) + '" style="border:1px solid var(--border);border-radius:var(--radius-md);padding:0.625rem 0.75rem;background:var(--bg-surface)">'
          + '<div style="display:flex;justify-content:space-between;align-items:baseline;gap:0.5rem;font-size:0.75rem;color:var(--text-muted);margin-bottom:0.25rem">'
          +   '<span class="font-medium">' + escapeHtml(stateLabel(b.state)) + ' \u00b7 ' + escapeHtml(b.length_human || '') + '</span>'
          +   '<span>' + escapeHtml(b.banned_at_human || '') + '</span>'
          + '</div>'
          + '<div class="text-sm" style="white-space:pre-wrap">' + escapeHtml(b.reason || '(no reason)') + '</div>'
          + (b.admin_name || b.server_name
            ? '<div class="text-xs text-faint" style="margin-top:0.25rem">'
              + (b.admin_name ? 'by ' + escapeHtml(b.admin_name) : '')
              + (b.admin_name && b.server_name ? ' \u00b7 ' : '')
              + (b.server_name ? escapeHtml(b.server_name) : '')
              + '</div>'
            : '')
          + '</li>'
        ).join('')
      + '</ul>';
  }

  /**
   * Render the Comms pane content from a `comms.player_history`
   * envelope. Same shape as History; "No prior comm-blocks on file"
   * empty state.
   * @param {Array<any>} items
   * @returns {string}
   */
  function renderCommsPane(items) {
    const heading = '<h3 data-testid="drawer-comms-heading" class="text-xs text-faint" style="text-transform:uppercase;letter-spacing:0.06em;margin:0 0 0.5rem">Comm-blocks on file</h3>';
    if (!items.length) {
      return heading + '<p class="text-sm text-muted" style="margin:0">No prior comm-blocks on file.</p>';
    }
    return heading
      + '<ul data-testid="drawer-comms-list" style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0.625rem">'
      + items.map((c) =>
          '<li data-testid="drawer-comms-item" data-bid="' + escapeHtml(String(c.bid)) + '" style="border:1px solid var(--border);border-radius:var(--radius-md);padding:0.625rem 0.75rem;background:var(--bg-surface)">'
          + '<div style="display:flex;justify-content:space-between;align-items:baseline;gap:0.5rem;font-size:0.75rem;color:var(--text-muted);margin-bottom:0.25rem">'
          +   '<span class="font-medium">' + escapeHtml(c.type_label || 'Block') + ' \u00b7 ' + escapeHtml(stateLabel(c.state)) + ' \u00b7 ' + escapeHtml(c.length_human || '') + '</span>'
          +   '<span>' + escapeHtml(c.created_human || '') + '</span>'
          + '</div>'
          + '<div class="text-sm" style="white-space:pre-wrap">' + escapeHtml(c.reason || '(no reason)') + '</div>'
          + (c.admin_name
            ? '<div class="text-xs text-faint" style="margin-top:0.25rem">by ' + escapeHtml(c.admin_name) + '</div>'
            : '')
          + '</li>'
        ).join('')
      + '</ul>';
  }

  /**
   * Render the Notes pane content. Includes a `<form>` with a textarea
   * + Add button for new notes; existing notes render as a list with a
   * per-row Delete button. The form posts via `sb.api.call`
   * (`notes.add` / `notes.delete`); the dispatcher requires admin and
   * the X-CSRF-Token header is set automatically by `sb.api.call`.
   * @param {Array<any>} items
   * @returns {string}
   */
  function renderNotesPane(items) {
    const heading = '<h3 data-testid="drawer-notes-heading" class="text-xs text-faint" style="text-transform:uppercase;letter-spacing:0.06em;margin:0 0 0.5rem">Notes</h3>';

    const formHtml =
      '<form data-notes-add data-testid="drawer-notes-add" style="display:flex;flex-direction:column;gap:0.5rem;margin:0">'
      + '<label class="text-xs text-faint" for="drawer-notes-body">Add a note (admins only)</label>'
      + '<textarea id="drawer-notes-body" name="body" rows="3" maxlength="4000"'
      +   ' style="width:100%;padding:0.5rem;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--bg-surface);color:inherit;font:inherit;resize:vertical"'
      +   ' placeholder="Pin context that should follow this player"></textarea>'
      + '<div style="display:flex;justify-content:flex-end">'
      +   '<button type="submit" class="btn btn--primary" data-testid="drawer-notes-submit">Add note</button>'
      + '</div>'
      + '</form>';

    const listHtml = !items.length
      ? '<p class="text-sm text-muted" data-testid="drawer-notes-empty" style="margin:0">No notes on file.</p>'
      : '<ul data-testid="drawer-notes-list" style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0.625rem">'
        + items.map((n) =>
            '<li data-testid="drawer-notes-item" data-nid="' + escapeHtml(String(n.nid)) + '" style="border:1px solid var(--border);border-radius:var(--radius-md);padding:0.625rem 0.75rem;background:var(--bg-surface)">'
            + '<div style="display:flex;justify-content:space-between;align-items:baseline;gap:0.5rem;font-size:0.75rem;color:var(--text-muted);margin-bottom:0.25rem">'
            +   '<span class="font-medium">' + escapeHtml(n.author || 'unknown') + '</span>'
            +   '<span style="display:flex;gap:0.5rem;align-items:center">'
            +     '<span>' + escapeHtml(n.created_human || '') + '</span>'
            +     '<button type="button" class="btn btn--ghost btn--icon" data-notes-delete="' + escapeHtml(String(n.nid)) + '" aria-label="Delete note" title="Delete note">'
            +       '<i data-lucide="trash-2" style="width:14px;height:14px"></i>'
            +     '</button>'
            +   '</span>'
            + '</div>'
            + '<div class="text-sm" style="white-space:pre-wrap">' + escapeHtml(n.body || '') + '</div>'
            + '</li>'
          ).join('')
        + '</ul>';

    return heading + formHtml + listHtml;
  }

  /**
   * Activate the named tab inside the open drawer: flip aria-selected
   * + tabindex on the buttons, hide every panel except the matching
   * one, focus-shift to the activated tab button, and lazy-load the
   * panel's content if this is its first activation.
   * @param {string} tabId
   * @param {boolean} [moveFocus] when true, focus the tab button (used by keyboard nav)
   * @returns {void}
   */
  function activateDrawerTab(tabId, moveFocus) {
    if (!drawerRoot) return;
    const tabs = drawerRoot.querySelectorAll('[role="tab"][data-drawer-tab]');
    const panels = drawerRoot.querySelectorAll('[role="tabpanel"][data-drawer-panel]');
    tabs.forEach((/** @type {Element} */ btn) => {
      const el = /** @type {HTMLButtonElement} */ (btn);
      const active = el.dataset.drawerTab === tabId;
      el.setAttribute('aria-selected', active ? 'true' : 'false');
      el.tabIndex = active ? 0 : -1;
      el.style.borderBottomColor = active ? 'var(--accent)' : 'transparent';
      el.style.fontWeight = active ? '600' : '400';
    });
    panels.forEach((/** @type {Element} */ p) => {
      const el = /** @type {HTMLElement} */ (p);
      const active = el.dataset.drawerPanel === tabId;
      el.hidden = !active;
      // Inline `display:flex` would otherwise override the [hidden] UA
      // rule (`display:none`) so toggling `el.hidden` alone leaves every
      // panel visually stacked. Mirror the toggle inline so the panels
      // actually swap.
      el.style.display = active ? 'flex' : 'none';
    });
    if (moveFocus) {
      const target = drawerRoot.querySelector('[role="tab"][data-drawer-tab="' + tabId + '"]');
      if (target instanceof HTMLElement) target.focus();
    }
    void loadPaneIfNeeded(tabId);
  }

  /**
   * Lazy-load the tab content the first time the user activates a
   * non-Overview tab. The Overview pane is always populated server-
   * side via `bans.detail`; the others fetch their own feed.
   * @param {string} tabId
   * @returns {Promise<void>}
   */
  async function loadPaneIfNeeded(tabId) {
    if (!drawerRoot || !drawerDetail) return;
    if (tabId === 'overview') return;
    const panel = /** @type {HTMLElement | null} */ (drawerRoot.querySelector('[role="tabpanel"][data-drawer-panel="' + tabId + '"]'));
    if (!panel) return;
    if (panel.dataset.loaded === 'true' || panel.dataset.loading === 'true') return;
    panel.dataset.loading = 'true';

    const steamId = (drawerDetail.player && drawerDetail.player.steam_id) || '';
    let html = '';
    /** @type {SbApiEnvelope | null} */
    let env = null;

    try {
      if (tabId === 'history') {
        // History pane = OTHER bans on file for this player. Two
        // call-shapes per drawer kind:
        //
        //   - bans-focal: pass the focal `bid`. The handler resolves
        //     it to `(type, authid, ip)` and queries siblings, with
        //     `BA.bid <> ?` excluding the focal record (the user is
        //     already looking at it in Overview).
        //   - comm-focal: there is no focal bid, so we pass the
        //     player's `authid` instead. The handler skips the focal
        //     exclusion clause — there's nothing to exclude — and
        //     matches Steam bans only (IP-only sibling matching needs
        //     an anchor IP that the comm row doesn't carry).
        //
        // Mirroring the focal across both kinds matters for the UX
        // contract: with a single ban on file, bans-focal History
        // reads "No prior bans on file." (the empty state), and
        // comm-focal History reads "Other bans on file" listing
        // that one ban. Either shape gives the player's history at
        // a glance without rendering the focal twice on bans-focal.
        const bansParams = drawerKind === 'comm'
          ? { authid: steamId }
          : { bid: (drawerDetail && /** @type {any} */ (drawerDetail).bid) || 0 };
        env = await sb.api.call(Actions.BansPlayerHistory, bansParams);
        const items = (env && env.ok && env.data && Array.isArray(env.data.items)) ? env.data.items : [];
        html = renderHistoryPane(items);
      } else if (tabId === 'comms') {
        // Comms pane = comm-blocks on file for this player. Same
        // dual-shape contract as History above: bans-focal passes
        // the focal `bid` (handler resolves to authid, no comm to
        // exclude — comm and bans IDs live in different tables);
        // comm-focal passes the focal `cid` so the handler excludes
        // it from the sibling list.
        const commsParams = drawerKind === 'comm'
          ? { cid: (drawerDetail && /** @type {any} */ (drawerDetail).cid) || 0 }
          : { bid: (drawerDetail && /** @type {any} */ (drawerDetail).bid) || 0 };
        env = await sb.api.call(Actions.CommsPlayerHistory, commsParams);
        const items = (env && env.ok && env.data && Array.isArray(env.data.items)) ? env.data.items : [];
        html = renderCommsPane(items);
      } else if (tabId === 'notes') {
        env = await sb.api.call(Actions.NotesList, { steam_id: steamId });
        const items = (env && env.ok && env.data && Array.isArray(env.data.items)) ? env.data.items : [];
        html = renderNotesPane(items);
      }
    } catch (_e) {
      env = null;
    }

    delete panel.dataset.loading;
    if (env && env.ok) {
      panel.innerHTML = html;
      panel.dataset.loaded = 'true';
    } else {
      const msg = (env && env.error && env.error.message) || 'Couldn\u2019t load.';
      panel.innerHTML = '<p class="text-sm text-muted" style="margin:0" data-testid="drawer-pane-error">' + escapeHtml(msg) + '</p>';
    }
    if (window.lucide) window.lucide.createIcons();
  }

  /**
   * Reload the Notes pane after an add/delete mutation. Forces the
   * lazy loader to re-run by clearing `data-loaded` and dropping the
   * same `renderPaneSkeleton()` placeholder back in so the operator
   * gets the same shimmer treatment they got on first activation.
   * @returns {Promise<void>}
   */
  async function refreshNotesPane() {
    if (!drawerRoot) return;
    const panel = /** @type {HTMLElement | null} */ (drawerRoot.querySelector('[role="tabpanel"][data-drawer-panel="notes"]'));
    if (!panel) return;
    delete panel.dataset.loaded;
    panel.innerHTML = renderPaneSkeleton();
    await loadPaneIfNeeded('notes');
  }

  /**
   * Render the placeholder skeleton shown during the in-flight `bans.detail`
   * fetch. The blocks use the `.skel` class (defined in `theme.css` —
   * `linear-gradient` + `shimmer` keyframe + dark-mode override; the
   * global `prefers-reduced-motion: reduce` rule pins
   * `animation-duration` to ~0ms so reduced-motion users see the
   * static placeholder colour, not the shimmer).
   *
   * Pre-fix the function emitted `class="skeleton"` (singular, no CSS
   * rule defined), so the skeleton blocks rendered as transparent
   * zero-background divs and the drawer read as "just blank" between
   * click and bans.detail response — exactly the symptom the user
   * reported. The CSS rule the markup was meant to ride has always
   * been `.skel`, not `.skeleton`; rename the markup to match.
   *
   * `[data-testid="drawer-loading"]` is the E2E hook; `aria-busy="true"`
   * + `aria-label="Loading player details"` carry the screen-reader
   * announcement without needing a `.sr-only` CSS rule (none exists
   * in the theme).
   *
   * @returns {string}
   */
  function renderDrawerLoading() {
    return '<header class="drawer__header" data-testid="drawer-loading" aria-busy="true" aria-label="Loading player details" style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;border-bottom:1px solid var(--border)">'
      + '<div class="skel" data-skeleton style="width:8rem;height:1.25rem"></div>'
      + '<button class="btn btn--ghost btn--icon" type="button" data-drawer-close aria-label="Close">'
      + '<i data-lucide="x"></i>'
      + '</button>'
      + '</header>'
      + '<div class="drawer__body" style="padding:1.25rem;display:flex;flex-direction:column;gap:0.75rem">'
      +   '<div class="skel" data-skeleton style="height:0.875rem"></div>'
      +   '<div class="skel" data-skeleton style="height:0.875rem;width:60%"></div>'
      +   '<div class="skel" data-skeleton style="height:0.875rem;width:80%"></div>'
      + '</div>';
  }

  /**
   * Render the error state (server returned an error envelope or the
   * fetch itself failed). Headline is kind-aware so a comm-focal
   * drawer doesn't read "Couldn't load ban" on a transient error.
   * @param {string} message
   * @param {'ban' | 'comm'} kind
   * @returns {string}
   */
  function renderDrawerError(message, kind) {
    const headline = kind === 'comm' ? 'Couldn\u2019t load comm-block' : 'Couldn\u2019t load ban';
    return '<header class="drawer__header" style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;border-bottom:1px solid var(--border)">'
      +   '<h2 class="font-semibold" style="margin:0;font-size:1rem">' + headline + '</h2>'
      +   '<button class="btn btn--ghost btn--icon" type="button" data-drawer-close aria-label="Close">'
      +     '<i data-lucide="x"></i>'
      +   '</button>'
      + '</header>'
      + '<div class="drawer__body" style="padding:1.25rem">'
      +   '<p class="text-sm text-muted" style="margin:0">' + escapeHtml(message) + '</p>'
      + '</div>';
  }

  /**
   * Fetch and render the player-detail drawer for a focal record.
   * Dispatches on the focal kind to either `bans.detail` (ban focal)
   * or `comms.detail` (comm focal); both envelopes share the same
   * `player` / `admin` / `server` / `comments` shape, so the
   * downstream renderers branch only on `drawerKind` (set here before
   * `renderDrawerBody` runs).
   * @param {DrawerKey} key
   * @returns {Promise<void>}
   */
  async function loadDrawer(key) {
    if (!drawerRoot) return;
    drawerKind = key.kind;
    showDrawer(renderDrawerLoading());
    drawerRoot.dataset.loading = 'true';

    const action = key.kind === 'comm' ? Actions.CommsDetail : Actions.BansDetail;
    const params = key.kind === 'comm' ? { cid: key.id } : { bid: key.id };

    /** @type {{ ok: boolean, data?: any, error?: { code: string, message: string } }} */
    const env = window.sb && window.sb.api
      ? await window.sb.api.call(action, params)
      : { ok: false, error: { code: 'no_client', message: 'sb.api unavailable' } };

    delete drawerRoot.dataset.loading;
    if (env && env.ok && env.data) {
      drawerDetail = env.data;
      showDrawer(renderDrawerBody(env.data));
    } else {
      drawerDetail = null;
      const msg = (env && env.error && env.error.message) || 'Unknown error.';
      showDrawer(renderDrawerError(msg, key.kind));
    }
  }

  document.addEventListener('click', (/** @type {MouseEvent} */ e) => {
    const target = /** @type {Element | null} */ (e.target);
    const trigger = target && target.closest('[data-drawer-bid], [data-drawer-cid], [data-drawer-href]');
    if (trigger) {
      // #1207 DET-2 follow-up (review finding 1): graceful-degradation
      // guard for modifier-clicks. Without this, Cmd/Ctrl/Shift+left-click
      // is fired as a regular `click` event with the modifier flag set;
      // the e.preventDefault() below would suppress the browser's native
      // "open in new tab/window" default action and silently open the
      // drawer in the current tab — violating the modifier-click contract
      // every browser ships. Middle-click already worked because browsers
      // fire `auxclick` (not `click`) for non-primary buttons; this guard
      // restores the same graceful-degradation for the keyboard-modifier
      // chords. The href on every [data-drawer-bid] / [data-drawer-cid] /
      // [data-drawer-href] anchor IS the fallback path:
      //   - palette rows: `?p=banlist&advType=name&advSearch=<name>`
      //     opens a name-filtered banlist in a new tab,
      //   - banlist rows: `?p=banlist&id=<bid>` opens the banlist URL
      //     in a new tab (the panel-history shape),
      //   - commslist rows: `?p=commslist&id=<cid>` opens the commslist
      //     URL in a new tab (the panel-history shape).
      // `e.button !== 0` belt-and-suspenders for any future synthetic
      // dispatch with a non-primary button (legitimate middle/right
      // clicks reach `auxclick`, not this listener, but be defensive).
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
      const key = keyFromTrigger(trigger);
      if (key !== null) {
        e.preventDefault();
        // #1207 DET-2: when the drawer is opened from a palette result
        // row (bare Enter on a focused row fires a synthetic click that
        // bubbles here), close the palette so the drawer isn't stacked
        // behind the palette dialog. Scoped to the in-palette path via
        // `target.closest('.palette')` so non-palette drawer triggers
        // (banlist rows, history list items, etc.) keep their existing
        // behaviour — the palette is only closed when it was the source
        // of the navigation.
        if (target && target.closest('.palette')) closePalette();
        loadDrawer(key);
        return;
      }
    }

    // Tab buttons inside the open drawer activate their pane.
    const tabBtn = target && target.closest('[role="tab"][data-drawer-tab]');
    if (tabBtn instanceof HTMLElement) {
      const tabId = tabBtn.dataset.drawerTab;
      if (tabId) {
        e.preventDefault();
        activateDrawerTab(tabId, false);
        return;
      }
    }

    // Per-note delete (Notes pane). The handler enforces "own notes
    // only OR ADMIN_OWNER"; we still send the request and let the
    // server decide so the UI doesn't have to know the caller's flags.
    const delBtn = target && target.closest('[data-notes-delete]');
    if (delBtn instanceof HTMLElement) {
      const nid = parseInt(delBtn.dataset.notesDelete || '0', 10);
      if (nid > 0) {
        e.preventDefault();
        void deleteNote(nid, delBtn);
        return;
      }
    }

    // Any element marked `data-drawer-close` closes the drawer — both the
    // backdrop (sibling of .drawer) and the in-header X button (descendant
    // of .drawer) carry the attribute. The earlier `!closest('.drawer')`
    // guard predated the X button and would silently swallow its clicks.
    if (target && target.closest('[data-drawer-close]')) closeDrawer();
  });

  // Tablist arrow-key navigation. Left/Right move + activate; Home/End
  // jump to the first/last visible tab. Pattern matches the W3C ARIA
  // Authoring Practices' "manual activation" tablist; e2e specs in
  // `flows/ui/player-drawer.spec.ts` exercise the keys directly.
  document.addEventListener('keydown', (/** @type {KeyboardEvent} */ e) => {
    const active = document.activeElement;
    if (!(active instanceof HTMLElement)) return;
    if (active.getAttribute('role') !== 'tab' || !active.dataset.drawerTab) return;
    if (!drawerRoot || !drawerRoot.contains(active)) return;

    const tabs = Array.from(drawerRoot.querySelectorAll('[role="tab"][data-drawer-tab]'));
    const currentIndex = tabs.indexOf(active);
    if (currentIndex === -1) return;

    /** @type {number | null} */
    let nextIndex = null;
    switch (e.key) {
      case 'ArrowLeft':  nextIndex = (currentIndex - 1 + tabs.length) % tabs.length; break;
      case 'ArrowRight': nextIndex = (currentIndex + 1) % tabs.length;                break;
      case 'Home':       nextIndex = 0;                                                break;
      case 'End':        nextIndex = tabs.length - 1;                                  break;
      default: return;
    }
    if (nextIndex === null) return;
    e.preventDefault();
    const next = /** @type {HTMLElement} */ (tabs[nextIndex]);
    const nextId = next.dataset.drawerTab;
    if (nextId) activateDrawerTab(nextId, true);
  });

  // Notes pane: form submit -> notes.add. Delegated so the form being
  // (re-)rendered by the lazy loader keeps working.
  document.addEventListener('submit', (/** @type {SubmitEvent} */ e) => {
    const target = /** @type {Element | null} */ (e.target);
    const form = target && target.closest('[data-notes-add]');
    if (!(form instanceof HTMLFormElement)) return;
    e.preventDefault();
    void submitNoteForm(form);
  });

  /**
   * POST `notes.add` and refresh the Notes pane.
   * @param {HTMLFormElement} form
   * @returns {Promise<void>}
   */
  async function submitNoteForm(form) {
    if (!drawerDetail) return;
    const textarea = /** @type {HTMLTextAreaElement | null} */ (form.querySelector('textarea[name="body"]'));
    const body = textarea ? textarea.value.trim() : '';
    const steamId = (drawerDetail.player && drawerDetail.player.steam_id) || '';
    if (!body) {
      showToast({ kind: 'warn', title: 'Note is empty' });
      return;
    }
    if (!steamId) {
      showToast({ kind: 'error', title: 'No Steam ID on this ban' });
      return;
    }

    // Surface the busy state on the form's submit button so the operator
    // sees that the click registered while notes.add is in flight.
    const submitBtn = /** @type {HTMLButtonElement | null} */ (form.querySelector('button[type="submit"]'));
    setBusy(submitBtn, true);
    try {
      const env = await sb.api.call(Actions.NotesAdd, { steam_id: steamId, body: body });
      if (env && env.ok) {
        if (textarea) textarea.value = '';
        await refreshNotesPane();
        showToast({ kind: 'success', title: 'Note added' });
      } else {
        const msg = (env && env.error && env.error.message) || 'Couldn\u2019t add note.';
        showToast({ kind: 'error', title: 'Note not saved', body: msg });
      }
    } finally {
      // The pane re-renders on success which replaces the form node, so
      // `submitBtn` may already be detached — `setBusy` no-ops when the
      // ref is missing, but the explicit release guards the failure path
      // (and any future early-return) without leaving the button busy.
      setBusy(submitBtn, false);
    }
  }

  /**
   * POST `notes.delete` and refresh the Notes pane.
   * @param {number} nid
   * @param {HTMLElement | null} [triggerBtn]
   * @returns {Promise<void>}
   */
  async function deleteNote(nid, triggerBtn) {
    setBusy(triggerBtn || null, true);
    try {
      const env = await sb.api.call(Actions.NotesDelete, { nid: nid });
      if (env && env.ok) {
        await refreshNotesPane();
        showToast({ kind: 'success', title: 'Note deleted' });
      } else {
        const msg = (env && env.error && env.error.message) || 'Couldn\u2019t delete note.';
        showToast({ kind: 'error', title: 'Note not deleted', body: msg });
      }
    } finally {
      setBusy(triggerBtn || null, false);
    }
  }

  // ---- COPY BUTTONS ----------------------------------------
  // #1308: every panel surface that exposes a "copy this id/value" affordance
  // (banlist row's SteamID button, drawer's identity rows, future
  // history-list copy hooks, …) wires through this single document-level
  // delegate. Two failure modes the pre-#1308 implementation hid:
  //
  //   1. `navigator.clipboard` is undefined on non-secure contexts (plain
  //      HTTP, non-localhost — i.e. the typical self-hoster behind a TLS-
  //      terminating reverse proxy where the panel sees plain HTTP). The
  //      old code silently no-op'd the writeText call but fired the success
  //      toast unconditionally, so the user got "Copied to clipboard" with
  //      an empty clipboard. Fix: feature-detect `window.isSecureContext`
  //      AND `navigator.clipboard`, fall back to the hidden-textarea +
  //      `document.execCommand('copy')` pattern when either is missing.
  //   2. Even on a secure context, `writeText()` returns a Promise that can
  //      reject (permission denied, focus stolen, …). The old code dropped
  //      the Promise on the floor and toasted success regardless. Fix:
  //      `.then(success, fallback)` so a rejection drops to the same
  //      execCommand path and the toast reflects the actual outcome.
  //
  // Mirrors the shape of `handlePaletteCopyShortcut` (Ctrl/Cmd+Enter on a
  // palette row), which gets this right.
  document.addEventListener('click', (/** @type {MouseEvent} */ e) => {
    const target = /** @type {Element | null} */ (e.target);
    const c = /** @type {HTMLElement | null} */ (target && target.closest('[data-copy]'));
    if (!c) return;
    e.preventDefault();
    const value = c.dataset.copy || '';
    if (!value) return;
    if (navigator.clipboard && window.isSecureContext) {
      void navigator.clipboard.writeText(value).then(
        () => showToast({ kind: 'success', title: 'Copied to clipboard' }),
        () => copyFallback(value),
      );
      return;
    }
    copyFallback(value);
  });

  /**
   * Hidden-textarea + `document.execCommand('copy')` fallback for non-
   * secure contexts (plain HTTP, non-localhost) where `navigator.clipboard`
   * is undefined, AND for secure-context callsites whose `writeText()`
   * Promise rejects (permission denied, transient focus issue). The
   * `execCommand` API is officially deprecated but every shipping browser
   * still implements it for this exact fallback path — there is no other
   * portable copy-to-clipboard option outside HTTPS.
   *
   * @param {string} value
   * @returns {void}
   */
  function copyFallback(value) {
    try {
      const ta = document.createElement('textarea');
      ta.value = value;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.top = '0';
      ta.style.left = '0';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.focus();
      ta.select();
      const ok = document.execCommand && document.execCommand('copy');
      document.body.removeChild(ta);
      showToast(ok
        ? { kind: 'success', title: 'Copied to clipboard' }
        : { kind: 'error', title: 'Couldn\u2019t copy', body: 'Clipboard unavailable in this browser context.' });
    } catch (_e) {
      showToast({ kind: 'error', title: 'Couldn\u2019t copy' });
    }
  }

  // ---- FILE INPUTS -----------------------------------------
  // Native <input type="file"> ships an unstyleable user-agent button
  // that pops out of the panel's design language (#1189). Each callsite
  // wraps the input in:
  //   <div class="file-input">
  //     <label class="btn btn--secondary">
  //       <input type="file" name="…" hidden data-file-input>
  //       Choose file…
  //     </label>
  //     <span class="text-muted text-sm" data-file-name>No file chosen</span>
  //   </div>
  // The label-wraps-input pattern preserves native click-through to the
  // file picker; this delegated listener just mirrors the chosen
  // filename into the sibling span so the user sees what they picked.
  document.addEventListener('change', (/** @type {Event} */ e) => {
    const target = e.target;
    if (!(target instanceof HTMLInputElement)) return;
    if (target.type !== 'file' || !target.matches('[data-file-input]')) return;
    const lbl = target.closest('label');
    const wrap = lbl && lbl.parentElement;
    const span = wrap && wrap.querySelector('[data-file-name]');
    if (!span) return;
    const file = target.files && target.files[0];
    span.textContent = file ? file.name : 'No file chosen';
  });

  // ---- TOASTS ----------------------------------------------
  /**
   * @typedef {Object} ToastOpts
   * @property {string} [kind]      'info' | 'success' | 'warn' | 'error'
   * @property {string} title
   * @property {string} [body]
   * @property {number} [duration]  ms before auto-dismiss; default 4000
   */

  /**
   * @param {ToastOpts} opts
   * @returns {void}
   */
  function showToast(opts) {
    const kind = opts.kind || 'info';
    const title = opts.title;
    const body = opts.body;
    const duration = opts.duration === undefined ? 4000 : opts.duration;
    let stack = document.getElementById('toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.id = 'toast-stack';
      stack.className = 'toast-stack';
      document.body.appendChild(stack);
    }
    const el = document.createElement('div');
    el.className = 'toast';
    el.dataset.kind = kind;
    const icon = kind === 'error' ? 'circle-x' : kind === 'warn' ? 'triangle-alert' : kind === 'success' ? 'circle-check' : 'info';
    el.innerHTML = '<i data-lucide="' + icon + '" style="color:var(--' + kind + ')"></i>'
      + '<div style="flex:1;min-width:0"><div class="font-semibold text-sm">' + escapeHtml(title) + '</div>'
      + (body ? '<div class="text-xs text-muted" style="margin-top:2px">' + escapeHtml(body) + '</div>' : '')
      + '</div>'
      + '<button class="btn btn--ghost btn--icon" data-toast-close><i data-lucide="x"></i></button>';
    stack.appendChild(el);
    if (window.lucide) window.lucide.createIcons();
    setTimeout(() => el.remove(), duration);
  }

  document.addEventListener('click', (/** @type {MouseEvent} */ e) => {
    const target = /** @type {Element | null} */ (e.target);
    const btn = target && target.closest('[data-toast-close]');
    if (btn) {
      const toast = btn.closest('.toast');
      if (toast) toast.remove();
    }
  });

  // ---- ACTION-BUTTON BUSY STATE ----------------------------
  // Inline page-tail scripts that fire `sb.api.call(…)` from a click
  // handler call this on submit and again from the .then tail to
  // release. Without it every Confirm modal looked frozen between
  // the click and the API response — `btn.disabled = true` is a
  // load-bearing gate against double-clicks but invisible on its
  // own; the paired `.btn[data-loading="true"]` CSS rule in
  // theme.css owns the visual spinner.
  //
  // Idempotent in both directions: calling with `busy=true` twice
  // leaves the button in its busy state; calling with `busy=false`
  // when it isn't busy is a no-op.
  //
  // The `disabled` flip stays even on third-party themes that
  // strip the CSS rule, so the gate against double-clicks is
  // preserved regardless of whether the spinner ever paints.
  // Per-file fallback helpers (`setBusy(btn, busy)` in each
  // inline IIFE) further fall back to bare `btn.disabled = busy`
  // when `window.SBPP` itself isn't defined.
  /**
   * @param {HTMLElement | null} btn
   * @param {boolean} [busy] defaults to true
   * @returns {void}
   */
  function setBusy(btn, busy) {
    if (!btn) return;
    const isBusy = busy === undefined ? true : !!busy;
    const b = /** @type {HTMLButtonElement} */ (btn);
    if (isBusy) {
      btn.setAttribute('data-loading', 'true');
      btn.setAttribute('aria-busy', 'true');
      b.disabled = true;
    } else {
      btn.removeAttribute('data-loading');
      btn.removeAttribute('aria-busy');
      b.disabled = false;
    }
  }

  /** @type {any} */ (window).SBPP = { showToast: showToast, openDrawer: openDrawer, closeDrawer: closeDrawer, setBusy: setBusy };

  // ---- HELPERS ---------------------------------------------
  /**
   * Escape a string for safe insertion into an HTML attribute or text node.
   * @param {string | null | undefined} s
   * @returns {string}
   */
  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, (/** @type {string} */ c) => {
      switch (c) {
        case '&': return '&amp;';
        case '<': return '&lt;';
        case '>': return '&gt;';
        case '"': return '&quot;';
        default:  return '&#39;';
      }
    });
  }

  // Init Lucide icons whenever DOM changes (cheap MutationObserver hook).
  /** @returns {void} */
  const initIcons = () => { if (window.lucide) window.lucide.createIcons(); };
  if (document.readyState !== 'loading') initIcons();
  else document.addEventListener('DOMContentLoaded', initIcons);

  // ---- PLATFORM-AWARE SHORTCUT HINTS -----------------------
  // The Cmd glyph (U+2318 ⌘) and Return glyph (U+23CE ⏎) are missing
  // from the vendored JetBrains Mono and the generic CSS mono fallback
  // on every non-Mac browser, so a server-rendered '⌘' / '⏎' renders
  // as tofu for the majority of users (#1184). Templates render the
  // textual form server-side ("Ctrl", "Enter"); on macOS / iOS, swap
  // the visible label to the glyph form here at boot. The shortcut
  // handler at line ~112 already accepts metaKey || ctrlKey, so
  // behavior is platform-correct regardless of the visible hint.
  //
  // Attribute contract:
  //   [data-modkey]   → "Ctrl" (text)  → "\u2318" (⌘) on Mac
  //   [data-enterkey] → "Enter" (text) → "\u23CE" (⏎) on Mac
  //
  // The topbar trigger's "Ctrl K" kbd is a special case (the entire
  // textContent is replaced with "⌘K") — it predates `[data-modkey]`
  // and stays scoped via `.topbar__search kbd` so the kbd group inside
  // the palette result rows (DET-2) doesn't accidentally pick up the
  // K-suffixed swap.
  //
  // `renderPaletteResults` calls this after each result-render so the
  // dynamically-injected kbd hints get the swap on every refresh.
  /** @returns {void} */
  function applyPlatformHints() {
    const isMac = /Mac|iPhone|iPad/.test(navigator.userAgent)
      || navigator.platform.toUpperCase().includes('MAC');
    if (!isMac) return;
    document.querySelectorAll('.topbar__search kbd').forEach((/** @type {Element} */ el) => {
      el.textContent = '\u2318K';
    });
    document.querySelectorAll('[data-modkey]').forEach((/** @type {Element} */ el) => {
      el.textContent = '\u2318';
    });
    document.querySelectorAll('[data-enterkey]').forEach((/** @type {Element} */ el) => {
      el.textContent = '\u23CE';
    });
  }
  if (document.readyState !== 'loading') applyPlatformHints();
  else document.addEventListener('DOMContentLoaded', applyPlatformHints);
})();
