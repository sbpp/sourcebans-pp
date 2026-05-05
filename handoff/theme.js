/* ============================================================
   theme.js — SourceBans++ 2026 panel JS (vanilla, no jQuery dep)
   Wires: theme toggle, command palette, drawer, toasts, mobile menu.
   ============================================================ */
(function () {
  'use strict';

  // ---- THEME TOGGLE ----------------------------------------
  const THEME_KEY = 'sbpp-theme';
  function applyTheme(mode) {
    const dark = mode === 'dark' || (mode === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
    try { localStorage.setItem(THEME_KEY, mode); } catch(e) {}
  }
  function currentTheme() {
    try { return localStorage.getItem(THEME_KEY) || 'system'; } catch(e) { return 'system'; }
  }
  applyTheme(currentTheme());
  document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-theme-toggle]');
    if (!t) return;
    const cur = currentTheme();
    const next = cur === 'light' ? 'dark' : cur === 'dark' ? 'system' : 'light';
    applyTheme(next);
    fetch('?p=settings&c=theme', { method: 'POST', body: new URLSearchParams({ theme: next }) }).catch(() => {});
  });
  matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (currentTheme() === 'system') applyTheme('system');
  });

  // ---- MOBILE SIDEBAR --------------------------------------
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-mobile-menu]')) {
      document.getElementById('sidebar')?.classList.add('is-open');
    }
  });

  // ---- COMMAND PALETTE -------------------------------------
  const palette = document.getElementById('palette-root');
  const paletteInput = document.getElementById('palette-input');
  const paletteResults = document.getElementById('palette-results');

  function openPalette() {
    if (!palette) return;
    palette.hidden = false;
    setTimeout(() => paletteInput?.focus(), 10);
    renderPaletteResults('');
  }
  function closePalette() { if (palette) palette.hidden = true; }

  document.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      palette?.hidden ? openPalette() : closePalette();
    } else if (e.key === 'Escape') {
      closePalette();
      closeDrawer();
    }
  });
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-palette-open]')) openPalette();
    if (e.target.closest('[data-palette-close]') && !e.target.closest('.palette')) closePalette();
  });

  let paletteTimer = null;
  paletteInput?.addEventListener('input', (e) => {
    clearTimeout(paletteTimer);
    paletteTimer = setTimeout(() => renderPaletteResults(e.target.value), 150);
  });

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

  async function renderPaletteResults(q) {
    if (!paletteResults) return;
    const ql = (q || '').toLowerCase();
    const nav = NAV_ITEMS.filter(n => !ql || n.label.toLowerCase().includes(ql));
    let players = [];
    if (ql.length >= 2) {
      try {
        const r = await fetch(`?p=banlist&format=json&search=${encodeURIComponent(ql)}&limit=5`);
        players = (await r.json()).bans || [];
      } catch(e) {}
    }
    paletteResults.innerHTML = `
      ${nav.length ? `<div style="padding:4px 8px;font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-faint);font-weight:600">Navigate</div>
        ${nav.map(n => `<a href="${n.href}" class="sidebar__link"><i data-lucide="${n.icon}"></i><span>${n.label}</span></a>`).join('')}` : ''}
      ${players.length ? `<div style="padding:4px 8px;margin-top:8px;font-size:10px;text-transform:uppercase;letter-spacing:0.06em;color:var(--text-faint);font-weight:600">Players</div>
        ${players.map(p => `<a href="?p=banlist&id=${p.bid}" class="sidebar__link" style="height:auto;padding:8px"><div><div class="text-sm">${escapeHtml(p.name)}</div><div class="font-mono text-xs text-muted">${escapeHtml(p.steam)}</div></div></a>`).join('')}` : ''}
    `;
    window.lucide?.createIcons();
  }

  // ---- DRAWER ----------------------------------------------
  const drawerRoot = document.getElementById('drawer-root');
  function openDrawer(html) {
    if (!drawerRoot) return;
    drawerRoot.hidden = false;
    drawerRoot.innerHTML = `<div class="drawer-backdrop" data-drawer-close></div><div class="drawer" role="dialog">${html}</div>`;
    window.lucide?.createIcons();
  }
  function closeDrawer() { if (drawerRoot) { drawerRoot.hidden = true; drawerRoot.innerHTML = ''; } }
  document.addEventListener('click', async (e) => {
    const a = e.target.closest('[data-drawer-href]');
    if (a) {
      e.preventDefault();
      try {
        openDrawer('<div style="padding:2rem">Loading…</div>');
        const r = await fetch(a.dataset.drawerHref);
        openDrawer(await r.text());
      } catch (err) { closeDrawer(); }
      return;
    }
    if (e.target.closest('[data-drawer-close]') && !e.target.closest('.drawer')) closeDrawer();
  });

  // ---- COPY BUTTONS ----------------------------------------
  document.addEventListener('click', (e) => {
    const c = e.target.closest('[data-copy]');
    if (!c) return;
    e.preventDefault();
    navigator.clipboard?.writeText(c.dataset.copy);
    showToast({ kind: 'success', title: 'Copied to clipboard' });
  });

  // ---- TOASTS ----------------------------------------------
  function showToast({ kind = 'info', title, body, duration = 4000 }) {
    let stack = document.getElementById('toast-stack');
    if (!stack) { stack = document.createElement('div'); stack.id = 'toast-stack'; stack.className = 'toast-stack'; document.body.appendChild(stack); }
    const el = document.createElement('div');
    el.className = 'toast'; el.dataset.kind = kind;
    el.innerHTML = `<i data-lucide="${kind === 'error' ? 'circle-x' : kind === 'warn' ? 'triangle-alert' : kind === 'success' ? 'circle-check' : 'info'}" style="color:var(--${kind})"></i>
      <div style="flex:1;min-width:0"><div class="font-semibold text-sm">${escapeHtml(title)}</div>${body ? `<div class="text-xs text-muted" style="margin-top:2px">${escapeHtml(body)}</div>` : ''}</div>
      <button class="btn--ghost btn--icon" data-toast-close><i data-lucide="x"></i></button>`;
    stack.appendChild(el);
    window.lucide?.createIcons();
    setTimeout(() => el.remove(), duration);
  }
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-toast-close]')) e.target.closest('.toast').remove();
  });
  window.SBPP = { showToast, openDrawer, closeDrawer };

  // ---- HELPERS ---------------------------------------------
  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
  }

  // Init Lucide icons whenever DOM changes (cheap MutationObserver)
  const initIcons = () => window.lucide?.createIcons();
  if (document.readyState !== 'loading') initIcons();
  else document.addEventListener('DOMContentLoaded', initIcons);
})();
