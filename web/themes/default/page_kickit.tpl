<html>
<head>
    <meta name="csrf-token" content="-{$csrf_token}-" />
    <script type="text/javascript" src="../scripts/sb.js"></script>
    <script type="text/javascript" src="../scripts/api.js"></script>
    <script type="text/javascript">
        //<![CDATA[
        var srvcount = 0;
        function set_counter(count) {
            srvcount += count;
            if (srvcount === -{$total}- || count === -1 || count === '-1') {
                parent.document.getElementById('dialog-control').innerHTML =
                    '<font color="green" style="font-size: 12px;"><b>Done searching.</b></font>'
                    + parent.document.getElementById('dialog-control').innerHTML;
                parent.document.getElementById('dialog-control').style.display = 'block';
                setTimeout(function () {
                    parent.document.getElementById('dialog-placement').style.display = 'none';
                }, 5000);
                setTimeout(function () { window.location = '../index.php?p=admin&c=bans'; }, 5000);
            }
        }

        function processRow(check, type, sid, num) {
            sb.api.call('kickit.kick_player', { check: check, sid: sid, num: num, type: Number(type) })
                .then(function (r) {
                    if (!r || !r.ok || !r.data) {
                        sb.setHTML('srv_' + num, "<font color='red' size='1'><i>Error.</i></font>");
                        set_counter(1);
                        return;
                    }
                    var d = r.data;
                    if (d.hostname) {
                        sb.setHTML('srvip_' + num, "<font size='1'><span title='" + d.ip + ':' + d.port + "'>" + d.hostname + "</span></font>");
                    }
                    if (d.status === 'no_connect') {
                        sb.setHTML('srv_' + num, "<font color='red' size='1'><i>Can't connect to server.</i></font>");
                        set_counter(1);
                    } else if (d.status === 'kicked') {
                        sb.setHTML('srv_' + num, "<font color='green' size='1'><b><u>Player Found & Kicked!</u></b></font>");
                        set_counter(-1);
                    } else {
                        sb.setHTML('srv_' + num, "<font size='1'>Player not found.</font>");
                        set_counter(1);
                    }
                });
        }

        window.addEventListener('load', function () {
            parent.document.getElementById('dialog-control').style.display = 'none';
            sb.api.call('kickit.load_servers', {}).then(function (r) {
                if (!r || !r.ok || !r.data) return;
                r.data.servers.forEach(function (s) {
                    if (s.has_rcon) {
                        sb.setHTML('srv_' + s.num, '<font size="1">Searching...</font>');
                        processRow('-{$check}-', '-{$type}-', s.sid, s.num);
                    } else {
                        sb.setHTML('srv_' + s.num, '<font size="1">No rcon password.</font>');
                        set_counter(1);
                    }
                });
            });
        });
        //]]>
    </script>
</head>
<body style="
	background-repeat: repeat-x;
	color: #444;
	font-family: Verdana, Arial, Tahoma, Trebuchet MS, Sans-Serif, Georgia, Courier, Times New Roman, Serif;
	font-size: 11px;
	line-height: 135%;
	margin: 5px;
	padding: 0px;
   ">
<div id="container" name="container">
    <h3 style="font-size: 12px;">Searching for the player on all servers...</h3>
    <table border="0">
        -{foreach from=$servers item=serv}-
        <tr>
            <td><div id="srvip_-{$serv.num}-"><font size="1">-{$serv.ip}-:-{$serv.port}-</font></div></td>
            <td>
                <div id="srv_-{$serv.num}-"><font size="1">Waiting...</font></div>
            </td>
        </tr>
        -{/foreach}-
    </table>
</div>
<script type="text/javascript">
    if (parent.document.getElementById('srvkicker')) {
        parent.document.getElementById('srvkicker').height =
            (document.getElementById('container').offsetHeight + 10) + 'px';
    }
</script>
</body>
</html>
