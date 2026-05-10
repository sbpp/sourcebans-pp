<?php
declare(strict_types=1);

// Issue #1332 C3: pre-bootstrap recovery surface for missing vendor/.
//
// This file is the "no autoloader" branch of `web/install/index.php`.
// It is loaded BEFORE the panel's Composer autoload runs, so it MUST
// stay self-contained — no `Sbpp\…` references, no Smarty, no class
// loaded from `web/includes/vendor/`. Pure inline HTML + CSS.
//
// The legacy behaviour (pre-#1332) was for `web/init.php` (NOT this
// installer's init.php) to `die('Compose autoload not found! Run
// `composer install`...')` the moment a non-technical self-hoster
// uploaded a zip that omitted vendor/ — historically every release
// zip, until the Workstream A change to .github/workflows/release.yml
// started bundling it. This page is the belt-and-suspenders for
// installs that still hit the missing-vendor case (git checkouts,
// hand-edited zips, partial uploads).
//
// Sister-fix on the API side: web/init.php's bail message is what an
// `/index.php` or `/api.php` request hits without vendor/. This file
// only handles the `/install/` entry; init.php's surface is upstream
// of every other panel route.

http_response_code(503);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Setup needed &mdash; SourceBans++ installer</title>
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
            --error-bg: #fef2f2;
            --error-border: #fecaca;
            --error-text: #991b1b;
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
                --error-bg: #450a0a;
                --error-border: #7f1d1d;
                --error-text: #fecaca;
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
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            color: var(--error-text);
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
<main role="main" data-testid="install-recovery">
    <h1>Setup needed</h1>
    <p class="lead">
        SourceBans++ can't find its bundled PHP dependencies, so the
        installer can't continue.
    </p>

    <div class="alert" role="alert">
        Missing <code>web/includes/vendor/autoload.php</code>.
    </div>

    <h2>What this means</h2>
    <p>
        The panel needs a <code>vendor/</code> directory under
        <code>web/includes/</code> to load Smarty, Symfony Mailer, and
        the other PHP libraries it depends on. The directory is
        normally bundled inside the release zip; if it's missing, you
        likely uploaded the wrong artifact, or you're running from a
        git checkout.
    </p>

    <h2>How to fix it</h2>

    <p><strong>Easiest &mdash; download the release zip:</strong></p>
    <ol>
        <li>
            Grab the latest
            <code>sourcebans-pp-X.Y.Z.webpanel-only.zip</code> from
            <a href="https://github.com/sbpp/sourcebans-pp/releases"
               target="_blank" rel="noopener">the releases page</a>.
        </li>
        <li>
            Extract it on your computer. Make sure
            <code>web/includes/vendor/</code> exists in the extracted
            tree.
        </li>
        <li>
            Re-upload the contents to your web root, overwriting the
            current files. Pay extra attention to the
            <code>includes/vendor/</code> folder &mdash; some FTP
            clients skip "hidden" or unfamiliar directories by
            default.
        </li>
        <li>Reload this page.</li>
    </ol>

    <p><strong>If you have SSH access (developer / advanced):</strong></p>
    <p>
        Run <a href="https://getcomposer.org/" target="_blank" rel="noopener">Composer</a>
        from the panel root to populate <code>vendor/</code>:
    </p>
    <pre><code>cd /path/to/sourcebans/web
composer install --no-dev --optimize-autoloader</code></pre>
    <p>Then reload this page.</p>

    <h2>Why the installer can't recover automatically</h2>
    <p>
        The dependencies aren't a single file the installer could
        download &mdash; they're hundreds of PHP source files
        organised under <code>vendor/</code>. The packaging step that
        produces them needs Composer (or a pre-built tarball that
        already contains them). Either route happens before the
        panel runs; the installer just consumes the result.
    </p>

    <div class="footer">
        SourceBans++ installer &middot;
        <a href="https://sbpp.github.io/" target="_blank" rel="noopener">sbpp.github.io</a>
    </div>
</main>
</body>
</html>
