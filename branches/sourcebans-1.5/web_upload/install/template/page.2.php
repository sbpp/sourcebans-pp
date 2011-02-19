<?php
if($_SERVER['REQUEST_METHOD'] == 'POST'):
  if(empty($_POST['db_host']) || empty($_POST['db_port']) || empty($_POST['db_user']) || empty($_POST['db_name']) || empty($_POST['db_prefix'])):
?>
<script type="text/javascript">
  ShowBox('Error', 'There is some missing data. All fields are required.', 'red', '', true);
</script>
<?php
  else:
    require_once ROOT . '../includes/adodb/adodb.inc.php';
    include_once ROOT . '../includes/adodb/adodb-errorhandler.inc.php';
    
    $db = ADONewConnection('mysql://' . $_POST['db_user'] . ':' . $_POST['db_pass'] . '@' . $_POST['db_host'] . ':' . $_POST['db_port'] . '/' . $_POST['db_name']);
    if(!$db):
?>
<script type="text/javascript">
  ShowBox('Error', 'There was an error connecting to your database. <br />Recheck the details to make sure they are correct', 'red', '', true);
</script>
<?php
    elseif(strlen($_POST['db_prefix']) > 9):
?>
<script type="text/javascript">
  ShowBox('Error', 'The prefix cannot be longer than 9 characters.<br />Correct this and submit again.', 'red', '', true);
</script>
<?php
    else:
      $_SESSION['db_host']   = $_POST['db_host'];
      $_SESSION['db_port']   = $_POST['db_port'];
      $_SESSION['db_user']   = $_POST['db_user'];
      $_SESSION['db_pass']   = $_POST['db_pass'];
      $_SESSION['db_name']   = $_POST['db_name'];
      $_SESSION['db_prefix'] = $_POST['db_prefix'];
?>
<script type="text/javascript">
  window.location = '?step=3';
</script>
<?php
    endif;
  endif;
endif;
?>
          <form action="" method="post">
            <div id="submit-introduction">
              Hover your mouse over the '?' buttons to see an explanation of the field.
            </div>
            <div id="submit-main">
              <h3>Database Details</h3>
              <table cellpadding="3" id="group.details" style="border-collapse: collapse;" width="90%">
                <tr>
                  <td valign="top" width="35%">
                    <label class="rowdesc" for="db_host"><?php echo HelpIcon('Server', 'Type the IP or hostname to your MySQL server') ?>Server Hostname</label>
                  </td>
                  <td>
                    <div align="left">
                     <input class="inputbox" id="db_host" name="db_host" value="<?php echo isset($_POST['db_host']) ? $_POST['db_host'] : 'localhost'; ?>" />
                    </div>
                    <div id="db_host.msg" style="color: #CC0000;"></div>
                  </td>
                </tr>
                <tr>
                  <td valign="top" width="35%">
                    <label class="rowdesc" for="db_port"><?php echo HelpIcon('Port', 'Type the port that your MySQL server is running on') ?>Server Port</label>
                  </td>
                  <td>
                    <div align="left">
                     <input class="inputbox" id="db_port" name="db_port" value="<?php echo isset($_POST['db_port']) ? $_POST['db_port'] : 3306; ?>" />
                    </div>
                    <div id="db_port.msg" style="color: #CC0000;"></div>
                  </td>
                </tr>
                <tr>
                  <td valign="top" width="35%">
                    <label class="rowdesc" for="db_user"><?php echo HelpIcon('Username', 'Type your MySQL username') ?>Username</label>
                  </td>
                  <td>
                    <div align="left">
                     <input class="inputbox" id="db_user" name="db_user" value="<?php echo isset($_POST['db_user']) ? $_POST['db_user'] : ''; ?>" />
                    </div>
                    <div id="db_user.msg" style="color: #CC0000;"></div>
                  </td>
                </tr>
                <tr>
                  <td valign="top" width="35%">
                    <label class="rowdesc" for="db_pass"><?php echo HelpIcon('Password', 'Type your MySQL password') ?>Password</label>
                  </td>
                  <td>
                    <div align="left">
                     <input class="inputbox" id="db_pass" name="db_pass" type="password" value="<?php echo isset($_POST['db_pass']) ? $_POST['db_pass'] : ''; ?>" />
                    </div>
                    <div id="db_pass.msg" style="color: #CC0000;"></div>
                  </td>
                </tr>
                <tr>
                  <td valign="top" width="35%">
                    <label class="rowdesc" for="db_name"><?php echo HelpIcon('Database', 'Type the name of the database you want to use for SourceBans') ?>Database</label>
                  </td>
                  <td>
                    <div align="left">
                     <input class="inputbox" id="db_name" name="db_name" value="<?php echo isset($_POST['db_name']) ? $_POST['db_name'] : ''; ?>" />
                    </div>
                    <div id="db_name.msg" style="color: #CC0000;"></div>
                  </td>
                </tr>
                <tr>
                  <td valign="top" width="35%">
                    <label class="rowdesc" for="db_prefix"><?php echo HelpIcon('Prefix', 'Type the prefix you want to use for the database tables') ?>Table Prefix</label>
                  </td>
                  <td>
                    <div align="left">
                     <input class="inputbox" id="db_prefix" name="db_prefix" value="<?php echo isset($_POST['db_prefix']) ? $_POST['db_prefix'] : 'sb'; ?>" />
                    </div>
                    <div id="db_prefix.msg" style="color: #CC0000;"></div>
                  </td>
                </tr>
              </table>
              <div align="center">
                <input class="btn ok" type="submit" value="Ok" />
              </div>
            </div>
          </form>