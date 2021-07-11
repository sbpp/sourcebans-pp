<?php
$errors = 0;
$warnings = 0;

if (isset($_POST['username'], $_POST['password'], $_POST['server'], $_POST['port'], $_POST['database'])) {
    require_once(ROOT.'../includes/Database.php');
    $db = new Database($_POST['server'], $_POST['port'], $_POST['database'], $_POST['username'], $_POST['password'], $_POST['prefix']);
    $db->query('SELECT VERSION();');
    $version = $db->single();
    $sql_version = "Could not connect, database information missing. (Please go back and submit the form.)";

    if (!empty($version['VERSION()'])) {
        $sql_version = $version['VERSION()'];
    }
}
?>

<b><p>This page will list all of the requirements to run the SourceBans web interface, and compare them with your current values. This page will also list some recomendations. These aren't required to run SourceBans web interface, but they are highly recomended.</p></b>
<table style="width: 101%; margin: 0 0 -2px -2px;">
    <tr>
        <td colspan="3" class="listtable_top"><b>PHP Requirements</b></td>
    </tr>
</table>
<div id="submit-main">
<table width="98%" cellspacing="0" cellpadding="0" align="center" class="listtable" style="margin-top:3px;">
  <tr>
  <td width="33%" height="16" class="listtable_top">Setting</td>
	<td width="22%" height="16" class="listtable_top">Recomended</td>
	<td width="22%" height="16" class="listtable_top">Required</td>
	 <td width="22%" height="16" class="listtable_top">Your Value</td>
  </tr>
   <tr>
  <td width="33%" height="16" class="listtable_1"><b>PHP Version</b></td>
  <td width="22%" height="16" class="listtable_top">N/A</td>
    <td width="22%" height="16" class="listtable_1"><b>7.4</b></td>
    <?php
    if (version_compare(PHP_VERSION, "7.4") != -1) {
        $class = "green";
    } else {
        $class = "red";
        $errors++;
    }
    ?>
	<td width="22%" height="16" class="<?php echo $class?>"><?php echo PHP_VERSION;?></td>
  </tr>

  <td width="33%" height="16" class="listtable_1"><b>File Uploads</b></td>
	<td width="22%" height="16" class="listtable_top">N/A</td>
	<td width="22%" height="16" class="listtable_1"><b>On</b></td>
	<?php $uploads = ini_get("file_uploads");if($uploads)
		$class = "green";
	  else {  $class = "red"; $errors++;}?>
	<td width="22%" height="16" class="<?php echo $class?>"><?php echo $uploads?'On':'Off';?></td>
  </tr>

  <td width="33%" height="16" class="listtable_1"><b>OpenSSL Support</b></td>
	<td width="22%" height="16" class="listtable_top">N/A</td>
	<td width="22%" height="16" class="listtable_1"><b>Enabled</b></td>
	<?php
        $openssl = extension_loaded('OpenSSL');
        if ($openssl) {
            $class = "green";
        } else {
            $class = 'red';
            $errors++;
        }
     ?>
	<td width="22%" height="16" class="<?php echo $class?>"><?php echo $openssl?'Enabled':'Disabled';?></td>
  </tr>

  <td width="33%" height="16" class="listtable_1"><b>XML Support</b></td>
	<td width="22%" height="16" class="listtable_top">N/A</td>
	<td width="22%" height="16" class="listtable_1"><b>Enabled</b></td>
	<?php $xml = extension_loaded('xml');if($xml)
		$class = "green";
	  else {  $class = "red"; $errors++;}?>
	<td width="22%" height="16" class="<?php echo $class?>"><?php echo $xml?'Enabled':'Disabled';?></td>
  </tr>

  <td width="33%" height="16" class="listtable_1"><b>GMP Extension</b></td>
	<td width="22%" height="16" class="listtable_top">N/A</td>
	<td width="22%" height="16" class="listtable_1"><b>Enabled</b></td>
	<?php $gmp = extension_loaded('gmp');if($gmp)
		$class = "green";
	  else {  $class = "red"; $errors++;}?>
	<td width="22%" height="16" class="<?php echo $class?>"><?php echo $gmp?'Enabled':'Disabled';?></td>
  </tr>

  <td width="33%" height="16" class="listtable_1"><b>Register Globals</b></td>
	<td width="22%" height="16" class="listtable_1"><b>Off</b></td>
	<td width="22%" height="16" class="listtable_top">N/A</td>
	<?php $rg = ini_get("register_globals");if(!$rg)
		$class = "green";
	  else {  $class = "yellow"; $warnings++;}?>
	<td width="22%" height="16" class="<?php echo $class?>"><?php echo $rg==""?"Off":"On";?></td>
  </tr>

  <td width="33%" height="16" class="listtable_1"><b>Send Mail Path</b></td>
	<td width="22%" height="16" class="listtable_1"><b>Not Empty</b></td>
	<td width="22%" height="16" class="listtable_top">N/A</td>
	<?php $sm = ini_get("sendmail_path");if($sm)
		$class = "green";
	  else {  $class = "yellow"; $warnings++;}?>
	<td width="22%" height="16" class="<?php echo $class?>"><?php echo ($sm?$sm:"Empty");?></td>
  </tr>

  <td width="33%" height="16" class="listtable_1"><b>Safe Mode</b></td>
	<td width="22%" height="16" class="listtable_1"><b>Off</b></td>
	<td width="22%" height="16" class="listtable_top">N/A</td>
	<?php if(ini_get('safe_mode')==0) {
			$class = "green";
			$safem = "Off";
		}
		else {
			$safem = "On";
			$class = "yellow";
			$warnings++;
		}?>
	<td width="22%" height="16" class="<?php echo $class?>"><?php echo $safem;?></td>
  </tr>
</table>
</div>
<br /><br />
<table style="width: 101%; margin: 0 0 -2px -2px;">
    <tr>
        <td colspan="3" class="listtable_top"><b>Database Requirements</b></td>
    </tr>
</table>
<div id="submit-main">
<table width="98%" cellspacing="0" cellpadding="0" align="center" class="listtable" style="margin-top:3px;">
  <tr>
  <td width="33%" height="16" class="listtable_top">Setting</td>
	<td width="22%" height="16" class="listtable_top">Recomended</td>
	<td width="22%" height="16" class="listtable_top">Required</td>
	 <td width="22%" height="16" class="listtable_top">Your Value</td>
  </tr>
   <tr>
  <td width="33%" height="16" class="listtable_1"><b>Database Version</b></td>
	<td width="22%" height="16" class="listtable_top">N/A</td>
	<td width="22%" height="16" class="listtable_1"><b>Mysql 5.5 or MariaDB 10.0.5</b></td>
	<?php
        //our SQL is using FULLTEXT in inno DB.
        //this is only supported from Mysql 5.5 onwards
        //and  >= MariaDB 10.0.5 ( https://mariadb.com/kb/en/full-text-index-overview/ )
	if (strpos($sql_version, 'MARIADB') !== false){
           	 //we have a mariadb.
		//check for versions below 10.0.5
		if(version_compare($sql_version, "10.0.5", "<")){
			$class = "red";
			$errors++;
		}else{
			$class = "green";
		}
        }else{
            //other DB (presumably mysql)
            //check for stuff lower then 5.5
            if(version_compare($sql_version, "5.5", "<")) {
				$class = "red";
				$errors++;
            }else{
				$class = "green";
            }
	}
	?>
	<td width="22%" height="16" class="<?php echo $class?>"><?php echo $sql_version;?></td>
  </tr>
</table>
</div>
<br /><br />
<table style="width: 101%; margin: 0 0 -2px -2px;">
    <tr>
        <td colspan="3" class="listtable_top"><b>Filesystem Requirements</b></td>
    </tr>
</table>
<div id="submit-main">
<table width="98%" cellspacing="0" cellpadding="0" align="center" class="listtable" style="margin-top:3px;">
  <tr>
  <td width="33%" height="16" class="listtable_top">Setting</td>
	<td width="22%" height="16" class="listtable_top">Recomended</td>
	<td width="22%" height="16" class="listtable_top">Required</td>
	 <td width="22%" height="16" class="listtable_top">Your Value</td>
  </tr>
   <tr>
  <td width="33%" height="16" class="listtable_1"><b>Demo Folder Writable (/demos)</b></td>
	<td width="22%" height="16" class="listtable_top">N/A</td>
	<td width="22%" height="16" class="listtable_1"><b>Yes</b></td>
	<?php if(is_writable("../demos"))
		$class = "green";
	  else {  $class = "red"; $errors++;}?>
	<td width="22%" height="16" class="<?php echo $class?>"><?php echo is_writable("../demos")?"Yes":"No";?></td>
  </tr>

   <tr>
  <td width="33%" height="16" class="listtable_1"><b>Cache Writable (/cache)</b></td>
	<td width="22%" height="16" class="listtable_top">N/A</td>
	<td width="22%" height="16" class="listtable_1"><b>Yes</b></td>
	<?php if(is_writable("../cache"))
		$class = "green";
	  else {  $class = "red"; $errors++;}?>
	<td width="22%" height="16" class="<?php echo $class?>"><?php echo is_writable("../cache")?"Yes":"No";?></td>
  </tr>

  <tr>
  <td width="33%" height="16" class="listtable_1"><b>Mod Icon Folder Writable (/images/games)</b></td>
	<td width="22%" height="16" class="listtable_top">N/A</td>
	<td width="22%" height="16" class="listtable_1"><b>Yes</b></td>
	<?php if(is_writable("../images/games"))
		$class = "green";
	  else {  $class = "red"; $errors++;}?>
	<td width="22%" height="16" class="<?php echo $class?>"><?php echo is_writable("../images/games")?"Yes":"No";?></td>
  </tr>

  <tr>
  <td width="33%" height="16" class="listtable_1"><b>Map Image Folder Writable (/images/maps)</b></td>
	<td width="22%" height="16" class="listtable_top">N/A</td>
	<td width="22%" height="16" class="listtable_1"><b>Yes</b></td>
	<?php if(is_writable("../images/maps"))
		$class = "green";
	  else {  $class = "red"; $errors++;}?>
	<td width="22%" height="16" class="<?php echo $class?>"><?php echo is_writable("../images/maps")?"Yes":"No";?></td>
  </tr>

   <tr>
  <td width="33%" height="16" class="listtable_1"><b>Config File Writable (/config.php)</b></td>
	<td width="22%" height="16" class="listtable_1"><b>Yes</b></td>
	<td width="22%" height="16" class="listtable_top">N/A</td>
	<?php if(is_writable("../config.php"))
		$class = "green";
	  else {  $class = "yellow"; $warnings++;}?>
	<td width="22%" height="16" class="<?php echo $class?>"><?php echo is_writable("../config.php")?"Yes":"No";?></td>
  </tr>
  </table><br/><br/>
	<?php /* WhiteWolf: This is a hack to make sure the user didn't refresh the page, in the future we should tell them what they did. */
	if(!isset($_POST['username'], $_POST['password'], $_POST['server'], $_POST['database'], $_POST['port'], $_POST['prefix'])) {
	?>
	<form action="index.php?step=2" method="post" name="send" id="send">
		<!-- We don't even include the body here, since the javascript shouldn't let them go forward -->
	</form>
	<form action="index.php?step=2" method="post" name="sendback" id="sendback">
	</form>
</div>
	<?php
	}
	else
	{
	?>
	<form action="index.php?step=4" method="post" name="send" id="send">
				<input type="hidden" name="username" value="<?php echo $_POST['username']?>">
				<input type="hidden" name="password" value="<?php echo $_POST['password']?>">
				<input type="hidden" name="server" value="<?php echo $_POST['server']?>">
				<input type="hidden" name="database" value="<?php echo $_POST['database']?>">
				<input type="hidden" name="port" value="<?php echo $_POST['port']?>">
				<input type="hidden" name="prefix" value="<?php echo $_POST['prefix']?>">
				<input type="hidden" name="apikey" value="<?php echo $_POST['apikey']?>">
				<input type="hidden" name="sb-email" value="<?php echo $_POST['sb-email']?>">
	</form>
	<form action="index.php?step=3" method="post" name="sendback" id="sendback">
				<input type="hidden" name="username" value="<?php echo $_POST['username']?>">
				<input type="hidden" name="password" value="<?php echo $_POST['password']?>">
				<input type="hidden" name="server" value="<?php echo $_POST['server']?>">
				<input type="hidden" name="database" value="<?php echo $_POST['database']?>">
				<input type="hidden" name="port" value="<?php echo $_POST['port']?>">
				<input type="hidden" name="prefix" value="<?php echo $_POST['prefix']?>">
				<input type="hidden" name="apikey" value="<?php echo $_POST['apikey']?>">
				<input type="hidden" name="sb-email" value="<?php echo $_POST['sb-email']?>">
	</form>
	<?php
	}
	?>
<div align="center">
<input type="button" TABINDEX=2 onclick="next()" name="button" class="btn ok" id="button" value="Ok" /> <input type="button" TABINDEX=2 onclick="$('sendback').submit();" name="button" class="btn cancel" id="button" value="Recheck" /></div>
</div>
</form>
<script type="text/javascript">
<?php if($errors > 0)
{
	echo "ShowBox('Errors', 'There were some errors in your setup that prevent SourceBans from being installed. <br />Please refer to the documentation to find possible fixes for these problems.', 'red', '', true);";
}
elseif($warnings > 0)
{
	echo "ShowBox('Warnings', 'There were some warnings while inspecting your setup. The installation can carry on, but some features may not work properly. <br />Please refer to the documentation to find possible fixes for these problems.', 'red', '', true);";
}?>
$E('html').onkeydown = function(event){
	var event = new Event(event);
	if (event.key == 'enter' ) next();
};
function next()
{
	var errors = <?php echo $errors?>;
	if(errors > 0)
		ShowBox('Errors', 'There were some errors in your setup that prevent SourceBans from being installed. <br />Please refer to the documentation to find possible fixes for these problems.', 'red', '', true);
	else
		$('send').submit();
}
</script>
