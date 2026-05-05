{* admin/groups.tpl — Master-detail with flag checkboxes *}
<div class="p-6" style="max-width:1400px">
  <div class="flex items-end justify-between gap-4 mb-6" style="flex-wrap:wrap">
    <div>
      <h1 style="font-size:1.5rem;font-weight:600;margin:0">Groups</h1>
      <p class="text-sm text-muted m-0 mt-2">Permission flags and immunity levels.</p>
    </div>
    <a class="btn btn--primary" href="?p=admin&c=groups&action=add"><i data-lucide="plus"></i> New group</a>
  </div>
  <div class="grid gap-4" style="grid-template-columns:1fr 2fr">
    <div class="card" style="overflow:hidden">
      {foreach $groups as $g}
      <a class="flex items-center gap-3 p-4" style="border-bottom:1px solid var(--border){if $g.gid==$selected.gid};background:var(--bg-muted){/if}" href="?p=admin&c=groups&gid={$g.gid}">
        <div style="width:36px;height:36px;border-radius:var(--radius-lg);background:var(--brand-600);color:white;display:grid;place-items:center;font-weight:600">{$g.name|truncate:1:'':true|upper}</div>
        <div style="flex:1;min-width:0">
          <div class="font-medium text-sm">{$g.name|escape}</div>
          <div class="text-xs text-muted">{$g.member_count} members · immunity {$g.immunity}</div>
        </div>
      </a>
      {/foreach}
    </div>
    <form class="card" method="post" action="?p=admin&c=groups&action=save">
      <input type="hidden" name="token" value="{$form_token}">
      <input type="hidden" name="gid" value="{$selected.gid}">
      <div class="card__header"><div><h3>{$selected.name|escape}</h3><p>{$selected.member_count} members · immunity {$selected.immunity}</p></div></div>
      <div class="card__body space-y-4">
        <div class="grid gap-4" style="grid-template-columns:1fr 1fr">
          <div><label class="label">Group name</label><input class="input" name="name" value="{$selected.name|escape}"></div>
          <div><label class="label">Immunity (0–100)</label><input class="input" type="number" name="immunity" value="{$selected.immunity}" min="0" max="100"></div>
        </div>
        <div>
          <label class="label">Permission flags</label>
          <div class="grid gap-2" style="grid-template-columns:repeat(auto-fill,minmax(11rem,1fr))">
            {foreach $all_flags as $flag => $label}
            <label class="flex items-center gap-2 p-2" style="border:1px solid var(--border);border-radius:var(--radius-md)">
              <input type="checkbox" name="flags[]" value="{$flag}" {if $selected.flags|strpos:$flag !== false}checked{/if}>
              <span class="font-mono text-xs" style="background:var(--bg-muted);padding:0 0.25rem;border-radius:var(--radius-sm)">{$flag}</span>
              <span class="text-xs truncate">{$label|escape}</span>
            </label>
            {/foreach}
          </div>
        </div>
        <div class="flex justify-end gap-2" style="border-top:1px solid var(--border);padding-top:0.75rem">
          <a class="btn btn--ghost" href="?p=admin&c=groups">Discard</a>
          <button class="btn btn--primary" type="submit"><i data-lucide="save"></i> Save changes</button>
        </div>
      </div>
    </form>
  </div>
</div>
