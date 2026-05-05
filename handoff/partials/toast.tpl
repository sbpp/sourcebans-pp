{* toast.tpl — params: kind (success|error|warn|info), title, body *}
{assign var=_icon value=[
  'success'=>'circle-check','error'=>'circle-x','warn'=>'triangle-alert','info'=>'info'
]}
<div class="toast" data-kind="{$kind}">
  <i data-lucide="{$_icon[$kind]|default:'info'}" style="color:var(--{$kind|default:'info'})"></i>
  <div style="flex:1;min-width:0">
    <div class="font-semibold text-sm">{$title|escape}</div>
    {if $body}<div class="text-xs text-muted" style="margin-top:0.125rem">{$body|escape}</div>{/if}
  </div>
  <button class="btn--ghost btn--icon" data-toast-close aria-label="Dismiss"><i data-lucide="x"></i></button>
</div>
