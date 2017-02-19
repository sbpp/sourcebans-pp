<?php
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
if (isset($_POST['postd'])) {
    if (empty($_POST['server']) ||empty($_POST['port']) ||empty($_POST['username']) ||empty($_POST['database']) ||empty($_POST['prefix'])) {
        echo "<script>ShowBox('Error', 'There is some missing data. All fields are required.', 'red', '', true);</script>";
    } else {
        require_once(ROOT.'../includes/Database.php');
        $db = new Database($_POST['server'], $_POST['port'], $_POST['database'], $_POST['username'], $_POST['password'], $_POST['prefix']);
        if (!$db) {
            echo "<script>ShowBox('Error', 'There was an error connecting to your database. <br />Recheck the details to make sure they are correct', 'red', '', true);</script>";
        } elseif (strlen($_POST['prefix']) > 9) {
            echo "<script>ShowBox('Error', 'The prefix cannot be longer than 9 characters.<br />Correct this and submit again.', 'red', '', true);</script>";
        } else {
?>
<form action="index.php?step=3" method="post" name="send" id="send">
    <input type="hidden" name="username" value="<?php echo $_POST['username']?>">
    <input type="hidden" name="password" value="<?php echo $_POST['password']?>">
    <input type="hidden" name="server" value="<?php echo $_POST['server']?>">
    <input type="hidden" name="database" value="<?php echo $_POST['database']?>">
    <input type="hidden" name="port" value="<?php echo $_POST['port']?>">
    <input type="hidden" name="prefix" value="<?php echo $_POST['prefix']?>">
    <input type="hidden" name="apikey" value="<?php echo $_POST['apikey']?>">
    <input type="hidden" name="sb-wp-url" value="<?php echo $_POST['sb-wp-url']?>">
    <input type="hidden" name="sb-email" value="<?php echo $_POST['sb-email']?>">
</form>
<script>
    $('send').submit();
</script>
<?php
        }
    }
}
?>

<b>Hover your mouse over the '?' buttons to see an explanation of the field.</b><br /><br />

<table style="width: 101%; margin: 0 0 -2px -2px;">
    <tr>
        <td colspan="3" class="listtable_top"><b>MySQL Information</b></td>
    </tr>
</table>
<div id="submit-main">
<form action="" method="post" name="submit" id="submit">
    <div align="center">
<table width="60%" style="border-collapse:collapse;" id="group.details" cellpadding="3">
  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Server", "Type the ip, or hostname to your MySQL server");?>Server Hostname</div></td>
    <td><div align="center">
  	 <input type="text" TABINDEX=1 class="textbox" id="server" name="server" value="<?php echo isset($_POST['server'])?$_POST['server']:'localhost';?>" />
    </div><div id="server.msg" style="color:#CC0000;"></div></td>
  </tr>
  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Port", "Type the port that your MySQL server is running on");?>Server Port</div></td>
    <td><div align="center">
  	 <input type="text" TABINDEX=1 class="textbox" id="port" name="port" value="<?php echo isset($_POST['port'])?$_POST['port']:3306;?>" />
    </div><div id="port.msg" style="color:#CC0000;"></div></td>
  </tr>
  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Username", "Type your MySQL username");?>Username</div></td>
    <td><div align="center">
  	 <input type="text" TABINDEX=1 class="textbox" id="username" name="username" value="<?php echo isset($_POST['username'])?$_POST['username']:'';?>" />
    </div><div id="user.msg" style="color:#CC0000;"></div></td>
  </tr>

   <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Password", "Type your MySQL password");?>Password</div></td>
    <td><div align="center">
  	 <input type="password" TABINDEX=1 class="textbox" id="password" name="password" value="<?php echo isset($_POST['password'])?$_POST['password']:'';?>" />
    </div><div id="password.msg" style="color:#CC0000;"></div></td>
  </tr>

  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Database", "Type name of the database you want to use for SourceBans");?>Database</div></td>
    <td><div align="center">
  	 <input type="text" TABINDEX=1 class="textbox" id="database" name="database" value="<?php echo isset($_POST['database'])?$_POST['database']:'';?>" />
    </div><div id="database.msg" style="color:#CC0000;"></div></td>
  </tr>

  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Prefix", "Type a prefix you want to use for the tables");?>Table Prefix</div></td>
    <td><div align="center">
  	 <input type="text" TABINDEX=1 class="textbox" id="prefix" name="prefix" value="<?php echo isset($_POST['prefix'])?$_POST['prefix']:'sb';?>" />
    </div><div id="database.msg" style="color:#CC0000;"></div></td>
  </tr>

  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Steam API Key", "Copy & Paste Your Steam API Key Here");?>Steam API Key</div></td>
    <td><div align="center">
  	 <input type="text" TABINDEX=1 class="textbox" id="apikey" name="apikey" value="<?php echo isset($_POST['apikey'])?$_POST['apikey']:'';?>" />
    </div><div id="database.msg" style="color:#CC0000;"></div></td>
  </tr>

  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("SourceBans URL", "Whats the URL of your SourceBans install (eg. http://bans.mysite.com or http://mysite.com/bans)");?>SourceBans URL</div></td>
    <td><div align="center">
  	 <input type="text" TABINDEX=1 class="textbox" id="sb-wp-url" name="sb-wp-url" value="<?php echo isset($_POST['sb-wp-url'])?$_POST['sb-wp-url']:'';?>" />
    </div><div id="database.msg" style="color:#CC0000;"></div></td>
  </tr>

  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("SourceBans EMail", "Email Adress used by SourceBans for Password Reset and Bans");?>SourceBans EMail</div></td>
    <td><div align="center">
  	 <input type="text" TABINDEX=1 class="textbox" id="sb-email" name="sb-email" value="<?php echo isset($_POST['sb-email'])?$_POST['sb-email']:'';?>" />
    </div><div id="database.msg" style="color:#CC0000;"></div></td>
  </tr>
 </table>
<br/><br/>

<input type="submit" TABINDEX=2 onclick="" name="button" class="btn ok" id="button" value="Ok" /></div>
<input type="hidden" name="postd" value="1">
</div>
</form>
<script type="text/javascript">
	$E('html').onkeydown = function(event){
	    var event = new Event(event);
	    if (event.key == 'enter' ) $('submit').submit();
    }
</script>
