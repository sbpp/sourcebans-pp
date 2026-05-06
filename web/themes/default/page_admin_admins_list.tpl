{*
    SourceBans++ 2026 — admin/admins list

    Pair: web/pages/admin.admins.php (renders this + the add tab) and
    web/includes/View/AdminAdminsListView.php (typed DTO that
    SmartyTemplateRule keeps in lockstep with this file).

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

    #1207 ADM-3 — Page-level ToC + cross-template shell
    ---------------------------------------------------
    The audit (#1207 ADM-3) called out admin-admins as ~7 stacked
    surfaces (search + admins list + add admin + overrides + add
    override) on one long scroll with no internal navigation. We open
    a `.admin-admins-shell` wrapper here that spans all three templates
    (this one, page_admin_admins_add.tpl, page_admin_overrides.tpl) and
    paint a sticky anchor sidebar at >=1024px / accordion at <1024px
    inside it. The closing `</div>` lives at the bottom of
    page_admin_overrides.tpl — keep these tags paired across edits.

    Each section below is wrapped in a `<section id="…">` with
    `scroll-margin-top` so the sticky topbar (3.5rem) clears the
    heading after an anchor jump. The anchor IDs are referenced by the
    ToC (rendered via {include file="admin.admins.toc.tpl"}) and by
    other templates that link back into this page (e.g. the admin home
    Overrides card already uses `#overrides`).
*}
<div class="admin-admins-shell" data-testid="admin-admins-shell">

{include file="admin.admins.toc.tpl"}

<div class="admin-admins-content">
<div class="card-tab" id="List admins">
    {if !$can_list_admins}
        <div class="card">
            <div class="card__body">
                <p class="text-sm text-muted m-0">Access denied.</p>
            </div>
        </div>
    {else}
        <div class="flex items-end justify-between gap-3 mb-4" style="flex-wrap:wrap">
            <div>
                <h1 style="font-size:var(--fs-xl);font-weight:600;margin:0">Admins
                    <span class="text-faint" style="font-weight:400;margin-left:0.375rem" data-testid="admin-count">({$admin_count})</span>
                </h1>
                <p class="text-sm text-muted m-0 mt-2">Click an admin row's actions to edit details, permissions, or server access.</p>
            </div>
            {if $can_add_admins}
                <a class="btn btn--primary btn--sm"
                   href="#add-admin"
                   data-testid="admin-add-cta"><i data-lucide="user-plus"></i> Add admin</a>
            {/if}
        </div>

        <section id="search" class="admin-admins-section" data-testid="admin-admins-section-search" aria-labelledby="search-heading">
            <h2 id="search-heading" class="admin-admins-section__heading">Search admins</h2>
            {load_template file="admin.admins.search"}
        </section>

        <section id="admins" class="admin-admins-section" data-testid="admin-admins-section-admins" aria-labelledby="admins-heading">
            <h2 id="admins-heading" class="admin-admins-section__heading">Admins list</h2>

            <div class="text-xs text-muted mb-2" data-testid="admin-nav">
                {* nofilter: server-built pagination HTML — `<displaying N - M of K results>` (integers), prev/next `<a>` from `CreateLinkR(…)`, and a page-jump `<select onchange>`. After #1207 ADM-4 every populated filter flows through `http_build_query($activeFilters)`, which percent-encodes filter values (so single quotes / angle brackets can't break out of the single-quoted `href='…'` or `onchange="… '…'…"` attributes). The page-jump `<select>` additionally `htmlspecialchars()`-escapes the base URL with `ENT_QUOTES` before interpolation. Loop counters and pre-computed page numbers are integers. No raw user input reaches the rendered string. *}
                {$admin_nav nofilter}
            </div>

        <div class="card" style="overflow:hidden">
            <table class="table" role="table" aria-label="Admins">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Bans</th>
                        <th scope="col">Server group</th>
                        <th scope="col">Web group</th>
                        <th scope="col">Immunity</th>
                        <th scope="col">Last visit</th>
                        <th scope="col" style="width:1%"></th>
                    </tr>
                </thead>
                <tbody>
                {foreach $admins as $admin}
                    <tr data-testid="admin-row" data-id="{$admin.aid}">
                        <td>
                            <div class="flex items-center gap-3">
                                <div class="avatar" style="width:1.75rem;height:1.75rem;background:var(--brand-600);font-size:var(--fs-xs)">
                                    {$admin.user|truncate:1:'':true|upper|escape}
                                </div>
                                <div>
                                    <div class="font-medium">{$admin.user|escape}</div>
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
                        <td class="text-muted">{$admin.server_group|escape}</td>
                        <td class="text-muted">{$admin.web_group|escape}</td>
                        <td class="tabular-nums text-muted">{$admin.immunity}</td>
                        <td class="text-xs text-muted">{$admin.lastvisit|escape}</td>
                        <td>
                            <div class="row-actions" style="white-space:nowrap">
                                {if $can_edit_admins}
                                    <a class="btn btn--ghost btn--icon btn--sm"
                                       href="index.php?p=admin&c=admins&o=editdetails&id={$admin.aid|escape:'url'}"
                                       title="Edit details"
                                       aria-label="Edit details for {$admin.user|escape}"
                                       data-testid="admin-action-edit-details">
                                        <i data-lucide="clipboard-list" style="width:14px;height:14px"></i>
                                    </a>
                                    <a class="btn btn--ghost btn--icon btn--sm"
                                       href="index.php?p=admin&c=admins&o=editpermissions&id={$admin.aid|escape:'url'}"
                                       title="Edit permissions"
                                       aria-label="Edit permissions for {$admin.user|escape}"
                                       data-testid="admin-action-edit-perms">
                                        <i data-lucide="shield" style="width:14px;height:14px"></i>
                                    </a>
                                    <a class="btn btn--ghost btn--icon btn--sm"
                                       href="index.php?p=admin&c=admins&o=editservers&id={$admin.aid|escape:'url'}"
                                       title="Edit server access"
                                       aria-label="Edit server access for {$admin.user|escape}"
                                       data-testid="admin-action-edit-servers">
                                        <i data-lucide="server" style="width:14px;height:14px"></i>
                                    </a>
                                    <a class="btn btn--ghost btn--icon btn--sm"
                                       href="index.php?p=admin&c=admins&o=editgroup&id={$admin.aid|escape:'url'}"
                                       title="Edit groups"
                                       aria-label="Edit groups for {$admin.user|escape}"
                                       data-testid="admin-action-edit-group">
                                        <i data-lucide="users" style="width:14px;height:14px"></i>
                                    </a>
                                {/if}
                                {if $can_delete_admins}
                                    <button type="button" class="btn btn--ghost btn--icon btn--sm"
                                            onclick="if (typeof RemoveAdmin === 'function') RemoveAdmin({$admin.aid}, '{$admin.user|escape:'javascript'}');"
                                            title="Delete admin"
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
        </section>
    {/if}
</div>
{* admin-admins-content + admin-admins-shell remain open; closed in page_admin_overrides.tpl *}
