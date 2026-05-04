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

/**
 * Compute the schema-migrator dry-run plan against the live database.
 *
 * Backs the `?p=admin&c=upgrade` page's "Refresh dry-run" button. Read-only
 * — never mutates the schema. Permission gating (`ADMIN_OWNER`) is enforced
 * at the {@see Api::register()} call in `_register.php`.
 *
 * The exact response shape is documented on
 * {@see \Sbpp\Migrator\MigrationPlan::toArray()}; we keep the @return tag
 * loose here on purpose — the api-contract generator's JSDoc emitter
 * trips on `?T`-followed-by-comma in nested array shapes, and the JS
 * caller (`UpgradeRefresh()`) re-types the response inline anyway.
 *
 * @return array
 */
function api_system_upgrade_diff(array $params): array
{
    $migrator = api_system_make_migrator();
    return $migrator->plan(api_system_raw_pdo())->toArray();
}

/**
 * Apply the schema-migrator plan against the live database.
 *
 * Re-computes the plan inside the handler so the apply always reflects the
 * live diff at the moment of click — staleness between a "Refresh" view
 * and the "Apply" click would otherwise let an admin try to apply a
 * change that's already been satisfied. Permission gating (`ADMIN_OWNER`)
 * is enforced at the {@see Api::register()} call in `_register.php`.
 *
 * The exact response shape is documented on
 * {@see \Sbpp\Migrator\MigrationResult::toArray()}; we keep the @return
 * tag loose here on purpose — the api-contract generator's JSDoc emitter
 * trips on `?T`-followed-by-comma in nested array shapes, and the JS
 * caller (`UpgradeApply()`) re-types the response inline anyway.
 *
 * @return array
 */
function api_system_upgrade_apply(array $params): array
{
    $migrator = api_system_make_migrator();
    $pdo      = api_system_raw_pdo();

    $plan    = $migrator->plan($pdo);
    $applier = new \Sbpp\Migrator\MigrationApplier($GLOBALS['PDO']->getPrefix());
    $result  = $applier->apply($pdo, $plan);

    Log::add(
        $result->success ? 'm' : 'e',
        $result->success ? 'Schema upgrade applied' : 'Schema upgrade failed',
        sprintf(
            '%d step(s); %s',
            count($result->steps),
            $result->error ?? 'ok'
        ),
    );

    return $result->toArray();
}

/**
 * Build the migrator façade pointed at the canonical SQL files shipped
 * in `web/install/includes/sql/`. Helper kept inside the handler file
 * because nothing else needs it.
 */
function api_system_make_migrator(): \Sbpp\Migrator\Migrator
{
    return \Sbpp\Migrator\Migrator::fromInstallSql(
        ROOT,
        $GLOBALS['PDO']->getPrefix(),
        defined('DB_CHARSET') ? (string) DB_CHARSET : 'utf8mb4',
    );
}

/**
 * Open a fresh PDO connection for the migrator. The differ runs raw
 * `information_schema` queries, which is awkward through `Database`
 * (the `:prefix_` rewriter would mangle bound table names). A bare PDO
 * sidesteps the whole issue.
 */
function api_system_raw_pdo(): \PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        defined('DB_PORT') ? (int) DB_PORT : 3306,
        DB_NAME,
        defined('DB_CHARSET') ? (string) DB_CHARSET : 'utf8mb4',
    );
    return new \PDO($dsn, DB_USER, DB_PASS, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
}
