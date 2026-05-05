{* admin/appeals.tpl — admin appeal queue (master-detail) *}
<div class="p-6" style="max-width:1400px">
  <div class="mb-4"><h1 style="font-size:1.5rem;font-weight:600;margin:0">Appeals</h1><p class="text-sm text-muted m-0 mt-2">Ban protests submitted by players.</p></div>
  <form method="get" class="flex items-center gap-2 mb-4 scroll-x">
    <input type="hidden" name="p" value="admin"><input type="hidden" name="c" value="appeals">
    {foreach [['open','Open','#f59e0b'],['replied','Replied','#2563eb'],['accepted','Accepted','#10b981'],['denied','Denied','#ef4444'],['all','All',null]] as $f}
    <button class="chip" type="submit" name="status" value="{$f[0]}" aria-pressed="{if $filters.status==$f[0]}true{else}false{/if}">{if $f[2]}<span class="chip__dot" style="background:{$f[2]}"></span>{/if}{$f[1]}</button>
    {/foreach}
  </form>
  <div class="grid gap-4" style="grid-template-columns:380px 1fr">
    <div class="card" style="overflow:hidden;max-height:40rem;overflow-y:auto">
      {foreach $appeals as $a}
      <a class="flex items-start gap-3 p-3" style="border-bottom:1px solid var(--border){if $picked.aid==$a.aid};background:var(--bg-muted){/if}" href="?p=admin&c=appeals&aid={$a.aid}">
        {include file="partials/avatar.tpl" name=$a.name size=32}
        <div style="flex:1;min-width:0">
          <div class="flex items-center gap-2"><span class="font-medium text-sm truncate">{$a.name|escape}</span></div>
          <div class="text-xs text-muted truncate">{$a.reason|escape}</div>
          <div class="text-xs text-faint" style="margin-top:0.25rem">{$a.time_human}</div>
        </div>
      </a>
      {/foreach}
    </div>
    {if $picked}
    <form class="card" method="post" action="?p=admin&c=appeals&action=respond&aid={$picked.aid}">
      <input type="hidden" name="token" value="{$form_token}">
      <div class="card__header"><div><h3>{$picked.name|escape}</h3><p class="font-mono">{$picked.steam|escape}</p></div></div>
      <div class="card__body space-y-4">
        <div><label class="label">Player's statement</label><div class="card p-4" style="background:var(--bg-muted)">{$picked.body|escape|nl2br}</div></div>
        <div><label class="label">Reply</label><textarea class="textarea" name="reply" rows="3" placeholder="Write a response…"></textarea></div>
        <div class="flex justify-end gap-2" style="border-top:1px solid var(--border);padding-top:0.75rem">
          <button class="btn btn--danger" type="submit" name="action" value="deny"><i data-lucide="x"></i> Deny</button>
          <button class="btn btn--secondary" type="submit" name="action" value="reply"><i data-lucide="message-square"></i> Reply</button>
          <button class="btn btn--primary" type="submit" name="action" value="accept"><i data-lucide="check"></i> Accept &amp; unban</button>
        </div>
      </div>
    </form>
    {/if}
  </div>
</div>
