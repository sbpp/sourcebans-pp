// @ts-check
/* ============================================================
   comment-actions.js — shared comment-action dispatcher

   Single document-level click delegate handling
   `data-action="comment-delete"` triggers across the three
   surfaces that render comment threads:

     1. Public banlist  (`web/pages/page.banlist.php`)
     2. Public commslist (`web/pages/page.commslist.php`)
     3. Admin moderation queues — protests + submissions
        (`web/pages/admin.bans.php`)

   All three previously emitted inline
   `onclick="RemoveComment(<cid>, '<ctype>', <page>);"` blobs
   pointing at a helper in the deleted `web/scripts/sourcebans.js`
   (#1123 D1). Without the helper, every trash-can click was a
   silent `ReferenceError: RemoveComment is not defined` — no
   toast, no API call, no row removal; the operator perceived the
   button as broken.

   The replacement uses `Actions.BansRemoveComment` (already
   registered in `_register.php` with `ADMIN_OWNER`; `ctype` arm
   handles 'B'/'C'/'S'/'P' in `api_bans_remove_comment`). On a
   successful response we honour the handler's `message.redir`
   envelope so the operator lands back on the same paginated view
   they were on; on error we surface a toast and leave the row
   intact.

   Each trigger carries:
     - `data-cid="<int>"`   — required (the comments row id)
     - `data-ctype="<B|C|S|P>"` — required
     - `data-page="<int>"`  — optional, defaults to -1

   This file lives at panel scope so any future page that needs
   comment-delete just adds the `data-action="comment-delete"`
   attribute + the three data hooks and includes this script.
   ============================================================ */
(function () {
    'use strict';

    /** @returns {{call: (a:string,p?:object)=>Promise<any>}|null} */
    function api()     { return /** @type {any} */ (window.sb && /** @type {any} */ (window.sb).api) || null; }
    /** @returns {Record<string,string>|null} */
    function actions() { return /** @type {any} */ (window).Actions || null; }
    /**
     * @param {Element|null} btn
     * @param {boolean} [busy]
     */
    function setBusy(btn, busy) {
        if (!btn) return;
        var S = /** @type {any} */ (window).SBPP;
        if (S && typeof S.setBusy === 'function') S.setBusy(btn, busy);
        else /** @type {HTMLButtonElement|HTMLAnchorElement} */ (btn).setAttribute('aria-busy', busy ? 'true' : 'false');
    }
    /**
     * @param {string} kind
     * @param {string} title
     * @param {string} [body]
     */
    function toast(kind, title, body) {
        var S = /** @type {any} */ (window).SBPP;
        if (S && typeof S.showToast === 'function') {
            S.showToast({ kind: kind, title: title, body: body || '' });
        }
    }

    document.addEventListener('click', function (e) {
        var t = /** @type {Element|null} */ (e.target);
        if (!t) return;
        var trigger = /** @type {HTMLElement|null} */ (t.closest && t.closest('[data-action="comment-delete"]'));
        if (!trigger) return;
        e.preventDefault();

        var cid = parseInt(trigger.getAttribute('data-cid') || '0', 10);
        var ctype = trigger.getAttribute('data-ctype') || '';
        var page = parseInt(trigger.getAttribute('data-page') || '-1', 10);

        if (!cid || !ctype) {
            toast('error', 'Delete failed', 'Missing comment context.');
            return;
        }

        // Confirm prompt — comment deletion is irreversible and the
        // legacy helper used the native confirm() too. We keep it as
        // a native `confirm()` rather than a `<dialog>` because the
        // trash-can appears in dense threads (potentially 10+ per
        // page) and the dialog scaffolding noise per row would
        // dwarf the affordance.
        if (!window.confirm('Are you sure you want to delete this comment?')) {
            return;
        }

        var a = api(), A = actions();
        if (!a || !A) {
            toast('error', 'Delete failed', 'The API client is unavailable. Reload the page and try again.');
            return;
        }

        setBusy(trigger, true);
        a.call(A.BansRemoveComment, {
            cid:   cid,
            ctype: ctype,
            page:  page,
        }).then(function (r) {
            // sb.api.call follows r.redirect natively when the envelope
            // sets it; on success api_bans_remove_comment surfaces a
            // `message.redir` field that drives the navigation back to
            // the same paginated view. Mirror SbppGroupsAdd's shape.
            if (!r) { setBusy(trigger, false); return; }
            if (r.redirect) return;
            if (r.ok === false) {
                setBusy(trigger, false);
                var em = (r.error && r.error.message) || 'Failed to delete comment.';
                toast('error', 'Delete failed', em);
                return;
            }
            var data = r.data || {};
            var msg = data.message || {};
            toast('success', msg.title || 'Comment Deleted', msg.body || 'The comment was deleted.');
            // Honour the handler's redir envelope (sb.api.call only
            // auto-redirects on r.redirect, NOT on data.message.redir).
            // Match SbppGroupsAdd's 1.2-1.5s pause so the toast is
            // visible before the navigation.
            setTimeout(function () {
                if (msg.redir) window.location.href = msg.redir;
                else window.location.reload();
            }, 1200);
        }).catch(function (err) {
            // #1402 adversarial review MEDIUM 4: defensive .catch() arm
            // so a throw inside the success callback (or a sb.api.call
            // internal failure) doesn't leave the trash-can stuck in
            // its busy state. The trash-can appears in dense threads
            // (potentially 10+ per page) and a stuck row reads as a
            // broken affordance — the operator clicks again, gets the
            // confirm prompt, and the second click stays no-op'd
            // because the bubble-phase delegate sees `aria-busy` and
            // the dispatch silently re-fires.
            setBusy(trigger, false);
            toast('error', 'Delete failed', String(err && err.message ? err.message : err));
        });
    });
})();
