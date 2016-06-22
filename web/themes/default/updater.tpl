<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>SourceBans</title>
<link rel="Shortcut Icon" href="./images/favicon.ico" />
<link href="../themes/default/css/css.php" rel="stylesheet" type="text/css" />
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
        <div id="tabs">
        </div>
	</div>

<div id="innerwrapper">
	<div id="navigation">
		<div id="nav"></div>
		<div id="search">
			</div>

	</div><div id="msg-red-debug" style="display:none;" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Debug</b>
	<br />
	<div id="debug-text">
	</div>
</div>

<div id="dialog-placement" style="align:center;display:none;text-align:center;width:892px;margin:0 auto;position:fixed !important;position:absolute;overflow:hidden;top:10px;"> 
</div>


<div id="content_title">
	<b>Updater</b>
</div>
<div id="breadcrumb">
</div>
<div id="content">
<h3>Setup...</h3>
<ul>{$setup}</ul>

{if $progress}
<h3>Updating...</h3>
<ul>{$progress}</ul>
{/if}
</div>

	</div>
	<div id="footer">
		<div id="gc">
		By <a href="http://www.interwavestudios.com" target="_blank" class="footer_link">InterWave Studios</a>		</div>

		<div id="sb"><br/>
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

