{if NOT $permission_listgroups}
	Access Denied!
{else}
	<h3>Groups</h3>
	Click on a group to view its permissions. <br /><br />
	
	<!-- Web Admin Groups -->
	<table width="100%" cellpadding="0" cellspacing="0">
		<tr>
			<td colspan="4">
				<table width="100%" cellpadding="0" cellspacing="0" class="front-module-header" class="listtable">
					<tr>
						<td align="left">Web Admin Groups</td>
						<td align="right">Total: {$web_group_count}</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td width="40%" height='16' class="listtable_top"><strong>Group Name</strong></td>
			<td width="25%" height='16' class="listtable_top"><strong>Admins in group</strong></td>
			<td width="30%" height='16' class="listtable_top"><strong>Action</strong></td>
		</tr>
		{foreach from="$web_group_list" item="group" name="web_group"}
			<tr id="gid_{$group.gid}" class="opener tbl_out" onmouseout="this.className='tbl_out'" onmouseover="this.className='tbl_hover'">
				<td style="border-bottom: solid 1px #ccc" height='16'>{$group.name}</td>
		      	<td style="border-bottom: solid 1px #ccc" height='16'>{$web_admins[$smarty.foreach.web_group.index]}</td>
				<td style="border-bottom: solid 1px #ccc" height='16'> 
					{if $permission_editgroup}
			        	<a href="index.php?p=admin&c=groups&o=edit&type=web&id={$group.gid}">Edit</a>
			        {/if}
			        {if $permission_deletegroup}
			            - <a href="#" onclick="RemoveGroup({$group.gid}, '{$group.name}', 'web');">Delete</a>
					{/if}
				</td>
			</tr>
			<tr>	 
		    	<td colspan="7" align="center">     	
		      	<div class="opener"> 
					<table width="80%" cellspacing="0" cellpadding="0" class="listtable">
		          		<tr>
		            		<td height="16" align="left" class="listtable_top" colspan="3">
								<b>Group Details</b>            
							</td>
		          		</tr>
		          		<tr align="left">
		            		<td width="20%" height="16" class="listtable_1">Permissions</td>
		            		<td height="16" class="listtable_1">{$group.permissions}</td>
		           		</tr>
						<tr align="left">
		            		<td width="20%" height="16" class="listtable_1">Members</td>
		            		<td height="16" class="listtable_1">
								<table width="100%" cellspacing="0" cellpadding="0" class="listtable">
								{foreach from=$web_admins_list[$smarty.foreach.web_group.index] item="web_admin"}
									<tr>
										<td width="60%" height="16" class="listtable_1">{$web_admin.user}</td>
										{if $permission_editadmin}
										<td width="20%" height="16" class="listtable_1"><a href="index.php?p=admin&c=admins&o=editgroup&id={$web_admin.aid}" title="Edit Groups">Edit</a></td>
										<td width="20%" height="16" class="listtable_1"><a href="index.php?p=admin&c=admins&o=editgroup&id={$web_admin.aid}&wg=" title="Remove From Group">Remove</a></td>
										{/if}
									</tr>
								{/foreach}
								</table>
							</td>
		           		</tr>
		        	</table>		
		     	</div>
		    </td> 	
		</tr>        
		{/foreach}
	</table>
	<br /><br />
	
	<!-- Server Admin Groups -->
	<table width="100%" cellpadding="0" cellspacing="0">
	<tr>
		<td colspan="4">
			<table width="100%" cellpadding="0" cellspacing="0" class="front-module-header" class="listtable">
				<tr>
					<td align="left">Server Admin Groups</td>
					<td align="right">Total: {$server_admin_group_count}</td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td width="40%" height='16' class="listtable_top"><strong>Group Name</strong></td>
      	<td width="25%" height='16' class="listtable_top"><strong>Admins in group</strong></td>
		<td width="30%" height='16' class="listtable_top"><strong>Action</strong></td>
	</tr>
	{foreach from="$server_group_list" item="group" name="server_admin_group"}
		<tr id="gid_{$group.id}" class="opener tbl_out" onmouseout="this.className='tbl_out'" onmouseover="this.setProperty('class', 'tbl_hover')">
			<td style="border-bottom: solid 1px #ccc" height='16'>{$group.name}</td>
	      	<td style="border-bottom: solid 1px #ccc" height='16'>{$server_admins[$smarty.foreach.server_admin_group.index]}</td>
	        <td style="border-bottom: solid 1px #ccc" height='16'> 
				{if $permission_editgroup}
					<a href="index.php?p=admin&c=groups&o=edit&type=srv&id={$group.id}">Edit</a>
				{/if}
				{if $permission_deletegroup}
					- <a href="#" onclick="RemoveGroup({$group.id}, '{$group.name}', 'srv');">Delete</a>
				{/if}
			</td>
		</tr>
		<tr>	 
    		<td colspan="7" align="center">     	
      			<div class="opener"> 
					<table width="80%" cellspacing="0" cellpadding="0" class="listtable">
          				<tr>
            				<td height="16" align="left" class="listtable_top" colspan="3">
								<b>Group Details</b>            
							</td>
	          			</tr>
	          			<tr align="left">
	            			<td width="20%" height="16" class="listtable_1">Permissions</td>
	            			<td height="16" class="listtable_1">{$group.permissions}</td>
	           			</tr>
						<tr align="left">
		            		<td width="20%" height="16" class="listtable_1">Members</td>
		            		<td height="16" class="listtable_1">
								<table width="100%" cellspacing="0" cellpadding="0" class="listtable">
								{foreach from=$server_admins_list[$smarty.foreach.server_admin_group.index] item="server_admin"}
									<tr>
										<td width="60%" height="16" class="listtable_1">{$server_admin.user}</td>
										{if $permission_editadmin}
										<td width="20%" height="16" class="listtable_1"><a href="index.php?p=admin&c=admins&o=editgroup&id={$server_admin.aid}" title="Edit Groups">Edit</a></td>
										<td width="20%" height="16" class="listtable_1"><a href="index.php?p=admin&c=admins&o=editgroup&id={$server_admin.aid}&sg=" title="Remove From Group">Remove</a></td>
										{/if}
									</tr>
								{/foreach}
								</table>
							</td>
		           		</tr>
							<tr align="left">
		            <td width="20%" height="16" class="listtable_1">Overrides</td>
		            <td height="16" class="listtable_1">
									<table width="100%" cellspacing="0" cellpadding="0" class="listtable">
										<tr>
											<td class="listtable_top">Type</td>
											<td class="listtable_top">Name</td>
											<td class="listtable_top">Access</td>
										</tr>
										{foreach from=$server_overrides_list[$smarty.foreach.server_admin_group.index] item="override"}
										<tr>
											<td width="60%" height="16" class="listtable_1">{$override.type}</td>
											<td width="60%" height="16" class="listtable_1">{$override.name|htmlspecialchars}</td>
											<td width="60%" height="16" class="listtable_1">{$override.access}</td>
										</tr>
										{/foreach}
									</table>
								</td>
		           </tr>
	        	</table>		
	     		</div>
	     	</td> 	
	  	</tr>      
	{/foreach}
	</table>
	<br /><br />


	<!-- Server Groups -->
	<table width="100%" cellpadding="0" cellspacing="0">
		<tr>
			<td colspan="4">
				<table width="100%" cellpadding="0" cellspacing="0" class="front-module-header">
					<tr>
						<td align="left">Server Groups</td>
						<td align="right">Total: {$server_group_count}</td>
					</tr>
				</table>
			</td>
		</tr>
		<tr>
			<td width="37%" height='16' class="listtable_top"><strong>Group Name</strong></td>
			<td width="25%" height='16' class="listtable_top"><strong>Servers in group</strong></td>
			<td width="30%" height='16' class="listtable_top"><strong>Action</strong></td>
		</tr>
		{foreach from="$server_list" item="group" name="server_group"}
			<tr id="gid_{$group.gid}" class="opener tbl_out" onmouseout="this.className='tbl_out'" onmouseover="this.setProperty('class', 'tbl_hover')">
	            <td style="border-bottom: solid 1px #ccc" height='16'>{$group.name}</td>
	      		<td style="border-bottom: solid 1px #ccc" height='16'>{$server_counts[$smarty.foreach.server_group.index]}</td>
	            <td style="border-bottom: solid 1px #ccc" height='16'>   
	            {if $permission_editgroup}
					<a href="index.php?p=admin&c=groups&o=edit&type=server&id={$group.gid}">Edit</a>
				{/if}
				{if $permission_deletegroup}
					- <a href="#" onclick="RemoveGroup({$group.gid}, '{$group.name}', 'server');">Delete</a>
				{/if}        
	            </td>
			</tr>
			<tr>	 
	    		<td colspan="7" align="center">     	
	      			<div class="opener"> 
						<table width="80%" cellspacing="0" cellpadding="0" class="listtable">
	          				<tr>
	            				<td height="16" align="left" class="listtable_top" colspan="3"><b>Servers in this group</b></td>
	          				</tr>
	          				<tr align="left">
	            				<td width="20%" height="16" class="listtable_1">Server Names</td>
	            				<td height="16" class="listtable_1" id="servers_{$group.gid}">
	            					Please Wait!
		            			</td>
	           				</tr>
	        			</table>		
	     			</div>
	     		</td> 	
	  		</tr> 
		{/foreach}
	</table>
{/if}
