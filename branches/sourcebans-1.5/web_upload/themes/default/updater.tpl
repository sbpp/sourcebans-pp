<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>Updater | SourceBans</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="author" content="GameConnect" />
    <meta name="copyright" content="SourceBans © 2007-2011 GameConnect.net. All rights reserved." />
    <meta name="description" content="Global admin and ban management for the Source engine" />
    <link href="../images/favicon.ico" rel="shortcut icon" />
    <link href="../themes/{$theme_name}/style.css" rel="stylesheet" type="text/css" />
    <style type="text/css">
      #content_title { margin: 55px 0 17px; }
      #content p { margin: 11px 0 11px 40px; }
    </style>
  </head>
  <body>
    <div id="mainwrapper">
      <div id="header">
        <a href="index.php" id="head-logo"><h1><span>SourceBans</span></h1></a>
      </div>
      <div id="innerwrapper">
        <h2 id="content_title">Updater</h2>
        <div id="content">
          <h3>Setup...</h3>
          <p>
            Checking current database version... <strong>{$current_version}</strong><br />
{if empty($updates)}
            Installation up-to-date.
{else}
            Updating database to version: <strong>{$latest_version}</strong>
{/if}
          </p>
{if !empty($updates)}
          <h3>Updating...</h3>
          <p>
{foreach from=$updates item=update key=version}
            Running update: <strong>v{$version}</strong>... 
{if $update}
            Done.<br /><br />
{else}
            <strong>Error executing: /updater/data/{$version}.php. Stopping Update!</strong>
{/if}
{/foreach}
{if $update}
            <br />Updated successfully. Please delete the /updater folder.
{/if}
          </p>
{/if}
        </div>
      </div>
      <div id="footer">
        <p class="gc">By <a href="http://www.gameconnect.net">GameConnect</a></p>
        <div class="sb">
          <a href="http://www.sourcebans.net"><img alt="SourceBans" src="../images/sb.png" title="SourceBans" /></a>
          <p>Version {$SB_VERSION}</p>
          <p>{$SB_QUOTE}</p>
        </div>
        <p class="sm">Powered by <a href="http://www.sourcemod.net">SourceMod</a></p>
      </div>
    </div>
  </body>
</html>