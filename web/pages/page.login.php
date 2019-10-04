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

global $userbank, $theme;
if (isset($_GET['m'])) {
    $lostpassword_url = Host::complete() . '/index.php?p=lostpassword';
    switch ($_GET['m']) {
        case 'no_access':
            echo <<<HTML
				<script>
					ShowBox(
						'Error - No Access',
						'You dont have permission to access this page.<br />' +
						'Please login with an account that has access.',
						'red', '', false
					);
				</script>
HTML;
            break;

        case 'empty_pwd':
            echo <<<HTML
				<script>
					ShowBox(
						'Information',
						'You are unable to login because your account have an empty password set.<br />' +
						'Please <a href="$lostpassword_url">restore your password</a> or ask an admin to do that for you.<br />' +
						'Do note that you are required to have a non empty password set event if you sign in through Steam.',
						'blue', '', true
					);
				</script>
HTML;
            break;

        case 'failed':
            echo <<<HTML
    			<script>
    				ShowBox(
                        'Error',
    					'The username or password you supplied was incorrect.<br \>'+
                        'If you have forgotten your password, use the <a href="$lostpassword_url">Lost Password</a> link.',
    					'red', '', false
    				);
    			</script>
HTML;
            break;

        case 'steam_failed':
            echo <<<HTML
                <script>
                    ShowBox(
                        'Error',
                        'Steam login was sucessful, but your SteamID isn\'t associated with any account.',
                        'red', '', false
                    );
                </script>
HTML;
            break;
    }


}

$theme->assign('steamlogin_show', Config::getBool('config.enablesteamlogin'));
$theme->assign('redir', "DoLogin('');");
$theme->left_delimiter  = "-{";
$theme->right_delimiter = "}-";
$theme->display('page_login.tpl');
$theme->left_delimiter  = "{";
$theme->right_delimiter = "}";
