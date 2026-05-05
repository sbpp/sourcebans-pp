{*
    SourceBans++ 2026 — chrome / admin_tabs.tpl

    Admin sub-tab bar. Pair: includes/AdminTabs.php (assigns $tabs —
    same contract as web/themes/default/core/admin_tabs.tpl). Pure
    visual reskin: same {foreach} over the AdminTabs data the PHP
    layer already builds, just rendered with the new theme's button
    primitives.

    The "openTab(this, '<name>')" handler is defined inline by each
    admin page that uses tabs (legacy convention preserved by AdminTabs.php).

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
<div class="admin-tabs flex gap-2 mb-4" aria-label="Admin sections">
    {foreach from=$tabs item=tab}
        <button type="button"
                class="btn btn--secondary btn--sm"
                data-testid="admin-tab-{$tab.name}"
                onclick="openTab(this, '{$tab.name}');">{$tab.name}</button>
    {/foreach}
    <a class="btn btn--ghost btn--sm"
       href="javascript:history.go(-1);"
       data-testid="admin-tab-back">Back</a>
</div>
