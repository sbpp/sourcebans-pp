INSERT INTO `{prefix}_mods` (`mid`, `name`, `icon`, `modfolder`, `steam_universe`) VALUES
(1, 'Web', 'web.png', 'NULL', '0'),
(2, 'Half-Life 2 Deathmatch', 'hl2dm.png', 'hl2mp', '0'),
(3, 'Counter-Strike: Source', 'csource.png', 'cstrike', '0'),
(4, 'Day of Defeat: Source', 'dods.png', 'dod', '0'),
(5, 'Insurgency: Source', 'ins.png', 'insurgency', '0'),
(6, 'Dystopia', 'dys.png', 'dystopia_v1', '0'),
(7, 'Hidden: Source', 'hidden.png', 'hidden', '0'),
(8, 'Half-Life 2 Capture the Flag', 'hl2ctf.png', 'hl2ctf', '0'),
(9, 'Pirates Vikings and Knights II', 'pvkii.png', 'pvkii', '0'),
(10, 'Perfect Dark: Source', 'pdark.png', 'pdark', '0'),
(11, 'The Ship', 'ship.png', 'ship', '0'),
(12, 'Fortress Forever', 'hl2-fortressforever.png', 'FortressForever', '0'),
(13, 'Team Fortress 2', 'tf2.png', 'tf', '0'),
(14, 'Zombie Panic', 'zps.png', 'zps', '0'),
(15, "Garry's Mod", 'gmod.png', 'garrysmod', '0'),
(16, "Left 4 Dead", 'l4d.png', 'left4dead', '1'),
(17, "Left 4 Dead 2", 'l4d2.png', 'left4dead2', '1'),
(18, "CSPromod", 'cspromod.png', 'cspromod', '0'),
(19, "Alien Swarm", 'alienswarm.png', 'alienswarm', '0'),
(20, "E.Y.E: Divine Cybermancy", 'eye.png', 'eye', '0'),
(21, "Nuclear Dawn", 'nucleardawn.png', 'nucleardawn', '0'),
(22, "Counter-Strike: Global Offensive", 'csgo.png', 'csgo', '1'),
(23, 'Synergy', 'synergy.png', 'synergy', '0');

UPDATE `{prefix}_mods` SET `mid` = '0' WHERE `name` = 'Web';

INSERT INTO `{prefix}_settings` (`setting`, `value`) VALUES
('dash.intro.text', '<center><p>Your new SourceBans install</p><p>SourceBans successfully installed!</center>'),
('dash.intro.title', 'Your SourceBans install'),
('dash.lognopopup', '0'),
('banlist.bansperpage', '30'),
('banlist.hideadminname', '0'),
('banlist.nocountryfetch', '0'),
('banlist.hideplayerips', '0'),
('bans.customreasons', ''),
('config.password.minlength', '4'),
('config.debug', '0 '),
('template.logo', 'logos/sb-large.png'),
('template.title', 'SourceBans'),
('config.enableprotest', '1'),
('config.enablecomms', '1'),
('config.enablesubmit', '1'),
('config.exportpublic', '0'),
('config.enablekickit', '1'),
('config.dateformat', ''),
('config.theme', 'default'),
('config.defaultpage', '0'),
('config.enablegroupbanning', '0'),
('config.enablefriendsbanning', '0'),
('config.enableadminrehashing', '1'),
('protest.emailonlyinvolved', '0'),
('config.version', '603'),
('config.enablesteamlogin', '1');


INSERT INTO `{prefix}_admins` (
`aid` ,	`user` , `authid` ,	`password` , `gid` , `email` ,	`validate` , `extraflags`, `immunity`)
VALUES (
NULL , 'CONSOLE', 'STEAM_ID_SERVER', '', '0', '', NULL, '0', 0);

UPDATE `{prefix}_admins` SET `aid` = '0' WHERE `authid` = 'STEAM_ID_SERVER';
