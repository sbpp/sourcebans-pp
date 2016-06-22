<form action="" method="post">
	<div id="admin-page-content">
	<div id="add-group">
		<h3>Admin Groups</h3>
		For more information or help regarding a certain subject move your mouse over the question mark.<br /><br />
		Choose the new groups that you want <b>{$group_admin_name}</b> to appear in.<br /><br />
		<table width="90%" border="0" style="border-collapse:collapse;" id="group.details" cellpadding="3">
		  <tr>
		    <td valign="middle"><div class="rowdesc">{help_icon title="Web Group" message="Choose the group you want this admin to appear in for web permissions"}Web Admin Group</div></td>
		    <td>
		    	<div align="left" id="wadmingroup">
			      	<select name="wg" id="wg" class="submit-fields">
				        <option value="-1">No Group</option>
				        <optgroup label="Groups" style="font-weight:bold;">
							{foreach from=$web_lst item=wg}
							<option value="{$wg.gid}"{if $wg.gid == $group_admin_id} selected="selected"{/if}>{$wg.name}</option>
							{/foreach}
						</optgroup>
			        </select>
		        </div>
		        <div id="wgroup.msg" class="badentry"></div>
		        </td>
		  </tr>
		  
		  <tr id="nsgroup" valign="top" style="height:5px;overflow:hidden;">
		 </tr>
		 
		 <tr>
		    <td valign="middle"><div class="rowdesc">{help_icon title="Server Group" message="Choose the group you want this admin to appear in for server admin permissions"}Server Admin Group </div></td>
		    <td><div align="left" id="wadmingroup">
		      <select name="sg" id="sg" class="submit-fields">
		        <option value="-1">No Group</option>
		        
		        <optgroup label="Groups" style="font-weight:bold;">
					{foreach from=$group_lst item=sg}
					<option value="{$sg.id}"{if $sg.id == $server_admin_group_id} selected="selected"{/if}>{$sg.name}</option>
					{/foreach}
				</optgroup>
		        </select>
		        </div>
		        <div id="sgroup.msg" class="badentry"></div>
		        </td>
		  </tr>
		 
		  <tr>
		    <td>&nbsp;</td>
		    <td>
		      {sb_button text="Save Changes" class="ok" id="agroups" submit=true}
		      &nbsp;
		      {sb_button text="Back" onclick="history.go(-1)" class="cancel" id="aback"}
		      </td>
		  </tr>
		</table>
		</div>
	</div>
</form>
