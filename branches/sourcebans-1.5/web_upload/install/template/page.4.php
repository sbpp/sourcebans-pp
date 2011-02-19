<?php
$error = false;

require_once ROOT . '../includes/adodb/adodb.inc.php';
include_once ROOT . '../includes/adodb/adodb-errorhandler.inc.php';
$db = ADONewConnection('mysql://' . $_SESSION['db_user'] . ':' . $_SESSION['db_pass'] . '@' . $_SESSION['db_host'] . ':' . $_SESSION['db_port'] . '/' . $_SESSION['db_name']);

$file = file_get_contents(INCLUDES_PATH . 'struc.sql');
$file = str_replace('{prefix}', $_SESSION['db_prefix'], $file);
$queries = explode(';', $file);
foreach($queries as $q)
{
  if(strlen($q) <= 2)
    continue;
  
  $res = $db->Execute(stripslashes($q));
  if($res)
    continue;
  
  $error = true;
}
?>
          <div id="submit-main">
            <h3>Table Creation</h3>
            <div align="center">
              <input class="btn ok" onclick="next(); return false;" type="submit" value="Ok" />
            </div>
          </div>
          <script type="text/javascript">
<?php if($error): ?>
            ShowBox('Error', 'There was an error creating the table structure. Please read the message above to help debug the problem.', 'red', '', true);
<?php else: ?>
            ShowBox('Success', 'The tables were created successfully', 'green', '', true);
<?php endif ?>
            
            function next()
            {
<?php if($error): ?>
              ShowBox('Error', 'There was an error creating the table structure. Please read the message above to help debug the problem.', 'red', '', true);
<?php else: ?>
              window.location = '?step=5';
<?php endif ?>
            }
          </script>