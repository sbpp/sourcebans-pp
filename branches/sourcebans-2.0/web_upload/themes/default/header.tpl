<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xml:lang="{$language}" xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>{$action_title} « {if $controller_title != $action_title}{$controller_title} « {/if}SourceBans</title>
    <meta content="text/html; charset=UTF-8" http-equiv="Content-Type" />
    <meta name="author" content="InterWave Studios" />
    <meta name="copyright" content="SourceBans © 2007-2010 InterWaveStudios.com. All rights reserved." />
    <meta name="description" content="Global admin and ban management for the Source engine" />
    <link href="{$uri->base}/images/favicon.ico" rel="shortcut icon" />
    <link href="{$uri->base}/themes/{$theme}/style.css" rel="stylesheet" type="text/css" />
{foreach from=$styles item=style}
    <link href="{$style}" rel="stylesheet" type="text/css" />
{/foreach}
    <script src="{$uri->base}/scripts/tiny_mce/tiny_mce.js" type="text/javascript"></script>
    <script src="{$uri->base}/scripts/mootools-core.js" type="text/javascript"></script>
    <script src="{$uri->base}/scripts/mootools-more.js" type="text/javascript"></script>
    <script src="{$uri->base}/scripts/contextMenoo.js" type="text/javascript"></script>
    <script src="{$uri->base}/scripts/iepngfix_tilebg.js" type="text/x-component"></script>
    <script src="{$uri->base}/scripts/ajax.php" type="text/javascript"></script>
    <script src="{$uri->base}/scripts/swfaddress.js" type="text/javascript"></script>
    <script src="{$uri->base}/scripts/sourcebans.js" type="text/javascript"></script>
    <script src="{$uri->base}/themes/{$theme}/script.js" type="text/javascript"></script>
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
            <input class="btn ok" id="dialog-submit" type="submit" value="{$language->submit}" />
            <input class="btn cancel" id="dialog-back" type="button" value="{$language->back}" />
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
    <div id="ajax-indicator"><span>{$language->please_wait}...</span></div>
    <div id="mainwrapper">
      <div id="header">
        <a href="{build_uri controller=index}" id="head-logo"><h1><span>SourceBans</span></h1></a>
        <div id="head-userbox">
          {$language->welcome}
{if $user->is_logged_in()}
          {$user->name} (<a href="{build_uri controller=logout}">{$language->logout}</a>)
          <br /><a href="{build_uri controller=account}">{$language->your_account}</a>
{else}
          (<a href="{build_uri controller=login}">{$language->login}</a>)
{/if}
        </div>
      </div>
      <div id="tabsWrapper">
        <ul id="tabs">
          <li{if $uri->controller == "dashboard"} class="active"{/if}><span class="tabfill"><a class="tips" href="{build_uri controller=dashboard}" title="{$language->dashboard|ucwords} :: {$language->dashboard_desc}">{$language->dashboard|ucwords}</a></span></li>
          <li{if $uri->controller == "bans"} class="active"{/if}><span class="tabfill"><a class="tips" href="{build_uri controller=bans}" title="{$language->bans|ucwords} :: {$language->bans_desc}">{$language->bans|ucwords}</a></span></li>
          <li{if $uri->controller == "servers"} class="active"{/if}><span class="tabfill"><a class="tips" href="{build_uri controller=servers}" title="{$language->servers|ucwords} :: {$language->servers_desc}">{$language->servers|ucwords}</a></span></li>
{if $settings->enable_submit}
          <li{if $uri->controller == "submitban"} class="active"{/if}><span class="tabfill"><a class="tips" href="{build_uri controller=submitban}" title="{$language->submit_ban|ucwords} :: {$language->submit_ban_desc}">{$language->submit_ban|ucwords}</a></span></li>
{/if}
{if $settings->enable_protest}
          <li{if $uri->controller == "protestban"} class="active"{/if}><span class="tabfill"><a class="tips" href="{build_uri controller=protestban}" title="{$language->protest_ban|ucwords} :: {$language->protest_ban_desc}">{$language->protest_ban|ucwords}</a></span></li>
{/if}
{if $user->is_admin()}
          <li{if $uri->controller == "admin"} class="active"{/if}><span class="tabfill"><a class="tips" href="{build_uri controller=admin}" title="{$language->administration|ucwords} :: {$language->administration_desc}">{$language->administration|ucwords}</a></span></li>
{/if}
{foreach from=$tabs item=tab}
          <li{if $uri->controller == $tab->uri->controller} class="active"{/if}><span class="tabfill"><a class="tips" href="{$tab->uri}" title="{$tab->name} :: {$tab->description}">{$tab->name}</a></span></li>
{/foreach}
        </ul>
        <ul id="nav">
{if $user->permission_admins}
          <li><a href="{build_uri controller=admin action=admins}">{$language->admins}</a></li>
{/if}
{if $user->permission_bans}
          <li><a href="{build_uri controller=admin action=bans}">{$language->bans}</a></li>
{/if}
{if $user->permission_groups}
          <li><a href="{build_uri controller=admin action=groups}">{$language->groups}</a></li>
{/if}
{if $user->permission_servers}
          <li><a href="{build_uri controller=admin action=servers}">{$language->servers}</a></li>
{/if}
{if $user->permission_games}
          <li><a href="{build_uri controller=admin action=games}">{$language->games}</a></li>
{/if}
{if $user->permission_settings}
          <li><a href="{build_uri controller=admin action=settings}">{$language->settings}</a></li>
{/if}
        </ul>
        <form action="bans.php" id="search" method="get">
          <fieldset>
            <input id="searchbox" name="search" value=" {$language->search_bans|ucwords}..." /><input class="searchbtn" type="submit" value="" />
          </fieldset>
        </form>
      </div>
      <div id="innerwrapper">
        <h2 id="content_title">{$action_title}</h2>
        <div id="breadcrumb">
          » <a href="{build_uri controller=index}">Home</a>
{if $uri->controller != "index" && $uri->action != "index" && isset($controller_title)}
          » <a href="{build_uri controller=$uri->controller}">{$controller_title}</a>
{/if}
          » <strong>{$action_title}</strong>
        </div>
        <div id="content">
