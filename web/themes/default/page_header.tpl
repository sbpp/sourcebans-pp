<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>{if $header_title != ""}{$header_title}{else}SourceBans{/if}</title>
<link rel="Shortcut Icon" href="./images/favicon.ico" />
<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet">
<script type="text/javascript" src="./scripts/sourcebans.js"></script>
<link href="themes/{$theme_name}/css/css.php" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="./scripts/mootools.js"></script>
<script type="text/javascript" src="./scripts/contextMenoo.js"></script>


{$tiny_mce_js}
{$xajax_functions}


</head>
<body>


    <div style="background-color: #bab5b2;">
	<div id="header">
		<div id="head-logo">
    		<a href="index.php">
    			<img src="images/{$header_logo}" border="0" alt="SourceBans Logo" />
    		</a>
		</div>
	</div>
	</div>  
	<div id="tabsWrapper">
		<div id="mainwrapper">
            <div id="tabs">
	            {if $logged_in}
	                <div style="float: right;">
	         	        <ul>
	         	        	<li>
	         	        		<a style="background-color: #B8383B;" href='index.php?p=logout'>Logout</a>
	         	        	</li>
	         	        </ul>
	         	    </div>
	         	    <div class="user">Welcome, <a href='index.php?p=account'>{$username}</a></div>
	                {else}
	                <div style="float: right;">
	                    <ul>
	                    	<li>
	          	                <a style="background-color: #70B04A;" href='index.php?p=login'>Login</a>
	          	            </li>
	          	        </ul>
	          	    </div>
	            {/if}
                <ul>
         
