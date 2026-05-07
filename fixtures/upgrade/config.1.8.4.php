<?php
// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2024 SourceBans++ Dev Team <https://github.com/sbpp>
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

if (!defined('IN_SB')) {
    echo 'You should not be here. Only follow links!';
    die();
}

define('DB_HOST', 'db'); // The host/ip to your SQL server
define('DB_USER', 'sourcebans'); // The username to connect with
define('DB_PASS', 'sourcebans'); // The password
define('DB_NAME', 'sourcebans'); // Database name
define('DB_PREFIX', 'sb'); // The table prefix for SourceBans
define('DB_PORT', '3306'); // The SQL port (Default: 3306)
define('DB_CHARSET', 'utf8mb4'); // The Database charset (Default: utf8)
define('STEAMAPIKEY', 'REPLACE_WITH_REAL_STEAM_API_KEY'); // Steam API Key for Shizz
define('SB_EMAIL', 'admin@example.test');
define('SB_NEW_SALT', '$5$'); //Salt for passwords
define('SB_SECRET_KEY', 'REPLACE_WITH_BASE64_47_BYTES_PLACEHOLDER_DO_NOT_USE_IN_PROD'); //Secret for JWT
