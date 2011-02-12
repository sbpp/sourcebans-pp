{if NOT $permission_addban}
	Access Denied!
{else}
	{if NOT $groupbanning_enabled}
		This feature is disabled! Only follow links!
	{else}
		<h3>Add Group Ban</h3>
		{if NOT $list_steam_groups}
		Here you can add a ban for a whole steam community group.<br />
		e.g. <code>http://steamcommunity.com/groups/interwavestudios</code><br /><br />
		<table width="90%" style="border-collapse:collapse;" id="group.details" cellpadding="3">
		<tr>
			<td valign="top" width="35%">
				<div class="rowdesc">
					{help_icon title="Group Link" message="Type the link to a steam community group."}Group Link 
				</div>
			</td>
			<td>
				<div align="left">
					<input type="text" TABINDEX=1 class="submit-fields" id="groupurl" name="groupurl" />
				</div>
				<div id="groupurl.msg" class="badentry"></div>
			</td>
		</tr>
		<tr>
			<td valign="top" width="35%">
				<div class="rowdesc">
					{help_icon title="Group Ban Reason" message="Type the reason, why you are going to ban this steam community group."}Group Ban Reason 
				</div>
			</td>
			<td>
				<div align="left">
					<textarea class="submit-fields" TABINDEX=2 cols="30" rows="5" id="groupreason" name="groupreason" /></textarea>
				</div>
				<div id="groupreason.msg" class="badentry"></div>
			</td>
		</tr>
		<tr>
			<td>&nbsp;</td>
			<td>
				{sb_button text="Add Group Ban" onclick="ProcessGroupBan();" class="ok" id="agban" submit=false}
					  &nbsp;
				{sb_button text="Back" onclick="history.go(-1)" class="cancel" id="aback"}
			</td>
		</tr>
		</table>
		{else}
		All groups the player {$player_name} is member of are listed here.<br />
		Choose the steam groups you want to ban.<br /><br />
		<div id="steamGroupsText" name="steamGroupsText">Loading the groups...</div>
		<div id="steamGroups" name="steamGroups" style="display:none;">
			<table id="steamGroupsTable" name="steamGroupsTable" border="0" width="500px">
			<tr>
				<td height="16" class="listtable_1" style="padding:0px;width:3px;" align="center"><div class="ok" style="height:16px;width:16px;cursor:pointer;" id="tickswitch" name="tickswitch" onclick="TickSelectAll();"></div></td>
				<td height="16" class="listtable_top" align="center"><b>Group</b></td>
			</tr>
			</table>
			&nbsp;&nbsp;L&nbsp;&nbsp;<a href="#" onclick="TickSelectAll();return false;" title="Select All" name="tickswitchlink" id="tickswitchlink">Select All</a><br /><br />
			<table width="90%" style="border-collapse:collapse;" id="group.details" cellpadding="3">
				<tr>
					<td valign="top" width="35%">
						<div class="rowdesc">
							{help_icon title="Group Ban Reason" message="Type the reason, why you are going to ban this steam community group."}Group Ban Reason 
						</div>
					</td>
					<td>
						<div align="left">
							<textarea class="submit-fields" TABINDEX=2 cols="30" rows="5" id="groupreason" name="groupreason" /></textarea>
						</div>
						<div id="groupreason.msg" class="badentry"></div>
					</td>
				</tr>
			</table>
			<input type="button" class="btn ok" onclick="CheckGroupBan();" name="gban" id="gban" onmouseover="ButtonOver('gban');" onmouseout="ButtonOver('gban');" value="Add Group Ban">
		</div>
		<div id="steamGroupStatus" name="steamGroupStatus" width="100%"></div>
		<script type="text/javascript">$('tickswitch').value = 0;xajax_GetGroups('{$list_steam_groups}');</script>
		{/if}
	{/if}
{/if}