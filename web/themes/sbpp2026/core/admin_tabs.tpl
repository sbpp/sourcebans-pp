{*
    SourceBans++ 2026 — chrome / admin_tabs.tpl

    Admin sub-tab bar. Pair: includes/AdminTabs.php (assigns $tabs —
    same contract as web/themes/default/core/admin_tabs.tpl). Pure
    visual reskin: same {foreach} over the AdminTabs data the PHP
    layer already builds, just rendered with the new theme's button
    primitives.

    The "openTab(this, '<name>')" handler is defined inline by each
    admin page that uses tabs (legacy convention preserved by AdminTabs.php).
*}
<div class="admin-tabs flex gap-2 mb-4" role="tablist" aria-label="Admin tabs">
    {foreach from=$tabs item=tab}
        <button type="button"
                class="btn btn--secondary btn--sm"
                role="tab"
                data-testid="admin-tab-{$tab.name}"
                onclick="openTab(this, '{$tab.name}');">{$tab.name}</button>
    {/foreach}
    <a class="btn btn--ghost btn--sm"
       href="javascript:history.go(-1);"
       data-testid="admin-tab-back">Back</a>
</div>
