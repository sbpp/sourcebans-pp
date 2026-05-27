<?php
// SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
// Licensed under the Elastic License 2.0.
// See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

if (!defined("IN_SB")) {
    echo "You should not be here. Only follow links!";
    die();
}

global $userbank, $theme;

if ($userbank->is_logged_in()) {
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

// `?m=…` query params drive a status banner. The shipped v2.0.0
// `page_login.tpl` renders the message via `window.SBPP.showToast()`
// driven off the URL params on page load, so this handler emits no
// server-rendered text. The `<script>` blocks below echo the legacy
// pre-v2.0.0 `ShowBox()` dialogs purely as a no-op for any third-party
// theme that forked the pre-v2.0.0 default — the
// `if (typeof ShowBox === 'function')` guard keeps them inert when
// `ShowBox` is undefined, which is the case in the shipped chrome.
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
                $remainingTime = (int) $_GET['time'];
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

// `$redir` is a v1.x-shaped *JavaScript expression* preserved here for
// SmartyTemplateRule property↔reference parity (see the LoginView
// docblock). The shipped `page_login.tpl` echoes it on a dead
// `data-legacy-redir="…"` attribute and posts via
// `sb.api.call(Actions.AuthLogin, …)` with a hardcoded `redirect: ''`
// (post-login destination is the dashboard). Any third-party theme
// that forked the pre-v2.0.0 default and still calls `DoLogin(...)`
// from removed legacy bulk JS would no-op there.
// `template.logo` is the operator-configurable brand mark path. The
// `BrandLogo::resolveUrl()` helper handles the theme-relative join
// AND falls back to `images/favicon.svg` (the SourceBans++ shield
// from the favicon set) when the configured value is empty, points
// at the v1.x default `logos/sb-large.png` (which the v2.0 default
// theme never shipped), or points at a file that's been deleted
// from disk. Pre-fix this handler concatenated the raw
// `Config::get('template.logo')` value into a URL with no
// existence check, so any of the three broken-input shapes
// rendered a broken `<img>` on the sign-in card; the fallback now
// matches what the sidebar mark does via `core/header.php`.
$brandLogoUrl = \Sbpp\View\BrandLogo::resolveUrl();

$loginView = new \Sbpp\View\LoginView(
    normallogin_show: Config::getBool('config.enablenormallogin'),
    steamlogin_show: Config::getBool('config.enablesteamlogin'),
    redir: "DoLogin('');",
    brand_logo_url: $brandLogoUrl,
);

// `page_login.tpl` renders with the custom `-{ … }-` delimiter pair so
// inline `<script>` blocks can keep `{` / `}` for JS object literals
// without `{literal}` wrapping. Swap delimiters around
// `Renderer::render()` so the chrome (which uses the standard
// `{ … }` pair) is unaffected. {@see LoginView::DELIMITERS}.
$theme->setLeftDelimiter('-{');
$theme->setRightDelimiter('}-');
\Sbpp\View\Renderer::render($theme, $loginView);
$theme->setLeftDelimiter('{');
$theme->setRightDelimiter('}');
