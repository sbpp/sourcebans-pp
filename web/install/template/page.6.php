<?php
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

if (isset($_POST['postd']) && $_POST['postd']) {
    if (empty($_POST['amx_server']) ||empty($_POST['amx_port']) ||empty($_POST['amx_username']) ||empty($_POST['amx_password']) ||empty($_POST['amx_database']) ||empty($_POST['amx_prefix'])) {
        echo "<script>ShowBox('Error', 'There is some missing data. All feilds are required.', 'red', '', true);</script>";
    } else {
        include_once(INCLUDES_PATH . "/converter.inc.php");
            $oldDB = new Database($_POST['amx_server'], $_POST['amx_port'], $_POST['amx_database'], $_POST['amx_username'], $_POST['amx_password'], $_POST['amx_prefix']);
            $newDB = new Database(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_PREFIX);

            convertAmxbans($oldDB, $newDB);
    }
}
?>
<b>Hover your mouse over the '?' buttons to see an explanation of the field.</b><br /><br />
<table style="width: 101%; margin: 0 0 -2px -2px;">
    <tr>
        <td colspan="3" class="listtable_top"><b>Setup</b></td>
    </tr>
</table>
<form action="" method="post">
<div id="submit-main">
<div align="center">
Type the database information for the AMXBans mysql server you wish to import from.<br/><br/>
<table width="60%" style="border-collapse:collapse;" id="group.details" cellpadding="3">
  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Server", "Type the ip, or hostname to your MySQL server");?>Server Hostname</div></td>
    <td><div align="center">
  	 <input type="text" TABINDEX=1 class="textbox" id="amx_server" name="amx_server" value="" />
    </div><div id="server.msg" style="color:#CC0000;"></div></td>
  </tr>
  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Port", "Type the port that your MySQL server is running on");?>Server Port</div></td>
    <td><div align="center">
  	 <input type="text" TABINDEX=1 class="textbox" id="amx_port" name="amx_port" value="" />
    </div><div id="port.msg" style="color:#CC0000;"></div></td>
  </tr>
  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Username", "Type your MySQL username");?>Username</div></td>
    <td><div align="center">
  	 <input type="text" TABINDEX=1 class="textbox" id="amx_username" name="amx_username" value="" />
    </div><div id="user.msg" style="color:#CC0000;"></div></td>
  </tr>

   <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Password", "Type your MySQL password");?>Password</div></td>
    <td><div align="center">
  	 <input type="password" TABINDEX=1 class="textbox" id="amx_password" name="amx_password" value="" />
    </div><div id="password.msg" style="color:#CC0000;"></div></td>
  </tr>

  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Database", "Type name of the database you want to use for SourceBans");?>Database</div></td>
    <td><div align="center">
  	 <input type="text" TABINDEX=1 class="textbox" id="amx_database" name="amx_database" value="" />
    </div><div id="database.msg" style="color:#CC0000;"></div></td>
  </tr>

  <tr>
    <td valign="top" width="35%"><div class="rowdesc"><?php echo HelpIcon("Prefix", "Type a prefix you want to use for the tables");?>Table Prefix</div></td>
    <td><div align="center">
  	 <input type="text" TABINDEX=1 class="textbox" id="amx_prefix" name="amx_prefix" value="" />
    </div><div id="database.msg" style="color:#CC0000;"></div></td>
  </tr>
 </table><br/><br/>

<input type="submit" TABINDEX=2 onclick="" name="button" class="btn ok" id="button" value="Ok" /></div>
<input type="hidden" name="postd" value="1">
<input type="hidden" name="username" value="<?php echo $_POST['username']?>">
<input type="hidden" name="password" value="<?php echo $_POST['password']?>">
<input type="hidden" name="server" value="<?php echo $_POST['server']?>">
<input type="hidden" name="database" value="<?php echo $_POST['database']?>">
<input type="hidden" name="port" value="<?php echo $_POST['port']?>">
<input type="hidden" name="prefix" value="<?php echo $_POST['prefix']?>">
<input type="hidden" name="apikey" value="<?php echo $_POST['apikey']?>">
<input type="hidden" name="sb-wp-url" value="<?php echo $_POST['sb-wp-url']?>">
<input type="hidden" name="sb-email" value="<?php echo $_POST['sb-email']?>">
</div>
</form>
