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

class CTabsMenu {
	var $menuItems = array( );
	
	function addMenuItem($title, $id, $description="", $url="", $external=false)
	{
		$curItem = array();
		$curItem['title'] = $title;
		$curItem['desc'] = $description;
		$curItem['url'] = $url;
		$curItem['external'] = $external;
		$curItem['id'] = $id;
		array_push($this->menuItems, $curItem);
	}
	
	function outputMenu()
	{
		$var = $this->menuItems;
		include TEMPLATES_PATH . "/admin.detail.navbar.php";
	}
	
	function getMenuArray()
	{
		return $this->menuItems;
	}
}

?>