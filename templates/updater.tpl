<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title>Updater | SourceBans++</title>
        <link rel="Shortcut Icon" href="../themes/default/images/favicon.ico" />
        <link href="../themes/default/css/main.css" rel="stylesheet" type="text/css" />
    </head>
    <body>
        <div id="header">
            <div id="head-logo">
                <a href="index.php"><img src="../images/logos/sb-large.png" border="0" alt="SourceBans Logo" /></a>
            </div>
        </div>
        <div id="tabsWrapper"></div>
        <div id="mainwrapper">
            <div id="content_title" style="padding: 1rem 0rem"><b>Updater</b></div>
            <div id="content">
                <h3>Updating...</h3>
                {foreach from=$updates item=update}
                    <ul style="font-size: 13px">{$update}</ul>
                {/foreach}
            </div>
        </div>
        <div id="footer">
            <div id="mainwrapper" style="text-align: center;">
    			<a href="https://sbpp.github.io/" target="_blank"><img src="../images/sb.png" alt="SourceBans++" border="0" /></a><br/>
    			<span style="line-height: 20px;"><a style="color: #C1C1C1" href="https://sbpp.github.io/" target="_blank">SourceBans++</a></span><br/>
    		    <span style="line-height: 20px;">Powered by <a href="http://www.sourcemod.net" target="_blank" style="color: #C1C1C1">SourceMod</a></span>
    		</div>
    	</div>
    </body>
</html>
