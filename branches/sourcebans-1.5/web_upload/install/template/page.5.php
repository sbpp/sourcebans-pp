<?php
$srv_cfg = '	
	"sourcebans"
	{
		"driver"		"mysql"
		"host"			"{db_host}"
		"database"		"{db_name}"
		"user"			"{db_user}"
		"pass"			"{db_pass}"
		//"timeout"		"0"
		"port"			"{db_port}"
	}
	';
  
  $srv_cfg = str_replace('{db_host}', $_SESSION['db_host'], $srv_cfg);
  $srv_cfg = str_replace('{db_port}', $_SESSION['db_port'], $srv_cfg);
  $srv_cfg = str_replace('{db_user}', $_SESSION['db_user'], $srv_cfg);
  $srv_cfg = str_replace('{db_pass}', $_SESSION['db_pass'], $srv_cfg);
  $srv_cfg = str_replace('{db_name}', $_SESSION['db_name'], $srv_cfg);
  
  $web_cfg = file_get_contents(ROOT . '../config.php.template');
  $web_cfg = str_replace('{db_host}',   $_SESSION['db_host'],   $web_cfg);
  $web_cfg = str_replace('{db_port}',   $_SESSION['db_port'],   $web_cfg);
  $web_cfg = str_replace('{db_user}',   $_SESSION['db_user'],   $web_cfg);
  $web_cfg = str_replace('{db_pass}',   $_SESSION['db_pass'],   $web_cfg);
  $web_cfg = str_replace('{db_name}',   $_SESSION['db_name'],   $web_cfg);
  $web_cfg = str_replace('{db_prefix}', $_SESSION['db_prefix'], $web_cfg);
  
  if(is_writable(ROOT . '../config.php'))
  {
    $config = fopen(ROOT . '../config.php', 'w');
    fwrite($config, $web_cfg);
    fclose($config);
  }
  
  if($_SERVER['REQUEST_METHOD'] == 'POST'):
    if(empty($_POST['name']) || empty($_POST['password']) || empty($_POST['password_confirm']) || empty($_POST['steam']) || empty($_POST['email'])):
?>
<script type="text/javascript">
  ShowBox('Error', 'There is some missing data. All fields are required.', 'red', '', true);
</script>
<?php
    else:
      require_once ROOT . '../includes/adodb/adodb.inc.php';
      include_once ROOT . '../includes/adodb/adodb-errorhandler.inc.php';
      
      $db = ADONewConnection('mysql://' . $_SESSION['db_user'] . ':' . $_SESSION['db_pass'] . '@' . $_SESSION['db_host'] . ':' . $_SESSION['db_port'] . '/' . $_SESSION['db_name']);
      if(!$db):
?>
<script type="text/javascript">
  ShowBox('Error', 'There was an error connecting to your database. <br />Recheck the details to make sure they are correct', 'red', '', true);
</script>
<?php
      else:
        // Setup Admin
        $db->Execute('INSERT INTO ' . $_SESSION['db_prefix'] . '_admins (user, authid, password, gid, email, extraflags, validate, immunity)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                      array($_POST['name'], $_POST['steam'], sha1(sha1(SB_SALT . $_POST['password'])), -1, $_POST['email'], (1<<24), ' ', 100));
        
        // Setup Database
        $file = file_get_contents(INCLUDES_PATH . '/data.sql');
        $file = str_replace('{prefix}', $_SESSION['db_prefix'], $file);
        $queries = explode(';', $file);
        foreach($queries AS $q)
        {
          if(strlen($q) <= 2)
            continue;
          
          $res = $db->Execute(stripslashes($q));
          if($res)
            continue;
          
          $error = true;
        }
?>
          <form action="" method="post">
            <div id="submit-main">
              <h3>Final Steps</h3>
              The final step is to add this to your databases.cfg on your gameserver (/[MOD]/addons/sourcemod/configs/databases.cfg)<br />
              <u>This code must be added <b>INSIDE</b> the `"Databases" { [insert here] }` part of the file.</u>
              <textarea cols="105" readonly="readonly" rows="15"><?php echo $srv_cfg ?></textarea>
<?php if(!is_writable(ROOT . '../config.php')): ?>
              <br /><br />
              As your config.php wasn't writable by the server, you will need to add the following into the (./config.php) file.
              <textarea cols="105" readonly="readonly" rows="15"><?php echo $web_cfg ?></textarea>
<?php endif ?>
              <br />
              <h3>Finish Up</h3>
              The setup of SourceBans is finished. Delete this folder to complete the install.<br />
              <i>If you need to import bans from AMXBans, then click the import button below.</i>
              <div align="center">
                <input class="btn save" onclick="next(); return false;" type="submit" value="Import AMXBans" />
              </div>
            </div>
          </form>
          <script type="text/javascript">
<?php if(strtolower($_SESSION['db_host']) == 'localhost'): ?>
            ShowBox('Local Server Warning', 'You have said your MySQL server is running on the same box as the webserver, this is fine, but you may need to alter the following config to set the remote domain/IP of your MySQL server. Unless your gameserver is on the same box as your webserver.' , 'blue', '', true);
<?php endif ?>
            
            function next() {
              window.location = '?step=6';
            }
          </script>
<?php
      endif;
    endif;
    
    exit;
  endif;
?>
          <form action="" method="post">
            <div id="submit-introduction">
              Hover your mouse over the '?' buttons to see an explanation of the field.
            </div>
            <div id="submit-main">
              <h3>Initial Setup</h3>
              <table cellpadding="3" id="group.details" style="border-collapse: collapse;" width="90%">
                <tr>
                  <td valign="top" width="35%">
                    <label class="rowdesc" for="name"><?php echo HelpIcon('Main Admin', 'Type the username for the main SourceBans admin') ?>Admin Username</label>
                  </td>
                  <td>
                    <div align="left">
                      <input class="inputbox" id="name" name="name" />
                    </div>
                    <div id="name.msg" style="color: #CC0000;"></div>
                  </td>
                </tr>
                <tr>
                  <td valign="top" width="35%">
                    <label class="rowdesc" for="password"><?php echo HelpIcon('Password', 'Type a password for the main admin') ?>Admin Password</label>
                  </td>
                  <td>
                    <div align="left">
                      <input class="inputbox" id="password" name="password" type="password" />
                    </div>
                    <div id="password.msg" style="color: #CC0000;"></div>
                  </td>
                </tr>
                <tr>
                  <td valign="top" width="35%">
                    <label class="rowdesc" for="password_confirm"><?php echo HelpIcon('Confirm', 'Type the password again') ?>Confirm Password</label>
                  </td>
                  <td>
                    <div align="left">
                      <input class="inputbox" id="password_confirm" name="password_confirm" type="password" />
                    </div>
                    <div id="password_confirm.msg" style="color: #CC0000;"></div>
                  </td>
                </tr>
                <tr>
                  <td valign="top" width="35%">
                    <label class="rowdesc" for="steam"><?php echo HelpIcon('Steam ID', 'Type your Steam ID') ?>Steam ID</label>
                  </td>
                  <td>
                    <div align="left">
                      <input class="inputbox" id="steam" name="steam" value="STEAM_" />
                    </div>
                    <div id="steam.msg" style="color: #CC0000;"></div>
                  </td>
                </tr>
                <tr>
                  <td valign="top" width="35%">
                    <label class="rowdesc" for="email"><?php echo HelpIcon('Email', 'Type your email') ?>Email</label>
                  </td>
                  <td>
                    <div align="left">
                      <input class="inputbox" id="email" name="email" />
                    </div>
                    <div id="email.msg" style="color: #CC0000;"></div>
                  </td>
                </tr>
              </table>
              <div align="center">
                <input class="btn ok" onclick="return next()" type="submit" value="Ok" />
              </div>
            </div>
          </form>
          <script type="text/javascript">
            function next()
            {
              if($('name').value             == '' ||
                 $('password').value         == '' ||
                 $('password_confirm').value == '' ||
                 $('steam').value            == '' ||
                 $('email').value            == '')
              {
                ShowBox('Error', 'You must fill in all the fields on this page.', 'red', '', true);
                
                return false;
              }
              
              return true;
            }
          </script>