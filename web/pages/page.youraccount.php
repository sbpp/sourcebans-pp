<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright © 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC-BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

global $userbank, $theme;

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
if ($userbank->GetAid() == -1) {
    echo "You shoudnt be here. looks like we messed up ><";
    die();
}

$GLOBALS['PDO']->query("SELECT `srv_password`, `email` FROM `:prefix_admins` WHERE `aid` = :aid");
$GLOBALS['PDO']->bind(':aid', $userbank->GetAid());
$res      = $GLOBALS['PDO']->single();
$srvpwset = !empty($res['srv_password']);

\Sbpp\View\Renderer::render($theme, new \Sbpp\View\YourAccountView(
    srvpwset:           $srvpwset,
    email:              (string) ($res['email'] ?? ''),
    user_aid:           (int) $userbank->GetAid(),
    web_permissions:    BitToString($userbank->GetProperty("extraflags")),
    server_permissions: SmFlagsToSb($userbank->GetProperty("srv_flags")),
    min_pass_len:       (int) MIN_PASS_LENGTH,
));
