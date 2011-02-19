
        </div>
      </div>
      <div id="footer">
        <div id="gc">
          By <a class="footer_link" href="http://www.interwavestudios.com" target="_blank">InterWave Studios</a>
        </div>
        <div id="sb">
          <br />
          <a href="http://www.sourcebans.net" target="_blank"><img alt="SourceBans" border="0" src="../images/sb.png" /></a>
          <br />
          <div id="footqversion">Version <?php echo SB_VERSION ?></div>
          <div id="footquote"><?php echo Quote() ?></div>
        </div>
        <div id="sm">
          Powered by <a class="footer_link" href="http://www.sourcemod.net" target="_blank">SourceMod</a>
        </div>
      </div>
<?php if(isset($_GET['debug']) && $_GET['debug'] == 1): ?>
      <h3>Session Data</h3>
      <pre><?php print_r($_SESSION) ?></pre>
      <h3>Post Data</h3>
      <pre><?php print_r($_POST) ?></pre>
      <h3>Cookie Data</h3>
      <pre><?php print_r($_COOKIE) ?></pre>
<?php endif ?>
    </div>
    <script type="text/javascript">
      window.addEvent('domready', function() {
        var Tips2 = new Tips($$('.tip'), {
          initialize: function() {
            this.fx = new Fx.Style(this.toolTip, 'opacity', {
              duration: 300,
              wait: false
            }).set(0);
          },
          onHide: function(toolTip) {
            this.fx.start(0);
          },
          onShow: function(toolTip) {
            this.fx.start(1);
          }
        });
        var Tips4 = new Tips($$('.perm'), {
          className: 'perm'
        });
      });
    </script>
    <!--[if lt IE 7]>
    <script defer type="text/javascript" src="../scripts/pngfix.js"></script>
    <![endif]-->
  </body>
</html>