{* servers.tpl — public + admin server list *}
<div class="p-6 space-y-4" style="max-width:1400px">
  <div class="flex items-end justify-between gap-4" style="flex-wrap:wrap">
    <div>
      <h1 style="font-size:1.5rem;font-weight:600;margin:0">Servers</h1>
      <p class="text-sm text-muted m-0 mt-2">{$servers_online}/{$servers|@count} online · {$total_players} players right now</p>
    </div>
    {if $user.srv_flags|strpos:'z' !== false}
    <a class="btn btn--primary" href="?p=admin&c=servers&action=add"><i data-lucide="plus"></i> Add server</a>
    {/if}
  </div>
  <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(20rem,1fr))">
    {foreach $servers as $s}
    <div class="card p-4">
      <div class="flex items-start gap-3">
        <span style="width:36px;height:36px;border-radius:3px;background:#52525b;color:white;font-weight:700;font-size:14px;display:grid;place-items:center;flex-shrink:0">{$s.mod|truncate:2:'':true|upper}</span>
        <div style="flex:1;min-width:0">
          <div class="font-semibold truncate">{$s.name|escape}</div>
          <div class="font-mono text-xs text-faint truncate" style="margin-top:0.125rem">{$s.host|escape}:{$s.port}</div>
        </div>
        {include file="partials/status-pill.tpl" status=($s.online ? 'online' : 'offline')}
      </div>
      {if $s.online}
      <div class="flex items-center justify-between text-xs text-muted mt-4">
        <div class="flex items-center gap-1"><i data-lucide="map" style="width:13px;height:13px"></i> {$s.map|escape}</div>
        <div class="tabular-nums font-medium" style="color:var(--text)">{$s.players}/{$s.max} players</div>
      </div>
      <div style="margin-top:0.5rem;height:6px;border-radius:9999px;background:var(--bg-muted);overflow:hidden">
        <div style="height:100%;background:var(--brand-500);width:{($s.players/$s.max)*100}%"></div>
      </div>
      {else}
      <div class="text-xs text-muted mt-4" style="font-style:italic">Server is currently offline.</div>
      {/if}
      {if $user.srv_flags|strpos:'m' !== false}
      <div class="flex gap-1 mt-4" style="border-top:1px solid var(--border);padding-top:0.75rem">
        <a class="btn btn--ghost btn--sm" href="?p=admin&c=rcon&sid={$s.sid}"><i data-lucide="terminal-square"></i> RCON</a>
        <a class="btn btn--ghost btn--sm" href="?p=admin&c=servers&sid={$s.sid}"><i data-lucide="users"></i> Players</a>
      </div>
      {/if}
    </div>
    {/foreach}
  </div>
</div>
