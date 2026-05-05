{* ============================================================
   pages/banlist.tpl — Public ban list (the marquee page)
   Use: include header.tpl, this body, footer.tpl
   Smarty vars: $bans (array of rows), $bans_count, $servers, $filters, $user
   ============================================================ *}
<div class="p-6 space-y-4" style="max-width:1400px">
  <div class="flex items-end justify-between gap-4" style="flex-wrap:wrap">
    <div>
      <h1 style="font-size:1.5rem;font-weight:600;margin:0">Ban list</h1>
      <p class="text-sm text-muted m-0 mt-2">{$bans|@count|number_format} of {$bans_count|number_format} bans</p>
    </div>
    <div class="flex gap-2">
      <a class="btn btn--secondary" href="?p=banlist&format=csv"><i data-lucide="download"></i> Export CSV</a>
      {if $user.srv_flags|strpos:'d' !== false}
      <a class="btn btn--primary" href="?p=admin&c=bans&action=add"><i data-lucide="plus"></i> Add ban</a>
      {/if}
    </div>
  </div>

  {* Sticky filter bar *}
  <form method="get" action="?" id="banlist-filters" style="position:sticky;top:3.5rem;z-index:20;background:var(--bg-page);padding:0.75rem 0;border-bottom:1px solid var(--border)">
    <input type="hidden" name="p" value="banlist">
    <div class="flex gap-3" style="flex-wrap:wrap">
      <div style="flex:1;min-width:14rem;max-width:24rem;position:relative">
        <i data-lucide="search" style="position:absolute;left:0.625rem;top:50%;transform:translateY(-50%);color:var(--text-faint);width:14px;height:14px"></i>
        <input class="input input--with-icon" type="search" name="search" value="{$filters.search|escape}" placeholder="Player, SteamID, or IP…">
      </div>
      <select class="select" name="server" style="width:auto;min-width:11rem">
        <option value="">All servers</option>
        {foreach $servers as $s}
          <option value="{$s.sid}" {if $filters.server==$s.sid}selected{/if}>{$s.name|escape}</option>
        {/foreach}
      </select>
      <select class="select" name="time" style="width:auto">
        <option value="">All time</option>
        <option value="1d" {if $filters.time=='1d'}selected{/if}>Today</option>
        <option value="7d" {if $filters.time=='7d'}selected{/if}>Last 7 days</option>
        <option value="30d" {if $filters.time=='30d'}selected{/if}>Last 30 days</option>
      </select>
    </div>

    <div class="flex items-center gap-2 mt-2 scroll-x">
      {foreach [['','All',$bans_count],['permanent','Permanent',null,'#ef4444'],['active','Active',null,'#f59e0b'],['expired','Expired',null,'#a1a1aa'],['unbanned','Unbanned',null,'#10b981']] as $f}
      <button class="chip" type="submit" name="state" value="{$f[0]}" aria-pressed="{if $filters.state==$f[0]}true{else}false{/if}">
        {if $f[3]}<span class="chip__dot" style="background:{$f[3]}"></span>{/if}
        {$f[1]}
      </button>
      {/foreach}
    </div>
  </form>

  {* Desktop table *}
  <div class="card" style="overflow:hidden">
    <table class="table">
      <thead>
        <tr>
          <th>Player</th>
          <th>SteamID</th>
          <th>Reason</th>
          <th>Server</th>
          <th>Length</th>
          <th>Banned</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        {foreach $bans as $b}
          {* derive state in PHP and pass it as $b.state; here we just consume *}
          <tr class="ban-row ban-row--{$b.state}" data-drawer-href="?p=banlist&c=details&id={$b.bid}">
            <td>
              <div class="flex items-center gap-3" style="min-width:0">
                {include file="partials/avatar.tpl" name=$b.name size=28}
                <span class="font-medium truncate">{$b.name|escape}</span>
              </div>
            </td>
            <td class="font-mono text-xs text-muted">{$b.steam|escape}</td>
            <td class="text-muted">{$b.reason|escape}</td>
            <td class="text-muted">{$b.sname|escape}</td>
            <td class="tabular-nums text-muted">{if $b.length==0}Permanent{else}{$b.length_human}{/if}</td>
            <td class="text-muted text-xs">{$b.banned_human}</td>
            <td>{include file="partials/status-pill.tpl" status=$b.state}</td>
            <td>
              <div class="row-actions">
                {if $user.srv_flags|strpos:'e' !== false}
                  <button class="btn--ghost btn--icon" data-action="edit" data-id="{$b.bid}" onclick="event.stopPropagation()" title="Edit"><i data-lucide="pencil"></i></button>
                  {if $b.state != 'unbanned'}
                  <form method="post" action="?p=admin&c=bans&action=unban" style="display:inline" onclick="event.stopPropagation()">
                    <input type="hidden" name="ban" value="{$b.bid}">
                    <input type="hidden" name="token" value="{$form_token}">
                    <button class="btn--ghost btn--icon" type="submit" title="Unban"><i data-lucide="check"></i></button>
                  </form>
                  {/if}
                {/if}
                <button class="btn--ghost btn--icon" data-copy="{$b.steam|escape}" onclick="event.stopPropagation()" title="Copy SteamID"><i data-lucide="copy"></i></button>
              </div>
            </td>
          </tr>
        {foreachelse}
          <tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--text-muted)">No bans match those filters.</td></tr>
        {/foreach}
      </tbody>
    </table>

    {* Mobile cards *}
    <div class="ban-cards">
      {foreach $bans as $b}
        <a class="ban-row ban-row--{$b.state} flex items-center gap-3 p-4" style="border-bottom:1px solid var(--border)" href="?p=banlist&id={$b.bid}">
          {include file="partials/avatar.tpl" name=$b.name size=36}
          <div style="flex:1;min-width:0">
            <div class="flex items-center gap-2">
              <span class="font-medium text-sm truncate">{$b.name|escape}</span>
              {include file="partials/status-pill.tpl" status=$b.state}
            </div>
            <div class="text-xs text-muted truncate" style="margin-top:0.125rem">{$b.reason|escape} · {if $b.length==0}Permanent{else}{$b.length_human}{/if}</div>
            <div class="font-mono text-xs text-faint truncate" style="margin-top:0.125rem">{$b.steam|escape}</div>
          </div>
          <i data-lucide="chevron-right"></i>
        </a>
      {/foreach}
    </div>
  </div>

  {* Pagination *}
  {if $pagination}
  <div class="flex items-center justify-between text-xs text-muted">
    <div>Showing <span class="font-medium" style="color:var(--text)">{$pagination.from}–{$pagination.to}</span> of <span class="font-medium" style="color:var(--text)">{$pagination.total|number_format}</span></div>
    <div class="flex gap-1">
      <a class="btn btn--secondary btn--sm" href="{$pagination.prev_url}" {if !$pagination.prev_url}aria-disabled="true"{/if}><i data-lucide="chevron-left"></i> Prev</a>
      <a class="btn btn--secondary btn--sm" href="{$pagination.next_url}" {if !$pagination.next_url}aria-disabled="true"{/if}>Next <i data-lucide="chevron-right"></i></a>
    </div>
  </div>
  {/if}
</div>
