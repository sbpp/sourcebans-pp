{*
    SourceBans++ 2026 — chrome / admin_tabs.tpl

    Trailing "Back" anchor strip for admin edit-* pages. Pair:
    includes/View/AdminTabs.php (assigns $tabs + $active_tab; only routes
    to this partial when $tabs === [], i.e. the edit-* shape that
    only needs the Back affordance).

    #1259 — sidebar split
    --------------------
    Pre-#1259 this partial drew BOTH the section-nav strip (when $tabs
    was non-empty) AND the trailing Back link. Pattern A admin routes
    (servers / mods / groups / settings) have since been moved onto
    the parameterized `core/admin_sidebar.tpl` partial — see #1259's
    notes in includes/View/AdminTabs.php — so this template's only job
    today is the Back link emitted by edit-* pages that pass `$tabs
    === []`.

    The `{foreach}` branch below survives for defence in depth: any
    third-party theme that calls `$theme->display('core/admin_tabs.tpl')`
    with a populated `$tabs` array still gets a working horizontal
    strip. AdminTabs.php's #1259 router never sends a populated array
    here.

    #1239 — anchor links, no JS toggle
    ----------------------------------
    Pre-#1239 the partial emitted `<button onclick="openTab(this, …)">`
    elements that called a JS function from the v1.x sourcebans.js
    bulk file (removed at #1123 D1, AGENTS.md "Anti-patterns"). The
    handler was silently dead, so every pane stacked together below
    the strip with no way to switch. Since #1259 the section-nav
    surface lives in `core/admin_sidebar.tpl`; the foreach below is
    the legacy fallback shape only.

    "Back" is right-aligned via the `.admin-tabs__back` rule
    (#1186) so it visibly separates from the (now legacy-fallback)
    tab cluster.

    A11y note (#1124 Slice 2): the wrapper used to declare
    role="tablist" with role="tab" children. The full ARIA tabs
    pattern is NOT implemented (the surface is now a plain anchor
    list — semantically a navigation toolbar, not a JS tab control),
    so the roles described an interaction model the DOM didn't
    honour. Both reasons argue for the same fix: drop the partial
    ARIA roles, label the wrapper as a plain "Admin sections"
    toolbar so SR users hear the links as links.
*}
<div class="admin-tabs flex gap-2 mb-4 items-center" data-testid="admin-tabs" aria-label="Admin sections">
    {foreach from=$tabs item=tab}
        <a class="btn btn--ghost btn--sm"
           href="{$tab.url}"
           data-testid="admin-tab-{$tab.slug}"
           {if $tab.slug == $active_tab}aria-current="page"{/if}>{$tab.name}</a>
    {/foreach}
    <a class="btn btn--ghost btn--sm admin-tabs__back"
       href="javascript:history.go(-1);"
       data-testid="admin-tab-back">
        <i data-lucide="arrow-left"></i> Back
    </a>
</div>
