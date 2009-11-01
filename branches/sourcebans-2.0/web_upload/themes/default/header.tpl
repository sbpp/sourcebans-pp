<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>{if $smarty.server.PHP_SELF|basename != 'index.php'}{$page_title} | {/if}SourceBans</title>
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type" />
    <meta name="author" content="InterWave Studios" />
    <meta name="copyright" content="SourceBans © 2007-2009 InterWaveStudios.com. All rights reserved." />
    <meta name="description" content="Global admin and ban management for the Source engine" />
    <link href="images/favicon.ico" rel="shortcut icon" />
    <link href="themes/{$theme_dir}/style.css" rel="stylesheet" type="text/css" />
    {foreach from=$styles item=style}
    <link href="{$style}" rel="stylesheet" type="text/css" />
    {/foreach}
    <script src="scripts/tiny_mce/tiny_mce.js" type="text/javascript"></script>
    <script src="scripts/mootools.js" type="text/javascript"></script>
    <script src="scripts/contextMenoo.js" type="text/javascript"></script>
    <script src="scripts/iepngfix_tilebg.js" type="text/x-component"></script>
    <script src="scripts/sajax.php" type="text/javascript"></script>
    <script src="scripts/swfaddress.js" type="text/javascript"></script>
    <script src="scripts/sourcebans.js" type="text/javascript"></script>
    <script src="themes/{$theme_dir}/script.js" type="text/javascript"></script>
    {foreach from=$scripts item=script}
    <script src="{$script}" type="text/javascript"></script>
    {/foreach}
  </head>
  <body>
    <table id="dialog">
      <tr>
        <td class="dialog-topleft"></td>
        <td class="dialog-border"></td>
        <td class="dialog-topright"></td>
      </tr>
      <tr>
        <td class="dialog-border"></td>
        <td class="dialog-content">
          <h2 id="dialog-title"></h2>
          <div class="dialog-body">
            <div class="clearfix">
              <div class="dialog-icon"></div>
              <div id="dialog-text"></div>
            </div>
          </div>
          <div id="dialog-control">
            <input class="btn ok" id="dialog-submit" type="submit" value="{$lang_submit}" />
            <input class="btn cancel" id="dialog-back" type="button" value="{$lang_back}" />
          </div>
        </td>
        <td class="dialog-border"></td>
      </tr>
      <tr>
        <td class="dialog-bottomleft"></td>
        <td class="dialog-border"></td>
        <td class="dialog-bottomright"></td>
      </tr>
    </table>
    <div id="ajax-indicator"><span>{$lang_please_wait}...</span></div>
    <div id="mainwrapper">
      <div id="header">
        <a href="index.php" id="head-logo"><h1><span>SourceBans</span></h1></a>
        <div id="head-userbox">
          {$lang_welcome}
          {if $is_logged_in}
          {$username} (<a href="{build_url _=logout.php}">{$lang_logout}</a>)
          <br /><a href="{build_url _=account.php}">{$lang_your_account}</a>
          {else}
          (<a href="{build_url _=login.php}">{$lang_login}</a>)
          {/if}
        </div>
      </div>
      <div id="tabsWrapper">
        <ul id="tabs">
          <li{if $active == "dashboard.php"} class="active"{/if}><span class="tabfill"><a class="tips" href="{build_url _=dashboard.php}" title="{$lang_dashboard|ucwords} :: {$lang_dashboard_desc}">{$lang_dashboard|ucwords}</a></span></li>
          <li{if $active == "banlist.php"} class="active"{/if}><span class="tabfill"><a class="tips" href="{build_url _=banlist.php}" title="{$lang_ban_list|ucwords} :: {$lang_ban_list_desc}">{$lang_ban_list|ucwords}</a></span></li>
          <li{if $active == "servers.php"} class="active"{/if}><span class="tabfill"><a class="tips" href="{build_url _=servers.php}" title="{$lang_servers|ucwords} :: {$lang_servers_desc}">{$lang_servers|ucwords}</a></span></li>
          {if $enable_submit}
          <li{if $active == "submitban.php"} class="active"{/if}><span class="tabfill"><a class="tips" href="{build_url _=submitban.php}" title="{$lang_submit_ban|ucwords} :: {$lang_submit_ban_desc}">{$lang_submit_ban|ucwords}</a></span></li>
          {/if}
          {if $enable_protest}
          <li{if $active == "protestban.php"} class="active"{/if}><span class="tabfill"><a class="tips" href="{build_url _=protestban.php}" title="{$lang_protest_ban|ucwords} :: {$lang_protest_ban_desc}">{$lang_protest_ban|ucwords}</a></span></li>
          {/if}
          {if $is_admin}
          <li{if $active == "admin.php"} class="active"{/if}><span class="tabfill"><a class="tips" href="{build_url _=admin.php}" title="{$lang_administration|ucwords} :: {$lang_administration_desc}">{$lang_administration|ucwords}</a></span></li>
          {/if}
          {foreach from=$tabs item=tab}
          <li{if $active == $tab.url} class="active"{/if}><span class="tabfill"><a class="tips" href="{$tab.url}" title="{$tab.name} :: {$tab.desc}">{$tab.name}</a></span></li>
          {/foreach}
        </ul>
        <ul id="nav">
          {if $user_permission_admins}
          <li><a href="{build_url _=admin_admins.php}">{$lang_admins}</a></li>
          {/if}
          {if $user_permission_bans}
          <li><a href="{build_url _=admin_bans.php}">{$lang_bans}</a></li>
          {/if}
          {if $user_permission_groups}
          <li><a href="{build_url _=admin_groups.php}">{$lang_groups}</a></li>
          {/if}
          {if $user_permission_mods}
          <li><a href="{build_url _=admin_mods.php}">{$lang_mods}</a></li>
          {/if}
          {if $user_permission_servers}
          <li><a href="{build_url _=admin_servers.php}">{$lang_servers}</a></li>
          {/if}
          {if $user_permission_settings}
          <li><a href="{build_url _=admin_settings.php}">{$lang_settings}</a></li>
          {/if}
        </ul>
        <form action="banlist.php" id="search" method="get">
          <fieldset>
            <input class="searchbox" name="search" onblur="{literal}if (this.value=='') {this.value=' {/literal}{$lang_search_bans|ucwords}{literal}...';}{/literal}" onfocus="this.value='';" value=" {$lang_search_bans|ucwords}..." /><input class="searchbtn" type="submit" value="" />
          </fieldset>
        </form>
      </div>
      <div id="innerwrapper">
        <h2 id="content_title">{$page_title}</h2>
        <div id="breadcrumb">
          » <a href="{build_url _=index.php}">Home</a>
          {foreach from=$breadcrumb_links item=link}
          » <a href="{$link.href}">{$link.title}</a>
          {/foreach}
          » <strong>{$page_title}</strong>
        </div>
        <div id="content">
