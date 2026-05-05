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
  const drawerRoot = document.getElementById('drawer-root');

  /**
   * Mount drawer markup. Caller is responsible for the inner HTML
   * being safe (built from trusted server-rendered partials, never
   * raw user input).
   * @param {string} html
   * @returns {void}
   */
  function openDrawer(html) {
    if (!drawerRoot) return;
    drawerRoot.hidden = false;
    drawerRoot.innerHTML = '<div class="drawer-backdrop" data-drawer-close></div><div class="drawer" role="dialog">' + html + '</div>';
    if (window.lucide) window.lucide.createIcons();
  }

  /** @returns {void} */
  function closeDrawer() {
    if (drawerRoot) { drawerRoot.hidden = true; drawerRoot.innerHTML = ''; }
  }

  document.addEventListener('click', (/** @type {MouseEvent} */ e) => {
    const target = /** @type {Element | null} */ (e.target);
    const a = target && target.closest('[data-drawer-href]');
    if (a) {
      e.preventDefault();
      // TODO(C1): wire to sb.api.call(Actions.BansDetail, { bid: a.dataset.drawerHref })
      // and render the returned partial via openDrawer(html). Until C1 lands,
      // the drawer just opens with a placeholder so the click target isn't dead.
      openDrawer('<div style="padding:2rem" class="text-muted">Player drawer ships in C1.</div>');
      return;
    }
    if (target && target.closest('[data-drawer-close]') && !target.closest('.drawer')) closeDrawer();
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
