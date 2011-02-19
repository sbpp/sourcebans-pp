<?php
$error   = false;
$warning = false;

// List of paths that need to be writable
$writable = array(
  'config' => ROOT . '../config.php',
  'demos'  => ROOT . '../demos',
  'games'  => ROOT . '../images/games',
  'maps'   => ROOT . '../images/maps',
  'themes' => ROOT . '../themes_c',
);

// If a path is not writable, attempt to make it writable
foreach($writable as $name => $path)
{
  if(!is_writable($path))
  {
    chmod($path, 0755);
  }
  
  define('WRITABLE_' . strtoupper($name), is_writable($path));
}


// Server settings
define('FILE_UPLOADS',      ini_get('file_uploads'));
define('MYSQL_VERSION',     mysql_get_client_info());
define('MYSQL_VERSION_REQ', '5.0');
define('PHP_VERSION_REQ',   '5.2');
define('REGISTER_GLOBALS',  ini_get('register_globals'));
define('SAFE_MODE',         ini_get('safe_mode'));
define('SENDMAIL_PATH',     ini_get('sendmail_path'));
define('XML_SUPPORT',       extension_loaded('xml'));
?>
          <div id="submit-introduction">
            This page will list all of the requirements to run the SourceBans web interface, and compare them with your current values. This page will also list some recomendations. These aren't required to run SourceBans web interface, but they are highly recommended.
          </div>
          <div id="submit-main">
            <h3>PHP Requirements</h3>
            <table width="98%" cellspacing="0" cellpadding="0" align="center" class="listtable" style="margin-top: 3px;">
              <tr>
                <td class="listtable_top">Setting</td>
                <td class="listtable_top" width="22%">Recommended</td>
                <td class="listtable_top" width="22%">Required</td>
                <td class="listtable_top" width="22%">Your Value</td>
              </tr>
              <tr>
                <td class="listtable_1">PHP Version</td>
                <td class="listtable_top">N/A</td>
                <td class="listtable_1"><?php echo PHP_VERSION_REQ ?></td>
<?php if(version_compare(PHP_VERSION, PHP_VERSION_REQ) != -1): ?>
                <td class="listtable_1 green"><?php echo PHP_VERSION ?></td>
<?php else: $error = true; ?>
                <td class="listtable_1 red"><?php echo PHP_VERSION ?></td>
<?php endif ?>
              </tr>
              <tr>
                <td class="listtable_1">File Uploads</td>
                <td class="listtable_top">N/A</td>
                <td class="listtable_1">On</td>
<?php if(FILE_UPLOADS): ?>
                <td class="listtable_1 green">On</td>
<?php else: $error = true; ?>
                <td class="listtable_1 red">Off</td>
<?php endif ?>
              </tr>
              <tr>
                <td class="listtable_1">XML Support</td>
                <td class="listtable_top">N/A</td>
                <td class="listtable_1">Enabled</td>
<?php if(XML_SUPPORT): ?>
                <td class="listtable_1 green">Enabled</td>
<?php else: $error = true; ?>
                <td class="listtable_1 red">Disabled</td>
<?php endif ?>
              </tr>
              <tr>
                <td class="listtable_1">Register Globals</td>
                <td class="listtable_1">Off</td>
                <td class="listtable_top">N/A</td>
<?php if(REGISTER_GLOBALS): $warning = true; ?>
                <td class="listtable_1 yellow">On</td>
<?php else: ?>
                <td class="listtable_1 green">Off</td>
<?php endif ?>
              </tr>
              <tr>
                <td class="listtable_1">Send Mail Path</td>
                <td class="listtable_1">Not Empty</td>
                <td class="listtable_top">N/A</td>
<?php if(SENDMAIL_PATH): ?>
                <td class="listtable_1 green"><?php echo SENDMAIL_PATH ?></td>
<?php else: $warning = true; ?>
                <td class="listtable_1 yellow">Empty</td>
<?php endif ?>
              </tr>
              <tr>
                <td class="listtable_1">Safe Mode</td>
                <td class="listtable_1">Off</td>
                <td class="listtable_top">N/A</td>
<?php if(SAFE_MODE): $warning = true; ?>
                <td class="listtable_1 yellow">On</td>
<?php else: ?>
                <td class="listtable_1 green">Off</td>
<?php endif ?>
              </tr>
            </table>
            <br /><br />
            <h3>MySQL Requirements</h3>
            <table width="98%" cellspacing="0" cellpadding="0" align="center" class="listtable" style="margin-top:3px;">
              <tr>
                <td class="listtable_top">Setting</td>
                <td class="listtable_top" width="22%">Recommended</td>
                <td class="listtable_top" width="22%">Required</td>
                <td class="listtable_top" width="22%">Your Value</td> 
              </tr>
              <tr>
                <td class="listtable_1">MySQL Version</td>
                <td class="listtable_top">N/A</td>
                <td class="listtable_1"><?php echo MYSQL_VERSION_REQ ?></td>
<?php if(version_compare(MYSQL_VERSION, MYSQL_VERSION_REQ) != -1): ?>
                <td class="listtable_1 green"><?php echo MYSQL_VERSION ?></td>
<?php else: $error = true; ?>
                <td class="listtable_1 red"><?php echo MYSQL_VERSION ?></td>
<?php endif ?>
              </tr>
            </table>
            <br /><br />
            <h3>File System Requirements</h3>
            <table width="98%" cellspacing="0" cellpadding="0" align="center" class="listtable" style="margin-top:3px;">
              <tr>
                <td class="listtable_top">Setting</td>
                <td width="22%" class="listtable_top">Recommended</td>
                <td width="22%" class="listtable_top">Required</td>
                <td width="22%" class="listtable_top">Your Value</td> 
              </tr>
              <tr>
                <td class="listtable_1">Demo Folder Writable (/demos)</td>
                <td class="listtable_top">N/A</td>
                <td class="listtable_1">Yes</td>
<?php if(WRITABLE_DEMOS): ?>
                <td class="listtable_1 green">Yes</td>
<?php else: $error = true; ?>
                <td class="listtable_1 red">No</td>
<?php endif ?>
              </tr>
              <tr>
                <td class="listtable_1">Compiled Themes Writable (/themes_c)</td>
                <td class="listtable_top">N/A</td>
                <td class="listtable_1">Yes</td>
<?php if(WRITABLE_THEMES): ?>
                <td class="listtable_1 green">Yes</td>
<?php else: $error = true; ?>
                <td class="listtable_1 red">No</td>
<?php endif ?>
              </tr>
              <tr>
                <td class="listtable_1">Mod Icon Folder Writable (/images/games)</td>
                <td class="listtable_top">N/A</td>
                <td class="listtable_1">Yes</td>
<?php if(WRITABLE_GAMES): ?>
                <td class="listtable_1 green">Yes</td>
<?php else: $error = true; ?>
                <td class="listtable_1 red">No</td>
<?php endif ?>
              </tr>
              <tr>
                <td class="listtable_1">Map Image Folder Writable (/images/maps)</td>
                <td class="listtable_top">N/A</td>
                <td class="listtable_1">Yes</td>
<?php if(WRITABLE_MAPS): ?>
                <td class="listtable_1 green">Yes</td>
<?php else: $error = true; ?>
                <td class="listtable_1 red">No</td>
<?php endif ?>
              </tr>
              <tr>
                <td class="listtable_1">Config File Writable (/config.php)</td>
                <td class="listtable_1">Yes</td>
                <td class="listtable_top">N/A</td>
<?php if(WRITABLE_CONFIG): ?>
                <td class="listtable_1 green">Yes</td>
<?php else: $error = true; ?>
                <td class="listtable_1 red">No</td>
<?php endif ?>
              </tr>
            </table>
            <div align="center">
              <input class="btn ok" onclick="next(); return false;" type="submit" value="Ok" />
              <input class="btn refresh" onclick="location.reload()" type="button" value="Recheck" />
            </div>
          </div>
          <script type="text/javascript">
<?php if($error): ?>
            ShowBox('Errors', 'There were some errors in your setup that prevent SourceBans from being installed. <br />Please refer to the documentation to find possible fixes for these problems.', 'red', '', true);
<?php elseif($warning): ?>
            ShowBox('Warnings', 'There were some warnings while inspecting your setup. The installation can carry on, but some features may not work properly. <br />Please refer to the documentation to find possible fixes for these problems.', 'red', '', true);
<?php endif ?>
            
            function next() {
<?php if($error): ?>
              ShowBox('Errors', 'There were some errors in your setup that prevent SourceBans from being installed. <br />Please refer to the documentation to find possible fixes for these problems.', 'red', '', true);
<?php else: ?>
              window.location = '?step=4';
<?php endif ?>
            }
          </script>