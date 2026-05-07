{*
    SourceBans++ 2026 — chrome / header.tpl

    Renders <!DOCTYPE>, <head>, and the opening <body><div class="app">.
    Pair: web/pages/core/header.php (assigns $title, $logo, $theme).
    Globals from web/init.php: $csrf_token, $csrf_field_name, $theme_url.

    Variable contract is identical to web/themes/default/core/header.tpl
    except for the new $theme_url assigned by web/init.php in this PR;
    all other vars (title, theme, logo) come from web/pages/core/header.php
    unchanged. The legacy navbar.php, title.php, footer.php files keep
    invoking their templates by literal name (core/navbar.tpl etc.), so
    page-builder.php's build() pulls in this chrome automatically when
    config.theme=sbpp2026.

    Vendored Inter + JetBrains Mono are loaded via @font-face in
    {$theme_url}/css/theme.css; the handoff's Google Fonts <link> tags
    are intentionally omitted (self-hosters install offline).
    Lucide is vendored too (web/themes/sbpp2026/js/lucide.min.js) and
    pulled in from the footer alongside theme.js.
*}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{$csrf_token}">
    {*
        SteamIDs ([U:1:144180014], STEAM_0:0:..., +1 712 555 ... lookalikes)
        and the per-row IPs the drawer shows are colon-/digit-heavy enough that
        Mobile Safari + Chrome's auto-detection heuristics flag them as phone
        numbers and overlay a tap-to-dial link with the platform's accent
        colour (#1207 DET-1: pinkish on iOS dark, blueish on Android). The
        chrome doesn't have a single phone number on it, so we opt the entire
        document out — `format-detection` for Safari, `x-apple-data-detectors`
        for legacy iOS WebKit, `address=no` for the postal-code variant.
    *}
    <meta name="format-detection" content="telephone=no,date=no,address=no,email=no">
    <meta name="x-apple-data-detectors" content="false">
    <title>{$title}</title>
    {*
        Favicon set (#1235): the SVG is the primary — Chrome/Firefox/Safari
        prefer it and the @media (prefers-color-scheme: dark) rule baked
        into the file lightens the orange to --brand-400 (#fb923c) when
        the OS chrome is dark, matching the dark-theme accent. The .ico
        is the legacy fallback. apple-touch-icon-180 covers iOS home
        screens. The two theme-color metas paint the mobile chrome
        bar: --brand-600 (#ea580c) by default, --zinc-950 (#09090b —
        the value html.dark resolves --bg-page to in theme.css) when
        the OS is dark.
    *}
    <link rel="icon" type="image/svg+xml" href="{$theme_url}/images/favicon.svg">
    <link rel="alternate icon" type="image/x-icon" href="{$theme_url}/images/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="{$theme_url}/images/apple-touch-icon-180.png">
    <meta name="theme-color" content="#ea580c">
    <meta name="theme-color" content="#09090b" media="(prefers-color-scheme: dark)">
    <link rel="stylesheet" href="{$theme_url}/css/theme.css">
    <script src="./scripts/api-contract.js"></script>
    <script src="./scripts/sb.js"></script>
    <script src="./scripts/api.js"></script>
</head>
<body>
<div class="app">
