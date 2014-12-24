<html>
<head>
-{$xajax_functions}-
<script type="text/javascript">
//<![CDATA[
window.onload = function() {xajax_LoadServers2('-{$check}-', '-{$type}-', '-{$length}-');}
var srvcount = 0;
function set_counter(count)
{
	srvcount += count;
	if(srvcount==-{$total}- || count=='-1') {
		parent.document.getElementById('dialog-control').innerHTML = "<font color=\"green\" style=\"font-size: 12px;\"><b>Done searching.</b></font>"+parent.document.getElementById('dialog-control').innerHTML;
		parent.document.getElementById('dialog-control').setStyle('display', 'block');
		setTimeout("parent.document.getElementById('dialog-placement').setStyle('display', 'none');",5000);
		setTimeout("window.location='../index.php?p=admin&c=comms'",5000);
	}
}
parent.document.getElementById('dialog-control').setStyle('display', 'none');
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
if(document.all) {
	parent.document.all["srvkicker"].height = document.all["container"].offsetHeight + 10;
}
else {
	parent.document.getElementById("srvkicker").height = document.documentElement.clientHeight;
}
</script>
</body>
</html>