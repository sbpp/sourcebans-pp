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
    <header class="topbar" data-testid="topbar">
        <button type="button"
                class="btn--ghost btn--icon"
                data-mobile-menu
                data-testid="mobile-menu-toggle"
                aria-label="Open navigation menu"
                aria-controls="sidebar">
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

        {*
            #1207 CC-1: at <=768px the search button collapses to icon-only
            — the .topbar__search-label / .topbar__search-kbd hooks below
            are the CSS handles theme.css uses to hide the visible label
            and the keyboard hint at mobile widths. Keep them BOTH in the
            DOM unconditionally so:
              - SR users hear "Open command palette …" via the existing
                aria-label regardless of viewport,
              - theme.js's applyPlatformHints() can still rewrite the kbd
                text to ⌘K on Mac after first paint without re-rendering,
              - the icon stays the visible affordance on every viewport so
                the testability hook (`data-palette-open` /
                `data-testid="palette-trigger"`) works the same way for
                desktop click + mobile tap.
        *}
        <button type="button"
                class="topbar__search"
                data-palette-open
                data-testid="palette-trigger"
                aria-label="Open command palette (search players, SteamIDs, pages)">
            <i data-lucide="search" style="width:14px;height:14px"></i>
            <span class="topbar__search-label">Search players, SteamIDs…</span>
            {* The U+2318 ⌘ glyph is missing from the vendored JetBrains Mono
               and the generic CSS mono fallback on every non-Mac browser, so
               a server-rendered '⌘K' renders as tofu for the majority of users
               (#1184). Render the Ctrl form here and let theme.js upgrade
               Mac/iOS clients to '⌘K' at boot. *}
            <kbd class="topbar__search-kbd">Ctrl K</kbd>
        </button>

        <button type="button"
                class="btn btn--ghost btn--icon"
                data-theme-toggle
                data-testid="theme-toggle"
                aria-label="Toggle color theme">
            <i data-lucide="sun"  class="theme-toggle__sun"></i>
            <i data-lucide="moon" class="theme-toggle__moon"></i>
        </button>
    </header>

    <main class="page" id="page">
