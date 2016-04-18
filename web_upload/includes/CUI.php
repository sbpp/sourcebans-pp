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

class CUI
{
	function drawButton($text, $click, $class, $id="", $submit=false)
	{
		$type = $submit ? "submit" : "button";
		$button = "<input type='$type' onclick=\"$click\" name='$id' class='btn $class' onmouseover='ButtonOver(\"$id\")' onmouseout='ButtonOver(\"$id\")' id='$id' value='$text' />";
		return $button;
	}
	
	function drawInlineBox($title, $text, $color)
	{
		$icon = "";
		switch($color)
		{
			case "red":
				$icon = "warning";
			break;
			case "blue":
				$icon = "info";
			break;
			case "green":
				$icon = "yay";
		}
		$text = '<div id="msg-'.$color.'-debug" style="">
				 <i><img src="./images/'.$icon.'.png" alt="MsgIcon" /></i>
				 <b>' . $title .'</b>
				 <br />
		 		' . $text . '</i>
				</div>';
		return $text;
	}
}
?>