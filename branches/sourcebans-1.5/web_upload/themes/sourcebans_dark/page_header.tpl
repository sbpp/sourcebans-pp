<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{if !empty($header_title)}{$header_title}{else}SourceBans{/if}</title>
    <link rel="shortcut icon" href="images/favicon.ico" />
    <link href="themes/{$theme_name}/css/css.php" rel="stylesheet" type="text/css" />
    <script src="scripts/mootools.js" type="text/javascript"></script>
    <script src="scripts/contextMenoo.js" type="text/javascript"></script>
    <script src="scripts/sourcebans.js" type="text/javascript"></script>
{if !empty($tiny_mce_js)}
    {$tiny_mce_js}
{/if}
    {$xajax_functions|trim|replace:"\t":"    "}
  </head>
  
  <body>
    <div id="mainwrapper">
      <div id="header">
        <div id="head-logo">
          <a href="index.php">
            <img src="images/{$header_logo}" border="0" alt="SourceBans Logo" />
          </a>
        </div>
        <div id="head-userbox">
          Welcome {$username}
{if $logged_in}
          (<a href='index.php?p=logout'>Logout</a>)<br /><a href='index.php?p=account'>Your account</a>
{else}
          (<a href='index.php?p=login'>Login</a>)
{/if}
        </div>
      </div>     
      <div id="tabsWrapper">
        <div id="tabs">
          <ul>
