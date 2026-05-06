{*
    SourceBans++ 2026 — page_admin_bans_protests.tpl
    Bound to Sbpp\View\AdminBansProtestsView (validated by SmartyTemplateRule).

    Current ban protest queue. Players file these from the public site
    via web/pages/page.protestban.php; admins triage them here. The page
    handler hydrates each row with the underlying ban's data so the
    detail panel can render player + ban context inline (no extra fetch).

    Actions per row (gated on $permission_editban):
      - Remove: archiv=1, moves to the protest archive.
      - Contact: opens admin.email.php with the protester's email.

    Row id `pid_<pid>` and the `ban_details_<pid>` td id are preserved
    so legacy callers in sourcebans.js still find the correct nodes
    when the default theme renders the same data.
*}
{if NOT $permission_protests}
    <div class="card" data-testid="protests-denied">
        <div class="card__body">
            <h1 style="font-size:1.25rem;font-weight:600;margin:0">Access denied</h1>
            <p class="text-sm text-muted m-0 mt-2">You don't have permission to view ban protests.</p>
        </div>
    </div>
{else}
    <section class="p-6" data-testid="protests-section" style="max-width:1400px">
        <div class="flex items-center justify-between gap-4 mb-4" style="flex-wrap:wrap">
            <div>
                <h1 style="font-size:1.5rem;font-weight:600;margin:0">
                    Ban protests
                    <span class="text-sm text-muted" style="font-weight:400">
                        (<span id="protcount" data-testid="protests-count">{$protest_count}</span>)
                    </span>
                </h1>
                <p class="text-sm text-muted m-0 mt-2">
                    Bans players have asked you to reconsider.
                </p>
            </div>
            <div class="text-xs text-muted" data-testid="protests-nav">
                {* nofilter: $protest_nav is server-built pagination HTML from admin.bans.php with no $_GET interpolation in this branch. *}
                {$protest_nav nofilter}
            </div>
        </div>

        {if $protest_list|@count == 0}
            <div class="card" data-testid="protests-empty">
                <div class="card__body">
                    <p class="text-sm text-muted m-0">No active protests. Nothing waiting for review.</p>
                </div>
            </div>
        {else}
            <div class="card" style="overflow:hidden" data-testid="protests-list">
                {foreach from=$protest_list item="protest"}
                    {* PUB-2 (#1207): `queue-row` is the layout class for
                       the summary; `ban-row` keeps the state border-left
                       (orange "active" stripe). theme.css owns the flex
                       row + mobile card-stack rules — see the
                       `.queue-row` block there. *}
                    <details class="queue-row ban-row ban-row--active"
                             id="pid_{$protest.pid}"
                             data-testid="protest-row"
                             data-id="{$protest.pid}"
                             style="border-bottom:1px solid var(--border)">
                        <summary>
                            <div class="queue-row__body">
                                <div class="font-medium text-sm truncate" data-testid="protest-row-name">
                                    <a class="link"
                                       href="./index.php?p=banlist&advSearch={$protest.authid|escape:'url'}&advType=steamid"
                                       title="Show ban"
                                       onclick="event.stopPropagation();">{$protest.name|escape}</a>
                                </div>
                                <div class="font-mono text-xs text-muted truncate" data-testid="protest-row-steam">
                                    {if $protest.authid != ""}{$protest.authid|escape}{else}{$protest.ip|escape}{/if}
                                </div>
                            </div>
                            <div class="queue-row__date">
                                {$protest.datesubmitted|escape}
                            </div>
                            <div class="row-actions">
                                {if $permission_editban}
                                    <button type="button"
                                            class="btn btn--ghost btn--sm"
                                            data-testid="row-action-remove"
                                            data-action="protest-archive"
                                            data-pid="{$protest.pid}"
                                            data-key="{if $protest.authid != ''}{$protest.authid|escape}{else}{$protest.ip|escape}{/if}"
                                            data-archiv="1"
                                            style="color:var(--danger)">
                                        Remove
                                    </button>
                                {/if}
                                <a class="btn btn--ghost btn--sm"
                                   data-testid="row-action-contact"
                                   href="index.php?p=admin&c=bans&o=email&type=p&id={$protest.pid|escape:'url'}">
                                    Contact
                                </a>
                            </div>
                        </summary>

                        <div class="p-4" id="ban_details_{$protest.pid}" style="background:var(--bg-muted);border-top:1px solid var(--border)">
                            <div class="grid gap-4" style="grid-template-columns:2fr 1fr">
                                <dl class="text-sm" style="margin:0;display:grid;grid-template-columns:auto 1fr;gap:0.375rem 0.75rem">
                                    <dt class="text-muted">Player</dt>
                                    <dd class="font-medium" style="margin:0">{$protest.name|escape}</dd>

                                    <dt class="text-muted">SteamID</dt>
                                    <dd class="font-mono" style="margin:0">
                                        {if $protest.authid == ""}<span class="text-faint">no steamid present</span>{else}{$protest.authid|escape}{/if}
                                    </dd>

                                    <dt class="text-muted">IP address</dt>
                                    <dd class="font-mono" style="margin:0">
                                        {if $protest.ip == 'none' OR $protest.ip == ''}<span class="text-faint">no IP address present</span>{else}{$protest.ip|escape}{/if}
                                    </dd>

                                    <dt class="text-muted">Invoked on</dt>
                                    <dd style="margin:0">{$protest.date|escape}</dd>

                                    <dt class="text-muted">End date</dt>
                                    <dd style="margin:0">
                                        {if $protest.ends == 'never'}<span class="text-faint">Not applicable.</span>{else}{$protest.ends|escape}{/if}
                                    </dd>

                                    <dt class="text-muted">Ban reason</dt>
                                    {* nofilter: protest.ban_reason is htmlspecialchars($protestb['reason']) in admin.bans.php, already entity-escaped. *}
                                    <dd style="margin:0">{$protest.ban_reason nofilter}</dd>

                                    <dt class="text-muted">Banned by</dt>
                                    <dd style="margin:0">{$protest.admin|escape}</dd>

                                    <dt class="text-muted">Server</dt>
                                    <dd class="font-mono" style="margin:0">{$protest.server|escape}</dd>

                                    <dt class="text-muted">Protester IP</dt>
                                    <dd class="font-mono" style="margin:0">{$protest.pip|escape}</dd>

                                    <dt class="text-muted">Protested on</dt>
                                    <dd style="margin:0">{$protest.datesubmitted|escape}</dd>

                                    <dt class="text-muted">Message</dt>
                                    {* nofilter: protest.reason is wordwrap(htmlspecialchars($prot['reason']), 55, "<br />\n", true) — already entity-escaped in admin.bans.php. *}
                                    <dd style="margin:0">{$protest.reason nofilter}</dd>
                                </dl>

                                <ul class="text-sm" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:0.375rem">
                                    {* nofilter: protaddcomment is CreateLinkR-built `<a>` HTML with integer pid + static URL; no user input flows in. *}
                                    <li>{$protest.protaddcomment nofilter}</li>
                                </ul>
                            </div>

                            {if $protest.commentdata != "None"}
                                <div class="mt-4">
                                    <h4 class="text-xs text-muted" style="font-weight:600;margin:0 0 0.5rem;text-transform:uppercase;letter-spacing:0.06em">Comments</h4>
                                    <div class="space-y-3">
                                        {foreach from=$protest.commentdata item=commenta}
                                            <div class="card p-4">
                                                <div class="flex items-center justify-between gap-2 text-xs text-muted mb-2">
                                                    <strong style="color:var(--text);font-weight:600">
                                                        {if !empty($commenta.comname)}{$commenta.comname|escape}{else}<i class="text-faint">Admin deleted</i>{/if}
                                                    </strong>
                                                    <span class="flex items-center gap-2">
                                                        <span>{$commenta.added|escape}</span>
                                                        {if $commenta.editcomlink != ""}
                                                            {* nofilter: editcomlink/delcomlink are CreateLinkR-built HTML from admin.bans.php with integer cid + literal pid; no user input. *}
                                                            <span>{$commenta.editcomlink nofilter} {$commenta.delcomlink nofilter}</span>
                                                        {/if}
                                                    </span>
                                                </div>
                                                <div class="text-sm" style="word-break:break-all;word-wrap:break-word">
                                                    {* nofilter: commenttxt passes through encodePreservingBr (htmlspecialchars per-segment) + URL-wrap regex; admin input is HTML-escaped before `<a>`/`<br>` tags are reintroduced. *}
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
                                <div class="text-xs text-faint mt-3">{$protest.commentdata|escape}</div>
                            {/if}
                        </div>
                    </details>
                {/foreach}
            </div>
        {/if}
    </section>
{/if}
{* Inline action wiring — Remove dispatches Actions.ProtestsRemove with
   archiv=1. Both protest templates listen for `protest-archive` so the
   handler is shared; the archive-template adds a `protest-archive-toggle`
   variant for restore/delete to keep the two click handlers independent. *}
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
        var btn = t.closest('[data-action="protest-archive"]');
        if (!btn) return;
        e.preventDefault();
        var pid = Number(btn.dataset.pid);
        var key = btn.dataset.key || ('protest #' + pid);
        var archiv = btn.dataset.archiv || '1';
        var msg;
        if (archiv === '2') msg = 'Restore the ban protest for "' + key + '" from the archive?';
        else if (archiv === '1') msg = 'Move the ban protest for "' + key + '" to the archive?';
        else msg = 'Delete the ban protest for "' + key + '"?';
        if (!window.confirm(msg)) return;
        var a = api(), A = actions();
        if (!a || !A || !Number.isFinite(pid)) return;
        btn.disabled = true;
        a.call(A.ProtestsRemove, { pid: pid, archiv: archiv }).then(function (r) {
            if (!r || r.ok === false) {
                btn.disabled = false;
                toast('error', 'Action failed', (r && r.error && r.error.message) || 'Unknown error');
                return;
            }
            var node = document.getElementById('pid_' + pid);
            if (node && node.parentNode) node.parentNode.removeChild(node);
            var counter = document.getElementById('protcount');
            if (counter) counter.textContent = String(Math.max(0, Number(counter.textContent) - 1));
            toast('success', 'Done', 'Protest updated.');
        });
    });
})();
</script>
{/literal}
