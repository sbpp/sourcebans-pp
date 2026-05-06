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
    <link rel="icon" href="{$theme_url}/images/favicon.ico">
    <link rel="stylesheet" href="{$theme_url}/css/theme.css">
    <script src="./scripts/api-contract.js"></script>
    <script src="./scripts/sb.js"></script>
    <script src="./scripts/api.js"></script>
</head>
<body>
<div class="app">
