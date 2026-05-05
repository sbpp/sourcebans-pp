{* ============================================================
   footer.tpl — closes layout shell, mounts drawer + palette
   ============================================================ *}
    </main>{* /.page *}
  </div>{* /.main *}
</div>{* /.app *}

{* ---- Drawer scaffold (filled by JS) ---- *}
<div id="drawer-root" hidden></div>

{* ---- Command palette scaffold ---- *}
<div id="palette-root" hidden>
  <div class="palette-backdrop" data-palette-close>
    <div class="palette" role="dialog" aria-label="Command palette" onclick="event.stopPropagation()">
      <div class="palette__input">
        <i data-lucide="search"></i>
        <input type="search" placeholder="Search players, SteamIDs, pages…" id="palette-input">
        <kbd style="font-family:var(--font-mono);font-size:0.625rem;color:var(--text-muted)">esc</kbd>
      </div>
      <div id="palette-results" style="max-height:26rem;overflow-y:auto;padding:0.5rem"></div>
    </div>
  </div>
</div>

</body>
</html>
