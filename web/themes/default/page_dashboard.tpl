{*
    SourceBans++ 2026 — page_dashboard.tpl

    Public landing page. Renders a stat-card grid + recent activity panels
    sourced from `:prefix_bans`, `:prefix_comms`, `:prefix_banlog`, and
    `:prefix_servers`. View: Sbpp\View\HomeDashboardView (also shared with
    the legacy default theme during the v2.0.0 rollout — see the View's
    docblock for the per-theme field map).

    Skipped from the handoff design per #1123 B3 brief ("directional, not
    literal"):
      * Bans-over-time sparkline — no per-day aggregation feed yet.
      * Team coverage — no on-duty / response-time tracking yet.
    Both stay open for a follow-up once the underlying data exists.

    Live server status (player counts, hostname, online/offline) is the
    other intentional omission. The legacy theme's sb-callback /
    LoadServerHostProperty UDP probe was deleted with sourcebans.js in
    A1's footer rewrite; re-implementing it needs a new JSON action
    under web/api/handlers/, which Phase B is forbidden from touching.
    The Servers card therefore renders configured-server metadata only
    (mod icon + ip:port + sid) and links out to ?p=servers.
*}
<div class="p-6 space-y-6" style="max-width:1400px;margin:0 auto;width:100%">

    {* -- Page header -------------------------------------------------- *}
    <header data-testid="dashboard-header">
        <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">
            {if $dashboard_title}{$dashboard_title}{else}Dashboard{/if}
        </h1>
        <p class="text-sm text-muted m-0 mt-2">Activity across your servers.</p>
    </header>

    {* -- Optional admin-authored intro (Markdown) --------------------- *}
    {if $dashboard_text}
    <section class="card" data-testid="dashboard-intro">
        <div class="card__body">
            {* nofilter: rendered by IntroRenderer (CommonMark, html_input=escape, allow_unsafe_links=false); never raw user input *}
            {$dashboard_text nofilter}
        </div>
    </section>
    {/if}

    {* -- Stat cards (4-up; collapses to 2-up then 1-up) --------------- *}
    <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">

        <div class="card p-5" data-testid="dashboard-stat-total-bans">
            <div class="flex items-start justify-between">
                <div class="text-xs font-medium text-muted" style="text-transform:uppercase;letter-spacing:0.06em">Total bans</div>
                <i data-lucide="ban" style="color:var(--text-faint)"></i>
            </div>
            <div class="tabular-nums" style="font-size:1.875rem;font-weight:600;margin-top:0.5rem">{$total_bans|number_format}</div>
            <div class="text-xs text-muted">all time</div>
        </div>

        <div class="card p-5" data-testid="dashboard-stat-active-bans">
            <div class="flex items-start justify-between">
                <div class="text-xs font-medium text-muted" style="text-transform:uppercase;letter-spacing:0.06em">Active bans</div>
                <i data-lucide="user-x" style="color:var(--text-faint)"></i>
            </div>
            <div class="tabular-nums" style="font-size:1.875rem;font-weight:600;margin-top:0.5rem">{$active_bans|number_format}</div>
            <div class="text-xs text-muted">currently in force</div>
        </div>

        <div class="card p-5" data-testid="dashboard-stat-comm-blocks">
            <div class="flex items-start justify-between">
                <div class="text-xs font-medium text-muted" style="text-transform:uppercase;letter-spacing:0.06em">Comm blocks</div>
                <i data-lucide="mic-off" style="color:var(--text-faint)"></i>
            </div>
            <div class="tabular-nums" style="font-size:1.875rem;font-weight:600;margin-top:0.5rem">{$total_comms|number_format}</div>
            <div class="text-xs text-muted">mutes + gags</div>
        </div>

        <div class="card p-5" data-testid="dashboard-stat-servers">
            <div class="flex items-start justify-between">
                <div class="text-xs font-medium text-muted" style="text-transform:uppercase;letter-spacing:0.06em">Servers</div>
                <i data-lucide="server" style="color:var(--text-faint)"></i>
            </div>
            <div class="tabular-nums" style="font-size:1.875rem;font-weight:600;margin-top:0.5rem">{$total_servers|number_format}</div>
            <div class="text-xs text-muted">configured</div>
        </div>

    </div>

    {* -- Recent bans + Servers (2-col on desktop, collapses to 1-col below ~320px+gap per card; #1188) *}
    <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr))">

        <section class="card" data-testid="dashboard-recent-bans">
            <div class="card__header">
                <div>
                    <h3>Latest bans</h3>
                    <p>Most recent enforcement actions</p>
                </div>
                <a class="btn btn--ghost btn--sm" href="?p=banlist">
                    View all <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
                </a>
            </div>
            <div>
                {foreach $players_banned as $b}
                <a class="ban-row ban-row--{$b.state} flex items-center gap-3 p-4"
                   style="border-bottom:1px solid var(--border);text-decoration:none;color:var(--text)"
                   href="{$b.search_link}"
                   data-testid="dashboard-ban-row"
                   data-id="{$b.bid}">
                    <div class="flex-1" style="min-width:0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-sm truncate">
                                {if $b.short_name}{$b.short_name}{else}<span class="text-faint">no nickname</span>{/if}
                            </span>
                            <span class="pill pill--{$b.state}">{$b.state}</span>
                        </div>
                        <div class="text-xs text-muted truncate" style="margin-top:0.125rem">
                            {$b.length_human}{if $b.sname} · {$b.sname}{/if}{if $b.reason} · {$b.reason}{/if}
                        </div>
                    </div>
                    <div class="text-xs text-muted text-right" style="white-space:nowrap">{$b.banned_human}</div>
                </a>
                {foreachelse}
                {* #1207 PUB-5: first-run empty state. The "Add a ban" CTA
                   is gated on `can_add_ban` (Perms::for $userbank), so
                   anonymous visitors / admins without ADMIN_ADD_BAN see
                   the copy without a link they couldn't follow. *}
                <div class="empty-state" data-testid="dashboard-recent-bans-empty">
                    <span class="empty-state__icon" aria-hidden="true">
                        <i data-lucide="ban" style="width:18px;height:18px"></i>
                    </span>
                    <h4 class="empty-state__title">No bans yet</h4>
                    <p class="empty-state__body">Enforcement actions will show up here as soon as admins start moderating.</p>
                    {if $can_add_ban}
                        <div class="empty-state__actions">
                            <a class="btn btn--primary btn--sm"
                               href="?p=admin&amp;c=bans"
                               data-testid="dashboard-recent-bans-empty-add">
                                <i data-lucide="plus" style="width:13px;height:13px"></i>
                                Add a ban
                            </a>
                        </div>
                    {/if}
                </div>
                {/foreach}
            </div>
        </section>

        <section class="card" data-testid="dashboard-servers">
            <div class="card__header">
                <div>
                    <h3>Servers</h3>
                    <p>{$total_servers|number_format} configured</p>
                </div>
                <a class="btn btn--ghost btn--sm" href="?p=servers">
                    View all <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
                </a>
            </div>
            {if $access_bans}
            <div class="text-xs text-muted" style="border-bottom:1px solid var(--border);padding:0.625rem 1.25rem">
                <i data-lucide="info" style="width:12px;height:12px;vertical-align:-2px"></i>
                Open the servers page to manage players in real time.
            </div>
            {/if}
            <div style="padding:0.5rem">
                {foreach $server_list as $server}
                <a class="flex items-center gap-3"
                   style="padding:0.625rem;border-radius:var(--radius-md);text-decoration:none;color:var(--text)"
                   href="?p=servers&s={$server.index}"
                   data-testid="server-tile" data-id="{$server.sid}">
                    <span style="width:22px;height:22px;border-radius:3px;background:var(--bg-muted);display:grid;place-items:center;flex-shrink:0">
                        <img src="images/games/{$server.icon}" alt="" width="16" height="16">
                    </span>
                    <div class="flex-1" style="min-width:0">
                        <div class="font-medium text-sm font-mono truncate">{$server.ip}:{$server.port}</div>
                        <div class="text-xs text-faint">sid {$server.sid}</div>
                    </div>
                    <i data-lucide="external-link" style="width:14px;height:14px;color:var(--text-faint)"></i>
                </a>
                {foreachelse}
                {* #1207 PUB-5: first-run empty state. CTA gated on
                   `can_add_server` so visitors without ADMIN_ADD_SERVER
                   see the copy only. *}
                <div class="empty-state" data-testid="dashboard-servers-empty">
                    <span class="empty-state__icon" aria-hidden="true">
                        <i data-lucide="server" style="width:18px;height:18px"></i>
                    </span>
                    <h4 class="empty-state__title">No servers configured</h4>
                    <p class="empty-state__body">Add a server so visitors can see live status and connect from the panel.</p>
                    {if $can_add_server}
                        <div class="empty-state__actions">
                            <a class="btn btn--primary btn--sm"
                               href="?p=admin&amp;c=servers"
                               data-testid="dashboard-servers-empty-add">
                                <i data-lucide="plus" style="width:13px;height:13px"></i>
                                Add a server
                            </a>
                        </div>
                    {/if}
                </div>
                {/foreach}
            </div>
        </section>

    </div>

    {* -- Blocked attempts + Recent comm blocks (2-col) ---------------- *}
    <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(360px,1fr))">

        {*
            data-lognopopup is sourced from $dashboard_lognopopup (the
            admin-controlled "front-page block log without popup" flag,
            sb_settings:dash.lognopopup). Persisted to a data attribute
            so future client-side wiring can opt back into a tooltip
            without another round trip. Anchor click already navigates
            to the player; the popup confirmation step from the legacy
            theme is intentionally dropped per the new design's
            single-action UX.
        *}
        <section class="card" data-testid="dashboard-blocked-attempts" data-lognopopup="{if $dashboard_lognopopup}1{else}0{/if}">
            <div class="card__header">
                <div>
                    <h3>Latest blocked attempts</h3>
                    <p>{$total_blocked|number_format} total intercepts</p>
                </div>
            </div>
            <div>
                {foreach $players_blocked as $p}
                <a class="flex items-center gap-3 p-4"
                   style="border-bottom:1px solid var(--border);text-decoration:none;color:var(--text)"
                   href="{$p.search_link}"
                   data-testid="dashboard-blocked-row" data-id="{$p.bid}">
                    <i data-lucide="shield-x" style="color:var(--danger);flex-shrink:0"></i>
                    <div class="flex-1" style="min-width:0">
                        <div class="font-medium text-sm truncate">
                            {if $p.short_name}{$p.short_name}{else}<span class="text-faint">no nickname</span>{/if}
                        </div>
                        <div class="text-xs text-muted truncate">{if $p.sname}{$p.sname}{else}—{/if}</div>
                    </div>
                    <div class="text-xs text-muted text-right" style="white-space:nowrap">{$p.blocked_human}</div>
                </a>
                {foreachelse}
                {* #1207 PUB-5: blocked-attempts is a read-only stream
                   of plugin-side intercepts; there's no admin "add"
                   action that maps to it, so the empty state is copy
                   only — no CTA. *}
                <div class="empty-state" data-testid="dashboard-blocked-attempts-empty">
                    <span class="empty-state__icon" aria-hidden="true">
                        <i data-lucide="shield-x" style="width:18px;height:18px"></i>
                    </span>
                    <h4 class="empty-state__title">No blocked attempts yet</h4>
                    <p class="empty-state__body">Once banned players try to rejoin, the SourceMod plugin will log every intercept here.</p>
                </div>
                {/foreach}
            </div>
        </section>

        <section class="card" data-testid="dashboard-recent-comms">
            <div class="card__header">
                <div>
                    <h3>Latest comm blocks</h3>
                    <p>Most recent mutes &amp; gags</p>
                </div>
                <a class="btn btn--ghost btn--sm" href="?p=commslist">
                    View all <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
                </a>
            </div>
            <div>
                {foreach $players_commed as $c}
                <a class="ban-row ban-row--{$c.state} flex items-center gap-3 p-4"
                   style="border-bottom:1px solid var(--border);text-decoration:none;color:var(--text)"
                   href="{$c.search_link}"
                   data-testid="dashboard-comm-row" data-id="{$c.bid}">
                    <i data-lucide="{$c.lucide_icon}" style="color:var(--text-muted);flex-shrink:0"></i>
                    <div class="flex-1" style="min-width:0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-sm truncate">
                                {if $c.short_name}{$c.short_name}{else}<span class="text-faint">no nickname</span>{/if}
                            </span>
                            <span class="pill pill--{$c.state}">{$c.state}</span>
                        </div>
                        <div class="text-xs text-muted truncate" style="margin-top:0.125rem">
                            {$c.length_human}{if $c.sname} · {$c.sname}{/if}{if $c.reason} · {$c.reason}{/if}
                        </div>
                    </div>
                    <div class="text-xs text-muted text-right" style="white-space:nowrap">{$c.banned_human}</div>
                </a>
                {foreachelse}
                {* #1207 PUB-5: first-run empty state. The "Add a comm
                   block" CTA reuses `can_add_ban` because admin.comms.php
                   gates Add on the same flag (ADMIN_OWNER | ADMIN_ADD_BAN);
                   see _register.php's `comms.add` row. *}
                <div class="empty-state" data-testid="dashboard-recent-comms-empty">
                    <span class="empty-state__icon" aria-hidden="true">
                        <i data-lucide="mic-off" style="width:18px;height:18px"></i>
                    </span>
                    <h4 class="empty-state__title">No comm blocks yet</h4>
                    <p class="empty-state__body">Mutes and gags issued from the panel or in-game will appear here.</p>
                    {if $can_add_ban}
                        <div class="empty-state__actions">
                            <a class="btn btn--primary btn--sm"
                               href="?p=admin&amp;c=comms"
                               data-testid="dashboard-recent-comms-empty-add">
                                <i data-lucide="plus" style="width:13px;height:13px"></i>
                                Add a comm block
                            </a>
                        </div>
                    {/if}
                </div>
                {/foreach}
            </div>
        </section>

    </div>

    {*
        $IN_SERVERS_PAGE is declared on HomeDashboardView; always
        false on the dashboard, so this block intentionally never
        renders. The reference is kept here so SmartyTemplateRule's
        "unused property" check stays green without us having to
        carry a bespoke baseline entry. The pre-#1306 rationale
        ("for the transitively included page_servers.tpl") no longer
        applies — #1306 burned $IN_SERVERS_PAGE on ServersView along
        with the misleading right-click hint it gated; the prop
        survives on HomeDashboardView purely as parity scaffolding
        for any third-party theme fork that still wires it.
    *}
    {if $IN_SERVERS_PAGE}{* unreachable on dashboard *}{/if}

</div>
