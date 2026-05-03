-{if NOT $permission_rcon}-
    Access Denied!
    -{else}-
    <div id="admin-page-content">
        <div id="1">


            <h3>RCON Console</h3>
            <div align="center" width="90%">
                <div id="rcon" style="overflow:auto;
			background-color:#efefef;
			border: 1px solid #999;
			padding: 3px;
			height: 250px;
			width: 90%;" align="left">

<pre>















<div id="rcon_con">***********************************************************<br />**&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;**<br />*&nbsp;SourceBans RCON console&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;*<br />*&nbsp;Type your comand in the box below and hit enter&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;*<br />*&nbsp;Type 'clr' to clear the console&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;*<br />**&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;**<br />***********************************************************<br />
</div>
</pre>
                </div>
                <br />
                Command: <input type="text" style="font-family:verdana, tahoma, arial;font-size:10px;width:500px" id="cmd">
                <input type="button" onclick="SendRcon();" id="rcon_btn" value="Send">
            </div>
        </div></div>
    <script>

        document.documentElement.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') SendRcon();
        });

        function SendRcon()
        {
            LoadSendRcon('-{$id}-', sb.$id('cmd').value, true);
            sb.$id('cmd').value = 'Executing, Please Wait...';
            sb.$id('cmd').disabled = true;
            sb.$id('rcon_btn').disabled = true;
        }

        // Auto-scroll the RCON pane to the bottom (replaces Fx.Scroll).
        var scroll = (function () {
            var box = sb.$id('rcon');
            return {
                toBottom: function () { if (box) box.scrollTop = box.scrollHeight; },
            };
        })();
        scroll.toBottom();
    </script>
    -{/if}-
