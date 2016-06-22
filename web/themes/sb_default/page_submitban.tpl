<div id="submit-introduction">
<h3>Submit a player</h3>
Here you will be able to submit a ban for a player who is breaking the rules of the gameserver. When submitting a ban we request you to fill out all the fields to be as descriptive as possible in your comments. This will ensure that your ban submission is processed much faster.<br /><br />
For a short explination on how to create a demo, click <a href="javascript:void(0)" onclick="ShowBox('How To Record A Demo', 'While you are spectating the offending player, press the ` key on your keyboard. Then type record [demoname] and hit enter. Also type sb_status for extra information in SteamBans servers. The file will be in your mod folder.', 'blue', '', true);">here</a>
</div>
<div id="submit-main">
<form action="index.php?p=submit" method="post" enctype="multipart/form-data">
<input type="hidden" name="subban" value="1">
<table cellspacing='10' width='100%' align='center'>
<tr>
	<td colspan="3">
		Ban Details:	</td>
</tr>
<tr>
	<td width="20%">
		Players SteamID:</td>
	<td>
		<input type="text" name="SteamID" size="40" maxlength="64" value="{$STEAMID}" class="submit-fields" />
	</td>
    <td rowspan="7" align="center" valign="top" width="200px"><img src="images/nocheat.jpg" alt="No Cheaters!" width="200" height="200" /></td>
</tr>
<tr>
	<td width="20%">
		Players IP:</td>
	<td>
		<input type="text" name="BanIP" size="40" maxlength="64" value="{$ban_ip}" class="submit-fields" />
	</td>
</tr>
<tr>
	<td width="20%">
        Players Nick Name<span class="mandatory">*</span>:</td>
	<td>
        <input type="text" size="40" maxlength="70" name="PlayerName" value="{$player_name}" class="submit-fields" /></td>
</tr>
<tr>
	<td width="20%" valign="top">
		Comments<span class="mandatory">*</span>:<br />
		(Please write down a descriptive comment. So NO comments like: "hacking")	</td>
	<td><textarea name="BanReason" cols="30" rows="5" class="submit-fields">{$ban_reason}</textarea></td>
    </tr>
<tr>
	<td width="20%">
		Your Name:	</td>
	<td>
		<input type="text" size="40" maxlength="70" name="SubmitName" value="{$subplayer_name}" class="submit-fields" />	</td>
    </tr>

<tr>
	<td width="20%">
		Your Email<span class="mandatory">*</span>:	</td>
	<td>
		<input type="text" size="40" maxlength="70" name="EmailAddr" value="{$player_email}" class="submit-fields" />	</td>
    </tr>
<tr>
	<td width="20%">
		Server<span class="mandatory">*</span>:	</td>
	<td colspan="2">
        <select id="server" name="server">
			<option value="-1">-- Select Server --</option>
			{foreach from="$server_list" item="server}
				<option value="{$server.sid}" {if $server_selected == $server.sid}selected{/if}>{$server.hostname}</option>
			{/foreach}
			<option value="0">Other server / Not listed here</option>
		</select> 
    </td>
    </tr>
<tr>
	<td width="20%">
		Upload demo:	</td>
	<td>
		<input name="demo_file" type="file" size="25" class="submit-fields" /><br />
		Note: Only DEM, <a href="http://www.winzip.com" target="_blank">ZIP</a>, <a href="http://www.rarlab.com" target="_blank">RAR</a>, <a href="http://www.7-zip.org" target="_blank">7Z</a>, <a href="http://www.bzip.org" target="_blank">BZ2</a> or <a href="http://www.gzip.org" target="_blank">GZ</a> allowed.	</td>
    </tr>
<tr>
	<td width="20%"><span class="mandatory">*</span> = Mandatory Field</td>
	<td>
		{sb_button text=Ok onclick="" class=ok id=save submit=true}
	</td>
    <td>&nbsp;</td>
</tr>
</table>
</form>
<b>What happens if someone gets banned?</b><br />
If someone gets banned, the specific STEAMID or IP will be included in this SourceBans database and everytime this player tries to connect to one of our servers he/she will be blocked and will receive a message that they are blocked by SourceBans. 
</div>
