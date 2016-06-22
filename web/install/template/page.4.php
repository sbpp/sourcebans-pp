<?php
	if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
	$errors = 0;
	$warnings = 0;

	require(ROOT . "../includes/adodb/adodb.inc.php");
	include_once(ROOT . "../includes/adodb/adodb-errorhandler.inc.php");
	$server = "mysqli://" . $_POST['username'] . ":" . $_POST['password'] . "@" . $_POST['server'] . ":" . $_POST['port'] . "/" . $_POST['database'];
	$db = ADONewConnection($server);
	
	$file = file_get_contents(INCLUDES_PATH . "/struc.sql");
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
<div id="install-progress">
<b><u>Installation Progress</u></b><br />
<strike>Step 1: License Agreement</strike><br />
<strike>Step 2: Database Information</strike><br />
<strike>Step 3: System Requirements</strike><br />
<b>Step 4: Table Creation</b><br />
Step 5: Setup<br />
</div><br />
<div id="submit-main" style="width:75%;"><h3>Table installation</h3>

<?php if($errors > 0)
{
	?>
	<script>ShowBox('Error', 'There was an error creating the table structure. Please read the message above to help debug the problem.', 'red', '', true);</script>
	<?php
}else 
{
	?>
	<script>ShowBox('Success', 'The tables were created successfully', 'green', '', true);</script>
	<?php
}
?>
<form action="index.php?step=5" method="post" name="send" id="send">
				<input type="hidden" name="username" value="<?php echo $_POST['username']?>">
				<input type="hidden" name="password" value="<?php echo $_POST['password']?>">
				<input type="hidden" name="server" value="<?php echo $_POST['server']?>">
				<input type="hidden" name="database" value="<?php echo $_POST['database']?>">
				<input type="hidden" name="port" value="<?php echo $_POST['port']?>">
				<input type="hidden" name="prefix" value="<?php echo $_POST['prefix']?>">
				<input type="hidden" name="apikey" value="<?php echo $_POST['apikey']?>">
				<input type="hidden" name="sb-wp-url" value="<?php echo $_POST['sb-wp-url']?>">
</form>
<div align="center">
<input type="submit" TABINDEX=2 onclick="next()" name="button" class="btn ok" id="button" value="Ok" /></div>
</div>
</form>
<script type="text/javascript">
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
