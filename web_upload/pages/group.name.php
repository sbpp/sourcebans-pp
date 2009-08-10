<?php 
/**
 * =============================================================================
 * Display group name
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: group.name.php 24 2007-11-06 18:17:05Z olly $
 * =============================================================================
 */

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();} ?>
<table width="90%" border="0" id="group.name" cellpadding="5">
  <tr>
    <td width="445"><div align="right">Group Name: </div></td>
    <td><div align="left">
       <input type="text" class="submit-fields" id="{name}" name="{name}" />
       <div id="{name}_err" class="badentry"></div>
    </div></td>
  </tr>
  </table>
  