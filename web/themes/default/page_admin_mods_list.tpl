{if NOT $permission_listmods}
	Access Denied!
{else}
	<h3>Server Mods ({$mod_count})</h3>
	<table width="100%" cellspacing="0" cellpadding="0" align="center" class="listtable">
		<tr>
			<td width="50%" height='16' class="listtable_top"><strong>Name</strong></td>
			<td width="25%" height='16' class="listtable_top"><strong>Mod Folder</strong></td>
			<td width="10%" height='16' class="listtable_top"><strong>Mod icon</strong></td>
			<td width="2%" height='16' class="listtable_top"><strong><span title="SteamID Universe (X of STEAM_X:Y:Z)">SU</span></strong></td>
			{if $permission_editmods || $permission_deletemods}
			<td height='16' class="listtable_top"><strong>Action</strong></td>
			{/if}
		</tr>
		{foreach from="$mod_list" item="mod" name="gaben"}
			<tr id="mid_{$mod.mid}">
				<td class="listtable_1" height='16'>{$mod.name|htmlspecialchars}</td>
				<td class="listtable_1" height='16'>{$mod.modfolder|htmlspecialchars}</td>
				<td class="listtable_1" height='16'><img src="images/games/{$mod.icon}" width="16"></td>
				<td class="listtable_1" height='16'>{$mod.steam_universe|htmlspecialchars}</td>
				{if $permission_editmods || $permission_deletemods}
				<td class="listtable_1" height='16'>
					{if $permission_editmods}
					<a href="index.php?p=admin&c=mods&o=edit&id={$mod.mid}">Edit</a> - 
					{/if}
					{if $permission_deletemods}
					<a href="#" onclick="RemoveMod('{$mod.name|escape:'quotes'|htmlspecialchars}', '{$mod.mid}');">Delete</a>
					{/if}
				</td>
				{/if}
			</tr>
		{/foreach}
	</table>
{/if}
