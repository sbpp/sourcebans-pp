{*
    SourceBans++ 2026 — page_admin.tpl

    Admin landing page (?p=admin with no c=…). Replaces the A1 stub
    with the canonical 8-card grid from the AdminPanelView mockup
    (handoff/ui_kits/webpanel-2026/views.jsx). Pair:
    web/pages/page.admin.php → web/includes/View/AdminHomeView.php.

    The page-builder route (`case 'admin':` default in
    web/includes/page-builder.php) gates the page itself on
    CheckAdminAccess(ALL_WEB), so anyone landing here already holds
    *some* web flag. This template's job is to gate the per-area
    cards so an admin only sees the cards that match the sub-routes
    they can actually open.

    Cards are gated by composite $can_<area> booleans precomputed by
    the page handler from Sbpp\View\Perms::for($userbank). Each gate
    OR's together the underlying ADMIN_* flags the legacy router
    requires for that sub-route (see page-builder.php), so a card
    visible here implies the router will let the user through —
    "card visible but route 403's" can't drift between the two.

    Comms folds into the sidebar nav (admin/comms) per the mockup,
    not a card here. Audit is owner-gated and points at the live
    `c=audit` route (admin.audit.php), introduced ahead of this
    landing's redesign. The legacy router's `default:` case now
    returns a 404 for any unrecognised c=… (#1207 ADM-1), so a
    typo'd href would surface visibly rather than silently
    rendering this same landing.

    Testability hooks per the issue's "Testability hooks" rule:
      - card grid carries role="list" + aria-label
      - each card carries data-testid="admin-card-<area>"
      - active sidebar item is set by core/navbar.tpl, not here

    Card styles live in a literal-wrapped <style> block at the bottom
    so the Phase B "Don't touch ../css/*.css" rule is honoured (the
    shell tokens — --bg-surface, --border, --accent, etc. — come from
    web/themes/sbpp2026/css/theme.css unchanged). Paired
    {literal}…{/literal} prevents Smarty from interpreting CSS braces
    as tags, matching the convention used by page_lostpassword.tpl.
*}
<section class="admin-home">
    <header class="admin-home__header mb-6">
        <h1 class="admin-home__title">Admin panel</h1>
        <p class="admin-home__subtitle text-sm text-muted mt-2">Manage admins, groups, servers, and panel settings.</p>
    </header>

    <ul class="admin-cards" role="list" aria-label="Admin areas">
        {if $can_admins}
            <li>
                <a class="admin-card"
                   href="index.php?p=admin&amp;c=admins"
                   data-testid="admin-card-admins">
                    <div class="admin-card__icon" aria-hidden="true"><i data-lucide="users"></i></div>
                    <div class="admin-card__title">Admins</div>
                    <div class="admin-card__desc">View, add, edit, and remove panel admins.</div>
                </a>
            </li>
        {/if}

        {if $can_groups}
            <li>
                <a class="admin-card"
                   href="index.php?p=admin&amp;c=groups"
                   data-testid="admin-card-groups">
                    <div class="admin-card__icon" aria-hidden="true"><i data-lucide="shield-check"></i></div>
                    <div class="admin-card__title">Groups</div>
                    <div class="admin-card__desc">Permission and immunity groupings for admins.</div>
                </a>
            </li>
        {/if}

        {if $can_servers}
            <li>
                <a class="admin-card"
                   href="index.php?p=admin&amp;c=servers"
                   data-testid="admin-card-servers">
                    <div class="admin-card__icon" aria-hidden="true"><i data-lucide="server"></i></div>
                    <div class="admin-card__title">Servers</div>
                    <div class="admin-card__desc">Game servers SourceBans++ talks to.</div>
                </a>
            </li>
        {/if}

        {if $can_bans}
            <li>
                <a class="admin-card"
                   href="index.php?p=admin&amp;c=bans"
                   data-testid="admin-card-bans">
                    <div class="admin-card__icon" aria-hidden="true"><i data-lucide="ban"></i></div>
                    <div class="admin-card__title">Bans</div>
                    <div class="admin-card__desc">Add bans, review submissions and appeals.</div>
                </a>
            </li>
        {/if}

        {if $can_mods}
            <li>
                <a class="admin-card"
                   href="index.php?p=admin&amp;c=mods"
                   data-testid="admin-card-mods">
                    <div class="admin-card__icon" aria-hidden="true"><i data-lucide="puzzle"></i></div>
                    <div class="admin-card__title">Mods</div>
                    <div class="admin-card__desc">Game/mod entries shown across the panel.</div>
                </a>
            </li>
        {/if}

        {if $can_overrides}
            <li>
                {* The overrides editor lives at the bottom of admin.admins.php's
                   c=admins route (admin.overrides.php is `require`d there).
                   The hash anchors to the `#overrides` block in
                   page_admin_overrides.tpl so clicking the card lands on the
                   intended editor instead of the admins list (#1207 ADM-1). *}
                <a class="admin-card"
                   href="index.php?p=admin&amp;c=admins#overrides"
                   data-testid="admin-card-overrides">
                    <div class="admin-card__icon" aria-hidden="true"><i data-lucide="key-round"></i></div>
                    <div class="admin-card__title">Overrides</div>
                    <div class="admin-card__desc">Override SourceMod command flags per server or group.</div>
                </a>
            </li>
        {/if}

        {if $can_settings}
            <li>
                <a class="admin-card"
                   href="index.php?p=admin&amp;c=settings"
                   data-testid="admin-card-settings">
                    <div class="admin-card__icon" aria-hidden="true"><i data-lucide="settings"></i></div>
                    <div class="admin-card__title">Settings</div>
                    <div class="admin-card__desc">SMTP, theme, and integration configuration.</div>
                </a>
            </li>
        {/if}

        {if $can_audit}
            <li>
                <a class="admin-card"
                   href="index.php?p=admin&amp;c=audit"
                   data-testid="admin-card-audit">
                    <div class="admin-card__icon" aria-hidden="true"><i data-lucide="scroll-text"></i></div>
                    <div class="admin-card__title">Audit log</div>
                    <div class="admin-card__desc">Admin actions across the panel.</div>
                </a>
            </li>
        {/if}
    </ul>
</section>

{*
    Parity block - references the legacy default-theme variables that
    AdminHomeView still declares so SmartyTemplateRule's "unused
    property" check stays green for the sbpp2026 PHPStan leg without a
    bespoke baseline entry. The if-false branch is unreachable at
    render time, so the new theme never visibly renders the legacy
    counts. Mirrors the unreachable parity reference HomeDashboardView
    established (#1123 B3) using IN_SERVERS_PAGE. D1 deletes the legacy
    theme + the matching props on AdminHomeView; this block leaves
    with them.
*}
{if false}
    {$access_admins}{$access_bans}{$access_groups}{$access_mods}
    {$access_servers}{$access_settings}{$archived_protests}
    {$archived_submissions}{$demosize}{$dev}{$total_admins}
    {$total_bans}{$total_blocks}{$total_comms}{$total_protests}
    {$total_servers}{$total_submissions}
{/if}

{literal}
<style>
    .admin-home { max-width: 1400px; padding: 1rem; }
    @media (min-width: 640px) { .admin-home { padding: 1.5rem; } }
    .admin-home__title {
        font-size: var(--fs-2xl);
        font-weight: 600;
        letter-spacing: -0.02em;
        color: var(--text);
        margin: 0;
    }
    .admin-home__subtitle { margin-bottom: 0; }

    .admin-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
        gap: 1rem;
        list-style: none;
        margin: 0;
        padding: 0;
    }
    /* #1207 ADM-10: at <768px the auto-fill grid above only fits a
       single 15rem column so the 8 admin cards stack vertically and
       produce a long scroll. Force 2 columns at mobile instead — at
       the iPhone-13's 375px viewport this lands ~165px-wide cards
       (full-width minus 1rem body padding minus 0.75rem gap, halved)
       with ~120px+ height each (2.25rem icon + 1.25rem padding +
       title + description), well above the 44x44 tap-target floor.
       Tablet (768–1023px) and desktop (>=1024px) keep the auto-fill
       behaviour above so wider viewports still get 2-3-4 columns
       depending on how much sidebar-less width is available. */
    @media (max-width: 767.98px) {
        .admin-cards {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
        }
    }

    .admin-card {
        display: block;
        background: var(--bg-surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-xl);
        padding: 1.25rem;
        color: var(--text);
        text-decoration: none;
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }
    .admin-card:hover {
        border-color: var(--zinc-300);
        box-shadow: var(--shadow);
    }
    html.dark .admin-card:hover { border-color: var(--zinc-700); }
    .admin-card:focus-visible {
        outline: 2px solid var(--accent);
        outline-offset: 2px;
    }

    .admin-card__icon {
        width: 2.25rem;
        height: 2.25rem;
        border-radius: var(--radius-lg);
        background: var(--bg-muted);
        color: var(--text);
        display: grid;
        place-items: center;
        margin-bottom: 0.75rem;
    }
    .admin-card__icon i { width: 1rem; height: 1rem; }

    .admin-card__title {
        font-size: var(--fs-base);
        font-weight: 600;
        color: var(--text);
    }
    .admin-card__desc {
        font-size: var(--fs-xs);
        color: var(--text-muted);
        margin-top: 0.25rem;
        line-height: 1.5;
    }
</style>
{/literal}
