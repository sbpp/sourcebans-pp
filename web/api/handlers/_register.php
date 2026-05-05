<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

Centralised handler registry. Every action ends up here so the
action -> permission table is reviewable in one place.
*************************************************************************/

require_once __DIR__ . '/account.php';
require_once __DIR__ . '/admins.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/bans.php';
require_once __DIR__ . '/blockit.php';
require_once __DIR__ . '/comms.php';
require_once __DIR__ . '/groups.php';
require_once __DIR__ . '/kickit.php';
require_once __DIR__ . '/mods.php';
require_once __DIR__ . '/protests.php';
require_once __DIR__ . '/servers.php';
require_once __DIR__ . '/submissions.php';
require_once __DIR__ . '/system.php';

// ---- public actions (no auth required) --------------------------------
Api::register('auth.login',         'api_auth_login',         0, false, true);
Api::register('auth.lost_password', 'api_auth_lost_password', 0, false, true);
// bans.detail mirrors the public ban-list page's reach: any visitor can
// click a ban row in the sbpp2026 drawer (#1123 C1). Player IP, admin
// name, removed-by and comments are gated *inside* the handler against
// banlist.hideplayerips / banlist.hideadminname / config.enablepubliccomments
// + is_admin(), matching page.banlist.php exactly so we don't leak
// fields the page intentionally suppresses.
Api::register('bans.detail',        'api_bans_detail',        0, false, true);

// ---- account: dispatcher enforces login; handler enforces aid match ---
Api::register('account.check_password',     'api_account_check_password');
Api::register('account.change_password',    'api_account_change_password');
Api::register('account.check_srv_password', 'api_account_check_srv_password');
Api::register('account.change_srv_password','api_account_change_srv_password');
Api::register('account.change_email',       'api_account_change_email');

// ---- admins -----------------------------------------------------------
Api::register('admins.add',               'api_admins_add',               ADMIN_OWNER | ADMIN_ADD_ADMINS);
Api::register('admins.remove',            'api_admins_remove',            ADMIN_OWNER | ADMIN_DELETE_ADMINS);
Api::register('admins.edit_perms',        'api_admins_edit_perms',        ADMIN_OWNER | ADMIN_EDIT_ADMINS);
Api::register('admins.update_perms',      'api_admins_update_perms',      0, true);
Api::register('admins.generate_password', 'api_admins_generate_password', 0, true);

// ---- auth groups (SteamCommunity ban features) ------------------------
Api::register('bans.add',                 'api_bans_add',                  ADMIN_OWNER | ADMIN_ADD_BAN);
Api::register('bans.setup_ban',           'api_bans_setup_ban',            0, true);
Api::register('bans.prepare_reban',       'api_bans_prepare_reban',        0, true);
Api::register('bans.paste',               'api_bans_paste',                ADMIN_OWNER | ADMIN_ADD_BAN);
Api::register('bans.add_comment',         'api_bans_add_comment',          0, true);
Api::register('bans.edit_comment',        'api_bans_edit_comment',         0, true);
Api::register('bans.remove_comment',      'api_bans_remove_comment',       ADMIN_OWNER);
Api::register('bans.group_ban',           'api_bans_group_ban',            ADMIN_OWNER | ADMIN_ADD_BAN);
Api::register('bans.ban_member_of_group', 'api_bans_ban_member_of_group',  ADMIN_OWNER | ADMIN_ADD_BAN);
Api::register('bans.ban_friends',         'api_bans_ban_friends',          ADMIN_OWNER | ADMIN_ADD_BAN);
Api::register('bans.get_groups',          'api_bans_get_groups',           ADMIN_OWNER | ADMIN_ADD_BAN);
Api::register('bans.kick_player',         'api_bans_kick_player',          ADMIN_OWNER | ADMIN_ADD_BAN);
Api::register('bans.send_message',        'api_bans_send_message',         0, true);
Api::register('bans.view_community',      'api_bans_view_community',       0, true);

// ---- blockit (single-page admin.blockit.php iframe) -------------------
Api::register('blockit.load_servers', 'api_blockit_load_servers', ADMIN_OWNER | ADMIN_ADD_BAN);
Api::register('blockit.block_player', 'api_blockit_block_player', ADMIN_OWNER | ADMIN_ADD_BAN);

// ---- comms (block/mute/gag) ------------------------------------------
Api::register('comms.add',                    'api_comms_add',                    ADMIN_OWNER | ADMIN_ADD_BAN);
Api::register('comms.prepare_reblock',        'api_comms_prepare_reblock',        0, true);
Api::register('comms.paste',                  'api_comms_paste',                  ADMIN_OWNER | ADMIN_ADD_BAN);
Api::register('comms.prepare_block_from_ban', 'api_comms_prepare_block_from_ban', 0, true);

// ---- groups -----------------------------------------------------------
Api::register('groups.add',                   'api_groups_add',                   ADMIN_OWNER | ADMIN_ADD_GROUP);
Api::register('groups.remove',                'api_groups_remove',                ADMIN_OWNER | ADMIN_DELETE_GROUPS);
Api::register('groups.edit',                  'api_groups_edit',                  ADMIN_OWNER | ADMIN_EDIT_GROUPS);
Api::register('groups.update_perms',          'api_groups_update_perms',          0, true);
Api::register('groups.add_server_group_name', 'api_groups_add_server_group_name', ADMIN_OWNER | ADMIN_EDIT_GROUPS);

// ---- kickit (single-page admin.kickit.php iframe) --------------------
Api::register('kickit.load_servers', 'api_kickit_load_servers', ADMIN_OWNER | ADMIN_ADD_BAN);
Api::register('kickit.kick_player',  'api_kickit_kick_player',  ADMIN_OWNER | ADMIN_ADD_BAN);

// ---- mods -------------------------------------------------------------
Api::register('mods.add',    'api_mods_add',    ADMIN_OWNER | ADMIN_ADD_MODS);
Api::register('mods.remove', 'api_mods_remove', ADMIN_OWNER | ADMIN_DELETE_MODS);

// ---- protests ---------------------------------------------------------
Api::register('protests.remove', 'api_protests_remove', ADMIN_OWNER | ADMIN_BAN_PROTESTS);

// ---- servers ----------------------------------------------------------
Api::register('servers.add',                 'api_servers_add',                 ADMIN_OWNER | ADMIN_ADD_SERVER);
Api::register('servers.remove',              'api_servers_remove',              ADMIN_OWNER | ADMIN_DELETE_SERVERS);
Api::register('servers.setup_edit',          'api_servers_setup_edit',          ADMIN_OWNER | ADMIN_EDIT_SERVERS);
Api::register('servers.refresh',             'api_servers_refresh',             0, false, true);
Api::register('servers.host_players',        'api_servers_host_players',        0, false, true);
Api::register('servers.host_property',       'api_servers_host_property',       0, false, true);
Api::register('servers.host_players_list',   'api_servers_host_players_list',   0, false, true);
Api::register('servers.players',             'api_servers_players',             0, false, true);
Api::register('servers.send_rcon',           'api_servers_send_rcon',           SM_RCON . SM_ROOT);

// ---- submissions ------------------------------------------------------
Api::register('submissions.remove', 'api_submissions_remove', ADMIN_OWNER | ADMIN_BAN_SUBMISSIONS);

// ---- system -----------------------------------------------------------
Api::register('system.rehash_admins',         'api_system_rehash_admins',         ADMIN_OWNER | ADMIN_EDIT_ADMINS | ADMIN_EDIT_GROUPS | ADMIN_ADD_ADMINS);
Api::register('system.send_mail',             'api_system_send_mail',             ADMIN_OWNER | ADMIN_BAN_PROTESTS | ADMIN_BAN_SUBMISSIONS);
Api::register('system.check_version',         'api_system_check_version',         0, false, true);
Api::register('system.sel_theme',             'api_system_sel_theme',             ADMIN_OWNER | ADMIN_WEB_SETTINGS);
Api::register('system.apply_theme',           'api_system_apply_theme',           ADMIN_OWNER | ADMIN_WEB_SETTINGS);
Api::register('system.clear_cache',           'api_system_clear_cache',           ADMIN_OWNER | ADMIN_WEB_SETTINGS);
