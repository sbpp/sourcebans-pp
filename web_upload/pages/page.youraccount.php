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

global $userbank, $theme;

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();}
if($userbank->GetAid() == -1){echo "You shoudnt be here. looks like we messed up ><";die();}
		
$groupsTabMenu = new CTabsMenu();
$groupsTabMenu->addMenuItem("View Permissions", 0);
$groupsTabMenu->addMenuItem("Change Password", 1);
$groupsTabMenu->addMenuItem("Server Password", 2);
$groupsTabMenu->addMenuItem("Change Email", 3);
$groupsTabMenu->outputMenu();

$res = $GLOBALS['db']->Execute("SELECT `srv_password`, `email` FROM `".DB_PREFIX."_admins` WHERE `aid` = '".$userbank->GetAid()."'");
$srvpwset = (!empty($res->fields['srv_password'])?true:false);

$theme->assign('srvpwset',				$srvpwset);
$theme->assign('email',					$res->fields['email']);
$theme->assign('user_aid',				$userbank->GetAid());
$theme->assign('web_permissions',		BitToString($userbank->GetProperty("extraflags")));
$theme->assign('server_permissions',	SmFlagsToSb($userbank->GetProperty("srv_flags")));
$theme->assign('min_pass_len',			MIN_PASS_LENGTH);

$theme->left_delimiter = "-{";
$theme->right_delimiter = "}-";
$theme->display('page_youraccount.tpl');
$theme->left_delimiter = "{";
$theme->right_delimiter = "}";
?>