<div align="center">
	<table width="80%" cellpadding="0" class="listtable" cellspacing="0">
		<tr class="sea_open">
			<td width="2%" height="16" class="listtable_top" colspan="3"><b>Advanced Search<b> (Click)</td>
	  	</tr>
	  	<tr>
	  		<td>
	  		<div class="panel">
	  			<table width="100%" cellpadding="0" class="listtable" cellspacing="0">
			    <tr>
					<td class="listtable_1" width="8%" align="center"><input id="name_" name="search_type" type="radio" value="name"></td>
			        <td class="listtable_1" width="26%">Nickname</td>
			        <td class="listtable_1" width="66%"><input type="text" id="nick" value="" onmouseup="$('name_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 250px;"></td>
				</tr>       
			    <tr>
			        <td align="center" class="listtable_1" ><input id="steam_" type="radio" name="search_type" value="radiobutton"></td>
			        <td class="listtable_1" >SteamID</td>
			        <td class="listtable_1" >
				    <input type="text" id="steamid" value="" onmouseup="$('steam_').checked = true"style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 150px;"><select id="steam_match" onmouseup="$('steam_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 100px;">
					<option label="exact" value="0" selected>Exact Match</option>
					<option label="partial" value="1">Partial Match</option>
				    </select>
			        </td>
			    </tr>
				{if $can_editadmin}
				<tr>
					<td class="listtable_1" width="8%" align="center"><input id="admemail_" name="search_type" type="radio" value="radiobutton"></td>
			        <td class="listtable_1" width="26%">E-Mail</td>
			        <td class="listtable_1" width="66%"><input type="text" id="admemail" value="" onmouseup="$('admemail_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 250px;"></td>
				</tr>
				{/if}
			    <tr>
			        <td align="center" class="listtable_1" ><input id="webgroup_" type="radio" name="search_type" value="radiobutton"></td>
			        <td class="listtable_1" >Web Group</td>
			        <td class="listtable_1" >
						<select id="webgroup" onmouseup="$('webgroup_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 250px;">
							{foreach from="$webgroup_list" item="webgrp"}
								<option label="{$webgrp.name}" value="{$webgrp.gid}">{$webgrp.name}</option>
							{/foreach}
						</select>
					</td>
			    </tr>
				<tr>
					<td align="center" class="listtable_1" ><input id="srvadmgroup_" type="radio" name="search_type" value="radiobutton"></td>
			        <td class="listtable_1" >Serveradmin Group</td>
			        <td class="listtable_1" >
			        	<select id="srvadmgroup" onmouseup="$('srvadmgroup_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 250px;">
							{foreach from="$srvadmgroup_list" item="srvadmgrp"}
								<option label="{$srvadmgrp.name}" value="{$srvadmgrp.name}">{$srvadmgrp.name}</option>
							{/foreach}
						</select>
			        </td>
			  	</tr>
				<tr>
					<td align="center" class="listtable_1" ><input id="srvgroup_" type="radio" name="search_type" value="radiobutton"></td>
			        <td class="listtable_1" >Server Group</td>
			        <td class="listtable_1" >
			        	<select id="srvgroup" onmouseup="$('srvgroup_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 250px;">
							{foreach from="$srvgroup_list" item="srvgrp"}
								<option label="{$srvgrp.name}" value="{$srvgrp.gid}">{$srvgrp.name}</option>
							{/foreach}
						</select>
			        </td>
			  	</tr>
			    <tr>
			    	<td class="listtable_1"  align="center"><input id="admwebflags_" name="search_type" type="radio" value="radiobutton"></td>
			        <td class="listtable_1" >Web Permissions</td>
			        <td class="listtable_1" >
						<select id="admwebflag" name="admwebflag" onblur="getMultiple(this, 1);" size="5" multiple onmouseup="$('admwebflags_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 250px;">
							{foreach from="$admwebflag_list" item="admwebflag"}
								<option label="{$admwebflag.name}" value="{$admwebflag.flag}">{$admwebflag.name}</option>
							{/foreach}
						</select>
					</td> 
				</tr>
				<tr>
			    	<td class="listtable_1"  align="center"><input id="admsrvflags_" name="search_type" type="radio" value="radiobutton"></td>
			        <td class="listtable_1" >Server Permissions</td>
			        <td class="listtable_1" >
						<select id="admwebflag" name="admsrvflag" onblur="getMultiple(this, 2);" size="5" multiple onmouseup="$('admsrvflags_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 250px;">
							{foreach from="$admsrvflag_list" item="admsrvflag"}
								<option label="{$admsrvflag.name}" value="{$admsrvflag.flag}">{$admsrvflag.name}</option>
							{/foreach}
						</select>
					</td> 
				</tr>
			    <tr>
			    	<td class="listtable_1"  align="center"><input id="admin_on_" name="search_type" type="radio" value="radiobutton"></td>
					<td class="listtable_1" >Server</td>
			        <td class="listtable_1" >
						<select id="server" onmouseup="$('admin_on_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 250px;">
							{foreach from="$server_list" item="server}
								<option value="{$server.sid}" id="ss{$server.sid}">Retrieving Hostname... ({$server.ip}:{$server.port})</option>
							{/foreach}
						</select>            
					</td>
			    </tr>
			    <tr>
				    <td> </td>
				    <td> </td>
			        <td>{sb_button text="Search" onclick="search_admins();" class="ok" id="searchbtn" submit=false}</td>
			    </tr>
			   </table>
			   </div>
		  </td>
		</tr>
	</table>
</div>
{$server_script}
<script>InitAccordion('tr.sea_open', 'div.panel', 'mainwrapper');</script>
