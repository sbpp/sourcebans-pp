<?php
if (!defined("IN_SB")) {
    print "You should not be here. Only follow links!";
    die();
}
?>

</div></div>
<div id="footer">
    <div id="mainwrapper" style="text-align: center;">
        <a href="https://sbpp.github.io/" target="_blank"><img src="images/logos/sb-small.png" alt="SourceBans" border="0" /></a><br/>
        <div id="footqversion" style="line-height: 20px;"><a style="color: #C1C1C1" href="https://sbpp.github.io/" target="_blank">SourceBans++</a> <?php print SB_VERSION;?></div>
        <span style="line-height: 20px;">Powered by <a href="http://www.sourcemod.net" target="_blank" style="color: #C1C1C1">SourceMod</a></span><br />
        <span style="line-height: 20px;"><?php print CreateQuote();?><span>
    </div>
</div>
<?php
if (isset($_GET['debug']) && $_GET['debug'] == 1) {
    echo '<h3>Session Data</h3><pre>';
    print_r($_SESSION);
    echo '</pre><h3>Post Data</h3><pre>';
    print_r($_POST);
    echo '</pre><h3>Cookie Data</h3><pre>';
    print_r($_COOKIE);
    echo'</pre>';
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
