<?php if(!defined("IN_SB")){echo "You should not be here. Only follow links!";die();} ?>
	
	</div></div>
	<div id="footer">
		<div id="gc">
		By <a href="http://www.sourcebans.net" target="_blank" class="footer_link">SourceBans Dev Team</a>		</div>
		<div id="sb"><br/>
		<a href="https://sarabveer.github.io/SourceBans-Fork/" target="_blank"><img src="images/sb.png" alt="SourceBans" border="0" /></a><br/>
		<div id="footqversion">Version <?php echo SB_VERSION;?></div>
		<div id="footquote"><?php echo CreateQuote() ?></div>
		
		
		</div>
		<div id="sm">
		Powered by <a class="footer_link" href="http://www.sourcemod.net" target="_blank">SourceMod</a>
		</div>
	</div>
<?php
if(isset($_GET['debug']) && $_GET['debug'] == 1)
{
	echo '
	<h3>Session Data</h3><pre>
'; print_r($_SESSION); 
echo '
</pre>
<h3>Post Data</h3><pre>
';
 print_r($_POST); 
 echo '
</pre>
<h3>Cookie Data</h3><pre>
'; 
 print_r($_COOKIE); echo'
</pre> ';
}

?>
</div>
<script type="text/javascript">
window.addEvent('domready', function() {
	var Tips2 = new Tips($$('.tip'), {
		initialize:function(){
			this.fx = new Fx.Style(this.toolTip, 'opacity', {duration: 300, wait: false}).set(0);
		},
		onShow: function(toolTip) {
			this.fx.start(1);
		},
		onHide: function(toolTip) {
			this.fx.start(0);
		}
	});
	var Tips4 = new Tips($$('.perm'), {
		className: 'perm'
	});
}); 
$('content_title').setHTML('<?php echo $GLOBALS['TitleRewrite'] ?>');
</script>
<!--[if lt IE 7]>
<script defer type="text/javascript" src="./scripts/pngfix.js"></script>
<![endif]-->

</body>
</html>
