--
-- Table structure for table 'sb_actions'
--

CREATE TABLE {prefix}_actions (
  id int(10) unsigned NOT NULL auto_increment,
  name varchar(64) default NULL,
  steam varchar(32) default NULL,
  ip varchar(15) default NULL,
  message varchar(255) NOT NULL,
  server_id smallint(5) unsigned NOT NULL,
  admin_id smallint(5) unsigned default NULL,
  admin_ip varchar(32) NOT NULL,
  time int(10) unsigned NOT NULL,
  PRIMARY KEY  (id),
  KEY admin_id (admin_id),
  KEY server_id (server_id)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_admins'
--

CREATE TABLE {prefix}_admins (
  id smallint(5) unsigned NOT NULL auto_increment,
  name varchar(64) NOT NULL,
  auth enum('steam','name','ip') NOT NULL,
  identity varchar(64) NOT NULL,
  password varchar(64) default NULL,
  group_id tinyint(3) unsigned default NULL,
  email varchar(128) default NULL,
  language varchar(2) NOT NULL default 'en',
  theme varchar(32) NOT NULL default 'default',
  srv_password varchar(64) default NULL,
  validate varchar(64) default NULL,
  lastvisit int(10) unsigned default NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY name (name),
  UNIQUE KEY auth (auth,identity),
  KEY group_id (group_id)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_admins_srvgroups'
--

CREATE TABLE {prefix}_admins_srvgroups (
  admin_id smallint(5) unsigned NOT NULL,
  group_id smallint(5) unsigned NOT NULL,
  inherit_order int(10) NOT NULL,
  PRIMARY KEY  (admin_id,group_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_bans'
--

CREATE TABLE {prefix}_bans (
  id mediumint(8) unsigned NOT NULL auto_increment,
  type tinyint(1) NOT NULL default '0',
  steam varchar(32) default NULL,
  ip varchar(15) default NULL,
  name varchar(64) default NULL,
  reason varchar(255) NOT NULL,
  length mediumint(8) unsigned NOT NULL,
  server_id smallint(5) unsigned default NULL,
  admin_id smallint(5) unsigned default NULL,
  admin_ip varchar(15) NOT NULL,
  unban_admin_id smallint(5) unsigned default NULL,
  unban_reason varchar(255) default NULL,
  unban_time int(10) unsigned default NULL,
  time int(10) unsigned NOT NULL,
  PRIMARY KEY  (id),
  KEY server_id (server_id),
  KEY admin_id (admin_id),
  KEY unban_admin_id (unban_admin_id)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_blocks'
--

CREATE TABLE {prefix}_blocks (
  ban_id mediumint(8) unsigned NOT NULL,
  name varchar(64) NOT NULL,
  server_id smallint(5) unsigned NOT NULL,
  time int(10) unsigned NOT NULL,
  KEY ban_id (ban_id),
  KEY server_id (server_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_comments'
--

CREATE TABLE {prefix}_comments (
  id mediumint(8) unsigned NOT NULL auto_increment,
  type varchar(1) NOT NULL,
  ban_id mediumint(8) unsigned NOT NULL,
  admin_id smallint(5) unsigned NOT NULL,
  message varchar(255) NOT NULL,
  time int(10) unsigned NOT NULL,
  edit_admin_id smallint(5) unsigned default NULL,
  edit_time int(10) unsigned default NULL,
  PRIMARY KEY  (id),
  KEY ban_id (ban_id),
  KEY admin_id (admin_id),
  KEY edit_admin_id (edit_admin_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_countries'
--

CREATE TABLE sb_countries (
  ip varchar(15) NOT NULL,
  code varchar(2) NOT NULL,
  name varchar(32) NOT NULL,
  PRIMARY KEY (ip)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_demos'
--

CREATE TABLE {prefix}_demos (
  id int(10) unsigned NOT NULL auto_increment,
  ban_id mediumint(8) unsigned NOT NULL,
  type varchar(1) NOT NULL,
  filename varchar(255) NOT NULL,
  PRIMARY KEY  (id),
  KEY ban_id (ban_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_groups'
--

CREATE TABLE {prefix}_groups (
  id tinyint(3) unsigned NOT NULL auto_increment,
  name varchar(32) NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY name (name)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_groups_permissions'
--

CREATE TABLE {prefix}_groups_permissions (
  group_id tinyint(3) unsigned NOT NULL,
  permission_id tinyint(3) unsigned NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_log'
--

CREATE TABLE {prefix}_log (
  id int(10) unsigned NOT NULL auto_increment,
  type enum('m','w','e') NOT NULL,
  title varchar(64) NOT NULL,
  message varchar(255) NOT NULL,
  function varchar(255) NOT NULL,
  query varchar(255) NOT NULL,
  admin_id smallint(5) unsigned NOT NULL,
  admin_ip varchar(15) NOT NULL,
  time int(10) unsigned NOT NULL,
  PRIMARY KEY  (id),
  KEY admin_id (admin_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_mods'
--

CREATE TABLE {prefix}_mods (
  id tinyint(3) unsigned NOT NULL auto_increment,
  name varchar(32) NOT NULL,
  folder varchar(32) NOT NULL,
  icon varchar(32) NOT NULL,
  PRIMARY KEY  (id)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_overrides'
--

CREATE TABLE {prefix}_overrides (
  type enum('command','group') NOT NULL,
  name varchar(32) NOT NULL,
  flags varchar(30) NOT NULL,
  PRIMARY KEY  (type,name)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_permissions'
--

CREATE TABLE {prefix}_permissions (
  id tinyint(3) unsigned NOT NULL auto_increment,
  name varchar(32) NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY name (name)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_plugins'
--

CREATE TABLE {prefix}_plugins (
  name varchar(32) NOT NULL,
  enabled tinyint(1) NOT NULL default '1',
  PRIMARY KEY  (name)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_protests'
--

CREATE TABLE {prefix}_protests (
  id mediumint(8) unsigned NOT NULL auto_increment,
  ban_id mediumint(8) unsigned NOT NULL,
  reason varchar(255) NOT NULL,
  email varchar(128) NOT NULL,
  ip varchar(15) NOT NULL,
  archived tinyint(1) NOT NULL default '0',
  time int(10) unsigned NOT NULL,
  PRIMARY KEY  (id),
  KEY ban_id (ban_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_quotes'
--

CREATE TABLE {prefix}_quotes (
  name varchar(32) NOT NULL,
  text varchar(255) NOT NULL,
  UNIQUE KEY name (name,text)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_servers'
--

CREATE TABLE {prefix}_servers (
  id smallint(5) unsigned NOT NULL auto_increment,
  ip varchar(15) NOT NULL,
  port smallint(5) unsigned NOT NULL default '27015',
  rcon varchar(32) default NULL,
  mod_id tinyint(3) unsigned NOT NULL,
  enabled tinyint(1) NOT NULL default '1',
  PRIMARY KEY  (id),
  KEY mod_id (mod_id)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_servers_srvgroups'
--

CREATE TABLE {prefix}_servers_srvgroups (
  server_id smallint(5) NOT NULL,
  group_id smallint(5) NOT NULL,
  PRIMARY KEY  (server_id,group_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_settings'
--

CREATE TABLE {prefix}_settings (
  name varchar(32) NOT NULL,
  value text NOT NULL,
  PRIMARY KEY  (name)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_srvgroups'
--

CREATE TABLE {prefix}_srvgroups (
  id smallint(5) unsigned NOT NULL auto_increment,
  name varchar(32) NOT NULL,
  flags varchar(32) NOT NULL,
  immunity smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY  (id),
  UNIQUE KEY name (name)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_srvgroups_immunity'
--

CREATE TABLE {prefix}_srvgroups_immunity (
  group_id smallint(5) unsigned NOT NULL,
  other_id smallint(5) unsigned NOT NULL,
  PRIMARY KEY  (group_id,other_id)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_srvgroups_overrides'
--

CREATE TABLE {prefix}_srvgroups_overrides (
  group_id smallint(5) unsigned NOT NULL,
  type enum('command','group') NOT NULL,
  name varchar(32) NOT NULL,
  access enum('allow','deny') NOT NULL,
  PRIMARY KEY  (group_id,type,name)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table 'sb_submissions'
--

CREATE TABLE {prefix}_submissions (
  id mediumint(8) unsigned NOT NULL auto_increment,
  name varchar(64) NOT NULL,
  steam varchar(32) default NULL,
  ip varchar(15) default NULL,
  reason varchar(255) NOT NULL,
  server_id smallint(5) unsigned NOT NULL,
  subname varchar(64) NOT NULL,
  subemail varchar(128) NOT NULL,
  subip varchar(15) NOT NULL,
  archived tinyint(1) NOT NULL default '0',
  time int(10) unsigned NOT NULL,
  PRIMARY KEY  (id),
  KEY server_id (server_id)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Dumping data for table 'sb_mods'
--

INSERT INTO {prefix}_mods (name, folder, icon) VALUES
('Alien Swarm', 'alienswarm', 'alienswarm.png'),
('Counter-Strike: Source', 'cstrike', 'csource.png'),
('CSPromod', 'cspromod', 'cspromod.png'),
('Day of Defeat: Source', 'dod', 'dods.png'),
('Dystopia', 'dystopia', 'dys.gif'),
('E.Y.E: Divine Cybermancy', 'eye', 'eye.png'),
('Fortress Forever', 'FortressForever', 'fortressforever.gif'),
('Garry''s Mod', 'garrysmod', 'gmod.png'),
('GoldenEye: Source', 'gesource', 'gesource.png'),
('Half-Life 2 Capture the Flag', 'hl2ctf', 'hl2ctf.png'),
('Half-Life 2 Deathmatch', 'hl2mp', 'hl2dm.png'),
('Hidden: Source', 'hidden', 'hidden.png'),
('Insurgency', 'insurgency', 'ins.gif'),
('Left 4 Dead', 'left4dead', 'l4d.png'),
('Left 4 Dead 2', 'left4dead2', 'l4d2.png'),
('Nuclear Dawn', 'nucleardawn', 'nucleardawn.png'),
('Obsidian Conflict', 'obsidian', 'obsidian.gif'),
('Perfect Dark: Source', 'pdark', 'pdark.gif'),
('Pirates Vikings and Knights II', 'pvkii', 'pvkii.gif'),
('SourceForts', 'sourceforts', 'sourceforts.gif'),
('Synergy', 'synergy', 'synergy.gif'),
('Team Fortress 2', 'tf', 'tf2.gif'),
('The Ship', 'ship', 'ship.gif'),
('Zombie Panic: Source', 'zps', 'zps.gif');

-- --------------------------------------------------------

--
-- Dumping data for table 'sb_permissions'
--

INSERT INTO {prefix}_permissions (name) VALUES
('OWNER'),
('ADD_ADMINS'),
('DELETE_ADMINS'),
('EDIT_ADMINS'),
('LIST_ADMINS'),
('ADD_BANS'),
('DELETE_BANS'),
('EDIT_ALL_BANS'),
('EDIT_GROUP_BANS'),
('EDIT_OWN_BANS'),
('IMPORT_BANS'),
('UNBAN_ALL_BANS'),
('UNBAN_GROUP_BANS'),
('UNBAN_OWN_BANS'),
('BAN_PROTESTS'),
('BAN_SUBMISSIONS'),
('NOTIFY_PROT'),
('NOTIFY_SUB'),
('ADD_GROUPS'),
('DELETE_GROUPS'),
('EDIT_GROUPS'),
('LIST_GROUPS'),
('ADD_MODS'),
('DELETE_MODS'),
('EDIT_MODS'),
('LIST_MODS'),
('ADD_SERVERS'),
('DELETE_SERVERS'),
('EDIT_SERVERS'),
('LIST_SERVERS'),
('SETTINGS');

-- --------------------------------------------------------

--
-- Dumping data for table 'sb_quotes'
--

INSERT INTO {prefix}_quotes (name, text) VALUES
('Alfred', 'Looks like that custom server.dll you injected in the way is corrupting the vtables of our interfaces.'),
('AnarkiNet', 'does anyone do source modding with C# instead of C++?'),
('BAILOPAN', 'i just join conversation when i see a chance to tell people they might be wrong, then i quickly leave, LIKE A BAT'),
('Brizad', 'I''m not lazy! I just utilize technical resources!'),
('DamagedSoul', 'Good night, talking desk lamp.'),
('Faith', 'I''m just getting a beer'),
('gdogg', 'built in cheat 1.6 - my friend told me theres a cheat where u can buy a car door and run around and it makes u invincible....'),
('Olly', 'Oh no! I''ve overflowed my balls!'),
('SirTiger', 'I wish my lawn was emo, so it would cut itself'),
('sslice', 'I need to mow the lawn'),
('SteamFriend', 'Rave''s momma so fat, she sat on the beach and Greenpeace threw her in'),
('SteveUK', 'I can''t find any .cpp files anywhere even in my steamapps folder'),
('teame06', 'Yams'),
('Viper', 'Buy a new PC!'),
('Viper', 'Get your ass ingame'),
('Viper', 'Hi OllyBunch'),
('Viper', 'Like A Glove!'),
('Viper', 'Mother F***ing Pieces of Sh**'),
('Viper', 'To be honest{if $logged_in} {$username}{/if}..., I DON''T CARE!'),
('Viper', 'You''re a Noob and You Know It!'),
('[Everyone]', 'Let''s just blame it on FlyingMongoose'),
('[Everyone]', 'Shut up Bam'),
('[Unknown]', 'Procrastination is like masturbation. Sure it feels good, but in the end you''re only f***ing yourself!');

-- --------------------------------------------------------

--
-- Dumping data for table 'sb_settings'
--

INSERT INTO {prefix}_settings (name, value) VALUES
('banlist.bansperpage', '25'),
('banlist.hideadminname', '0'),
('config.dateformat', '%m-%d-%y %H:%M'),
('config.debug', '0'),
('config.defaultpage', '0'),
('config.enableprotest', '1'),
('config.enablesubmit', '1'),
('config.exportpublic', '0'),
('config.language', 'en'),
('config.password.minlength', '4'),
('config.summertime', '0'),
('config.theme', 'default'),
('config.timezone', '0'),
('config.version', '294'),
('dash.intro.text', '<img alt="SourceBans Logo" src="images/logo-large.jpg" title="SourceBans Logo" /><h3>Your new SourceBans install</h3><p>SourceBans successfully installed!</p>'),
('dash.intro.title', 'Your SourceBans install'),
('dash.lognopopup', '0'),
('email.host', ''),
('email.password', ''),
('email.port', '25'),
('email.secure', ''),
('email.smtp', '0'),
('email.username', '');