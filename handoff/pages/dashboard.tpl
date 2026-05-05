{* dashboard.tpl — public/admin landing page *}
<div class="p-6 space-y-6" style="max-width:1400px">
  <div>
    <h1 style="font-size:1.5rem;font-weight:600;margin:0">Dashboard</h1>
    <p class="text-sm text-muted m-0 mt-2">Activity across your servers, last 7 days.</p>
  </div>

  <div class="grid gap-4" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
    {foreach [['Total bans',$stats.total_bans|number_format,'+'|cat:$stats.delta_bans,'ban'],['Active bans',$stats.active_bans|number_format,'+'|cat:$stats.delta_active,'user-x'],['Comm blocks',$stats.comms|number_format,$stats.delta_comms,'mic-off'],['Servers online',$stats.servers_online|cat:'/'|cat:$stats.servers_total,null,'server']] as $s}
    <div class="card p-5">
      <div class="flex items-start justify-between">
        <div class="text-xs font-medium text-muted" style="text-transform:uppercase;letter-spacing:0.06em">{$s[0]}</div>
        <i data-lucide="{$s[3]}" style="color:var(--text-faint)"></i>
      </div>
      <div class="flex items-baseline gap-2 mt-2">
        <div style="font-size:1.875rem;font-weight:600;font-variant-numeric:tabular-nums">{$s[1]}</div>
        {if $s[2]}<span class="text-xs font-medium" style="color:var(--success)">{$s[2]}</span>{/if}
      </div>
    </div>
    {/foreach}
  </div>

  <div class="grid gap-4" style="grid-template-columns:2fr 1fr">
    <div class="card">
      <div class="card__header"><div><h3>Recent bans</h3><p>Latest enforcement actions</p></div><a class="btn btn--ghost btn--sm" href="?p=banlist">View all <i data-lucide="arrow-right"></i></a></div>
      <div>
        {foreach $recent_bans as $b}
        <a class="ban-row ban-row--{$b.state} flex items-center gap-3 p-4" style="border-bottom:1px solid var(--border)" href="?p=banlist&id={$b.bid}">
          {include file="partials/avatar.tpl" name=$b.name size=32}
          <div style="flex:1;min-width:0">
            <div class="flex items-center gap-2">
              <span class="font-medium text-sm truncate">{$b.name|escape}</span>
              {include file="partials/status-pill.tpl" status=$b.state}
            </div>
            <div class="text-xs text-muted truncate" style="margin-top:0.125rem">{$b.reason|escape} · {$b.sname|escape}</div>
          </div>
          <div class="text-xs text-muted text-right">{$b.banned_human}</div>
        </a>
        {/foreach}
      </div>
    </div>

    <div class="card">
      <div class="card__header"><div><h3>Servers</h3><p>Live status</p></div></div>
      <div class="p-2">
        {foreach $servers as $s}
        <div class="flex items-center gap-3 p-2" style="border-radius:var(--radius-md)">
          <span style="width:22px;height:22px;border-radius:3px;background:#52525b;color:white;font-weight:700;font-size:11px;display:grid;place-items:center">{$s.mod|truncate:2:'':true|upper}</span>
          <div style="flex:1;min-width:0">
            <div class="font-medium text-sm truncate">{$s.name|escape}</div>
            <div class="font-mono text-xs text-faint truncate">{$s.host|escape}</div>
          </div>
          {include file="partials/status-pill.tpl" status=($s.online ? 'online' : 'offline')}
        </div>
        {/foreach}
      </div>
    </div>
  </div>
</div>
