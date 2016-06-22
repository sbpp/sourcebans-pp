<?php
	if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
	
	$web_cfg = "<?php
/**
 * config.php
 * 
 * This file contains all of the configuration for the db
 * that will 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SteamFriends (www.SteamFriends.com)
 * @package SourceBans
 */
if(!defined('IN_SB')){echo 'You should not be here. Only follow links!';die();}

define('DB_HOST', '{server}');   					// The host/ip to your SQL server
define('DB_USER', '{user}');						// The username to connect with
define('DB_PASS', '{pass}');						// The password
define('DB_NAME', '{db}');  						// Database name	
define('DB_PREFIX', '{prefix}');					// The table prefix for SourceBans
define('DB_PORT','{port}');							// The SQL port (Default: 3306)
define('STEAMAPIKEY','{steamapikey}');				// Steam API Key for Shizz
define('SB_WP_URL','{sbwpurl}');       				//URL of SourceBans Site

//define('DEVELOPER_MODE', true);			// Use if you want to show debugmessages
//define('SB_MEM', '128M'); 				// Override php memory limit, if isn't enough (Banlist is just a blank page)
?>";

	$srv_cfg = '"driver_default"		"mysql"
	
	"sourcebans"
	{
		"driver"			"default"
		"host"				"{server}"
		"database"			"{db}"
		"user"				"{user}"
		"pass"				"{pass}"
		//"timeout"			"0"
		"port"			"{port}"
	}
';
	
	$web_cfg = str_replace("{server}", $_POST['server'], $web_cfg);
	$web_cfg = str_replace("{user}", $_POST['username'], $web_cfg);
	$web_cfg = str_replace("{pass}", $_POST['password'], $web_cfg);
	$web_cfg = str_replace("{db}", $_POST['database'], $web_cfg);
	$web_cfg = str_replace("{prefix}", $_POST['prefix'], $web_cfg);
	$web_cfg = str_replace("{port}", $_POST['port'], $web_cfg);
	$web_cfg = str_replace("{steamapikey}", $_POST['apikey'], $web_cfg);
	$web_cfg = str_replace("{sbwpurl}", $_POST['sb-wp-url'], $web_cfg);
	
	$srv_cfg = str_replace("{server}", $_POST['server'], $srv_cfg);
	$srv_cfg = str_replace("{user}", $_POST['username'], $srv_cfg);
	$srv_cfg = str_replace("{pass}", $_POST['password'], $srv_cfg);
	$srv_cfg = str_replace("{db}", $_POST['database'], $srv_cfg);
	$srv_cfg = str_replace("{port}", $_POST['port'], $srv_cfg);
	
	if(is_writable("../config.php"))
	{
		$config = fopen(ROOT . "../config.php", "w");
		fwrite($config, $web_cfg);
		fclose($config);
	}
	
	if(isset($_POST['postd']) && $_POST['postd'])
	{
		if(empty($_POST['uname']) ||empty($_POST['pass1']) ||empty($_POST['pass2'])||empty($_POST['steam'])||empty($_POST['email']))
		{
			echo "<script>ShowBox('Error', 'There is some missing data. All fields are required.', 'red', '', true);</script>";
		}
		else
		{
			require(ROOT . "../includes/adodb/adodb.inc.php");
			include_once(ROOT . "../includes/adodb/adodb-errorhandler.inc.php");
			$server = "mysqli://" . $_POST['username'] . ":" . $_POST['password'] . "@" . $_POST['server'] . ":" . $_POST['port'] . "/" . $_POST['database'];
			$db = ADONewConnection($server);
			if(!$db)
				echo "<script>ShowBox('Error', 'There was an error connecting to your database. <br />Recheck the details to make sure they are correct', 'red', '', true);</script>";
			else 
			{
				// Setup Admin
				$admin = $GLOBALS['db']->Prepare("INSERT INTO ".$_POST['prefix']."_admins(user,authid,password,gid, email, extraflags, immunity) VALUES (?,?,?,?,?,?,?)");
				$GLOBALS['db']->Execute($admin,array($_POST['uname'], $_POST['steam'], sha1(sha1(SB_SALT . $_POST['pass1'])), -1, $_POST['email'], (1<<24), 100));

							
				// Setup Settings
				$file = file_get_contents(INCLUDES_PATH . "/data.sql");
				$file = str_replace("{prefix}", $_POST['prefix'], $file);
				$querys = explode(";", $file);
				foreach($querys AS $q)
				{
					if(strlen($q) > 2)
					{
						$res = $db->Execute(stripslashes($q) . ";");
						if(!$res)
							$errors++;
					}	
				}	
				
				?>
				<div id="submit-main"><h3>Final Steps</h3>
				The final step is to add this to your databases.cfg on your gameserver (/[MOD]/addons/sourcemod/configs/databases.cfg)<br />
				<u>This code must be added <b>INSIDE</b> the `"Databases" { [insert here] }` part of the file.</u><br />
				<textarea cols="105" rows="15" readonly><?php echo $srv_cfg;?></textarea>
				
				<?php
				if(strtolower($_POST['server']) == "localhost")
				{
					echo '<script>ShowBox("Local server warning", "You have said your MySQL server is running on the same box as the webserver, this is fine, but you may need to alter the following config to set the remote domain/ip of your MySQL server. Unless your gameserver is on the same box as your webserver." , "blue", "", true);</script>';
				}
				if(!is_writable("../config.php"))
				{
					?>
					<br /><br />
					As your config.php wasnt writeable by the server, you will need to add the following into the (./config.php) file.
					<textarea cols="105" rows="15" readonly><?php echo $web_cfg;?></textarea><br />
					<?php
				}
				?>
				<h3>Finish Up</h3>
				The setup of SourceBans is finished. Delete this folder to complete the install. <br />
				<i>If you need to import bans from AMXBans, then click the import button below</i>
				<form name="import" method="POST" action="index.php?step=6">
				<div align="center">
<input type="submit" TABINDEX=2 onclick="" name="button" class="btn" id="button" value="Import AMXBans" /></div>
				<input type="hidden" name="username" value="<?php echo $_POST['username']?>">
				<input type="hidden" name="password" value="<?php echo $_POST['password']?>">
				<input type="hidden" name="server" value="<?php echo $_POST['server']?>">
				<input type="hidden" name="database" value="<?php echo $_POST['database']?>">
				<input type="hidden" name="port" value="<?php echo $_POST['port']?>">
				<input type="hidden" name="prefix" value="<?php echo $_POST['prefix']?>">
				</form></div>
				<?php
			}
		}
		include TEMPLATES_PATH.'/footer.php';
		die();
	}
	
	?>
<form action="" name="mfrm" id="mfrm" method="post">
<div id="install-progress">
<b><u>Installation Progress</u></b><br />
<strike>Step 1: License Agreement</strike><br />
<strike>Step 2: Database Information</strike><br />
<strike>Step 3: System Requirements</strike><br />
<strike>Step 4: Table Creation</strike><br />
<b>Step 5: Setup</b><br />
</div><br />
<div id="submit-main" style="width:75%;"><h3>Setup</h3>
Hover your mouse over the '?' buttons to see an explanation of the field.<br /><br />
<table width="90%" style="border-collapse:collapse;" id="group.details" cellpadding="3">
  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Main Admin", "Type the username for the main SourceBans admin");?>Admin Username</div></td>
    <td><div align="left">
  	 <input type="text" TABINDEX=1 class="inputbox" id="uname" name="uname" value="" />
    </div><div id="server.msg" style="color:#CC0000;"></div></td>
  </tr>
  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Password", "Type a password for the main admin");?>Admin Password</div></td>
    <td><div align="left">
  	 <input type="password" TABINDEX=1 class="inputbox" id="pass1" name="pass1" value="" />
    </div><div id="port.msg" style="color:#CC0000;"></div></td>
  </tr>
  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Confirm", "Type the password again");?>Confirm Password</div></td>
    <td><div align="left">
  	 <input type="password" TABINDEX=1 class="inputbox" id="pass2" name="pass2" value="" />
    </div><div id="user.msg" style="color:#CC0000;"></div></td>
  </tr>
  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("STEAM", "Type your STEAM id");?>Steam ID</div></td>
    <td><div align="left">
  	 <input type="text" TABINDEX=1 class="inputbox" id="steam" name="steam" value="" />
    </div><div id="user.msg" style="color:#CC0000;"></div></td>
  </tr>
  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Email", "Type your email");?>Email</div></td>
    <td><div align="left">
  	 <input type="text" TABINDEX=1 class="inputbox" id="email" name="email" value="" />
    </div><div id="user.msg" style="color:#CC0000;"></div></td>
  </tr>
 </table>

<div align="center">
<input type="button" onclick="CheckInput();" TABINDEX=2 onclick="" name="button" class="btn ok" id="button" value="Ok" /></div>
<input type="hidden" name="postd" value="1">
<input type="hidden" name="username" value="<?php echo $_POST['username']?>">
<input type="hidden" name="password" value="<?php echo $_POST['password']?>">
<input type="hidden" name="server" value="<?php echo $_POST['server']?>">
<input type="hidden" name="database" value="<?php echo $_POST['database']?>">
<input type="hidden" name="port" value="<?php echo $_POST['port']?>">
<input type="hidden" name="prefix" value="<?php echo $_POST['prefix']?>">
<input type="hidden" name="apikey" value="<?php echo $_POST['apikey']?>">
<input type="hidden" name="sb-wp-url" value="<?php echo $_POST['sb-wp-url']?>">
</div>
</form>

<script type="text/javascript">
$E('html').onkeydown = function(event){
	var event = new Event(event);
	if (event.key == 'enter' ) CheckInput();
};
function CheckInput()
{
	var error = 0;
	
	if($('uname').value == "")
		error++;
	if($('pass1').value == "")
		error++;
	if($('pass2').value == "")
		error++;
	if($('steam').value == "")
		error++;
	if($('email').value == "")
		error++;
		
	if(error > 0)
		ShowBox('Error', 'You must fill all fields on this page.', 'red', '', true);
	else
		$('mfrm').submit();
}
</script>
