{*
    SourceBans++ 2026 — page / page_admin_settings_logs.tpl

    "System log" sub-tab on the admin Settings page. Pair:
    Sbpp\View\AdminLogsView + web/pages/admin.settings.php (which
    routes by ?section= and renders one View per request — see
    sibling page_admin_settings_settings.tpl for the rationale).

    Variable contract (kept in sync by SmartyTemplateRule):
        Permission gates:
            $can_web_settings — required to see the table; mirrors
                the legacy ALL_WEB gate on the System Log tab.
            $can_owner — required to truncate the log table; gates
                the "Clear log" button. Mirrors legacy
                CheckAccess(ADMIN_OWNER) before TRUNCATE.
        Section nav: $active_section.
        Pagination: $page_numbers (server-built nav HTML emitted via
            nofilter; see annotation below).
        Rows: $log_items — list of legacy-shaped log dicts.

    Testability hooks:
        - Sub-nav links: data-testid="settings-tab-<key>".
        - Each summary row: data-testid="log-row" + data-id (lid).
        - "Clear log" button: data-testid="logs-clear".
*}
<div class="p-6">
    <div class="mb-6">
        <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">Settings</h1>
        <p class="text-sm text-muted m-0 mt-2">System log of admin actions and warnings.</p>
    </div>

    <div class="grid gap-4" style="grid-template-columns:14rem 1fr;align-items:start">
        <nav aria-label="Settings sections" role="tablist">
            <a class="sidebar__link" href="?p=admin&amp;c=settings&amp;section=settings"
               role="tab"
               data-testid="settings-tab-settings"
               {if $active_section == 'settings'}aria-current="page"{/if}>
                <i data-lucide="settings"></i> Main
            </a>
            <a class="sidebar__link" href="?p=admin&amp;c=settings&amp;section=features"
               role="tab"
               data-testid="settings-tab-features"
               {if $active_section == 'features'}aria-current="page"{/if}>
                <i data-lucide="toggle-right"></i> Features
            </a>
            <a class="sidebar__link" href="?p=admin&amp;c=settings&amp;section=logs"
               role="tab"
               data-testid="settings-tab-logs"
               {if $active_section == 'logs'}aria-current="page"{/if}>
                <i data-lucide="scroll-text"></i> System Log
            </a>
            <a class="sidebar__link" href="?p=admin&amp;c=settings&amp;section=themes"
               role="tab"
               data-testid="settings-tab-themes"
               {if $active_section == 'themes'}aria-current="page"{/if}>
                <i data-lucide="palette"></i> Themes
            </a>
        </nav>

        <div>
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
                            <table class="table" data-testid="logs-table">
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
                                        <tr data-testid="log-row" data-id="{$log.lid}" onclick="toggleLogRow(this);">
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
                                        <tr data-detail-for="{$log.lid}" hidden>
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
                        {else}
                            <p class="text-muted text-sm m-0">No log entries.</p>
                        {/if}
                    </div>
                </div>
            {/if}
        </div>
    </div>
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
     */
    window.toggleLogRow = function (row) {
        if (!row || !row.dataset || !row.dataset.id) return;
        var detail = document.querySelector('tr[data-detail-for="' + row.dataset.id + '"]');
        if (!detail) return;
        if (detail.hasAttribute('hidden')) {
            detail.removeAttribute('hidden');
        } else {
            detail.setAttribute('hidden', '');
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
