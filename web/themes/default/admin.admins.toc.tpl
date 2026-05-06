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

    Permissions: every entry mirrors the gate the dispatcher uses to
    decide whether the corresponding `<section id="…">` lands in the
    DOM, so a ToC click never targets a non-existent anchor.
      - `Search` / `Admins` are gated on `$can_list_admins`
        (`ADMIN_OWNER | ADMIN_LIST_ADMINS`) — `page_admin_admins_list.tpl`
        only renders those two sections inside the `{if !$can_list_admins}`
        else-arm.
      - `Add admin` / `Overrides` / `Add override` are gated on
        `$can_add_admins` (`ADMIN_OWNER | ADMIN_ADD_ADMINS`) — the
        AdminTabs entries for "Add new admin" + "Overrides" key off the
        same mask, and `AdminOverridesView.permission_addadmin` matches
        too. Until the override surface grows its own permission, one
        flag covers all three.
    A user with `ADMIN_ADD_ADMINS` but not `ADMIN_LIST_ADMINS` (the
    perms are independent) lands on this page through the "Add new
    admin" tab; the ToC rendering must still elide Search/Admins for
    that user. The regression case is locked in
    `web/tests/integration/AdminAdminsSearchTest.php` —
    `testTocElidesSearchAndAdminsForAddOnlyAdmin` (and the broader
    "every link has a section" invariant in
    `testTocLinksMatchRenderedSectionsForOwner`); the AGENTS.md
    "Page-level table of contents" convention captures the rule for
    future dense pages.
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
                {if $can_list_admins}
                    <li><a href="#search" class="admin-admins-toc__link" data-testid="admin-admins-toc-link-search">Search</a></li>
                    <li><a href="#admins" class="admin-admins-toc__link" data-testid="admin-admins-toc-link-admins">Admins</a></li>
                {/if}
                {if $can_add_admins}
                    <li><a href="#add-admin" class="admin-admins-toc__link" data-testid="admin-admins-toc-link-add-admin">Add admin</a></li>
                    <li><a href="#overrides" class="admin-admins-toc__link" data-testid="admin-admins-toc-link-overrides">Overrides</a></li>
                    <li><a href="#add-override" class="admin-admins-toc__link" data-testid="admin-admins-toc-link-add-override">Add override</a></li>
                {/if}
            </ul>
        </nav>
    </details>
</aside>
