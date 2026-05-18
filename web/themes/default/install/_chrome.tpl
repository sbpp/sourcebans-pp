{*
    SourceBans++ install wizard — shared chrome (header + footer halves
    sandwiched around the step body).

    NOT a parent layout — Smarty has no template-inheritance primitive.
    Each step .tpl in this directory `{include file="install/_chrome.tpl"}`s
    THIS file at the very top to emit <!doctype>, <head>, the wizard
    header, and the open <main>; the step .tpl then closes </main> + the
    document with `{include file="install/_chrome_close.tpl"}`.

    The split exists so each step can drop its content between the two
    `include`s without copy-pasting the chrome markup.

    The wizard runs OUTSIDE the panel's core/header.tpl because:
      - There's no logged-in user yet (no sidebar, no command palette,
        no theme toggle to gate).
      - There's no DB up yet on step 1 (no Config::get, no
        $userbank to drive permission gating).
      - The chrome JS (theme.js / lucide.min.js) reads $palette_actions_json
        which is built by Sbpp\View\PaletteActions::for($userbank); the
        wizard has no $userbank.

    Reuses the panel's theme.css so install pages look like the same
    product — design tokens, .card, .btn, .input, .label. Install-only
    chrome (.install-shell, .install-alert*, .install-pill*,
    .install-grid, .install-table) lives inline below — those don't
    appear elsewhere in the panel.

    SmartyTemplateRule notes
    ------------------------
    Variables referenced in this partial (`$page_title`, `$step`,
    `$step_title`, `$step_count`, `$step_label`) propagate transitively
    to every step view that {include}s it. Each step view declares
    them as public readonly properties so the parity check stays green.

    `$step_label` is the human label for the step indicator (e.g.
    "License", "Database", "Setup"). `$step_count` is fixed at the
    total wizard length so the indicator reads "Step 2 of 5". The
    optional AMXBans import (step 6) re-uses the same chrome but is
    marked as a sub-flow rather than a numbered step.
*}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{$page_title} &middot; SourceBans++ installer</title>
    {*
        The wizard relies on the panel's design-system stylesheet
        (theme.css) so its forms / buttons / cards look identical to
        the live panel. Path is computed from web/install/, hence the
        single `..`. Auto-color-scheme via @media in theme.css works
        out of the box — install respects light/dark.
    *}
    <link rel="stylesheet" href="../themes/default/css/theme.css">
    <link rel="icon" type="image/svg+xml" href="../themes/default/images/favicon.svg">
    <link rel="alternate icon" type="image/x-icon" href="../themes/default/images/favicon.ico">
    <style>
        /* Chrome-only styling that doesn't fit cleanly into theme.css's
           panel-shaped surfaces. Kept inline because the install wizard
           is the only consumer + we don't want to grow theme.css's
           surface for it. */
        .install-shell {
            min-height: 100vh;
            background: var(--bg-page);
            display: flex;
            flex-direction: column;
        }
        .install-header {
            border-bottom: 1px solid var(--border);
            background: var(--bg-surface);
            padding: 1rem 1.5rem;
        }
        .install-header__inner {
            max-width: 56rem;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .install-brand {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            font-weight: 600;
            color: var(--text);
            text-decoration: none;
        }
        .install-brand img {
            width: 1.5rem;
            height: 1.5rem;
        }
        .install-brand small {
            display: block;
            font-size: 0.7rem;
            font-weight: 400;
            color: var(--text-muted, var(--text-faint));
        }
        .install-progress {
            font-size: 0.8rem;
            color: var(--text-muted, var(--text-faint));
            font-variant-numeric: tabular-nums;
        }
        .install-progress strong {
            color: var(--text);
            font-weight: 600;
        }
        .install-stepper {
            max-width: 56rem;
            margin: 0 auto;
            padding: 1rem 1.5rem 0;
            display: flex;
            gap: 0.375rem;
            list-style: none;
        }
        .install-stepper li {
            flex: 1;
            height: 4px;
            border-radius: 2px;
            background: var(--border);
        }
        .install-stepper li[data-state="done"]    { background: var(--brand-600, #ea580c); }
        .install-stepper li[data-state="current"] { background: var(--brand-600, #ea580c); }
        .install-stepper li[data-state="todo"]    { background: var(--border); }
        .install-main {
            flex: 1;
            max-width: 56rem;
            width: 100%;
            margin: 0 auto;
            padding: 1.5rem;
        }
        .install-main h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 0.5rem;
        }
        .install-main p.lead {
            color: var(--text-muted, var(--text-faint));
            margin: 0 0 1.25rem;
        }
        .install-section {
            margin-top: 1.5rem;
        }
        .install-section h2 {
            font-size: 0.95rem;
            font-weight: 600;
            margin: 0 0 0.5rem;
        }
        .install-actions {
            margin-top: 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .install-footer {
            border-top: 1px solid var(--border);
            background: var(--bg-surface);
            padding: 0.75rem 1.5rem;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-muted, var(--text-faint));
        }
        .install-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
        }
        .install-table th,
        .install-table td {
            border-bottom: 1px solid var(--border);
            padding: 0.5rem 0.75rem;
            text-align: left;
            vertical-align: middle;
        }
        .install-table th {
            font-weight: 600;
            color: var(--text-muted, var(--text-faint));
            background: var(--bg-page);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .install-pill {
            display: inline-block;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .install-pill--ok {
            background: rgba(34, 197, 94, 0.15);
            color: rgb(21, 128, 61);
        }
        .install-pill--warn {
            background: rgba(234, 179, 8, 0.15);
            color: rgb(133, 77, 14);
        }
        .install-pill--err {
            background: rgba(239, 68, 68, 0.15);
            color: rgb(153, 27, 27);
        }
        .install-alert {
            border: 1px solid;
            border-radius: var(--radius-md, 0.5rem);
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .install-alert--error {
            border-color: rgba(239, 68, 68, 0.4);
            background: var(--danger-bg);
            color: rgb(153, 27, 27);
        }
        .install-alert--warn {
            border-color: rgba(234, 179, 8, 0.4);
            background: var(--warning-bg);
            color: rgb(133, 77, 14);
        }
        .install-alert--ok {
            border-color: rgba(34, 197, 94, 0.4);
            background: rgba(34, 197, 94, 0.1);
            color: rgb(21, 128, 61);
        }
        .install-alert--info {
            border-color: rgba(59, 130, 246, 0.4);
            background: rgba(59, 130, 246, 0.1);
            color: rgb(30, 64, 175);
        }
        .install-code {
            display: block;
            background: var(--bg-page);
            border: 1px solid var(--border);
            border-radius: var(--radius-md, 0.5rem);
            padding: 0.75rem 1rem;
            font-family: var(--font-mono, monospace);
            font-size: 0.8125rem;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
            color: var(--text);
        }
        .install-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
            gap: 1rem;
        }
        @media (prefers-color-scheme: dark) {
            .install-pill--ok   { color: rgb(134, 239, 172); }
            .install-pill--warn { color: rgb(253, 224, 71); }
            .install-pill--err  { color: rgb(252, 165, 165); }
            .install-alert--error { color: rgb(252, 165, 165); }
            .install-alert--warn  { color: rgb(253, 224, 71); }
            .install-alert--ok    { color: rgb(134, 239, 172); }
            .install-alert--info  { color: rgb(147, 197, 253); }
        }
        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: 0ms !important; transition-duration: 0ms !important; }
        }
    </style>
</head>
<body>
<div class="install-shell">
    <header class="install-header" role="banner">
        <div class="install-header__inner">
            <div class="install-brand">
                <img src="../themes/default/images/favicon.svg" alt="" aria-hidden="true">
                <div>
                    <div>SourceBans++</div>
                    <small>Installer</small>
                </div>
            </div>
            <div class="install-progress" data-testid="install-progress">
                {if $step <= $step_count}
                    Step <strong>{$step}</strong> of {$step_count}: {$step_label}
                {else}
                    {$step_label}
                {/if}
            </div>
        </div>
    </header>

    {if $step <= $step_count}
        <ol class="install-stepper" aria-hidden="true">
            {section name=i loop=$step_count start=1}
                {if $smarty.section.i.iteration < $step}
                    <li data-state="done"></li>
                {elseif $smarty.section.i.iteration == $step}
                    <li data-state="current"></li>
                {else}
                    <li data-state="todo"></li>
                {/if}
            {/section}
        </ol>
    {/if}

    <main class="install-main" role="main">
        <h1 data-testid="install-step-title">{$step_title}</h1>
