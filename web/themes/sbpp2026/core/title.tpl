{*
    SourceBans++ 2026 — chrome / title.tpl

    Topbar (breadcrumbs + ⌘K palette trigger + theme toggle), then opens
    <main class="page"> for the page handler's content. Pair:
    web/pages/core/title.php (assigns $title, $breadcrumb, $board_name —
    same contract as web/themes/default/core/title.tpl).

    Interactive surfaces carry data-testid + ARIA per the issue's
    "Testability hooks" rule:
      - palette trigger: data-testid="palette-trigger" + aria-label
      - theme toggle:    data-testid="theme-toggle"    + aria-label
      - mobile menu:     data-testid="mobile-menu-toggle" + aria-label
      - active breadcrumb: aria-current="page"

    The palette / drawer JS that consumes data-palette-open and
    data-theme-toggle ships in C1/C2 — the buttons render now so the
    static markup contract is locked from A2 onward.
*}
<div class="main">
    <header class="topbar">
        <button type="button"
                class="btn--ghost btn--icon"
                data-mobile-menu
                data-testid="mobile-menu-toggle"
                aria-label="Open navigation menu"
                aria-controls="sidebar"
                style="display:none">
            <i data-lucide="menu"></i>
        </button>

        <nav class="topbar__breadcrumbs" aria-label="Breadcrumb">
            {foreach from=$breadcrumb item=crumb name=bc}
                {if !$smarty.foreach.bc.first}
                    <i data-lucide="chevron-right" style="width:12px;height:12px;color:var(--text-faint)"></i>
                {/if}
                <a href="{$crumb.url}"
                   {if $smarty.foreach.bc.last}aria-current="page"{/if}>{$crumb.title}</a>
            {/foreach}
        </nav>

        <div style="flex:1"></div>

        <button type="button"
                class="topbar__search"
                data-palette-open
                data-testid="palette-trigger"
                aria-label="Open command palette (search players, SteamIDs, pages)">
            <i data-lucide="search" style="width:14px;height:14px"></i>
            <span>Search players, SteamIDs…</span>
            <kbd>&#8984;K</kbd>
        </button>

        <button type="button"
                class="btn--ghost btn--icon"
                data-theme-toggle
                data-testid="theme-toggle"
                aria-label="Toggle color theme">
            <i data-lucide="sun"></i>
        </button>
    </header>

    <main class="page" id="page">
