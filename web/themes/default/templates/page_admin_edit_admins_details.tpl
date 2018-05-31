<form action="" method="post">
<div id="admin-page-content">

<div id="add-group">
<h3>Admin Details</h3>
<table width="90%" border="0" style="border-collapse:collapse;" id="group.details" cellpadding="3">
  <tr>
    <td valign="top" width="35%"><div class="rowdesc">{help_icon title="Admin Login" message="This is the username the admin will use to login-to their admin panel. Also this will identify the admin on any bans they make."}Admin Login </div></td>
    <td><div align="left">
        <input type="text" class="textbox" id="adminname" name="adminname" value="{$user}" />
      </div>
        <div id="adminname.msg" class="badentry"></div></td>
  </tr>
  <tr>
    <td valign="top"><div class="rowdesc">{help_icon title="Steam ID" message="This is the admins 'STEAM' id. This must be set so that admins can use their admin rights ingame."}Admin STEAM ID </div></td>
    <td><div align="left">
      <input type="text" class="textbox" id="steam" name="steam" value="{$authid}" />
    </div><div id="steam.msg" class="badentry"></div></td>
  </tr>
  <tr>
    <td valign="top"><div class="rowdesc">{help_icon title="Admin Email" message="Set the admins e-mail address. This will be used for sending out any automated messages from the system, and for use when you forget your password."}Admin Email </div></td>
    <td><div align="left">
        <input type="text" class="textbox" id="email" name="email" value="{$email}" />
      </div>
        <div id="email.msg" class="badentry"></div></td>
  </tr>
  
  {if $change_pass}
  <tr>
    <td valign="top"><div class="rowdesc">{help_icon title="Password" message="The password the admin will need to access the admin panel."}Admin Password </div></td>
    <td><div align="left">
        <input type="password" class="textbox" id="password" name="password" />
      </div>
        <div id="password.msg" class="badentry"></div></td>
  </tr>
  <tr>
    <td valign="top"><div class="rowdesc">{help_icon title="Password" message="Type your password again to confirm."}Admin Password (confirm) </div></td>
    <td><div align="left">
        <input type="password" class="textbox" id="password2" name="password2" />
      </div>
        <div id="password2.msg" class="badentry"></div></td>
  </tr>
  <tr>
    <td valign="top" width="35%">
      <div class="rowdesc">
        {help_icon title="Server Admin Password" message="If this box is checked, you will need to specify this password in the game server before you can use your admin rights."}Server Password <small>(<a href="http://wiki.alliedmods.net/Adding_Admins_%28SourceMod%29#Passwords" title="SourceMod Password Info" target="_blank">More</a>)</small>
      </div>
    </td>
    <td>
      <div align="left">
        <input type="checkbox" id="a_useserverpass" name="a_useserverpass"{if $a_spass} checked="checked"{/if} TABINDEX=6 onclick="$('a_serverpass').disabled = !$(this).checked;" /> <input type="password" TABINDEX=7 class="textbox" name="a_serverpass" id="a_serverpass"{if !$a_spass} disabled="disabled"{/if} />
      </div>
      <div id="a_serverpass.msg" class="badentry"></div>
    </td>
  </tr>
  
  {/if}
  
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
</div></div></form>
