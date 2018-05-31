<table style="width: 101%; margin: 0 0 -2px -2px;">
	<tr>
		<td colspan="3" class="listtable_top"><b>Protest a ban</b></td>
	</tr>
</table>
<div id="submit-main">
Before you proceed make sure you first check our banlist and search it by clicking <a href="index.php?p=banlist">here</a> if you are listed and for what reason.<br />
If you do find yourself listed on the banlist and find the reason for this to be untrue you can write a protest.<br /><br />
<form action="index.php?p=protest" method="post">
<input type="hidden" name="subprotest" value="1">
<table cellspacing='10' width='100%' align='center'>
<tr>
	<td colspan="3">
		Your Details:	</td>
</tr>
<tr>
	<td width="20%">Ban Type:</td>
	<td>
		<select id="Type" name="Type" class="select" style="width: 250px;" onChange="changeType(this[this.selectedIndex].value);">
			<option value="0">Steam ID</option>
			<option value="1">IP Address</option>
		</select>
	</td>
</tr>
<tr id="steam.row">
	<td width="20%">
		Your SteamID<span class="mandatory">*</span>:</td>
	<td>
		<input type="text" name="SteamID" size="40" maxlength="64" value="{$steam_id}" class="textbox" style="width: 223px;" />
	</td>
</tr>
<tr id="ip.row" style="display: none;">
	<td width="20%">
		Your IP<span class="mandatory">*</span>:</td>
	<td>
		<input type="text" name="IP" size="40" maxlength="64" value="{$ip}" class="textbox" style="width: 223px;" />
	</td>
</tr>
<tr>
	<td width="20%">
        Name<span class="mandatory">*</span>:</td>
	<td>
        <input type="text" size="40" maxlength="70" name="PlayerName" value="{$player_name}" class="textbox" style="width: 223px;" /></td>
    </tr>
<tr>
	<td width="20%" valign="top">
		Reason why you should be unbanned <span class="mandatory">*</span>: (Be as descriptive as possible) </td>
	<td><textarea name="BanReason" cols="30" rows="5" class="textbox" style="width: 223px;">{$reason}</textarea></td>
    </tr>
<tr>
	<td width="20%">
		Your Email<span class="mandatory">*</span>:	</td>
	<td>
		<input type="text" size="40" maxlength="70" name="EmailAddr" value="{$player_email}" class="textbox" style="width: 223px;" /></td>
    </tr>
<tr>
	<td width="20%"><span class="mandatory">*</span> = Mandatory Field</td>
	<td>
		{sb_button text=Submit class=ok id=alogin submit=true}
	</td>
    <td>&nbsp;</td>
</tr>
</table>
</form>
<b>What happens after you posted your protest?</b><br />
  The admins will get notified of your protest. They will then review if the ban is conclusive. After reviewing you will get a reply, which usally means within 24 hours.<br /><br />
  <b>Note:</b> Sending emails with threats to our admins, scolding or shouting will not get you unbanned and in fact we will delete your protest right away! 
</div>
