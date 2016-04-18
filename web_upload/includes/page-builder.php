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

$_GET['p'] = isset($_GET['p']) ? $_GET['p'] : 'default';
$_GET['p'] = trim($_GET['p']);
switch ($_GET['p'])
{
	case "login":
		$page = TEMPLATES_PATH . "/page.login.php";
		break;
	case "logout":
		logout();
		Header("Location: index.php");
		break;
	case "admin":
		$page = INCLUDES_PATH . "/admin.php";
		break;
	case "submit":
		RewritePageTitle("Submit a Ban");
		$page = TEMPLATES_PATH . "/page.submit.php";
		break;
	case "banlist":
		RewritePageTitle("Ban List");
		$page = TEMPLATES_PATH ."/page.banlist.php";
		break;
	case "commslist":
		RewritePageTitle("Communications Block List");
		$page = TEMPLATES_PATH ."/page.commslist.php";
		break;
	case "servers":
		RewritePageTitle("Server List");
		$page = TEMPLATES_PATH . "/page.servers.php";
		break;
	case "serverinfo":
		RewritePageTitle("Server Info");
		$page = TEMPLATES_PATH . "/page.serverinfo.php";
		break;
	case "protest":
		RewritePageTitle("Protest a Ban");
		$page = TEMPLATES_PATH . "/page.protest.php";
		break;
	case "account":
		RewritePageTitle("Your Account");
		$page = TEMPLATES_PATH . "/page.youraccount.php";
		break;
	case "lostpassword":
		RewritePageTitle("Lost your password");
		$page = TEMPLATES_PATH . "/page.lostpassword.php";
		break;
	case "home":
		RewritePageTitle("Dashboard");
		$page = TEMPLATES_PATH . "/page.home.php";
		break;
	default:
		switch($GLOBALS['config']['config.defaultpage'])
		{
			case 1:
				RewritePageTitle("Ban List");
				$page = TEMPLATES_PATH . "/page.banlist.php";
				$_GET['p'] = "banlist";
				break;
			case 2:
				RewritePageTitle("Server Info");
				$page = TEMPLATES_PATH . "/page.servers.php";
				$_GET['p'] = "servers";
				break;
			case 3:
				RewritePageTitle("Submit a Ban");
				$page = TEMPLATES_PATH . "/page.submit.php";
				$_GET['p'] = "submit";
				break;
			case 4:
				RewritePageTitle("Protest a Ban");
				$page = TEMPLATES_PATH . "/page.protest.php";
				$_GET['p'] = "protest";
				break;
			default: //case 0:
				RewritePageTitle("Dashboard");
				$page = TEMPLATES_PATH . "/page.home.php";
				$_GET['p'] = "home";
				break;
		}
}

global $ui;
$ui = new CUI();
BuildPageHeader();
BuildPageTabs();
BuildSubMenu();
BuildContHeader();
BuildBreadcrumbs();
if(!empty($page))
	include $page;
include_once(TEMPLATES_PATH . '/footer.php');
?>
