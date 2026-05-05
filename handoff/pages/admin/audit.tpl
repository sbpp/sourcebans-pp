{* admin/audit.tpl — Audit log feed *}
<div class="p-6" style="max-width:1400px">
  <div class="flex items-end justify-between mb-4" style="flex-wrap:wrap;gap:1rem">
    <div>
      <h1 style="font-size:1.5rem;font-weight:600;margin:0">Audit log</h1>
      <p class="text-sm text-muted m-0 mt-2">Every administrative action.</p>
    </div>
    <a class="btn btn--secondary" href="?p=admin&c=audit&format=csv"><i data-lucide="download"></i> Export</a>
  </div>
  <form method="get" class="flex items-center gap-2 mb-4 scroll-x">
    <input type="hidden" name="p" value="admin"><input type="hidden" name="c" value="audit">
    {foreach [['','All',null],['ban_added','Bans','#ef4444'],['ban_unbanned','Unbans','#10b981'],['comm_added','Comms','#f59e0b'],['admin_added','Admin changes','#2563eb'],['login','Logins',null],['settings','Settings',null]] as $f}
    <button class="chip" type="submit" name="kind" value="{$f[0]}" aria-pressed="{if $filters.kind==$f[0]}true{else}false{/if}">
      {if $f[2]}<span class="chip__dot" style="background:{$f[2]}"></span>{/if}{$f[1]}
    </button>
    {/foreach}
  </form>
  <div class="card">
    {foreach $audit as $e}
    <div class="flex items-center gap-3 p-4" style="border-bottom:1px solid var(--border)">
      <div style="width:32px;height:32px;border-radius:50%;background:var(--bg-muted);display:grid;place-items:center;flex-shrink:0;color:var(--text-muted)">
        <i data-lucide="{$e.icon}" style="width:14px;height:14px"></i>
      </div>
      <div style="flex:1;min-width:0">
        <div class="text-sm">
          <span class="font-semibold">{$e.admin|escape}</span>
          <span class="text-muted"> {$e.verb|escape} </span>
          {if $e.target}<code class="font-mono text-xs" style="background:var(--bg-muted);padding:0 0.375rem;border-radius:var(--radius-sm)">{$e.target|escape}</code>{/if}
        </div>
        <div class="text-xs text-muted font-mono" style="margin-top:0.125rem">{$e.time_human} · from {$e.ip}</div>
      </div>
    </div>
    {foreachelse}
    <div class="p-6 text-center text-sm text-muted">No audit events match.</div>
    {/foreach}
  </div>
</div>
