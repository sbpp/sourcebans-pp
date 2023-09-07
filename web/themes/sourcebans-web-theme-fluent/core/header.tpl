<!DOCTYPE html>
<html lang="en" class="tee">

<head>
    <script type="text/javascript" src="themes/{$theme}/scripts/initial.js"></script>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap"
        rel="stylesheet">

    <style id="colorTheme" type="text/css"></style>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{$title}</title>
    <link rel="Shortcut Icon" href="themes/{$theme}/images/favicon.ico" />
    <link rel="stylesheet" type="text/css" href="themes/{$theme}/style/global.css?v05212021" />
    <link rel="stylesheet" type="text/css" href="themes/{$theme}/style/global.css.map?v05212021" />
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.4.2/css/all.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.4.2/css/v4-shims.css">
    <meta name="description" content="Sourcebans for website" />
    <script type="text/javascript" src="themes/{$theme}/scripts/sourcebans.js"></script>
    <script type="text/javascript" src="./scripts/mootools.js"></script>
    <script type="text/javascript" src="./scripts/contextMenoo.js"></script>
    {$xajax}
</head>

<body>
    <header class="header">
        <div class="layout_container responsive_hide:mobile flex flex-jc:space-between flex-ai:center">
			<div class="flex flex-fd:column text:left">
				<a href="./index.php?p=home" class="header_logo">
					<img src="images/{$logo}" alt="SourceBans Logo" />
				</a>
			</div>
			<div class="flex flex-fd:column text:right responsive_show:desktop">
            {literal}
                    <form method="get" action="index.php">
                        <input type="hidden" name="p" value="banlist" />
                        <input class="searchbox" alt="Search Bans" name="searchText" type="text" onfocus="this.value='';" onblur="if (this.value=='') {this.value=' Search Bans...';}" value=" Search Bans..." />
                        <input class="button_search" type="submit" name="Submit" value="Search" />						
                    </form>
	            
	                <form method="get" action="index.php">
                        <input type="hidden" name="p" value="commslist" />
                        <input class="searchbox" alt="Search Comms" name="searchText" type="text" onfocus="this.value='';" onblur="if (this.value=='') {this.value=' Search Comms...';}" value=" Search Comms... " />
                        <input class="button_search" type="submit" name="Submit" value="Search" />
                    </form> 
            {/literal}
			</div>
        </div>
    </header>
