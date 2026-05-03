		</div></div></div>
		<div id="footer">
			<div id="mainwrapper" style="text-align: center;">
				<a href="https://sbpp.github.io/" target="_blank"><img src="images/logos/sb.png" alt="SourceBans" border="0" /></a><br/>
				<div id="footqversion" style="line-height: 20px;"><a style="color: #C1C1C1" href="https://sbpp.github.io/" target="_blank">SourceBans++</a> {$version}{$git}</div>
			    <span style="line-height: 20px;">Powered by <a href="https://www.sourcemod.net" target="_blank" style="color: #C1C1C1">SourceMod</a></span><br />
			</div>
		</div>
</div>
<script>

{$query nofilter}

{literal}
sb.ready(function () {
    sb.tabs.init();
    sb.tooltip('.tip');
    sb.tooltip('.perm', { className: 'perm' });
});
{/literal}
</script>
</body>
</html>
