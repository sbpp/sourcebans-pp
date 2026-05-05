{*
    SourceBans++ 2026 — chrome / admin_tabs.tpl

    Admin sub-tab bar. Pair: includes/AdminTabs.php (assigns $tabs +
    $active_tab). Rendered with the theme's button primitives —
    inactive tabs use `btn--ghost`; the tab whose `name` matches
    `$active_tab` carries `aria-current="page"` and gets the active
    treatment via `.admin-tabs > [aria-current="page"]` in theme.css
    (#1186).

    The "openTab(this, '<name>')" handler is defined inline by each
    admin page that uses tabs (legacy convention preserved by AdminTabs.php).

    "Back" is rendered outside the tab cluster (right-aligned via the
    `.admin-tabs__back` rule) so it no longer reads as a peer tab
    (#1186). Edit-* pages that pass an empty `$tabs` array still
    render the Back link standalone — the right-aligned positioning
    is harmless when no siblings precede it.

    A11y note (#1124 Slice 2): the wrapper used to declare
    role="tablist" with role="tab" children. The full ARIA tabs
    pattern is NOT implemented (no aria-controls, no aria-selected on
    the buttons; the .tabcontent panes have no role="tabpanel" /
    aria-labelledby), so the roles described an interaction model the
    DOM didn't honour. axe-core's `aria-required-children` rule
    additionally flags the trailing "Back" anchor as an unallowed
    [tabindex] child of role="tablist". Both reasons argue for the
    same fix: drop the partial ARIA roles, label the wrapper as a
    plain "Admin sections" toolbar so SR users hear the buttons as
    buttons. If a follow-up wires the full tab pattern (controls,
    selected, panel labelling) the roles can come back in lockstep.
*}
<div class="admin-tabs flex gap-2 mb-4 items-center" aria-label="Admin sections">
    {foreach from=$tabs item=tab}
        <button type="button"
                class="btn btn--ghost btn--sm"
                data-testid="admin-tab-{$tab.name}"
                {if $tab.name == $active_tab}aria-current="page"{/if}
                onclick="openTab(this, '{$tab.name}');">{$tab.name}</button>
    {/foreach}
    <a class="btn btn--ghost btn--sm admin-tabs__back"
       href="javascript:history.go(-1);"
       data-testid="admin-tab-back">
        <i data-lucide="arrow-left"></i> Back
    </a>
</div>
