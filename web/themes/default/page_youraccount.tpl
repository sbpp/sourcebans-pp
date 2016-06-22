<div id="admin-page-content">


<div id="0"> <!-- div ID 0 is the first 'panel' to be shown -->
<h3>Your Permissions</h3>
The following is a list of the permissions that you have on this system.<br /><br /> <br />
<table width="100%" border="0">
  <tr>
    <td width="35%" valign="top">-{$web_permissions}-</td>
    <td valign="top">-{$server_permissions}-</td>
  </tr>
</table>
</div>


<div id="1" style="display:none;"> <!-- div ID 1 is the second 'panel' to be shown -->
<h3>Change Password</h3>
<table width="90%" border="0" style="border-collapse:collapse;" id="group.details" cellpadding="3">
  <tr>
    <td valign="top" width="35%"><div class="rowdesc">-{help_icon title="Current Password" message="We need to know your current password to verify its you."}-Current Password</div></td>
    <td><div align="left">
        <input type="password" onblur="xajax_CheckPassword(-{$user_aid}-, $('current').value);" class="textbox" id="current" name="current" />
      </div>
        <div id="current.msg" class="badentry"></div></td>
  </tr>
  <tr>
  <td>&nbsp;</td>
  <td>&nbsp;</td>
  </tr>
  <tr>
    <td valign="top"><div class="rowdesc">-{help_icon title="New password" message="Type your new password here. <br /><br /><i>Min Length: $min_pass_len</i>"}-New Password</div></td>
    <td><div align="left">
      <input class="textbox" type="password" onkeyup="checkYourAcctPass();" id="pass1" value="" name="pass1" />
    </div><div id="pass1.msg" class="badentry"></div></td>
  </tr>
  <tr>
    <td valign="top"><div class="rowdesc">-{help_icon title="Confirm Password" message="Please type your new password again to avoid a mistake"}-Confirm Password</div></td>
    <td><div align="left">
        <input type="password" onkeyup="checkYourAcctPass();" class="textbox" id="pass2" name="pass2" />
      </div>
        <div id="pass2.msg" class="badentry"></div></td>
  </tr>
  
  <tr>
    <td>&nbsp;</td>
    <td>
      <input type="submit" onclick="xajax_CheckPassword(-{$user_aid}-, $('current').value);dispatch();" name="button" class="btn ok" id="button" value="Save" />
      &nbsp; <input type="submit" onclick="history.go(-1)" name="button" class="btn cancel" id="button" value="Cancel" />	</td>
  </tr>
</table>
</div>


<div id="2" style="display:none;"> <!-- div ID 2 is the third 'panel' to be shown -->
<h3>Change Server Password</h3>
You will need to specify this password in the game server before you can use your admin rights.<br />Click <a href="http://wiki.alliedmods.net/Adding_Admins_%28SourceMod%29#Passwords" title="SourceMod Password Info" target="_blank"><b>here</b></a> for more infos.
<table width="90%" border="0" style="border-collapse:collapse;" id="group.details" cellpadding="3">
  -{if $srvpwset}-
  <tr>
    <td valign="top" width="35%"><div class="rowdesc">-{help_icon title="Current Password" message="We need to know your current password to verify its you."}-Current Server Password</div></td>
    <td><div align="left">
        <input type="password" onblur="xajax_CheckSrvPassword(-{$user_aid}-, $('scurrent').value);" class="textbox" id="scurrent" name="scurrent" />
      </div>
        <div id="scurrent.msg" class="badentry"></div></td>
  </tr>
  <tr>
  <td>&nbsp;</td>
  <td>&nbsp;</td>
  </tr>
  -{/if}-
  <tr>
    <td valign="top"><div class="rowdesc">-{help_icon title="New password" message="Type your new server password here. <br /><br /><i>Min Length: $min_pass_len"}-New Password</div></td>
    <td><div align="left">
      <input class="textbox" type="password" onkeyup="checkYourSrvPass();" id="spass1" value="" name="spass1" />
    </div><div id="spass1.msg" class="badentry"></div></td>
  </tr>
  <tr>
    <td valign="top"><div class="rowdesc">-{help_icon title="Confirm Password" message="Please type your new server password again to avoid a mistake."}-Confirm Password</div></td>
    <td><div align="left">
        <input type="password" onkeyup="checkYourSrvPass();" class="textbox" id="spass2" name="spass2" />
      </div>
        <div id="spass2.msg" class="badentry"></div></td>
  </tr>
   -{if $srvpwset}-
  <tr>
  <td valign="top"><div class="rowdesc">-{help_icon title="Remove Server Password" message="Check this box, if you want to delete your server password"}-Remove Password</div></td>
  <td><div align="left">
    <input type="checkbox" id="delspass" name="delspass" />
    </div>
  </td>
  </tr>
  -{/if}-
  <tr>
    <td>&nbsp;</td>
    <td>
      <input type="submit" onclick="-{if $srvpwset}-xajax_CheckSrvPassword(-{$user_aid}-, $('scurrent').value);-{/if}-srvdispatch();" name="button" class="btn ok" id="button" value="Save" />
      &nbsp; <input type="submit" onclick="history.go(-1)" name="button" class="btn cancel" id="button" value="Cancel" />	</td>
  </tr>
</table>
</div>


<div id="3" style="display:none;"> <!-- div ID 3 is the fourth 'panel' to be shown -->
<h3>Change E-Mail</h3>
<table width="90%" border="0" style="border-collapse:collapse;" id="group.details" cellpadding="3">
  <tr>
	  <td valign="top"><div class="rowdesc">-{help_icon title="Current E-Mail" message="This is your current saved E-mail address."}-Current E-Mail</div></td>
    <td><div align="left">-{$email}-</div></td>
  </tr>
  <tr>
    <td valign="top"><div class="rowdesc">-{help_icon title="Current Password" message="Type your password here."}-Password</div></td>
    <td><div align="left">
      <input class="textbox" type="password" id="emailpw" value="" name="emailpw" />
    </div><div id="emailpw.msg" class="badentry"></div></td>
  </tr>
  <tr>
    <td valign="top"><div class="rowdesc">-{help_icon title="New E-mail" message="Type your new email address here."}-New E-mail</div></td>
    <td><div align="left">
      <input class="textbox" type="text" id="email1" value="" name="email1" />
    </div><div id="email1.msg" class="badentry"></div></td>
  </tr>
  <tr>
    <td valign="top"><div class="rowdesc">-{help_icon title="Confirm E-mail" message="Please type your new email address again to avoid a mistake."}-Confirm E-mail</div></td>
    <td><div align="left">
        <input type="text" class="textbox" id="email2" name="email2" />
      </div>
        <div id="email2.msg" class="badentry"></div></td>
  </tr>
  <tr>
    <td>&nbsp;</td>
    <td>
      <input type="submit" onclick="checkmail();" name="button" class="btn ok" id="button" value="Save" />
      &nbsp; <input type="submit" onclick="history.go(-1)" name="button" class="btn cancel" id="button" value="Cancel" />	</td>
  </tr>
</table>
</div>
<script>
var error = 0;
	function set_error(count)
	{
		error = count;
	}
function checkYourAcctPass()
	{
		var err = 0;
		
		if($('pass1').value.length < -{$min_pass_len}-)
		{
			$('pass1.msg').setStyle('display', 'block');
			$('pass1.msg').setHTML('Your password must be atleast -{$min_pass_len}- letters long');
			err++;
		}
		else
		{
			$('pass1.msg').setStyle('display', 'none');
		}
		if($('pass2').value != "" && $('pass2').value != $('pass1').value)
		{	
			$('pass2.msg').setStyle('display', 'block');
			$('pass2.msg').setHTML('Your passwords dont match');
			err++;
		}else{
			$('pass2.msg').setStyle('display', 'none');
		}
		if(err > 0)
		{
			set_error(1);
			return false;
		}
		else
		{
			set_error(0);
			return true;
		}	
	}
	function dispatch()
	{
		if($('current.msg').innerHTML == "Incorrect password.")
		{
			alert("Incorrect Password");
			return false;
		}
		if(checkYourAcctPass() && error == 0)
		{
			xajax_ChangePassword(-{$user_aid}-, $('pass2').value);
		}
	}
	function checkYourSrvPass()
	{
		if(!$('delspass') || $('delspass').checked == false)
		{
			var err = 0;
			
			if($('spass1').value.length < -{$min_pass_len}-)
			{
				$('spass1.msg').setStyle('display', 'block');
				$('spass1.msg').setHTML('Your password must be atleast -{$min_pass_len}- letters long');
				err++;
			}
			else
			{
				$('spass1.msg').setStyle('display', 'none');
			}
			if($('spass2').value != "" && $('spass2').value != $('spass1').value)
			{	
				$('spass2.msg').setStyle('display', 'block');
				$('spass2.msg').setHTML('Your passwords dont match');
				err++;
			}else{
				$('spass2.msg').setStyle('display', 'none');
			}
			if(err > 0)
			{
				set_error(1);
				return false;
			}
			else
			{
				set_error(0);
				return true;
			}	
		}
		else
		{
			set_error(0);
			return true;
		}	
	}
	function srvdispatch()
	{
		-{if $srvpwset}-
		if($('scurrent.msg').innerHTML == "Incorrect password.")
		{
			alert("Incorrect Password");
			return false;
		}
		-{/if}-
		if(checkYourSrvPass() && error == 0 && (!$('delspass') || $('delspass').checked == false))
		{
			xajax_ChangeSrvPassword(-{$user_aid}-, $('spass2').value);
		}
		if($('delspass').checked == true)
		{
			xajax_ChangeSrvPassword(-{$user_aid}-, 'NULL');
		}
	}
	function checkmail()
	{
		var err = 0;
        if($('email1').value == "")
        {
            $('email1.msg').setStyle('display', 'block');
			$('email1.msg').setHTML('Please type the new E-mail.');
			err++;
		}else{
			$('email1.msg').setStyle('display', 'none');
		}
        
        if($('email2').value == "")
        {
            $('email2.msg').setStyle('display', 'block');
			$('email2.msg').setHTML('Please retype the new E-mail.');
			err++;
		}else{
			$('email2.msg').setStyle('display', 'none');
		}
         
		if(err == 0 && $('email2').value != $('email1').value)
		{	
			$('email2.msg').setStyle('display', 'block');
			$('email2.msg').setHTML('The typed E-mails doesn\'t match.');
			err++;
		}
        
        if($('emailpw').value == "")
        {
            $('emailpw.msg').setStyle('display', 'block');
			$('emailpw.msg').setHTML('Please type your password.');
			err++;
		}else{
			$('emailpw.msg').setStyle('display', 'none');
		}
        
		if(err > 0)
		{
			set_error(1);
			return false;
		}
		else
		{
			set_error(0);
		}
		if(error == 0)
		{
			xajax_ChangeEmail(-{$user_aid}-, $('email2').value, $('emailpw').value);
		}
	}
</script>
</div>	
