<h3>Admins on this server ({$admin_count})</h3>
<table width="100%" cellpadding="1" cellspacing="1" class="listtable">
<tr >
            <td width="50%" height='16' class="listtable_top"><strong>Admin Name</strong></td>
            <td width="50%" height='16' class="listtable_top"><strong>Admin SteamID</strong></td>
</tr>

{foreach from=$admin_list item=admin}
	{if $admin.user}
	<tr class="opener tbl_out" onmouseout="this.className='tbl_out'" onmouseover="this.className='tbl_hover'">
		<td class="listtable_1{if $admin.ingame}_unbanned{/if}" style="border-bottom: solid 1px #ccc" height="16">{$admin.user|escape:'html'}</td>
		<td class="listtable_1{if $admin.ingame}_unbanned{/if}" style="border-bottom: solid 1px #ccc" height="16">{$admin.authid}</td>
	</tr>
	<tr align="left">
		<td colspan="7" align="center">
			<div class="opener"> 
			{if $admin.ingame}
				<table width="80%" cellspacing="0" cellpadding="0" class="listtable">
					<tr>
						<td height="16" align="left" class="listtable_top" colspan="5">
							<b>Admin Details Ingame</b>            
						</td>
					</tr>
					<tr align="left">
						<td width="30%" height="16" class="listtable_1">Name</td>
						<td width="20%" height="16" class="listtable_1">Steam ID</td>
						<td width="20%" height="16" class="listtable_1">IP</td>
						<td width="20%" height="16" class="listtable_1">Time</td>
						<td width="20%" height="16" class="listtable_1">Ping</td>
					</tr>
					<tr align="left">
						<td height="16" class="listtable_1">
							{$admin.iname|escape:'html'}
						</td>
						<td height="16" class="listtable_1">
							{$admin.authid}
						</td>
						<td height="16" class="listtable_1">
							{$admin.iip}
						</td>
						<td height="16" class="listtable_1">
							{$admin.itime}
						</td>
						<td height="16" class="listtable_1">
							{$admin.iping}
						</td>
					</tr>
				</table>	
			{/if}
			</div>
		</td>
	</tr>
	{/if}
{/foreach}
</table>
<script>InitAccordion('tr.opener', 'div.opener', 'mainwrapper');</script>
