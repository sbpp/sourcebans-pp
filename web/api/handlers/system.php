<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.
*************************************************************************/

use Sbpp\Mail\EmailType;
use Sbpp\Mail\Mail;
use Sbpp\Markup\IntroRenderer;

function api_system_check_version(array $params): array
{
    $raw = (string)@file_get_contents('https://sbpp.github.io/version.json');
    $version = $raw ? json_decode($raw, true) : null;
    $version = is_array($version) ? $version : ['version' => '', 'git' => ''];

    $latest = $version['version'] ?? '';
    if (strlen((string)$latest) > 8 || $latest === '') {
        $latest = 'Error';
        $msg = 'Error Retrieving Latest Release.';
        $update = false;
    } elseif (version_compare($latest, SB_VERSION) > 0) {
        $msg = 'A New Release is Available.';
        $update = true;
    } else {
        $msg = 'You have the Latest Release.';
        $update = false;
    }

    $devLatest = null;
    $devUpdate = null;
    $devMsg = null;
    if (SB_DEV) {
        $git = $version['git'] ?? '';
        if (strlen((string)$git) > 8 || $git === '') {
            $devLatest = 'Error';
            $devMsg = 'Error retrieving latest Dev Version.';
            $devUpdate = false;
        } elseif ((int)$git > SB_GITREV) {
            $devLatest = $git;
            $devMsg = 'A New Dev Version is Available.';
            $devUpdate = true;
        } else {
            $devLatest = $git;
            $devMsg = 'You have the Latest Dev Version.';
            $devUpdate = false;
        }
    }

    return [
        'release_latest' => $latest,
        'release_msg'    => $msg,
        'release_update' => $update,
        'dev'            => SB_DEV,
        'dev_latest'     => $devLatest,
        'dev_msg'        => $devMsg,
        'dev_update'     => $devUpdate,
    ];
}

function api_system_sel_theme(array $params): array
{
    $theme = rawurldecode((string)($params['theme'] ?? ''));
    $theme = str_replace(['../', '..\\', chr(0)], '', $theme);
    $theme = basename($theme);

    if ($theme === '' || $theme[0] === '.' || !in_array($theme, scandir(SB_THEMES), true)
        || !is_dir(SB_THEMES . $theme) || !file_exists(SB_THEMES . $theme . '/theme.conf.php')) {
        throw new ApiError('invalid_theme', 'Invalid theme selected.');
    }

    include SB_THEMES . $theme . '/theme.conf.php';
    if (!defined('theme_screenshot')) {
        throw new ApiError('bad_theme', 'Bad theme selected.');
    }

    return [
        'theme'      => $theme,
        'name'       => theme_name,
        'author'     => theme_author,
        'version'    => theme_version,
        'link'       => theme_link,
        'screenshot' => 'themes/' . $theme . '/' . strip_tags(theme_screenshot),
    ];
}

function api_system_apply_theme(array $params): array
{
    $theme = rawurldecode((string)($params['theme'] ?? ''));
    $theme = str_replace(['../', '..\\', chr(0)], '', $theme);
    $theme = basename($theme);

    if ($theme === '' || $theme[0] === '.' || !in_array($theme, scandir(SB_THEMES), true)
        || !is_dir(SB_THEMES . $theme) || !file_exists(SB_THEMES . $theme . '/theme.conf.php')) {
        throw new ApiError('invalid_theme', 'Invalid theme selected.');
    }

    include SB_THEMES . $theme . '/theme.conf.php';
    if (!defined('theme_screenshot')) {
        throw new ApiError('bad_theme', 'Bad theme selected.');
    }

    $GLOBALS['PDO']->query("UPDATE `:prefix_settings` SET value = ? WHERE setting = 'config.theme'")->execute([$theme]);
    return ['reload' => true];
}

function api_system_clear_cache(array $params): array
{
    $cachedir = dir(SB_CACHE);
    while (($entry = $cachedir->read()) !== false) {
        if (is_file($cachedir->path . $entry)) {
            @unlink($cachedir->path . $entry);
        }
    }
    $cachedir->close();

    return ['cleared' => true];
}

function api_system_send_mail(array $params): array
{
    global $username;
    $subject = (string)($params['subject'] ?? '');
    $message = (string)($params['message'] ?? '');
    $type    = (string)($params['type']    ?? '');
    $id      = (int)($params['id']      ?? 0);

    if ($type !== 's' && $type !== 'p') {
        throw new ApiError('bad_type', 'Bad email type.');
    }

    $email = '';
    if ($type === 's') {
        $GLOBALS['PDO']->query("SELECT email FROM `:prefix_submissions` WHERE subid = :id");
        $GLOBALS['PDO']->bind(':id', $id);
        $row = $GLOBALS['PDO']->single();
        $email = $row['email'] ?? '';
    } elseif ($type === 'p') {
        $GLOBALS['PDO']->query("SELECT email FROM `:prefix_protests` WHERE pid = :id");
        $GLOBALS['PDO']->bind(':id', $id);
        $row = $GLOBALS['PDO']->single();
        $email = $row['email'] ?? '';
    }

    if (empty($email)) {
        throw new ApiError('no_email', 'There is no email to send to supplied.');
    }

    $sent = Mail::send($email, EmailType::Custom, [
        '{message}' => $message,
        '{subject}' => $subject,
        '{admin}'   => $username,
        '{link}'    => Host::complete(true),
        '{home}'    => Host::complete(true),
    ], $subject);

    if (!$sent) {
        throw new ApiError('mail_failed', 'Failed to send the email to the user.');
    }

    Log::add('m', 'Email Sent', "$username send an email to $email. Subject: '[SourceBans++] $subject'; Message: $message");

    return [
        'message' => [
            'title' => 'Email Sent',
            'body'  => 'The email has been sent to the user.',
            'kind'  => 'green',
            'redir' => 'index.php?p=admin&c=bans',
        ],
    ];
}

/**
 * Render an admin-authored Markdown snippet (currently used by the
 * dashboard `dash.intro.text` setting; #1207 SET-1) through the same
 * `Sbpp\Markup\IntroRenderer` the public dashboard uses, so the
 * settings page can show a live "this is what visitors will see"
 * preview without forcing the admin to save + navigate to `/`.
 *
 * Critical: NEVER render the supplied Markdown with anything other
 * than `IntroRenderer::renderIntroText`. The renderer wraps
 * league/commonmark with `html_input: 'escape'` and
 * `allow_unsafe_links: false` — that's the only safe path documented
 * in AGENTS.md ("Admin-authored display text"). #1113 was a stored
 * XSS rooted in a parallel render path; ducking back into a plain
 * CommonMark/Parsedown call here would re-open the vector.
 *
 * Gated on `ADMIN_OWNER | ADMIN_WEB_SETTINGS` because the only
 * caller is the settings page (gated on the same flag), and we'd
 * rather refuse a stray call from another surface than discover a
 * new caller exists.
 *
 * @param array{markdown?: string} $params
 * @return array{html: string}
 */
function api_system_preview_intro_text(array $params): array
{
    $markdown = (string) ($params['markdown'] ?? '');
    // Long inputs can stall the renderer; the textarea has no
    // server-side length limit but a 64 KiB ceiling is well past any
    // reasonable intro length (the rendered field already lives in
    // a single TEXT column) and prevents a logged-in admin from
    // wedging the API with a runaway paste.
    if (strlen($markdown) > 65536) {
        $markdown = substr($markdown, 0, 65536);
    }
    return ['html' => IntroRenderer::renderIntroText($markdown)];
}

function api_system_rehash_admins(array $params): array
{
    $serversCsv = (string)($params['servers'] ?? '');
    $servers = array_filter(explode(',', $serversCsv), fn($v) => $v !== '');
    $results = [];
    foreach ($servers as $sid) {
        $ret = rcon('sm_rehash', (int)$sid);
        $results[] = [
            'sid'     => (int)$sid,
            'success' => $ret === '',
        ];
    }
    return ['results' => $results];
}
