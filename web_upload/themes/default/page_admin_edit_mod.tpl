<form action="" method="post">
	<div id="add-group">
		<h3>Mod Details</h3>
		For more information or help regarding a certain subject move your mouse over the question mark.<br /><br />
		<input type="hidden" name="insert_type" value="add">
		<table width="90%" border="0" style="border-collapse:collapse;" id="group.details" cellpadding="3">
			<tr>
		    	<td valign="top" width="35%"><div class="rowdesc">{help_icon title="Mod Name" message="Type the name of the mod you are adding."}Mod Name</div></td>
		    	<td>
		    		<div align="left"> 
					    <input type="hidden" id="icon_hid" name="icon_hid" value="{$mod_icon}">
					    <input type="text" TABINDEX=1 class="textbox" id="name" name="name" value="{$name}" />
					</div>
					<div id="name.msg" class="badentry"></div>
				</td>
		  </tr>
		  
			<tr>
				<td valign="top"><div class="rowdesc">{help_icon title="Folder Name" message="Type the name of this mods folder. For example, Counter-Strike: Source's mod folder is 'cstrike'"}Mod Folder</div></td>
		    	<td>
		    		<div align="left">
		      			<input type="text" TABINDEX=2 class="textbox" id="folder" name="folder" value="{$folder}" />
		    		</div>
		    		<div id="folder.msg" class="badentry"></div>
		    	</td>
		  </tr>
      <tr>
				<td valign="top"><div class="rowdesc">{help_icon title="Steam Universe Number" message="(STEAM_<b>X</b>:Y:Z) Some games display the steamid differently than others. Type the first number in the SteamID (<b>X</b>) depending on how it's rendered by this mod. (Default: 0)."}Steam Universe Number</div></td>
		    	<td>
		    		<div align="left">
		      			<input type="text" TABINDEX=3 class="textbox" id="steam_universe" name="steam_universe" value="{$steam_universe}" />
		    		</div>
		    	</td>
		  </tr>
		  <tr>
			<td valign="top"><div class="rowdesc">{help_icon title="Mod Enabled" message="Select if this mod is enabled and assignable to bans and servers."}Enabled</div></td>
		    	<td>
		    		<div align="left">
		      			<input type="checkbox" TABINDEX=4 id="enabled" name="enabled" value="1" />
		    		</div>
		    	</td>
		  </tr>
		 	
			<tr>
				<td valign="top" width="35%"><div class="rowdesc">{help_icon title="Upload Icon" message="Click here to upload an icon to associate with this mod."}Upload Icon</div></td>
		    	<td>
		    		<div align="left">
		      			{sb_button text="Upload MOD Icon" onclick="childWindow=open('pages/admin.uploadicon.php','upload','resizable=yes,width=300,height=130');" class="save" id="upload" submit=false} 
		    		</div>
		    		<div id="icon.msg" class="badentry" style="display:block;">
			    		{if $mod_icon}
			    			Uploaded: <b>{$mod_icon}</b>
			    		{/if}
		    		</div>
		    	</td>
		  	</tr>
			<tr>
		    	<td>&nbsp;</td>
		    	<td>      
			    	{sb_button text="Save Changes" class="ok" id="editmod" submit=true}
			     	 &nbsp;
			     	 {sb_button text="Back" onclick="history.go(-1)" class="cancel" id="back" submit=false} 
		      	</td>
		  	</tr>
		</table>
	</div>
</form>
