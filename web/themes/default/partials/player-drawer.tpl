{*
    SourceBans++ 2026 — partials / player-drawer.tpl

    Reference structure for the right-side ban-detail drawer rendered by
    web/themes/sbpp2026/js/theme.js after a `bans.detail` call (#1123 C1).

    The handler returns structured JSON (see api_bans_detail() in
    web/api/handlers/bans.php); theme.js renders the markup client-side
    via renderDrawerBody(), funnelling every dynamic value through
    escapeHtml(). This file is the canonical *shape* the JS mirrors so a
    designer can iterate on the visual without booting the JSON path,
    and so a future B-phase ticket that needs a server-rendered initial
    state for a deep-link (e.g. `?p=banlist&id=N` opening the drawer
    on first paint) can {include file="partials/player-drawer.tpl"}
    here without forking the markup.

    Variable contract (only required when assigned by a page handler):
      - $detail.bid                          int
      - $detail.player.name                  string
      - $detail.player.steam_id              string
      - $detail.player.steam_id_3            string
      - $detail.player.community_id          string
      - $detail.player.ip                    string|null
      - $detail.player.country               string|null
      - $detail.ban.state                    'permanent'|'active'|'expired'|'unbanned'
      - $detail.ban.reason                   string
      - $detail.ban.length_human             string
      - $detail.ban.banned_at_human          string
      - $detail.ban.expires_at_human         string|null
      - $detail.ban.removed_at_human         string|null
      - $detail.ban.removed_by               string|null
      - $detail.ban.unban_reason             string
      - $detail.admin.name                   string|null
      - $detail.server.name                  string|null
      - $detail.comments_visible             bool
      - $detail.comments[].author            string|null
      - $detail.comments[].added_human       string
      - $detail.comments[].text              string

    Smarty's auto-escape is on globally (init.php), so {$value} renders
    safely without per-line nofilter.
*}
<header class="drawer__header" style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;border-bottom:1px solid var(--border)">
    <div>
        <div class="text-xs text-faint" style="text-transform:uppercase;letter-spacing:0.06em">Ban #{$detail.bid}</div>
        <h2 class="font-semibold" style="margin:0.125rem 0 0;font-size:1.125rem">{$detail.player.name}</h2>
    </div>
    <button class="btn--ghost btn--icon" type="button" data-drawer-close aria-label="Close">
        <i data-lucide="x"></i>
    </button>
</header>

<div class="drawer__body" style="padding:1rem 1.25rem;display:flex;flex-direction:column;gap:1rem;overflow-y:auto;flex:1">
    <dl class="drawer__ids" style="display:grid;grid-template-columns:6rem 1fr;gap:0.25rem 0.75rem;margin:0;font-size:0.8125rem">
        {if $detail.player.steam_id}
            <dt class="text-faint">SteamID</dt>
            <dd class="font-mono" style="margin:0">{$detail.player.steam_id}</dd>
        {/if}
        {if $detail.player.steam_id_3}
            <dt class="text-faint">Steam3</dt>
            <dd class="font-mono" style="margin:0">{$detail.player.steam_id_3}</dd>
        {/if}
        {if $detail.player.community_id}
            <dt class="text-faint">Community</dt>
            <dd class="font-mono" style="margin:0">{$detail.player.community_id}</dd>
        {/if}
        {if $detail.player.ip}
            <dt class="text-faint">IP</dt>
            <dd class="font-mono" style="margin:0">{$detail.player.ip}</dd>
        {/if}
        {if $detail.player.country}
            <dt class="text-faint">Country</dt>
            <dd class="font-mono" style="margin:0">{$detail.player.country}</dd>
        {/if}
    </dl>

    <dl class="drawer__ban" style="display:grid;grid-template-columns:6rem 1fr;gap:0.25rem 0.75rem;margin:0;font-size:0.8125rem">
        <dt class="text-faint">State</dt>
        <dd style="margin:0">{$detail.ban.state|capitalize}</dd>

        <dt class="text-faint">Reason</dt>
        <dd style="margin:0">{$detail.ban.reason}</dd>

        <dt class="text-faint">Length</dt>
        <dd style="margin:0">{$detail.ban.length_human}</dd>

        <dt class="text-faint">Banned</dt>
        <dd style="margin:0">{$detail.ban.banned_at_human}</dd>

        {if $detail.ban.expires_at_human}
            <dt class="text-faint">Expires</dt>
            <dd style="margin:0">{$detail.ban.expires_at_human}</dd>
        {/if}
        {if $detail.ban.removed_at_human}
            <dt class="text-faint">Removed</dt>
            <dd style="margin:0">{$detail.ban.removed_at_human}</dd>
        {/if}
        {if $detail.ban.removed_by}
            <dt class="text-faint">Removed by</dt>
            <dd style="margin:0">{$detail.ban.removed_by}</dd>
        {/if}
        {if $detail.ban.unban_reason}
            <dt class="text-faint">Unban reason</dt>
            <dd style="margin:0">{$detail.ban.unban_reason}</dd>
        {/if}
        {if $detail.admin.name}
            <dt class="text-faint">Admin</dt>
            <dd style="margin:0">{$detail.admin.name}</dd>
        {/if}
        {if $detail.server.name}
            <dt class="text-faint">Server</dt>
            <dd style="margin:0">{$detail.server.name}</dd>
        {/if}
    </dl>
</div>

{if $detail.comments_visible}
    <section style="padding:0 1.25rem 1.25rem">
        <h3 class="text-xs text-faint" style="text-transform:uppercase;letter-spacing:0.06em;margin:0 0 0.5rem">Comments</h3>
        {if $detail.comments}
            <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0.625rem">
                {foreach $detail.comments as $c}
                    <li style="border:1px solid var(--border);border-radius:var(--radius-md);padding:0.625rem 0.75rem;background:var(--bg-surface)">
                        <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:var(--text-muted);margin-bottom:0.25rem">
                            <span class="font-medium">{$c.author|default:'unknown'}</span>
                            <span>{$c.added_human}</span>
                        </div>
                        <div class="text-sm" style="white-space:pre-wrap">{$c.text}</div>
                    </li>
                {/foreach}
            </ul>
        {else}
            <p class="text-sm text-muted" style="margin:0">No comments.</p>
        {/if}
    </section>
{/if}
