{*
    page_admin_audit.tpl -- Admin -> Audit log (#1123 B19).

    NEW page (no legacy template). Pair: web/pages/admin.audit.php +
    web/includes/View/AuditLogView.php. The page handler reads the
    :prefix_log table (no schema change), normalises each row into the
    handoff-style shape this template iterates over (tid, severity,
    severity_label, severity_class, time_human, time_iso, actor, title,
    detail, ip), and SSRs the filter/page state into the forms below.

    Auto-escape is on globally (web/init.php), so every Smarty
    variable below is HTML-escaped automatically. There is no nofilter
    use in this template, and none is needed: all values are either
    DB-stored strings rendered as text or numbers / class tokens we
    built ourselves in the page handler.

    Testability hooks (per #1123 issue body):
      - audit-search           -- free-text input
      - filter-chip-<kind>     -- severity filter buttons (all|info|warning|error)
      - audit-row              -- each log row, with data-id and data-severity
      - page-prev / page-next  -- pager links
*}
<style>
    .audit-page {
        padding: 1.5rem;
        max-width: 1400px;
    }
    .audit-page__header {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .audit-page__title {
        font-size: 1.5rem;
        font-weight: 600;
        margin: 0;
    }
    .audit-page__subtitle {
        margin: 0.5rem 0 0;
        color: var(--text-muted);
        font-size: var(--fs-sm);
    }
    .audit-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        align-items: center;
        margin-bottom: 1rem;
    }
    .audit-row {
        display: flex;
        gap: 0.75rem;
        align-items: flex-start;
        padding: 1rem;
        border-bottom: 1px solid var(--border);
    }
    .audit-row:last-child {
        border-bottom: none;
    }
    .audit-row__icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--bg-muted);
        color: var(--text-muted);
        display: grid;
        place-items: center;
        flex-shrink: 0;
    }
    .audit-row__body {
        flex: 1;
        min-width: 0;
    }
    .audit-row__title {
        font-weight: 500;
        font-size: var(--fs-base);
        margin: 0;
        word-wrap: break-word;
    }
    .audit-row__meta {
        margin-top: 0.125rem;
        color: var(--text-muted);
        font-size: var(--fs-xs);
        font-family: var(--font-mono);
    }
    .audit-row__detail {
        margin-top: 0.5rem;
        font-size: var(--fs-sm);
        color: var(--text-muted);
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0 0.5rem;
        height: 1.25rem;
        border-radius: var(--radius-full);
        font-size: 0.6875rem;
        font-weight: 500;
        box-shadow: inset 0 0 0 1px currentColor;
    }
    .badge--info    { background: var(--info-bg);    color: var(--info);    }
    .badge--warning { background: var(--warning-bg); color: var(--warning); }
    .badge--error   { background: var(--danger-bg);  color: var(--danger);  }
    .badge--system  { background: var(--bg-muted);   color: var(--text-muted); }
    .audit-empty {
        padding: 1.5rem;
        text-align: center;
        color: var(--text-muted);
        font-size: var(--fs-sm);
    }
    .audit-pager {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        margin-top: 1rem;
        flex-wrap: wrap;
    }
    .audit-pager__status {
        color: var(--text-muted);
        font-size: var(--fs-xs);
    }
    .audit-pager__nav {
        display: flex;
        gap: 0.5rem;
    }
    .audit-pager__nav .btn--disabled {
        opacity: 0.4;
        pointer-events: none;
    }
</style>

<div class="audit-page">
    <header class="audit-page__header">
        <div>
            <h1 class="audit-page__title">Audit log</h1>
            <p class="audit-page__subtitle">Every administrative action recorded by the panel ({$total_count} {if $total_count == 1}event{else}events{/if}).</p>
        </div>
    </header>

    {* Search and severity are independent filters that compose. Splitting
       them into two <form>s avoids the multi-submit-button trap where
       pressing Enter inside the search input would otherwise activate
       the first chip submit (and reset severity). Each form re-submits
       the *other* filter's current value via a hidden input. *}
    <div class="audit-filters">
        <form method="get" action="index.php" role="search" aria-label="Search audit log" style="flex:1 1 16rem;max-width:22rem">
            <input type="hidden" name="p" value="admin">
            <input type="hidden" name="c" value="audit">
            <input type="hidden" name="severity" value="{$current_severity}">
            <input type="search"
                   class="input"
                   name="search"
                   value="{$search}"
                   placeholder="Search title or detail…"
                   data-testid="audit-search"
                   aria-label="Search audit log">
        </form>

        <form method="get" action="index.php" class="flex items-center gap-2" style="flex-wrap:wrap" aria-label="Filter audit log by severity">
            <input type="hidden" name="p" value="admin">
            <input type="hidden" name="c" value="audit">
            <input type="hidden" name="search" value="{$search}">
            <button type="submit"
                    name="severity"
                    value=""
                    class="chip"
                    data-testid="filter-chip-all"
                    aria-pressed="{if $current_severity == ''}true{else}false{/if}">
                All
            </button>
            <button type="submit"
                    name="severity"
                    value="m"
                    class="chip"
                    data-testid="filter-chip-info"
                    aria-pressed="{if $current_severity == 'm'}true{else}false{/if}">
                <span class="chip__dot" style="background:var(--info)"></span>
                Info
            </button>
            <button type="submit"
                    name="severity"
                    value="w"
                    class="chip"
                    data-testid="filter-chip-warning"
                    aria-pressed="{if $current_severity == 'w'}true{else}false{/if}">
                <span class="chip__dot" style="background:var(--warning)"></span>
                Warning
            </button>
            <button type="submit"
                    name="severity"
                    value="e"
                    class="chip"
                    data-testid="filter-chip-error"
                    aria-pressed="{if $current_severity == 'e'}true{else}false{/if}">
                <span class="chip__dot" style="background:var(--danger)"></span>
                Error
            </button>
        </form>
    </div>

    <div class="card">
        {foreach $audit_log as $log}
            <article class="audit-row"
                     data-testid="audit-row"
                     data-id="{$log.tid}"
                     data-severity="{$log.severity_class}">
                <div class="audit-row__icon" aria-hidden="true">
                    <i data-lucide="{if $log.severity == 'e'}alert-octagon{elseif $log.severity == 'w'}alert-triangle{elseif $log.severity == 'm'}info{else}circle{/if}" style="width:14px;height:14px"></i>
                </div>
                <div class="audit-row__body">
                    <div class="flex items-center gap-2" style="flex-wrap:wrap">
                        <span class="badge badge--{$log.severity_class}" data-severity="{$log.severity_class}">{$log.severity_label}</span>
                        <span class="audit-row__title">{$log.title}</span>
                    </div>
                    <div class="audit-row__meta">
                        <span class="font-semibold">{$log.actor}</span>
                        {if $log.time_iso != ''}
                            · <time datetime="{$log.time_iso}">{$log.time_human}</time>
                        {else}
                            · {$log.time_human}
                        {/if}
                        {if $log.ip != ''}
                            · from {$log.ip}
                        {/if}
                    </div>
                    {if $log.detail != ''}
                        <div class="audit-row__detail">{$log.detail}</div>
                    {/if}
                </div>
            </article>
        {foreachelse}
            <div class="audit-empty">No audit events match these filters.</div>
        {/foreach}
    </div>

    {if $page_count > 1 || $current_page > 1}
        <nav class="audit-pager" aria-label="Audit log pagination">
            <div class="audit-pager__status" aria-live="polite">
                Page {$current_page} of {$page_count}
            </div>
            <div class="audit-pager__nav">
                <a class="btn btn--secondary btn--sm{if !$has_prev} btn--disabled{/if}"
                   href="{if $has_prev}{$prev_url}{else}#{/if}"
                   data-testid="page-prev"
                   {if !$has_prev}aria-disabled="true" tabindex="-1"{/if}>
                    <i data-lucide="chevron-left"></i>
                    Previous
                </a>
                <a class="btn btn--secondary btn--sm{if !$has_next} btn--disabled{/if}"
                   href="{if $has_next}{$next_url}{else}#{/if}"
                   data-testid="page-next"
                   {if !$has_next}aria-disabled="true" tabindex="-1"{/if}>
                    Next
                    <i data-lucide="chevron-right"></i>
                </a>
            </div>
        </nav>
    {/if}
</div>
