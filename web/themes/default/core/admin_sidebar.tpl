{*
    SourceBans++ 2026 — chrome / admin_sidebar.tpl

    Sub-paged admin sidebar nav (#1259). Renders the vertical-sidebar
    chrome used by Pattern A admin routes — settings / servers / mods /
    groups — that subdivide via `?section=<slug>` URLs. Pre-#1259 each
    of these routes drew its own chrome:

      - Settings inlined a full `<nav><a class="sidebar__link">…</a></nav>`
        block inside every settings template (page_admin_settings_*.tpl),
        each declaring its own `grid-template-columns:14rem 1fr`.
      - Servers / Mods / Groups rode the horizontal `core/admin_tabs.tpl`
        pill strip (the Back-link partial in disguise — same wrapper,
        different visual).

    Two patterns, same routing shape, completely different chrome. #1259
    unifies them on the Settings-style vertical sidebar so dense routes
    with 3+ sections (mods/groups/admins) don't read as a chip cluster.

    Caller contract:

      - Open `<div class="admin-sidebar-shell">` BEFORE this partial. The
        shell is the grid host at >=1024px (14rem + 1fr) and a single
        column at <1024px.
      - {include this partial} — emits the <aside> + the link list.
      - Open `<div class="admin-sidebar-content">` AFTER this partial.
        That's the content column the sticky sidebar pairs with at
        desktop.
      - Close both wrappers AFTER the View(s) render. Document the
        open/close pairing with a comment at each end so edits don't
        silently break the layout.

    AdminTabs.php drives all three open/close emissions when $tabs is
    non-empty so individual page handlers stay terse — see the docblock
    there for the contract + the close-tag echo at the bottom of each
    Pattern A page handler (admin.servers.php, admin.mods.php,
    admin.groups.php, admin.settings.php).

    Parameters (assigned by AdminTabs.php):

        $tabs           list<TabSpec>. Already filtered by AdminTabs to
                        the entries the current user can reach +
                        feature-toggle gates (config:false drops the
                        entry). Each tab carries:
                          - name  (string)         link label
                          - slug  (string)         URL slug + testid
                          - url   (string)         href target
                          - icon  (string|null)    optional Lucide name
                                                   (e.g. "server",
                                                   "puzzle"). Falls
                                                   back to a generic
                                                   "circle-dot" if
                                                   omitted so every
                                                   row has matching
                                                   visual weight.

        $active_tab     string. Slug of the section currently rendered;
                        the matching <a> carries `aria-current="page"`.
                        Empty string = no link is marked active (the
                        sidebar still renders).

        $sidebar_id     string. Testid prefix for the surface (e.g.
                        "admin-servers-sidebar"). Drives
                        `data-testid="{$sidebar_id}"` on the <aside>
                        and `data-testid="admin-tab-<slug>"` on each
                        link (the per-link hook is shared with the
                        legacy admin_tabs.tpl strip — E2E specs that
                        anchor on `admin-tab-<slug>` keep working).

        $sidebar_label  string. aria-label on the <aside>. Screen
                        readers announce the navigation by this label
                        ("Settings sections" / "Server sections" / …).

    Mobile (<1024px) shape:
        The aside collapses to a `<details open>` accordion — the
        section name above the link list, chevron rotates 180° on
        toggle. The link list paints inline at desktop; the summary
        chrome is hidden via `.admin-sidebar__summary { display: none }`
        in the desktop media query.
*}
<aside class="admin-sidebar"
       data-testid="{$sidebar_id}"
       aria-label="{$sidebar_label}">
    <details class="admin-sidebar__details" open>
        <summary class="admin-sidebar__summary">
            <span class="admin-sidebar__summary-label">
                <i data-lucide="menu" style="width:14px;height:14px"></i>
                {$sidebar_label}
            </span>
            <i data-lucide="chevron-down" class="admin-sidebar__chevron" style="width:14px;height:14px"></i>
        </summary>
        <nav class="admin-sidebar__nav" aria-label="{$sidebar_label}">
            {foreach from=$tabs item=tab}
                <a class="sidebar__link admin-sidebar__link"
                   href="{$tab.url}"
                   data-testid="admin-tab-{$tab.slug}"
                   {if $tab.slug == $active_tab}aria-current="page"{/if}>
                    <i data-lucide="{if $tab.icon}{$tab.icon}{else}circle-dot{/if}"></i>
                    <span>{$tab.name}</span>
                </a>
            {/foreach}
        </nav>
    </details>
</aside>
