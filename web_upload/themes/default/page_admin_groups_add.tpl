{if NOT $permission_addgroup}
	Access Denied!
{else}
	<div id="add-group">
		<h3>New Group</h3>
		<table width="90%" style="border-collapse:collapse;" id="group.details" cellpadding="3">
	  	<tr>
	    	<td valign="top" width="35%">
	    		<div class="rowdesc">
	    			{help_icon title="Group Name" message="Type the name of the new group you want to create."}Group Name 
	    		</div>
	    	</td>
	    	<td>
	    		<div align="left">
	      			<input type="text" TABINDEX=1 class="submit-fields" id="groupname" name="groupname" />
	    		</div>
	    		<div id="name.msg" class="badentry"></div>
	    	</td>
	  	</tr>
	  	<tr>
	    	<td valign="top">
	    		<div class="rowdesc">
	    			{help_icon title="Group Type" message="This defines the type of group you are about to create. This helps identify and catagorize the groups list."}Group Type 
	    		</div>
	    	</td>
	    	<td>
	    		<div align="left">
					<select onchange="UpdateGroupPermissionCheckBoxes()" TABINDEX=2 class="submit-fields" name="grouptype" id="grouptype">
						<option value="0">Please Select...</option>
						<option value="1">Web Admin Group</option>
						<option value="2">Server Admin Group</option>
						<option value="3">Server Group</option>
					</select>
	    		</div>
	    		<div id="type.msg" class="badentry"></div>
	    	</td>
	  		</tr>
	 		<tr>
	 			<td colspan="2" id="perms" valign="top" style="height:5px;overflow:hidden;"></td>
	 		</tr>
	  		<tr>
		    	<td>&nbsp;</td>
		    	<td>      
			    	{sb_button text="Save Changes" onclick="ProcessGroup();" class="ok" id="agroup" submit=false}
				     	 &nbsp;
				    {sb_button text="Back" onclick="history.go(-1)" class="cancel" id="back" submit=false} 
		      	</td>
	  		</tr>
		</table>
	</div>
{/if}
