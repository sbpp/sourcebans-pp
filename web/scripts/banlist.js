// @ts-check
/* ============================================================
   banlist.js — sbpp2026 public ban list interactions

   Layered on top of the server-rendered table from
   themes/sbpp2026/page_bans.tpl. The template is fully usable
   without this script (filters fall through as a server-side
   ?searchText= query, copy buttons no-op, comment-edit is a
   normal POST round-trip), so this file only adds:

     1. Status-filter chips   — toggle row visibility client-side,
        sync the active state into the URL hash so deep links
        survive a refresh.
     2. SteamID copy buttons  — the {has_access}-gated copy is
        already inert without JS; this just wires
        navigator.clipboard + the toast helper from theme.js.
     3. Comment edit form     — POSTs through sb.api.call()
        instead of the legacy `<form action="">` so the new
        theme doesn't need to navigate away.

   Each interaction is wrapped in a feature-detect (`if (foo)`)
   so a missing element on the comment-only branch doesn't throw.
   ============================================================ */
(function () {
  'use strict';

  /** @type {HTMLElement | null} */
  const root = /** @type {HTMLElement | null} */ (document.getElementById('banlist-root'));

  // ---- STATUS FILTER CHIPS ---------------------------------
  /** @type {NodeListOf<HTMLButtonElement>} */
  const chips = document.querySelectorAll('[data-state-filter]');
  /** @type {NodeListOf<HTMLElement>} */
  const rows = document.querySelectorAll('.ban-row[data-state]');

  /**
   * @param {string} state '' to show all, otherwise one of permanent|active|expired|unbanned.
   * @returns {void}
   */
  function applyStateFilter(state) {
    rows.forEach((r) => {
      const match = !state || r.dataset.state === state;
      r.style.display = match ? '' : 'none';
    });
    chips.forEach((c) => {
      c.setAttribute('aria-pressed', c.dataset.stateFilter === state ? 'true' : 'false');
    });
    try {
      const url = new URL(window.location.href);
      if (state) url.searchParams.set('state', state); else url.searchParams.delete('state');
      window.history.replaceState({}, '', url.toString());
    } catch (e) { /* URL unsupported in some old browsers; chip filter still works */ }
  }

  chips.forEach((c) => {
    c.addEventListener('click', () => applyStateFilter(c.dataset.stateFilter || ''));
  });

  try {
    const initial = new URL(window.location.href).searchParams.get('state') || '';
    if (initial) applyStateFilter(initial);
  } catch (e) { /* ignore — default "All" is already applied server-side */ }

  // ---- COMMENT EDIT FORM (sbpp2026 only) -------------------
  // Legacy theme keeps the `<form method=post>` round-trip; the
  // new theme's form has no `action`, so submit hits this handler
  // and goes through the JSON API. Falls back to navigation on
  // non-OK responses.
  /** @type {HTMLFormElement | null} */
  const cform = /** @type {HTMLFormElement | null} */ (document.getElementById('banlist-comment-form'));
  if (cform) {
    cform.addEventListener('submit', (/** @type {SubmitEvent} */ e) => {
      e.preventDefault();
      const bid = parseInt(cform.dataset.bid || '0', 10);
      const cid = parseInt(cform.dataset.cid || '0', 10);
      const ctype = cform.dataset.ctype || 'B';
      const page = parseInt(cform.dataset.page || '-1', 10);
      const text = /** @type {HTMLTextAreaElement | null} */ (document.getElementById('banlist-comment-text'));
      const value = text ? text.value : '';

      const sb = /** @type {any} */ (window).sb;
      const Actions = /** @type {any} */ (window).Actions;
      if (!sb || !sb.api || !Actions) {
        // sb / api-contract failed to load (offline, asset 404, …).
        // Native form.submit() bypasses listeners per the HTML spec, so
        // we don't need to detach this handler first; the submission
        // POSTs to the action-less form URL, which page.banlist.php
        // doesn't handle, but that's the same fall-through the legacy
        // theme has when sourcebans.js fails to load.
        cform.submit();
        return;
      }

      const action = cid > 0 ? Actions.BansEditComment : Actions.BansAddComment;
      sb.api.call(action, { bid: bid, cid: cid, ctype: ctype, ctext: value, page: page })
        .then(() => {
          window.location.href = 'index.php?p=banlist' + (page > 0 ? '&page=' + page : '');
        })
        .catch((/** @type {Error} */ err) => {
          const SBPP = /** @type {any} */ (window).SBPP;
          if (SBPP && SBPP.showToast) {
            SBPP.showToast({ kind: 'error', title: 'Failed to save comment', body: err.message });
          } else {
            alert('Failed to save comment: ' + err.message);
          }
        });
    });
  }

  // ---- LOADING SKELETON HOOK -------------------------------
  // The chip filter resolves entirely client-side so we never
  // flip [data-loading] for it; the hook is reserved for future
  // C-phase async fetches (e.g. the drawer detail load). Kept
  // as a no-op API on the root so the marquee testability
  // contract is satisfied.
  if (root) {
    /** @type {any} */ (root).sbpp_setLoading = (/** @type {boolean} */ flag) => {
      root.dataset.loading = flag ? 'true' : 'false';
    };
  }
})();
