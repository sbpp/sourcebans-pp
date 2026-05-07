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

use Sbpp\View\AdminFeaturesView;
use Sbpp\View\AdminLogsView;
use Sbpp\View\AdminSettingsView;
use Sbpp\View\AdminThemesView;
use Sbpp\View\Perms;
use Sbpp\View\Renderer;

/*
 * Section routing (B18 redesign).
 *
 * Each section is its own page request keyed on
 * `?section=settings|features|logs|themes`; the sub-nav at the top of
 * each template carries `aria-current="page"` on the active tab. CSRF
 * is enforced globally by `route()` in includes/page-builder.php.
 *
 * #1239 brought the rest of the admin family onto this same shape —
 * servers / mods / groups / comms now route via `?section=…` too.
 * See AGENTS.md "Sub-paged admin routes" for the convention; this
 * file remains the long-standing reference.
 */
$validSections = ['settings', 'features', 'logs', 'themes'];
$section = (string)($_GET['section'] ?? 'settings');
if (!in_array($section, $validSections, true)) {
    $section = 'settings';
}

if (isset($_GET['log_clear']) && $_GET['log_clear'] === 'true') {
    if ($userbank->HasAccess(ADMIN_OWNER)) {
        $GLOBALS['PDO']->query("TRUNCATE TABLE `:prefix_log`")->execute();
    } else {
        Log::add('w', 'Hacking Attempt', $userbank->GetProperty('user') . " tried to clear the logs, but doesn't have access.");
    }
}

$canSettings = (bool) $userbank->HasAccess(ADMIN_OWNER | ADMIN_WEB_SETTINGS);

/*
 * Post-save flash, emitted at the bottom of this file.
 *
 * `header('Location: …')` after a successful save *doesn't work here*
 * because by the time admin.settings.php runs, build() in
 * includes/page-builder.php has already required core/header.php →
 * core/navbar.php → core/title.php — each of which calls
 * `$theme->display()` and flushes Smarty output to stdout. PHP has no
 * global output buffer (no ob_start in init.php / index.php, no
 * `output_buffering` in docker/Dockerfile), so a server-side redirect
 * here triggers `headers already sent` and the user lands on a partial
 * page with a raw PHP warning. The pre-B18 code dodged this by emitting
 * a JS-side `ShowBox(…, redir)` bounce instead; we keep that shape but
 * use the post-v2.0.0 `window.SBPP.showToast` helper with a
 * `ShowBox` fallback for any third-party theme that forked the
 * pre-v2.0.0 default.
 */
$savedSection = '';

if ($canSettings && isset($_POST['settingsGroup'])) {
    $errors = '';

    if ($_POST['settingsGroup'] === 'mainsettings') {
        if (!is_numeric($_POST['config_password_minlength'] ?? '')) {
            $errors .= "Min password length must be a number<br />";
        }
        if (!is_numeric($_POST['banlist_bansperpage'] ?? '')) {
            $errors .= 'Bans per page must be a number';
        }

        if (empty($errors)) {
            $submit         = (isset($_POST['enable_submit'])    && $_POST['enable_submit']    === 'on') ? 1 : 0;
            $protest        = (isset($_POST['enable_protest'])   && $_POST['enable_protest']   === 'on') ? 1 : 0;
            $commslist      = (isset($_POST['enable_commslist']) && $_POST['enable_commslist'] === 'on') ? 1 : 0;
            $lognopopup     = (isset($_POST['dash_nopopup'])     && $_POST['dash_nopopup']     === 'on') ? 1 : 0;
            $debugmode      = (isset($_POST['config_debug'])     && $_POST['config_debug']     === 'on') ? 1 : 0;
            $hideadmname    = (isset($_POST['banlist_hideadmname'])    && $_POST['banlist_hideadmname']    === 'on') ? 1 : 0;
            $hideplayerips  = (isset($_POST['banlist_hideplayerips'])  && $_POST['banlist_hideplayerips']  === 'on') ? 1 : 0;
            $nocountryfetch = (isset($_POST['banlist_nocountryfetch']) && $_POST['banlist_nocountryfetch'] === 'on') ? 1 : 0;
            $onlyinvolved   = (isset($_POST['protest_emailonlyinvolved']) && $_POST['protest_emailonlyinvolved'] === 'on') ? 1 : 0;

            $rawReasons = $_POST['bans_customreason'] ?? [];
            if (!is_array($rawReasons)) {
                $rawReasons = [];
            }
            $reasons = [];
            foreach ($rawReasons as $r) {
                if (is_string($r) && $r !== '') {
                    $reasons[] = htmlspecialchars($r);
                }
            }
            $cureason = !empty($reasons) ? serialize($reasons) : '';

            $smtpConfigSql = ", (?, 'smtp.host'), (?, 'smtp.user'), (?, 'smtp.port'), (?, 'smtp.verify_peer')"
                . ", (?, 'config.mail.from_email'), (?, 'config.mail.from_name')";
            $smtpConfig = [
                trim((string) ($_POST['mail_host'] ?? '')),
                trim((string) ($_POST['mail_user'] ?? '')),
                trim((string) ($_POST['mail_port'] ?? '')),
                isset($_POST['mail_verify_peer']) && $_POST['mail_verify_peer'] === 'on' ? 1 : 0,
                trim((string) ($_POST['mail_from_email'] ?? '')),
                trim((string) ($_POST['mail_from_name'] ?? '')),
            ];

            if (!empty($_POST['mail_pass'])) {
                $smtpConfigSql .= ", (?, 'smtp.pass')";
                $smtpConfig[]   = (string) $_POST['mail_pass'];
            }

            $GLOBALS['PDO']->query("REPLACE INTO `:prefix_settings` (`value`, `setting`) VALUES
                (?, 'template.title'),
                (?, 'template.logo'),
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
                (" . (int) ($_POST['default_page'] ?? 0) . ", 'config.defaultpage')"
                . $smtpConfigSql)->execute([
                    (string) ($_POST['template_title'] ?? ''),
                    (string) ($_POST['template_logo'] ?? ''),
                    (string) ($_POST['config_dateformat'] ?? ''),
                    (string) ($_POST['dash_intro_title'] ?? ''),
                    (string) ($_POST['dash_intro_text'] ?? ''),
                    $cureason,
                    (string) ($_POST['auth_maxlife'] ?? ''),
                    (string) ($_POST['auth_maxlife_remember'] ?? ''),
                    (string) ($_POST['auth_maxlife_steam'] ?? ''),
                    ...$smtpConfig,
                ]);
            Log::add('m', 'Settings updated', 'Main settings were updated.');
            $savedSection = 'settings';
        }
    }

    if ($_POST['settingsGroup'] === 'features') {
        $kickit         = (isset($_POST['enable_kickit'])         && $_POST['enable_kickit']         === 'on') ? 1 : 0;
        $exportpub      = (isset($_POST['export_public'])         && $_POST['export_public']         === 'on') ? 1 : 0;
        $groupban       = (isset($_POST['enable_groupbanning'])   && $_POST['enable_groupbanning']   === 'on') ? 1 : 0;
        $friendsban     = (isset($_POST['enable_friendsbanning']) && $_POST['enable_friendsbanning'] === 'on') ? 1 : 0;
        $adminrehash    = (isset($_POST['enable_adminrehashing']) && $_POST['enable_adminrehashing'] === 'on') ? 1 : 0;
        $steamloginopt  = (isset($_POST['enable_steamlogin'])     && $_POST['enable_steamlogin']     === 'on') ? 1 : 0;
        $normalloginopt = (isset($_POST['enable_normallogin'])    && $_POST['enable_normallogin']    === 'on') ? 1 : 0;
        $publiccomments = (isset($_POST['enable_publiccomments']) && $_POST['enable_publiccomments'] === 'on') ? 1 : 0;

        $GLOBALS['PDO']->query("REPLACE INTO `:prefix_settings` (`value`, `setting`) VALUES
            (" . $exportpub      . ", 'config.exportpublic'),
            (" . $kickit         . ", 'config.enablekickit'),
            (" . $groupban       . ", 'config.enablegroupbanning'),
            (" . $friendsban     . ", 'config.enablefriendsbanning'),
            (" . $adminrehash    . ", 'config.enableadminrehashing'),
            (" . $publiccomments . ", 'config.enablepubliccomments'),
            (" . $steamloginopt  . ", 'config.enablesteamlogin'),
            (" . $normalloginopt . ", 'config.enablenormallogin')")->execute();
        Log::add('m', 'Settings updated', 'Feature toggles were updated.');
        $savedSection = 'features';
    }

    if (!empty($errors)) {
        echo '<div class="card" style="margin:1rem"><div class="card__body" style="color:var(--danger)">' . $errors . '</div></div>';
    }
}

/*
 * Theme discovery.
 *
 * The legacy version of this loop only captured `theme_name` per theme
 * via regex against theme.conf.php (PHP can't `define()` the same
 * constant twice in one process, so the regex is the only way to
 * enumerate without resetting state). B18 keeps the regex-based
 * discovery and just enriches it with author / version / link /
 * screenshot — every theme.conf.php is expected to declare those four
 * constants.
 */
$validThemes = [];
$themesDir   = opendir(SB_THEMES);
if ($themesDir !== false) {
    while (($filename = readdir($themesDir)) !== false) {
        if ($filename[0] === '.') {
            continue;
        }
        $confPath = SB_THEMES . $filename . '/theme.conf.php';
        if (!@is_file($confPath)) {
            continue;
        }
        $confSrc = (string) @file_get_contents($confPath);
        $validThemes[] = [
            'dir'        => $filename,
            'name'       => themeConfMatch($confSrc, 'theme_name', $filename),
            'author'     => themeConfMatch($confSrc, 'theme_author', 'Unknown'),
            'version'    => themeConfMatch($confSrc, 'theme_version', '?'),
            'link'       => themeConfMatch($confSrc, 'theme_link', ''),
            'screenshot' => 'themes/' . $filename . '/' . themeConfMatch($confSrc, 'theme_screenshot', 'screenshot.jpg'),
            'active'     => $filename === SB_THEME,
        ];
    }
    closedir($themesDir);
}
usort($validThemes, fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

/**
 * Pluck a `define('<key>', "<value>")` literal out of a theme.conf.php
 * source string. Mirrors the legacy regex shape (double-quoted only)
 * so existing theme manifests keep parsing identically.
 */
function themeConfMatch(string $src, string $key, string $default): string
{
    $pattern = '/define\(\s*\'' . preg_quote($key, '/') . '\'\s*,\s*"([^"]*)"\s*\)\s*;/';
    if (preg_match($pattern, $src, $m) === 1) {
        return strip_tags($m[1]);
    }
    return $default;
}

/**
 * Whether STEAMAPIKEY is set to a non-empty value at runtime. Wrapped
 * in a function so PHPStan can't narrow the constant value against its
 * phpstan-bootstrap.php sentinel ('') — see the same workaround in
 * web/api/handlers/bans.php::_api_bans_steam_api_key().
 */
function adminSettingsHasSteamApiKey(): bool
{
    /** @var string $key */
    $key = defined('STEAMAPIKEY') ? (string)constant('STEAMAPIKEY') : '';
    return $key !== '';
}

/*
 * Currently-selected theme metadata.
 *
 * The shipped template derives the active theme card from the
 * `$theme_list[].active` row; the standalone `theme_*` properties are
 * still passed for any third-party theme that forked the pre-v2.0.0
 * default and rendered them in a separate "current theme" panel.
 *
 * `require()` is intentional here (matches the legacy loop) — by the
 * time this page renders, init.php has already require'd the same
 * theme.conf.php exactly once, so re-requiring it is a no-op.
 */
require(SB_THEMES . SB_THEME . '/theme.conf.php');
$currentScreenshotUrl = 'themes/' . SB_THEME . '/' . strip_tags((string) constant('theme_screenshot'));
$currentScreenshotImg = '<img width="250px" height="170px" src="' . htmlspecialchars($currentScreenshotUrl, ENT_QUOTES, 'UTF-8') . '">';

/*
 * Permission snapshot used by every settings sub-tab. We pick the
 * specific `can_*` keys each View declares rather than splatting
 * `...Perms::for()` whole — the helper returns every ADMIN_* flag
 * the panel knows about, but PHP 8.1 throws "Unknown named parameter"
 * on a constructor that doesn't declare them all. Picking explicitly
 * is also self-documenting: the page handler enumerates exactly which
 * gates this template surfaces.
 */
$perms = Perms::for($userbank);

if ($section === 'themes') {
    Renderer::render($theme, new AdminThemesView(
        can_web_settings:  $perms['can_web_settings'],
        can_owner:         $perms['can_owner'],
        active_section:    $section,
        current_theme_dir: SB_THEME,
        theme_list:        $validThemes,
        theme_name:        strip_tags((string) constant('theme_name')),
        theme_author:      strip_tags((string) constant('theme_author')),
        theme_version:     strip_tags((string) constant('theme_version')),
        theme_link:        strip_tags((string) constant('theme_link')),
        theme_screenshot:  $currentScreenshotImg,
    ));
} elseif ($section === 'logs') {
    $page = 1;
    if (isset($_GET['page']) && (int) $_GET['page'] > 0) {
        $page = (int) $_GET['page'];
    }
    $listStart = ($page - 1) * SB_BANS_PER_PAGE;
    $listEnd   = $listStart + SB_BANS_PER_PAGE;

    if (isset($_GET['advSearch'])) {
        $searchlink = '&advSearch=' . urlencode((string) $_GET['advSearch']) . '&advType=' . urlencode((string) ($_GET['advType'] ?? ''));
    } else {
        $searchlink = '';
    }

    $logCount = (int) Log::getCount('');
    $log      = Log::getAll($listStart, SB_BANS_PER_PAGE);

    $prev = '';
    $next = '';
    if ($page > 1) {
        $prev = CreateLinkR('&laquo; prev', 'index.php?p=admin&c=settings&section=logs' . $searchlink . '&page=' . ($page - 1));
    }
    if ($listEnd < $logCount) {
        $next = CreateLinkR('next &raquo;', 'index.php?p=admin&c=settings&section=logs' . $searchlink . '&page=' . ($page + 1));
    }
    $pages = (int) max(1, (int) ceil($logCount / SB_BANS_PER_PAGE));
    $pageNumbers = 'Page ' . $page . ' of ' . $pages;
    if ($pages > 1) {
        if ($prev !== '') {
            $pageNumbers .= ' &nbsp; ' . $prev;
        }
        if ($next !== '') {
            $pageNumbers .= ' &nbsp; ' . $next;
        }
    }

    $logList = [];
    foreach ($log as $l) {
        $item             = $l;
        $item['user']     = !empty($l['user']) ? $l['user'] : 'Guest';
        $item['date_str'] = Config::time((int) $l['created']);
        $item['message']  = str_replace("\n", '<br />', htmlentities(str_replace(['<br />', '<br>', '<br/>'], "\n", (string) $l['message'])));
        $item['type_img'] = match ((string) $l['type']) {
            'm'     => "<img src='themes/" . SB_THEME . "/images/admin/help.png' alt='Info'>",
            'w'     => "<img src='themes/" . SB_THEME . "/images/admin/warning.png' alt='Warning'>",
            'e'     => "<img src='themes/" . SB_THEME . "/images/admin/error.png' alt='Error'>",
            default => '',
        };
        $logList[]        = $item;
    }

    Renderer::render($theme, new AdminLogsView(
        can_web_settings: $perms['can_web_settings'],
        can_owner:        $perms['can_owner'],
        active_section:   $section,
        clear_logs:       $userbank->HasAccess(ADMIN_OWNER) ? "( <a href='javascript:ClearLogs();'>Clear Log</a> )" : '',
        page_numbers:     $pageNumbers,
        log_items:        $logList,
    ));
} elseif ($section === 'features') {
    Renderer::render($theme, new AdminFeaturesView(
        can_web_settings:      $perms['can_web_settings'],
        can_owner:             $perms['can_owner'],
        active_section:        $section,
        steamapi:              adminSettingsHasSteamApiKey(),
        export_public:         Config::getBool('config.exportpublic'),
        enable_kickit:         Config::getBool('config.enablekickit'),
        enable_groupbanning:   Config::getBool('config.enablegroupbanning'),
        enable_friendsbanning: Config::getBool('config.enablefriendsbanning'),
        enable_adminrehashing: Config::getBool('config.enableadminrehashing'),
        enable_steamlogin:     Config::getBool('config.enablesteamlogin'),
        enable_normallogin:    Config::getBool('config.enablenormallogin'),
        enable_publiccomments: Config::getBool('config.enablepubliccomments'),
    ));
} else {
    $rawCustomReasons = Config::getBool('bans.customreasons')
        ? @unserialize((string) Config::get('bans.customreasons'))
        : [];
    if (!is_array($rawCustomReasons)) {
        $rawCustomReasons = [];
    }
    $customReasons = array_values(array_filter(
        array_map(fn($v) => is_string($v) ? $v : '', $rawCustomReasons),
        fn(string $v): bool => $v !== ''
    ));

    /** @var array{0: string, 1: string, 2: string} $smtpTuple */
    $smtpTuple = [
        (string) Config::get('smtp.host'),
        (string) Config::get('smtp.user'),
        (string) Config::get('smtp.port'),
    ];

    $dashText = (string) Config::get('dash.intro.text');
    /*
     * #1232: server-rendered first paint for the per-input duration
     * echo on the Authentication fieldset. The minute-typed integers
     * stay as the wire-format source of truth (the SourceMod plugin
     * reads `auth.maxlife*` in minutes too); these `_human` props are
     * the operator-readable strings ("≈ 7 days" / "1 hour" / etc.)
     * the template emits in muted spans next to each input. The
     * page-tail JS re-implements the same formula so the echo stays
     * live as the operator types — see the matching `humanizeMinutes`
     * in `page_admin_settings_settings.tpl`.
     */
    $authMaxlife         = (int) Config::get('auth.maxlife');
    $authMaxlifeRemember = (int) Config::get('auth.maxlife.remember');
    $authMaxlifeSteam    = (int) Config::get('auth.maxlife.steam');

    Renderer::render($theme, new AdminSettingsView(
        can_web_settings:            $perms['can_web_settings'],
        can_owner:                   $perms['can_owner'],
        active_section:              $section,
        config_title:                (string) Config::get('template.title'),
        config_logo:                 (string) Config::get('template.logo'),
        config_min_password:         (int) MIN_PASS_LENGTH,
        config_dateformat:           (string) Config::get('config.dateformat'),
        config_dash_title:           (string) Config::get('dash.intro.title'),
        config_dash_text:            $dashText,
        // #1207 SET-1: server-rendered first paint for the live preview.
        // JS-side updates call system.preview_intro_text on input.
        config_dash_text_preview:    \Sbpp\Markup\IntroRenderer::renderIntroText($dashText),
        auth_maxlife:                $authMaxlife,
        auth_maxlife_remember:       $authMaxlifeRemember,
        auth_maxlife_steam:          $authMaxlifeSteam,
        auth_maxlife_human:          \Sbpp\Util\Duration::humanizeMinutes($authMaxlife),
        auth_maxlife_remember_human: \Sbpp\Util\Duration::humanizeMinutes($authMaxlifeRemember),
        auth_maxlife_steam_human:    \Sbpp\Util\Duration::humanizeMinutes($authMaxlifeSteam),
        config_debug:                Config::getBool('config.debug'),
        enable_submit:               Config::getBool('config.enablesubmit'),
        enable_protest:              Config::getBool('config.enableprotest'),
        enable_commslist:            Config::getBool('config.enablecomms'),
        protest_emailonlyinvolved:   Config::getBool('protest.emailonlyinvolved'),
        dash_lognopopup:             Config::getBool('dash.lognopopup'),
        config_default_page:         (int) Config::get('config.defaultpage'),
        config_bans_per_page:        (int) SB_BANS_PER_PAGE,
        banlist_hideadmname:         Config::getBool('banlist.hideadminname'),
        banlist_nocountryfetch:      Config::getBool('banlist.nocountryfetch'),
        banlist_hideplayerips:       Config::getBool('banlist.hideplayerips'),
        bans_customreason:           $customReasons,
        config_smtp:                 $smtpTuple,
        config_smtp_verify_peer:     Config::getBool('smtp.verify_peer'),
        config_mail_from_email:      (string) Config::get('config.mail.from_email'),
        config_mail_from_name:       (string) Config::get('config.mail.from_name'),
    ));
}

/*
 * Post-save flash + bounce.
 *
 * Emitted only when a settings-group POST just succeeded. We can't use
 * `header('Location: …')` from this file (see the `$savedSection`
 * declaration near the top for why); instead we render a small inline
 * <script> that:
 *
 *   1. Surfaces a "Settings saved" toast via the active theme's toast
 *      surface — `window.SBPP.showToast` (theme.js) with `ShowBox` as
 *      a fallback for any third-party theme that forked the
 *      pre-v2.0.0 default.
 *   2. Bounces to the GET URL after a short delay so a refresh doesn't
 *      re-POST the form (the `ShowBox(…, redir)` fallback does this
 *      itself; otherwise we issue an explicit `location.href` after
 *      `setTimeout`).
 *
 * Cooperatively no-op if neither toast surface is available — the
 * `location.href` fallback still cleans the request method.
 */
if ($savedSection !== ''):
    $bouncePath = 'index.php?p=admin&c=settings&section=' . htmlspecialchars($savedSection, ENT_QUOTES, 'UTF-8');
?>
<script>
(function () {
    var url   = '<?= $bouncePath ?>';
    var title = 'Settings updated';
    var msg   = 'The changes have been saved.';
    if (window.SBPP && typeof window.SBPP.showToast === 'function') {
        window.SBPP.showToast({ kind: 'success', title: title, body: msg });
        setTimeout(function () { window.location.href = url; }, 1200);
    } else if (typeof ShowBox === 'function') {
        ShowBox(title, msg, 'green', url);
    } else {
        window.location.href = url;
    }
})();
</script>
<?php endif; ?>
