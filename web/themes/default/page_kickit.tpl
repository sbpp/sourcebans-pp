-{*
    SourceBans++ 2026 — page_kickit.tpl
    Bound view: \Sbpp\View\KickitView (web/includes/View/KickitView.php).

    Self-contained <html> doc loaded by the parent admin/bans page in
    an iframe (no chrome shell), so we link directly to the active
    theme's stylesheet — the iframe URL is /pages/admin.kickit.php so
    `../themes/default/css/theme.css` resolves correctly. The custom
    `-{ … }-` delimiter pair lets the inline JS keep its raw `{` /
    `}` tokens — see KickitView::DELIMITERS.

    The window title, return URL, and theme path are NOT template
    variables — they're fixed for this theme/page.

    Per-row JS calls Actions.KickitLoadServers + Actions.KickitKickPlayer
    via sb.api.call (CSRF token forwarded as the X-CSRF-Token header
    read from the <meta> tag below). No <form> on this page, so no
    {csrf_field} needed. Per-row containers (`srv_<n>`, `srvip_<n>`)
    preserve the IDs the legacy template used so existing
    parent-window dialog hooks (set_counter, height adjustment) keep
    working.

    Anti-FOUC bootloader (#1438): the `pages/admin.kickit.php` URL is
    reachable two ways in v2.0 chrome: as an iframe inside the
    post-Ban "Ban Added" `sb.message.show` dialog (legacy default-theme
    surface — `#dialog-placement` only exists on the default theme),
    AND as a top-level navigation from the public Servers page's
    right-click context menu's "Kick player" item (`web/scripts/server-context-menu.js`
    builds the href directly to `pages/admin.kickit.php?check=…`). The
    latter is the user-reported #1438 path: an operator in dark mode
    right-clicks a player, picks Kick, and lands on this chromeless
    iframe template rendered as a full-page document — and the page
    paints stark white because `<html>` never gets the `dark` class.
    The inline bootloader below mirrors `core/header.tpl`'s shape
    (same THEME_KEY 'sbpp-theme', same default 'system', same dark-
    resolution predicate; only ADDS the class, never removes — :root
    defaults to light) so the very first paint lands in the operator's
    persisted theme regardless of how the page was reached. Logic
    must stay byte-equivalent to the canonical bootloader in
    `core/header.tpl` (which in turn mirrors `theme.js`'s
    `applyTheme(currentTheme())` minus the localStorage write); the
    regression gate is `web/tests/integration/IframeChromeAntiFoucBootloaderTest.php`.
    See "Anti-FOUC theme bootloader" in AGENTS.md Conventions for the
    full contract. Wrapped in IIFE + try/catch because localStorage
    throws on private-mode iframes / SecurityError and matchMedia is
    missing on very old browsers — defaults to light on either failure,
    matching the chrome's shape.
*}-
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="-{$csrf_token}-">
    <title>Kick player</title>
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
    <link rel="stylesheet" href="../themes/default/css/theme.css">
    <script src="../scripts/api-contract.js"></script>
    <script src="../scripts/sb.js"></script>
    <script src="../scripts/api.js"></script>
</head>
<body style="background:transparent;padding:0.5rem">
<div id="container" class="card" data-testid="kickit-container">
    <div class="card__header">
        <div>
            <h3>Searching for the player on all servers&hellip;</h3>
            <p>Each row is polled live; rows update as servers respond.</p>
        </div>
    </div>
    <div class="card__body">
        <table class="table" data-testid="kickit-results">
            <thead>
                <tr>
                    <th>Server</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                -{foreach from=$servers item=serv}-
                <tr data-testid="kickit-row--{$serv.num}-">
                    <td class="font-mono text-xs">
                        <div id="srvip_-{$serv.num}-"
                             data-testid="kickit-host--{$serv.num}-">-{$serv.ip}-:-{$serv.port}-</div>
                    </td>
                    <td>
                        <div id="srv_-{$serv.num}-"
                             class="text-xs text-muted"
                             data-testid="kickit-status--{$serv.num}-">Waiting&hellip;</div>
                    </td>
                </tr>
                -{/foreach}-
            </tbody>
        </table>
    </div>
</div>
<script>
    (function () {
        var TOTAL = -{$total}-;
        var CHECK = '-{$check}-';
        var TYPE = -{$type}-;
        var srvcount = 0;

        function setCounter(count) {
            srvcount += count;
            if (srvcount === TOTAL || count === -1) {
                var ctl = parent.document.getElementById('dialog-control');
                if (ctl) {
                    ctl.innerHTML = '<font color="green" style="font-size: 12px;"><b>Done searching.</b></font>' + ctl.innerHTML;
                    ctl.style.display = 'block';
                }
                setTimeout(function () {
                    var place = parent.document.getElementById('dialog-placement');
                    if (place) place.style.display = 'none';
                }, 5000);
                setTimeout(function () { window.location = '../index.php?p=admin&c=bans'; }, 5000);
            }
        }

        function processRow(sid, num) {
            sb.api.call(Actions.KickitKickPlayer, { check: CHECK, sid: sid, num: num, type: TYPE })
                .then(function (r) {
                    if (!r || !r.ok || !r.data) {
                        sb.setHTML('srv_' + num, "<span class='text-xs' style='color:var(--danger)'><i>Error.</i></span>");
                        setCounter(1);
                        return;
                    }
                    var d = r.data;
                    if (d.hostname) {
                        sb.setHTML('srvip_' + num, "<span class='font-mono text-xs' title='" + d.ip + ':' + d.port + "'>" + d.hostname + "</span>");
                    }
                    if (d.status === 'no_connect') {
                        sb.setHTML('srv_' + num, "<span class='text-xs' style='color:var(--danger)'><i>Can't connect to server.</i></span>");
                        setCounter(1);
                    } else if (d.status === 'kicked') {
                        sb.setHTML('srv_' + num, "<span class='text-xs font-semibold' style='color:var(--success)'><u>Player Found &amp; Kicked!</u></span>");
                        setCounter(-1);
                    } else {
                        sb.setHTML('srv_' + num, "<span class='text-xs text-muted'>Player not found.</span>");
                        setCounter(1);
                    }
                });
        }

        window.addEventListener('load', function () {
            var ctl = parent.document.getElementById('dialog-control');
            if (ctl) ctl.style.display = 'none';

            sb.api.call(Actions.KickitLoadServers, {}).then(function (r) {
                if (!r || !r.ok || !r.data) return;
                r.data.servers.forEach(function (s) {
                    if (s.has_rcon) {
                        sb.setHTML('srv_' + s.num, '<span class="text-xs text-muted">Searching&hellip;</span>');
                        processRow(s.sid, s.num);
                    } else {
                        sb.setHTML('srv_' + s.num, '<span class="text-xs text-faint">No rcon password.</span>');
                        setCounter(1);
                    }
                });
            });

            var srvkicker = parent.document.getElementById('srvkicker');
            if (srvkicker) {
                srvkicker.height = (document.getElementById('container').offsetHeight + 20) + 'px';
            }
        });
    })();
</script>
</body>
</html>
