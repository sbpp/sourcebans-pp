<?php
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
$errors = 0;
$warnings = 0;

require_once(ROOT.'../includes/Database.php');
$db = new Database($_POST['server'], $_POST['port'], $_POST['database'], $_POST['username'], $_POST['password'], $_POST['prefix']);

$db->query('SELECT VERSION() AS version');
$version = $db->single();

$charset = 'utf8';
if (version_compare($version['version'], "5.5.3") >= 0) {
    $charset .= 'mb4';
}

$file = file_get_contents(INCLUDES_PATH . "/struc.sql");
$file = str_replace("{prefix}", $_POST['prefix'], $file);
$file = str_replace("{charset}", $charset, $file);
$querys = explode(";", $file);
foreach ($querys as $query) {
    if (strlen($query) > 2) {
        $db->query(stripslashes($query));
        if (!$db->execute()) {
            $errors++;
        }
    }
}
?>
<br />
<table style="width: 101%; margin: 0 0 -2px -2px;">
    <tr>
        <td colspan="3" class="listtable_top"><b>Table installation</b></td>
    </tr>
</table>
<div id="submit-main">
<?php
if ($errors > 0) {
    print "<script>ShowBox('Error', 'There was an error creating the table structure. Please read the message above to help debug the problem.', 'red', '', true);</script>";
} else {
    print "<script>ShowBox('Success', 'The tables were created successfully', 'green', '', true);</script>";
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
    <input type="hidden" name="sb-email" value="<?php echo $_POST['sb-email']?>">
    <input type="hidden" name="charset" value="<?php echo $charset?>">
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
