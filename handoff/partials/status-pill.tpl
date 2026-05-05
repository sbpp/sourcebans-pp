{* status-pill.tpl — params: status (permanent|active|expired|unbanned|online|offline) *}
{assign var=_label value=[
  'permanent'=>'Permanent','active'=>'Active','expired'=>'Expired',
  'unbanned'=>'Unbanned','online'=>'Online','offline'=>'Offline'
]}
<span class="pill pill--{$status}">
  {if $status=='online'}<span style="width:6px;height:6px;border-radius:50%;background:#10b981"></span>{/if}
  {$_label[$status]|default:$status|escape}
</span>
