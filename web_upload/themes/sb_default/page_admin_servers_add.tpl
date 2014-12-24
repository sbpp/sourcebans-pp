{if not $permission_addserver}
	Access Denied
{else}

<div id="add-group">
	<h3>Server Details</h3>
	For more information or help regarding a certain subject move your mouse over the question mark.<br /><br />
	<input type="hidden" name="insert_type" value="add">
	<table width="90%" border="0" style="border-collapse:collapse;" id="group.details" cellpadding="3">
		<tr>
		    <td valign="top" width="35%">
		    	<div class="rowdesc">{help_icon title="Server Address" message="This is the IP address to your server. You can also type a domain, if you have one setup."}Server IP/Domain</div>
		    </td>
		    <td>
		    	<div align="left">
		        	<input type="text" TABINDEX=1 class="submit-fields" id="address" name="address" value="{$ip}" />
		      	</div>
		        <div id="address.msg" class="badentry"></div>
			</td>
		</tr>
		
		<tr>
			<td valign="middle">
				<div class="rowdesc">{help_icon title="Server Port" message="This is the port that the server is running off. <br /><br /><i>Default: 27015</i>"}Server Port</div>
			</td>
		    <td>
		    	<div align="left">
		      		<input type="text" TABINDEX=2 class="submit-fields" id="port" name="port" value="{if $port}{$port}{else}{27015}{/if}" />
		    	</div>
		    	<div id="port.msg" class="badentry"></div>
		    </td>
		</tr>

		<tr>
			<td valign="middle">
				<div class="rowdesc">{help_icon title="Rcon Password" message="This is your servers RCON password. This can be found in your server.cfg file next to <i>rcon_password</i>.<br /><br />This will be used to allow admins to administrate the server though the web interface."}RCON Password</div>
			</td>
		    <td>
		    	<div align="left">
		        	<input type="password" TABINDEX=3 class="submit-fields" id="rcon" name="rcon" value="{$rcon}" />
		      	</div>
		        <div id="rcon.msg" class="badentry"></div>
			</td>
		</tr>
		  
		<tr>
		    <td valign="middle">
		    	<div class="rowdesc">{help_icon title="Rcon Password" message="Please re-type your rcon password to avoid 'typos'"}RCON Password (Confirm)</div>
		    </td>
		    <td>
		    <div align="left">
		    	<input type="password" TABINDEX=4 class="submit-fields" id="rcon2" name="rcon2" value="{$rcon}" />
		    </div>
		        <div id="rcon2.msg" class="badentry"></div>
			</td>
		</tr>
		 
		<tr>
			<td valign="middle">
				<div class="rowdesc">{help_icon title="Server Mod" message="Select the mod that your server is currently running."}Server MOD </div>
			</td>
		    <td>
		    	<div align="left" id="admingroup">
		      		<select name="mod" TABINDEX=5 onchange="" id="mod" class="submit-fields">
						{if !$edit_server}
		        		<option value="-2">Please Select...</option>
						{/if}
							{foreach from="$modlist" item="mod"}
								<option value='{$mod.mid}'>{$mod.name}</option>
							{/foreach}
		        	</select>
		        </div>
		        <div id="mod.msg" class="badentry"></div>
			</td>
		</tr>
		  
		<tr>
		    <td valign="middle">
		    	<div class="rowdesc">{help_icon title="Enabled" message="Enables the server to be shown on the public servers list."}Enabled</div>
		    </td>
		    <td>
		    <div align="left">
		    	<input type="checkbox" id="enabled" name="enabled" checked="checked" /> 
		    </div>
		        <div id="enabled.msg" class="badentry"></div>
			</td>
		</tr>
		
		<tr>
			<td valign="middle">
				<div class="rowdesc">{help_icon title="Server Groups" message="Choose the groups to add this server to. Server groups are used for adding admins to specific sets of servers."}Server Groups </div>
			</td>
		    <td>&nbsp;</td>
		</tr>
			{foreach from="$grouplist" item="group"}
				<tr>
			   		<td valign="middle">
			   			<div align="right">{$group.name} </div>
			   		</td>
			    	<td>
			    		<div align="left">
			    			<input type="checkbox" value="{$group.gid}" id="g_{$group.gid}" name="groups[]" /> 
			    		</div>
			    	</td>
				</tr> 
			{/foreach}
		<tr id="nsgroup" valign="top" class="badentry"> 		
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td>
			{if $edit_server}
				{sb_button text=$submit_text onclick="process_edit_server();" class="ok" id="aserver" submit=false}
			{else}
				{sb_button text=$submit_text onclick="process_add_server();" class="ok" id="aserver" submit=false}
			{/if}
			      &nbsp;
				{sb_button text="Back" onclick="history.go(-1)" class="cancel" id="back" submit=false}
			</td>
		</tr>
	</table>
</div>

{/if}
