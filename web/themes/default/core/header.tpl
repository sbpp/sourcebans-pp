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
        Favicon set (#1235): the icon artwork ships byte-faithful
        from the favicon.zip rumblefrog attached to #1235 — orange shield
        (`#ea580c`) with a black "+" centred and a small white "++" tucked
        in the bottom-right (the "++" in SourceBans++). The SVG is the
        primary — Chrome/Firefox/Safari prefer it; the .ico (3-icon:
        48x48 + 32x32 + 16x16) is the legacy fallback; favicon-96x96
        covers higher-DPI tab strips that pick a 96px source over the
        SVG; apple-touch-icon-180 covers iOS home screens. The web app
        manifest wires up the install-as-PWA path on Edge/Chrome with
        maskable 192x192 + 512x512 sources. The two theme-color metas
        paint the in-browser chrome bar: --brand-600 (`#ea580c`) by
        default, --zinc-950 (`#09090b` — the value html.dark resolves
        --bg-page to in theme.css) when the OS is dark; HTML metas
        win over the manifest's static theme_color for the in-browser
        case.
    *}
    <link rel="icon" type="image/svg+xml" href="{$theme_url}/images/favicon.svg">
    <link rel="icon" type="image/png" sizes="96x96" href="{$theme_url}/images/favicon-96x96.png">
    <link rel="alternate icon" type="image/x-icon" href="{$theme_url}/images/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="{$theme_url}/images/apple-touch-icon-180.png">
    <link rel="manifest" href="{$theme_url}/images/site.webmanifest">
    <meta name="theme-color" content="#ea580c">
    <meta name="theme-color" content="#09090b" media="(prefers-color-scheme: dark)">
    {*
        Anti-FOUC bootloader (#1367). theme.js (loaded from footer.tpl,
        the document tail) runs `applyTheme(currentTheme())` on boot to
        flip <html> into the user's persisted theme — but by then the
        body has already painted in light mode (the :root tokens default
        to light), and theme.js's class flip triggers a full repaint
        the user perceives as a white flash + content flicker on every
        page navigation. The reporter's exact symptom: "When navigating
        between pages while using dark mode, the page briefly renders in
        light mode for a split second before switching back to dark."

        The inline script below is byte-equivalent to theme.js's
        `applyTheme(currentTheme())` minus the localStorage write
        (theme.js still owns persistence): same THEME_KEY ('sbpp-theme'),
        same default ('system'), same dark-resolution logic. It runs
        synchronously before <body> parses, so the very first paint
        lands in the user's chosen mode. theme.js still runs on boot —
        its `applyTheme(currentTheme())` is now a no-op when the class
        is already correct (toggle(true) on a set class, toggle(false)
        on an unset class — both no-ops), and it stays the load-bearing
        path for the click + matchMedia handlers below it.

        Wrapped in IIFE + try/catch because `localStorage` throws on
        private-mode iframes / SecurityError, and `matchMedia` is
        missing on very old browsers; in either failure mode we
        silently fall through to light, matching theme.js's
        defensiveness. The logic also only ADDS the dark class — never
        removes — because :root defaults to light, so removing would
        be a no-op anyway, and not removing means we don't have to
        repeatedly clear before-checking.

        Placement: BEFORE the <link rel="stylesheet"> below. The script
        is parser-blocking + synchronous, so anywhere in <head> works
        in principle, but pinning it just above the stylesheet makes
        the "this resolves the CSS cascade for dark vs light tokens"
        intent obvious to future readers. Regression guard:
        web/tests/e2e/specs/flows/theme-fouc.spec.ts (stalls theme.js
        via page.route, asserts <html class="dark"> is present anyway).
    *}
    <script>
    (function () {
        try {
            var m = localStorage.getItem('sbpp-theme') || 'system';
            var d = m === 'dark' || (m === 'system' && window.matchMedia
                && matchMedia('(prefers-color-scheme: dark)').matches);
            if (d) document.documentElement.classList.add('dark');
        } catch (e) { /* localStorage / matchMedia unavailable; default to light */ }
    })();
    </script>
    <link rel="stylesheet" href="{$theme_url}/css/theme.css">
    <script src="./scripts/api-contract.js"></script>
    <script src="./scripts/sb.js"></script>
    <script src="./scripts/api.js"></script>
</head>
<body>
<div class="app">
