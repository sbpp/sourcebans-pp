<?php
/*************************************************************************
This file is part of SourceBans++

Copyright � 2014-2016 SourceBans++ Dev Team <https://github.com/sbpp>

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
Copyright � 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

global $theme, $userbank;

if (!isset($_GET['id'])) {
    echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	No submission or protest id specified. Please only follow links
</div>';
    PageDie();
}

if (!isset($_GET['type']) || ($_GET['type'] != 's' && $_GET['type'] != 'p')) {
    echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	Invalid type. Please only follow links
</div>';
    PageDie();
}

// Submission
$email = "";
if ($_GET['type'] == 's') {
    $email = $GLOBALS['db']->GetOne('SELECT email FROM `' . DB_PREFIX . '_submissions` WHERE subid = ?', array(
        $_GET['id']
    ));
} elseif ($_GET['type'] == 'p') {
    // Protest
    $email = $GLOBALS['db']->GetOne('SELECT email FROM `' . DB_PREFIX . '_protests` WHERE pid = ?', array(
        $_GET['id']
    ));
}

if (empty($email)) {
    echo '<div id="msg-red" >
	<i><img src="./images/warning.png" alt="Warning" /></i>
	<b>Error</b>
	<br />
	There is no email to send to supplied.
</div>';
    PageDie();
}

$theme->assign('email_addr', htmlspecialchars($email));
$theme->assign('email_js', "CheckEmail('" . $_GET['type'] . "', " . (int) $_GET['id'] . ")");
?>

<div id="admin-page-content">
    <div id="1">
        <?php
        $theme->display('page_admin_bans_email.tpl');
        ?>
    </div>
</div>
