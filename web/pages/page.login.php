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

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

global $userbank, $theme;

if ($userbank->is_logged_in()) {
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

// `?m=…` query params drive a status banner. The legacy default theme
// echoes ShowBox() <script> dialogs (which require web/scripts/sourcebans.js,
// loaded only by the legacy chrome). The sbpp2026 chrome drops that
// bulk file (#1123 D1) and the new page_login.tpl renders the same
// status messages via window.SBPP.showToast() driven off the URL params
// directly — so the new template contributes ZERO server-rendered text
// here, and the dialog branch below stays guarded for the legacy theme
// only via the existing $lostpassword_url interpolation. The whole `if`
// goes away with the legacy template at #1123 D1.
$lostpassword_url = Host::complete() . '/index.php?p=lostpassword';
if (isset($_GET['m'])) {
    switch ($_GET['m']) {
        case 'no_access':
            echo <<<HTML
                <script>
                    if (typeof ShowBox === 'function') ShowBox(
                        'Error - No Access',
                        'You don\'t have permission to access this page.<br />' +
                        'Please login with an account that has access.',
                        'red', '', false
                    );
                </script>
HTML;
            break;

        case 'empty_pwd':
            echo <<<HTML
                <script>
                    if (typeof ShowBox === 'function') ShowBox(
                        'Information',
                        'You are unable to login because your account has an empty password set.<br />' +
                        'Please <a href="$lostpassword_url">restore your password</a> or ask an admin to do that for you.<br />' +
                        'Do note that you are required to have a non-empty password set even if you sign in through Steam.',
                        'blue', '', true
                    );
                </script>
HTML;
            break;

        case 'failed':
            echo <<<HTML
                <script>
                    if (typeof ShowBox === 'function') ShowBox(
                        'Error',
                        'The username or password you supplied was incorrect.<br />' +
                        'If you have forgotten your password, use the <a href="$lostpassword_url">Lost Password</a> link.',
                        'red', '', false
                    );
                </script>
HTML;
            break;

        case 'steam_failed':
            echo <<<HTML
                <script>
                    if (typeof ShowBox === 'function') ShowBox(
                        'Error',
                        'Steam login was successful, but your SteamID isn\'t associated with any account.',
                        'red', '', false
                    );
                </script>
HTML;
            break;

        case 'locked':
            if (isset($_GET['time'])) {
                $remainingTime = intval($_GET['time']);
                echo <<<HTML
                    <script>
                        if (typeof ShowBox === 'function') ShowBox(
                            'Account Locked',
                            'Your account is temporarily locked due to too many failed login attempts. Please try again in approximately $remainingTime minutes.',
                            'red', '', false
                        );
                    </script>
HTML;
            }
            break;
    }
}

// `$redir` is a v1.x-shaped *JavaScript expression* the legacy default
// theme inlines directly into its login button's `onclick=` and into
// the Enter/Space `keydown` handler — see web/themes/default/page_login.tpl.
// The literal `DoLogin('');` matches the prior assignment in this handler
// (commit 7c8bb9d6 baseline) and depends on `DoLogin` from
// web/scripts/sourcebans.js, which the legacy chrome loads. The new
// sbpp2026 chrome drops sourcebans.js (#1123 D1) so `DoLogin` is
// undefined there; the new page_login.tpl ignores `$redir` for actual
// login wiring and posts via `sb.api.call(Actions.AuthLogin, …)` with a
// hardcoded `redirect: ''` (= post-login destination is the dashboard).
// We still emit `$redir` so the legacy theme keeps working through the
// rollout window — the property goes away with the legacy template at
// #1123 D1.
$loginView = new \Sbpp\View\LoginView(
    normallogin_show: Config::getBool('config.enablenormallogin'),
    steamlogin_show: Config::getBool('config.enablesteamlogin'),
    redir: "DoLogin('');",
);

// Both the legacy default-theme template and the sbpp2026 redesign
// render with `-{ … }-` delimiters (see LoginView::DELIMITERS and the
// docblock on LoginView for why). Mirror the YourAccountView page
// handler's swap-around-render pattern so the chrome (which uses the
// standard `{ … }` pair) is unaffected. The swap goes away when #1123
// D1 rewrites both halves to standard delimiters.
$theme->setLeftDelimiter('-{');
$theme->setRightDelimiter('}-');
\Sbpp\View\Renderer::render($theme, $loginView);
$theme->setLeftDelimiter('{');
$theme->setRightDelimiter('}');
