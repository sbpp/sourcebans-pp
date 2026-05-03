<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>{$title}</title>
    <link rel="Shortcut Icon" href="themes/{$theme}/images/favicon.ico" />
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.7.0/css/all.css">
    <script type="text/javascript" src="./scripts/sourcebans.js"></script>
    <link href="themes/{$theme}/css/main.css" rel="stylesheet" type="text/css" />
    <script type="text/javascript" src="./scripts/mootools.js"></script>
    <script type="text/javascript" src="./scripts/contextMenoo.js"></script>
    {$xajax nofilter}
</head>
<body>
<div id="header">
    <div id="head-logo">
        <a href="index.php">
            <img src="images/{$logo}" border="0" alt="SourceBans Logo" />
        </a>
    </div>
    <div id="search">
        <form method="get" action="index.php" onsubmit="validateForm(this)">
            <input type="hidden" name="p" value="banlist" />
            <input class="searchbox" alt="Search Bans" name="searchText" type="text" onfocus="this.value='';" {literal}onblur="if (this.value=='') {this.value=' Search Bans...';}"{/literal} value=" Search Bans..." />
            <input type="submit" name="Submit" value="Search" style="cursor:pointer;" class="button" />
        </form>
        <form method="get" action="index.php" onsubmit="validateForm(this)">
            <input type="hidden" name="p" value="commslist" />
            <input class="searchbox" alt="Search Comms" name="searchText" type="text" onfocus="this.value='';" {literal}onblur="if (this.value=='') {this.value=' Search Comms...';}"{/literal} value=" Search Comms... " />
            <input type="submit" name="Submit" value="Search" style="cursor:pointer;" class="button" />
        </form>
    </div>
</div>

{literal}
<script>
    // Based on sourcebans.js
    function isValidID(steamid) {
        const regexes = [
            /STEAM_[0|1]:[0:1]:\d*/,
            /$U:1:\d*$/,
            /U:1:\d*/,
            /\d{17}/
        ];
        return regexes.some(regex => regex.test(steamid));
    }

    // Verify input data and dynamically adjust search
    function validateForm(form) {
        const searchInput = form.querySelector('.searchbox');
        const submitButton = form.querySelector('.button');
        const pageValue = form.querySelector('input[name="p"]').value;

        if (isValidID(searchInput.value)) {
            searchInput.name = 'advSearch';
            submitButton.name = 'advType';
            submitButton.value = 'steamid';
        } else {
            searchInput.name = 'searchText';
            submitButton.name = 'Submit';
            submitButton.value = 'Search';
        }
    }
</script>
{/literal}
