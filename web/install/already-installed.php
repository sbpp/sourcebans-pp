<?php
declare(strict_types=1);

// Issue #1335 C2: wizard-side panel-takeover prevention.
//
// Pre-fix, `web/install/index.php` had no "is the panel already
// installed?" gate. After a successful wizard run, re-visiting
// `/install/` re-rendered step 1 and let the operator walk the
// entire flow again — including overwriting `config.php` (when
// writable), creating a new admin account, and re-pointing the
// panel at a different DB. There was no warning and no
// confirmation. Combined with the C1 localhost-Host bypass on the
// init-side guard (or with any operator who simply forgot to
// delete `install/`), this was a panel-takeover path.
//
// This file exposes:
//
//   - `sbpp_install_is_already_installed()` — pure function;
//     returns true if `config.php` exists in the panel root.
//     Tested in isolation by `web/tests/integration/InstallGuardTest.php`.
//
//   - `sbpp_install_render_already_installed_page()` — inline-HTML
//     + inline-CSS render path; called from `web/install/index.php`
//     when the guard fires. Same shape as `recovery.php` (no
//     `Sbpp\…` references, no Smarty, no `web/includes/vendor/`)
//     because the C2 check intentionally runs upstream of Composer
//     autoload — a panel that's already installed shouldn't even
//     boot the wizard's Smarty instance, since the install/
//     directory is supposed to be deleted post-install.
//
// The render path is `: never` because it terminates with `exit;`.
// Splitting check from render keeps the function testable without
// spinning up a process.

/**
 * Decide whether the install wizard should refuse to start.
 *
 * The check is a single `file_exists()` against `config.php` in the
 * panel root. Pre-#1332 the wizard would happily walk you through
 * step 1-5 again over a live install; that's the regression #1335
 * C2 closes.
 *
 * `config.php` is the canonical "is the panel installed?" sentinel
 * for the same reason `web/init.php` keys off it — the file is
 * required-once by every panel entry point, and it's the artefact
 * the wizard's step 5 produces. An operator who genuinely wants to
 * reinstall over an existing panel deletes `config.php` first
 * (the page below tells them so).
 */
function sbpp_install_is_already_installed(string $panelRoot): bool
{
    return file_exists($panelRoot . 'config.php');
}

/**
 * Render the "already installed" guard page and exit.
 *
 * Self-contained inline HTML + inline CSS — runs upstream of
 * Composer / Smarty so the page renders even if vendor/ is missing
 * for some reason (race with an in-flight upload, partial restore
 * from backup, etc.).
 */
function sbpp_install_render_already_installed_page(): never
{
    http_response_code(409);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');

    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Already installed &middot; SourceBans++ installer</title>
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
<main role="main" data-testid="install-already-installed">
    <h1>This panel is already installed</h1>
    <p class="lead">
        SourceBans++ found a <code>config.php</code> in the panel root,
        which means the install wizard has already run successfully.
        Re-running it would overwrite your config, your admin account,
        and your DB connection &mdash; not something the wizard will do
        without an explicit reset.
    </p>

    <div class="alert" role="alert">
        For your safety, the wizard refuses to start while a panel is
        installed.
    </div>

    <h2>What you probably want to do</h2>
    <ul>
        <li>
            <strong>Open the panel:</strong>
            <a href="../" data-testid="install-already-installed-open-panel">return to <code>/</code></a>.
        </li>
        <li>
            <strong>Lock down post-install:</strong> delete the
            <code>install/</code> directory entirely. The panel
            refuses to boot until you do &mdash; that's the
            sister-guard that closes the same panel-takeover path
            on the runtime side.
        </li>
    </ul>

    <h2>If you really need to reinstall</h2>
    <p>
        Reinstalling will <strong>destroy your existing config and
        admin account</strong>; existing data in the database is
        preserved (the wizard's schema-install step runs <code>CREATE
        TABLE IF NOT EXISTS</code>, so no data is dropped &mdash; but
        the admin row gets re-inserted, and you'll need to point the
        wizard at the same DB to reuse what's there).
    </p>
    <ol>
        <li>
            Take a backup. At minimum, download <code>config.php</code>
            and run a <code>mysqldump</code> of the panel's database.
        </li>
        <li>
            Delete <code>config.php</code> from the panel root via
            File Manager / FTP / SSH.
        </li>
        <li>Reload this page; the wizard will start at step 1.</li>
    </ol>

    <h2>Why this matters</h2>
    <p>
        Without this guard, anyone who could reach <code>/install/</code>
        could re-run the wizard over your live panel, point it at a
        different database, and create themselves an Owner-flagged
        admin account &mdash; a complete panel takeover. The
        <code>config.php</code> sentinel + the matching guard in
        <code>web/init.php</code> close the loop on both sides.
    </p>

    <div class="footer">
        SourceBans++ installer &middot;
        <a href="https://sbpp.github.io/" target="_blank" rel="noopener">sbpp.github.io</a>
    </div>
</main>
</body>
</html><?php
    exit;
}
