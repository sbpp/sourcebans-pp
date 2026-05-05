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
  document.addEventListener('click', (/** @type {MouseEvent} */ e) => {
    const target = /** @type {Element | null} */ (e.target);
    if (target && target.closest('[data-mobile-menu]')) {
      const sidebar = document.getElementById('sidebar');
      if (sidebar) sidebar.classList.add('is-open');
    }
  });

  // ---- COMMAND PALETTE -------------------------------------
  const palette = document.getElementById('palette-root');
  const paletteInput = /** @type {HTMLInputElement | null} */ (document.getElementById('palette-input'));
  const paletteResults = document.getElementById('palette-results');

  /** @returns {void} */
  function openPalette() {
    if (!palette) return;
    palette.hidden = false;
    setTimeout(() => paletteInput && paletteInput.focus(), 10);
    renderPaletteResults('');
  }

  /** @returns {void} */
  function closePalette() { if (palette) palette.hidden = true; }

  document.addEventListener('keydown', (/** @type {KeyboardEvent} */ e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      if (palette && palette.hidden) openPalette(); else closePalette();
    } else if (e.key === 'Escape') {
      closePalette();
      closeDrawer();
    }
  });

  document.addEventListener('click', (/** @type {MouseEvent} */ e) => {
    const target = /** @type {Element | null} */ (e.target);
    if (target && target.closest('[data-palette-open]')) openPalette();
    if (target && target.closest('[data-palette-close]') && !target.closest('.palette')) closePalette();
  });

  /** @type {ReturnType<typeof setTimeout> | null} */
  let paletteTimer = null;
  if (paletteInput) {
    paletteInput.addEventListener('input', (/** @type {Event} */ e) => {
      if (paletteTimer) clearTimeout(paletteTimer);
      const value = /** @type {HTMLInputElement} */ (e.target).value;
      paletteTimer = setTimeout(() => renderPaletteResults(value), 150);
    });
  }

  /** @typedef {{icon: string, label: string, href: string}} NavItem */

  /** @type {NavItem[]} */
  const NAV_ITEMS = [
    { icon: 'layout-dashboard', label: 'Dashboard',     href: '?' },
    { icon: 'ban',              label: 'Ban list',      href: '?p=banlist' },
    { icon: 'mic-off',          label: 'Comm blocks',   href: '?p=commslist' },
    { icon: 'flag',             label: 'Submit a ban',  href: '?p=submit' },
    { icon: 'megaphone',        label: 'Appeals',       href: '?p=appeal' },
    { icon: 'server',           label: 'Servers',       href: '?p=servers' },
    { icon: 'shield',           label: 'Admin panel',   href: '?p=admin' },
    { icon: 'plus-circle',      label: 'Add ban',       href: '?p=admin&c=bans&action=add' },
  ];

  /**
   * Render filtered navigation results into the palette.
   * Player search is intentionally a no-op until C2 wires it via
   * `sb.api.call(Actions.BansSearch, ...)`.
   * @param {string} q
   * @returns {void}
   */
  function renderPaletteResults(q) {
    if (!paletteResults) return;
    const ql = (q || '').toLowerCase();
    const nav = NAV_ITEMS.filter((n) => !ql || n.label.toLowerCase().includes(ql));

    // TODO(C2): wire to sb.api.call(Actions.BansSearch, { q: ql, limit: 5 })
    // and render the returned `bans` array beneath the nav section.
    // Until C2 lands, the palette returns navigation items only.

    const navHtml = nav.length
      ? '<div style="padding:4px 8px;font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-faint);font-weight:600">Navigate</div>'
        + nav.map((n) => '<a href="' + n.href + '" class="sidebar__link"><i data-lucide="' + n.icon + '"></i><span>' + escapeHtml(n.label) + '</span></a>').join('')
      : '';
    paletteResults.innerHTML = navHtml;
    if (window.lucide) window.lucide.createIcons();
  }

  // ---- DRAWER ----------------------------------------------
  // Click target convention: any element with `[data-drawer-bid="N"]`
  // (or, for backwards-compat with the handoff template, an `id=N` query
  // param inside `[data-drawer-href]`) opens the drawer for ban N.
  // The state attribute `data-drawer-open="true"` on the container is the
  // testability hook — CI can observe deterministic open/close without
  // chasing CSS visibility heuristics. `data-loading="true"` is set
  // while the fetch is in flight so an integration test can wait on the
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
  }

  /**
   * Public entry point preserved for legacy callers (window.SBPP.openDrawer).
   * @param {string} html
   * @returns {void}
   */
  function openDrawer(html) { showDrawer(html); }

  /**
   * Resolve a click target into a numeric ban id, returning null when
   * the trigger doesn't carry one. Accepts:
   *   - `data-drawer-bid="123"`           (preferred new form)
   *   - `data-drawer-href="?p=banlist&id=123"`  (handoff template form)
   * @param {Element} el
   * @returns {number | null}
   */
  function bidFromTrigger(el) {
    const fromAttr = /** @type {HTMLElement} */ (el).dataset.drawerBid;
    if (fromAttr && /^\d+$/.test(fromAttr)) return parseInt(fromAttr, 10);
    const href = /** @type {HTMLElement} */ (el).dataset.drawerHref;
    if (typeof href === 'string') {
      const m = href.match(/[?&]id=(\d+)/);
      if (m) return parseInt(m[1], 10);
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
      default:          return 'Unknown';
    }
  }

  /**
   * Build the rendered drawer HTML for a successful bans.detail
   * envelope. Every value that ends up in innerHTML is funnelled
   * through escapeHtml(); the only literal HTML is the static layout
   * we author here.
   * @param {Object} data
   * @returns {string}
   */
  function renderDrawerBody(data) {
    const player = (data && data.player) || {};
    const ban    = (data && data.ban)    || {};
    const admin  = (data && data.admin)  || {};
    const server = (data && data.server) || {};
    const comments = Array.isArray(data && data.comments) ? data.comments : [];
    const commentsVisible = !!(data && data.comments_visible);

    const headerHtml =
      '<header class="drawer__header" style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;border-bottom:1px solid var(--border)">'
      +   '<div>'
      +     '<div class="text-xs text-faint" style="text-transform:uppercase;letter-spacing:0.06em">Ban #' + escapeHtml(String(data.bid)) + '</div>'
      +     '<h2 class="font-semibold" style="margin:0.125rem 0 0;font-size:1.125rem">' + escapeHtml(player.name || '(unknown)') + '</h2>'
      +   '</div>'
      +   '<button class="btn--ghost btn--icon" type="button" data-drawer-close aria-label="Close">'
      +     '<i data-lucide="x"></i>'
      +   '</button>'
      + '</header>';

    /** @type {Array<[string, string]>} */
    const idRows = [];
    if (player.steam_id)     idRows.push(['SteamID',    String(player.steam_id)]);
    if (player.steam_id_3)   idRows.push(['Steam3',     String(player.steam_id_3)]);
    if (player.community_id) idRows.push(['Community',  String(player.community_id)]);
    if (player.ip)           idRows.push(['IP',         String(player.ip)]);
    if (player.country)      idRows.push(['Country',    String(player.country)]);

    const idHtml = idRows.length === 0 ? '' :
      '<dl class="drawer__ids" style="display:grid;grid-template-columns:6rem 1fr;gap:0.25rem 0.75rem;margin:0;font-size:0.8125rem">'
      + idRows.map((row) =>
          '<dt class="text-faint">' + escapeHtml(row[0]) + '</dt>'
          + '<dd class="font-mono" style="margin:0">' + escapeHtml(row[1])
          + '<button class="btn--ghost btn--icon" type="button" data-copy="' + escapeHtml(row[1]) + '" title="Copy" style="margin-left:0.25rem">'
          +   '<i data-lucide="copy" style="width:12px;height:12px"></i>'
          + '</button>'
          + '</dd>'
        ).join('')
      + '</dl>';

    /** @type {Array<[string, string]>} */
    const banRows = [
      ['State',   stateLabel(/** @type {string} */ (ban.state))],
      ['Reason',  String(ban.reason || '(none)')],
      ['Length',  String(ban.length_human || '')],
      ['Banned',  String(ban.banned_at_human || '')],
    ];
    if (ban.expires_at_human) banRows.push(['Expires', String(ban.expires_at_human)]);
    if (ban.removed_at_human) banRows.push(['Removed', String(ban.removed_at_human)]);
    if (ban.removed_by)       banRows.push(['Removed by', String(ban.removed_by)]);
    if (ban.unban_reason)     banRows.push(['Unban reason', String(ban.unban_reason)]);
    if (admin.name)           banRows.push(['Admin', String(admin.name)]);
    if (server.name)          banRows.push(['Server', String(server.name)]);

    const banHtml =
      '<dl class="drawer__ban" style="display:grid;grid-template-columns:6rem 1fr;gap:0.25rem 0.75rem;margin:0;font-size:0.8125rem">'
      + banRows.map((row) =>
          '<dt class="text-faint">' + escapeHtml(row[0]) + '</dt>'
          + '<dd style="margin:0">' + escapeHtml(row[1]) + '</dd>'
        ).join('')
      + '</dl>';

    let commentsHtml = '';
    if (commentsVisible) {
      commentsHtml = '<section style="padding:0 1.25rem 1.25rem">'
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

    const bodyHtml =
      '<div class="drawer__body" style="padding:1rem 1.25rem;display:flex;flex-direction:column;gap:1rem;overflow-y:auto;flex:1">'
      +   idHtml
      +   banHtml
      + '</div>'
      + commentsHtml;

    return headerHtml + bodyHtml;
  }

  /**
   * Render the placeholder skeleton shown during the in-flight fetch.
   * @returns {string}
   */
  function renderDrawerLoading() {
    return '<header class="drawer__header" style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;border-bottom:1px solid var(--border)">'
      + '<div class="skeleton" style="width:8rem;height:1.25rem"></div>'
      + '<button class="btn--ghost btn--icon" type="button" data-drawer-close aria-label="Close">'
      + '<i data-lucide="x"></i>'
      + '</button>'
      + '</header>'
      + '<div class="drawer__body" style="padding:1.25rem;display:flex;flex-direction:column;gap:0.75rem">'
      +   '<div class="skeleton" style="height:0.875rem"></div>'
      +   '<div class="skeleton" style="height:0.875rem;width:60%"></div>'
      +   '<div class="skeleton" style="height:0.875rem;width:80%"></div>'
      + '</div>';
  }

  /**
   * Render the error state (server returned an error envelope or the
   * fetch itself failed).
   * @param {string} message
   * @returns {string}
   */
  function renderDrawerError(message) {
    return '<header class="drawer__header" style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;border-bottom:1px solid var(--border)">'
      +   '<h2 class="font-semibold" style="margin:0;font-size:1rem">Couldn\u2019t load ban</h2>'
      +   '<button class="btn--ghost btn--icon" type="button" data-drawer-close aria-label="Close">'
      +     '<i data-lucide="x"></i>'
      +   '</button>'
      + '</header>'
      + '<div class="drawer__body" style="padding:1.25rem">'
      +   '<p class="text-sm text-muted" style="margin:0">' + escapeHtml(message) + '</p>'
      + '</div>';
  }

  /**
   * Fetch and render the player-detail drawer for a ban.
   * @param {number} bid
   * @returns {Promise<void>}
   */
  async function loadDrawer(bid) {
    if (!drawerRoot) return;
    showDrawer(renderDrawerLoading());
    drawerRoot.dataset.loading = 'true';

    /** @type {{ ok: boolean, data?: any, error?: { code: string, message: string } }} */
    const env = window.sb && window.sb.api
      ? await window.sb.api.call(Actions.BansDetail, { bid: bid })
      : { ok: false, error: { code: 'no_client', message: 'sb.api unavailable' } };

    delete drawerRoot.dataset.loading;
    if (env && env.ok && env.data) {
      showDrawer(renderDrawerBody(env.data));
    } else {
      const msg = (env && env.error && env.error.message) || 'Unknown error.';
      showDrawer(renderDrawerError(msg));
    }
  }

  document.addEventListener('click', (/** @type {MouseEvent} */ e) => {
    const target = /** @type {Element | null} */ (e.target);
    const trigger = target && target.closest('[data-drawer-bid], [data-drawer-href]');
    if (trigger) {
      const bid = bidFromTrigger(trigger);
      if (bid !== null) {
        e.preventDefault();
        loadDrawer(bid);
        return;
      }
    }
    // Any element marked `data-drawer-close` closes the drawer — both the
    // backdrop (sibling of .drawer) and the in-header X button (descendant
    // of .drawer) carry the attribute. The earlier `!closest('.drawer')`
    // guard predated the X button and would silently swallow its clicks.
    if (target && target.closest('[data-drawer-close]')) closeDrawer();
  });

  // ---- COPY BUTTONS ----------------------------------------
  document.addEventListener('click', (/** @type {MouseEvent} */ e) => {
    const target = /** @type {Element | null} */ (e.target);
    const c = /** @type {HTMLElement | null} */ (target && target.closest('[data-copy]'));
    if (!c) return;
    e.preventDefault();
    const value = c.dataset.copy || '';
    if (navigator.clipboard) navigator.clipboard.writeText(value);
    showToast({ kind: 'success', title: 'Copied to clipboard' });
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
      + '<button class="btn--ghost btn--icon" data-toast-close><i data-lucide="x"></i></button>';
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

  /** @type {any} */ (window).SBPP = { showToast: showToast, openDrawer: openDrawer, closeDrawer: closeDrawer };

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
})();
