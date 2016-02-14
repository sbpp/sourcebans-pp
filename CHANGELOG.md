SourceBans++ Change Log
============

```
Legend:
* = New feature
- = Removed stuff
+ = Improved feature
! = Fixed bug
? = Other stuff
```

(14/02/16): Version 1.5.4
-----------------------
01. * Added Steam3 ID to Ban and Comm list
02. * Added PHP7 Support
03. + Updated Comms page with better CSS
04. + Small misc theme fixes
05. + Small plugin fixes/optimizations
06. ! Fixed config.php bug with APIKey and URL
07. ! Fix MariaDB Empty Ban List
08. ? Optimized and updated IpToCountry.csv

(01/09/15): Version 1.5.3
-----------------------
01. * Added Steam API Key to Installer for Future Use
02. * Added Steam OpenID Login Support
03. * Added Updater Support
04. * Added Own Admin Config System (No More admins.cfg)
05. + Updated Smarty Library to 2.6.29
06. + Updated Plugins to use partial SourceMod 1.7 Syntax/API
07. + Changed Webpanel Background Color
08. ! Fixed Email Injection Bug on Webpanel
09. ! Fixed admin-flatfile issue in TF2 with New Config System
10. ! Fixed RCON on webpanel skipping NULL characters (RCON XML error)
11. ! Fixed importing banned_user.cfg with Steam3 [U:1:X]
12. ! Fixed BoxToMask Issue #52 in SourceBans.js
13. ! Fix HHVM issues with ADOdb
14. ? Optimized and updated IpToCountry.csv

(29/05/15): Version 1.5.2F
-----------------------
01. * Changed licence to GNU AGPL v3
02. * Replaced GetClientAuthString with GetClientAuthId for SourceMod 1.7
03. * Added IP Banning with SourceSleuth
04. + Updated ADOdb Library to 5.19
05. + Updated TinyMCE Library to 3.5.11
06. - SourceMod 1.6.x and below are not supported
07. - Removed FamilySharing Ban Evasion Detection
08. - MariaDB not does not work anymore (Never was supported anyways)
09. ! Fixed Ban List lagging on MySQL 5.6+ 
10. ! Fixed Plugin Showing DataPack error
11. ! Fixed KickId in Webpanel not working when trying to use Steam3
12. ? Optimized and updated IpToCountry.csv

(29/01/15): Version 1.5.1F
-----------------------
01. * Changed licence to GNU GPL v3
02. * Added SourceBans Connection Debugger
03. * Added SourceComms Search Box 
04. + Re-made SourceBans Logo in Footer
05. ! Fixed getdemo.php spewing errors
06. ! Fixed Invalid Query in SB Plugin
07. ! Fixed parsing rcon status in CS:GO
08. ? Added/Fixed Copyright Headers
09. ? Updated SteamWorks Ext to git90
10. ? Optimized and updated IpToCountry.csv

(26/12/14): Version 1.5.0F
-----------------------
01. * Integrated SourceComms
02. * Added TF2 Modern Theme as Default (Made by IceMan)
03. * Integrated SourceBans Checker
04. * Re-made SourceBans FAQ
05. * Added MvM and HL2 Map Pics
06. * Added Synergy to the Game List
07. + Re-arranged/Renamed Tabs
08. + Added More Robust LFI Patch
09. ! Fixed Plugin Pointing to wrong FAQ link
10. ? Optimized and updated IpToCountry.csv

(02/12/14): Version 1.4.13F
-----------------------
01. ! Fixed LFI EXPLOIT //Thanks jsifuentes
02. ? Optimized and updated IpToCountry.csv

(15/11/14): Version 1.4.12F
-----------------------
01. * Added Steam3 Support for Player Menu
02. * Added IP Ban checking from SourceSlueth
03. ! Fixed Steam Family Sharing Ban Evasion.
04. ? Added SteamWorks Extension
05. ? Optimized and updated IpToCountry.csv