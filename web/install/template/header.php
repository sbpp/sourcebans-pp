<!DOCTYPE html>
<?php
ob_start();
if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
?>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <title>SourceBans++</title>
        <link href="includes/css.php" rel="stylesheet" type="text/css" />
        <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css" rel="stylesheet">
        <script type="text/javascript" src="./scripts/mootools.js"></script>
        <script type="text/javascript" src="./scripts/sourcebans.js"></script>
        <link rel="Shortcut Icon" href="./images/favicon.ico">
    </head>
    <body>
        <div style="background-color: #bab5b2;">
            <div id="header">
                <div id="head-logo">
        			<img src="images/logos/sb-large.png" border="0" alt="SourceBans Logo" />
                </div>
            </div>
        </div>
        <div id="tabsWrapper">
            <div id="mainwrapper">
                <div id="tabs">
                    <ul>
