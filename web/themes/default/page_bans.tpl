{* ============================================================
   page_bans.tpl — sbpp2026 public ban list (the marquee page)
   Vars: $ban_list, $total_bans, $hidetext, $searchlink, $view_bans,
         $hideadminname, $hideplayerips, $groupban, $friendsban,
         $general_unban, $can_delete, $can_export, $admin_postkey,
         $view_comments, plus the comment-edit scratch pad
         ($comment, $commenttype, $commenttext, $ctype, $cid, $page,
         $canedit, $othercomments).
   Per-row $ban.* keys: bid, name, steam, state, length, length_human,
         banned_human, banned_iso, sname, reason, aname, ban_ip_raw,
         can_edit_ban, can_unban, mod_icon, country, demo_available,
         view_delete, type, commentdata.
   Permission gates that aren't pre-precomputable on the View use
   the {has_access flag=...} block plugin (A3 helper).
   ============================================================ *}

{* -- Comment edit mode -- replaces the body when ?comment=N is set.
   The legacy default theme renders this at the top of the file; we
   keep the same surface but drop the page chrome around it so the
   action sits in a focused card. CSRF + submit go through the JSON
   API (sb.api.call(Actions.BansAddComment / BansEditComment)) —
   wired in the inline form handler below. *}
{if $comment}
<div class="card" style="max-width:42rem;margin:1.5rem auto">
  <div class="card__header">
    <div>
      <h3>{$commenttype} comment</h3>
      <p>Visible to admins; commented threads also surface to the public when public comments are enabled.</p>
    </div>
  </div>
  <div class="card__body">
    <form id="banlist-comment-form" data-bid="{$comment}" data-ctype="{$ctype}" data-cid="{$cid}" data-page="{$page}">
      <label class="label" for="banlist-comment-text">Comment</label>
      <textarea class="textarea" id="banlist-comment-text" name="commenttext" rows="6" {if !$canedit}disabled{/if}>{$commenttext}</textarea>
      <div class="flex gap-2 mt-4">
        {if $canedit}
        <button class="btn btn--primary" type="submit">{$commenttype} comment</button>
        {/if}
        <button class="btn btn--secondary" type="button" onclick="history.back()">Back</button>
      </div>
    </form>

    <div class="mt-6">
      {foreach from=$othercomments item=com name=othercomments}
        {if $smarty.foreach.othercomments.first}<h3 style="font-size:var(--fs-base);font-weight:600;margin:0 0 0.5rem">Other comments</h3>{/if}
        <div class="mt-4" style="border-top:1px solid var(--border);padding-top:0.75rem">
          <div class="flex items-center justify-between">
            <strong>{$com.comname|escape}</strong>
            <span class="text-xs text-muted">{$com.added}</span>
          </div>
          {* nofilter: $com.commenttxt is server-built HTML produced by encodePreservingBr (htmlspecialchars per text segment, only `<br/>` survives) plus a URL-wrap regex that wraps already-escaped URLs in `<a>` tags — see page.banlist.php $cotherdata loop *}
          <div class="text-sm mt-2">{$com.commenttxt nofilter}</div>
          {if $com.editname != ''}
          <div class="text-xs text-faint mt-2">last edit {$com.edittime} by {$com.editname|escape}</div>
          {/if}
        </div>
      {/foreach}
    </div>
  </div>
</div>
{else}

<div id="banlist-root" class="p-6 space-y-4" style="max-width:1400px;margin:0 auto" data-loading="false">
  <div class="flex items-center justify-between gap-3" style="flex-wrap:wrap">
    <div>
      <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">Ban list</h1>
      <p class="text-sm text-muted m-0 mt-2"><span class="tabular-nums">{$ban_list|@count}</span> of <span class="tabular-nums">{$total_bans}</span> bans</p>
    </div>
    <div class="flex gap-2 items-center">
      {if $can_export}
      <a class="btn btn--secondary btn--sm" href="exportbans.php?type=steam" title="Export permanent SteamID bans">Export Steam</a>
      <a class="btn btn--secondary btn--sm" href="exportbans.php?type=ip" title="Export permanent IP bans">Export IP</a>
      {/if}
      {* #1230: aria-pressed mirrors whether inactive bans are
         currently being hidden ($hidetext == 'Show' means the
         session toggle is set, i.e. the list is filtered).
         Pair: .btn--secondary[aria-pressed="true"] in theme.css.

         role="button" makes aria-pressed a valid attribute on the
         <a> per WAI-ARIA (the toggle is functionally a button —
         the href is the no-JS progressive-enhancement fallback,
         not a navigation in the breadcrumb sense). Without this
         role axe's aria-allowed-attr rule fires "ARIA attribute
         is not allowed: aria-pressed" — see the same shape on
         page_comms.tpl.

         #1352: the toggle is hidden when an explicit `?state=` chip
         is active. The session-based "Hide inactive" predicate
         (`RemoveType IS NULL`) is either redundant
         (state=permanent / state=active already pin
         `RemoveType IS NULL`) or contradictory (state=expired /
         state=unbanned ask for the OPPOSITE rowset). The page
         handler already drops the predicate when state is set;
         hiding the toggle keeps the two surfaces from visually
         competing too. *}
      {if $active_state == ''}
      <a class="btn btn--secondary btn--sm"
         role="button"
         aria-pressed="{if $hidetext == 'Show'}true{else}false{/if}"
         href="index.php?p=banlist&hideinactive={if $hidetext == 'Hide'}true{else}false{/if}{$searchlink}"
         data-testid="toggle-hide-inactive">{$hidetext} inactive</a>
      {/if}
      {has_access flag=$smarty.const.ADMIN_ADD_BAN}
      <a class="btn btn--primary btn--sm" href="index.php?p=admin&c=bans">Add ban</a>
      {/has_access}
    </div>
  </div>

  {* -- Sticky filter bar. The chip group is the testability hook
       table-locked surface for the marquee page (filter-chip-* test
       ids). The form's `method=get action=index.php` mirrors the
       legacy advanced search wiring, so a no-JS browser still gets a
       working server-rendered search; banlist.js layers chip-state
       URL sync on top of it.

       #1226: parity with the public commslist filter bar — server +
       time selects + Apply button alongside the existing search
       field. The server `<option value="<sid>">` round-trips into
       page.banlist.php's $publicFilterClauses (BA.sid = ?) and
       composes with both the search box and the legacy advSearch
       URL shim. The time options map to "Last N days" via the
       BA.created >= ? predicate (mirrors page.commslist.php's
       intent; the legacy advType=date shim stays the kitchen-sink
       power-user tool in box_admin_bans_search.tpl). *}
  <form method="get" action="index.php" id="banlist-filters" style="position:sticky;top:3.5rem;z-index:20;background:var(--bg-page);padding:0.75rem 0;border-bottom:1px solid var(--border)">
    <input type="hidden" name="p" value="banlist">
    <div class="flex gap-3" style="flex-wrap:wrap">
      <div style="flex:1;min-width:14rem;max-width:24rem;position:relative">
        <input class="input" type="search" name="searchText" data-testid="bans-search"
          value="{$filters.search|escape}"
          placeholder="Player, SteamID, or IP&hellip;" aria-label="Search bans">
      </div>
      <select class="select" name="server" style="width:auto;min-width:11rem" data-testid="bans-server-filter" aria-label="Filter by server">
        <option value="">All servers</option>
        {foreach $server_list as $s}
          <option value="{$s.sid}" {if $filters.server == $s.sid}selected{/if}>{$s.name|escape}</option>
        {/foreach}
      </select>
      <select class="select" name="time" style="width:auto" data-testid="bans-time-filter" aria-label="Filter by time range">
        <option value="">All time</option>
        <option value="1d"  {if $filters.time == '1d'}selected{/if}>Today</option>
        <option value="7d"  {if $filters.time == '7d'}selected{/if}>Last 7 days</option>
        <option value="30d" {if $filters.time == '30d'}selected{/if}>Last 30 days</option>
      </select>
      <button class="btn btn--secondary btn--sm" type="submit" data-testid="bans-filter-apply">
        <i data-lucide="filter"></i> Apply
      </button>
    </div>

    {* #1352: chip strip is now server-side. Each chip is a real
       anchor (not a `<button type="button">` with JS row-hiding)
       so the click navigates to `?p=banlist&state=<slug>`, the
       page handler narrows the SQL rowset, and pagination /
       no-JS browsers / shared deep links all behave correctly.
       Pre-#1352 this strip was a vanilla-JS row-hide layer
       (`web/scripts/banlist.js applyStateFilter`) that flipped
       `display: none` on rows whose `data-state` didn't match —
       which only worked on the rowset the server already
       returned. With 10k bans of which 50 were unbanned, page
       1 of `?state=unbanned` rendered 30 invisible rows; the
       chip read as broken. The new shape is server-side parity
       with the commslist `?state=active` chip (#1274).

       Each chip preserves the OTHER active filters via
       `$chip_base_link` (search, server, time, advSearch+advType
       — but NOT state, since the new chip's state is what we're
       swapping in). The active chip gets `aria-current="true"`
       AND `data-active="true"` server-rendered so testids anchor
       on the contract and screen-readers announce the active state
       without a JS round-trip. NOTE: `aria-current` (not
       `aria-pressed`) is the canonical ARIA attribute for "active
       item in a navigation set" on `<a>` elements; axe rejects
       `aria-pressed` on anchors as `aria-allowed-attr` because
       only role=button supports the toggle semantics it implies.
       The sibling commslist chip strip uses `<button>` so it can
       keep `aria-pressed`; the banlist's anchor shape exists so
       no-JS browsers can navigate. *}
    <div class="flex items-center gap-2 mt-2 scroll-x" role="group" aria-label="Filter by status">
      <a class="chip"
         href="index.php?p=banlist{$chip_base_link}"
         data-state-filter=""
         data-testid="filter-chip-all"
         data-active="{if $active_state == ''}true{else}false{/if}"
         aria-current="{if $active_state == ''}true{else}false{/if}">All</a>
      <a class="chip"
         href="index.php?p=banlist{$chip_base_link}&state=permanent"
         data-state-filter="permanent"
         data-testid="filter-chip-permanent"
         data-active="{if $active_state == 'permanent'}true{else}false{/if}"
         aria-current="{if $active_state == 'permanent'}true{else}false{/if}">
        <span class="chip__dot" style="background:#ef4444"></span>Permanent
      </a>
      <a class="chip"
         href="index.php?p=banlist{$chip_base_link}&state=active"
         data-state-filter="active"
         data-testid="filter-chip-active"
         data-active="{if $active_state == 'active'}true{else}false{/if}"
         aria-current="{if $active_state == 'active'}true{else}false{/if}">
        <span class="chip__dot" style="background:#f59e0b"></span>Active
      </a>
      <a class="chip"
         href="index.php?p=banlist{$chip_base_link}&state=expired"
         data-state-filter="expired"
         data-testid="filter-chip-expired"
         data-active="{if $active_state == 'expired'}true{else}false{/if}"
         aria-current="{if $active_state == 'expired'}true{else}false{/if}">
        <span class="chip__dot" style="background:var(--zinc-300)"></span>Expired
      </a>
      <a class="chip"
         href="index.php?p=banlist{$chip_base_link}&state=unbanned"
         data-state-filter="unbanned"
         data-testid="filter-chip-unbanned"
         data-active="{if $active_state == 'unbanned'}true{else}false{/if}"
         aria-current="{if $active_state == 'unbanned'}true{else}false{/if}">
        <span class="chip__dot" style="background:#10b981"></span>Unbanned
      </a>
    </div>
  </form>

  {* -- #1315: advanced-search disclosure -------------------------------
       v1.x always-rendered the multi-criterion advanced-search form
       (nickname / banid / SteamID / IP / reason / date range / length
       op / ban type / admin / server / comment) above the row table.
       The v2.0 redesign dropped the include and left the simple sticky
       filter bar as the only UI surface — power users discovered the
       legacy `?advSearch=…&advType=…` URL shim by url-spelunking, not
       by having a form to submit. This disclosure restores the form
       as a default-collapsed `<details>` so the unfiltered list still
       reaches above the fold; on a post-submit paint
       (`$is_advanced_search_open`) it auto-opens so the form chrome
       and the Clear-filters affordance stay visible while the user
       iterates. Reuses the `.filters-details` shape #1303 introduced
       on `box_admin_admins_search.tpl`. The included partial is
       paired with `Sbpp\View\AdminBansSearchView`; the page handler
       (`web/pages/admin.bans.search.php`) populates its props
       independently of `BanListView`. *}
  <details class="card filters-details"
           data-testid="banlist-advsearch-disclosure"
           {if $is_advanced_search_open}open{/if}>
    <summary class="filters-details__summary"
             data-testid="banlist-advsearch-toggle"
             aria-controls="banlist-advsearch-body">
      <span class="filters-details__summary-label">
        <i data-lucide="filter" style="width:14px;height:14px"></i>
        <span>Advanced search</span>
        {if $is_advanced_search_open}
          <span class="filters-details__count" data-testid="banlist-advsearch-active">
            &middot; 1 active
          </span>
        {/if}
      </span>
      <i data-lucide="chevron-down" class="filters-details__chevron" style="width:14px;height:14px"></i>
    </summary>
    <div id="banlist-advsearch-body" class="filters-details__body">
      {load_template file="admin.bans.search"}
    </div>
  </details>

  {* -- Desktop table (>=769px). Below that, .table is hidden by
       theme.css and .ban-cards takes over.

       The `.table-scroll` wrapper inside the card is the PUB-1
       (#1207) horizontal-overflow fallback: at 1024-1100px (after
       the sidebar collapses) the natural column widths can exceed
       the panel; rather than clipping STATUS / row-actions, the
       table scrolls horizontally inside the card's rounded frame.
       Pair with the `col-*` classes on each cell which pin the
       always-short columns to `white-space: nowrap` so we don't
       waste a scroll on a 3-line wrapped date. *}
  <div class="card" style="overflow:hidden">
    <div class="table-scroll">
    <table class="table">
      <thead>
        {* Column tier classes (`.col-tier-2` / `.col-tier-3`) are paired
           rules in `theme.css` next to `.table-scroll`. Tier-2 (Server,
           Admin) hides at <=1100px; tier-3 (IP, Length, Banned) at
           <=900px. Tier-1 (Player, SteamID, Reason, Status, Actions)
           always renders so the row stays useful at every desktop
           viewport without horizontal scroll inside `.table-scroll`. *}
        <tr>
          <th scope="col">Player</th>
          <th scope="col">SteamID</th>
          {* #1302: IP column gated on `banlist.hideplayerips` + admin
             status (`hideplayerips` is `Config::getBool('banlist.hideplayerips')
             && !$userbank->is_admin()` — admins always see IPs, non-admins
             see them only when the setting is off). Mirrors the existing
             `{if !$hideadminname}` admin-name guard above; v1.x had this
             column gated on `is_admin()`, the v2.0 redesign dropped it. *}
          {if !$hideplayerips}<th scope="col" class="col-ip col-tier-3">IP</th>{/if}
          <th scope="col">Reason</th>
          <th scope="col" class="col-tier-2">Server</th>
          {if !$hideadminname}<th scope="col" class="col-admin col-tier-2">Admin</th>{/if}
          <th scope="col" class="col-length col-tier-3">Length</th>
          <th scope="col" class="col-banned col-tier-3">Banned</th>
          <th scope="col" class="col-status">Status</th>
          <th scope="col" class="col-actions" aria-label="Row actions"></th>
        </tr>
      </thead>
      <tbody>
        {foreach from=$ban_list item=ban}
        <tr class="ban-row ban-row--{$ban.state}" data-state="{$ban.state}" data-id="{$ban.bid}" data-testid="ban-row">
          <td>
            <div class="flex items-center gap-3" style="min-width:0">
              {* Inlined avatar markup; the handoff partial is
                 design-only and Phase B can't add files under
                 themes/sbpp2026/{css,js,core}. Initials + hue come
                 from $ban.avatar_initials / $ban.avatar_hue,
                 precomputed in page.banlist.php so the template
                 doesn't depend on Smarty's `%` operator parsing. *}
              <span class="avatar" style="width:28px;height:28px;background:hsl({$ban.avatar_hue} 55% 45%);font-size:10px" aria-hidden="true">{$ban.avatar_initials|escape}</span>
              {* No `onclick="event.stopPropagation()"` here even though
                 the row's action buttons carry it. theme.js wires the
                 drawer via a delegated `document.addEventListener('click',
                 …)` on `[data-drawer-href]`; stopping bubble at the
                 anchor would silently fall back to native href
                 navigation and bypass the drawer entirely (#1124 Slice 6
                 found this — tests asserted `[data-drawer-open=true]`
                 after a row click, the page just navigated to the legacy
                 `?id=N` href instead). The mobile `.ban-cards` branch
                 below already follows this no-stopPropagation shape. *}
              <a class="font-medium truncate" href="?p=banlist&amp;id={$ban.bid}" data-drawer-href="?p=banlist&amp;c=details&amp;id={$ban.bid}" data-testid="drawer-trigger">{if empty($ban.name)}<i class="text-faint">no nickname</i>{else}{$ban.name|escape}{/if}</a>
            </div>
            {* #BANLIST-COMMENTS: inline <details> disclosure restoring
               the per-row comment visibility v1.x shipped (the
               mooaccordion sliding-panel surface that was deleted with
               sourcebans.js at #1123 D1). The v2.0 redesign moved the
               comments off the page and into the right-side drawer
               (Overview pane → "Comments" section), but the page-level
               affordance was a silent <span>[N]</span> with no click
               handler, so users reading the row had no on-page way to
               see *what* the comments said and no visual cue that the
               drawer was their new home. The disclosure restores
               on-page comment text via native <details> semantics: the
               <summary> is the clickable count badge, the body lists
               each comment inline so readers don't have to leave the
               row. The drawer's comments section in `theme.js`
               `renderOverviewPane` continues to render the SAME data
               via `api_bans_detail` for users who open the player
               drawer (clicking the player-name anchor above) — the two
               surfaces share the gating contract
               (`Config::getBool('config.enablepubliccomments') ||
               $userbank->is_admin()`).

               The disclosure stays default-collapsed so a banlist with
               comment-heavy rows doesn't blow out the table height on
               first paint; the count is visible inside the summary so
               admins can scan the list at a glance. *}
            {if $view_comments && $ban.commentdata != "None" && $ban.commentdata|@count > 0}
            <details class="ban-comments-inline"
                     data-testid="ban-comments-inline"
                     data-bid="{$ban.bid}">
              <summary class="ban-comments-inline__summary"
                       data-testid="ban-comments-toggle"
                       title="{$ban.commentdata|@count} comment{if $ban.commentdata|@count != 1}s{/if} on this ban">
                <i data-lucide="message-square-text" style="width:11px;height:11px" aria-hidden="true"></i>
                <span class="tabular-nums">{$ban.commentdata|@count}</span>
                <span class="ban-comments-inline__label">comment{if $ban.commentdata|@count != 1}s{/if}</span>
              </summary>
              <ul class="ban-comments-inline__list" data-testid="ban-comments-list">
                {foreach from=$ban.commentdata item=com}
                <li class="ban-comments-inline__item" data-testid="ban-comment-item">
                  <div class="ban-comments-inline__meta">
                    {if !empty($com.comname)}
                      <strong>{$com.comname|escape}</strong>
                    {else}
                      <i class="text-faint">deleted admin</i>
                    {/if}
                    <span class="text-faint">&middot;</span>
                    <span class="text-xs text-faint tabular-nums">{$com.added}</span>
                  </div>
                  {* nofilter: $com.commenttxt is server-built HTML produced by encodePreservingBr (htmlspecialchars per text segment, only `<br/>` survives) plus a URL-wrap regex that wraps already-escaped URLs in `<a>` tags — see page.banlist.php $commentres loop. Same provenance + safety as the existing comment-edit-mode block at the top of this template. *}
                  <div class="ban-comments-inline__text" data-testid="ban-comment-text">{$com.commenttxt nofilter}</div>
                  {if !empty($com.edittime)}
                  <div class="ban-comments-inline__edit text-xs text-faint">last edit {$com.edittime} by {if !empty($com.editname)}{$com.editname|escape}{else}<i>deleted admin</i>{/if}</div>
                  {/if}
                </li>
                {/foreach}
              </ul>
            </details>
            {/if}
          </td>
          <td class="font-mono text-xs text-muted">
            {if empty($ban.steam)}
              <i class="text-faint">none</i>
            {else}
              {$ban.steam|escape}
            {/if}
          </td>
          {if !$hideplayerips}
          <td class="col-ip col-tier-3 font-mono text-xs text-muted" data-testid="ban-ip">
            {if $ban.ban_ip_raw == ''}
              <i class="text-faint">none</i>
            {else}
              {$ban.ban_ip_raw|escape}
            {/if}
          </td>
          {/if}
          {* `title=` carries the full reason text so a hover / long-press
             surfaces it in the browser's native tooltip — the cell
             itself is `truncate`'d at `max-width:18rem` so the row
             stays one line tall (issue 5: "Unban/Ban reason are
             truncated"). The conditional is just to avoid emitting
             `title=""` for empty-reason rows; the visible body still
             reads "no reason" via the existing branch. *}
          <td class="text-muted truncate" style="max-width:18rem"
              {if !empty($ban.reason)}title="{$ban.reason|escape}"{/if}>
            {if empty($ban.reason)}<i class="text-faint">no reason</i>{else}{$ban.reason|escape}{/if}
            {* #1315: surface the unban-reason / removed-by line below the
               truncated reason cell when the row was lifted by an admin
               (state == 'unbanned' — natural-expiry rows have no admin
               involvement). Mirrors the v1.x sliding-panel surface so
               admins / players don't have to open the drawer to see
               *who* lifted it and *why*. Gated on $hideadminname so
               anonymous viewers under a hidden-admins config don't get
               the admin name leaked here either. *}
            {if $ban.state == 'unbanned' && !$hideadminname && (!empty($ban.ureason) || !empty($ban.removedby))}
              <div class="text-xs text-faint mt-1" data-testid="ban-unban-meta">
                {if !empty($ban.removedby)}
                  Unbanned by <span class="font-medium">{$ban.removedby|escape}</span>{if !empty($ban.ureason)}: {/if}
                {/if}
                {if !empty($ban.ureason)}
                  <span data-testid="ban-unban-reason" title="{$ban.ureason|escape}">{$ban.ureason|escape}</span>
                {/if}
              </div>
            {/if}
          </td>
          <td class="col-tier-2 text-muted truncate" style="max-width:12rem" title="{$ban.sname|escape}">{$ban.sname|escape}</td>
          {if !$hideadminname}
          <td class="col-admin col-tier-2 text-muted">
            {if empty($ban.aname)}<i class="text-faint">deleted</i>{else}{$ban.aname|escape}{/if}
          </td>
          {/if}
          <td class="col-length col-tier-3 tabular-nums text-muted">{if $ban.length == 0}Permanent{else}{$ban.length_human|escape}{/if}</td>
          <td class="col-banned col-tier-3 text-muted text-xs"><time datetime="{$ban.banned_iso}">{$ban.banned_human|escape}</time></td>
          <td class="col-status">
            {assign var=_pill_label value=$ban.state|capitalize}
            <span class="pill pill--{$ban.state}">{$_pill_label|escape}</span>
          </td>
          <td class="col-actions">
            {* #1207 ADM-5 + (this PR): banlist row affordances now mirror
               the commslist row chrome — Lucide icon + visible text label
               inside `.btn--ghost` / `.btn--secondary btn--sm` pills
               (`page_comms.tpl` lines 258-311 is the canonical reference).
               The bare HTML-entity glyphs the v2.0 cutover shipped
               (`&#9998;` ✎ / `&#10003;` ✓ / `&#8634;` ↺ / `&#128203;` 📋)
               are gone — they read as broken icons / a different app
               next to commslist's affordance set, and the icon-only
               buttons gave no SR / hover affordance. Edit / Unban / Re-apply
               / Copy / Remove are gated identically to the v2.0 shape;
               only the chrome moved. *}
            <div class="row-actions">
              {if $view_bans}
                {if $ban.can_edit_ban && $ban.state != 'unbanned'}
                <a class="btn btn--ghost btn--sm" data-testid="row-action-edit"
                   href="index.php?p=admin&amp;c=bans&amp;o=edit&amp;id={$ban.bid}&amp;key={$admin_postkey|escape}"
                   onclick="event.stopPropagation()">
                    <i data-lucide="pencil" style="width:13px;height:13px"></i>
                    Edit
                </a>
                {/if}
                {if $ban.can_unban && $ban.state != 'unbanned' && $ban.state != 'expired'}
                {* #1301: button + data-action wires to the inline page-tail
                   JS below, which prompts for an unban reason via the
                   `#bans-unban-dialog` <dialog> and calls
                   Actions.BansUnban with the trimmed reason. The
                   data-fallback-href is the legacy GET URL the no-JS
                   path lands on; the page handler now also rejects an
                   empty `ureason` there so a hand-crafted URL can't
                   slip through the back door either.

                   No `onclick="event.stopPropagation()"` here: the
                   page-tail JS opens the dialog via a
                   `document.addEventListener('click', …)` delegate
                   listening on the bubble phase. A bubble-stop here
                   silently swallows the click. The drawer trigger is
                   the player-name `<a data-drawer-href>` in column 1,
                   a sibling of `<td class="col-actions">`, so the
                   bubbled click has nothing to confuse on the drawer
                   side. *}
                <button type="button"
                        class="btn btn--secondary btn--sm"
                        data-testid="row-action-unban"
                        data-action="bans-unban"
                        data-bid="{$ban.bid}"
                        data-name="{$ban.name|escape}"
                        data-fallback-href="index.php?p=banlist&amp;a=unban&amp;id={$ban.bid}&amp;key={$admin_postkey|escape}">
                    <i data-lucide="check" style="width:13px;height:13px"></i>
                    Unban
                </button>
                {/if}
                {* #1315: Re-apply affordance for expired / unbanned rows.
                   Mirrors the commslist row's existing Re-apply anchor
                   (`page_comms.tpl` lines 286-298 desktop, 448-454
                   mobile). Routes to the admin add-ban form's
                   `?rebanid=` smart-default block, which
                   `web/api/handlers/bans.php::api_bans_prepare_reban`
                   pre-populates with the original ban's parameters.
                   Gated on $can_add_ban so the affordance only renders
                   for admins who can act on it. *}
                {if $can_add_ban && ($ban.state == 'expired' || $ban.state == 'unbanned')}
                <a class="btn btn--secondary btn--sm" data-testid="row-action-reapply"
                   href="index.php?p=admin&amp;c=bans&amp;section=add-ban&amp;rebanid={$ban.bid}&amp;key={$admin_postkey|escape}"
                   onclick="event.stopPropagation()">
                    <i data-lucide="rotate-ccw" style="width:13px;height:13px"></i>
                    Re-apply
                </a>
                {/if}
              {/if}
              {if !empty($ban.steam)}
              {* #1308: NO `onclick="event.stopPropagation()"` here. The
                 sibling row-action <a> tags carry it as defensive copy-paste,
                 but they survive on native href navigation regardless. This
                 <button>'s ONLY wiring is the document-level [data-copy]
                 click delegate in theme.js — stopPropagation kills it
                 silently (no toast, no clipboard write, no console error).
                 The desktop row's drawer trigger is the player-name anchor
                 in column 1 (data-drawer-href), not a row-level delegate,
                 so a bubbling click here has nothing to confuse. *}
              <button class="btn btn--ghost btn--sm" type="button"
                      data-copy="{$ban.steam|escape}"
                      data-testid="row-action-copy-steam"
                      aria-label="Copy SteamID"
                      title="Copy SteamID">
                  <i data-lucide="copy" style="width:13px;height:13px"></i>
                  Copy
              </button>
              {/if}
              {if $ban.can_delete_ban}
              {* Hard-delete affordance. No JSON `bans.delete` action
                 exists yet — the canonical write path is the legacy
                 GET handler `?p=banlist&a=delete&id=…&key=…` at the
                 top of page.banlist.php (DeleteBan-gated, RCON
                 cleanup + DELETE FROM :prefix_bans). The `data-action`
                 hook routes through the inline page-tail JS's
                 `bans-delete` branch, which `confirm()`-prompts and
                 then navigates to `data-fallback-href`. Mirror of
                 commslist's Remove button (page_comms.tpl line 299,
                 `comms-delete` data-action) for visual + interaction
                 parity. *}
              <button type="button"
                      class="btn btn--ghost btn--sm"
                      data-testid="row-action-delete"
                      data-action="bans-delete"
                      data-bid="{$ban.bid}"
                      data-name="{$ban.name|escape}"
                      data-fallback-href="index.php?p=banlist&amp;a=delete&amp;id={$ban.bid}&amp;key={$admin_postkey|escape}"
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
           `$is_filtered` is true whenever the request carries a search
           term, an advSearch chip, or the hide-inactive toggle — see
           page.banlist.php for the predicate. With zero rows AND no
           filter active we fall through to the first-run shape (CTA
           gated on `can_add_ban`). With a filter, the CTA stays
           "Clear filters" and `data-filtered="true"` so tests can
           disambiguate. *}
        <tr>
          {* colspan accounts for the maximum render: Player, SteamID, IP
             (gated on `!$hideplayerips`), Reason, Server, Admin (gated on
             `!$hideadminname`), Length, Banned, Status, Actions = 10. The
             empty-state cell stretches across whichever subset of columns
             is currently visible — the wider colspan is harmless when
             columns are conditionally hidden. *}
          <td colspan="10" style="padding:0">
            {if $is_filtered}
            <div class="empty-state" data-testid="banlist-empty" data-filtered="true">
              <span class="empty-state__icon" aria-hidden="true">
                <i data-lucide="search-x" style="width:18px;height:18px"></i>
              </span>
              <h2 class="empty-state__title">No bans match those filters</h2>
              <p class="empty-state__body">Try a different search term or clear the active filters to see every recorded ban.</p>
              <div class="empty-state__actions">
                <a class="btn btn--secondary btn--sm" href="?p=banlist" data-testid="banlist-empty-clear">
                  <i data-lucide="x" style="width:13px;height:13px"></i>
                  Clear filters
                </a>
              </div>
            </div>
            {else}
            <div class="empty-state" data-testid="banlist-empty" data-filtered="false">
              <span class="empty-state__icon" aria-hidden="true">
                <i data-lucide="ban" style="width:18px;height:18px"></i>
              </span>
              <h2 class="empty-state__title">No bans recorded yet</h2>
              <p class="empty-state__body">Enforcement actions will show up here as soon as admins start moderating.</p>
              {if $can_add_ban}
              <div class="empty-state__actions">
                <a class="btn btn--primary btn--sm" href="?p=admin&amp;c=bans" data-testid="banlist-empty-add">
                  <i data-lucide="plus" style="width:13px;height:13px"></i>
                  Add a ban
                </a>
              </div>
              {/if}
            </div>
            {/if}
          </td>
        </tr>
        {/foreach}
      </tbody>
    </table>
    </div>

    {* -- Mobile card list (<769px). Two-sibling shape mirroring
         `page_comms.tpl` (#1207 ADM-5): a clickable
         `.ban-card__summary` anchor that opens the player detail
         drawer (`data-drawer-href`) and a sibling
         `.ban-card__actions` row of buttons (Edit / Unban / Re-apply
         / Copy / Remove) so every desktop affordance is reachable
         on phone too. The previous shape wrapped the whole card in
         a single `<a>`, which (a) had nowhere to put `<button>`
         actions without producing nested-interactive HTML and (b)
         hid Edit / Unban / Re-apply / Copy / Remove on mobile
         entirely. The summary anchor keeps `data-testid="drawer-trigger"`
         so the existing responsive spec
         (`web/tests/e2e/specs/responsive/banlist.spec.ts`) still
         finds the seeded card via the testid contract. *}
    <div class="ban-cards">
      {foreach from=$ban_list item=ban}
      <div class="ban-row ban-row--{$ban.state} ban-card"
           style="border-bottom:1px solid var(--border)"
           data-testid="ban-card"
           data-id="{$ban.bid}"
           data-state="{$ban.state}">
        <a class="ban-card__summary flex items-center gap-3 p-4"
           style="text-decoration:none;color:var(--text)"
           href="?p=banlist&amp;id={$ban.bid}"
           data-drawer-href="?p=banlist&amp;c=details&amp;id={$ban.bid}"
           data-testid="drawer-trigger">
          <span class="avatar" style="width:36px;height:36px;background:hsl({$ban.avatar_hue} 55% 45%);font-size:13px" aria-hidden="true">{$ban.avatar_initials|escape}</span>
          <div style="flex:1;min-width:0">
            <div class="flex items-center gap-2">
              <span class="font-medium text-sm truncate">{if empty($ban.name)}<i class="text-faint">no nickname</i>{else}{$ban.name|escape}{/if}</span>
              {assign var=_m_pill_label value=$ban.state|capitalize}
              <span class="pill pill--{$ban.state}">{$_m_pill_label|escape}</span>
            </div>
            {* `title=` carries the full (un-truncated) reason +
               length so a long-press / hover surfaces the full
               text — the inline copy is .truncate'd to one line so
               the summary row stays a fixed height (issue 5). *}
            <div class="text-xs text-muted truncate" style="margin-top:0.125rem"
                 title="{if empty($ban.reason)}no reason{else}{$ban.reason|escape}{/if} &middot; {if $ban.length == 0}Permanent{else}{$ban.length_human|escape}{/if}">{if empty($ban.reason)}no reason{else}{$ban.reason|escape}{/if} &middot; {if $ban.length == 0}Permanent{else}{$ban.length_human|escape}{/if}</div>
            <div class="font-mono text-xs text-faint truncate" style="margin-top:0.125rem">{if empty($ban.steam)}&mdash;{else}{$ban.steam|escape}{/if}</div>
            {* #1302: IP line on the mobile card mirrors the desktop IP
               column above. Same `{if !$hideplayerips}` gate so an
               admin sees IPs at-a-glance on phone too; non-admins under
               `banlist.hideplayerips` get the same suppression contract. *}
            {if !$hideplayerips && $ban.ban_ip_raw != ''}
            <div class="font-mono text-xs text-faint truncate" style="margin-top:0.125rem" data-testid="ban-ip-mobile">{$ban.ban_ip_raw|escape}</div>
            {/if}
            {* #1315: mobile mirror of the desktop `ban-unban-meta` line —
               surface unban-by + ureason on rows the admin lifted so
               players don't have to open the drawer to see who unbanned
               them and why. Gated on $hideadminname for parity with the
               desktop branch. *}
            {if $ban.state == 'unbanned' && !$hideadminname && (!empty($ban.ureason) || !empty($ban.removedby))}
              <div class="text-xs text-faint truncate" style="margin-top:0.125rem" data-testid="ban-unban-meta-mobile"
                   title="{if !empty($ban.removedby)}Unbanned by {$ban.removedby|escape}{if !empty($ban.ureason)}: {/if}{/if}{if !empty($ban.ureason)}{$ban.ureason|escape}{/if}">
                {if !empty($ban.removedby)}Unbanned by {$ban.removedby|escape}{if !empty($ban.ureason)}: {/if}{/if}
                {if !empty($ban.ureason)}{$ban.ureason|escape}{/if}
              </div>
            {/if}
          </div>
          <span class="text-faint" aria-hidden="true">&rsaquo;</span>
        </a>
        {if $view_bans || !empty($ban.steam) || $ban.can_delete_ban}
        <div class="row-actions ban-card__actions">
          {if $view_bans}
            {if $ban.can_edit_ban && $ban.state != 'unbanned'}
            <a class="btn btn--ghost btn--sm" data-testid="row-action-edit-mobile"
               href="index.php?p=admin&amp;c=bans&amp;o=edit&amp;id={$ban.bid}&amp;key={$admin_postkey|escape}">
                <i data-lucide="pencil" style="width:13px;height:13px"></i>
                Edit
            </a>
            {/if}
            {if $ban.can_unban && $ban.state != 'unbanned' && $ban.state != 'expired'}
            <button type="button"
                    class="btn btn--secondary btn--sm"
                    data-testid="row-action-unban-mobile"
                    data-action="bans-unban"
                    data-bid="{$ban.bid}"
                    data-name="{$ban.name|escape}"
                    data-fallback-href="index.php?p=banlist&amp;a=unban&amp;id={$ban.bid}&amp;key={$admin_postkey|escape}">
                <i data-lucide="check" style="width:13px;height:13px"></i>
                Unban
            </button>
            {/if}
            {if $can_add_ban && ($ban.state == 'expired' || $ban.state == 'unbanned')}
            <a class="btn btn--secondary btn--sm" data-testid="row-action-reapply-mobile"
               href="index.php?p=admin&amp;c=bans&amp;section=add-ban&amp;rebanid={$ban.bid}&amp;key={$admin_postkey|escape}">
                <i data-lucide="rotate-ccw" style="width:13px;height:13px"></i>
                Re-apply
            </a>
            {/if}
          {/if}
          {if !empty($ban.steam)}
          <button class="btn btn--ghost btn--sm" type="button"
                  data-copy="{$ban.steam|escape}"
                  data-testid="row-action-copy-steam-mobile"
                  aria-label="Copy SteamID"
                  title="Copy SteamID">
              <i data-lucide="copy" style="width:13px;height:13px"></i>
              Copy
          </button>
          {/if}
          {if $ban.can_delete_ban}
          <button type="button"
                  class="btn btn--ghost btn--sm"
                  data-testid="row-action-delete-mobile"
                  data-action="bans-delete"
                  data-bid="{$ban.bid}"
                  data-name="{$ban.name|escape}"
                  data-fallback-href="index.php?p=banlist&amp;a=delete&amp;id={$ban.bid}&amp;key={$admin_postkey|escape}"
                  style="color:var(--danger)">
              <i data-lucide="trash-2" style="width:13px;height:13px"></i>
              Remove
          </button>
          {/if}
          {* #BANLIST-COMMENTS mobile mirror — at-a-glance count indicator
             only (NOT a clickable disclosure). The mobile card wraps
             everything in a single `<a data-testid="drawer-trigger">`
             so a nested <details> would be invalid HTML (interactive
             content can't contain interactive content). The drawer is
             the canonical mobile detail view per the existing comms-
             list pattern (see AGENTS.md "Reapply" mobile-deferred
             note); tapping the card opens the drawer which renders the
             same comments under the Overview pane. The count + icon
             here gives users the same at-a-glance signal desktop has. *}
          {if $view_comments && $ban.commentdata != "None" && $ban.commentdata|@count > 0}
          <div class="text-xs text-faint truncate" style="margin-top:0.125rem;display:flex;align-items:center;gap:0.25rem" data-testid="ban-comments-count-mobile">
            <i data-lucide="message-square-text" style="width:11px;height:11px" aria-hidden="true"></i>
            <span class="tabular-nums">{$ban.commentdata|@count}</span>
            <span>comment{if $ban.commentdata|@count != 1}s{/if}</span>
          </div>
          {/if}
        </div>
        {/if}
      </div>
      {foreachelse}
      {* Mobile mirror of the desktop empty above (first-run vs filtered). *}
      {if $is_filtered}
      <div class="empty-state" data-testid="banlist-empty-mobile" data-filtered="true">
        <span class="empty-state__icon" aria-hidden="true">
          <i data-lucide="search-x" style="width:18px;height:18px"></i>
        </span>
        <h2 class="empty-state__title">No bans match those filters</h2>
        <p class="empty-state__body">Try a different search term or clear the active filters.</p>
        <div class="empty-state__actions">
          <a class="btn btn--secondary btn--sm" href="?p=banlist">
            <i data-lucide="x" style="width:13px;height:13px"></i>
            Clear filters
          </a>
        </div>
      </div>
      {else}
      <div class="empty-state" data-testid="banlist-empty-mobile" data-filtered="false">
        <span class="empty-state__icon" aria-hidden="true">
          <i data-lucide="ban" style="width:18px;height:18px"></i>
        </span>
        <h2 class="empty-state__title">No bans recorded yet</h2>
        <p class="empty-state__body">Enforcement actions will show up here as soon as admins start moderating.</p>
        {if $can_add_ban}
        <div class="empty-state__actions">
          <a class="btn btn--primary btn--sm" href="?p=admin&amp;c=bans">
            <i data-lucide="plus" style="width:13px;height:13px"></i>
            Add a ban
          </a>
        </div>
        {/if}
      </div>
      {/if}
      {/foreach}
    </div>
  </div>

  {* -- Pagination. The prev/next anchors carry data-testid="page-prev"
       and data-testid="page-next" — the page handler (page.banlist.php)
       embeds them inside $ban_nav so the marquee page's E2E hooks
       (#1123 issue "Testability hooks" table) can address them
       without a per-theme view-model detour. The page-jump <select>
       embedded in $ban_nav is wired with vanilla `window.location`
       JS (page.banlist.php #1123 B2 rebuild), so it works in this
       theme without requiring sourcebans.js (deleted in #1123 D1).

       Bulk actions ($general_unban / $can_delete) are intentionally
       not surfaced on the public ban list under the new theme — the
       admin bans page (#1123 B13) is the canonical home for bulk
       unban/delete and ships per-row checkboxes there. The View
       still carries the booleans because the legacy default theme
       renders the bulk action UI on this page until #1123 D1 cuts
       over; the `{if false}…{/if}` manifest at EOF marks them as
       referenced for the dual-theme PHPStan matrix. *}
  {if !empty($ban_nav)}
  <div class="card">
    <div class="card__body">
      <nav class="text-xs text-muted" aria-label="Ban list pagination">
        {* nofilter: $ban_nav is server-built HTML in page.banlist.php — pagination label as numeric strings, two prev/next anchors with data-testid attrs whose URLs are urlencode()'d $_GET round-trips, plus a vanilla-JS page-jump <select> whose `window.location` template is htmlspecialchars(addslashes($_GET['advSearch']/['advType']))'d (the 2-layer escape from #1113). No raw user input flows in unescaped. *}
        {$ban_nav nofilter}
      </nav>
    </div>
  </div>
  {/if}
</div>

{* -- Loading skeleton placeholder. Hidden by default; banlist.js
     toggles [data-loading=true] on the root above when the chip
     filter triggers a re-render. Kept here so the testid hook the
     marquee issue locks in (`[data-skeleton]`) is always present in
     the DOM, even when the live list is populated. *}
<div data-skeleton hidden aria-hidden="true">
  <div class="skel" style="height:2.5rem;margin-bottom:0.5rem"></div>
  <div class="skel" style="height:2.5rem;margin-bottom:0.5rem"></div>
  <div class="skel" style="height:2.5rem"></div>
</div>
{/if}

{* ============================================================
   #1301 — unban confirm + reason modal scaffold.

   v1.x had two safeguards before unbanning a player: a confirm modal
   and a non-empty unban-reason input. Both lived in the deleted
   `web/scripts/sourcebans.js` (UnbanBan()), and the v2.0 cutover
   left the row's affordance as a bare GET that silently accepted an
   empty `ureason`. This `<dialog>` is the v2.0-shape replacement,
   wired by the inline IIFE below. The dialog stays `hidden` on
   first paint so a JS failure leaves the unban affordance reachable
   only via the no-JS GET fallback (which itself now requires a
   non-empty reason, so the back door is closed too).
   ============================================================ *}
<dialog id="bans-unban-dialog"
        class="palette"
        aria-labelledby="bans-unban-dialog-title"
        data-testid="bans-unban-dialog"
        hidden
        style="max-width:32rem;width:90vw;padding:1.25rem;border-radius:0.75rem;border:1px solid var(--border)">
  <form method="dialog" data-testid="bans-unban-form">
    <h2 id="bans-unban-dialog-title" style="font-size:var(--fs-lg);font-weight:600;margin:0 0 0.25rem">Unban player</h2>
    <p class="text-sm text-muted m-0" style="margin-bottom:0.75rem">
      Why are you unbanning <strong data-testid="bans-unban-target">this player</strong>? This reason is recorded against the ban and surfaced in the audit log.
    </p>
    <label class="label" for="bans-unban-reason">Unban reason <span aria-hidden="true" style="color:var(--danger)">*</span></label>
    {* aria-required (not the native `required`) so assistive tech announces
       the field as required while the JS submit handler owns the empty-reason
       branch — `required` would let the browser block the form submit before
       our handler runs, swallowing the inline error UX (#1301). *}
    <textarea class="textarea"
              id="bans-unban-reason"
              data-testid="bans-unban-reason"
              rows="3"
              aria-required="true"
              maxlength="255"
              autocomplete="off"
              placeholder="Mistaken ban, appeal accepted, sentence served, &hellip;"></textarea>
    <p class="text-xs" data-testid="bans-unban-error" role="alert" hidden style="color:var(--danger);margin:0.25rem 0 0"></p>
    <div class="flex gap-2 mt-4" style="justify-content:flex-end">
      <button type="button" class="btn btn--secondary" data-testid="bans-unban-cancel" value="cancel">Cancel</button>
      <button type="submit" class="btn btn--primary" data-testid="bans-unban-submit" value="confirm">
        <i data-lucide="check" style="width:13px;height:13px"></i> Unban
      </button>
    </div>
  </form>
</dialog>

{* banlist.js wires both branches: the chip filter / copy buttons /
   skeleton hook on the listing branch, and the `#banlist-comment-form`
   submit -> sb.api.call(BansAddComment / BansEditComment) on the
   comment-edit branch. The IIFE feature-detects every element it
   touches, so loading it unconditionally is safe; loading it only on
   the listing branch silently broke comment save (no submit handler
   attached, native form submission to action-less URL no-ops). *}
<script src="./scripts/banlist.js" defer></script>

{* ============================================================
   #1301 — banlist row-action wiring (inline page-tail JS).

   Click delegation per anti-pattern: every Unban button in the rows
   above carries `data-action="bans-unban"` plus `data-bid` /
   `data-name` / `data-fallback-href`. The handler intercepts those
   clicks, opens the `#bans-unban-dialog` <dialog>, validates the
   reason on submit, calls `sb.api.call(Actions.BansUnban, …)`, and
   updates the row in-place on success (state pill flips to
   "unbanned", the unban button is removed, `window.SBPP.showToast`
   confirms). The fallback href is followed as a navigation when the
   JSON dispatcher is missing entirely (e.g. third-party theme with
   stripped api.js) — that's the legacy GET URL the
   `?p=banlist&a=unban` handler in page.banlist.php still serves
   (and which now also requires a non-empty `ureason`, see #1301
   guard there).

   No `// @ts-check` here because the file is rendered by Smarty;
   ts-check only runs against `.js` sources in `web/scripts`. The
   shape mirrors the inline handler in page_comms.tpl (#1207
   ADM-5/ADM-6 reference, extended in #1301 for the same modal).
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
     * Find every DOM node that mirrors the same ban id. The desktop
     * `<tr data-testid="ban-row">` and the mobile `<a class="ban-row">`
     * both render the same row, and theme.css's
     * `@media (max-width: 768px)` block hides the `<table>` via
     * `display: none` rather than removing the DOM.
     * @param {string} bid
     * @returns {Element[]}
     */
    function rowsForBid(bid) {
        var sel = '[data-testid="ban-row"][data-id="' + bid + '"], '
                + '.ban-row[data-id="' + bid + '"]';
        return Array.prototype.slice.call(document.querySelectorAll(sel));
    }

    /**
     * Update both copies of the row from `permanent|active` -> `unbanned`:
     *  - data-state on the wrapper.
     *  - ban-row--<state> class on the wrapper.
     *  - The status pill has its `pill--<state>` class swapped AND its
     *    visible label rewritten to "Unbanned".
     *  - The Unban button is removed (the tpl gates it on
     *    `state != 'unbanned' && state != 'expired'` so the post-render
     *    matches what the next page load would emit).
     * @param {Element} row
     * @returns {void}
     */
    function flipRowToUnbanned(row) {
        row.setAttribute('data-state', 'unbanned');
        row.classList.remove('ban-row--active', 'ban-row--permanent', 'ban-row--expired');
        row.classList.add('ban-row--unbanned');
        Array.prototype.forEach.call(row.querySelectorAll('.pill'), function (pill) {
            pill.classList.remove('pill--active', 'pill--permanent', 'pill--expired');
            pill.classList.add('pill--unbanned');
            // Walk children backwards to find the trailing text node so
            // we don't clobber any leading <i> icons.
            var lastText = null;
            for (var i = pill.childNodes.length - 1; i >= 0; i--) {
                var n = pill.childNodes[i];
                if (n.nodeType === 3) { lastText = n; break; }
            }
            var txt = (pill.textContent || '').trim();
            if (txt === 'Active' || txt === 'Permanent' || txt === 'Expired') {
                if (lastText) lastText.textContent = 'Unbanned';
                else pill.textContent = 'Unbanned';
            }
        });
        Array.prototype.forEach.call(
            row.querySelectorAll('[data-action="bans-unban"]'),
            function (btn) { if (btn.parentNode) btn.parentNode.removeChild(btn); }
        );
    }

    /** @returns {HTMLDialogElement|null} */
    function dialog() {
        return /** @type {HTMLDialogElement|null} */ (document.getElementById('bans-unban-dialog'));
    }
    /** @returns {HTMLTextAreaElement|null} */
    function reasonInput() {
        return /** @type {HTMLTextAreaElement|null} */ (document.getElementById('bans-unban-reason'));
    }
    /** @returns {HTMLElement|null} */
    function errorEl() {
        var d = dialog();
        return d ? /** @type {HTMLElement|null} */ (d.querySelector('[data-testid="bans-unban-error"]')) : null;
    }

    /** @param {string} msg */
    function showError(msg) {
        var e = errorEl();
        if (!e) return;
        e.textContent = msg;
        e.hidden = false;
    }
    function clearError() {
        var e = errorEl();
        if (!e) return;
        e.textContent = '';
        e.hidden = true;
    }

    /** @type {{bid: string, name: string, fallback: string}|null} */
    var pending = null;

    /**
     * Open the unban dialog for the supplied row data. We use the
     * native <dialog>.showModal() so the browser handles focus trapping
     * + ESC dismissal + the top-layer overlay for free.
     * @param {{bid: string, name: string, fallback: string}} ctx
     */
    function openUnbanDialog(ctx) {
        pending = ctx;
        var d = dialog();
        if (!d) {
            // The dialog markup is in the template above; if it's
            // missing the page is in an inconsistent state. Fall back
            // to the legacy GET path so the action still works (it
            // now also requires `ureason=` per #1301 — empty-reason
            // hand-edits get bounced server-side too).
            if (ctx.fallback) window.location.href = ctx.fallback;
            return;
        }
        var target = d.querySelector('[data-testid="bans-unban-target"]');
        if (target) target.textContent = ctx.name || ('ban #' + ctx.bid);
        var input = reasonInput();
        if (input) input.value = '';
        clearError();
        d.removeAttribute('hidden');
        try { d.showModal(); }
        catch (_e) { d.setAttribute('open', ''); }
        if (input) {
            try { input.focus(); } catch (_e) { /* focus may throw if hidden */ }
        }
    }

    function closeUnbanDialog() {
        var d = dialog();
        if (!d) return;
        try { d.close(); } catch (_e) { /* not opened modally */ }
        d.setAttribute('hidden', '');
        pending = null;
    }

    document.addEventListener('click', function (e) {
        var t = /** @type {Element|null} */ (e.target);
        if (!t || !t.closest) return;

        // Cancel button inside the dialog.
        if (t.closest('[data-testid="bans-unban-cancel"]')) {
            e.preventDefault();
            closeUnbanDialog();
            return;
        }

        var actionBtn = /** @type {HTMLElement|null} */ (t.closest('[data-action]'));
        if (!actionBtn) return;
        var act = actionBtn.getAttribute('data-action');
        if (act !== 'bans-unban' && act !== 'bans-delete') return;
        e.preventDefault();

        var bid = actionBtn.getAttribute('data-bid') || '';
        var name = actionBtn.getAttribute('data-name') || ('ban #' + bid);
        var fallback = actionBtn.getAttribute('data-fallback-href') || '';

        if (act === 'bans-delete') {
            // No JSON `bans.delete` action exists yet — the canonical
            // write path is the legacy GET handler at the top of
            // page.banlist.php (DeleteBan-gated, RCON cleanup +
            // hard-delete from :prefix_bans). Confirm + navigate is
            // the simplest mirror of commslist's Remove flow without
            // adding a new handler / snapshot / permission-matrix
            // entry — the in-place row removal lands on the next
            // page load.
            if (!fallback) return;
            if (!window.confirm('Permanently delete the ban for "' + name + '"? This cannot be undone.')) return;
            window.location.href = fallback;
            return;
        }

        // bans-unban: existing #1301 confirm + reason modal flow.
        var a = api(), A = actions();
        if (!a || !A || !bid) {
            // No JSON dispatcher available (e.g. third-party theme that
            // stripped api.js). Fall back to the legacy GET URL — the
            // server-side handler now also rejects empty `ureason` so
            // a hand-edited URL won't slip through (#1301).
            if (fallback) window.location.href = fallback;
            return;
        }
        openUnbanDialog({ bid: bid, name: name, fallback: fallback });
    });

    document.addEventListener('submit', function (e) {
        var form = /** @type {Element|null} */ (e.target);
        if (!form || !(/** @type {Element} */ (form)).closest) return;
        if (!form.matches('[data-testid="bans-unban-form"]')) return;
        e.preventDefault();
        if (!pending) return;

        var input = reasonInput();
        var reason = input ? input.value.trim() : '';
        if (reason === '') {
            // Mirror the v1.x JS guard: surface an inline error rather
            // than submitting an empty reason that the server would
            // bounce. The server still re-validates as the load-bearing
            // gate.
            showError('Please leave a comment.');
            if (input) try { input.focus(); } catch (_e) { /* focus may throw */ }
            return;
        }
        clearError();

        var ctx = pending;
        var submitBtn = /** @type {HTMLButtonElement|null} */ (form.querySelector('[data-testid="bans-unban-submit"]'));
        setBusy(submitBtn, true);

        var a = api(), A = actions();
        if (!a || !A) {
            setBusy(submitBtn, false);
            if (ctx.fallback) {
                // Encode the reason into the legacy GET URL so the no-JS
                // path lands with the typed reason populated.
                var sep = ctx.fallback.indexOf('?') === -1 ? '?' : '&';
                window.location.href = ctx.fallback + sep + 'ureason=' + encodeURIComponent(reason);
            }
            return;
        }

        a.call(A.BansUnban, { bid: Number(ctx.bid), ureason: reason }).then(function (r) {
            setBusy(submitBtn, false);
            if (!r || r.ok === false) {
                var msg = (r && r.error && r.error.message) || 'Unknown error';
                showError(msg);
                toast('error', 'Unban failed', msg);
                return;
            }
            rowsForBid(ctx.bid).forEach(flipRowToUnbanned);
            closeUnbanDialog();
            toast('success', 'Player unbanned', ctx.name + ' has been unbanned.');
        });
    });

    // Native <dialog> fires `cancel` on ESC; clear the pending context
    // so a subsequent click reopens cleanly with the next row's data.
    document.addEventListener('cancel', function (e) {
        var t = /** @type {Element|null} */ (e.target);
        if (!t || t.id !== 'bans-unban-dialog') return;
        // Let the native close fire too; we just reset our state.
        pending = null;
        clearError();
    });
})();
</script>
{/literal}

{* ============================================================
   Manifest of properties only consumed by themes/default/page_bans.tpl.
   The dual-theme PHPStan matrix (#1123 A2) scans this template
   against BanListView; SmartyTemplateRule's "every declared property
   is referenced" check applies per theme, so without the references
   below the sbpp2026 leg would flag every legacy-only field on the
   View as unused.

   {if false} blocks render to nothing — the parser still walks the
   tag bodies, so the variable refs are seen, but no output is
   produced.

   D1 deletes themes/default/, the legacy template stops needing
   these props, this manifest stops being necessary, and the View
   drops them. Until then, keep this block at EOF.
   ============================================================ *}
{if false}{$ban_nav}{$ctype}{$cid}{$page}{$canedit}{$othercomments}{$commenttype}{$commenttext}{$comment}{$friendsban}{$groupban}{$can_delete}{$general_unban}{$hidetext}{$searchlink}{$can_export}{$admin_postkey}{$view_comments}{$view_bans}{$hideadminname}{$total_bans}{/if}
