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

   Confirm chrome is a single shared `<dialog>` (injected once)
   matching the banlist / commslist delete modals — not
   `window.confirm()`.

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

    /** @type {{cid: number, ctype: string, page: number, trigger: HTMLElement}|null} */
    var pending = null;

    /** @returns {HTMLDialogElement} */
    function ensureDialog() {
        var existing = /** @type {HTMLDialogElement|null} */ (document.getElementById('comment-delete-dialog'));
        if (existing) return existing;

        var d = document.createElement('dialog');
        d.id = 'comment-delete-dialog';
        d.className = 'palette';
        d.setAttribute('aria-labelledby', 'comment-delete-dialog-title');
        d.setAttribute('data-testid', 'comment-delete-dialog');
        d.setAttribute('hidden', '');
        d.setAttribute('style', 'max-width:32rem;width:90vw;padding:1.25rem;border-radius:0.75rem;border:1px solid var(--border)');
        d.innerHTML =
            '<form method="dialog" data-testid="comment-delete-form">'
            + '<h2 id="comment-delete-dialog-title" style="font-size:var(--fs-lg);font-weight:600;margin:0 0 0.25rem">Delete comment</h2>'
            + '<p class="text-sm text-muted m-0" style="margin-bottom:0.75rem">'
            + 'Delete this comment? This cannot be undone.'
            + '</p>'
            + '<div class="flex gap-2 mt-4" style="justify-content:flex-end">'
            + '<button type="button" class="btn btn--secondary" data-testid="comment-delete-cancel" value="cancel">Cancel</button>'
            + '<button type="submit" class="btn btn--danger" data-testid="comment-delete-submit" value="confirm">'
            + '<i data-lucide="trash-2" style="width:13px;height:13px"></i> Delete comment'
            + '</button>'
            + '</div>'
            + '</form>';
        document.body.appendChild(d);
        var lucide = /** @type {any} */ (window).lucide;
        if (lucide && typeof lucide.createIcons === 'function') lucide.createIcons();
        return d;
    }

    /**
     * @param {{cid: number, ctype: string, page: number, trigger: HTMLElement}} ctx
     */
    function openDeleteDialog(ctx) {
        pending = ctx;
        var d = ensureDialog();
        d.removeAttribute('hidden');
        try { d.showModal(); }
        catch (_e) { d.setAttribute('open', ''); }
        var submitBtn = /** @type {HTMLButtonElement|null} */ (d.querySelector('[data-testid="comment-delete-submit"]'));
        if (submitBtn) {
            try { submitBtn.focus(); } catch (_e) { /* focus may throw */ }
        }
    }

    function closeDeleteDialog() {
        var d = /** @type {HTMLDialogElement|null} */ (document.getElementById('comment-delete-dialog'));
        if (!d) return;
        try { d.close(); } catch (_e) { /* not opened modally */ }
        d.setAttribute('hidden', '');
        pending = null;
    }

    /**
     * @param {{cid: number, ctype: string, page: number, trigger: HTMLElement}} ctx
     */
    function runDelete(ctx) {
        var a = api(), A = actions();
        if (!a || !A) {
            toast('error', 'Delete failed', 'The API client is unavailable. Reload the page and try again.');
            return;
        }

        var submitBtn = /** @type {HTMLButtonElement|null} */ (
            document.querySelector('#comment-delete-dialog [data-testid="comment-delete-submit"]')
        );
        setBusy(submitBtn, true);
        setBusy(ctx.trigger, true);
        a.call(A.BansRemoveComment, {
            cid:   ctx.cid,
            ctype: ctx.ctype,
            page:  ctx.page,
        }).then(function (r) {
            // sb.api.call follows r.redirect natively when the envelope
            // sets it; on success api_bans_remove_comment surfaces a
            // `message.redir` field that drives the navigation back to
            // the same paginated view. Mirror SbppGroupsAdd's shape.
            if (!r) {
                setBusy(submitBtn, false);
                setBusy(ctx.trigger, false);
                return;
            }
            if (r.redirect) return;
            if (r.ok === false) {
                setBusy(submitBtn, false);
                setBusy(ctx.trigger, false);
                var em = (r.error && r.error.message) || 'Failed to delete comment.';
                toast('error', 'Delete failed', em);
                return;
            }
            var data = r.data || {};
            var msg = data.message || {};
            closeDeleteDialog();
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
            setBusy(submitBtn, false);
            setBusy(ctx.trigger, false);
            toast('error', 'Delete failed', String(err && err.message ? err.message : err));
        });
    }

    document.addEventListener('click', function (e) {
        var t = /** @type {Element|null} */ (e.target);
        if (!t) return;

        if (t.closest && t.closest('[data-testid="comment-delete-cancel"]')) {
            e.preventDefault();
            closeDeleteDialog();
            return;
        }

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

        openDeleteDialog({ cid: cid, ctype: ctype, page: page, trigger: trigger });
    });

    document.addEventListener('submit', function (e) {
        var form = /** @type {Element|null} */ (e.target);
        if (!form || !(/** @type {Element} */ (form)).closest) return;
        if (!form.matches('[data-testid="comment-delete-form"]')) return;
        e.preventDefault();
        if (!pending) return;
        runDelete(pending);
    });

    document.addEventListener('cancel', function (e) {
        var t = /** @type {Element|null} */ (e.target);
        if (!t || t.id !== 'comment-delete-dialog') return;
        pending = null;
    });
})();
