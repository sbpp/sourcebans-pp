<?php
/**
 * =============================================================================
 * Some basic UI builders
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: CUI.php 24 2007-11-06 18:17:05Z olly $
 * =============================================================================
 */

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