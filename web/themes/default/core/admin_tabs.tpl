{*
    SourceBans++ 2026 — chrome / admin_tabs.tpl

    Admin sub-section nav. Pair: includes/AdminTabs.php (assigns $tabs +
    $active_tab). Rendered with the theme's button primitives —
    inactive sections use `btn--ghost`; the section whose `slug`
    matches `$active_tab` carries `aria-current="page"` and gets the
    active treatment via `.admin-tabs > [aria-current="page"]` in
    theme.css (#1186).

    #1239 — anchor links, no JS toggle
    ----------------------------------
    Pre-#1239 the partial emitted `<button onclick="openTab(this, …)">`
    elements that called a JS function from the v1.x sourcebans.js
    bulk file (removed at #1123 D1, AGENTS.md "Anti-patterns"). The
    handler was silently dead, so every pane stacked together below
    the strip with no way to switch. The chrome now emits anchor
    `<a href="{$tab.url}">` links — each section is its own URL
    (`?p=admin&c=<page>&section=<slug>`), linkable, back-button-
    friendly, and works with JS off. The page handler is responsible
    for building each tab's `url` + `slug` and for routing the
    request to render exactly the matching section. See
    `web/pages/admin.servers.php` / `admin.mods.php` /
    `admin.groups.php` / `admin.comms.php` for the canonical Pattern A
    shape, and `admin.settings.php` for the long-standing reference
    that #1239 is aligning the rest of the admin family with.

    "Back" is rendered outside the tab cluster (right-aligned via the
    `.admin-tabs__back` rule) so it no longer reads as a peer tab
    (#1186). Edit-* pages that pass an empty `$tabs` array still
    render the Back link standalone — the right-aligned positioning
    is harmless when no siblings precede it.

    A11y note (#1124 Slice 2): the wrapper used to declare
    role="tablist" with role="tab" children. The full ARIA tabs
    pattern is NOT implemented (the surface is now a plain anchor
    list — semantically a navigation toolbar, not a JS tab control),
    so the roles described an interaction model the DOM didn't
    honour. axe-core's `aria-required-children` rule additionally
    flags the trailing "Back" anchor as an unallowed [tabindex]
    child of role="tablist". Both reasons argue for the same fix:
    drop the partial ARIA roles, label the wrapper as a plain
    "Admin sections" toolbar so SR users hear the links as links.
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
