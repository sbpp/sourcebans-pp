
        </div>
      </div>
      <div id="footer">
        <p class="iw">{$language->by} <a href="http://www.interwavestudios.com" rel="nofollow">InterWave Studios</a></p>
        <div class="sb">
          <a href="http://www.sourcebans.net" rel="nofollow"><img alt="SourceBans" src="{$uri->base}/images/sb.png" title="SourceBans" /></a>
          <p>{$language->version} {$sb_version}</p>
          <p>"{eval var=$sb_quote.text}" - <em>{$sb_quote.name}</em></p>
          <p><br />Generated in {$gen_time|round:2} seconds.</p>
        </div>
        <p class="sm">{$language->powered_by} <a href="http://www.sourcemod.net" rel="nofollow">SourceMod</a></p>
      </div>
    </div>
  </body>
</html>