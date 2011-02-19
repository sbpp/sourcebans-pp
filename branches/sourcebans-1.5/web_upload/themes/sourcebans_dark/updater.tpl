<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>SourceBans</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <link href="./images/favicon.ico" rel="shortcut icon" />
    <link href="../themes/{$theme_name}/css/css.php" rel="stylesheet" type="text/css" />
    <style type="text/css">
      {literal}
      #content p { margin: 11px 0 11px 40px; }
      {/literal}
    </style>
  </head>
  <body>
    <div id="mainwrapper">
      <div id="header">
        <div id="head-logo">
          <a href="index.php">
            <img src="../images/logos/sb-large.png" border="0" alt="SourceBans Logo" />
          </a>
        </div>
      </div>
      <div id="tabsWrapper">
        <div id="tabs"></div>
      </div>
      <div id="innerwrapper">
        <div id="navigation">
          <div id="nav"></div>
          <div id="search"></div>
        </div>
        <div id="msg-red-debug" style="display:none;">
          <i><img src="./images/warning.png" alt="Warning" /></i>
          <b>Debug</b>
          <br />
          <div id="debug-text">
          </div>
        </div>
        <div id="dialog-placement" style="align:center;display:none;text-align:center;width:892px;margin:0 auto;position:fixed !important;position:absolute;overflow:hidden;top:10px;">
        </div>
        <div id="content_title">Updater</div>
        <div id="breadcrumb"></div>
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
        <div id="gc">
          By <a class="footer_link" href="http://www.interwavestudios.com" target="_blank">InterWave Studios</a>
        </div>
        <div id="sb">
          <br/>
        </div>
        <div id="sm">
          Powered by <a class="footer_link" href="http://www.sourcemod.net" target="_blank">SourceMod</a>
        </div>
      </div>
    </div>
    <!--[if lt IE 7]>
    <script defer type="text/javascript" src="./scripts/pngfix.js"></script>
    <![endif]-->
  </body>
</html>