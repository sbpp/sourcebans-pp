-{*
    SourceBans++ 2026 — page_blockit.tpl
    Bound view: \Sbpp\View\BlockitView (web/includes/View/BlockitView.php).

    Mirror of page_kickit.tpl but for the comm-block flow loaded into
    an iframe by `ShowBlockBox()` in web/scripts/sourcebans.js after
    a comm block is added. Variable contract, delimiter rationale,
    parent-window dialog hooks (set_counter, height adjustment) all
    match the kickit template — see that file for the per-block
    explanation. The only delta on the wire is `$length` (the block
    duration in minutes) being forwarded into Actions.BlockitBlockPlayer.
*}-
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="-{$csrf_token}-">
    <title>Block player</title>
    <link rel="stylesheet" href="../themes/default/css/theme.css">
    <script src="../scripts/api-contract.js"></script>
    <script src="../scripts/sb.js"></script>
    <script src="../scripts/api.js"></script>
</head>
<body style="background:transparent;padding:0.5rem">
<div id="container" class="card" data-testid="blockit-container">
    <div class="card__header">
        <div>
            <h3>Searching for the player on all servers&hellip;</h3>
            <p>Each row is polled live; rows update as servers respond.</p>
        </div>
    </div>
    <div class="card__body">
        <table class="table" data-testid="blockit-results">
            <thead>
                <tr>
                    <th>Server</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                -{foreach from=$servers item=serv}-
                <tr data-testid="blockit-row--{$serv.num}-">
                    <td class="font-mono text-xs">
                        <div id="srvip_-{$serv.num}-"
                             data-testid="blockit-host--{$serv.num}-">-{$serv.ip}-:-{$serv.port}-</div>
                    </td>
                    <td>
                        <div id="srv_-{$serv.num}-"
                             class="text-xs text-muted"
                             data-testid="blockit-status--{$serv.num}-">Waiting&hellip;</div>
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
        var LENGTH = -{$length}-;
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
                setTimeout(function () { window.location = '../index.php?p=admin&c=comms'; }, 5000);
            }
        }

        function processRow(sid, num) {
            sb.api.call(Actions.BlockitBlockPlayer, { check: CHECK, sid: sid, num: num, type: TYPE, length: LENGTH })
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
                    } else if (d.status === 'blocked') {
                        sb.setHTML('srv_' + num, "<span class='text-xs font-semibold' style='color:var(--success)'><u>Player Found &amp; blocked!</u></span>");
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

            sb.api.call(Actions.BlockitLoadServers, {}).then(function (r) {
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
