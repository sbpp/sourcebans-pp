// @ts-check
/* ============================================================
   banlist.js — public punishment comment editor

   Shared by the banlist and commslist comment editor partial.
   POSTs add/edit operations through sb.api.call(), reports structured
   failures inline, and returns to the originating surface on success.
   The form is feature-detected, so loading this file on a list page is
   harmless.
   ============================================================ */
(function () {
  'use strict';

  // ---- COMMENT EDIT FORM ----------------------------------
  // The submit handler prevents the native POST and uses the JSON API.
  // Non-OK responses stay on the editor and surface an inline error.
  /** @type {HTMLFormElement | null} */
  const cform = /** @type {HTMLFormElement | null} */ (document.getElementById('banlist-comment-form'));
  if (cform) {
    cform.addEventListener('submit', (/** @type {SubmitEvent} */ e) => {
      e.preventDefault();
      const bid = parseInt(cform.dataset.bid || '0', 10);
      const cid = parseInt(cform.dataset.cid || '0', 10);
      const ctype = cform.dataset.ctype || 'B';
      const page = parseInt(cform.dataset.page || '-1', 10);
      let fallbackUrl = 'index.php?p=banlist';
      if (ctype === 'C') fallbackUrl = 'index.php?p=commslist';
      else if (ctype === 'S') fallbackUrl = 'index.php?p=admin&c=bans&section=submissions';
      else if (ctype === 'P') fallbackUrl = 'index.php?p=admin&c=bans&section=protests';
      if ((ctype === 'B' || ctype === 'C') && page > 0) fallbackUrl += '&page=' + page;
      const text = /** @type {HTMLTextAreaElement | null} */ (document.getElementById('banlist-comment-text'));
      const value = text ? text.value : '';
      const errorEl = /** @type {HTMLElement | null} */ (cform.querySelector('[data-testid="comment-editor-error"]'));

      const showError = (/** @type {string} */ message) => {
        if (errorEl) {
          errorEl.textContent = message;
          errorEl.hidden = false;
        }
      };
      if (errorEl) {
        errorEl.textContent = '';
        errorEl.hidden = true;
      }

      const sb = /** @type {any} */ (window).sb;
      const Actions = /** @type {any} */ (window).Actions;
      if (!sb || !sb.api || !Actions) {
        const message = 'The API client is unavailable. Reload the page and try again.';
        showError(message);
        const SBPP = /** @type {any} */ (window).SBPP;
        if (SBPP && SBPP.showToast) {
          SBPP.showToast({ kind: 'error', title: 'Comment not saved', body: message });
        }
        return;
      }

      // Surface the busy state on the submit button while the comment
      // POST is in flight. The success branch navigates to the source list
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
        .then((/** @type {SbApiEnvelope} */ response) => {
          if (!response || response.ok !== true) {
            setBusy(false);
            const message = response
              && response.error
              && typeof response.error.message === 'string'
              && response.error.message !== ''
              ? response.error.message
              : 'The comment could not be saved.';
            showError(message);
            if (SBPP && SBPP.showToast) {
              SBPP.showToast({ kind: 'error', title: 'Comment not saved', body: message });
            }
            return;
          }
          const data = /** @type {any} */ (response.data);
          const redirect = data
            && data.message
            && typeof data.message.redir === 'string'
            && data.message.redir !== ''
            ? data.message.redir
            : fallbackUrl;
          window.location.href = redirect;
        })
        .catch((/** @type {unknown} */ err) => {
          setBusy(false);
          const message = err instanceof Error && err.message
            ? err.message
            : 'The comment could not be saved.';
          showError(message);
          if (SBPP && SBPP.showToast) {
            SBPP.showToast({ kind: 'error', title: 'Comment not saved', body: message });
          }
        });
    });
  }
})();
