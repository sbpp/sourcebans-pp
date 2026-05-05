{*
    SourceBans++ 2026 — chrome / navbar.tpl

    Sidebar nav. Pair: web/pages/core/navbar.php (assigns $navbar,
    $adminbar, $isAdmin, $login, $username — same contract as
    web/themes/default/core/navbar.tpl). The $navbar / $adminbar
    arrays come from navbar.php unchanged; this template just reskins
    the legacy <div id="tabs"> markup as the new collapsible sidebar.

    Endpoint→icon mapping is inline because the chrome PHP files
    intentionally don't grow new fields in this PR (variable contract
    is locked per A2; B/C tickets touch their own templates). The
    {if/elseif} chain keeps the data flow PHP→template untouched.

    Interactive surfaces carry data-testid + ARIA per the issue's
    "Testability hooks" rule. The <nav> wrapper carries
    role="navigation" + aria-label; each link gets data-testid
    "nav-<endpoint>" and aria-current="page" when active.
*}
<aside class="sidebar" id="sidebar" data-mobile-open="false">
    <div class="sidebar__brand">
        <div class="sidebar__brand-mark">S</div>
        <div>
            <div class="font-semibold text-sm">SourceBans++</div>
        </div>
    </div>
    <nav class="sidebar__nav" role="navigation" aria-label="Primary">
        <div class="sidebar__section">
            <div class="sidebar__section-label">Public</div>
            {foreach from=$navbar item=nav}
                {if $nav.endpoint != 'admin'}
                    <a class="sidebar__link"
                       href="index.php?p={$nav.endpoint}"
                       data-testid="nav-{$nav.endpoint}"
                       title="{$nav.description}"
                       {if $nav.state == 'active'}aria-current="page"{/if}>
                        <i data-lucide="{if $nav.endpoint == 'home'}layout-dashboard{elseif $nav.endpoint == 'banlist'}ban{elseif $nav.endpoint == 'commslist'}mic-off{elseif $nav.endpoint == 'submit'}flag{elseif $nav.endpoint == 'protest'}megaphone{elseif $nav.endpoint == 'servers'}server{else}circle{/if}"></i>
                        {$nav.title}
                    </a>
                {/if}
            {/foreach}
        </div>

        {if $isAdmin}
            <div class="sidebar__section">
                <div class="sidebar__section-label">Admin</div>
                {foreach from=$navbar item=nav}
                    {if $nav.endpoint == 'admin'}
                        <a class="sidebar__link"
                           href="index.php?p={$nav.endpoint}"
                           data-testid="nav-{$nav.endpoint}"
                           title="{$nav.description}"
                           {if $nav.state == 'active'}aria-current="page"{/if}>
                            <i data-lucide="shield"></i>
                            {$nav.title}
                        </a>
                    {/if}
                {/foreach}
                {foreach from=$adminbar item=admin}
                    <a class="sidebar__link"
                       href="index.php?p=admin&c={$admin.endpoint}"
                       data-testid="nav-admin-{$admin.endpoint}"
                       {if $admin.state == 'active'}aria-current="page"{/if}>
                        <i data-lucide="{if $admin.endpoint == 'admins'}users{elseif $admin.endpoint == 'servers'}server{elseif $admin.endpoint == 'bans'}ban{elseif $admin.endpoint == 'comms'}mic-off{elseif $admin.endpoint == 'groups'}shield-check{elseif $admin.endpoint == 'settings'}settings{elseif $admin.endpoint == 'mods'}puzzle{else}circle{/if}"></i>
                        {$admin.title}
                    </a>
                {/foreach}
            </div>
        {/if}
    </nav>

    <div style="border-top: 1px solid var(--border); padding: 0.5rem;">
        {if $login}
            <a class="sidebar__link"
               href="index.php?p=account"
               data-testid="nav-account">
                <i data-lucide="user"></i>
                <div style="flex:1;min-width:0">
                    <div class="font-semibold text-xs truncate">{$username}</div>
                </div>
            </a>
            <a class="sidebar__link"
               href="index.php?p=logout"
               data-testid="nav-logout">
                <i data-lucide="log-out"></i> Logout
            </a>
        {else}
            <a class="sidebar__link"
               href="index.php?p=login"
               data-testid="nav-login">
                <i data-lucide="log-in"></i> Login
            </a>
        {/if}
    </div>
</aside>
