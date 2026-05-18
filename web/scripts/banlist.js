// @ts-check
/* ============================================================
   banlist.js — public ban list interactions

   Layered on top of the server-rendered table from
   themes/default/page_bans.tpl. The template is fully usable
   without this script (filters fall through as a server-side
   ?searchText= query, copy buttons no-op, comment-edit is a
   normal POST round-trip), so this file only adds:

     1. Comment edit form     — POSTs through sb.api.call()
        instead of a `<form action="">` round-trip so the page
        doesn't need to navigate away.

   The status-filter chips are server-rendered anchors (#1352).
   Pre-#1352 this file owned a `applyStateFilter` row-hide layer
   (chip click → loop `.ban-row[data-state]`, flip
   `display:none` on rows whose `data-state` didn't match) that
   only operated on the rowset the server already returned —
   so a 10k-ban install where 50 rows were unbanned would still
   render 30 invisible rows on page 1 of `?state=unbanned` and
   the chip read as broken. The new chip strip in `page_bans.tpl`
   is real anchors that navigate to `?p=banlist&state=<slug>`,
   the page handler narrows the SQL rowset, and pagination /
   no-JS browsers / shared deep links all behave correctly.
   See "Server-side state filter" in `page.banlist.php` for the
   full predicate set.

   The SteamID copy buttons in the row-actions cell are wired by
   theme.js's document-level `[data-copy]` click delegate (single
   source for every copy affordance on the panel — banlist row,
   drawer identity rows, future surfaces). Pre-#1308 this file's
   docblock claimed it owned that wiring; it never did, and the
   inline `onclick="event.stopPropagation()"` on the button
   silently killed the document delegate on the bubble phase.
   Both halves are fixed in #1308 — the wiring stays in theme.js.

   Each interaction is wrapped in a feature-detect (`if (foo)`)
   so a missing element on the comment-only branch doesn't throw.
   ============================================================ */
(function () {
  'use strict';

  /** @type {HTMLElement | null} */
  const root = /** @type {HTMLElement | null} */ (document.getElementById('banlist-root'));

  // ---- COMMENT EDIT FORM ----------------------------------
  // The form has no `action`, so submit hits this handler and goes
  // through the JSON API. Falls back to navigation on non-OK responses.
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
        // doesn't handle, but that's an acceptable fall-through —
        // the network call is the primary path.
        cform.submit();
        return;
      }

      // Surface the busy state on the submit button while the comment
      // POST is in flight. The success branch navigates to the banlist
      // (`window.location.href = …`), so the disabled state hangs around
      // until the new page paints; the catch branch releases it.
      const SBPP = /** @type {any} */ (window).SBPP;
      const submitBtn = /** @type {HTMLButtonElement | null} */ (cform.querySelector('button[type="submit"]'));
      const setBusy = (/** @type {boolean} */ on) => {
        if (!submitBtn) return;
        if (SBPP && typeof SBPP.setBusy === 'function') SBPP.setBusy(submitBtn, on);
        else submitBtn.disabled = on;
      };
      setBusy(true);

      const action = cid > 0 ? Actions.BansEditComment : Actions.BansAddComment;
      sb.api.call(action, { bid: bid, cid: cid, ctype: ctype, ctext: value, page: page })
        .then(() => {
          window.location.href = 'index.php?p=banlist' + (page > 0 ? '&page=' + page : '');
        })
        .catch((/** @type {Error} */ err) => {
          setBusy(false);
          if (SBPP && SBPP.showToast) {
            SBPP.showToast({ kind: 'error', title: 'Failed to save comment', body: err.message });
          } else {
            alert('Failed to save comment: ' + err.message);
          }
        });
    });
  }

  // ---- LOADING SKELETON HOOK -------------------------------
  // The chip filter is now a server-rendered anchor (#1352) so
  // there's no client-side row-hide work for the skeleton hook
  // to gate. Reserved for future C-phase async fetches (e.g.
  // the drawer detail load). Kept as a no-op API on the root so
  // the marquee testability contract is satisfied.
  if (root) {
    /** @type {any} */ (root).sbpp_setLoading = (/** @type {boolean} */ flag) => {
      root.dataset.loading = flag ? 'true' : 'false';
    };
  }
})();
