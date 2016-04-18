<?php 
// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2016 Sarabveer Singh <me@sarabveer.me>
//
//  SourceBans++ is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, per version 3 of the License.
//
//  SourceBans++ is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with SourceBans++. If not, see <http://www.gnu.org/licenses/>.
//
//  This file is based off work covered by the following copyright(s):  
//
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();} 

global $theme, $userbank;

if(!isset($_GET['id']))
{
	echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	No submission or protest id specified. Please only follow links
</div>';
	PageDie();
}

if(!isset($_GET['type']) || ($_GET['type'] != 's' && $_GET['type'] != 'p'))
{
	echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	Invalid type. Please only follow links
</div>';
	PageDie();
}

// Submission
$email = "";
if($_GET['type'] == 's')
{
	$email = $GLOBALS['db']->GetOne('SELECT email FROM `'.DB_PREFIX.'_submissions` WHERE subid = ?', array($_GET['id']));
}
// Protest
else if($_GET['type'] == 'p')
{
	$email = $GLOBALS['db']->GetOne('SELECT email FROM `'.DB_PREFIX.'_protests` WHERE pid = ?', array($_GET['id']));
}

if(empty($email))
{
	echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	There is no email to send to supplied.
</div>';
	PageDie();
}

$theme->assign('email_addr', htmlspecialchars($email));
$theme->assign('email_js', "CheckEmail('".$_GET['type']."', ".(int)$_GET['id'].")");
?>

<div id="admin-page-content">
	<div id="1">
		<?php $theme->display('page_admin_bans_email.tpl'); ?>
	</div>
</div>
