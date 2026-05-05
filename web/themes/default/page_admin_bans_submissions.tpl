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
            <div class="card" data-testid="submissions-empty">
                <div class="card__body">
                    <p class="text-sm text-muted m-0">No pending submissions. The queue is clear.</p>
                </div>
            </div>
        {else}
            <div class="card" style="overflow:hidden" data-testid="submissions-list">
                {foreach from=$submission_list item="sub"}
                    <details class="ban-row ban-row--active"
                             id="sid_{$sub.subid}"
                             data-testid="submission-row"
                             data-id="{$sub.subid}"
                             style="border-bottom:1px solid var(--border)">
                        <summary class="flex items-center gap-3 p-4"
                                 style="cursor:pointer;list-style:none">
                            <div style="flex:1;min-width:0">
                                <div class="font-medium text-sm truncate" data-testid="submission-row-name">
                                    {* nofilter: sub.name is wordwrap(htmlspecialchars($sub['name']), 55, "<br />", true) in admin.bans.php — already entity-escaped, only `<br />` reintroduced. *}
                                    {$sub.name nofilter}
                                </div>
                                <div class="font-mono text-xs text-muted truncate" data-testid="submission-row-steam">
                                    {if $sub.SteamId != ""}{$sub.SteamId|escape}{else}{$sub.sip|escape}{/if}
                                </div>
                            </div>
                            <div class="text-xs text-muted" style="flex-shrink:0">
                                {$sub.submitted|escape}
                            </div>
                            <div class="row-actions" style="opacity:1;flex-shrink:0">
                                <button type="button"
                                        class="btn btn--secondary btn--sm"
                                        data-testid="row-action-ban"
                                        data-action="submission-ban"
                                        data-subid="{$sub.subid}">
                                    Ban
                                </button>
                                {if $permissions_editsub}
                                    <button type="button"
                                            class="btn btn--ghost btn--sm"
                                            data-testid="row-action-remove"
                                            data-action="submission-archive"
                                            data-subid="{$sub.subid}"
                                            data-name="{$sub.name|smarty_stripslashes|escape}"
                                            data-archiv="1"
                                            style="color:var(--danger)">
                                        Remove
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
   handler so the buttons behave identically in either chrome. *}
{literal}
<script>
(function () {
    'use strict';
    function api() { return (window.sb && window.sb.api) || null; }
    function actions() { return window.Actions || null; }
    function toast(kind, title, body) {
        if (window.sb && window.sb.message && window.sb.message[kind]) {
            window.sb.message[kind](title, body || '');
        }
    }
    document.addEventListener('click', function (e) {
        var t = e.target;
        if (!t || !t.closest) return;
        var btn = t.closest('[data-action]');
        if (!btn) return;
        var act = btn.getAttribute('data-action');
        if (act === 'submission-ban') {
            e.preventDefault();
            var subid = Number(btn.dataset.subid);
            var a = api(), A = actions();
            if (!a || !A || !Number.isFinite(subid)) return;
            a.call(A.BansSetupBan, { subid: subid }).then(function (r) {
                if (r && r.ok && r.data && typeof window.applyBanFields === 'function') {
                    window.applyBanFields(r.data);
                }
                if (typeof window.swapTab === 'function') window.swapTab(0);
            });
            return;
        }
        if (act === 'submission-archive') {
            e.preventDefault();
            var sid = Number(btn.dataset.subid);
            var name = btn.dataset.name || ('submission #' + sid);
            var archiv = btn.dataset.archiv || '1';
            var msg;
            if (archiv === '2') msg = 'Restore the ban submission for "' + name + '" from the archive?';
            else if (archiv === '1') msg = 'Move the ban submission for "' + name + '" to the archive?';
            else msg = 'Delete the ban submission for "' + name + '"?';
            if (!window.confirm(msg)) return;
            var a2 = api(), A2 = actions();
            if (!a2 || !A2 || !Number.isFinite(sid)) return;
            btn.disabled = true;
            a2.call(A2.SubmissionsRemove, { sid: sid, archiv: archiv }).then(function (r) {
                if (!r || r.ok === false) {
                    btn.disabled = false;
                    toast('error', 'Action failed', (r && r.error && r.error.message) || 'Unknown error');
                    return;
                }
                var node = document.getElementById('sid_' + sid);
                if (node && node.parentNode) node.parentNode.removeChild(node);
                var counter = document.getElementById('subcount');
                if (counter) counter.textContent = String(Math.max(0, Number(counter.textContent) - 1));
                toast('success', 'Done', 'Submission updated.');
            });
        }
    });
})();
</script>
{/literal}
