<?php
/**
 * Some basic UI builders
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
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