<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2019 by SourceBans++ Dev Team

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

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}
global $userbank, $theme, $dash_intro_text;

new AdminTabs([
    ['name' => 'Main Settings', 'permission' => ADMIN_OWNER|ADMIN_WEB_SETTINGS],
    ['name' => 'Themes', 'permission' => ALL_WEB],
    ['name' => 'System Log', 'permission' => ALL_WEB],
    ['name' => 'Features', 'permission' => ADMIN_OWNER|ADMIN_WEB_SETTINGS]
], $userbank, $theme);

$page = 1;
if (isset($_GET['page']) && $_GET['page'] > 0) {
    $page = intval($_GET['page']);
}

if (isset($_GET['log_clear']) && $_GET['log_clear'] == "true") {
    if ($userbank->HasAccess(ADMIN_OWNER)) {
        $result = $GLOBALS['db']->Execute("TRUNCATE TABLE `" . DB_PREFIX . "_log`");
    } else {
        Log::add("w", "Hacking Attempt", $userbank->GetProperty('user')." tried to clear the logs, but doesn't have access.");
    }
}

// search
$where = "";
if (isset($_GET['advSearch'])) {
    // Escape the value, but strip the leading and trailing quote
    $value = substr($GLOBALS['db']->qstr($_GET['advSearch']), 1, -1);
    $type  = $_GET['advType'];
    switch ($type) {
        case "admin":
            $where = " WHERE l.aid = '" . $value . "'";
            break;
        case "message":
            $where = " WHERE l.message LIKE '%" . $value . "%' OR l.title LIKE '%" . $value . "%'";
            break;
        case "date":
            $date  = explode(",", $value);
            $date[0] = (is_numeric($date[0])) ? $date[0] : date('d');
            $date[1] = (is_numeric($date[1])) ? $date[1] : date('m');
            $date[2] = (is_numeric($date[2])) ? $date[2] : date('Y');
            $time  = mktime($date[3], $date[4], 0, $date[1], $date[0], $date[2]);
            $time2 = mktime($date[5], $date[6], 59, $date[1], $date[0], $date[2]);
            $where = " WHERE l.created > '$time' AND l.created < '$time2'";
            break;
        case "type":
            $where = " WHERE l.type = '" . $value . "'";
            break;
        default:
            $_GET['advType']   = "";
            $_GET['advSearch'] = "";
            $where             = "";
            break;
    }
    $searchlink = "&advSearch=" . $_GET['advSearch'] . "&advType=" . $_GET['advType'];
} else {
    $searchlink = "";
}
$list_start = ($page - 1) * SB_BANS_PER_PAGE;
$list_end   = $list_start + SB_BANS_PER_PAGE;

$log_count = Log::getCount($where);
$log       = Log::getAll($list_start, SB_BANS_PER_PAGE, $where);
if (($page > 1)) {
    $prev = CreateLinkR('<i class="fas fa-arrow-left fa-lg"></i> prev', "index.php?p=admin&c=settings" . $searchlink . "&page=" . ($page - 1) . "#^2");
} else {
    $prev = "";
}
if ($list_end < $log_count) {
    $next = CreateLinkR('next <i class="fas fa-arrow-right fa-lg"></i>', "index.php?p=admin&c=settings" . $searchlink . "&page=" . ($page + 1) . "#^2");
} else {
    $next = "";
}
$pages = (round($log_count / SB_BANS_PER_PAGE) == 0) ? 1 : round($log_count / SB_BANS_PER_PAGE);
if ($pages > 1) {
    $page_numbers = 'Page ' . $page . ' of ' . $pages . " - " . $prev . " | " . $next;
} else {
    $page_numbers = 'Page ' . $page . ' of ' . $pages;
}
$pages = ceil($log_count / SB_BANS_PER_PAGE);
if ($pages > 1) {
    if (!isset($_GET['advSearch']) || !isset($_GET['advType'])) {
        $_GET['advSearch'] = "";
        $_GET['advType']   = "";
    }
    $page_numbers .= '&nbsp;<select onchange="changePage(this,\'L\',\'' . $_GET['advSearch'] . '\',\'' . $_GET['advType'] . '\');">';
    for ($i = 1; $i <= $pages; $i++) {
        if (isset($_GET["page"]) && $i == $_GET["page"]) {
            $page_numbers .= '<option value="' . $i . '" selected="selected">' . $i . '</option>';
            continue;
        }
        $page_numbers .= '<option value="' . $i . '">' . $i . '</option>';
    }
    $page_numbers .= '</select>';
}
$log_list = array();
foreach ($log as $l) {
    $log_item = array();
    if ($l['type'] == "m") {
        $log_item['type_img'] = "<img src='themes/" . SB_THEME . "/images/admin/help.png' alt='Info'>";
    } elseif ($l['type'] == "w") {
        $log_item['type_img'] = "<img src='themes/" . SB_THEME . "/images/admin/warning.png' alt='Warning'>";
    } elseif ($l['type'] == "e") {
        $log_item['type_img'] = "<img src='themes/" . SB_THEME . "/images/admin/error.png' alt='Warning'>";
    }
    $log_item['user']     = !empty($l['user']) ? $l['user'] : 'Guest';
    $log_item['date_str'] = Config::time($l['created']);
    $log_item             = array_merge($l, $log_item);
    $log_item['message']  = str_replace("\n", "<br />", htmlentities(str_replace(["<br />", "<br>", "<br/>"], "\n", $log_item['message'])));
    array_push($log_list, $log_item);
}
// Theme stuff
$dh = opendir(SB_THEMES);
while (false !== ($filename = readdir($dh))) {
    $themes[] = $filename;
}
//$themes = scandir(SB_THEMES);
$valid_themes = array();
foreach ($themes as $thm) {
    if (@file_exists(SB_THEMES . $thm . "/theme.conf.php")) {
        $file = file_get_contents(SB_THEMES . $thm . "/theme.conf.php");
        if ($namesearch = preg_match_all('/define\(\'theme_name\',[ ]*\"(.+)\"\);/', $file, $thmname, PREG_PATTERN_ORDER)) {
            $thme['name'] = $thmname[1][0];
        } else {
            $thme['name'] = $thm;
        }
        $thme['dir'] = $thm;
        array_push($valid_themes, $thme);
    }
}
require(SB_THEMES . SB_THEME . "/theme.conf.php");
?>
<div id="admin-page-content">
<?php
if (!$userbank->HasAccess(ADMIN_OWNER | ADMIN_WEB_SETTINGS)) {
    echo '<div id="0" style="display:none;">Access Denied!</div>';
} else {
    if (isset($_POST['settingsGroup'])) {
        $errors = "";

        if ($_POST['settingsGroup'] == "mainsettings") {
            if (!is_numeric($_POST['config_password_minlength'])) {
                $errors .= "Min password length must be a number<br />";
            }
            if (!is_numeric($_POST['banlist_bansperpage'])) {
                $errors .= "Bans per page must be a number";
            }
            if (empty($errors)) {
                if (isset($_POST['enable_submit']) && $_POST['enable_submit'] == "on") {
                    $submit = 1;
                } else {
                    $submit = 0;
                }
                if (isset($_POST['enable_protest']) && $_POST['enable_protest'] == "on") {
                    $protest = 1;
                } else {
                    $protest = 0;
                }
                if (isset($_POST['enable_commslist']) && $_POST['enable_commslist'] == "on") {
                    $commslist = 1;
                } else {
                    $commslist = 0;
                }

                $lognopopup = (isset($_POST['dash_nopopup']) && $_POST['dash_nopopup'] == "on" ? 1 : 0);

                $debugmode = (isset($_POST['config_debug']) && $_POST['config_debug'] == "on" ? 1 : 0);

                $hideadmname = (isset($_POST['banlist_hideadmname']) && $_POST['banlist_hideadmname'] == "on" ? 1 : 0);

                $hideplayerips = (isset($_POST['banlist_hideplayerips']) && $_POST['banlist_hideplayerips'] == "on" ? 1 : 0);

                $nocountryfetch = (isset($_POST['banlist_nocountryfetch']) && $_POST['banlist_nocountryfetch'] == "on" ? 1 : 0);

                $onlyinvolved = (isset($_POST['protest_emailonlyinvolved']) && $_POST['protest_emailonlyinvolved'] == "on" ? 1 : 0);

                $size = sizeof($_POST['bans_customreason']);
                for ($i = 0; $i < $size; $i++) {
                    if (empty($_POST['bans_customreason'][$i])) {
                        unset($_POST['bans_customreason'][$i]);
                    } else {
                        $_POST['bans_customreason'][$i] = htmlspecialchars($_POST['bans_customreason'][$i]);
                    }
                }
                if (sizeof($_POST['bans_customreason']) != 0) {
                    $cureason = serialize($_POST['bans_customreason']);
                } else {
                    $cureason = "";
                }

                $edit = $GLOBALS['db']->Execute("REPLACE INTO " . DB_PREFIX . "_settings (`value`, `setting`) VALUES
                    (?, 'template.title'),
                    (?,'template.logo'),
                    (" . (int) $_POST['config_password_minlength'] . ", 'config.password.minlength'),
                    (" . $debugmode . ", 'config.debug'),
                    (?, 'config.dateformat'),
                    (?, 'dash.intro.title'),
                    (" . (int) $_POST['banlist_bansperpage'] . ", 'banlist.bansperpage'),
                    (" . (int) $hideadmname . ", 'banlist.hideadminname'),
                    (" . (int) $hideplayerips . ", 'banlist.hideplayerips'),
                    (" . (int) $nocountryfetch . ", 'banlist.nocountryfetch'),
                    (?, 'dash.intro.text'),
                    (" . (int) $lognopopup . ", 'dash.lognopopup'),
                    (" . (int) $protest . ", 'config.enableprotest'),
                    (" . (int) $commslist . ", 'config.enablecomms'),
                    (" . (int) $submit . ", 'config.enablesubmit'),
                    (" . (int) $onlyinvolved . ", 'protest.emailonlyinvolved'),
                    (?, 'bans.customreasons'),
                    (?, 'auth.maxlife'),
                    (?, 'auth.maxlife.remember'),
                    (?, 'auth.maxlife.steam'),
                    (" . (int) $_POST['default_page'] . ", 'config.defaultpage')", array(
                    $_POST['template_title'],
                    $_POST['template_logo'],
                    $_POST['config_dateformat'],
                    $_POST['dash_intro_title'],
                    $dash_intro_text,
                    $cureason,
                    $_POST['auth_maxlife'],
                    $_POST['auth_maxlife_remember'],
                    $_POST['auth_maxlife_steam']
                ));

?>
<script>ShowBox('Settings updated', 'The changes have been successfully updated', 'green', 'index.php?p=admin&c=settings');</script>
<?php
            } else {
                print "<script>ShowBox('Error', '$errors', 'red');</script>";
            }
        }

        if ($_POST['settingsGroup'] == "features") {
            $kickit = (isset($_POST['enable_kickit']) && $_POST['enable_kickit'] == "on" ? 1 : 0);

            $exportpub = (isset($_POST['export_public']) && $_POST['export_public'] == "on" ? 1 : 0);

            $groupban = (isset($_POST['enable_groupbanning']) && $_POST['enable_groupbanning'] == "on" ? 1 : 0);

            $friendsban = (isset($_POST['enable_friendsbanning']) && $_POST['enable_friendsbanning'] == "on" ? 1 : 0);

            $adminrehash = (isset($_POST['enable_adminrehashing']) && $_POST['enable_adminrehashing'] == "on" ? 1 : 0);

            $steamloginopt = (isset($_POST['enable_steamlogin']) && $_POST['enable_steamlogin'] == "on" ? 1 : 0);

            $publiccomments = (isset($_POST['enable_publiccomments']) && $_POST['enable_publiccomments'] == "on" ? 1 : 0);

            $edit = $GLOBALS['db']->Execute("REPLACE INTO " . DB_PREFIX . "_settings (`value`, `setting`) VALUES
											(" . (int) $exportpub . ", 'config.exportpublic'),
											(" . (int) $kickit . ", 'config.enablekickit'),
											(" . (int) $groupban . ", 'config.enablegroupbanning'),
											(" . (int) $friendsban . ", 'config.enablefriendsbanning'),
											(" . (int) $adminrehash . ", 'config.enableadminrehashing'),
											(" . (int) $publiccomments . ", 'config.enablepubliccomments'),
											(" . (int) $steamloginopt . ", 'config.enablesteamlogin')");


?>
<script>ShowBox('Settings updated', 'The changes have been successfully updated', 'green', 'index.php?p=admin&c=settings');</script>
<?php
        }
    }

    #########[Settings Page]###############
    echo '<div class="tabcontent" id="Main Settings">';
    $theme->assign('config_title', Config::get('template.title'));
    $theme->assign('config_logo', Config::get('template.logo'));
    $theme->assign('config_min_password', MIN_PASS_LENGTH);
    $theme->assign('config_dateformat', Config::get('config.dateformat'));
    $theme->assign('config_dash_title', Config::get('dash.intro.title'));
    $theme->assign('config_dash_text', Config::get('dash.intro.text'));
    $theme->assign('auth_maxlife', Config::get('auth.maxlife'));
    $theme->assign('auth_maxlife_remember', Config::get('auth.maxlife.remember'));
    $theme->assign('auth_maxlife_steam', Config::get('auth.maxlife.steam'));
    $theme->assign('config_bans_per_page', SB_BANS_PER_PAGE);

    $theme->assign('bans_customreason', (Config::getBool('bans.customreasons')) ? unserialize(Config::get('bans.customreasons')) : []);

    $theme->display('page_admin_settings_settings.tpl');
    echo '</div>';
    #########/[Settings Page]###############

    #########[Features Page]###############
    echo '<div class="tabcontent" id="Features">';
    $theme->assign('steamapi', (defined('STEAMAPIKEY') && STEAMAPIKEY != '') ? true : false);
    $theme->display('page_admin_settings_features.tpl');
    echo '</div>';
    #########/[Features Page]###############

    #########[Themes Page]###############
    echo '<div class="tabcontent" id="Themes">';
    $theme->assign('theme_list', $valid_themes);

    $theme->assign('theme_name', strip_tags(theme_name));
    $theme->assign('theme_author', strip_tags(theme_author));
    $theme->assign('theme_version', strip_tags(theme_version));
    $theme->assign('theme_link', strip_tags(theme_link));
    $theme->assign('theme_screenshot', '<img width="250px" height="170px" src="themes/' . SB_THEME . '/' . strip_tags(theme_screenshot) . '">');

    $theme->display('page_admin_settings_themes.tpl');
    echo '</div>';
    #########/[Settings Page]###############

    #########[Logs Page]###############
    echo '<div class="tabcontent" id="System Log">';
    if ($userbank->HasAccess(ADMIN_OWNER)) {
        $theme->assign('clear_logs', "( <a href='javascript:ClearLogs();'>Clear Log</a> )");
    }
    $theme->assign('page_numbers', $page_numbers);
    $theme->assign('log_items', $log_list);

    $theme->display('page_admin_settings_logs.tpl');
    echo '</div>';
    #########/[Logs Page]###############
}
?>
<script>
$('config_debug').checked = <?=(int)Config::getBool('config.debug');?>;
$('enable_submit').checked = <?=(int)Config::getBool('config.enablesubmit');?>;
$('enable_protest').checked = <?=(int)Config::getBool('config.enableprotest');?>;
$('enable_commslist').checked = <?=(int)Config::getBool('config.enablecomms');?>;
$('enable_kickit').checked = <?=(int)Config::getBool('config.enablekickit');?>;
$('export_public').checked = <?=(int)Config::getBool('config.exportpublic');?>;
$('dash_nopopup').checked = <?=(int)Config::getBool('dash.lognopopup');?>;
$('default_page').value = <?=(int)Config::getBool('config.defaultpage');?>;
$('protest_emailonlyinvolved').checked = <?=(int)Config::getBool('protest.emailonlyinvolved');?>;
$('banlist_hideadmname').checked = <?=(int)Config::getBool('banlist.hideadminname');?>;
$('banlist_nocountryfetch').checked = <?=(int)Config::getBool('banlist.nocountryfetch');?>;
$('banlist_hideplayerips').checked = <?=(int)Config::getBool('banlist.hideplayerips');?>;
$('enable_groupbanning').checked = <?=(int)Config::getBool('config.enablegroupbanning');?>;
$('enable_friendsbanning').checked = <?=(int)Config::getBool('config.enablefriendsbanning');?>;
$('enable_adminrehashing').checked = <?=(int)Config::getBool('config.enableadminrehashing');?>;
$('enable_steamlogin').checked = <?=(int)Config::getBool('config.enablesteamlogin');?>;
$('enable_publiccomments').checked = <?=(int)Config::getBool('config.enablepubliccomments');?>;
<?php
if (ini_get('safe_mode') == 1) {
    print "$('enable_groupbanning').disabled = true;\n";
    print "$('enable_friendsbanning').disabled = true;\n";
    print "$('enable_friendsbanning.msg').setHTML('You can\'t use these features. You need to set PHP safe mode off.');\n";
    print "$('enable_friendsbanning.msg').setStyle('display', 'block');\n";
}
?>
function MoreFields()
{
    var t = document.getElementById("custom.reasons");
    var tr = t.insertRow("-1");
    var td = tr.insertCell("-1");
    var inp = document.createElement("input");
    inp.setAttribute("type","text");
    inp.className = "submit-fields";
    inp.setAttribute("name","bans_customreason[]");
    inp.setAttribute("id","bans_customreason[]");
    td.appendChild(inp);
}
</script>
</div>
