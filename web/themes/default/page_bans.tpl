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
      <a class="btn btn--secondary btn--sm" href="index.php?p=banlist&hideinactive={if $hidetext == 'Hide'}true{else}false{/if}{$searchlink}">{$hidetext} inactive</a>
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
       URL sync on top of it. *}
  <form method="get" action="index.php" id="banlist-filters" style="position:sticky;top:3.5rem;z-index:20;background:var(--bg-page);padding:0.75rem 0;border-bottom:1px solid var(--border)">
    <input type="hidden" name="p" value="banlist">
    <div class="flex gap-3" style="flex-wrap:wrap">
      <div style="flex:1;min-width:14rem;max-width:24rem;position:relative">
        <input class="input" type="search" name="searchText" data-testid="bans-search"
          value="{if isset($smarty.get.searchText)}{$smarty.get.searchText|escape}{/if}"
          placeholder="Player, SteamID, or IP&hellip;" aria-label="Search bans">
      </div>
      <button class="btn btn--secondary btn--sm" type="submit">Search</button>
    </div>

    <div class="flex items-center gap-2 mt-2 scroll-x" role="group" aria-label="Filter by status">
      <button class="chip" type="button" data-state-filter="" aria-pressed="true">All</button>
      <button class="chip" type="button" data-state-filter="permanent" aria-pressed="false" data-testid="filter-chip-permanent">
        <span class="chip__dot" style="background:#ef4444"></span>Permanent
      </button>
      <button class="chip" type="button" data-state-filter="active" aria-pressed="false" data-testid="filter-chip-active">
        <span class="chip__dot" style="background:#f59e0b"></span>Active
      </button>
      <button class="chip" type="button" data-state-filter="expired" aria-pressed="false" data-testid="filter-chip-expired">
        <span class="chip__dot" style="background:var(--zinc-300)"></span>Expired
      </button>
      <button class="chip" type="button" data-state-filter="unbanned" aria-pressed="false" data-testid="filter-chip-unbanned">
        <span class="chip__dot" style="background:#10b981"></span>Unbanned
      </button>
    </div>
  </form>

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
        <tr>
          <th scope="col">Player</th>
          <th scope="col">SteamID</th>
          <th scope="col">Reason</th>
          <th scope="col">Server</th>
          {if !$hideadminname}<th scope="col" class="col-admin">Admin</th>{/if}
          <th scope="col" class="col-length">Length</th>
          <th scope="col" class="col-banned">Banned</th>
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
              {if $view_comments && $ban.commentdata != "None" && $ban.commentdata|@count > 0}
              <span class="text-xs text-muted" title="{$ban.commentdata|@count} comment(s)">[{$ban.commentdata|@count}]</span>
              {/if}
            </div>
          </td>
          <td class="font-mono text-xs text-muted">
            {if empty($ban.steam)}
              <i class="text-faint">none</i>
            {else}
              {$ban.steam|escape}
            {/if}
          </td>
          <td class="text-muted truncate" style="max-width:18rem">{if empty($ban.reason)}<i class="text-faint">no reason</i>{else}{$ban.reason|escape}{/if}</td>
          <td class="text-muted truncate" style="max-width:12rem">{$ban.sname|escape}</td>
          {if !$hideadminname}
          <td class="col-admin text-muted">
            {if empty($ban.aname)}<i class="text-faint">deleted</i>{else}{$ban.aname|escape}{/if}
          </td>
          {/if}
          <td class="col-length tabular-nums text-muted">{if $ban.length == 0}Permanent{else}{$ban.length_human|escape}{/if}</td>
          <td class="col-banned text-muted text-xs"><time datetime="{$ban.banned_iso}">{$ban.banned_human|escape}</time></td>
          <td class="col-status">
            {assign var=_pill_label value=$ban.state|capitalize}
            <span class="pill pill--{$ban.state}">{$_pill_label|escape}</span>
          </td>
          <td class="col-actions">
            <div class="row-actions">
              {if $view_bans}
                {if $ban.can_edit_ban && $ban.state != 'unbanned'}
                <a class="btn btn--ghost btn--sm btn--icon" data-testid="row-action-edit"
                   title="Edit"
                   href="index.php?p=admin&amp;c=bans&amp;o=edit&amp;id={$ban.bid}&amp;key={$admin_postkey|escape}"
                   onclick="event.stopPropagation()">&#9998;</a>
                {/if}
                {if $ban.can_unban && $ban.state != 'unbanned' && $ban.state != 'expired'}
                <a class="btn btn--ghost btn--sm btn--icon" data-testid="row-action-unban"
                   title="Unban"
                   href="index.php?p=banlist&amp;a=unban&amp;id={$ban.bid}&amp;key={$admin_postkey|escape}"
                   onclick="event.stopPropagation()">&#10003;</a>
                {/if}
              {/if}
              {if !empty($ban.steam)}
              <button class="btn btn--ghost btn--sm btn--icon" type="button"
                      data-copy="{$ban.steam|escape}"
                      title="Copy SteamID"
                      onclick="event.stopPropagation()">&#128203;</button>
              {/if}
            </div>
          </td>
        </tr>
        {foreachelse}
        <tr><td colspan="9" style="text-align:center;padding:3rem;color:var(--text-muted)">No bans match those filters.</td></tr>
        {/foreach}
      </tbody>
    </table>
    </div>

    {* -- Mobile card list (<769px). Single anchor per row so a tap
         opens the detail view; data-drawer-href layers the C1 drawer
         on top once that lands. *}
    <div class="ban-cards">
      {foreach from=$ban_list item=ban}
      <a class="ban-row ban-row--{$ban.state} flex items-center gap-3 p-4"
         style="border-bottom:1px solid var(--border)"
         href="?p=banlist&amp;id={$ban.bid}"
         data-drawer-href="?p=banlist&amp;c=details&amp;id={$ban.bid}"
         data-state="{$ban.state}"
         data-id="{$ban.bid}"
         data-testid="drawer-trigger">
        <span class="avatar" style="width:36px;height:36px;background:hsl({$ban.avatar_hue} 55% 45%);font-size:13px" aria-hidden="true">{$ban.avatar_initials|escape}</span>
        <div style="flex:1;min-width:0">
          <div class="flex items-center gap-2">
            <span class="font-medium text-sm truncate">{if empty($ban.name)}<i class="text-faint">no nickname</i>{else}{$ban.name|escape}{/if}</span>
            {assign var=_m_pill_label value=$ban.state|capitalize}
            <span class="pill pill--{$ban.state}">{$_m_pill_label|escape}</span>
          </div>
          <div class="text-xs text-muted truncate" style="margin-top:0.125rem">{if empty($ban.reason)}no reason{else}{$ban.reason|escape}{/if} &middot; {if $ban.length == 0}Permanent{else}{$ban.length_human|escape}{/if}</div>
          <div class="font-mono text-xs text-faint truncate" style="margin-top:0.125rem">{if empty($ban.steam)}&mdash;{else}{$ban.steam|escape}{/if}</div>
        </div>
        <span class="text-faint" aria-hidden="true">&rsaquo;</span>
      </a>
      {foreachelse}
      <div style="text-align:center;padding:3rem;color:var(--text-muted)">No bans match those filters.</div>
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

{* banlist.js wires both branches: the chip filter / copy buttons /
   skeleton hook on the listing branch, and the `#banlist-comment-form`
   submit -> sb.api.call(BansAddComment / BansEditComment) on the
   comment-edit branch. The IIFE feature-detects every element it
   touches, so loading it unconditionally is safe; loading it only on
   the listing branch silently broke comment save (no submit handler
   attached, native form submission to action-less URL no-ops). *}
<script src="./scripts/banlist.js" defer></script>

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
{if false}{$ban_nav}{$ctype}{$cid}{$page}{$canedit}{$othercomments}{$commenttype}{$commenttext}{$comment}{$friendsban}{$groupban}{$can_delete}{$general_unban}{$hidetext}{$searchlink}{$can_export}{$admin_postkey}{$view_comments}{$view_bans}{$hideadminname}{$hideplayerips}{$total_bans}{/if}
