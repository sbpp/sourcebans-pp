{*
    SourceBans++ 2026 — page_admin_bans_submissions.tpl
    Bound to Sbpp\View\AdminBansSubmissionsView (validated by SmartyTemplateRule).

    Current ban submissions queue. Each item collapses to a <details>
    block instead of the legacy InitAccordion('tr.opener3', …) row pair
    so no JS is required to expand. The row id `sid_<subid>` is kept to
    satisfy Swap2ndPane()/applyApiResponse() callers from the default
    theme (those still read the same DOM when admins land here on the
    legacy chrome).

    Actions per row (gated on the precomputed perms):
      - Ban: opens the Add Ban form prefilled (Actions.BansSetupBan via
        the legacy LoadSetupBan; falls back to a no-op when sourcebans.js
        isn't loaded — sbpp2026 inline script below also wires the call
        directly so the button works in both themes).
      - Remove: archiv=1 (move to archive). Requires $permissions_editsub.
      - Contact: opens admin.email.php with the submitter's email.

    `$submission_nav` is server-built pagination HTML emitted with
    nofilter; safety annotation matches the default template.
*}
{if NOT $permissions_submissions}
    <div class="card" data-testid="submissions-denied">
        <div class="card__body">
            <h1 style="font-size:1.25rem;font-weight:600;margin:0">Access denied</h1>
            <p class="text-sm text-muted m-0 mt-2">You don't have permission to view ban submissions.</p>
        </div>
    </div>
{else}
    <section class="p-6" data-testid="submissions-section" style="max-width:1400px">
        <div class="flex items-center justify-between gap-4 mb-4" style="flex-wrap:wrap">
            <div>
                <h1 style="font-size:1.5rem;font-weight:600;margin:0">
                    Ban submissions
                    <span class="text-sm text-muted" style="font-weight:400">
                        (<span id="subcount" data-testid="submissions-count">{$submission_count}</span>)
                    </span>
                </h1>
                <p class="text-sm text-muted m-0 mt-2">
                    Player-reported bans waiting for an admin to review.
                </p>
            </div>
            <div class="text-xs text-muted" data-testid="submissions-nav">
                {* nofilter: $submission_nav is server-built pagination HTML from admin.bans.php with no $_GET interpolation in this branch. *}
                {$submission_nav nofilter}
            </div>
        </div>

        {if $submission_list|@count == 0}
            {* #1207 empty-state unification — queue-empty. Like the
               protests queue, "empty" here means inbox-zero, so the
               surface stays copy-only. *}
            <div class="card" data-testid="submissions-empty">
                <div class="empty-state">
                    <span class="empty-state__icon" aria-hidden="true">
                        <i data-lucide="inbox" style="width:18px;height:18px"></i>
                    </span>
                    <h2 class="empty-state__title">No pending submissions</h2>
                    <p class="empty-state__body">The queue is clear. Player-reported bans will show up here for review when they're filed.</p>
                </div>
            </div>
        {else}
            <div class="card" style="overflow:hidden" data-testid="submissions-list">
                {foreach from=$submission_list item="sub"}
                    {* PUB-2 (#1207): `queue-row` is the layout class for
                       the summary; `ban-row` keeps the state border-left
                       (orange "active" stripe). theme.css owns the flex
                       row + mobile card-stack rules — see the
                       `.queue-row` block there. *}
                    <details class="queue-row ban-row ban-row--active"
                             id="sid_{$sub.subid}"
                             data-testid="submission-row"
                             data-id="{$sub.subid}"
                             style="border-bottom:1px solid var(--border)">
                        <summary>
                            <div class="queue-row__body">
                                <div class="font-medium text-sm truncate" data-testid="submission-row-name">
                                    {* nofilter: sub.name is wordwrap(htmlspecialchars($sub['name']), 55, "<br />", true) in admin.bans.php — already entity-escaped, only `<br />` reintroduced. *}
                                    {$sub.name nofilter}
                                </div>
                                <div class="font-mono text-xs text-muted truncate" data-testid="submission-row-steam">
                                    {if $sub.SteamId != ""}{$sub.SteamId|escape}{else}{$sub.sip|escape}{/if}
                                </div>
                            </div>
                            <div class="queue-row__date">
                                {$sub.submitted|escape}
                            </div>
                            {* #1229 — Ban is the canonical approve path on this
                               queue, so it carries the .btn--primary weight;
                               Remove is a ghost button without the inline danger
                               color so it stops competing visually with Ban.
                               The Remove button is also gated behind a
                               click-again-to-confirm step (see the inline JS
                               below) — a single misfire no longer silently
                               archives the submission. *}
                            <div class="row-actions">
                                {* #1275 — Pattern A. Pre-#1275 Ban was a JS button
                                   (data-action="submission-ban") that called
                                   Actions.BansSetupBan and prefilled the Add Ban
                                   form on the same page via __sbppApplyBanFields,
                                   then swapTab(0) to scroll up. Pattern A puts
                                   the Add Ban form on its own URL — the button
                                   is now a normal anchor to
                                   ?section=add-ban&fromsub=<subid>; the add-ban
                                   handler calls BansSetupBan + __sbppApplyBanFields
                                   after the form mounts. Same UX, real URL. *}
                                <a class="btn btn--primary btn--sm"
                                   data-testid="row-action-ban"
                                   href="index.php?p=admin&amp;c=bans&amp;section=add-ban&amp;fromsub={$sub.subid|escape:'url'}">
                                    Ban
                                </a>
                                {if $permissions_editsub}
                                    <button type="button"
                                            class="btn btn--ghost btn--sm"
                                            data-testid="row-action-remove"
                                            data-action="submission-archive"
                                            data-subid="{$sub.subid}"
                                            data-name="{$sub.name|smarty_stripslashes|escape}"
                                            data-archiv="1">
                                        <span data-confirm-label>Remove</span>
                                    </button>
                                {/if}
                                <a class="btn btn--ghost btn--sm"
                                   data-testid="row-action-contact"
                                   href="index.php?p=admin&c=bans&o=email&type=s&id={$sub.subid|escape:'url'}">
                                    Contact
                                </a>
                            </div>
                        </summary>

                        <div class="p-4" style="background:var(--bg-muted);border-top:1px solid var(--border)">
                            <div class="grid gap-4" style="grid-template-columns:2fr 1fr">
                                <dl class="text-sm" style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:0.375rem 0.75rem">
                                    <dt class="text-muted">Player</dt>
                                    {* nofilter: see sub.name above — wordwrap(htmlspecialchars(...))-encoded in admin.bans.php. *}
                                    <dd class="font-medium" style="margin:0">{$sub.name nofilter}</dd>

                                    <dt class="text-muted">Submitted</dt>
                                    <dd style="margin:0">{$sub.submitted|escape}</dd>

                                    <dt class="text-muted">SteamID</dt>
                                    <dd class="font-mono" style="margin:0">
                                        {if $sub.SteamId == ""}<span class="text-faint">no steamid present</span>{else}{$sub.SteamId|escape}{/if}
                                    </dd>

                                    <dt class="text-muted">IP</dt>
                                    <dd class="font-mono" style="margin:0">
                                        {if $sub.sip == ""}<span class="text-faint">no IP address present</span>{else}{$sub.sip|escape}{/if}
                                    </dd>

                                    <dt class="text-muted">Reason</dt>
                                    {* nofilter: sub.reason is wordwrap(htmlspecialchars($sub['reason']), 55, "<br />", true) — already entity-escaped in admin.bans.php. *}
                                    <dd style="margin:0">{$sub.reason nofilter}</dd>

                                    <dt class="text-muted">Server</dt>
                                    <dd style="margin:0" id="sub{$sub.subid}">
                                        {if $sub.hostname == ""}
                                            <i class="text-faint">Retrieving Hostname</i>
                                        {else}
                                            {* nofilter: sub.hostname is the literal `<i><font color="#677882">Other server...</font></i>` HTML emitted by admin.bans.php for non-tracked servers — server-controlled, no user input. *}
                                            {$sub.hostname nofilter}
                                        {/if}
                                    </dd>

                                    <dt class="text-muted">MOD</dt>
                                    <dd style="margin:0">{$sub.mod|escape}</dd>

                                    <dt class="text-muted">Submitter</dt>
                                    <dd style="margin:0">
                                        {if $sub.subname == ""}<span class="text-faint">no name present</span>{else}{$sub.subname|escape}{/if}
                                    </dd>

                                    <dt class="text-muted">Submitter IP</dt>
                                    <dd class="font-mono" style="margin:0">{$sub.ip|escape}</dd>
                                </dl>

                                <ul class="text-sm" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:0.375rem">
                                    {* nofilter: sub.demo is server-built `<a href="getdemo.php?id={URLENCODED INT}…">` HTML, sub.subaddcomment is CreateLinkR-built; no user input flows in. *}
                                    <li>{$sub.demo nofilter}</li>
                                    <li>{$sub.subaddcomment nofilter}</li>
                                </ul>
                            </div>

                            {if $sub.commentdata != "None"}
                                <div class="mt-4">
                                    <h4 class="text-xs text-muted" style="font-weight:600;margin:0 0 0.5rem;text-transform:uppercase;letter-spacing:0.06em">Comments</h4>
                                    <div class="space-y-3">
                                        {foreach from=$sub.commentdata item=commenta}
                                            <div class="card p-4">
                                                <div class="flex items-center justify-between gap-2 text-xs text-muted mb-2">
                                                    <strong style="color:var(--text);font-weight:600">
                                                        {if !empty($commenta.comname)}{$commenta.comname|escape}{else}<i class="text-faint">Admin deleted</i>{/if}
                                                    </strong>
                                                    <span class="flex items-center gap-2">
                                                        <span>{$commenta.added|escape}</span>
                                                        {if $commenta.editcomlink != ""}
                                                            {* nofilter: editcomlink/delcomlink are CreateLinkR-built `<a … onclick="…">` HTML from admin.bans.php with integer cid + literal subid; no user input. *}
                                                            <span>{$commenta.editcomlink nofilter} {$commenta.delcomlink nofilter}</span>
                                                        {/if}
                                                    </span>
                                                </div>
                                                <div class="text-sm" style="word-break:break-all;word-wrap:break-word">
                                                    {* nofilter: commenttxt passes through encodePreservingBr (htmlspecialchars per-segment, only `<br/>` survives) + URL-wrap regex on already-escaped text in admin.bans.php. *}
                                                    {$commenta.commenttxt nofilter}
                                                </div>
                                                {if !empty($commenta.edittime)}
                                                    <div class="text-xs text-faint mt-2">
                                                        last edit {$commenta.edittime|escape} by
                                                        {if !empty($commenta.editname)}{$commenta.editname|escape}{else}<i>Admin deleted</i>{/if}
                                                    </div>
                                                {/if}
                                            </div>
                                        {/foreach}
                                    </div>
                                </div>
                            {else}
                                <div class="text-xs text-faint mt-3">{$sub.commentdata|escape}</div>
                            {/if}
                        </div>
                    </details>
                {/foreach}
            </div>
        {/if}
    </section>
{/if}
{* Inline action wiring — works in both themes, sb.api.call + Actions are
   loaded by both core/header.tpl variants. The legacy theme also exposes
   LoadSetupBan/RemoveSubmission via sourcebans.js; we prefer the inline
   handler so the buttons behave identically in either chrome.

   #1229 — `submission-archive` is gated behind a click-again-to-confirm
   step instead of `window.confirm()`. The first click arms the button
   (label flips to "Click to confirm", `data-pending="true"` flag set,
   3s revert timer); the second click within that window actually
   archives. This replaces the browser-native dialog (which the audit
   flagged as both intrusive and easily dismissed) without expanding
   the toast API. The submission is recoverable from the archived
   submissions page if the operator wants to restore it.

   No `// @ts-check` here because the file is rendered by Smarty;
   ts-check only runs against `.js` sources in `web/scripts`. The
   shape mirrors the inline handler in `page_comms.tpl`. *}
{literal}
<script>
(function () {
    'use strict';

    /** How long a click-armed Remove button waits for the second click. */
    var PENDING_TIMEOUT_MS = 3000;

    /** @returns {{call: (a:string,p?:object)=>Promise<any>}|null} */
    function api()     { return (window.sb && window.sb.api) || null; }
    /** @returns {Record<string,string>|null} */
    function actions() { return /** @type {any} */ (window).Actions || null; }
    function toast(kind, title, body) {
        if (window.sb && window.sb.message && window.sb.message[kind]) {
            window.sb.message[kind](title, body || '');
        }
    }

    /** @type {WeakMap<HTMLElement, number>} */
    var pendingTimers = new WeakMap();

    /**
     * Is this Remove button currently armed (one click landed; waiting
     * for the second within PENDING_TIMEOUT_MS).
     * @param {HTMLElement} btn
     * @returns {boolean}
     */
    function isPending(btn) {
        return btn.getAttribute('data-pending') === 'true';
    }

    /**
     * Move the button into the "click again to confirm" state. Caches
     * the original label on the inner `[data-confirm-label]` span so
     * disarm() can restore it byte-for-byte; falls back to the button
     * itself if the span isn't present (defensive — the template ships
     * the span).
     * @param {HTMLElement} btn
     * @returns {void}
     */
    function arm(btn) {
        var labelEl = /** @type {HTMLElement} */ (btn.querySelector('[data-confirm-label]') || btn);
        if (labelEl.getAttribute('data-original-label') === null) {
            labelEl.setAttribute('data-original-label', labelEl.textContent || '');
        }
        labelEl.textContent = 'Click to confirm';
        btn.setAttribute('data-pending', 'true');
        btn.setAttribute('aria-label', 'Click again within 3 seconds to confirm removal');
        var prev = pendingTimers.get(btn);
        if (prev) window.clearTimeout(prev);
        var t = window.setTimeout(function () { disarm(btn); }, PENDING_TIMEOUT_MS);
        pendingTimers.set(btn, t);
    }

    /**
     * Restore the original label and clear the pending state + revert
     * timer.
     * @param {HTMLElement} btn
     * @returns {void}
     */
    function disarm(btn) {
        var labelEl = /** @type {HTMLElement} */ (btn.querySelector('[data-confirm-label]') || btn);
        var orig = labelEl.getAttribute('data-original-label');
        if (orig !== null) labelEl.textContent = orig;
        btn.removeAttribute('data-pending');
        btn.removeAttribute('aria-label');
        var t = pendingTimers.get(btn);
        if (t !== undefined) {
            window.clearTimeout(t);
            pendingTimers.delete(btn);
        }
    }

    document.addEventListener('click', function (e) {
        var t = /** @type {Element|null} */ (e.target);
        if (!t || !t.closest) return;
        var btn = /** @type {HTMLElement|null} */ (t.closest('[data-action]'));
        if (!btn) return;
        var act = btn.getAttribute('data-action');
        // #1275 — the `submission-ban` data-action handler that called
        // BansSetupBan + __sbppApplyBanFields + swapTab is gone; the Ban
        // button is now an anchor to ?section=add-ban&fromsub=<subid>
        // and the add-ban handler does the prefill on landing.
        if (act === 'submission-archive') {
            e.preventDefault();
            // First click on a non-armed button → arm it and bail.
            // The archive call only fires on the second click within
            // PENDING_TIMEOUT_MS; otherwise the revert timer disarms
            // and the operator hasn't lost the submission.
            if (!isPending(btn)) {
                arm(btn);
                return;
            }
            disarm(btn);
            var sid = Number(btn.dataset.subid);
            // This template only fires archiv=1 (archive a live
            // submission). The archived-submissions template uses a
            // separate `submission-archive-toggle` action for restore
            // (archiv=2) and delete (archiv=0), so the dead-code
            // branches that handled those values here have been
            // removed.
            var a2 = api(), A2 = actions();
            if (!a2 || !A2 || !Number.isFinite(sid)) return;
            /** @type {HTMLButtonElement} */ (btn).disabled = true;
            a2.call(A2.SubmissionsRemove, { sid: sid, archiv: '1' }).then(function (r) {
                if (!r || r.ok === false) {
                    /** @type {HTMLButtonElement} */ (btn).disabled = false;
                    toast('error', 'Action failed', (r && r.error && r.error.message) || 'Unknown error');
                    return;
                }
                var node = document.getElementById('sid_' + sid);
                if (node && node.parentNode) node.parentNode.removeChild(node);
                var counter = document.getElementById('subcount');
                if (counter) counter.textContent = String(Math.max(0, Number(counter.textContent) - 1));
                toast('success', 'Submission archived', 'Restore from the archived submissions page if needed.');
            });
        }
    });
})();
</script>
{/literal}
