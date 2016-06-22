{if NOT $permission_addadmin}
	Access Denied!
{else}
	{if $overrides_error != ""}
		<script type="text/javascript">ShowBox("Error", "{$overrides_error}", "red");</script>
	{/if}
	{if $overrides_save_success}
		<script type="text/javascript">ShowBox("Overrides updated", "The changes have been saved successfully.", "green", "index.php?p=admin&c=admins");</script>
	{/if}
	<div id="add-group">
		<form action="" method="post">
		<h3>Overrides</h3>
		With Overrides you can change the flags or permissions on any command, either globally, or for a specific group, without editing plugin source code.<br />
		<i>Read about <b><a href="http://wiki.alliedmods.net/Overriding_Command_Access_%28SourceMod%29" title="Overriding Command Access (SourceMod)" target="_blank">overriding command access</a></b> in the AlliedModders Wiki!</i><br /><br />
		Blanking out an overrides' name will delete it.<br /><br />
		<table align="center" cellspacing="0" cellpadding="4" id="overrides" width="90%">
			<tr>
				<td class="tablerow4">Type</td>
				<td class="tablerow4">Name</td>
				<td class="tablerow4">Flags</td>
			</tr>
			{foreach from=$overrides_list item=override}
			<tr>
				<td class="tablerow1">
					<select name="override_type[]">
						<option{if $override.type == "command"} selected="selected"{/if} value="command">Command</option>
						<option{if $override.type == "group"} selected="selected"{/if} value="group">Group</option>
					</select>
					<input type="hidden" name="override_id[]" value="{$override.id}" />
				</td>
				<td class="tablerow1"><input name="override_name[]" value="{$override.name|htmlspecialchars}" /></td>
				<td class="tablerow1"><input name="override_flags[]" value="{$override.flags|htmlspecialchars}" /></td>
			</tr>
			{/foreach}
			<tr>
				<td class="tablerow1">
					<select name="new_override_type">
						<option value="command">Command</option>
						<option value="group">Group</option>
					</select>
				</td>
				<td class="tablerow1"><input name="new_override_name" /></td>
				<td class="tablerow1"><input name="new_override_flags" /></td>
			</tr>
		</table>
		<center>
			{sb_button text="Save" class="ok" id="oversave" submit=true}
			{sb_button text="Back" onclick="history.go(-1)" class="cancel" id="oback"}
		</center>
		</form>
	</div>
{/if}
