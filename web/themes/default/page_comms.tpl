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

    Testability hooks (`data-testid`) match the B4 spec: comm-row /
    filter-chip-* / row-action-* / page-prev / page-next / comms-search.
*}
<div class="p-6 space-y-4" style="max-width:1700px;margin:0 auto;width:100%">

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
            {* #1230: aria-pressed reflects whether inactive blocks
               are currently being hidden (binary state, not a
               one-shot action). Pair: .btn--secondary[aria-pressed="true"]
               in theme.css.

               role="button" makes aria-pressed a valid attribute
               on the <a> per WAI-ARIA (the toggle is functionally
               a button — the href is the no-JS progressive-
               enhancement fallback). Without this role axe's
               aria-allowed-attr rule fires "ARIA attribute is not
               allowed: aria-pressed".

               #1274: $is_active_only is the union of the session
               toggle and the chip strip's `?state=active` URL
               surface, so the button's pressed/label state stays
               consistent whether the user clicked the chip or
               this toggle first. The toggle's URL clears both
               surfaces in one shot when going OFF (see
               `$hide_inactive_toggle_url` in page.commslist.php). *}
            <a class="btn btn--secondary btn--sm"
               role="button"
               aria-pressed="{if $is_active_only}true{else}false{/if}"
               href="{$hide_inactive_toggle_url|escape}"
               data-testid="toggle-hide-inactive">
                <i data-lucide="{if $is_active_only}eye{else}eye-off{/if}"></i>
                {if $is_active_only}Show inactive{else}Hide inactive{/if}
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

        {* Filter chips (#1274). Each chip submits the form so the
           rest of the filter state (search/server/time) is preserved
           — the URL gets `?type=mute&searchText=…` and the page
           handler's WHERE builder ANDs them together with the legacy
           ?advType / hideinactive paths. The chip-vs-SQL wiring lives
           in page.commslist.php's $chipType / $chipStateActive block.

             - All chip: pressed only when nothing is filtered (no
               type chip + no active-only). Submits `name="type"
               value=""` to drop the chip type; chip state drops
               naturally because it's not a form input.
             - Active chip: pressed when EITHER the chip's
               `?state=active` URL surface OR the session-based
               "Hide inactive" toggle is on. Both surfaces produce
               the same SQL filter, single-sourced via
               $is_active_only.
             - Type chips (Mute / Gag / Silence): submit `name="type"
               value=…`. Pressed when $filters.type matches. *}
        <div class="flex items-center gap-2 mt-2 scroll-x" role="group" aria-label="Quick filters">
            <button class="chip" type="submit" name="type" value=""
                    aria-pressed="{if $filters.type == '' && !$is_active_only}true{else}false{/if}"
                    data-testid="filter-chip-all">
                All
            </button>
            <button class="chip" type="submit" name="state" value="active"
                    aria-pressed="{if $is_active_only}true{else}false{/if}"
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

    {* -- #1315: advanced-search disclosure ----------------------------
         Mirrors the banlist disclosure shape — same `.filters-details`
         class from #1303, same default-collapsed UX, same
         post-submit auto-open driven by `$is_advanced_search_open`.
         Higher priority on commslist than on banlist because
         commslist doesn't have a drawer to fall back to (no
         `data-drawer-href` on comm rows), so the legacy v1.x
         multi-criterion form is the only way to reach a
         length-comparator / btype / admin / server filter without
         url-spelunking. *}
    <details class="card filters-details"
             data-testid="commslist-advsearch-disclosure"
             {if $is_advanced_search_open}open{/if}>
        <summary class="filters-details__summary"
                 data-testid="commslist-advsearch-toggle"
                 aria-controls="commslist-advsearch-body">
            <span class="filters-details__summary-label">
                <i data-lucide="filter" style="width:14px;height:14px"></i>
                <span>Advanced search</span>
                {if $is_advanced_search_open}
                    <span class="filters-details__count" data-testid="commslist-advsearch-active">
                        &middot; 1 active
                    </span>
                {/if}
            </span>
            <i data-lucide="chevron-down" class="filters-details__chevron" style="width:14px;height:14px"></i>
        </summary>
        <div id="commslist-advsearch-body" class="filters-details__body">
            {load_template file="admin.comms.search"}
        </div>
    </details>

    {* -- Desktop table ------------------------------------------------
         Column tier classes (`.col-tier-2` / `.col-tier-3`) are paired
         with the `.table-scroll` container queries in `theme.css`.
         Tier-3 (Length, Started) hides first when the card is
         <=1500px; tier-2 (Server, Admin) hides at <=1200px. Tier-1
         (Type, Player, SteamID, Status, Actions) always renders so
         the row stays useful without horizontal scroll. The
         `.table-scroll` wrapper carries `container-type: inline-size`
         (theme.css) so the breakpoints react to the painted card
         width — needed because the page-section cap at 1400px makes
         every viewport >= 1688px paint an identical 1352px card,
         which the previous viewport-based @media queries couldn't
         see (#1363). Same chrome shape as `page_bans.tpl`. *}
    <div class="card" style="overflow:hidden">
        <div class="table-scroll">
        <table class="table" data-testid="comms-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Player</th>
                    <th>SteamID</th>
                    <th class="col-length col-tier-3">Length</th>
                    <th class="col-tier-2">Server</th>
                    <th class="col-admin col-tier-2">Admin</th>
                    <th class="col-tier-3">Started</th>
                    <th class="col-status">Status</th>
                    <th class="col-actions" aria-label="Actions"></th>
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
                                <div style="min-width:0">
                                    {if $comm.name}
                                        <span class="font-medium truncate">{$comm.name|escape}</span>
                                    {else}
                                        <span class="text-faint">no nickname</span>
                                    {/if}
                                    {* #1315: surface unban-reason / removed-by line below the
                                       player nickname when the row was lifted by an admin
                                       (state == 'unmuted'). Higher-priority than the banlist
                                       equivalent because the commslist has no drawer to fall
                                       back to (no `data-drawer-href` on `<tr data-testid="comm-row">`).
                                       Gated on $hideadminname so anonymous viewers under a
                                       hidden-admins config don't get the admin name leaked
                                       here either. *}
                                    {if $comm.state == 'unmuted' && !$hideadminname && (!empty($comm.ureason) || !empty($comm.removedby))}
                                        <div class="text-xs text-faint mt-1" data-testid="comm-unban-meta">
                                            {if !empty($comm.removedby)}Lifted by <span class="font-medium">{$comm.removedby|escape}</span>{if !empty($comm.ureason)}: {/if}{/if}
                                            {* `title=` carries the full lift-reason so a long
                                               reason that was cropped on the desktop table reads
                                               in full on hover. Same shape as banlist's
                                               ban-unban-reason span (issue 5). *}
                                            {if !empty($comm.ureason)}<span data-testid="comm-unban-reason" title="{$comm.ureason|escape}">{$comm.ureason|escape}</span>{/if}
                                        </div>
                                    {/if}
                                    {* #BANLIST-COMMENTS sister-fix for commslist: this surface
                                       has the SAME regression as the banlist (page handler
                                       builds `commentdata` per row but the v2.0 rewrite of
                                       this template never re-rendered it — see the
                                       `commslist`-side rationale in AGENTS.md "Per-ban
                                       comments visibility"). The commslist regression is
                                       worse than the banlist's because there's no drawer
                                       fallback (no `data-drawer-href` on `<tr data-testid="comm-row">`),
                                       so the disclosure here is the ONLY on-page way for
                                       admins to see the comment text. Same structure +
                                       gating contract as the banlist disclosure. *}
                                    {if $view_comments && $comm.commentdata != "None" && isset($comm.commentdata) && $comm.commentdata|@count > 0}
                                    <details class="ban-comments-inline mt-1"
                                             data-testid="comm-comments-inline"
                                             data-cid="{$comm.cid}">
                                      <summary class="ban-comments-inline__summary"
                                               data-testid="comm-comments-toggle"
                                               title="{$comm.commentdata|@count} comment{if $comm.commentdata|@count != 1}s{/if} on this comm-block">
                                        <i data-lucide="message-square-text" style="width:11px;height:11px" aria-hidden="true"></i>
                                        <span class="tabular-nums">{$comm.commentdata|@count}</span>
                                        <span class="ban-comments-inline__label">comment{if $comm.commentdata|@count != 1}s{/if}</span>
                                      </summary>
                                      <ul class="ban-comments-inline__list" data-testid="comm-comments-list">
                                        {foreach from=$comm.commentdata item=com}
                                        <li class="ban-comments-inline__item" data-testid="comm-comment-item">
                                          <div class="ban-comments-inline__meta">
                                            {if !empty($com.comname)}<strong>{$com.comname|escape}</strong>{else}<i class="text-faint">deleted admin</i>{/if}
                                            <span class="text-faint">&middot;</span>
                                            <span class="text-xs text-faint tabular-nums">{$com.added}</span>
                                          </div>
                                          {* nofilter: $com.commenttxt is server-built HTML produced by encodePreservingBr (htmlspecialchars per text segment, only `<br/>` survives) plus a URL-wrap regex that wraps already-escaped URLs in `<a>` tags — see page.commslist.php $commentres loop. Same provenance + safety as the banlist disclosure (template comment up-thread). *}
                                          <div class="ban-comments-inline__text" data-testid="comm-comment-text">{$com.commenttxt nofilter}</div>
                                          {if !empty($com.edittime)}
                                          <div class="ban-comments-inline__edit text-xs text-faint">last edit {$com.edittime} by {if !empty($com.editname)}{$com.editname|escape}{else}<i>deleted admin</i>{/if}</div>
                                          {/if}
                                        </li>
                                        {/foreach}
                                      </ul>
                                    </details>
                                    {/if}
                                </div>
                            </div>
                        </td>
                        <td class="font-mono text-xs text-muted">{$comm.steam|escape}</td>
                        <td class="col-length col-tier-3 tabular-nums text-muted"
                            {if !empty($comm.length_human)}title="{$comm.length_human|escape}"{/if}>{$comm.length_human|escape}</td>
                        <td class="col-tier-2 text-muted">{$comm.sname|escape}</td>
                        <td class="col-admin col-tier-2 text-muted">
                            {if $comm.admin}
                                {$comm.admin|escape}
                            {else}
                                <span class="text-faint">—</span>
                            {/if}
                        </td>
                        <td class="col-tier-3 text-muted text-xs">
                            <time datetime="{$comm.started_iso|escape}">{$comm.started_human|escape}</time>
                        </td>
                        <td class="col-status">
                            <span class="pill pill--{$comm.state}" style="text-transform:capitalize">{$comm.state|escape}</span>
                        </td>
                        <td class="col-actions">
                            <div class="row-actions">
                                {if $can_edit_comm}
                                    <a class="btn btn--ghost btn--sm"
                                       href="{$comm.edit_url|escape}"
                                       data-testid="row-action-edit">
                                        <i data-lucide="pencil" style="width:13px;height:13px"></i>
                                        Edit
                                    </a>
                                {/if}
                                {if $can_unmute_gag && $comm.unmute_url}
                                    {* #1207 ADM-5/ADM-6: button + data-action wires to the
                                       inline page-tail JS below, which calls
                                       Actions.CommsUnblock and updates the row in-place
                                       (state pill flips, action set swaps to Re-apply,
                                       toast fires). The href fallback preserves the
                                       legacy GET path for no-JS callers + third-party
                                       themes that haven't migrated. *}
                                    <button type="button"
                                            class="btn btn--secondary btn--sm"
                                            data-testid="row-action-unmute"
                                            data-action="comms-unblock"
                                            data-bid="{$comm.cid}"
                                            data-name="{$comm.name|escape}"
                                            data-fallback-href="{$comm.unmute_url|escape}">
                                        <i data-lucide="check" style="width:13px;height:13px"></i>
                                        {if $comm.type == 'mute'}Unmute{elseif $comm.type == 'gag'}Ungag{else}Lift block{/if}
                                    </button>
                                {elseif $can_add_comm && ($comm.state == 'unmuted' || $comm.state == 'expired')}
                                    {* #1207 ADM-6: when a row is no longer active, swap the
                                       lift action for Re-apply. Anchor goes through the
                                       admin-comms add form's `rebanid` flow (which calls
                                       comms.prepare_reblock to hydrate every field) so we
                                       don't need a separate "re-block" handler. *}
                                    <a class="btn btn--secondary btn--sm"
                                       href="index.php?p=admin&amp;c=comms&amp;rebanid={$comm.cid}"
                                       data-testid="row-action-reapply">
                                        <i data-lucide="rotate-ccw" style="width:13px;height:13px"></i>
                                        Re-apply
                                    </a>
                                {/if}
                                {if $can_delete_comm}
                                    <button type="button"
                                            class="btn btn--ghost btn--sm"
                                            data-testid="row-action-delete"
                                            data-action="comms-delete"
                                            data-bid="{$comm.cid}"
                                            data-name="{$comm.name|escape}"
                                            data-fallback-href="{$comm.delete_url|escape}"
                                            style="color:var(--danger)">
                                        <i data-lucide="trash-2" style="width:13px;height:13px"></i>
                                        Remove
                                    </button>
                                {/if}
                            </div>
                        </td>
                    </tr>
                {foreachelse}
                    {* #1207 empty-state unification — first-run vs filtered.
                       `$is_filtered` flips on any of search / server / time /
                       state / type / hide-inactive (see page.commslist.php
                       for the predicate). With zero rows AND no filter we
                       fall through to the first-run shape (CTA gated on
                       `can_add_comm`); otherwise it stays "Clear filters". *}
                    <tr>
                        {if $is_filtered}
                        <td colspan="9"
                            style="padding:0"
                            data-testid="comms-empty"
                            data-filtered="true">
                            <div class="empty-state">
                                <span class="empty-state__icon" aria-hidden="true">
                                    <i data-lucide="search-x" style="width:18px;height:18px"></i>
                                </span>
                                <h2 class="empty-state__title">No comm blocks match those filters</h2>
                                <p class="empty-state__body">Try a different search term, server, or time range &mdash; or clear the active filters to see every recorded mute / gag.</p>
                                <div class="empty-state__actions">
                                    <a class="btn btn--secondary btn--sm"
                                       href="?p=commslist"
                                       data-testid="comms-empty-clear">
                                        <i data-lucide="x" style="width:13px;height:13px"></i>
                                        Clear filters
                                    </a>
                                </div>
                            </div>
                        </td>
                        {else}
                        <td colspan="9"
                            style="padding:0"
                            data-testid="comms-empty"
                            data-filtered="false">
                            <div class="empty-state">
                                <span class="empty-state__icon" aria-hidden="true">
                                    <i data-lucide="mic-off" style="width:18px;height:18px"></i>
                                </span>
                                <h2 class="empty-state__title">No comm blocks recorded yet</h2>
                                <p class="empty-state__body">Mutes and gags issued from the panel or in-game will appear here.</p>
                                {if $can_add_comm}
                                <div class="empty-state__actions">
                                    <a class="btn btn--primary btn--sm"
                                       href="?p=admin&amp;c=comms"
                                       data-testid="comms-empty-add">
                                        <i data-lucide="plus" style="width:13px;height:13px"></i>
                                        Add a comm block
                                    </a>
                                </div>
                                {/if}
                            </div>
                        </td>
                        {/if}
                    </tr>
                {/foreach}
            </tbody>
        </table>
        </div>{* /.table-scroll *}

        {* -- Mobile cards --------------------------------------------- *}
        {* #1207 ADM-5: each card is now a `<div>` wrapping (a) a
           clickable summary anchor that filters the list by the row's
           SteamID and (b) a row-actions footer with the same
           Edit / Unmute / Remove / Re-apply set the desktop table
           exposes. The previous shape wrapped the whole card in a
           single `<a>` so there was no place to put `<button>` actions
           without producing invalid nested-interactive HTML. The
           data-testid stays `comm-card`; the anchor's `comm-card-link`
           sub-hook lets specs assert the navigate-to-search affordance
           independently from the action row. *}
        <div class="ban-cards">
            {foreach $ban_list as $comm}
                <div class="ban-row ban-row--{$comm.state} ban-card"
                     style="border-bottom:1px solid var(--border)"
                     data-testid="comm-card"
                     data-id="{$comm.cid}"
                     data-state="{$comm.state}"
                     data-type="{$comm.type}">
                    <a class="ban-card__summary flex items-center gap-3 p-4"
                       style="text-decoration:none;color:var(--text)"
                       href="?p=commslist&amp;searchText={$comm.steam|escape:'url'}"
                       data-testid="comm-card-link">
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
                            {* #1315: mobile mirror of the desktop `comm-unban-meta`
                               line — surface lifted-by + ureason on rows the admin
                               unmuted so players don't have to hunt for the lift
                               context (no drawer fallback exists on the commslist).
                               Gated on $hideadminname for parity with the desktop branch. *}
                            {if $comm.state == 'unmuted' && !$hideadminname && (!empty($comm.ureason) || !empty($comm.removedby))}
                                {* `title=` carries the full lift-by + ureason so a long
                                   reason that was truncated to one line by the parent
                                   .truncate utility reads in full on long-press / hover
                                   (issue 5). *}
                                <div class="text-xs text-faint truncate" style="margin-top:0.125rem" data-testid="comm-unban-meta-mobile"
                                     title="{if !empty($comm.removedby)}Lifted by {$comm.removedby|escape}{if !empty($comm.ureason)}: {/if}{/if}{if !empty($comm.ureason)}{$comm.ureason|escape}{/if}">
                                    {if !empty($comm.removedby)}Lifted by {$comm.removedby|escape}{if !empty($comm.ureason)}: {/if}{/if}
                                    {if !empty($comm.ureason)}{$comm.ureason|escape}{/if}
                                </div>
                            {/if}
                        </div>
                        <i data-lucide="chevron-right"></i>
                    </a>
                    {if $can_edit_comm || ($can_unmute_gag && $comm.unmute_url) || ($can_add_comm && ($comm.state == 'unmuted' || $comm.state == 'expired')) || $can_delete_comm}
                    <div class="row-actions ban-card__actions">
                        {if $can_edit_comm}
                            <a class="btn btn--ghost btn--sm"
                               href="{$comm.edit_url|escape}"
                               data-testid="row-action-edit-mobile">
                                <i data-lucide="pencil" style="width:13px;height:13px"></i>
                                Edit
                            </a>
                        {/if}
                        {if $can_unmute_gag && $comm.unmute_url}
                            <button type="button"
                                    class="btn btn--secondary btn--sm"
                                    data-testid="row-action-unmute-mobile"
                                    data-action="comms-unblock"
                                    data-bid="{$comm.cid}"
                                    data-name="{$comm.name|escape}"
                                    data-fallback-href="{$comm.unmute_url|escape}">
                                <i data-lucide="check" style="width:13px;height:13px"></i>
                                {if $comm.type == 'mute'}Unmute{elseif $comm.type == 'gag'}Ungag{else}Lift block{/if}
                            </button>
                        {elseif $can_add_comm && ($comm.state == 'unmuted' || $comm.state == 'expired')}
                            <a class="btn btn--secondary btn--sm"
                               href="index.php?p=admin&amp;c=comms&amp;rebanid={$comm.cid}"
                               data-testid="row-action-reapply-mobile">
                                <i data-lucide="rotate-ccw" style="width:13px;height:13px"></i>
                                Re-apply
                            </a>
                        {/if}
                        {if $can_delete_comm}
                            <button type="button"
                                    class="btn btn--ghost btn--sm"
                                    data-testid="row-action-delete-mobile"
                                    data-action="comms-delete"
                                    data-bid="{$comm.cid}"
                                    data-name="{$comm.name|escape}"
                                    data-fallback-href="{$comm.delete_url|escape}"
                                    style="color:var(--danger)">
                                <i data-lucide="trash-2" style="width:13px;height:13px"></i>
                                Remove
                            </button>
                        {/if}
                    </div>
                    {/if}
                </div>
            {foreachelse}
                {* #1207: the desktop `<table>` is `display:none` on
                   mobile (theme.css responsive block), so its empty
                   row above never renders below 769px. Mirror the
                   first-run-vs-filtered split here for phones. *}
                {if $is_filtered}
                <div class="empty-state" data-testid="comms-empty-mobile" data-filtered="true">
                    <span class="empty-state__icon" aria-hidden="true">
                        <i data-lucide="search-x" style="width:18px;height:18px"></i>
                    </span>
                    <h2 class="empty-state__title">No comm blocks match those filters</h2>
                    <p class="empty-state__body">Try a different search term or clear the active filters.</p>
                    <div class="empty-state__actions">
                        <a class="btn btn--secondary btn--sm" href="?p=commslist">
                            <i data-lucide="x" style="width:13px;height:13px"></i>
                            Clear filters
                        </a>
                    </div>
                </div>
                {else}
                <div class="empty-state" data-testid="comms-empty-mobile" data-filtered="false">
                    <span class="empty-state__icon" aria-hidden="true">
                        <i data-lucide="mic-off" style="width:18px;height:18px"></i>
                    </span>
                    <h2 class="empty-state__title">No comm blocks recorded yet</h2>
                    <p class="empty-state__body">Mutes and gags issued from the panel or in-game will appear here.</p>
                    {if $can_add_comm}
                    <div class="empty-state__actions">
                        <a class="btn btn--primary btn--sm" href="?p=admin&amp;c=comms">
                            <i data-lucide="plus" style="width:13px;height:13px"></i>
                            Add a comm block
                        </a>
                    </div>
                    {/if}
                </div>
                {/if}
            {/foreach}
        </div>
    </div>

    {* -- Pagination ---------------------------------------------------
         #1225: short-circuit on a zero result count so the
         "Showing 0–0 of 0" shell doesn't render below the empty
         state on a fresh install. The empty state already owns the
         "this list is empty" message; the pagination card is dead
         chrome at total=0. Pair: page.commslist.php sets
         $ban_nav = '' for the legacy theme contract. *}
    {if $pagination.total > 0}
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
    {/if}

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
   #1301 — comms unblock confirm + reason modal scaffold.

   v1.x had two safeguards before lifting a comm block: a confirm
   modal and a non-empty unblock-reason prompt. Both lived in the
   deleted `web/scripts/sourcebans.js` (UnMuteBan / UnGagBan), and
   the v2.0 cutover left the row's affordance as a single click that
   silently sent an empty `ureason`. This `<dialog>` is the v2.0-shape
   replacement, wired by the inline IIFE below. The dialog stays
   `hidden` on first paint so a JS failure leaves the unmute
   affordance reachable only via the no-JS GET fallback (which itself
   now requires a non-empty reason, see #1301 guard in
   page.commslist.php).
   ============================================================ *}
<dialog id="comms-unblock-dialog"
        class="palette"
        aria-labelledby="comms-unblock-dialog-title"
        data-testid="comms-unblock-dialog"
        hidden
        style="max-width:32rem;width:90vw;padding:1.25rem;border-radius:0.75rem;border:1px solid var(--border)">
  <form method="dialog" data-testid="comms-unblock-form">
    <h2 id="comms-unblock-dialog-title" data-testid="comms-unblock-dialog-title" style="font-size:var(--fs-lg);font-weight:600;margin:0 0 0.25rem">Lift block</h2>
    <p class="text-sm text-muted m-0" style="margin-bottom:0.75rem">
      Why are you lifting the block on <strong data-testid="comms-unblock-target">this player</strong>? This reason is recorded against the block and surfaced in the audit log.
    </p>
    <label class="label" for="comms-unblock-reason">Reason <span aria-hidden="true" style="color:var(--danger)">*</span></label>
    {* aria-required (not the native `required`) so assistive tech announces
       the field as required while the JS submit handler owns the empty-reason
       branch — `required` would let the browser block the form submit before
       our handler runs, swallowing the inline error UX (#1301). *}
    <textarea class="textarea"
              id="comms-unblock-reason"
              data-testid="comms-unblock-reason"
              rows="3"
              aria-required="true"
              maxlength="255"
              autocomplete="off"
              placeholder="Mistaken block, appeal accepted, sentence served, &hellip;"></textarea>
    <p class="text-xs" data-testid="comms-unblock-error" role="alert" hidden style="color:var(--danger);margin:0.25rem 0 0"></p>
    <div class="flex gap-2 mt-4" style="justify-content:flex-end">
      <button type="button" class="btn btn--secondary" data-testid="comms-unblock-cancel" value="cancel">Cancel</button>
      <button type="submit" class="btn btn--primary" data-testid="comms-unblock-submit" value="confirm">
        <i data-lucide="check" style="width:13px;height:13px"></i> Confirm
      </button>
    </div>
  </form>
</dialog>

{* ============================================================
   #1207 ADM-5/ADM-6 + #1301 — comms row-action wiring (inline
   page-tail JS).

   Click delegation per anti-pattern: every action button in the rows
   above (desktop table + mobile cards) carries `data-action="comms-*"`
   plus `data-bid` / `data-name` / `data-fallback-href`. The handler
   intercepts those clicks, calls `sb.api.call(Actions.PascalName, …)`,
   and updates the row in-place on success (state pill flips, action
   set re-renders, `window.SBPP.showToast` confirms). The fallback
   href is followed as a navigation when the JSON dispatcher is
   missing entirely (e.g. third-party theme with stripped api.js) —
   that's the legacy GET URL the `?p=commslist&a=…` handler in
   page.commslist.php still serves (and which now also requires a
   non-empty `ureason` per #1301 — empty-reason hand-edits get
   bounced server-side too).

   `comms-unblock` flow (#1301): instead of firing the API call
   immediately, opens the `#comms-unblock-dialog` <dialog> for a
   confirm + reason prompt. The dialog's submit handler validates
   the trimmed reason locally (so we don't bother the server with
   the obvious empty-reason case) and forwards it as `ureason` to
   `Actions.CommsUnblock`. Server-side validation in
   `api_comms_unblock` is the load-bearing gate.

   No `// @ts-check` here because the file is rendered by Smarty;
   ts-check only runs against `.js` sources in `web/scripts`. The
   shape mirrors the inline handler in page_admin_bans_submissions.tpl
   (#1207 PUB-2 reference).
   ============================================================ *}
{literal}
<script>
(function () {
    'use strict';

    /** @returns {{call: (a:string,p?:object)=>Promise<any>}|null} */
    function api()     { return (window.sb && window.sb.api) || null; }
    /** @returns {Record<string,string>|null} */
    function actions() { return /** @type {any} */ (window).Actions || null; }
    function toast(kind, title, body) {
        var sbpp = /** @type {any} */ (window).SBPP;
        if (sbpp && typeof sbpp.showToast === 'function') {
            sbpp.showToast({ kind: kind, title: title, body: body || '' });
        }
    }
    /**
     * Flip the busy / loading state on a triggered action button. Calls
     * window.SBPP.setBusy when present (theme.js owns the spinner CSS
     * contract) and falls back to plain `disabled` so third-party themes
     * that strip theme.js still gate against double-clicks.
     * @param {Element|null} btn
     * @param {boolean} [busy] defaults to true
     */
    function setBusy(btn, busy) {
        if (!btn) return;
        var S = /** @type {any} */ (window).SBPP;
        if (S && typeof S.setBusy === 'function') S.setBusy(btn, busy);
        else /** @type {HTMLButtonElement} */ (btn).disabled = busy === undefined ? true : !!busy;
    }

    /**
     * Find every DOM node that mirrors the same comm-block id — the
     * desktop `<tr data-testid="comm-row">` and the mobile
     * `<div data-testid="comm-card">` both render the same row.
     * theme.css's `@media (max-width: 768px)` block hides the
     * `<table>` via `display: none` rather than removing the DOM,
     * so an in-place state update has to flip both copies (only one
     * is visible per viewport, but stale state in the hidden one
     * silently breaks the next viewport switch in the E2E spec).
     * @param {string} bid
     * @returns {Element[]}
     */
    function rowsForBid(bid) {
        var sel = '[data-testid="comm-row"][data-id="' + bid + '"], '
                + '[data-testid="comm-card"][data-id="' + bid + '"]';
        return Array.prototype.slice.call(document.querySelectorAll(sel));
    }

    /**
     * Update both copies of the row from `active|permanent` → `unmuted`:
     *  - `data-state` on the wrapper.
     *  - `ban-row--<state>` class on the wrapper.
     *  - The status pill in column 8 (desktop) / inline pill (mobile)
     *    has its `pill--<state>` class swapped AND its visible label
     *    rewritten. The type pill in column 1 also carries the
     *    `pill--<state>` class for the colored border treatment, but
     *    its text is the type label — we leave the text alone.
     *  - The Unmute button is replaced by a Re-apply anchor (for
     *    callers with the add_comm flag) so the row's "make this
     *    block live again" affordance lands in the same slot.
     * @param {Element} row
     * @returns {void}
     */
    function flipRowToUnmuted(row) {
        var prev = (row.getAttribute('data-state') || '').toLowerCase();
        row.setAttribute('data-state', 'unmuted');
        row.classList.remove('ban-row--active', 'ban-row--permanent', 'ban-row--expired');
        row.classList.add('ban-row--unmuted');
        Array.prototype.forEach.call(row.querySelectorAll('.pill'), function (pill) {
            // Only the *status* pill carries the previous state as its
            // visible label; type pills (column 1) say "mute"/"gag" and
            // we leave their text intact. The class swap applies to
            // both — the colored border treatment comes from
            // `pill--<state>` and both pills should track the new
            // state for the visual hierarchy to stay consistent.
            pill.classList.remove('pill--active', 'pill--permanent', 'pill--expired');
            pill.classList.add('pill--unmuted');
            var txt = (pill.textContent || '').trim().toLowerCase();
            if (txt === prev || txt === 'active' || txt === 'permanent' || txt === 'expired') {
                // Preserve any leading <i> icon — only the trailing
                // text node carries the state label. Walk children
                // backwards to find it.
                var lastText = null;
                for (var i = pill.childNodes.length - 1; i >= 0; i--) {
                    var n = pill.childNodes[i];
                    if (n.nodeType === 3) { lastText = n; break; }
                }
                if (lastText) lastText.textContent = ' Unmuted';
                else pill.textContent = 'Unmuted';
            }
        });
        Array.prototype.forEach.call(
            row.querySelectorAll('[data-action="comms-unblock"]'),
            function (btn) {
                var bid = btn.getAttribute('data-bid') || '';
                var a = document.createElement('a');
                a.className = 'btn btn--secondary btn--sm';
                var isMobile = (btn.getAttribute('data-testid') || '').indexOf('mobile') !== -1;
                a.setAttribute('data-testid', isMobile ? 'row-action-reapply-mobile' : 'row-action-reapply');
                a.setAttribute('href', 'index.php?p=admin&c=comms&rebanid=' + encodeURIComponent(bid));
                a.innerHTML = '<i data-lucide="rotate-ccw" style="width:13px;height:13px"></i> Re-apply';
                btn.parentNode.replaceChild(a, btn);
            }
        );
        if (window.lucide) window.lucide.createIcons();
    }

    /**
     * @param {Element} row
     * @returns {void}
     */
    function removeRow(row) { if (row.parentNode) row.parentNode.removeChild(row); }

    /** @returns {void} */
    function decrementCount() {
        var el = document.querySelector('[data-testid="comms-count"]');
        if (!el) return;
        var n = Number((el.textContent || '').replace(/[^0-9]/g, ''));
        if (!Number.isFinite(n) || n <= 0) return;
        el.textContent = (n - 1).toLocaleString();
    }

    /** @returns {HTMLDialogElement|null} */
    function dialog() {
        return /** @type {HTMLDialogElement|null} */ (document.getElementById('comms-unblock-dialog'));
    }
    /** @returns {HTMLTextAreaElement|null} */
    function reasonInput() {
        return /** @type {HTMLTextAreaElement|null} */ (document.getElementById('comms-unblock-reason'));
    }
    /** @returns {HTMLElement|null} */
    function errorEl() {
        var d = dialog();
        return d ? /** @type {HTMLElement|null} */ (d.querySelector('[data-testid="comms-unblock-error"]')) : null;
    }
    /** @param {string} msg */
    function showError(msg) { var e = errorEl(); if (!e) return; e.textContent = msg; e.hidden = false; }
    function clearError() { var e = errorEl(); if (!e) return; e.textContent = ''; e.hidden = true; }

    /** @type {{bid: string, name: string, fallback: string, type: string}|null} */
    var pending = null;

    /**
     * @param {string} type
     * @returns {{title: string, verb: string}}
     */
    function copyForType(type) {
        if (type === 'mute')    return { title: 'Unmute player',     verb: 'unmute' };
        if (type === 'gag')     return { title: 'Ungag player',      verb: 'ungag' };
        if (type === 'silence') return { title: 'Lift silence',      verb: 'lift the silence on' };
        return                          { title: 'Lift block',       verb: 'lift the block on' };
    }

    /** @param {{bid: string, name: string, fallback: string, type: string}} ctx */
    function openUnblockDialog(ctx) {
        pending = ctx;
        var d = dialog();
        if (!d) {
            // Dialog markup missing — fall back to the legacy GET path
            // so the action still works (it now also requires
            // ureason= in the URL per #1301; without one the page
            // handler bounces with an inline error).
            if (ctx.fallback) window.location.href = ctx.fallback;
            return;
        }
        var copy = copyForType(ctx.type);
        var title = d.querySelector('[data-testid="comms-unblock-dialog-title"]');
        if (title) title.textContent = copy.title;
        var prompt = d.querySelector('[data-testid="comms-unblock-target"]');
        if (prompt) prompt.textContent = ctx.name || ('block #' + ctx.bid);
        // Update the surrounding sentence's verb in place. The
        // <strong> nested-target child carries the player's name, so we
        // rewrite the parent paragraph's first text node to swap "lifting
        // the block on" for the type-specific verb without losing the
        // bold name span.
        var p = d.querySelector('p.text-sm.text-muted');
        if (p && p.firstChild && p.firstChild.nodeType === 3) {
            p.firstChild.textContent = 'Why are you ' + copy.verb + ' ';
        }
        var input = reasonInput();
        if (input) input.value = '';
        clearError();
        d.removeAttribute('hidden');
        try { d.showModal(); }
        catch (_e) { d.setAttribute('open', ''); }
        if (input) { try { input.focus(); } catch (_e) { /* focus may throw */ } }
    }

    function closeUnblockDialog() {
        var d = dialog();
        if (!d) return;
        try { d.close(); } catch (_e) { /* not opened modally */ }
        d.setAttribute('hidden', '');
        // Re-enable any unblock buttons we disabled before showing the
        // dialog so a Cancel click leaves the row clickable again.
        // Includes the `data-loading="true"` selector branch so a
        // SetBusy-flipped trigger releases too (the row-action button
        // never gets flipped through this code path today, but the
        // pairing keeps the contract symmetric).
        Array.prototype.forEach.call(
            document.querySelectorAll('[data-action="comms-unblock"][disabled], [data-action="comms-unblock"][data-loading="true"]'),
            function (btn) { setBusy(btn, false); }
        );
        pending = null;
    }

    document.addEventListener('click', function (e) {
        var t = /** @type {Element|null} */ (e.target);
        if (!t || !t.closest) return;

        // Cancel button inside the dialog.
        if (t.closest('[data-testid="comms-unblock-cancel"]')) {
            e.preventDefault();
            closeUnblockDialog();
            return;
        }

        var btn = /** @type {HTMLElement|null} */ (t.closest('[data-action]'));
        if (!btn) return;
        var act = btn.getAttribute('data-action');
        if (act !== 'comms-unblock' && act !== 'comms-delete') return;
        e.preventDefault();

        var bid = btn.getAttribute('data-bid') || '';
        var name = btn.getAttribute('data-name') || ('block #' + bid);
        var fallback = btn.getAttribute('data-fallback-href') || '';
        var a = api(), A = actions();
        if (!a || !A || !bid) {
            // No JSON dispatcher available (e.g. third-party theme that
            // stripped api.js). Fall back to the legacy GET URL — same
            // outcome, full page reload (page handler bounces empty
            // ureason per #1301).
            if (fallback) window.location.href = fallback;
            return;
        }

        if (act === 'comms-delete') {
            if (!window.confirm('Delete the block for "' + name + '"?')) return;
            setBusy(btn, true);
            a.call(A.CommsDelete, { bid: Number(bid) }).then(function (r) {
                if (!r || r.ok === false) {
                    setBusy(btn, false);
                    var msg = (r && r.error && r.error.message) || 'Unknown error';
                    toast('error', 'Delete failed', msg);
                    return;
                }
                rowsForBid(bid).forEach(removeRow);
                decrementCount();
                toast('success', 'Block removed', 'The block for ' + name + ' has been deleted.');
            });
            return;
        }

        // #1301 — comms-unblock now opens the confirm + reason modal
        // instead of firing the API call immediately. The legacy v2.0
        // shape silently posted `ureason: ''`; v1.x prompted via
        // sourcebans.js's UnMute()/UnGag() helpers. The modal is the
        // v2.0-shape replacement.
        var typeAttr = '';
        var rowEl = /** @type {Element|null} */ (btn.closest('[data-type]'));
        if (rowEl) typeAttr = rowEl.getAttribute('data-type') || '';
        openUnblockDialog({ bid: bid, name: name, fallback: fallback, type: typeAttr });
    });

    document.addEventListener('submit', function (e) {
        var form = /** @type {Element|null} */ (e.target);
        if (!form || !(/** @type {Element} */ (form)).closest) return;
        if (!form.matches('[data-testid="comms-unblock-form"]')) return;
        e.preventDefault();
        if (!pending) return;

        var input = reasonInput();
        var reason = input ? input.value.trim() : '';
        if (reason === '') {
            // Mirror the v1.x guard: surface an inline error rather
            // than submitting an empty reason that the server would
            // bounce. The server still re-validates as the
            // load-bearing gate (api_comms_unblock).
            showError('Please leave a comment.');
            if (input) try { input.focus(); } catch (_e) { /* focus may throw */ }
            return;
        }
        clearError();

        var ctx = pending;
        var submitBtn = /** @type {HTMLButtonElement|null} */ (form.querySelector('[data-testid="comms-unblock-submit"]'));
        setBusy(submitBtn, true);

        var a = api(), A = actions();
        if (!a || !A) {
            setBusy(submitBtn, false);
            if (ctx.fallback) {
                var sep = ctx.fallback.indexOf('?') === -1 ? '?' : '&';
                window.location.href = ctx.fallback + sep + 'ureason=' + encodeURIComponent(reason);
            }
            return;
        }

        a.call(A.CommsUnblock, { bid: Number(ctx.bid), ureason: reason }).then(function (r) {
            setBusy(submitBtn, false);
            if (!r || r.ok === false) {
                var msg = (r && r.error && r.error.message) || 'Unknown error';
                showError(msg);
                toast('error', 'Unblock failed', msg);
                return;
            }
            rowsForBid(ctx.bid).forEach(flipRowToUnmuted);
            closeUnblockDialog();
            toast('success', 'Block lifted', ctx.name + ' has been unblocked.');
        });
    });

    document.addEventListener('cancel', function (e) {
        var t = /** @type {Element|null} */ (e.target);
        if (!t || t.id !== 'comms-unblock-dialog') return;
        pending = null;
        clearError();
    });
})();
</script>
{/literal}

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
{if false}{$ban_nav}{$canedit}{$cid}{$comment}{$commenttext}{$commenttype}{$ctype}{$hide_inactive}{$hideadminname}{$hidetext}{$othercomments}{$page}{$view_bans}{$view_comments}{/if}
