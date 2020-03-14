<table style="width: 101%; margin: 0 0 -2px -2px;">
    <tr>
        <td colspan="3" class="listtable_top"><b>Submit a Report</b></td>
    </tr>
</table>
<div id="submit-main">
    In order to keep our servers running smoothly, offenders of our rules should be punished and we can't always be on call to help.<br />
    When submitting a player report, we ask you to fill out the report as detailed as possible to help ban the offender as this will help us process your report quickly.<br />
    If you are unsure on how to record evidence within in-game, please click
    <a href="javascript:void(0)" onclick="ShowBox('How To Record Evidence', 'The best way to record evidence on someone breaking the rules would be to use Shadow Play or Plays.TV. Both pieces of software will record your game 24/7 with little to no impact on your game and you simply press a keybind to record the last X amount of minutes of gameplay which is perfect for catching rule breakers.<br /><br /> Alternatively, you can use the old method of using demos. While you are spectating the offending player, press the ` key on your keyboard to show the Developers Console. If this does not show, you will need to go into your Game Settings and enable this. Then type `record [demoname]` and hit enter, the file will then be in your mod folder of your game directory.', 'blue', '', true);">here</a> for an explanation.<br /><br />
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
                    <input type="text" name="SteamID" size="40" maxlength="64" value="{$STEAMID}" class="textbox" style="width: 250px;" />
                </td>
            </tr>
            <tr>
                <td width="20%">
                    Players IP:</td>
                <td>
                    <input type="text" name="BanIP" size="40" maxlength="64" value="{$ban_ip}" class="textbox" style="width: 250px;" />
                </td>
            </tr>
            <tr>
                <td width="20%">
                    Players Nickname<span class="mandatory">*</span>:</td>
                <td>
                    <input type="text" size="40" maxlength="70" name="PlayerName" value="{$player_name}" class="textbox" style="width: 250px;" /></td>
            </tr>
            <tr>
                <td width="20%" valign="top">
                    Comments<span class="mandatory">*</span>:<br />
                    (Please write down a descriptive comment. So NO comments like: "hacking")	</td>
                <td><textarea name="BanReason" cols="30" rows="5" class="textbox" style="width: 250px;">{$ban_reason}</textarea></td>
            </tr>
            <tr>
                <td width="20%">
                    Your Name:	</td>
                <td>
                    <input type="text" size="40" maxlength="70" name="SubmitName" value="{$subplayer_name}" class="textbox" style="width: 250px;" />	</td>
            </tr>

            <tr>
                <td width="20%">
                    Your Email<span class="mandatory">*</span>:	</td>
                <td>
                    <input type="text" size="40" maxlength="70" name="EmailAddr" value="{$player_email}" class="textbox" style="width: 250px;" />	</td>
            </tr>
            <tr>
                <td width="20%">
                    Server<span class="mandatory">*</span>:	</td>
                <td colspan="2">
                    <select id="server" name="server" class="select" style="width: 277px;">
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
		<input name="demo_file" type="file" size="25" class="file" style="width: 268px;" /><br />
		Note: Only DEM, <a href="http://www.winzip.com" target="_blank">ZIP</a>, <a href="http://www.rarlab.com" target="_blank">RAR</a>, <a href="http://www.7-zip.org" target="_blank">7Z</a>, <a href="http://www.bzip.org" target="_blank">BZ2</a> or <a href="http://www.gzip.org" target="_blank">GZ</a> allowed.	</td>
    </tr>
<tr>
	<td width="20%"><span class="mandatory">*</span> = Mandatory Field</td>
	<td>
		{sb_button text=Submit onclick="" class=ok id=save submit=true}
	</td>
    <td>&nbsp;</td>
</tr>
</table>
</form>
<b>What happens if someone gets banned?</b><br />
If someone you reported gets banned, the SteamID or IP will be included onto the ban on the main bans list and everytime they try to connect to any server they will be blocked from joining and it will be logged into our database.
</div>
