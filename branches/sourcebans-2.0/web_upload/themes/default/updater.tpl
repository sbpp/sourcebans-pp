<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>Updater | SourceBans</title>
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type" />
    <meta name="author" content="InterWave Studios" />
    <meta name="copyright" content="SourceBans © 2007-2010 InterWaveStudios.com. All rights reserved." />
    <meta name="description" content="Global admin and ban management for the Source engine" />
    <link href="{$uri->base}/images/favicon.ico" rel="shortcut icon" />
    <link href="{$uri->base}/themes/{$theme}/style.css" rel="stylesheet" type="text/css" />
    <style type="text/css">
      {literal}
      #content_title { margin: 55px 0 17px; }
      #content p { margin: 11px 0 11px 40px; }
      {/literal}
    </style>
  </head>
  <body>
    <div id="mainwrapper">
      <div id="header">
        <a href="{build_uri controller=index}" id="head-logo"><h1><span>SourceBans</span></h1></a>
      </div>
      <div id="innerwrapper">
        <h2 id="content_title">Updater</h2>
        <div id="content">
          <h3>Setup...</h3>
          <p>
            Checking current database version... <strong>{$current_version}</strong><br />
            {if $needs_update}
            Updating database to version: <strong>{$latest_version}</strong>
            {else}
            Installation up-to-date.
            {/if}
          </p>
          {if $needs_update}
          <h3>Updating...</h3>
          <p>
            {foreach from=$updates item=update}
            Running update: <strong>v{$update.version}</strong>... 
            {if $update.error}
            <strong>Error executing: {$smarty.const.UPDATER_DIR}data/{$update.version}.php. Stopping Update!</strong>
            {else}
            Done.<br /><br />
            {/if}
            {foreachelse}
            <br />Nothing to update...
            {/foreach}
            {if !$update.error}
            <br />Updated successfully. Please delete the {$smarty.const.UPDATER_DIR} folder.
            {/if}
          </p>
          {/if}
        </div>
      </div>
      <div class="footer">
        <p class="iw">{$language->by} <a href="http://www.interwavestudios.com">InterWave Studios</a></p>
        <div class="sb">
          <a href="http://www.sourcebans.net"><img alt="SourceBans" src="{$uri->base}/images/sb.png" title="SourceBans" /></a>
          <p>{$language->version} {$SB_VERSION}</p>
          <p>"{eval var=$quote_text}" - <em>{$quote_name}</em></p>
        </div>
        <p class="sm">{$language->powered_by} <a href="http://www.sourcemod.net">SourceMod</a></p>
      </div>
    </div>
  </body>
</html>