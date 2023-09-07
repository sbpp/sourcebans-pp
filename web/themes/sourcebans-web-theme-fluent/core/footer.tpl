	<!-- </div> -->
	</div>
	</main>
	<footer class="footer">
	  <div class="layout_container flex flex-jc:space-between flex-ai:center">
	    <div class="flex flex-fd:column text:left">
	      <a href="https://sbpp.github.io/" target="_blank" rel="noopener">SourceBans++</a> {$version}{$git}
	      <span>Powered by <a href="https://www.sourcemod.net" target="_blank" rel="noopener">SourceMod</a></span>
	    </div>
	    <div class="flex flex-fd:column text:right">
	      <span>Copyright © (website name)</span>
	      <span><i class="fas fa-code"></i> Original <a href="https://github.com/Rushaway/sourcebans-web-theme-fluent" title="Theme Fluent for SourceBans++" target="_blank" rel="noopener">Theme</a> by <a href="https://axendev.net/" title="Theme by aXenDev" target="_blank" rel="noopener">aXenDev</a></span>
	    </div>
	  </div>
	</footer>

	<script type="text/javascript" src="themes/{$theme}/scripts/nav.js"></script>
	<script type="text/javascript" src="themes/{$theme}/scripts/jscolor.min.js"></script>
	<script type="text/javascript" src="themes/{$theme}/scripts/theme.js"></script>

	<script>
	  {$query}

	  {literal}
  	  window.addEvent('domready', function() {

  	    ProcessAdminTabs();

  	    var Tips2 = new Tips($('.tip'), {
  	      initialize: function() {
  	        this.fx = new Fx.Style(this.toolTip, 'opacity', {duration: 300, wait: false}).set(0);
  	      },
  	      onShow: function(toolTip) {
  	        this.fx.start(1);
  	      },
  	      onHide: function(toolTip) {
  	        this.fx.start(0);
  	      }
  	    });
  	    var Tips4 = new Tips($('.perm'), {
  	      className: 'perm'
  	    });
  	  });
	  {/literal}
	</script>
	</body>

	</html>