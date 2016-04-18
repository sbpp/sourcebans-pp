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

if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();} ?>
 
  <table width="90%" border="0" cellspacing="0" cellpadding="4" align="center">
  <tr>
    <td colspan="5"><h4 id="webtop">{title}</h4></td>
  </tr>
  <tr>
    <td colspan="2" class="tablerow4">Name</td>
    <td class="tablerow4">Flag</td>
    <td colspan="2" class="tablerow4">Purpose</td>
    </tr>
  <tr id="srootcheckbox" name="srootcheckbox">
    <td colspan="2" class="tablerow2">Root Admin (Full Admin Access)</td>
    <td class="tablerow2" align="center">z</td>
    <td class="tablerow2"> Magically enables all flags.</td>
    <td align="center" class="tablerow2"><input type="checkbox" name="s14" id="s14" /></td>
  </tr>
  <tr>
    <td colspan="5" class="tablerow4">Standard Admin Server Permissions </td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Reserved Slots </td>
    <td class="tablerow1" align="center">a</td>
    <td class="tablerow1"> Reserved slot access.</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s1" id="s1" value="1" /></td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Generic</td>
    <td class="tablerow1" align="center">b</td>
    <td class="tablerow1"> Generic admin; required for admins.</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s23" id="s23" /></td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Kick Players </td>
    <td class="tablerow1" align="center">c</td>
    <td class="tablerow1"> Kick other players.</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s2" id="s2" /></td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Ban Players </td>
    <td class="tablerow1" align="center">d</td>
    <td class="tablerow1"> Ban other players.</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s3" id="s3" /></td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Unban Players </td>
    <td align="center" class="tablerow1">e</td>
    <td class="tablerow1"> Remove bans.</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s4" id="s4" /></td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Slay</td>
    <td align="center" class="tablerow1">f</td>
    <td class="tablerow1"> Slay/harm other players.</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s5" id="s5" /></td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Map Changes </td>
    <td align="center" class="tablerow1">g</td>
    <td class="tablerow1"> Change the map or major gameplay features.</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s6" id="s6" /></td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Change cvars </td>
    <td align="center" class="tablerow1">h</td>
    <td class="tablerow1"> Change most cvars.</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s7" id="s7" /></td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Exec Config Files </td>
    <td class="tablerow1" align="center">i</td>
    <td class="tablerow1"> Execute config files.</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s8" id="s8" /></td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Admin Chat  </td>
    <td class="tablerow1" align="center">j</td>
    <td class="tablerow1"> Special chat privileges.</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s9" id="s9" /></td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Start Votes </td>
    <td class="tablerow1" align="center">k</td>
    <td class="tablerow1"> Start or create votes.</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s10" id="s10" /></td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Password Server </td>
    <td class="tablerow1" align="center">l</td>
    <td class="tablerow1"> Set a password on the server.</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s11" id="s11" /></td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Run RCON Commands </td>
    <td class="tablerow1" align="center">m</td>
    <td class="tablerow1"> Use RCON commands.</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s12" id="s12" /></td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Enable Cheats </td>
    <td class="tablerow1" align="center">n</td>
    <td class="tablerow1"> Change sv_cheats or use cheating commands.</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s13" id="s13" /></td>
  </tr>
  <tr>
    <td colspan="5" class="tablerow4">Immunity </td>
  </tr>
  <tr class="tablerow1">
    <td width="15%">&nbsp;</td>
    <td class="tablerow1">Immunity </td>
    <td class="tablerow1" align="center"></td>
    <td class="tablerow1">Choose the immunity level. The higher the number, the more immunity.<br /><div align="center"><input type="text" width="5" name="immunity" id="immunity" /></div></td>
    <td align="center" class="tablerow1"></td>
  </tr>
  <tr>
    <td colspan="5" class="tablerow4">Custom Admin Server Permissions </td>
  </tr>
  <tr class="tablerow1">
    <td>&nbsp;</td>
    <td class="tablerow1">Custom flag &quot;o&quot;  </td>
    <td class="tablerow1" align="center">o</td>
    <td class="tablerow1">&nbsp;</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s17" id="s17" /></td>
  </tr>
  <tr class="tablerow1">
    <td>&nbsp;</td>
    <td class="tablerow1">Custom flag &quot;p&quot; </td>
    <td class="tablerow1" align="center">p</td>
    <td class="tablerow1">&nbsp;</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s18" id="s18" /></td>
  </tr>
  <tr class="tablerow1">
    <td>&nbsp;</td>
    <td class="tablerow1">Custom flag &quot;q&quot; </td>
    <td class="tablerow1" align="center">q</td>
    <td class="tablerow1">&nbsp;</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s19" id="s19" /></td>
  </tr>
  <tr class="tablerow1">
    <td>&nbsp;</td>
    <td class="tablerow1">Custom flag &quot;r&quot; </td>
    <td class="tablerow1" align="center">r</td>
    <td class="tablerow1">&nbsp;</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s20" id="s20" /></td>
  </tr>
  <tr class="tablerow1">
    <td>&nbsp;</td>
    <td class="tablerow1">Custom flag &quot;s&quot; </td>
    <td class="tablerow1" align="center">s</td>
    <td class="tablerow1">&nbsp;</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s21" id="s21" /></td>
  </tr>
  <tr class="tablerow1">
    <td>&nbsp;</td>
    <td class="tablerow1">Custom flag &quot;t&quot; </td>
    <td class="tablerow1" align="center">t</td>
    <td class="tablerow1">&nbsp;</td>
    <td align="center" class="tablerow1"><input type="checkbox" name="s22" id="s22" /></td>
  </tr>
</table>
