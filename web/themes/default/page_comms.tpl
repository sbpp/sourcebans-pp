{*
    SourceBans++ 2026 — page_comms.tpl

    Public communications-blocklist page. Mirrors the new banlist shape
    from B2 (handoff/pages/banlist.tpl) with comms-specific columns:
    type (mute/gag/silence) replaces the IP column, length + started
    replace the long ban-reason banner, and the row state vocabulary is
    `unmuted` instead of `unbanned`. View: Sbpp\View\CommsListView.

    Skipped from the design / handoff for B4 (each open for follow-up):
      * Comment edit drawer — the `?comment=` flow stays on the legacy
        theme; the new theme list-only page just exposes the row's edit
        URL. Wiring the new comment editor needs its own template.
      * Live-server hostname resolution — the schema has no hostname
        column; sname renders ip:port (or "Web Block" for sid=0) until
        a future ticket re-implements the LoadServerHost equivalent
        via sb.api.call.
      * Fully-wired filter chips — `?type=` and `?state=` URL params
        round-trip into the filter bar so the chips highlight, but the
        SQL backend isn't filtered on them yet (legacy `advSearch`
        still works). Active is derived from the existing
        hideinactive session toggle.

    Testability hooks (`data-testid`) match the B4 spec: comm-row /
    filter-chip-* / row-action-* / page-prev / page-next / comms-search.
*}
<div class="p-6 space-y-4" style="max-width:1400px;margin:0 auto;width:100%">

    {* -- Page header --------------------------------------------------- *}
    <header class="flex items-center justify-between gap-4" style="flex-wrap:wrap" data-testid="comms-header">
        <div>
            <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">Comm blocks</h1>
            <p class="text-sm text-muted m-0 mt-2">
                <span class="tabular-nums" data-testid="comms-count">{$ban_list|@count|number_format}</span>
                of <span class="tabular-nums">{$total_bans|number_format}</span> blocks
            </p>
        </div>
        <div class="flex gap-2">
            <a class="btn btn--secondary btn--sm"
               href="{$hide_inactive_toggle_url|escape}"
               data-testid="toggle-hide-inactive">
                <i data-lucide="{if $hide_inactive}eye{else}eye-off{/if}"></i>
                {if $hide_inactive}Show inactive{else}Hide inactive{/if}
            </a>
            {if $can_add_comm}
            <a class="btn btn--primary btn--sm"
               href="index.php?p=admin&amp;c=comms"
               data-testid="comms-add-button">
                <i data-lucide="plus"></i> Add comm block
            </a>
            {/if}
        </div>
    </header>

    {* -- Sticky filter bar -------------------------------------------- *}
    <form method="get" action="index.php"
          id="comms-filters"
          style="position:sticky;top:3.5rem;z-index:20;background:var(--bg-page);padding:0.75rem 0;border-bottom:1px solid var(--border)">
        <input type="hidden" name="p" value="commslist">
        <div class="flex gap-3" style="flex-wrap:wrap">
            <div style="flex:1;min-width:14rem;max-width:24rem;position:relative">
                <i data-lucide="search"
                   style="position:absolute;left:0.625rem;top:50%;transform:translateY(-50%);color:var(--text-faint);width:14px;height:14px"></i>
                <input class="input input--with-icon"
                       type="search"
                       name="searchText"
                       value="{$filters.search|escape}"
                       placeholder="Player, SteamID, or reason…"
                       data-testid="comms-search">
            </div>
            <select class="select" name="server" style="width:auto;min-width:11rem" data-testid="comms-server-filter" aria-label="Filter by server">
                <option value="">All servers</option>
                {foreach $servers as $s}
                    <option value="{$s.sid}" {if $filters.server == $s.sid}selected{/if}>{$s.name|escape}</option>
                {/foreach}
            </select>
            <select class="select" name="time" style="width:auto" data-testid="comms-time-filter" aria-label="Filter by time range">
                <option value="">All time</option>
                <option value="1d" {if $filters.time == '1d'}selected{/if}>Today</option>
                <option value="7d" {if $filters.time == '7d'}selected{/if}>Last 7 days</option>
                <option value="30d" {if $filters.time == '30d'}selected{/if}>Last 30 days</option>
            </select>
            <button class="btn btn--secondary btn--sm" type="submit" data-testid="comms-filter-apply">
                <i data-lucide="filter"></i> Apply
            </button>
        </div>

        {* Filter chips. The active chip reflects URL state (?type= /
           ?state=); active also lights up when the hideinactive
           session toggle is set so the chip mirrors the toggle button
           above. Each chip submits the form so the rest of the filter
           state (search/server/time) is preserved. *}
        <div class="flex items-center gap-2 mt-2 scroll-x" role="group" aria-label="Quick filters">
            <button class="chip" type="submit" name="type" value=""
                    aria-pressed="{if $filters.type == '' && $filters.state == ''}true{else}false{/if}"
                    data-testid="filter-chip-all">
                All
            </button>
            <button class="chip" type="submit" name="state" value="active"
                    aria-pressed="{if $hide_inactive || $filters.state == 'active'}true{else}false{/if}"
                    data-testid="filter-chip-active">
                <span class="chip__dot" style="background:#f59e0b"></span> Active
            </button>
            <button class="chip" type="submit" name="type" value="mute"
                    aria-pressed="{if $filters.type == 'mute'}true{else}false{/if}"
                    data-testid="filter-chip-mute">
                <i data-lucide="mic-off" style="width:12px;height:12px"></i> Mute
            </button>
            <button class="chip" type="submit" name="type" value="gag"
                    aria-pressed="{if $filters.type == 'gag'}true{else}false{/if}"
                    data-testid="filter-chip-gag">
                <i data-lucide="message-square-off" style="width:12px;height:12px"></i> Gag
            </button>
            <button class="chip" type="submit" name="type" value="silence"
                    aria-pressed="{if $filters.type == 'silence'}true{else}false{/if}"
                    data-testid="filter-chip-silence">
                <i data-lucide="volume-x" style="width:12px;height:12px"></i> Silence
            </button>
        </div>
    </form>

    {* -- Desktop table ------------------------------------------------ *}
    <div class="card" style="overflow:hidden">
        <table class="table" data-testid="comms-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Player</th>
                    <th>SteamID</th>
                    <th>Length</th>
                    <th>Server</th>
                    <th>Admin</th>
                    <th>Started</th>
                    <th>Status</th>
                    <th aria-label="Actions"></th>
                </tr>
            </thead>
            <tbody>
                {foreach $ban_list as $comm}
                    <tr class="ban-row ban-row--{$comm.state}"
                        data-testid="comm-row"
                        data-id="{$comm.cid}"
                        data-state="{$comm.state}"
                        data-type="{$comm.type}">
                        <td>
                            <span class="pill pill--{$comm.state}" style="text-transform:capitalize">
                                <i data-lucide="{if $comm.type == 'mute'}mic-off{elseif $comm.type == 'gag'}message-square-off{elseif $comm.type == 'silence'}volume-x{else}help-circle{/if}"
                                   style="width:12px;height:12px"></i>
                                {$comm.type|escape}
                            </span>
                        </td>
                        <td>
                            <div class="flex items-center gap-3" style="min-width:0">
                                <span class="avatar"
                                      style="width:28px;height:28px;background:hsl({$comm.avatar_hue} 55% 45%);font-size:10px">
                                    {$comm.name|truncate:2:'':true|upper|escape}
                                </span>
                                {if $comm.name}
                                    <span class="font-medium truncate">{$comm.name|escape}</span>
                                {else}
                                    <span class="text-faint">no nickname</span>
                                {/if}
                            </div>
                        </td>
                        <td class="font-mono text-xs text-muted">{$comm.steam|escape}</td>
                        <td class="tabular-nums text-muted">{$comm.length_human|escape}</td>
                        <td class="text-muted">{$comm.sname|escape}</td>
                        <td class="text-muted">
                            {if $comm.admin}
                                {$comm.admin|escape}
                            {else}
                                <span class="text-faint">—</span>
                            {/if}
                        </td>
                        <td class="text-muted text-xs">
                            <time datetime="{$comm.started_iso|escape}">{$comm.started_human|escape}</time>
                        </td>
                        <td>
                            <span class="pill pill--{$comm.state}" style="text-transform:capitalize">{$comm.state|escape}</span>
                        </td>
                        <td>
                            <div class="row-actions">
                                {if $can_edit_comm}
                                    <a class="btn--ghost btn--icon"
                                       href="{$comm.edit_url|escape}"
                                       title="Edit"
                                       data-testid="row-action-edit">
                                        <i data-lucide="pencil"></i>
                                    </a>
                                {/if}
                                {if $can_unmute_gag && $comm.unmute_url}
                                    <a class="btn--ghost btn--icon"
                                       href="{$comm.unmute_url|escape}"
                                       title="{if $comm.type == 'mute'}Unmute{elseif $comm.type == 'gag'}Ungag{else}Lift block{/if}"
                                       data-testid="row-action-unmute">
                                        <i data-lucide="check"></i>
                                    </a>
                                {/if}
                                {if $can_delete_comm}
                                    <a class="btn--ghost btn--icon"
                                       href="{$comm.delete_url|escape}"
                                       title="Delete"
                                       data-testid="row-action-delete">
                                        <i data-lucide="trash-2"></i>
                                    </a>
                                {/if}
                            </div>
                        </td>
                    </tr>
                {foreachelse}
                    <tr>
                        <td colspan="9"
                            style="text-align:center;padding:3rem;color:var(--text-muted)"
                            data-testid="comms-empty">
                            No comm blocks match those filters.
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>

        {* -- Mobile cards --------------------------------------------- *}
        <div class="ban-cards">
            {foreach $ban_list as $comm}
                <a class="ban-row ban-row--{$comm.state} flex items-center gap-3 p-4"
                   style="border-bottom:1px solid var(--border);text-decoration:none;color:var(--text)"
                   href="?p=commslist&amp;searchText={$comm.steam|escape:'url'}"
                   data-testid="comm-card"
                   data-id="{$comm.cid}">
                    <span class="avatar"
                          style="width:36px;height:36px;background:hsl({$comm.avatar_hue} 55% 45%);font-size:12px">
                        {$comm.name|truncate:2:'':true|upper|escape}
                    </span>
                    <div style="flex:1;min-width:0">
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-sm truncate">
                                {if $comm.name}{$comm.name|escape}{else}<span class="text-faint">no nickname</span>{/if}
                            </span>
                            <span class="pill pill--{$comm.state}">{$comm.state|escape}</span>
                        </div>
                        <div class="text-xs text-muted truncate" style="margin-top:0.125rem">
                            <span style="text-transform:capitalize">{$comm.type|escape}</span>
                            · {$comm.length_human|escape}
                            {if $comm.sname} · {$comm.sname|escape}{/if}
                        </div>
                        <div class="font-mono text-xs text-faint truncate" style="margin-top:0.125rem">{$comm.steam|escape}</div>
                    </div>
                    <i data-lucide="chevron-right"></i>
                </a>
            {/foreach}
        </div>
    </div>

    {* -- Pagination --------------------------------------------------- *}
    <div class="flex items-center justify-between text-xs text-muted" data-testid="comms-pagination">
        <div>
            Showing
            <span class="font-medium tabular-nums" style="color:var(--text)">{$pagination.from|number_format}–{$pagination.to|number_format}</span>
            of
            <span class="font-medium tabular-nums" style="color:var(--text)">{$pagination.total|number_format}</span>
        </div>
        <div class="flex gap-1">
            {if $pagination.prev_url}
                <a class="btn btn--secondary btn--sm"
                   href="{$pagination.prev_url|escape}"
                   data-testid="page-prev">
                    <i data-lucide="chevron-left"></i> Prev
                </a>
            {else}
                <span class="btn btn--secondary btn--sm"
                      aria-disabled="true"
                      style="opacity:0.5;pointer-events:none"
                      data-testid="page-prev">
                    <i data-lucide="chevron-left"></i> Prev
                </span>
            {/if}
            {if $pagination.next_url}
                <a class="btn btn--secondary btn--sm"
                   href="{$pagination.next_url|escape}"
                   data-testid="page-next">
                    Next <i data-lucide="chevron-right"></i>
                </a>
            {else}
                <span class="btn btn--secondary btn--sm"
                      aria-disabled="true"
                      style="opacity:0.5;pointer-events:none"
                      data-testid="page-next">
                    Next <i data-lucide="chevron-right"></i>
                </span>
            {/if}
        </div>
    </div>

    {* `searchlink` is the URL fragment (e.g. `&searchText=foo`) the
       handler builds to preserve the active query across navigation.
       It is already woven into `pagination.prev_url` /
       `pagination.next_url` and `hide_inactive_toggle_url` above, so
       the only reason we mention it here is to keep
       SmartyTemplateRule's parity check happy without a baseline
       entry — the View must declare it so the legacy default theme
       (which embeds `{$searchlink}` literally) keeps rendering, and
       the rule needs to see at least one reference in the new theme
       too. The hidden input below also makes the chip-submit form
       carry the search text forward when a chip is clicked. *}
    <input type="hidden" form="comms-filters" name="_searchlink" value="{$searchlink|escape}" data-testid="comms-searchlink-shadow">

</div>

{* ============================================================
   Manifest of properties only consumed by themes/default/page_comms.tpl.
   Mirrors the dual-theme bridging pattern established by #1123 B2's
   themes/sbpp2026/page_bans.tpl: the `CommsListView` declares the
   union of variables both themes consume, and SmartyTemplateRule's
   "every declared property is referenced" check applies per theme.
   Without the references below, the sbpp2026 leg of the dual-theme
   PHPStan matrix (#1123 A2) would flag every legacy-only property on
   the View as unused.

   {if false} blocks render to nothing — the parser still walks the
   tag bodies, so the variable refs are seen, but no output is
   produced. Putting them in the shared phpstan-baseline.neon would
   instead break the default leg, where these properties are
   referenced naturally and the entry would fire 0 times
   (`reportUnmatchedIgnoredErrors` is on for the default leg).

   D1 deletes themes/default/, the legacy template stops needing
   these props, this manifest stops being necessary, and the View
   drops them. Until then, keep this block at EOF.
   ============================================================ *}
{if false}{$ban_nav}{$canedit}{$cid}{$comment}{$commenttext}{$commenttype}{$ctype}{$hideadminname}{$hidetext}{$othercomments}{$page}{$view_bans}{$view_comments}{/if}
