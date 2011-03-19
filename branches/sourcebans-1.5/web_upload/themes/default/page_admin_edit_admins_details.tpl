<form action="" method="post">
<div id="admin-page-content">

<div id="add-group">
<h3>Admin Details</h3>
<table width="90%" border="0" style="border-collapse:collapse;" id="group.details" cellpadding="3">
  <tr>
    <td valign="top" width="35%"><div class="rowdesc">{help_icon title="Admin Login" message="This is the username the admin will use to login-to their admin panel. Also this will identify the admin on any bans they make."}Admin Login </div></td>
    <td><div align="left">
        <input type="text" class="submit-fields" id="adminname" name="adminname" />
      </div>
        <div id="name.msg" class="badentry"></div></td>
  </tr>
  <tr>
    <td valign="top"><div class="rowdesc">{help_icon title="Steam ID" message="This is the admins 'STEAM' id. This must be set so that admins can use their admin rights ingame."}Admin STEAM ID </div></td>
    <td><div align="left">
      <input type="text" value="STEAM_0:" class="submit-fields" id="steam" name="steam" />
    </div><div id="steam.msg" class="badentry"></div></td>
  </tr>
  <tr>
    <td valign="top"><div class="rowdesc">{help_icon title="Admin Email" message="Set the admins e-mail address. This will be used for sending out any automated messages from the system, and for use when you forget your password."}Admin Email </div></td>
    <td><div align="left">
        <input type="text" class="submit-fields" id="email" name="email" />
      </div>
        <div id="email.msg" class="badentry"></div></td>
  </tr>
  
  {if $change_pass}
  <tr>
    <td valign="top"><div class="rowdesc">{help_icon title="Password" message="The password the admin will need to access the admin panel."}Admin Password </div></td>
    <td><div align="left">
        <input type="password" class="submit-fields" id="password" name="password" />
      </div>
        <div id="password.msg" class="badentry"></div></td>
  </tr>
  <tr>
    <td valign="top"><div class="rowdesc">{help_icon title="Password" message="Type your password again to confirm."}Admin Password (confirm) </div></td>
    <td><div align="left">
        <input type="password" class="submit-fields" id="password2" name="password2" />
      </div>
        <div id="password2.msg" class="badentry"></div></td>
  </tr>
  <tr>
    <td valign="top" width="35%"><div class="rowdesc">{help_icon title="Server Admin Password" message="If this box is checked, you will need to specify this password in the game server before you can use your admin rights."}Use as admin password?</div></td>
    <td><div align="left">
      <input type="checkbox" name="a_spass" id="a_spass" /> <small>You need to change the password, to enable the serverpassword</small>
    </div>
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
<script>
$('adminname').value = "{$user}";
$('steam').value = "{$authid}";
$('email').value = "{$email}";
$('a_spass').checked = "{$a_spass}";


</script>
</div></div></form>
