<?php
/*************************************************************************
	This file is part of SourceBans++

	Copyright © 2014-2016 SourceBans++ Dev Team <https://github.com/sbpp>

	SourceBans++ is licensed under a
	Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

	You should have received a copy of the license along with this
	work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

	This program is based off work covered by the following copyright(s):
		SourceBans 1.4.11
		Copyright © 2007-2014 SourceBans Team - Part of GameConnect
		Licensed under CC BY-NC-SA 3.0
		Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/
use xPaw\SourceQuery\SourceQuery;

if (!defined("IN_SB")) {
    die("You should not be here. Only follow links!");
}

/**
 * Displays the sub-nav menu of SourceBans
 *
 * @return noreturn
 */
function BuildSubMenu()
{
    global $theme;
    $theme->left_delimiter = '<!--{';
    $theme->right_delimiter = '}-->';
    $theme->display('submenu.tpl');
    $theme->left_delimiter = '{';
    $theme->right_delimiter = '}';
}

/**
 * Displays the content header
 *
 * @return noreturn
 */
function BuildContHeader()
{
    global $theme;

    if (!isset($_GET['s']) && isset($GLOBALS['pagetitle'])) {
        $page = "<b>".$GLOBALS['pagetitle']."</b>";
    }

    $theme->assign('main_title', isset($page) ? $page:'');
    $theme->display('content_header.tpl');
}


/**
 * Adds a tab to the page
 *
 * @param string $title The title of the tab
 * @param string $utl The link of the tab
 * @param boolean $active Is the tab active?
 * @return noreturn
 */

function AddTab($title, $url, $desc, $active=false)
{
    global $tabs;
    $tab_arr = array();
    $tab_arr[0] = "Dashboard";
    $tab_arr[1] = "Servers";
    $tab_arr[2] = "&nbsp;Bans&nbsp;";
    $tab_arr[3] = "Comms";
    $tab_arr[4] = "Report a Player";
    $tab_arr[5] = "Appeal a Ban";
    $tabs = array();
    $tabs['title'] = $title;
    $tabs['url'] = $url;
    $tabs['desc'] = $desc;
    if ($_GET['p'] == "default" && $title == $tab_arr[intval($GLOBALS['config']['config.defaultpage'])]) {
        $tabs['active'] = true;
        $GLOBALS['pagetitle'] = $title;
    } else {
        if ($_GET['p'] != "default" && substr($url, 12) == $_GET['p']) {
            $tabs['active'] = true;
            $GLOBALS['pagetitle'] = $title;
        } else {
            $tabs['active'] = false;
        }
    }
    include TEMPLATES_PATH . "/tab.php";
}

/**
 * Displays the pagetabs
 *
 * @return noreturn
 */
function BuildPageTabs()
{
    global $userbank;
    AddTab("Dashboard", "index.php?p=home", "This page shows an overview of your bans and servers.");
    AddTab("Servers", "index.php?p=servers", "All of your servers and their status can be viewed here");
    AddTab("Bans", "index.php?p=banlist", "All of the bans in the database can be viewed from here.");
    if ($GLOBALS['config']['config.enablecomms'] == "1") {
        AddTab("Comms", "index.php?p=commslist", "All of the communication bans (such as chat gags and voice mutes) in the database can be viewed from here.");
    }
    if ($GLOBALS['config']['config.enablesubmit']=="1") {
        AddTab("Report a Player", "index.php?p=submit", "You can submit a demo or screenshot of a suspected cheater here. It will then be up for review by one of the admins");
    }
    if ($GLOBALS['config']['config.enableprotest']=="1") {
        AddTab("Appeal a Ban", "index.php?p=protest", "Here you can appeal your ban. And prove your case as to why you should be unbanned.");
    }
    if ($userbank->is_admin()) {
        AddTab(" Admin Panel ", "index.php?p=admin", "This is the control panel for SourceBans where you can setup new admins, add new server, etc.");
    }

    include INCLUDES_PATH . "/CTabsMenu.php";

    // BUILD THE SUB-MENU's FOR ADMIN PAGES
    $submenu = new CTabsMenu();
    if ($userbank->HasAccess(ADMIN_OWNER|ADMIN_LIST_ADMINS|ADMIN_ADD_ADMINS|ADMIN_EDIT_ADMINS|ADMIN_DELETE_ADMINS)) {
        $submenu->addMenuItem("Admins", 0, "", "index.php?p=admin&amp;c=admins", true);
    }
    if ($userbank->HasAccess(ADMIN_OWNER|ADMIN_LIST_SERVERS|ADMIN_ADD_SERVER|ADMIN_EDIT_SERVERS|ADMIN_DELETE_SERVERS)) {
        $submenu->addMenuItem("Servers", 0, "", "index.php?p=admin&amp;c=servers", true);
    }
    if ($userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_GROUP_BANS|ADMIN_EDIT_ALL_BANS|ADMIN_BAN_PROTESTS|ADMIN_BAN_SUBMISSIONS)) {
        $submenu->addMenuItem("Bans", 0, "", "index.php?p=admin&amp;c=bans", true);
    }
    if ($userbank->HasAccess(ADMIN_OWNER|ADMIN_ADD_BAN|ADMIN_EDIT_OWN_BANS|ADMIN_EDIT_ALL_BANS)) {
        $submenu->addMenuItem("Comms", 0, "", "index.php?p=admin&amp;c=comms", true);
    }
    if ($userbank->HasAccess(ADMIN_OWNER|ADMIN_LIST_GROUPS|ADMIN_ADD_GROUP|ADMIN_EDIT_GROUPS|ADMIN_DELETE_GROUPS)) {
        $submenu->addMenuItem("Groups", 0, "", "index.php?p=admin&amp;c=groups", true);
    }
    if ($userbank->HasAccess(ADMIN_OWNER|ADMIN_WEB_SETTINGS)) {
        $submenu->addMenuItem("Settings", 0, "", "index.php?p=admin&amp;c=settings", true);
    }
    if ($userbank->HasAccess(ADMIN_OWNER|ADMIN_LIST_MODS|ADMIN_ADD_MODS|ADMIN_EDIT_MODS|ADMIN_DELETE_MODS)) {
        $submenu->addMenuItem("Mods", 0, "", "?p=admin&amp;c=mods", true);
    }
    SubMenu($submenu->getMenuArray());
}

/**
 * Rewrites the breadcrumb html
 *
 * @return noreturn
 */
function BuildBreadcrumbs()
{
    $base = isset($GLOBALS['pagetitle']) ? $GLOBALS['pagetitle'] : '';
    if (isset($_GET['c'])) {
        switch ($_GET['c']) {
            case "admins":
                $cat = "Admin Management";
                break;
            case "servers":
                $cat = "Server Management";
                break;
            case "bans":
                $cat = "Ban Management";
                break;
            case "comms":
                $cat = "Communication Blocks Management";
                break;
            case "groups":
                $cat = "Group Management";
                break;
            case "settings":
                $cat = "SourceBans Settings";
                break;
            case "mods":
                $cat = "Mod Management";
                break;
            default:
                unset($_GET['c']);
        }
    }

    if (!isset($_GET['c'])) {
        if (!empty($base)) {
            $bread = "<b>" . $base . "</b>";
        } else {
            unset ($bread);
        }
    } else {
        if (!empty($cat)) {
            $bread = "<a href='index.php?p=". $_GET['p'] . "'>" . $base . "</a>  &raquo; <b>" . $cat . "</b>";
        } else {
            $bread = "<a href='index.php?p=". $_GET['p'] . "'>" . $base . "</a>";
        }
    }

    if (!empty($bread)) {
        $text = "&raquo; <a href='index.php?p=home'>Home</a> &raquo; " . $bread;
    } else {
        $text = "&raquo; <a href='index.php?p=home'>Home</a>";
    }
    echo '<script type="text/javascript">$("breadcrumb").setHTML("' . $text . '");</script>';
}

/**
 * Creates an anchor tag, and adds tooltip code if needed
 *
 * @param string $title The title of the tooltip/text to link
 * @param string $url The link
 * @param string $tooltip The tooltip message
 * @param string $target The new links target
 * @return URL
 */
function CreateLinkR($title, $url, $tooltip="", $target="_self", $wide=false, $onclick="")
{
    if ($wide) {
        $class = "perm";
    } else {
        $class = "tip";
    }
    if (strlen($tooltip) == 0) {
        return '<a href="' . $url . '" onclick="' . $onclick . '" target="' . $target . '">' . $title .' </a>';
    } else {
        return '<a href="' . $url . '" class="' . $class .'" title="' .  $title . ' :: ' .  $tooltip . '" target="' . $target . '">' . $title .' </a>';
    }
}

function HelpIcon($title, $text)
{
    return '<img border="0" align="absbottom" src="themes/' . SB_THEME .'/images/admin/help.png" class="tip" title="' .  $title . ' :: ' .  $text . '">&nbsp;&nbsp;';
}

/**
 * Allows the title of the page to change wherever the code is being executed from
 *
 * @param string $title The new title
 * @return noreturn
 */
function RewritePageTitle($title)
{
    $GLOBALS['TitleRewrite'] = $title;
}

/**
 * Build sub-menu
 *
 * @param array $el The array of elements for the menu
 * @return noreturn
 */
function SubMenu($el)
{
    $output = "";
    $first = true;
    foreach ($el as $e) {
        preg_match('/.*?&c=(.*)/', html_entity_decode($e['url']), $matches);
        if (!empty($matches[1])) {
            $c = $matches[1];
        }

        $output .= "<a class=\"nav_link".($first?" first":"").(isset($_GET['c'])&&$_GET['c']==$c?" active":"")."\" href=\"" . $e['url'] . "\">" . $e['title']. "</a>";
        $first = false;
    }
    $GLOBALS['NavRewrite'] = $output;
}

/**
 * Converts a flag bitmask into a string
 *
 * @param integer $mask The mask to convert
 * @return string
 */
function BitToString($mask, $masktype=0, $head=true)
{
    $string = "";
    if ($head) {
        $string .= "<span style='font-size:10px;color:#1b75d1;'>Web Permissions</span><br>";
    }
    if ($mask == 0) {
        $string .= "<i>None</i>";
        return $string;
    }
    if (($mask & ADMIN_LIST_ADMINS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; View Admins<br />";
    }
    if (($mask & ADMIN_ADD_ADMINS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Add Admins<br />";
    }
    if (($mask & ADMIN_EDIT_ADMINS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Edit Admins<br />";
    }
    if (($mask & ADMIN_DELETE_ADMINS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Delete Admins<br />";
    }

    if (($mask & ADMIN_LIST_SERVERS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; View Servers<br />";
    }
    if (($mask & ADMIN_ADD_SERVER) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Add Servers<br />";
    }
    if (($mask & ADMIN_EDIT_SERVERS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Edit Servers<br />";
    }
    if (($mask & ADMIN_DELETE_SERVERS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Delete Servers<br />";
    }

    if (($mask & ADMIN_ADD_BAN) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Add Bans<br />";
    }
    if (($mask & ADMIN_EDIT_OWN_BANS) !=0 && ($mask & ADMIN_EDIT_ALL_BANS) ==0) {
        $string .="&bull; Edit Own Bans<br />";
    }
    if (($mask & ADMIN_EDIT_GROUP_BANS) !=0 && ($mask & ADMIN_EDIT_ALL_BANS) ==0) {
        $string .= "&bull; Edit Group Bans<br />";
    }
    if (($mask & ADMIN_EDIT_ALL_BANS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Edit All Bans<br />";
    }
    if (($mask & ADMIN_BAN_PROTESTS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Ban Appeals<br />";
    }
    if (($mask & ADMIN_BAN_SUBMISSIONS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Ban Reports<br />";
    }

    if (($mask & ADMIN_UNBAN_OWN_BANS) !=0 && ($mask & ADMIN_UNBAN) ==0) {
        $string .= "&bull; Unban Own Bans<br />";
    }
    if (($mask & ADMIN_UNBAN_GROUP_BANS) !=0 && ($mask & ADMIN_UNBAN) ==0) {
        $string .= "&bull; Unban Group Bans<br />";
    }
    if (($mask & ADMIN_UNBAN) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Unban All Bans<br />";
    }
    if (($mask & ADMIN_DELETE_BAN) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Delete All Bans<br />";
    }
    if (($mask & ADMIN_BAN_IMPORT) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Import Bans<br />";
    }

    if (($mask & ADMIN_LIST_GROUPS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; View Groups<br />";
    }
    if (($mask & ADMIN_ADD_GROUP) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Add Groups<br />";
    }
    if (($mask & ADMIN_EDIT_GROUPS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Edit Groups<br />";
    }
    if (($mask & ADMIN_DELETE_GROUPS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Delete Groups<br />";
    }

    if (($mask & ADMIN_NOTIFY_SUB) !=0 || ($mask & ADMIN_NOTIFY_SUB) !=0) {
        $string .= "&bull; Ban Report Email Notifications<br />";
    }
    if (($mask & ADMIN_NOTIFY_PROTEST) !=0 || ($mask & ADMIN_NOTIFY_PROTEST) !=0) {
        $string .= "&bull; Ban Appeal Email Notifications<br />";
    }

    if (($mask & ADMIN_WEB_SETTINGS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Web Settings<br />";
    }

    if (($mask & ADMIN_LIST_MODS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; View Mods<br />";
    }
    if (($mask & ADMIN_ADD_MODS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Add Mods<br />";
    }
    if (($mask & ADMIN_EDIT_MODS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Edit Mods<br />";
    }
    if (($mask & ADMIN_DELETE_MODS) !=0 || ($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Delete Mods<br />";
    }

    if (($mask & ADMIN_OWNER) !=0) {
        $string .= "&bull; Owner<br />";
    }

    return $string;
}


function SmFlagsToSb($flagstring, $head=true)
{
    $string = "";
    if ($head) {
        $string .= "<span style='font-size:10px;color:#1b75d1;'>Server Permissions</span><br>";
    }
    if (empty($flagstring)) {
        $string .= "<i>None</i>";
        return $string;
    }
    if ((strstr($flagstring, "a") || strstr($flagstring, "z"))) {
        $string .= "&bull; Reserved Slot<br />";
    }
    if ((strstr($flagstring, "b") || strstr($flagstring, "z"))) {
        $string .= "&bull; Generic Admin<br />";
    }
    if ((strstr($flagstring, "c") || strstr($flagstring, "z"))) {
        $string .= "&bull; Kick<br />";
    }
    if ((strstr($flagstring, "d") || strstr($flagstring, "z"))) {
        $string .= "&bull; Ban<br />";
    }
    if ((strstr($flagstring, "e") || strstr($flagstring, "z"))) {
        $string .= "&bull; Unban<br />";
    }
    if ((strstr($flagstring, "f") || strstr($flagstring, "z"))) {
        $string .= "&bull; Slay<br />";
    }
    if ((strstr($flagstring, "g") || strstr($flagstring, "z"))) {
        $string .= "&bull; Map Change<br />";
    }
    if ((strstr($flagstring, "h") || strstr($flagstring, "z"))) {
        $string .= "&bull; Change CVars<br />";
    }
    if ((strstr($flagstring, "i") || strstr($flagstring, "z"))) {
        $string .= "&bull; Run Configs<br />";
    }
    if ((strstr($flagstring, "j") || strstr($flagstring, "z"))) {
        $string .= "&bull; Admin Chat<br />";
    }
    if ((strstr($flagstring, "k") || strstr($flagstring, "z"))) {
        $string .="&bull; Start Votes<br />";
    }
    if ((strstr($flagstring, "l") || strstr($flagstring, "z"))) {
        $string .="&bull; Password Server<br />";
    }
    if ((strstr($flagstring, "m") || strstr($flagstring, "z"))) {
        $string .="&bull; RCON<br />";
    }
    if ((strstr($flagstring, "n") || strstr($flagstring, "z"))) {
        $string .="&bull; Enable Cheats<br />";
    }
    if ((strstr($flagstring, "z"))) {
        $string .="&bull; Full Admin<br />";
    }

    if ((strstr($flagstring, "o") || strstr($flagstring, "z"))) {
        $string .="&bull; Custom Flag 1<br />";
    }
    if ((strstr($flagstring, "p") || strstr($flagstring, "z"))) {
        $string .="&bull; Custom Flag 2<br />";
    }
    if ((strstr($flagstring, "q") || strstr($flagstring, "z"))) {
        $string .="&bull; Custom Flag 3<br />";
    }
    if ((strstr($flagstring, "r") || strstr($flagstring, "z"))) {
        $string .="&bull; Custom flag 4<br />";
    }
    if ((strstr($flagstring, "s") || strstr($flagstring, "z"))) {
        $string .="&bull; Custom Flag 5<br />";
    }
    if ((strstr($flagstring, "t") || strstr($flagstring, "z"))) {
        $string .="&bull; Custom Flag 6<br />";
    }

    return $string;
}

function NextSid()
{
    $sid = $GLOBALS['db']->GetRow("SELECT MAX(sid) AS next_sid FROM `" . DB_PREFIX . "_servers`");
    return ($sid['next_sid']+1);
}
function NextAid()
{
    $aid = $GLOBALS['db']->GetRow("SELECT MAX(aid) AS next_aid FROM `" . DB_PREFIX . "_admins`");
    return ($aid['next_aid']+1);
}

function trunc($text, $len, $byword=true)
{
    if (strlen($text) <= $len) {
        return $text;
    }
    $text = $text." ";
    $text = substr($text, 0, $len);
    if ($byword) {
        $text = substr($text, 0, strrpos($text, ' '));
    }
    $text = $text."...";
    return $text;
}

function CreateQuote()
{
    global $userbank;
    $quote = array(
        array("Buy a new PC!", "Viper"),
        array("I'm not lazy! I just utilize technical resources!", "Brizad"),
        array("I need to mow the lawn", "sslice"),
        array("Like A Glove!", "Viper"),
        array("Your a Noob and You Know It!", "Viper"),
        array("Get your ass ingame", "Viper"),
        array("Mother F***ing Peices of Sh**", "Viper"),
        array("Shut up Bam", "[Everyone]"),
        array("Hi OllyBunch", "Viper"),
        array("Procrastination is like masturbation. Sure it feels good, but in the end you're only F***ing yourself!", "[Unknown]"),
        array("Rave's momma so fat she sat on the beach and Greenpeace threw her in", "SteamFriend"),
        array("Im just getting a beer", "Faith"),
        array("To be honest " . ($userbank->is_logged_in()?$userbank->getProperty('user'):'...') . ", I DONT CARE!", "Viper"),
        array("Yams", "teame06"),
        array("built in cheat 1.6 - my friend told me theres a cheat where u can buy a car door and run around and it makes u invincible....", "gdogg"),
        array("i just join conversation when i see a chance to tell people they might be wrong, then i quickly leave, LIKE A BAT", "BAILOPAN"),
        array("Lets just blame it on FlyingMongoose", "[Everyone]"),
        array("Don't step on that boom... mine...", "Recon"),
        array("Looks through sniper scope... Sit ;)", "Recon"),
        array("That plugin looks like something you found in a junk yard.", "Recon"),
        array("That's exactly what I asked you not to do.", "Recon"),
        array("Why are you wasting your time looking at this?", "Recon"),
        array("You must have better things to do with your time", "Recon"),
        array("I pity da fool", "Mr. T"),
        array("you grew a 3rd head?", "Tsunami"),
        array("I dont think you want to know...", "devicenull"),
        array("Sheep sex isn't baaaaaa...aad", "Brizad"),
        array("Oh wow, he's got skillz spelled with an 's'", "Brizad"),
        array("I'll get to it this weekend... I promise", "Brizad"),
        array("People do crazy things all the time... Like eat a Arby's", "Marge Simpson"),
        array("I wish my lawn was emo, so it would cut itself", "SirTiger"),
        array("Oh no! I've overflowed my balls!", "Olly"),
        array("Pump me full of your precious information, Senpai!", "ISSUE_TEMPLATE.md")
    );
    $num = rand(0, sizeof($quote)-1);
    return '"' . $quote[$num][0] . '" - <i>' . $quote[$num][1] . '</i>';
}

function CheckAdminAccess($mask)
{
    global $userbank;
    if (!$userbank->HasAccess($mask)) {
        header("Location: index.php?p=login&m=no_access");
        die();
    }
}

function SecondsToString($sec, $textual=true)
{
    if ($textual) {
        $div = array( 2592000, 604800, 86400, 3600, 60, 1 );
        $desc = array('mo','wk','d','hr','min','sec');
        $ret = null;
        foreach ($div as $index => $value) {
            $quotent = floor($sec / $value); //greatest whole integer
            if ($quotent > 0) {
                $ret .= "$quotent {$desc[$index]}, ";
                $sec %= $value;
            }
        }
        return substr($ret, 0, -2);
    } else {
        $hours = floor($sec / 3600);
        $sec -= $hours * 3600;
        $mins = floor($sec / 60);
        $secs = $sec % 60;
        return "$hours:$mins:$secs";
    }
}

function FetchIp($ip)
{
    $ip = sprintf('%u', ip2long($ip));
    if (!isset($_SESSION['CountryFetchHndl']) || !is_resource($_SESSION['CountryFetchHndl'])) {
        $handle = fopen(INCLUDES_PATH.'/IpToCountry.csv', "r");
        $_SESSION['CountryFetchHndl'] = $handle;
    } else {
        $handle = $_SESSION['CountryFetchHndl'];
        rewind($handle);
    }

    if (!$handle) {
        return "zz";
    }

    while (($ipdata = fgetcsv($handle, 4096)) !== false) {
        // If line is comment or IP is out of range
        if ($ipdata[0][0] == '#' || $ip < $ipdata[0] || $ip > $ipdata[1]) {
            continue;
        }

        if (empty($ipdata[4])) {
            return "zz";
        }
        return $ipdata[4];
    }

	return "zz";
}

function PageDie()
{
    include TEMPLATES_PATH.'/footer.php';
    die();
}

function GetMapImage($map)
{
    if (@file_exists(SB_MAP_LOCATION . "/" . $map . ".jpg")) {
        return "images/maps/" . $map . ".jpg";
    }
    return "images/maps/nomap.jpg";
}

function CheckExt($filename, $ext)
{
    $filename = str_replace(chr(0), '', $filename);
    $path_info = pathinfo($filename);
    if (strtolower($path_info['extension']) == strtolower($ext)) {
        return true;
    }
    return false;
}

function PruneBans()
{
    global $userbank;

    $res = $GLOBALS['db']->Execute('UPDATE `'.DB_PREFIX.'_bans` SET `RemovedBy` = 0, `RemoveType` = \'E\', `RemovedOn` = UNIX_TIMESTAMP() WHERE `length` != 0 and `ends` < UNIX_TIMESTAMP() and `RemoveType` IS NULL');
    $prot = $GLOBALS['db']->Execute("UPDATE `".DB_PREFIX."_protests` SET archiv = '3', archivedby = ".($userbank->GetAid()<0?0:$userbank->GetAid())." WHERE archiv = '0' AND bid IN((SELECT bid FROM `".DB_PREFIX."_bans` WHERE `RemoveType` = 'E'))");
    $submission = $GLOBALS['db']->Execute('UPDATE `'.DB_PREFIX.'_submissions` SET archiv = \'3\', archivedby = '.($userbank->GetAid()<0?0:$userbank->GetAid()).' WHERE archiv = \'0\' AND (SteamId IN((SELECT authid FROM `'.DB_PREFIX.'_bans` WHERE `type` = 0 AND `RemoveType` IS NULL)) OR sip IN((SELECT ip FROM `'.DB_PREFIX.'_bans` WHERE `type` = 1 AND `RemoveType` IS NULL)))');
    return $res?true:false;
}

function PruneComms()
{
    global $userbank;
    $res = $GLOBALS['db']->Execute('UPDATE `'.DB_PREFIX.'_comms` SET `RemovedBy` = 0, `RemoveType` = \'E\', `RemovedOn` = UNIX_TIMESTAMP() WHERE `length` != 0 and `ends` < UNIX_TIMESTAMP() and `RemoveType` IS NULL');
    return $res?true:false;
}

// Function by Luman (http://snipplr.com/users/luman)
function array_qsort(&$array, $column=0, $order=SORT_ASC, $first=0, $last= -2)
{
    // $array  - the array to be sorted
    // $column - index (column) on which to sort
    //          can be a string if using an associative array
    // $order  - SORT_ASC (default) for ascending or SORT_DESC for descending
    // $first  - start index (row) for partial array sort
    // $last  - stop  index (row) for partial array sort
    // $keys  - array of key values for hash array sort

    $keys = array_keys($array);
    if ($last == -2) {
        $last = count($array) - 1;
    }
    if ($last > $first) {
        $alpha = $first;
        $omega = $last;
        $key_alpha = $keys[$alpha];
        $key_omega = $keys[$omega];
        $guess = $array[$key_alpha][$column];
        while ($omega >= $alpha) {
            if ($order == SORT_ASC) {
                while ($array[$key_alpha][$column] < $guess) {
                    $alpha++;
                    $key_alpha = $keys[$alpha];
                }

                while ($array[$key_omega][$column] > $guess) {
                    $omega--;
                    $key_omega = $keys[$omega];
                }
            } else {
                while ($array[$key_alpha][$column] > $guess) {
                    $alpha++;
                    $key_alpha = $keys[$alpha];
                }
                while ($array[$key_omega][$column] < $guess) {
                    $omega--;
                    $key_omega = $keys[$omega];
                }
            }
            if ($alpha > $omega) {
                break;
            }
            $temporary = $array[$key_alpha];
            $array[$key_alpha] = $array[$key_omega];
            $alpha++;
            $key_alpha = $keys[$alpha];
            $array[$key_omega] = $temporary;
            $omega--;
            if ($omega > 0) {
                $key_omega = $keys[$omega];
            }
        }
        array_qsort($array, $column, $order, $first, $omega);
        array_qsort($array, $column, $order, $alpha, $last);
    }
}


function getDirectorySize($path)
{
    $totalsize = 0;
    $totalcount = 0;
    $dircount = 0;
    if ($handle = opendir($path)) {
        while (false !== ($file = readdir($handle))) {
            $nextpath = $path . '/' . $file;
            if ($file != '.' && $file != '..' && !is_link($nextpath)) {
                if (is_dir($nextpath)) {
                    $dircount++;
                    $result = getDirectorySize($nextpath);
                    $totalsize += $result['size'];
                    $totalcount += $result['count'];
                    $dircount += $result['dircount'];
                } elseif (is_file($nextpath)) {
                    $totalsize += filesize($nextpath);
                    $totalcount++;
                }
            }
        }
    }
    closedir($handle);
    $total['size'] = $totalsize;
    $total['count'] = $totalcount;
    $total['dircount'] = $dircount;
    return $total;
}


function sizeFormat($size)
{
    if ($size<1024) {
        return $size." bytes";
    } elseif ($size<(1024*1024)) {
        $size=round($size/1024, 1);
        return $size." KB";
    } elseif ($size<(1024*1024*1024)) {
        $size=round($size/(1024*1024), 2);
        return $size." MB";
    } else {
        $size=round($size/(1024*1024*1024), 2);
        return $size." GB";
    }
}

//function to check for multiple steamids on one server.
// param $steamids needs to be an array of steamids.
//returns array('STEAM_ID_1' => array('name' => $name, 'steam' => $steam, 'ip' => $ip, 'time' => $time, 'ping' => $ping), 'STEAM_ID_2' => array()....)
function checkMultiplePlayers($sid, $steamids)
{
    require_once(INCLUDES_PATH.'/CServerRcon.php');
    $serv = $GLOBALS['db']->GetRow("SELECT ip, port, rcon FROM ".DB_PREFIX."_servers WHERE sid = '".$sid."';");
    if (empty($serv['rcon'])) {
        return false;
    }
    $test = @fsockopen($serv['ip'], $serv['port'], $errno, $errstr, 2);
    if (!$test) {
        return false;
    }
    $r = new CServerRcon($serv['ip'], $serv['port'], $serv['rcon']);

    if (!$r->Auth()) {
        $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_servers SET rcon = '' WHERE sid = '".(int)$sid."';");
        return false;
    }

    $ret = $r->rconCommand("status");
    $search = preg_match_all(STATUS_PARSE, $ret, $matches, PREG_PATTERN_ORDER);
    $i = 0;
    $found = array();
    foreach ($matches[3] as $match) {
        foreach ($steamids as $steam) {
            if (\SteamID\SteamID::toSteam2($match) === \SteamID\SteamID::toSteam2($steam)) {
                $steam = $matches[3][$i];
                $name = $matches[2][$i];
                $time = $matches[4][$i];
                $ping = $matches[5][$i];
                $ip = explode(":", $matches[8][$i]);
                $ip = $ip[0];
                $found[$steam] = array('name' => $name, 'steam' => $steam, 'ip' => $ip, 'time' => $time, 'ping' => $ping);
                break;
            }
        }
        $i++;
    }
    return $found;
}

function GetCommunityName($steamid)
{
    $endpoint = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=".STEAMAPIKEY.'&steamids='.\SteamID\SteamID::toSteam64($steamid);
    $data = json_decode(file_get_contents($endpoint), true);
    return (isset($data['response']['players'][0]['personaname'])) ? $data['response']['players'][0]['personaname'] : '';
}

function SendRconSilent($rcon, $sid)
{
    require_once(INCLUDES_PATH.'/CServerRcon.php');
    $serv = $GLOBALS['db']->GetRow("SELECT ip, port, rcon FROM ".DB_PREFIX."_servers WHERE sid = '".$sid."';");
    if (empty($serv['rcon'])) {
        return false;
    }
    $test = @fsockopen($serv['ip'], $serv['port'], $errno, $errstr, 2);
    if (!$test) {
        return false;
    }
    $r = new CServerRcon($serv['ip'], $serv['port'], $serv['rcon']);

    if (!$r->Auth()) {
        $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_servers SET rcon = '' WHERE sid = '".(int)$sid."';");
        return false;
    }

    $ret = $r->rconCommand($rcon);
    if ($ret) {
        return true;
    }
    return false;
}

function generate_salt($length = 5)
{
    return (substr(str_shuffle('qwertyuiopasdfghjklmnbvcxz0987612345'), 0, $length));
}
