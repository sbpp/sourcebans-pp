{if NOT $permission_addadmin}
	Access Denied!
{else}
	<div id="msg-green" style="display:none;">
		<i><img src="./images/yay.png" alt="Warning" /></i>
		<b>Admin Added</b>
		<br />
		The new admin has been successfully added to the system.<br /><br />
		<i>Redirecting back to admins page</i>
	</div>
	
	
	<div id="add-group">
		<h3>Admin Details</h3>
		For more information or help regarding a certain subject move your mouse over the question mark.<br /><br />
		<table width="90%" border="0" style="border-collapse:collapse;" id="group.details" cellpadding="3">
			<tr>
		    	<td valign="top" width="35%">
		    		<div class="rowdesc">
		    			{help_icon title="Admin Login" message="This is the username the admin will use to login-to their admin panel. Also this will identify the admin on any bans they make."}Admin Login 
		    		</div>
		    	</td>
		    	<td>
		    		<div align="left">
		        		<input type="text" TABINDEX=1 class="textbox" style="width: 200px" id="adminname" name="adminname" />
		      		</div>
		        	<div id="name.msg" class="badentry"></div>
		        </td>
			</tr>
		  	<tr>
		    	<td valign="top">
		    		<div class="rowdesc">
		    			{help_icon title="Steam ID" message="This is the admins 'STEAM' id. This must be set so that admins can use their admin rights ingame."}Admin Steam ID
		    		</div>
		    	</td>
		    	<td>
		    		<div align="left">
		     			<input type="text" TABINDEX=2 value="STEAM_0:" class="textbox" style="width: 200px" id="steam" name="steam" />
		    		</div>
		    		<div id="steam.msg" class="badentry"></div>
		    	</td>
		  	</tr>
		  	<tr>
		    	<td valign="top">
		    		<div class="rowdesc">
		    			{help_icon title="Admin Email" message="Set the admins e-mail address. This will be used for sending out any automated messages from the system and changing of forgotten passwords. This is only required, if you set webpanel permissions."}Admin Email 
		    		</div>
		    	</td>
		    	<td>
		    		<div align="left">
		        		<input type="text" TABINDEX=3 class="textbox" style="width: 200px" id="email" name="email" />
		     		</div>
		        	<div id="email.msg" class="badentry"></div>
		        </td>
		  	</tr>
		  	<tr>
		    	<td valign="top">
		    		<div class="rowdesc">
		    			{help_icon title="Password" message="The password the admin will need to access the admin panel. This is only required, if you set webpanel permissions."}Admin Password 
		    		</div>
		    	</td>
		    	<td>
		    		<div align="left">
		       			<input type="password" TABINDEX=4 class="textbox" style="width: 200px" id="password" name="password" />
		      		</div>
		        	<div id="password.msg" class="badentry"></div>
		        </td>
		  	</tr>
		  	<tr>
		    	<td valign="top">
		    		<div class="rowdesc">
		    			{help_icon title="Password" message="Type the password again to confirm."}Admin Password (confirm) 
		    		</div>
		    	</td>
		    	<td>
		    		<div align="left">
		        		<input type="password" TABINDEX=5 class="textbox" style="width: 200px" id="password2" name="password2" />
		      		</div>
		        	<div id="password2.msg" class="badentry"></div>
		        </td>
		  	</tr>
		    <tr>
		    	<td valign="top" width="35%">
		    		<div class="rowdesc">
		    			{help_icon title="Server Admin Password" message="If this box is checked, you will need to specify this password in the game server before you can use your admin rights."}Server Password <small>(<a href="http://wiki.alliedmods.net/Adding_Admins_%28SourceMod%29#Passwords" title="SourceMod Password Info" target="_blank">More</a>)</small>
		    		</div>
		    	</td>
		    	<td>
		    		<div align="left">
		        		<input type="checkbox" id="a_useserverpass" name="a_useserverpass" TABINDEX=6 onclick="$('a_serverpass').disabled = !$(this).checked;" /> <input type="password" TABINDEX=7 class="textbox" style="width: 176px" name="a_serverpass" id="a_serverpass" disabled="disabled" />
		    		</div>
					<div id="a_serverpass.msg" class="badentry"></div>
		    	</td>
		  	</tr>
		</table>
	
		
		<br />
	
		
		<h3>Admin Access</h3>
			<table width="90%" border="0" style="border-collapse:collapse;" id="group.details" cellpadding="3">
		  	<tr>
		    	<td valign="top" width="35%">
		    		<div class="rowdesc">
		    			{help_icon title="Server" message="<b>Server: </b><br>Choose the server, or server group that this admin will be able to administer."}Server Access 
		    		</div>
		    	</td>
		    	<td>&nbsp;</td>
		  	</tr>
		  	<tr>
			  	<td colspan="2">
			  		<table width="90%" border="0" cellspacing="0" cellpadding="4" align="center">
						{foreach from="$group_list" item="group"}
							<tr>
								<td colspan="2" class="tablerow4">{$group.name}<b><i>(Group)</i></b></td>
								<td align="center" class="tablerow4"><input type="checkbox" id="group[]" name="group[]" value="g{$group.gid}" /></td>
							</tr>
						{/foreach}
					
						{foreach from="$server_list" item="server"}
							<tr class="tablerow1">
								<td colspan="2" class="tablerow1" id="sa{$server.sid}"><i>Retrieving Hostname... {$server.ip}:{$server.port}</i></td>
								<td align="center" class="tablerow1">
									<input type="checkbox" name="servers[]" id="servers[]" value="s{$server.sid}" />
						  		</td> 
							</tr>
						{/foreach}
			  		</table>
		  		</td>
			</tr>
		</table>
	
		
		
		<br />
		
		
		
		<h3>Admin Permissions</h3>
		<table width="90%" border="0" style="border-collapse:collapse;" id="group.details" cellpadding="3">
			<tr>
			    <td valign="top" width="35%">
			    	<div class="rowdesc">
			    		{help_icon title="Admin Group" message="<b>Custom Permisions: </b><br>Select this to choose custom permissions for this admin.<br><br><b>New Group: </b><br>Select this to choose custom permissions and then save the permissions as a new group.<br><br><b>Groups: </b><br>Select a pre-made group to add the admin to."}Server Admin Group 
			    	</div>
			    </td>
			    <td>
			    	<div align="left" id="admingroup">
				      	<select TABINDEX=8 onchange="update_server()" name="serverg" id="serverg" class="select" style="width: 225px">
					        <option value="-2">Please Select...</option>
					        <option value="-3">No Permissions</option>
					        <option value="c">Custom Permissions</option>
					        <option value="n">New Admin Group</option>
					        <optgroup label="Groups" style="font-weight:bold;">
						        {foreach from="$server_admin_group_list" item="server_wg"}
									<option value='{$server_wg.id}'>{$server_wg.name}</option>
								{/foreach}
							</optgroup>
				        </select>
			        </div>
			        <div id="server.msg" class="badentry"></div>
				</td>
		  	</tr>
		   	<tr>
		 		<td colspan="2" id="serverperm" valign="top" style="height:5px;overflow:hidden;"></td>
		 	</tr>
		   	<tr>
		    	<td valign="top">
		    		<div class="rowdesc">
		    			{help_icon title="Admin Group" message="<b>Custom Permisions: </b><br>Select this to choose custom permissions for this admin.<br><br><b>New Group: </b><br>Select this to choose custom permissions and then save the permissions as a new group.<br><br><b>Groups: </b><br>Select a pre-made group to add the admin to."}Web Admin Group 
		    		</div>
		    	</td>
		    	<td>
		    		<div align="left" id="webgroup">
						<select TABINDEX=9 onchange="update_web()" name="webg" id="webg" class="select" style="width: 225px">
							<option value="-2">Please Select...</option>
							<option value="-3">No Permissions</option>
							<option value="c">Custom Permissions</option>
							<option value="n">New Admin Group</option>
							<optgroup label="Groups" style="font-weight:bold;">
								{foreach from="$server_group_list" item="server_g"}
									<option value='{$server_g.gid}'>{$server_g.name}</option>
								{/foreach}
							</optgroup>
						</select>
		        	</div>
		        	<div id="web.msg" class="badentry"></div>
		       	</td>
		  	</tr>
		  	<tr>
		 		<td colspan="2" id="webperm" valign="top" style="height:5px;overflow:hidden;"></td>
		 	</tr>
		  	<tr>
		    	<td>&nbsp;</td>
		    	<td>
			    	{sb_button text="Add Admin" onclick="ProcessAddAdmin();" class="ok" id="aadmin" submit=false}
				      &nbsp;
				    {sb_button text="Back" onclick="history.go(-1)" class="cancel" id="aback"}
		      	</td>
		  	</tr>
		</table>
        {$server_script}
	</div>
{/if}
