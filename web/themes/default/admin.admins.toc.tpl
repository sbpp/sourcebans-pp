{*
    SourceBans++ 2026 — admin/admins page-level table of contents

    #1207 ADM-3 — admin-admins is ~7 stacked surfaces (search + admins
    list + add admin + overrides + add override) on one long scroll. We
    paint a sticky anchor sidebar at >=1024px and an accordion-style
    collapsible nav at <1024px so the user can jump to "Add admin"
    without paging past the search and listing.

    Loaded by page_admin_admins_list.tpl (the first template in the
    page) inside the cross-template `.admin-admins-shell` wrapper. The
    sidebar uses CSS `position: sticky` against that wrapper; the
    sticky topbar height (3.5rem) is accounted for via top + section
    `scroll-margin-top` in admins-toc.css.

    The link list is hardcoded — these are the canonical sections of
    admin-admins, gated only by what the dispatcher would render
    anyway. We don't drive this from the View DTO because nothing else
    needs the data; if more density-rework pages adopt the same
    pattern (admin-bans, myaccount), promote the markup into a shared
    partial then.

    Permissions: AdminTabs in admin.admins.php gates both the "Add new
    admin" tab and the "Overrides" tab on the same flag mask
    (ADMIN_OWNER | ADMIN_ADD_ADMINS), and AdminOverridesView's
    `permission_addadmin` keys off the same. Until the override surface
    grows its own permission, the ToC reuses `$can_add_admins` for all
    three gated entries — entries for sections the dispatcher would
    elide become dead links otherwise.
*}
<aside class="admin-admins-toc"
       data-testid="admin-admins-toc"
       aria-label="Admins page sections">
    <details class="admin-admins-toc__details" open>
        <summary class="admin-admins-toc__summary">
            <span class="admin-admins-toc__summary-label">
                <i data-lucide="list" style="width:14px;height:14px"></i>
                On this page
            </span>
            <i data-lucide="chevron-down" class="admin-admins-toc__chevron" style="width:14px;height:14px"></i>
        </summary>
        <nav class="admin-admins-toc__nav">
            <ul class="admin-admins-toc__list">
                <li><a href="#search" class="admin-admins-toc__link" data-testid="admin-admins-toc-link-search">Search</a></li>
                <li><a href="#admins" class="admin-admins-toc__link" data-testid="admin-admins-toc-link-admins">Admins</a></li>
                {if $can_add_admins}
                    <li><a href="#add-admin" class="admin-admins-toc__link" data-testid="admin-admins-toc-link-add-admin">Add admin</a></li>
                    <li><a href="#overrides" class="admin-admins-toc__link" data-testid="admin-admins-toc-link-overrides">Overrides</a></li>
                    <li><a href="#add-override" class="admin-admins-toc__link" data-testid="admin-admins-toc-link-add-override">Add override</a></li>
                {/if}
            </ul>
        </nav>
    </details>
</aside>
