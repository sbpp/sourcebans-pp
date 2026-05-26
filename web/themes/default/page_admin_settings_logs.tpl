{*
    SourceBans++ 2026 — page / page_admin_settings_logs.tpl

    "System log" sub-tab on the admin Settings page. Pair:
    Sbpp\View\AdminLogsView + web/pages/admin.settings.php (which
    routes by ?section= and renders one View per request — see
    sibling page_admin_settings_settings.tpl for the rationale).

    #1259 — sidebar lifted into a shared partial: the inline
    `<nav>` block + `grid-template-columns:14rem 1fr` shell that
    used to wrap this template's content is now driven by
    `core/admin_sidebar.tpl` via `web/includes/View/AdminTabs.php`. The
    page handler (admin.settings.php) opens the shell BEFORE this
    template renders. See AGENTS.md "Sub-paged admin routes".

    Variable contract (kept in sync by SmartyTemplateRule):
        Permission gates:
            $can_web_settings — required to see the table; mirrors
                the legacy ALL_WEB gate on the System Log tab.
            $can_owner — required to truncate the log table; gates
                the "Clear log" button. Mirrors legacy
                CheckAccess(ADMIN_OWNER) before TRUNCATE.
        Pagination: $page_numbers (server-built nav HTML emitted via
            nofilter; see annotation below).
        Rows: $log_items — list of legacy-shaped log dicts.

    Testability hooks:
        - Sidebar links: data-testid="admin-tab-<slug>" (#1259 unified
          shape across servers / mods / groups / settings).
        - Each desktop summary row: data-testid="log-row" + data-id (lid).
        - Each mobile card: data-testid="log-card" + data-id (lid).
        - Desktop table wrapper: data-testid="logs-table".
        - Mobile card wrapper:   data-testid="logs-cards".
        - "Clear log" button:    data-testid="logs-clear".

    #1266 — outer `.p-6` removed; the page inset lives on
    `.admin-sidebar-shell` so both grid columns share the same top y.

    #1462 — paired mobile card surface. At `<=768px` the global
    `.table { display: none }` rule (paired originally with the bans /
    comms `.ban-cards` mobile mirror in `theme.css`) collapses every
    `<table class="table">` on the panel. The System Log never got a
    paired mobile surface, so on mobile the chrome rendered (heading,
    "Clear log" button) while the rows themselves were silently
    hidden — the reporter saw "no logs visible" on the iPhone view.
    The `.log-cards` block below mirrors `$log_items` as native
    `<details>` disclosures so the same rows surface on mobile, with
    JS-free expansion + keyboard reachability + screen-reader
    announce out of the box. Desktop chrome unchanged.
*}
<div>
    <div class="mb-6">
        <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">Settings</h1>
        <p class="text-sm text-muted m-0 mt-2">System log of admin actions and warnings.</p>
    </div>

    {if NOT $can_web_settings}
        <div class="card">
            <div class="card__body">
                <p class="text-muted">Access denied. <code>ADMIN_WEB_SETTINGS</code> required.</p>
            </div>
        </div>
    {else}
                {* nofilter: $clear_logs is the legacy default-theme "( <a href='javascript:ClearLogs();'>Clear Log</a> )" link string built in admin.settings.php (static literal gated by ADMIN_OWNER, no user input). The sbpp2026 layout renders its own <button> below via $can_owner, so the legacy link string is captured-and-discarded — keeps the SmartyTemplateRule view↔template parity green without rendering the link twice. Drop this capture and the View property when D1 retires the default theme. *}
                {capture name="legacy_clear_logs"}{$clear_logs nofilter}{/capture}
                <div class="card">
                    <div class="card__header">
                        <div>
                            <h3>System log</h3>
                            <p>Click a row to expand. Newest first.</p>
                        </div>
                        {if $can_owner}
                            <button type="button"
                                    class="btn btn--danger btn--sm"
                                    data-testid="logs-clear"
                                    onclick="clearLogs();">
                                <i data-lucide="trash-2"></i> Clear log
                            </button>
                        {/if}
                    </div>
                    <div class="card__body">
                        <div class="text-xs text-muted mb-4">
                            {* nofilter: $page_numbers is server-built nav HTML; advSearch/advType (the only $_GET inputs) are htmlspecialchars(json_encode(...)) before interpolation in admin.settings.php. *}
                            {$page_numbers nofilter}
                        </div>

                        {if count($log_items) > 0}
                            {* #1443: this is the in-tree canonical surface where
                               the table-row IS the click target — `onclick="toggleLogRow"`
                               flips a sibling detail row. `.table--clickable-rows`
                               opts the rows into the row-wide `cursor: pointer`
                               affordance from `theme.css`. New surfaces that want
                               the same shape add the modifier class AND wire a
                               real `<tr>`-level click handler AND update the
                               regression test's allowlist; see the comment above
                               the `.table tbody tr` rule in `theme.css`. *}
                            <table class="table table--clickable-rows" data-testid="logs-table">
                                <thead>
                                    <tr>
                                        <th style="width:6rem">Type</th>
                                        <th>Event</th>
                                        <th style="width:14rem">User</th>
                                        <th style="width:14rem">Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {foreach from=$log_items item="log"}
                                        {* #1443 a11y: `role="button"` + `tabindex="0"` +
                                           `aria-expanded` + matching keydown handler make
                                           the row keyboard-reachable AND screen-reader-
                                           announced as an expandable disclosure. Pre-fix
                                           the only affordance was the `onclick` attribute
                                           — a bare `<tr>` with no role / tabindex / key
                                           handling — so keyboard-only and AT users had no
                                           way to expand a log row. *}
                                        {* #1462: extracted the inline `onkeydown` body to
                                           the page-tail `<script>{literal}` block (now
                                           `handleLogRowKey`). The original `onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleLogRow(this);}"`
                                           form (added in #1451) compiles cleanly when
                                           Smarty has a stale cached copy of the template
                                           on disk but fails fresh-compile with
                                           "Unexpected '.', expected one of: '}'" — the
                                           `{event.preventDefault();...}` substring is
                                           parsed as a Smarty tag by the default
                                           `auto_literal=true` (no whitespace after the
                                           open brace = parse as tag). Calling a tail-
                                           defined helper sidesteps the per-character
                                           braces hazard without losing keyboard
                                           reachability. *}
                                        <tr data-testid="log-row"
                                            data-id="{$log.lid}"
                                            role="button"
                                            tabindex="0"
                                            aria-expanded="false"
                                            aria-controls="log-detail-{$log.lid}"
                                            onclick="toggleLogRow(this);"
                                            onkeydown="handleLogRowKey(event, this);">
                                            <td>
                                                {if $log.type == 'm'}
                                                    <span class="pill pill--online"><i data-lucide="info" style="width:0.75rem;height:0.75rem"></i> Info</span>
                                                {elseif $log.type == 'w'}
                                                    <span class="pill pill--active"><i data-lucide="alert-triangle" style="width:0.75rem;height:0.75rem"></i> Warn</span>
                                                {elseif $log.type == 'e'}
                                                    <span class="pill pill--permanent"><i data-lucide="circle-x" style="width:0.75rem;height:0.75rem"></i> Error</span>
                                                {else}
                                                    <span class="pill pill--offline">{$log.type}</span>
                                                {/if}
                                            </td>
                                            <td>{$log.title}</td>
                                            <td class="font-mono text-xs">{$log.user}</td>
                                            <td class="font-mono text-xs tabular-nums">{$log.date_str}</td>
                                        </tr>
                                        <tr data-detail-for="{$log.lid}" id="log-detail-{$log.lid}" hidden>
                                            <td colspan="4" style="background:var(--bg-muted)">
                                                <dl class="grid gap-2 text-xs" style="grid-template-columns:8rem 1fr;margin:0">
                                                    <dt class="text-muted">Details</dt>
                                                    {* nofilter: $log.message is escaped via htmlentities() in admin.settings.php (line replaces literal <br/> tags then re-html-encodes) before being assigned. *}
                                                    <dd style="margin:0">{$log.message nofilter}</dd>
                                                    <dt class="text-muted">Function</dt>
                                                    <dd class="font-mono" style="margin:0">{$log.function}</dd>
                                                    <dt class="text-muted">Query</dt>
                                                    <dd class="font-mono" style="margin:0;word-break:break-all">{$log.query}</dd>
                                                    <dt class="text-muted">IP</dt>
                                                    <dd class="font-mono tabular-nums" style="margin:0">{$log.host}</dd>
                                                </dl>
                                            </td>
                                        </tr>
                                    {/foreach}
                                </tbody>
                            </table>

                            {* #1462: paired mobile card surface. The desktop table
                               above is hidden by the global `.table { display: none }`
                               rule at `<=768px`; this `<ul class="log-cards">` block
                               is hidden by default (`display: none`) and only paints
                               at the same `<=768px` breakpoint. Both surfaces iterate
                               the same `$log_items` so the visible row set is
                               viewport-symmetric. Native `<details>` carries the
                               disclosure semantic — no JS required, keyboard-
                               accessible, screen-reader-announced as an expandable
                               group out of the box. The `aria-label` on the `<ul>`
                               names the list for AT users; the `aria-hidden="true"`
                               on the desktop table's `<thead>` is unnecessary here
                               because each card carries its own per-row context in
                               the summary. *}
                            <ul class="log-cards" data-testid="logs-cards" aria-label="System log entries">
                                {foreach from=$log_items item="log"}
                                    <li>
                                        <details class="log-card"
                                                 data-testid="log-card"
                                                 data-id="{$log.lid}">
                                            <summary class="log-card__summary">
                                                {if $log.type == 'm'}
                                                    <span class="pill pill--online"><i data-lucide="info" style="width:0.75rem;height:0.75rem"></i> Info</span>
                                                {elseif $log.type == 'w'}
                                                    <span class="pill pill--active"><i data-lucide="alert-triangle" style="width:0.75rem;height:0.75rem"></i> Warn</span>
                                                {elseif $log.type == 'e'}
                                                    <span class="pill pill--permanent"><i data-lucide="circle-x" style="width:0.75rem;height:0.75rem"></i> Error</span>
                                                {else}
                                                    <span class="pill pill--offline">{$log.type}</span>
                                                {/if}
                                                <span class="log-card__title">{$log.title}</span>
                                                <i class="log-card__chevron" data-lucide="chevron-right" style="width:1rem;height:1rem" aria-hidden="true"></i>
                                            </summary>
                                            <div class="log-card__meta">
                                                <span><span class="text-muted">User:</span> {$log.user}</span>
                                                <span><span class="text-muted">Date:</span> {$log.date_str}</span>
                                            </div>
                                            <div class="log-card__body">
                                                <dl class="grid gap-2 text-xs" style="grid-template-columns:6rem 1fr;margin:0">
                                                    <dt class="text-muted">Details</dt>
                                                    {* nofilter: $log.message is escaped via htmlentities() in admin.settings.php (same value as the desktop table above; the page handler stages each log row once and both surfaces consume the same dict). *}
                                                    <dd style="margin:0">{$log.message nofilter}</dd>
                                                    <dt class="text-muted">Function</dt>
                                                    <dd class="font-mono" style="margin:0;word-break:break-all">{$log.function}</dd>
                                                    <dt class="text-muted">Query</dt>
                                                    <dd class="font-mono" style="margin:0;word-break:break-all">{$log.query}</dd>
                                                    <dt class="text-muted">IP</dt>
                                                    <dd class="font-mono tabular-nums" style="margin:0">{$log.host}</dd>
                                                </dl>
                                            </div>
                                        </details>
                                    </li>
                                {/foreach}
                            </ul>
                        {else}
                            <p class="text-muted text-sm m-0">No log entries.</p>
                        {/if}
                    </div>
                </div>
    {/if}
</div>

<script>
{literal}
// @ts-check
(function () {
    'use strict';

    /**
     * Toggle the hidden detail row that follows each log row. Detail rows
     * are emitted with data-detail-for="<lid>", so a click on the summary
     * row finds its sibling by id and flips the `hidden` attribute. No
     * accordion library, no jQuery, no global state.
     *
     * #1443 a11y: also flips the summary row's `aria-expanded` so AT users
     * hear the disclosure state. The summary row carries
     * `role="button" tabindex="0" aria-controls="log-detail-<lid>"` so it's
     * keyboard-reachable; the inline `onkeydown` handler dispatches Enter /
     * Space to the same toggle path.
     */
    window.toggleLogRow = function (row) {
        if (!row || !row.dataset || !row.dataset.id) return;
        var detail = document.querySelector('tr[data-detail-for="' + row.dataset.id + '"]');
        if (!detail) return;
        if (detail.hasAttribute('hidden')) {
            detail.removeAttribute('hidden');
            row.setAttribute('aria-expanded', 'true');
        } else {
            detail.setAttribute('hidden', '');
            row.setAttribute('aria-expanded', 'false');
        }
    };

    /**
     * Keyboard dispatcher for the desktop summary row. #1462 lifted this out
     * of an inline `onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();...}"`
     * attribute body because the inline shape compiles under a stale Smarty
     * cache but fails fresh-compile: the `{event.preventDefault();...}`
     * substring is parsed as a Smarty tag (no whitespace after `{` defeats
     * `auto_literal`). Keeping the dispatcher here also makes the
     * `role="button"` keyboard contract testable without parsing Smarty.
     */
    window.handleLogRowKey = function (event, row) {
        if (!event || !row) return;
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            window.toggleLogRow(row);
        }
    };

    /**
     * Truncate the log table by hitting the legacy `?log_clear=true`
     * endpoint on this page (admin.settings.php's TRUNCATE branch). We
     * full-page nav so the freshly-empty list paints without a JSON dance.
     * Confirm() so a misclick on the danger button doesn't nuke history.
     */
    window.clearLogs = function () {
        if (!window.confirm('Clear the entire system log? This cannot be undone.')) return;
        window.location.href = 'index.php?p=admin&c=settings&section=logs&log_clear=true';
    };
})();
{/literal}
</script>
