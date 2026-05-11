<?php
declare(strict_types=1);

// Issue #1335 M1: panel-runtime recovery surfaces.
//
// `web/init.php` runs before Composer autoload + Smarty are wired
// up, so any pre-bootstrap error path (missing config.php, install/
// still present, updater/ still present, missing vendor/autoload.php)
// has historically been a bare `die('plain text')` call. The result
// looks like a server crash to a non-technical self-hoster who clicked
// the wizard's "Open the panel" CTA before completing the post-install
// cleanup steps.
//
// This file exposes:
//
//   - `sbpp_check_install_guard()` — pure function; verdicts on whether
//     the panel runtime should refuse to boot. Tested in isolation
//     by `web/tests/integration/InstallGuardTest.php` (issue #1335 C1
//     regression: localhost-Host bypass).
//
//   - `sbpp_render_install_blocked_page()` — inline-HTML + inline-CSS
//     render path for the M1 surfaces. Called from `web/init.php` when
//     the guard fires. Same shape as `web/install/recovery.php` (no
//     `Sbpp\…` references, no Smarty, no `web/includes/vendor/`); pure
//     PHP + HTML.
//
// The render path is `: never` because each branch terminates with
// `exit;` after emitting the page. Splitting check from render keeps
// the function testable without spinning up a process — the tests
// just assert the verdict, not the rendered HTML.

/**
 * Decide whether the panel runtime should refuse to boot.
 *
 * Issue #1335 C1: pre-fix, `web/init.php` exempted `HTTP_HOST ==
 * "localhost"` from the install/ + updater/ presence check. The
 * exemption was a panel-takeover path on any panel reachable via a
 * `localhost` Host header (port-forward, SSH tunnel, ngrok, Cloudflare
 * Tunnel) and on local-development workflows — the operator simply
 * never saw the warning that they'd need to act on once they deployed.
 *
 * This function is the unconditional replacement: it ignores the Host
 * header entirely, returns the matching scenario when either directory
 * is present, and is bypassed in two cases:
 *
 *   - `IS_UPDATE` is defined — the updater itself sets this
 *     (`web/updater/index.php` defines it before requiring init.php
 *     so it can run while `updater/` is still on disk).
 *
 *   - `SBPP_DEV_KEEP_INSTALL` is defined truthy — explicit
 *     dev-only opt-in. Used by the project's `docker/php/dev-prepend.php`
 *     so the bind-mounted worktree (which includes `install/` and
 *     `updater/` from git) doesn't fail the guard during local
 *     iteration. Production panels MUST NOT define this — the
 *     constant is intentionally not auto-discovered from any
 *     environment variable, and the dev container's auto_prepend_file
 *     is the single legitimate setter. `web/configs/version.json`'s
 *     release-tarball flow has no path to this constant either, so
 *     a release zip extracted on top of an existing install can't
 *     accidentally inherit it.
 *
 * @return null|'install'|'updater' Non-null = render the matching
 *     blocked page and exit; null = boot normally.
 */
function sbpp_check_install_guard(string $root, bool $isUpdate, bool $devKeepInstall = false): ?string
{
    if ($isUpdate || $devKeepInstall) {
        return null;
    }
    if (file_exists($root . '/install')) {
        return 'install';
    }
    if (file_exists($root . '/updater')) {
        return 'updater';
    }
    return null;
}

/**
 * Render the panel-runtime blocked page for the given scenario and
 * exit. Three scenarios are supported:
 *
 *   - `'install'` — `install/` directory still present after the
 *      wizard completed. The operator clicked the wizard's "Open the
 *      panel" CTA before deleting the directory.
 *   - `'updater'` — `updater/` directory still present after a panel
 *      upgrade.
 *   - `'autoload'` — `web/includes/vendor/autoload.php` is missing.
 *      Mirror of `web/install/recovery.php` for the panel route.
 *
 * Self-contained inline HTML + CSS — runs upstream of Composer / Smarty.
 *
 * @param 'install'|'updater'|'autoload' $scenario
 */
function sbpp_render_install_blocked_page(string $scenario): never
{
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    $cfg = match ($scenario) {
        'install' => [
            'title'   => 'Finish installing SourceBans++',
            'lead'    => 'The install wizard left an <code>install/</code> directory in the panel root. SourceBans++ refuses to boot until you remove it &mdash; otherwise anyone who can reach the panel could re-run the wizard over your live install.',
            'heading' => 'How to fix it',
            'body'    => '<ol>'
                . '<li>Open your hosting File Manager (cPanel, DirectAdmin, Plesk, &hellip;) <em>or</em> connect via FTP / SFTP / SSH.</li>'
                . '<li>Navigate to the panel\'s web root &mdash; the directory that contains <code>index.php</code>, <code>config.php</code>, and the <code>install/</code> directory.</li>'
                . '<li>Delete the <code>install/</code> directory entirely. Folder + everything inside it.</li>'
                . '<li>Reload this page.</li>'
                . '</ol>'
                . '<p>If you still need to revisit the wizard (re-run schema, set up a fresh admin), open <a href="install/" data-testid="init-blocked-install-link">/install/</a> &mdash; it remains reachable while the directory is on disk.</p>',
            'testid'  => 'init-blocked-install',
            'why'     => 'The wizard\'s state &mdash; including the form that creates new admins and overwrites <code>config.php</code> &mdash; is all live PHP under <code>install/</code>. Leaving the folder in place after a successful install is a panel-takeover path; the panel guards against it by refusing to boot.',
        ],
        'updater' => [
            'title'   => 'Finish upgrading SourceBans++',
            'lead'    => 'The upgrade runner left an <code>updater/</code> directory in the panel root. SourceBans++ refuses to boot until you remove it.',
            'heading' => 'How to fix it',
            'body'    => '<ol>'
                . '<li>Open your hosting File Manager <em>or</em> connect via FTP / SFTP / SSH.</li>'
                . '<li>Navigate to the panel\'s web root.</li>'
                . '<li>Delete the <code>updater/</code> directory entirely.</li>'
                . '<li>Reload this page.</li>'
                . '</ol>',
            'testid'  => 'init-blocked-updater',
            'why'     => 'The updater can apply schema migrations against your live database; leaving the directory on disk leaves that surface reachable to anyone who can hit your panel URL.',
        ],
        'autoload' => [
            'title'   => 'SourceBans++ dependencies are missing',
            'lead'    => 'The panel can\'t find its bundled PHP dependencies (<code>web/includes/vendor/</code>), so it can\'t boot.',
            'heading' => 'How to fix it',
            'body'    => '<p><strong>Easiest &mdash; download the release zip:</strong></p>'
                . '<ol>'
                . '<li>Grab the latest <code>sourcebans-pp-X.Y.Z.webpanel-only.zip</code> from <a href="https://github.com/sbpp/sourcebans-pp/releases" target="_blank" rel="noopener">the releases page</a>.</li>'
                . '<li>Extract it on your computer. Make sure <code>web/includes/vendor/</code> exists in the extracted tree.</li>'
                . '<li>Re-upload the contents to your web root, overwriting the current files.</li>'
                . '<li>Reload this page.</li>'
                . '</ol>'
                . '<p><strong>If you have SSH access (developer / advanced):</strong></p>'
                . '<pre><code>cd /path/to/sourcebans/web' . "\n"
                . 'composer install --no-dev --optimize-autoloader</code></pre>',
            'testid'  => 'init-blocked-autoload',
            'why'     => 'The dependencies are hundreds of PHP source files under <code>vendor/</code> &mdash; not a single file the panel could fetch on the fly. Either re-uploading the bundled <code>vendor/</code> or running Composer locally produces them.',
        ],
    };

    $title   = $cfg['title'];
    $lead    = $cfg['lead'];
    $heading = $cfg['heading'];
    $body    = $cfg['body'];
    $testid  = $cfg['testid'];
    $why     = $cfg['why'];

    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> &middot; SourceBans++</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg-page: #fafafa;
            --bg-surface: #ffffff;
            --text: #18181b;
            --text-muted: #52525b;
            --border: #e4e4e7;
            --brand: #ea580c;
            --brand-hover: #c2410c;
            --warn-bg: #fef3c7;
            --warn-border: #fcd34d;
            --warn-text: #92400e;
            --code-bg: #f4f4f5;
            --code-text: #18181b;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg-page: #09090b;
                --bg-surface: #18181b;
                --text: #fafafa;
                --text-muted: #a1a1aa;
                --border: #27272a;
                --warn-bg: #422006;
                --warn-border: #92400e;
                --warn-text: #fde68a;
                --code-bg: #27272a;
                --code-text: #fafafa;
            }
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
                         "Helvetica Neue", Arial, sans-serif;
            font-size: 15px;
            line-height: 1.5;
            color: var(--text);
            background: var(--bg-page);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        main {
            width: 100%;
            max-width: 38rem;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        h1 { margin: 0 0 0.5rem; font-size: 1.5rem; font-weight: 600; }
        h2 { margin: 1.75rem 0 0.5rem; font-size: 1rem; font-weight: 600; }
        p { margin: 0.5rem 0; }
        .lead { color: var(--text-muted); margin-bottom: 1.25rem; }
        .alert {
            background: var(--warn-bg);
            border: 1px solid var(--warn-border);
            color: var(--warn-text);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin: 1.25rem 0;
            font-size: 0.9rem;
        }
        code, pre {
            font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
            font-size: 0.85rem;
        }
        code {
            background: var(--code-bg);
            color: var(--code-text);
            padding: 0.1rem 0.35rem;
            border-radius: 4px;
        }
        pre {
            background: var(--code-bg);
            color: var(--code-text);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            overflow-x: auto;
            margin: 0.5rem 0;
        }
        pre code {
            background: transparent;
            padding: 0;
            border-radius: 0;
        }
        ul, ol { padding-left: 1.5rem; }
        li { margin: 0.25rem 0; }
        a {
            color: var(--brand);
            text-decoration: underline;
            text-underline-offset: 2px;
        }
        a:hover { color: var(--brand-hover); }
        .footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
            font-size: 0.8rem;
            color: var(--text-muted);
            text-align: center;
        }
    </style>
</head>
<body>
<main role="main" data-testid="<?= htmlspecialchars($testid, ENT_QUOTES, 'UTF-8') ?>">
    <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="lead"><?= $lead /* trusted constant string from match() above */ ?></p>

    <h2><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></h2>
    <?= $body /* trusted constant string from match() above */ ?>

    <h2>Why this matters</h2>
    <p><?= $why /* trusted constant string from match() above */ ?></p>

    <div class="footer">
        SourceBans++ &middot;
        <a href="https://sbpp.github.io/" target="_blank" rel="noopener">sbpp.github.io</a>
    </div>
</main>
</body>
</html><?php
    exit;
}
