<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xml:lang="en" xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <title>SourceBans</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <link href="../images/favicon.ico" rel="shortcut icon" />
    <link href="../themes/default/css/css.php" rel="stylesheet" type="text/css" />
    <link href="template/css/css.php" rel="stylesheet" type="text/css" />
    <script src="../scripts/mootools.js" type="text/javascript"></script>
    <script src="../scripts/sourcebans.js" type="text/javascript"></script>
  </head>
  <body>
    <div id="mainwrapper">
      <div id="header">
        <div id="head-logo">
          <img alt="SourceBans Logo" src="../images/logos/sb-large.png" />
        </div>
      </div>     
      <div id="tabsWrapper">
        <div id="tabs">
          <ul>
            <li><a href="http://www.sourcebans.net" target="_blank">SourceBans</a></li>
            <li><a href="http://www.sourcemod.net" target="_blank">SourceMod</a></li>
          </ul>
        </div>
      </div>
      <div id="innerwrapper">
        <div id="navigation">
          <div id="nav"></div>
          <div id="search"></div>
        </div>
        <div id="msg-red-debug" style="display: none;" >
          <i><img alt="Warning" src="../images/warning.png" /></i>
          <b>Debug</b>
          <br />
          <div id="debug-text"></div>
        </div>
        <div id="dialog-placement" style="vertical-align: middle; display: none; text-align: center; width: 892px; margin: 0 auto; position: fixed !important; position: absolute; overflow: hidden; top: 10px; left: 100px;">
          <table width="460" id="dialog-holder" class="dialog-holder" border="0" cellspacing="0" cellpadding="0">
            <tbody width="460">
              <tr>
                <td class="dialog-topleft"></td>
                <td class="dialog-border"></td>
                <td class="dialog-topright"></td>
              </tr>
              <tr>
                <td class="dialog-border"></td>
                <td class="box-content">
                  <div id="dragbar" style="cursor: pointer;">
                    <ilayer width="100%" onselectstart="return false">
                      <layer width="100%" onmouseover="dragswitch=1;if (ns4) drag_drop_ns(dialog-placement)" onmouseout="dragswitch=0">
                        <h2 align="left" id="dialog-title" class="ok"></h2>
                      </layer>
                    </ilayer>
                  </div>
                  <div class="dialog-content" align="left">
                    <div class="dialog-body">
                      <div class="clearfix">
                        <div style="float: left; margin-right: 15px;" id="dialog-icon" class="icon-info">&nbsp;</div>
                        <div style="width: 360px; float: right; padding-bottom: 5px; font-size: 11px;" id="dialog-content-text"></div>
                      </div>
                    </div>
                    <div class="dialog-control" id="dialog-control"></div>
                  </div>
                </td>
                <td class="dialog-border"></td>
              </tr>
              <tr>
                <td class="dialog-bottomleft"></td>
                <td class="dialog-border"></td>
                <td class="dialog-bottomright"></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div id="content_title">Step <?php echo $step ?> - <?php echo $page_title ?></div>
        <div id="content">
          <div id="install-progress">
            <strong>Installation Progress</strong>
<?php foreach($steps as $i => $title): ?>
            <br />
            <span
<?php if($i == $step): ?>
              class="current"
<?php elseif($i < $step): ?>
              class="done"
<?php endif ?>
            >Step <?php echo $i ?>: <?php echo $title ?></span>
<?php endforeach ?>
          </div>
