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
(04/07/17): Version 1.6.2
-----------------------
```
01. ! Fixed issue with group Banning
02. ! Fixed AmxBans import issue
03. ! Fixed possible XSS Injection
04. + Adjusted regex for CSGO
05. + Added option to disable 'comms' tab
06. ! Fixed bugs with SteamID format
07. ! Fixed version checks
08. ? Git version is now only shown in dev builds
09. ! Fixed issue with email links
10. * Added session based logins
```

(07/05/17): Version 1.6.1
-----------------------
```
01. ! Fixed an issue while XAJAX initialized
02. ! Fixed the 'dash intro text' not displaying custom HTML elements
03. ! Fixed 'change password' function
04. ! Fixed encoding issues with player names
05. ! Fixed aspect ratio of map image
06. ! Fixed editing groups/override pages
07. ! Fixed display error for 'edit mod' page
08. ! Fixed version numbering displaying 0
09. + Improved sizes and file types of images
```

(23/04/17): Version 1.6.0
-----------------------
```
01. ! Fixed some XSS exploits
02. + Improved password hashing / security
03. * Added utf8mb4 support
04. + Updated tinymce
05. + Reformatted most of the code
06. * Added new natives (SourceBans_OnBanPlayer, SourceComms_OnBlockAdded)
07. - Removed DB Info page (potential attack vector)
08. ! various Plugin fixes
09. + Updated Installer Theme
```

(28/04/16): Version 1.5.4.7
-----------------------
```
01. ! Fix Admins and Groups Not Loading from Config
```

(23/04/16): Version 1.5.4.6
-----------------------
```
01. ! Fix Perm Ban bug in SourceSleuth
02. ! Fix Updater
```

(18/04/16): Version 1.5.4.5
-----------------------
```
01. ! Fix Variuous Bugs in the Plugins EXCEPT SourceSleuth
02. ? Updated ADOdb and LightOpenID Library
```

(07/04/16): Version 1.5.4.4
-----------------------
```
01. ! Fix Memory Leak in SourceSleuth Plugin
02. ? Optimized and updated IpToCountry.csv
```

(24/03/16): Version 1.5.4.3
-----------------------
```
01. ! Downgrade plugin to 1.5.3
02. + Add ULX Module for GMOD (Not Maintained by Me)
```

(09/03/16): Version 1.5.4.2
-----------------------
```
01. ! Fix XSS Vulnerability in SourceComms Page
```

(01/03/16): Version 1.5.4.1
-----------------------
```
01. ! Fix Ban/Comm Reason Issue in Plugin
02. ? CC-BY-NC-SA-3.0
```

(14/02/16): Version 1.5.4
-----------------------
```
01. * Added Steam3 ID to Ban and Comm list
02. * Added PHP7 Support
03. + Updated Comms page with better CSS
04. + Small misc theme fixes
05. + Small plugin fixes/optimizations
06. ! Fixed config.php bug with APIKey and URL
07. ! Fix MariaDB Empty Ban List
08. ? Optimized and updated IpToCountry.csv
```

(01/09/15): Version 1.5.3
-----------------------
```
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
```

(29/05/15): Version 1.5.2F
-----------------------
```
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
```

(29/01/15): Version 1.5.1F
-----------------------
```
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
```

(26/12/14): Version 1.5.0F
-----------------------
```
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
```

(02/12/14): Version 1.4.13F
-----------------------
```
01. ! Fixed LFI EXPLOIT //Thanks jsifuentes
02. ? Optimized and updated IpToCountry.csv
```

(15/11/14): Version 1.4.12F
-----------------------
```
01. * Added Steam3 Support for Player Menu
02. * Added IP Ban checking from SourceSlueth
03. ! Fixed Steam Family Sharing Ban Evasion.
04. ? Added SteamWorks Extension
05. ? Optimized and updated IpToCountry.csv
```
