<?php 
/**
 * Display group name
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
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
  