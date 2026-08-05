{*
    SourceBans++ 2026 — admin/admins list

    Pair: web/pages/admin.admins.php (renders this OR add OR overrides
    based on ?section=) and web/includes/View/AdminAdminsListView.php
    (typed DTO that SmartyTemplateRule keeps in lockstep with this file).

    Layout note: the embedded {load_template file="admin.admins.search"}
    runs admin.admins.search.php inline; that handler does its own
    $theme->assign / $theme->display, so the search-box variables are
    NOT part of this View's contract. Keep that boundary intact — adding
    search vars here would silently double-bind them.

    UX note: the legacy theme used MooTools' InitAccordion to expand a
    sub-row per admin with permission flags + actions. The 2026 footer
    intentionally drops sourcebans.js (#1123 D1 prep), so this template
    flattens the row into one table row with hover-revealed action
    buttons. Per-flag permission lists move to the edit-permissions
    page where they're actionable; the list page stays scannable.

    #1275 — Pattern A `?section=…` routing
    --------------------------------------
    Pre-#1275 this template opened a cross-template `.page-toc-shell`
    that spanned page_admin_admins_add.tpl + page_admin_overrides.tpl
    so all three sections stacked into one DOM and the page-level ToC
    (page_toc.tpl) emitted #fragment anchor jumps between them. #1275
    unifies on the same `?section=…` shape used by admin.servers.php /
    admin.settings.php / admin.mods.php / admin.groups.php — each
    section renders alone via AdminTabs. The shell wrappers are gone;
    the admin sidebar lives in core/admin_sidebar.tpl, mounted by
    AdminTabs.php. The search box stays embedded above the table
    because filtering is the same UX surface as browsing the list (one
    `<form>`, results re-render in place — splitting them across two
    URLs would force the user to bounce between pages to iterate
    filters). See the docblock on web/pages/admin.admins.php.
*}
{if !$can_list_admins}
    <div class="page-section">
        <div class="card">
            <div class="card__body">
                <p class="text-sm text-muted m-0">Access denied.</p>
            </div>
        </div>
    </div>
{else}
<div class="page-section">
    <div class="mb-4">
        <h1 style="font-size:var(--fs-xl);font-weight:600;margin:0">Admins
            <span class="text-faint" style="font-weight:400;margin-left:0.375rem" data-testid="admin-count">({$admin_count})</span>
        </h1>
        <p class="text-sm text-muted m-0 mt-2">Click an admin row's actions to edit details, permissions, or server access.</p>
    </div>

    <div class="mb-3" data-testid="admin-admins-section-search">
        {load_template file="admin.admins.search"}
    </div>

    <div class="chip-row mb-4" role="tablist" aria-label="Admin status filter" data-testid="admins-view-chips">
        <a class="chip"
           href="{$chip_base_link|escape}&amp;view=active"
           data-testid="admins-view-active"
           role="tab"
           data-active="{if $active_view == 'active'}true{else}false{/if}"
           aria-selected="{if $active_view == 'active'}true{else}false{/if}"
           {if $active_view == 'active'}aria-current="true"{/if}>Active</a>
        <a class="chip"
           href="{$chip_base_link|escape}&amp;view=inactive"
           data-testid="admins-view-inactive"
           role="tab"
           data-active="{if $active_view == 'inactive'}true{else}false{/if}"
           aria-selected="{if $active_view == 'inactive'}true{else}false{/if}"
           {if $active_view == 'inactive'}aria-current="true"{/if}>Inactive</a>
        <a class="chip"
           href="{$chip_base_link|escape}&amp;view=all"
           data-testid="admins-view-all"
           role="tab"
           data-active="{if $active_view == 'all'}true{else}false{/if}"
           aria-selected="{if $active_view == 'all'}true{else}false{/if}"
           {if $active_view == 'all'}aria-current="true"{/if}>All</a>
    </div>

    <div data-testid="admin-admins-section-admins">
        <div class="text-xs text-muted mb-2" data-testid="admin-nav">
            {* nofilter: server-built pagination HTML — `<displaying N - M of K results>` (integers), prev/next `<a>` from `CreateLinkR(…)`, and a page-jump `<select onchange>`. After #1207 ADM-4 every populated filter flows through `http_build_query($activeFilters)`, which percent-encodes filter values (so single quotes / angle brackets can't break out of the single-quoted `href='…'` or `onchange="… '…'…"` attributes). The page-jump `<select>` additionally `htmlspecialchars()`-escapes the base URL with `ENT_QUOTES` before interpolation. Loop counters and pre-computed page numbers are integers. No raw user input reaches the rendered string. *}
            {$admin_nav nofilter}
        </div>

        {if $can_delete_admins || $can_edit_admins}
        <div class="admins-bulk-bar"
             data-testid="admins-bulk-bar"
             hidden
             role="region"
             aria-label="Bulk admin actions">
            <span class="admins-bulk-bar__count text-sm font-medium" data-testid="admins-bulk-count">0 selected</span>
            <div class="admins-bulk-bar__actions">
                {if $can_delete_admins}
                <div class="admins-bulk-bar__group" role="group" aria-label="Lifecycle">
                    <button type="button" class="btn btn--secondary btn--sm" data-action="admins-bulk-deactivate" data-testid="admins-bulk-deactivate">
                        <i data-lucide="user-x" style="width:13px;height:13px"></i> Deactivate
                    </button>
                    <button type="button" class="btn btn--secondary btn--sm" data-action="admins-bulk-reactivate" data-testid="admins-bulk-reactivate">
                        <i data-lucide="user-check" style="width:13px;height:13px"></i> Reactivate
                    </button>
                </div>
                {/if}
                {if $can_edit_admins}
                <div class="admins-bulk-bar__group" role="group" aria-label="Groups">
                    <button type="button" class="btn btn--secondary btn--sm" data-action="admins-bulk-web-group" data-testid="admins-bulk-web-group">
                        <i data-lucide="shield" style="width:13px;height:13px"></i> Web group
                    </button>
                    <button type="button" class="btn btn--secondary btn--sm" data-action="admins-bulk-srv-group" data-testid="admins-bulk-srv-group">
                        <i data-lucide="server" style="width:13px;height:13px"></i> Server group
                    </button>
                </div>
                {/if}
                <div class="admins-bulk-bar__group" role="group" aria-label="Destructive and clear">
                    {if $can_delete_admins}
                    <button type="button" class="btn btn--danger btn--sm" data-action="admins-bulk-delete" data-testid="admins-bulk-delete">
                        <i data-lucide="trash-2" style="width:13px;height:13px"></i> Delete
                    </button>
                    {/if}
                    <button type="button" class="btn btn--secondary btn--sm" data-action="admins-bulk-clear" data-testid="admins-bulk-clear">Clear</button>
                </div>
            </div>
        </div>
        {/if}

        <div class="card" style="overflow:hidden">
            <div class="table-scroll">
            <table class="table" role="table" aria-label="Admins">
                <thead>
                    <tr>
                        {if $can_delete_admins || $can_edit_admins}
                        <th scope="col" style="width:2.5rem">
                            <input type="checkbox"
                                   data-action="admins-select-all"
                                   data-testid="admins-select-all"
                                   aria-label="Select all admins on this page">
                        </th>
                        {/if}
                        <th scope="col">Name</th>
                        <th scope="col">Bans</th>
                        <th scope="col">Server group</th>
                        <th scope="col">Web group</th>
                        <th scope="col">Immunity</th>
                        <th scope="col">Last visit</th>
                        <th scope="col" class="col-actions"></th>
                    </tr>
                </thead>
                <tbody>
                {foreach $admins as $admin}
                    <tr data-testid="admin-row"
                        data-id="{$admin.aid}"
                        data-enabled="{$admin.enabled|default:1}"
                        data-name="{$admin.user|escape}">
                        {if $can_delete_admins || $can_edit_admins}
                        <td>
                            {if (!empty($admin.is_owner)) || ($admin.aid == $current_aid)}
                                <input type="checkbox"
                                       disabled
                                       aria-label="Cannot select {$admin.user|escape}"
                                       data-testid="admin-row-select"
                                       title="{if $admin.aid == $current_aid}You cannot select yourself{else}Owner cannot be selected{/if}">
                            {else}
                                <input type="checkbox"
                                       data-action="admins-select-row"
                                       data-aid="{$admin.aid}"
                                       data-testid="admin-row-select"
                                       aria-label="Select {$admin.user|escape}">
                            {/if}
                        </td>
                        {/if}
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="avatar" style="width:1.75rem;height:1.75rem;background:var(--brand-600);font-size:var(--fs-xs)">
                                    {$admin.user|truncate:1:'':true|upper|escape}
                                </div>
                                <div>
                                    <div class="font-medium">
                                        {$admin.user|escape}
                                        {if isset($admin.enabled) && $admin.enabled == 0}
                                            <span class="pill pill--warn text-xs" style="margin-left:0.375rem" data-testid="admin-inactive-badge">Inactive</span>
                                        {/if}
                                    </div>
                                    <div class="text-xs text-faint" style="margin-top:0.125rem">aid {$admin.aid}</div>
                                </div>
                            </div>
                        </td>
                        <td class="tabular-nums text-muted">
                            <a href="./index.php?p=banlist&advSearch={$admin.aid|escape:'url'}&advType=admin"
                               title="Show bans">{$admin.bancount}</a>
                            <span class="text-faint"> · </span>
                            <a href="./index.php?p=banlist&advSearch={$admin.aid|escape:'url'}&advType=nodemo"
                               title="Show bans without demo">{$admin.nodemocount} w/o demo</a>
                        </td>
                        <td class="text-muted truncate" style="max-width:10rem" title="{$admin.server_group|escape}">{$admin.server_group|escape}</td>
                        <td class="text-muted truncate" style="max-width:10rem" title="{$admin.web_group|escape}">{$admin.web_group|escape}</td>
                        <td class="tabular-nums text-muted">{$admin.immunity}</td>
                        <td class="text-xs text-muted">{$admin.lastvisit|escape}</td>
                        <td class="col-actions">
                            <div class="row-actions row-actions--icons">
                                {if $can_edit_admins}
                                    <a class="btn btn--ghost btn--icon btn--sm"
                                       href="index.php?p=admin&c=admins&o=editdetails&id={$admin.aid|escape:'url'}"
                                       data-tooltip="Edit details"
                                       aria-label="Edit details for {$admin.user|escape}"
                                       data-testid="admin-action-edit-details">
                                        <i data-lucide="clipboard-list" style="width:14px;height:14px"></i>
                                    </a>
                                    <a class="btn btn--ghost btn--icon btn--sm"
                                       href="index.php?p=admin&c=admins&o=editpermissions&id={$admin.aid|escape:'url'}"
                                       data-tooltip="Edit permissions"
                                       aria-label="Edit permissions for {$admin.user|escape}"
                                       data-testid="admin-action-edit-perms">
                                        <i data-lucide="shield" style="width:14px;height:14px"></i>
                                    </a>
                                    <a class="btn btn--ghost btn--icon btn--sm"
                                       href="index.php?p=admin&c=admins&o=editservers&id={$admin.aid|escape:'url'}"
                                       data-tooltip="Edit server access"
                                       aria-label="Edit server access for {$admin.user|escape}"
                                       data-testid="admin-action-edit-servers">
                                        <i data-lucide="server" style="width:14px;height:14px"></i>
                                    </a>
                                    <a class="btn btn--ghost btn--icon btn--sm"
                                       href="index.php?p=admin&c=admins&o=editgroup&id={$admin.aid|escape:'url'}"
                                       data-tooltip="Edit groups"
                                       aria-label="Edit groups for {$admin.user|escape}"
                                       data-testid="admin-action-edit-group">
                                        <i data-lucide="users" style="width:14px;height:14px"></i>
                                    </a>
                                {/if}
                                {if $can_delete_admins}
                                    {if isset($admin.enabled) && $admin.enabled == 0}
                                        <button type="button" class="btn btn--ghost btn--icon btn--sm"
                                                data-action="admins-reactivate"
                                                data-aid="{$admin.aid}"
                                                data-name="{$admin.user|escape}"
                                                data-tooltip="Reactivate admin"
                                                aria-label="Reactivate admin {$admin.user|escape}"
                                                data-testid="admin-action-reactivate">
                                            <i data-lucide="user-check" style="width:14px;height:14px"></i>
                                        </button>
                                    {else}
                                        <button type="button" class="btn btn--ghost btn--icon btn--sm"
                                                data-action="admins-deactivate"
                                                data-aid="{$admin.aid}"
                                                data-name="{$admin.user|escape}"
                                                data-tooltip="Deactivate admin"
                                                aria-label="Deactivate admin {$admin.user|escape}"
                                                data-testid="admin-action-deactivate">
                                            <i data-lucide="user-x" style="width:14px;height:14px"></i>
                                        </button>
                                    {/if}
                                    <button type="button" class="btn btn--ghost btn--icon btn--sm"
                                            data-action="admins-delete"
                                            data-aid="{$admin.aid}"
                                            data-name="{$admin.user|escape}"
                                            data-fallback-href="index.php?p=admin&amp;c=admins"
                                            data-tooltip="Delete admin"
                                            aria-label="Delete admin {$admin.user|escape}"
                                            data-testid="admin-action-delete">
                                        <i data-lucide="trash-2" style="width:14px;height:14px;color:var(--danger)"></i>
                                    </button>
                                {/if}
                            </div>
                        </td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
            </div>

            {* Mobile cards — paired surface for the global
               `@media (max-width: 768px) { .table { display: none } }`
               rule. Same display dance as `.ban-cards` / `.log-cards`. *}
            <div class="admins-list-cards" data-testid="admins-list-cards">
                {if $can_delete_admins || $can_edit_admins}
                <div class="admins-list-select-all" data-testid="admins-select-all-mobile-wrap">
                    <label class="admins-list-select-all__label">
                        <input type="checkbox"
                               data-action="admins-select-all"
                               data-testid="admins-select-all-mobile"
                               aria-label="Select all admins on this page">
                        <span>Select all on this page</span>
                    </label>
                </div>
                {/if}
                {foreach $admins as $admin}
                    <div class="admins-list-card"
                         data-testid="admins-list-card"
                         data-id="{$admin.aid}"
                         data-enabled="{$admin.enabled|default:1}"
                         data-name="{$admin.user|escape}">
                        <div class="admins-list-card__body flex items-center gap-3">
                            {if $can_delete_admins || $can_edit_admins}
                                {if (!empty($admin.is_owner)) || ($admin.aid == $current_aid)}
                                    <input type="checkbox"
                                           disabled
                                           aria-label="Cannot select {$admin.user|escape}"
                                           data-testid="admin-row-select-mobile"
                                           title="{if $admin.aid == $current_aid}You cannot select yourself{else}Owner cannot be selected{/if}">
                                {else}
                                    <input type="checkbox"
                                           data-action="admins-select-row"
                                           data-aid="{$admin.aid}"
                                           data-testid="admin-row-select-mobile"
                                           aria-label="Select {$admin.user|escape}">
                                {/if}
                            {/if}
                            <div class="avatar" style="width:2.25rem;height:2.25rem;background:var(--brand-600);font-size:var(--fs-xs)">
                                {$admin.user|truncate:1:'':true|upper|escape}
                            </div>
                            <div style="flex:1;min-width:0">
                                <div class="font-medium text-sm truncate">{$admin.user|escape}</div>
                                <div class="text-xs text-muted truncate" style="margin-top:0.125rem">
                                    {$admin.web_group|escape}
                                    · {$admin.server_group|escape}
                                    · imm {$admin.immunity}
                                </div>
                                <div class="text-xs text-faint truncate" style="margin-top:0.125rem">
                                    <a href="./index.php?p=banlist&amp;advSearch={$admin.aid|escape:'url'}&amp;advType=admin">{$admin.bancount} bans</a>
                                    · {$admin.lastvisit|escape}
                                </div>
                            </div>
                        </div>
                        {if $can_edit_admins || $can_delete_admins}
                        <div class="row-actions ban-card__actions">
                            {if $can_edit_admins}
                                <a class="btn btn--ghost btn--icon btn--sm"
                                   href="index.php?p=admin&amp;c=admins&amp;o=editdetails&amp;id={$admin.aid|escape:'url'}"
                                   data-tooltip="Edit details"
                                   aria-label="Edit details for {$admin.user|escape}"
                                   data-testid="admin-action-edit-details-mobile">
                                    <i data-lucide="clipboard-list" style="width:14px;height:14px"></i>
                                </a>
                                <a class="btn btn--ghost btn--icon btn--sm"
                                   href="index.php?p=admin&amp;c=admins&amp;o=editpermissions&amp;id={$admin.aid|escape:'url'}"
                                   data-tooltip="Edit permissions"
                                   aria-label="Edit permissions for {$admin.user|escape}"
                                   data-testid="admin-action-edit-perms-mobile">
                                    <i data-lucide="shield" style="width:14px;height:14px"></i>
                                </a>
                                <a class="btn btn--ghost btn--icon btn--sm"
                                   href="index.php?p=admin&amp;c=admins&amp;o=editservers&amp;id={$admin.aid|escape:'url'}"
                                   data-tooltip="Edit server access"
                                   aria-label="Edit server access for {$admin.user|escape}"
                                   data-testid="admin-action-edit-servers-mobile">
                                    <i data-lucide="server" style="width:14px;height:14px"></i>
                                </a>
                                <a class="btn btn--ghost btn--icon btn--sm"
                                   href="index.php?p=admin&amp;c=admins&amp;o=editgroup&amp;id={$admin.aid|escape:'url'}"
                                   data-tooltip="Edit groups"
                                   aria-label="Edit groups for {$admin.user|escape}"
                                   data-testid="admin-action-edit-group-mobile">
                                    <i data-lucide="users" style="width:14px;height:14px"></i>
                                </a>
                            {/if}
                            {if $can_delete_admins}
                                    {if isset($admin.enabled) && $admin.enabled == 0}
                                        <button type="button" class="btn btn--ghost btn--icon btn--sm"
                                                data-action="admins-reactivate"
                                                data-aid="{$admin.aid}"
                                                data-name="{$admin.user|escape}"
                                                data-tooltip="Reactivate admin"
                                                aria-label="Reactivate admin {$admin.user|escape}"
                                                data-testid="admin-action-reactivate-mobile">
                                            <i data-lucide="user-check" style="width:14px;height:14px"></i>
                                        </button>
                                    {else}
                                        <button type="button" class="btn btn--ghost btn--icon btn--sm"
                                                data-action="admins-deactivate"
                                                data-aid="{$admin.aid}"
                                                data-name="{$admin.user|escape}"
                                                data-tooltip="Deactivate admin"
                                                aria-label="Deactivate admin {$admin.user|escape}"
                                                data-testid="admin-action-deactivate-mobile">
                                            <i data-lucide="user-x" style="width:14px;height:14px"></i>
                                        </button>
                                    {/if}
                                <button type="button" class="btn btn--ghost btn--icon btn--sm"
                                        data-action="admins-delete"
                                        data-aid="{$admin.aid}"
                                        data-name="{$admin.user|escape}"
                                        data-fallback-href="index.php?p=admin&amp;c=admins"
                                        data-tooltip="Delete admin"
                                        aria-label="Delete admin {$admin.user|escape}"
                                        data-testid="admin-action-delete-mobile">
                                    <i data-lucide="trash-2" style="width:14px;height:14px;color:var(--danger)"></i>
                                </button>
                            {/if}
                        </div>
                        {/if}
                    </div>
                {/foreach}
            </div>
        </div>
    </div>

    {* ============================================================
       #1352 — admin-delete confirm + reason modal scaffold.

       Mirrors the canonical `#bans-unban-dialog` (`page_bans.tpl`,
       #1301) and `#comms-unblock-dialog` (`page_comms.tpl`, #1301)
       shapes. The pre-fix delete button called a long-deleted
       `RemoveAdmin()` JS helper from sourcebans.js (removed at
       #1123 D1) and the `typeof RemoveAdmin === 'function'` guard
       made every click a silent no-op. v1.x also surfaced a
       confirm prompt before deleting; this dialog restores that
       safeguard plus an optional reason field that flows into the
       audit-log entry — destructive irreversible row-flips need
       both per AGENTS.md "Reason-less, no-confirm" anti-pattern.

       The reason is OPTIONAL (vs the required-reason shape on
       bans-unban / comms-unblock) because admin deletion is the
       end of an admin's lifecycle, not a moderation action against
       a player — the audit value is "who removed this admin and
       optionally why" rather than "the admin must justify lifting
       a punishment". Server-side handler accepts empty `ureason`;
       the audit-log entry omits the `Reason: …` suffix when empty.
       ============================================================ *}
    <dialog id="admins-delete-dialog"
            class="palette"
            aria-labelledby="admins-delete-dialog-title"
            data-testid="admins-delete-dialog"
            hidden
            style="max-width:32rem;width:90vw;padding:1.25rem;border-radius:0.75rem;border:1px solid var(--border)">
        <form method="dialog" data-testid="admins-delete-form">
            <h2 id="admins-delete-dialog-title" style="font-size:var(--fs-lg);font-weight:600;margin:0 0 0.25rem">Delete admin</h2>
            <p class="text-sm text-muted m-0" style="margin-bottom:0.75rem">
                You're about to permanently delete <strong data-testid="admins-delete-target">this admin</strong>. This cannot be undone. Their server access is revoked immediately and any rehash queue is flushed.
            </p>
            <label class="label" for="admins-delete-reason">Reason (optional)</label>
            {* aria-required (not the native `required`) parity with the
               canonical confirm-modal shape — see the matching note on
               `#bans-unban-dialog` / `#comms-unblock-dialog`. We mark
               it `false` here because the reason is optional for the
               delete-admin surface (vs required for the unban /
               unblock surfaces); declaring the attribute keeps the
               assistive-tech contract explicit. *}
            <textarea class="textarea"
                      id="admins-delete-reason"
                      data-testid="admins-delete-reason"
                      rows="3"
                      aria-required="false"
                      maxlength="255"
                      autocomplete="off"
                      placeholder="Audit-log only. Leave blank to skip."></textarea>
            <p class="text-xs" data-testid="admins-delete-error" role="alert" hidden style="color:var(--danger);margin:0.25rem 0 0"></p>
            <div class="flex gap-2 mt-4" style="justify-content:flex-end">
                <button type="button" class="btn btn--secondary" data-testid="admins-delete-cancel" value="cancel">Cancel</button>
                <button type="submit" class="btn btn--danger" data-testid="admins-delete-submit" value="confirm">
                    <i data-lucide="trash-2" style="width:13px;height:13px"></i> Delete admin
                </button>
            </div>
        </form>
    </dialog>

    <dialog id="admins-deactivate-dialog"
            class="palette"
            aria-labelledby="admins-deactivate-dialog-title"
            data-testid="admins-deactivate-dialog"
            hidden
            style="max-width:32rem;width:90vw;padding:1.25rem;border-radius:0.75rem;border:1px solid var(--border)">
        <form method="dialog" data-testid="admins-deactivate-form">
            <h2 id="admins-deactivate-dialog-title" style="font-size:var(--fs-lg);font-weight:600;margin:0 0 0.25rem">Deactivate admin</h2>
            <p class="text-sm text-muted m-0" style="margin-bottom:0.75rem">
                You're about to deactivate <strong data-testid="admins-deactivate-target">this admin</strong>.
                They will lose panel login and in-game admin access. Ban history still shows their name.
            </p>
            <label class="label" for="admins-deactivate-reason">Reason (optional)</label>
            <textarea class="textarea"
                      id="admins-deactivate-reason"
                      data-testid="admins-deactivate-reason"
                      rows="3"
                      aria-required="false"
                      maxlength="255"
                      autocomplete="off"
                      placeholder="Audit-log only. Leave blank to skip."></textarea>
            <p class="text-xs" data-testid="admins-deactivate-error" role="alert" hidden style="color:var(--danger);margin:0.25rem 0 0"></p>
            <div class="flex gap-2 mt-4" style="justify-content:flex-end">
                <button type="button" class="btn btn--secondary" data-testid="admins-deactivate-cancel" value="cancel">Cancel</button>
                <button type="submit" class="btn btn--primary" data-testid="admins-deactivate-submit" value="confirm">
                    <i data-lucide="user-x" style="width:13px;height:13px"></i> Deactivate
                </button>
            </div>
        </form>
    </dialog>

    <dialog id="admins-bulk-deactivate-dialog"
            class="palette"
            aria-labelledby="admins-bulk-deactivate-dialog-title"
            data-testid="admins-bulk-deactivate-dialog"
            hidden
            style="max-width:32rem;width:90vw;padding:1.25rem;border-radius:0.75rem;border:1px solid var(--border)">
        <form method="dialog" data-testid="admins-bulk-deactivate-form">
            <h2 id="admins-bulk-deactivate-dialog-title" style="font-size:var(--fs-lg);font-weight:600;margin:0 0 0.25rem">Deactivate selected admins</h2>
            <p class="text-sm text-muted m-0" style="margin-bottom:0.75rem">
                You're about to deactivate <strong data-testid="admins-bulk-deactivate-target">0 admins</strong>.
                They will lose panel login and in-game admin access.
            </p>
            <label class="label" for="admins-bulk-deactivate-reason">Reason (optional)</label>
            <textarea class="textarea"
                      id="admins-bulk-deactivate-reason"
                      data-testid="admins-bulk-deactivate-reason"
                      rows="3"
                      aria-required="false"
                      maxlength="255"
                      autocomplete="off"
                      placeholder="Audit-log only. Leave blank to skip."></textarea>
            <p class="text-xs" data-testid="admins-bulk-deactivate-error" role="alert" hidden style="color:var(--danger);margin:0.25rem 0 0"></p>
            <div class="flex gap-2 mt-4" style="justify-content:flex-end">
                <button type="button" class="btn btn--secondary" data-testid="admins-bulk-deactivate-cancel" value="cancel">Cancel</button>
                <button type="submit" class="btn btn--primary" data-testid="admins-bulk-deactivate-submit" value="confirm">Deactivate</button>
            </div>
        </form>
    </dialog>

    <dialog id="admins-bulk-delete-dialog"
            class="palette"
            aria-labelledby="admins-bulk-delete-dialog-title"
            data-testid="admins-bulk-delete-dialog"
            hidden
            style="max-width:32rem;width:90vw;padding:1.25rem;border-radius:0.75rem;border:1px solid var(--border)">
        <form method="dialog" data-testid="admins-bulk-delete-form">
            <h2 id="admins-bulk-delete-dialog-title" style="font-size:var(--fs-lg);font-weight:600;margin:0 0 0.25rem">Delete selected admins</h2>
            <p class="text-sm text-muted m-0" style="margin-bottom:0.75rem">
                You're about to permanently delete <strong data-testid="admins-bulk-delete-target">0 admins</strong>. This cannot be undone.
            </p>
            <label class="label" for="admins-bulk-delete-reason">Reason (optional)</label>
            <textarea class="textarea"
                      id="admins-bulk-delete-reason"
                      data-testid="admins-bulk-delete-reason"
                      rows="3"
                      aria-required="false"
                      maxlength="255"
                      autocomplete="off"
                      placeholder="Audit-log only. Leave blank to skip."></textarea>
            <p class="text-xs" data-testid="admins-bulk-delete-error" role="alert" hidden style="color:var(--danger);margin:0.25rem 0 0"></p>
            <div class="flex gap-2 mt-4" style="justify-content:flex-end">
                <button type="button" class="btn btn--secondary" data-testid="admins-bulk-delete-cancel" value="cancel">Cancel</button>
                <button type="submit" class="btn btn--danger" data-testid="admins-bulk-delete-submit" value="confirm">Delete</button>
            </div>
        </form>
    </dialog>

    <dialog id="admins-bulk-web-group-dialog"
            class="palette"
            aria-labelledby="admins-bulk-web-group-dialog-title"
            data-testid="admins-bulk-web-group-dialog"
            hidden
            style="max-width:32rem;width:90vw;padding:1.25rem;border-radius:0.75rem;border:1px solid var(--border)">
        <form method="dialog" data-testid="admins-bulk-web-group-form">
            <h2 id="admins-bulk-web-group-dialog-title" style="font-size:var(--fs-lg);font-weight:600;margin:0 0 0.25rem">Assign web group</h2>
            <p class="text-sm text-muted m-0" style="margin-bottom:0.75rem">
                Set the web group for <strong data-testid="admins-bulk-web-group-target">0 admins</strong>.
            </p>
            <label class="label" for="admins-bulk-web-group-select">Web group</label>
            <select class="select" id="admins-bulk-web-group-select" data-testid="admins-bulk-web-group-select">
                <option value="0">No web group</option>
                {foreach $web_groups as $g}
                    <option value="{$g.gid}">{$g.name|escape}</option>
                {/foreach}
            </select>
            <p class="text-xs" data-testid="admins-bulk-web-group-error" role="alert" hidden style="color:var(--danger);margin:0.25rem 0 0"></p>
            <div class="flex gap-2 mt-4" style="justify-content:flex-end">
                <button type="button" class="btn btn--secondary" data-testid="admins-bulk-web-group-cancel" value="cancel">Cancel</button>
                <button type="submit" class="btn btn--primary" data-testid="admins-bulk-web-group-submit" value="confirm">Apply</button>
            </div>
        </form>
    </dialog>

    <dialog id="admins-bulk-srv-group-dialog"
            class="palette"
            aria-labelledby="admins-bulk-srv-group-dialog-title"
            data-testid="admins-bulk-srv-group-dialog"
            hidden
            style="max-width:32rem;width:90vw;padding:1.25rem;border-radius:0.75rem;border:1px solid var(--border)">
        <form method="dialog" data-testid="admins-bulk-srv-group-form">
            <h2 id="admins-bulk-srv-group-dialog-title" style="font-size:var(--fs-lg);font-weight:600;margin:0 0 0.25rem">Assign server group</h2>
            <p class="text-sm text-muted m-0" style="margin-bottom:0.75rem">
                Set the SourceMod server group for <strong data-testid="admins-bulk-srv-group-target">0 admins</strong>.
            </p>
            <label class="label" for="admins-bulk-srv-group-select">Server group</label>
            <select class="select" id="admins-bulk-srv-group-select" data-testid="admins-bulk-srv-group-select">
                <option value="0">No server group</option>
                {foreach $srv_groups as $g}
                    <option value="{$g.id}">{$g.name|escape}</option>
                {/foreach}
            </select>
            <p class="text-xs" data-testid="admins-bulk-srv-group-error" role="alert" hidden style="color:var(--danger);margin:0.25rem 0 0"></p>
            <div class="flex gap-2 mt-4" style="justify-content:flex-end">
                <button type="button" class="btn btn--secondary" data-testid="admins-bulk-srv-group-cancel" value="cancel">Cancel</button>
                <button type="submit" class="btn btn--primary" data-testid="admins-bulk-srv-group-submit" value="confirm">Apply</button>
            </div>
        </form>
    </dialog>

    {* ============================================================
       #1352 — admins-delete row-action wiring (inline page-tail JS).

       Click delegation: every Delete button in the rows above carries
       `data-action="admins-delete"` plus `data-aid` / `data-name` /
       `data-fallback-href`. The handler intercepts those clicks, opens
       the `#admins-delete-dialog` <dialog>, accepts an optional reason
       on submit, calls `sb.api.call(Actions.AdminsRemove, …)`, and on
       success removes the row from the DOM, decrements the admin-count
       badge, and fires `window.SBPP.showToast` for confirmation. The
       fallback href is followed as a navigation when the JSON
       dispatcher is missing entirely (third-party theme that stripped
       api.js); since there's no legacy GET handler for `o=remove`
       (RemoveAdmin always went through the JSON dispatcher), the
       fallback just lands the operator back at the admins list.

       No `// @ts-check` here because the file is rendered by Smarty;
       ts-check only runs against `.js` sources in `web/scripts`. The
       shape mirrors the inline handler in page_bans.tpl
       (`#bans-unban-dialog`, #1301).
       ============================================================ *}
    {literal}
    <script>
    (function () {
        'use strict';

        /** @returns {{call: (a:string,p?:object)=>Promise<any>}|null} */
        function api()     { return (window.sb && window.sb.api) || null; }
        /** @returns {Record<string,string>|null} */
        function actions() { return /** @type {any} */ (window).Actions || null; }
        function toast(kind, title, body) {
            var sbpp = /** @type {any} */ (window).SBPP;
            if (sbpp && typeof sbpp.showToast === 'function') {
                sbpp.showToast({ kind: kind, title: title, body: body || '' });
            }
        }
        /**
         * @param {Element|null} btn
         * @param {boolean} [busy]
         */
        function setBusy(btn, busy) {
            if (!btn) return;
            var S = /** @type {any} */ (window).SBPP;
            if (S && typeof S.setBusy === 'function') S.setBusy(btn, busy);
            else /** @type {HTMLButtonElement} */ (btn).disabled = busy === undefined ? true : !!busy;
        }

        /**
         * @param {string} aid
         * @returns {NodeListOf<Element>}
         */
        function rowsForAid(aid) {
            return document.querySelectorAll(
                '[data-testid="admin-row"][data-id="' + aid + '"],'
                + '[data-testid="admins-list-card"][data-id="' + aid + '"]'
            );
        }

        /** @returns {void} */
        function decrementCount() {
            var el = document.querySelector('[data-testid="admin-count"]');
            if (!el) return;
            var n = Number((el.textContent || '').replace(/[^0-9]/g, ''));
            if (!Number.isFinite(n) || n <= 0) return;
            el.textContent = '(' + (n - 1).toLocaleString() + ')';
        }

        /** @returns {number[]} */
        function selectedAids() {
            var boxes = document.querySelectorAll('[data-action="admins-select-row"]:checked');
            var aids = [];
            for (var i = 0; i < boxes.length; i++) {
                var aid = Number(/** @type {HTMLElement} */ (boxes[i]).getAttribute('data-aid') || 0);
                if (aid > 0 && aids.indexOf(aid) === -1) aids.push(aid);
            }
            return aids;
        }

        /** @returns {number[]} */
        function enabledAids() {
            var boxes = document.querySelectorAll('[data-action="admins-select-row"]:not(:disabled)');
            var aids = [];
            for (var i = 0; i < boxes.length; i++) {
                var aid = Number(/** @type {HTMLElement} */ (boxes[i]).getAttribute('data-aid') || 0);
                if (aid > 0 && aids.indexOf(aid) === -1) aids.push(aid);
            }
            return aids;
        }

        /**
         * Keep desktop-table and mobile-card checkboxes for the same
         * aid in lockstep (both surfaces stay in the DOM; only one is
         * visible per viewport).
         * @param {string} aid
         * @param {boolean} on
         * @returns {void}
         */
        function setRowChecked(aid, on) {
            if (!aid) return;
            var boxes = document.querySelectorAll('[data-action="admins-select-row"][data-aid="' + aid + '"]');
            for (var i = 0; i < boxes.length; i++) {
                var box = /** @type {HTMLInputElement} */ (boxes[i]);
                if (box.disabled) continue;
                box.checked = on;
            }
        }

        /** @returns {void} */
        function syncBulkBar() {
            var bar = document.querySelector('[data-testid="admins-bulk-bar"]');
            var countEl = document.querySelector('[data-testid="admins-bulk-count"]');
            var aids = selectedAids();
            if (countEl) countEl.textContent = aids.length + ' selected';
            if (!bar) return;
            if (aids.length > 0) {
                bar.removeAttribute('hidden');
                /** @type {HTMLElement} */ (bar).style.display = 'flex';
            } else {
                bar.setAttribute('hidden', '');
                /** @type {HTMLElement} */ (bar).style.display = 'none';
            }
            var enabled = enabledAids();
            var allOn = enabled.length > 0 && aids.length === enabled.length;
            var allSome = aids.length > 0 && aids.length < enabled.length;
            var allBoxes = document.querySelectorAll('[data-action="admins-select-all"]');
            for (var ai = 0; ai < allBoxes.length; ai++) {
                /** @type {HTMLInputElement} */ (allBoxes[ai]).checked = allOn;
                /** @type {HTMLInputElement} */ (allBoxes[ai]).indeterminate = allSome;
            }
        }

        /**
         * Chain system.rehash_admins when the handler returned SIDs
         * (config.enableadminrehashing). Same shape as Add Admin /
         * _admin_edit_helpers fireRehash — never block the UI toast on
         * a flaky rehash.
         * @param {any} data
         * @param {() => void} [then]
         * @returns {void}
         */
        function fireRehashIfNeeded(data, then) {
            var done = typeof then === 'function' ? then : function () {};
            var a = api(), A = actions();
            var rehashSids = ((data && data.rehash) || '').toString();
            if (!a || !A || !A.SystemRehashAdmins || !rehashSids) {
                done();
                return;
            }
            a.call(A.SystemRehashAdmins, { servers: rehashSids })
                .then(done)
                .catch(done);
        }

        /** @returns {void} */
        function clearSelection() {
            var boxes = document.querySelectorAll('[data-action="admins-select-row"]');
            for (var i = 0; i < boxes.length; i++) {
                /** @type {HTMLInputElement} */ (boxes[i]).checked = false;
            }
            syncBulkBar();
        }

        /** @type {{aid: string, name: string, fallback: string, mode: string}|null} */
        var pending = null;
        /** @type {string|null} */
        var pendingBulkOp = null;

        /**
         * @param {string} prefix
         * @returns {HTMLDialogElement|null}
         */
        function dialogBy(prefix) {
            return /** @type {HTMLDialogElement|null} */ (document.getElementById(prefix + '-dialog'));
        }
        /**
         * @param {string} prefix
         * @returns {HTMLTextAreaElement|null}
         */
        function reasonBy(prefix) {
            return /** @type {HTMLTextAreaElement|null} */ (document.getElementById(prefix + '-reason'));
        }
        /**
         * @param {string} prefix
         * @returns {HTMLElement|null}
         */
        function errorBy(prefix) {
            var d = dialogBy(prefix);
            return d ? /** @type {HTMLElement|null} */ (d.querySelector('[data-testid="' + prefix + '-error"]')) : null;
        }
        /** @param {string} prefix @param {string} msg */
        function showError(prefix, msg) {
            var e = errorBy(prefix);
            if (!e) return;
            e.textContent = msg;
            e.hidden = false;
        }
        /** @param {string} prefix */
        function clearError(prefix) {
            var e = errorBy(prefix);
            if (!e) return;
            e.textContent = '';
            e.hidden = true;
        }

        /**
         * @param {string} prefix
         * @param {{aid: string, name: string, fallback: string, mode: string}} ctx
         */
        function openDialog(prefix, ctx) {
            pending = ctx;
            var d = dialogBy(prefix);
            if (!d) {
                if (ctx.fallback) window.location.href = ctx.fallback;
                return;
            }
            var target = d.querySelector('[data-testid="' + prefix + '-target"]');
            if (target) target.textContent = ctx.name || ('admin #' + ctx.aid);
            var input = reasonBy(prefix);
            if (input) input.value = '';
            clearError(prefix);
            d.removeAttribute('hidden');
            try { d.showModal(); }
            catch (_e) { d.setAttribute('open', ''); }
            if (input) { try { input.focus(); } catch (_e2) { /* ignore */ } }
        }

        /** @param {string} prefix */
        function closeDialog(prefix) {
            var d = dialogBy(prefix);
            if (!d) return;
            try { d.close(); } catch (_e) { /* ignore */ }
            d.setAttribute('hidden', '');
            pending = null;
        }

        /**
         * @param {string} prefix
         * @param {string} op
         * @param {string} label
         */
        function openBulkDialog(prefix, op, label) {
            var aids = selectedAids();
            if (!aids.length) return;
            pendingBulkOp = op;
            var d = dialogBy(prefix);
            if (!d) return;
            var target = d.querySelector('[data-testid="' + prefix + '-target"]');
            if (target) target.textContent = aids.length + ' ' + label;
            var input = reasonBy(prefix);
            if (input) input.value = '';
            clearError(prefix);
            d.removeAttribute('hidden');
            try { d.showModal(); }
            catch (_e) { d.setAttribute('open', ''); }
        }

        /** @param {string} prefix */
        function closeBulkDialog(prefix) {
            var d = dialogBy(prefix);
            if (!d) return;
            try { d.close(); } catch (_e) { /* ignore */ }
            d.setAttribute('hidden', '');
            pendingBulkOp = null;
        }

        /**
         * @param {string} op
         * @param {Record<string, any>} extra
         * @param {HTMLButtonElement|null} submitBtn
         * @param {string} prefix
         */
        function runBulk(op, extra, submitBtn, prefix) {
            var a = api(), A = actions();
            var aids = selectedAids();
            if (!a || !A || !aids.length) {
                setBusy(submitBtn, false);
                return;
            }
            /** @type {Record<string, any>} */
            var params = { op: op, aids: aids };
            Object.keys(extra || {}).forEach(function (k) { params[k] = extra[k]; });
            setBusy(submitBtn, true);
            a.call(A.AdminsBulk, params).then(function (r) {
                setBusy(submitBtn, false);
                if (!r || r.ok === false) {
                    var msg = (r && r.error && r.error.message) || 'Unknown error';
                    if (prefix) showError(prefix, msg);
                    toast('error', 'Bulk action failed', msg);
                    return;
                }
                var data = r.data || {};
                var applied = data.applied || [];
                for (var i = 0; i < applied.length; i++) {
                    if (op === 'remove' || op === 'deactivate') {
                        var rows = rowsForAid(String(applied[i]));
                        for (var j = 0; j < rows.length; j++) {
                            var row = rows[j];
                            if (row && row.parentNode) row.parentNode.removeChild(row);
                        }
                        decrementCount();
                    }
                }
                if (prefix) closeBulkDialog(prefix);
                clearSelection();
                var title = (data.message && data.message.title) || 'Done';
                var body = (data.message && data.message.body) || '';
                toast(applied.length ? 'success' : 'error', title, body);
                fireRehashIfNeeded(data, function () {
                    if (op === 'set_web_group' || op === 'set_srv_group' || op === 'reactivate') {
                        window.location.reload();
                    }
                });
            });
        }

        document.addEventListener('change', function (e) {
            var t = /** @type {Element|null} */ (e.target);
            if (!t || !t.closest) return;
            if (t.matches('[data-action="admins-select-all"]')) {
                var on = /** @type {HTMLInputElement} */ (t).checked;
                var boxes = document.querySelectorAll('[data-action="admins-select-row"]:not(:disabled)');
                for (var i = 0; i < boxes.length; i++) {
                    /** @type {HTMLInputElement} */ (boxes[i]).checked = on;
                }
                syncBulkBar();
                return;
            }
            if (t.matches('[data-action="admins-select-row"]')) {
                var row = /** @type {HTMLInputElement} */ (t);
                setRowChecked(row.getAttribute('data-aid') || '', row.checked);
                syncBulkBar();
            }
        });

        document.addEventListener('click', function (e) {
            var t = /** @type {Element|null} */ (e.target);
            if (!t || !t.closest) return;

            if (t.closest('[data-testid="admins-delete-cancel"]')) {
                e.preventDefault();
                closeDialog('admins-delete');
                return;
            }
            if (t.closest('[data-testid="admins-deactivate-cancel"]')) {
                e.preventDefault();
                closeDialog('admins-deactivate');
                return;
            }
            if (t.closest('[data-testid="admins-bulk-deactivate-cancel"]')) {
                e.preventDefault();
                closeBulkDialog('admins-bulk-deactivate');
                return;
            }
            if (t.closest('[data-testid="admins-bulk-delete-cancel"]')) {
                e.preventDefault();
                closeBulkDialog('admins-bulk-delete');
                return;
            }
            if (t.closest('[data-testid="admins-bulk-web-group-cancel"]')) {
                e.preventDefault();
                closeBulkDialog('admins-bulk-web-group');
                return;
            }
            if (t.closest('[data-testid="admins-bulk-srv-group-cancel"]')) {
                e.preventDefault();
                closeBulkDialog('admins-bulk-srv-group');
                return;
            }
            if (t.closest('[data-action="admins-bulk-clear"]')) {
                e.preventDefault();
                clearSelection();
                return;
            }
            if (t.closest('[data-action="admins-bulk-deactivate"]')) {
                e.preventDefault();
                openBulkDialog('admins-bulk-deactivate', 'deactivate', 'admins');
                return;
            }
            if (t.closest('[data-action="admins-bulk-reactivate"]')) {
                e.preventDefault();
                var aR = api(), AR = actions();
                if (!aR || !AR) return;
                runBulk('reactivate', {}, /** @type {HTMLButtonElement|null} */ (t.closest('button')), '');
                return;
            }
            if (t.closest('[data-action="admins-bulk-delete"]')) {
                e.preventDefault();
                openBulkDialog('admins-bulk-delete', 'remove', 'admins');
                return;
            }
            if (t.closest('[data-action="admins-bulk-web-group"]')) {
                e.preventDefault();
                openBulkDialog('admins-bulk-web-group', 'set_web_group', 'admins');
                return;
            }
            if (t.closest('[data-action="admins-bulk-srv-group"]')) {
                e.preventDefault();
                openBulkDialog('admins-bulk-srv-group', 'set_srv_group', 'admins');
                return;
            }

            var reactivateBtn = /** @type {HTMLElement|null} */ (t.closest('[data-action="admins-reactivate"]'));
            if (reactivateBtn) {
                e.preventDefault();
                var rAid = reactivateBtn.getAttribute('data-aid') || '';
                var rName = reactivateBtn.getAttribute('data-name') || ('admin #' + rAid);
                var a = api(), A = actions();
                if (!a || !A || !rAid) return;
                setBusy(reactivateBtn, true);
                a.call(A.AdminsReactivate, { aid: Number(rAid) }).then(function (r) {
                    setBusy(reactivateBtn, false);
                    if (!r || r.ok === false) {
                        var msg = (r && r.error && r.error.message) || 'Unknown error';
                        toast('error', 'Reactivate failed', msg);
                        return;
                    }
                    var rows = rowsForAid(rAid);
                    for (var i = 0; i < rows.length; i++) {
                        var row = rows[i];
                        if (row && row.parentNode) row.parentNode.removeChild(row);
                    }
                    decrementCount();
                    toast('success', 'Admin reactivated', rName + ' can log in again.');
                    fireRehashIfNeeded(r.data || {});
                });
                return;
            }

            var deactivateBtn = /** @type {HTMLElement|null} */ (t.closest('[data-action="admins-deactivate"]'));
            if (deactivateBtn) {
                e.preventDefault();
                var dAid = deactivateBtn.getAttribute('data-aid') || '';
                var dName = deactivateBtn.getAttribute('data-name') || ('admin #' + dAid);
                var a2 = api(), A2 = actions();
                if (!a2 || !A2 || !dAid) return;
                openDialog('admins-deactivate', { aid: dAid, name: dName, fallback: '', mode: 'deactivate' });
                return;
            }

            var btn = /** @type {HTMLElement|null} */ (t.closest('[data-action="admins-delete"]'));
            if (!btn) return;
            e.preventDefault();

            var aid = btn.getAttribute('data-aid') || '';
            var name = btn.getAttribute('data-name') || ('admin #' + aid);
            var fallback = btn.getAttribute('data-fallback-href') || '';
            var a3 = api(), A3 = actions();
            if (!a3 || !A3 || !aid) {
                if (fallback) window.location.href = fallback;
                return;
            }
            openDialog('admins-delete', { aid: aid, name: name, fallback: fallback, mode: 'delete' });
        });

        document.addEventListener('submit', function (e) {
            var form = /** @type {Element|null} */ (e.target);
            if (!form || !(/** @type {Element} */ (form)).closest) return;

            if (form.matches('[data-testid="admins-bulk-deactivate-form"]')) {
                e.preventDefault();
                var reasonB = reasonBy('admins-bulk-deactivate');
                var submitB = /** @type {HTMLButtonElement|null} */ (form.querySelector('[data-testid="admins-bulk-deactivate-submit"]'));
                /** @type {Record<string, any>} */
                var extraB = {};
                if (reasonB && reasonB.value.trim() !== '') extraB.ureason = reasonB.value.trim();
                runBulk('deactivate', extraB, submitB, 'admins-bulk-deactivate');
                return;
            }
            if (form.matches('[data-testid="admins-bulk-delete-form"]')) {
                e.preventDefault();
                var reasonD = reasonBy('admins-bulk-delete');
                var submitD = /** @type {HTMLButtonElement|null} */ (form.querySelector('[data-testid="admins-bulk-delete-submit"]'));
                /** @type {Record<string, any>} */
                var extraD = {};
                if (reasonD && reasonD.value.trim() !== '') extraD.ureason = reasonD.value.trim();
                runBulk('remove', extraD, submitD, 'admins-bulk-delete');
                return;
            }
            if (form.matches('[data-testid="admins-bulk-web-group-form"]')) {
                e.preventDefault();
                var selW = /** @type {HTMLSelectElement|null} */ (document.getElementById('admins-bulk-web-group-select'));
                var submitW = /** @type {HTMLButtonElement|null} */ (form.querySelector('[data-testid="admins-bulk-web-group-submit"]'));
                runBulk('set_web_group', { gid: Number(selW ? selW.value : 0) }, submitW, 'admins-bulk-web-group');
                return;
            }
            if (form.matches('[data-testid="admins-bulk-srv-group-form"]')) {
                e.preventDefault();
                var selS = /** @type {HTMLSelectElement|null} */ (document.getElementById('admins-bulk-srv-group-select'));
                var submitS = /** @type {HTMLButtonElement|null} */ (form.querySelector('[data-testid="admins-bulk-srv-group-submit"]'));
                runBulk('set_srv_group', { srv_group_id: Number(selS ? selS.value : 0) }, submitS, 'admins-bulk-srv-group');
                return;
            }

            var isDelete = form.matches('[data-testid="admins-delete-form"]');
            var isDeactivate = form.matches('[data-testid="admins-deactivate-form"]');
            if (!isDelete && !isDeactivate) return;
            e.preventDefault();
            if (!pending) return;

            var prefix = isDelete ? 'admins-delete' : 'admins-deactivate';
            var input = reasonBy(prefix);
            var reason = input ? input.value.trim() : '';
            clearError(prefix);

            var ctx = pending;
            var submitBtn = /** @type {HTMLButtonElement|null} */ (form.querySelector('[data-testid="' + prefix + '-submit"]'));
            setBusy(submitBtn, true);

            var a = api(), A = actions();
            if (!a || !A) {
                setBusy(submitBtn, false);
                if (ctx.fallback) window.location.href = ctx.fallback;
                return;
            }

            /** @type {{aid: number, ureason?: string}} */
            var params = { aid: Number(ctx.aid) };
            if (reason !== '') params.ureason = reason;

            var action = isDelete ? A.AdminsRemove : A.AdminsDeactivate;
            a.call(action, params).then(function (r) {
                setBusy(submitBtn, false);
                if (!r || r.ok === false) {
                    var msg = (r && r.error && r.error.message) || 'Unknown error';
                    showError(prefix, msg);
                    toast('error', isDelete ? 'Delete failed' : 'Deactivate failed', msg);
                    return;
                }
                var rows = rowsForAid(ctx.aid);
                for (var i = 0; i < rows.length; i++) {
                    var row = rows[i];
                    if (row && row.parentNode) row.parentNode.removeChild(row);
                }
                decrementCount();
                closeDialog(prefix);
                if (isDelete) {
                    toast('success', 'Admin deleted', ctx.name + ' has been removed.');
                } else {
                    toast('success', 'Admin deactivated', ctx.name + ' can no longer log in.');
                }
                fireRehashIfNeeded(r.data || {});
            });
        });

        document.addEventListener('cancel', function (e) {
            var t = /** @type {Element|null} */ (e.target);
            if (!t) return;
            if (t.id === 'admins-delete-dialog') {
                pending = null;
                clearError('admins-delete');
            } else if (t.id === 'admins-deactivate-dialog') {
                pending = null;
                clearError('admins-deactivate');
            } else if (t.id === 'admins-bulk-deactivate-dialog') {
                pendingBulkOp = null;
                clearError('admins-bulk-deactivate');
            } else if (t.id === 'admins-bulk-delete-dialog') {
                pendingBulkOp = null;
                clearError('admins-bulk-delete');
            } else if (t.id === 'admins-bulk-web-group-dialog') {
                pendingBulkOp = null;
                clearError('admins-bulk-web-group');
            } else if (t.id === 'admins-bulk-srv-group-dialog') {
                pendingBulkOp = null;
                clearError('admins-bulk-srv-group');
            }
        });

        syncBulkBar();
    })();
    </script>
    {/literal}
</div>
{/if}
