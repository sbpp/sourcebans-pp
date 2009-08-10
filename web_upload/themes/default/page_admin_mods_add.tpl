{if NOT $permission_add}
	Access Denied!
{else}
	<div id="add-group1">
		<h3>Add Mod</h3>
		For more information or help regarding a certain subject move your mouse over the question mark.<br /><br />
		<table width="90%" style="border-collapse:collapse;" id="group.details" cellpadding="3">
			<tr>
		    	<td valign="top" width="35%">
		    		<div class="rowdesc">
		    			{help_icon title="Mod Name" message="Type the name of the mod you are adding."}Mod Name 
		    		</div>
		    	</td>
		    	<td>
		    		<div align="left">
		    			<input type="hidden" id="fromsub" value="" />
		      			<input type="text" TABINDEX=1 class="submit-fields" id="name" name="name" />
		    		</div>
		    		<div id="name.msg" class="badentry"></div>
		    	</td>
			</tr>
		  	<tr>
		    	<td valign="top">
		    		<div class="rowdesc">
		    			{help_icon title="Folder Name" message="Type the name of this mods folder. For example, Counter-Strike: Source's mod folder is 'cstrike'"}Mod Folder 
		    		</div>
		    	</td>
		    	<td>
			    	<div align="left">
			      		<input type="text" TABINDEX=2 class="submit-fields" id="folder" name="folder" />
			    	</div>
			    	<div id="folder.msg" class="badentry"></div>
				</td>
			</tr>
		  	<tr>
		    	<td valign="top" width="35%">
		    		<div class="rowdesc">
		    			{help_icon title="Upload Icon" message="Click here to upload an icon to associate with this mod."}Upload Icon
		    		</div>
		    	</td>
		    	<td>
		    		<div align="left">
		      			{sb_button text="Upload Mod Icon" onclick="childWindow=open('pages/admin.uploadicon.php','upload','resizable=yes,width=300,height=130');" class="save" id="upload"}
		    		</div>
		    		<div id="icon.msg" style="color:#CC0000;"></div>
		    	</td>
		  	</tr>
		 	<tr>
		    	<td>&nbsp;</td>
		    	<td>		
			     	{sb_button text="Add Mod" onclick="ProcessMod();" class="ok" id="amod"}&nbsp;
			     	{sb_button text="Back" onclick="history.go(-1)" class="cancel" id="aback"}      
		      	</td>
		  	</tr>
		</table>
	</div>
{/if}
