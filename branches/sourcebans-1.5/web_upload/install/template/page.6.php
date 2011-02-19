<?php
  if($_SERVER['REQUEST_METHOD'] == 'POST'):
    if(empty($_POST['db_host']) || empty($_POST['db_port']) || empty($_POST['db_user']) || empty($_POST['db_pass']) || empty($_POST['db_name']) || empty($_POST['db_prefix'])):
?>
<script type="text/javascript">
  ShowBox('Error', 'There is some missing data. All fields are required.', 'red', '', true);
</script>
<?php
    else:
      include_once(INCLUDES_PATH . 'converter.inc.php');
      
      $olddsn = 'mysql://' . $_POST['db_user'] . ':' . $_POST['db_pass'] . '@' . $_POST['db_host'] . ':' . $_POST['db_port'] . '/' . $_POST['db_name'];
      $newdsn = 'mysql://' . DB_USER . ':' . DB_PASS . '@' . DB_HOST . ':' . DB_PORT . '/' . DB_NAME;
      
      convertAmxbans($olddsn, $newdsn, $_POST['db_prefix'], DB_PREFIX);
    endif;
  endif;
?>
          <form action="" method="post">
            <div id="submit-introduction">
              Hover your mouse over the '?' buttons to see an explanation of the field.<br /><br />
              Type the database information for the AMXBans MySQL server you wish to import from.
            </div>
            <div id="submit-main">
              <h3>AMXBans Import</h3>
              <table cellpadding="3" id="group.details" style="border-collapse: collapse;" width="90%">
                <tr>
                  <td valign="top" width="35%">
                    <label class="rowdesc" for="db_host"><?php echo HelpIcon('Server', 'Type the IP or hostname to your MySQL server') ?>Server Hostname</label>
                  </td>
                  <td>
                    <div align="left">
                      <input class="inputbox" id="db_host" name="db_host" />
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
                      <input class="inputbox" id="db_port" name="db_port" />
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
                      <input class="inputbox" id="db_user" name="db_user" />
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
                      <input class="inputbox" id="db_pass" name="db_pass" type="password" />
                    </div>
                    <div id="db_pass.msg" style="color: #CC0000;"></div>
                  </td>
                </tr>
                <tr>
                  <td valign="top" width="35%">
                    <label class="rowdesc" for="db_name"><?php echo HelpIcon('Database', 'Type the name of the database of AMXBans') ?>Database</label>
                  </td>
                  <td>
                    <div align="left">
                      <input class="inputbox" id="db_name" name="db_name" />
                    </div>
                    <div id="db_name.msg" style="color: #CC0000;"></div>
                  </td>
                </tr>
                <tr>
                  <td valign="top" width="35%">
                    <label class="rowdesc" for="db_prefix"><?php echo HelpIcon('Prefix', 'Type the prefix of the database tables of AMXBans') ?>Table Prefix</label>
                  </td>
                  <td>
                    <div align="left">
                      <input class="inputbox" id="db_prefix" name="db_prefix" />
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