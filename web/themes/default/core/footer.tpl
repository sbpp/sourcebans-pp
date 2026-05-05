{*
    SourceBans++ 2026 — chrome / footer.tpl

    Closes <main>, .main, .app, then mounts the drawer + palette
    scaffolds and loads vendored Lucide + theme.js. Pair:
    web/pages/core/footer.php (assigns $version, $git, $query — same
    contract as web/themes/default/core/footer.tpl).

    The drawer + palette scaffolds use semantic <aside> + <dialog> with
    role/aria-label per the issue's "Testability hooks" rule that
    "state belongs in attributes, not just styling" — both ship with
    the `hidden` boolean attribute as the deterministic terminal
    state. C1 wires the drawer to sb.api.call(Actions.BansDetail);
    C2 (this PR) wires the palette input + result list to
    sb.api.call(Actions.BansSearch). The `data-palette-open`
    attribute mirrors the dialog's open/closed state for tests +
    CSS so selectors don't have to probe the `hidden` property.

    Legacy hooks intentionally NOT carried over from
    web/themes/default/core/footer.tpl:
      - {$query nofilter}: emits LoadServerHostProperty() calls that
        live in web/scripts/sourcebans.js; the new theme drops that
        bulk file (#1123 D1) so the calls would error. B3 will
        re-implement the live-server widget via sb.api.call.
      - sb.ready/tabs.init/tooltip: legacy MooTools-flavored helpers
        replaced by theme.js's vanilla wiring.
    Footer credits ($version + $git) are kept — pure display, no JS.
*}
    </main>{* /.page *}
  </div>{* /.main *}
</div>{* /.app *}

{* Drawer scaffold — C1 wires inner markup via sb.api.call(Actions.BansDetail). *}
<aside id="drawer-root"
       role="complementary"
       aria-label="Player details"
       data-drawer-open="false"
       hidden></aside>

{*
    Palette dialog (#1123 C2). theme.js owns the open/close lifecycle:
      - Cmd+K / Ctrl+K toggles the dialog (showModal/close).
      - data-palette-open="true|false" mirrors the open state for tests.
      - data-loading="true" is set on the dialog while bans.search is in
        flight so themes can render a spinner without hooking into JS.
    The dialog stays `hidden` on first paint so a JS failure leaves the
    palette unreachable rather than half-rendered. Class names mirror
    what theme.css ships (`.palette` on the dialog, `.palette__input`
    on the input row, `.sidebar__link` on result rows); no new CSS
    classes are introduced — see C1's drawer for the same "JS owns the
    lifecycle, CSS owns the look" split.
*}
<dialog id="palette-root"
        class="palette"
        aria-label="Command palette"
        data-palette-open="false"
        hidden>
    <div class="palette__input">
        <i data-lucide="search" aria-hidden="true" style="width:14px;height:14px;color:var(--text-faint)"></i>
        <input id="palette-input"
               type="search"
               autocomplete="off"
               spellcheck="false"
               placeholder="Search players, SteamIDs, pages…"
               aria-label="Search players, SteamIDs, pages"
               data-testid="palette-input">
        <button type="button"
                class="btn--ghost btn--icon"
                data-palette-close
                data-testid="palette-close"
                aria-label="Close command palette"
                style="font-size:0.7rem">
            <kbd>Esc</kbd>
        </button>
    </div>
    <div id="palette-results"
         role="listbox"
         aria-label="Search results"
         data-testid="palette-results"
         style="max-height:60vh;overflow-y:auto;padding:0.5rem"></div>
</dialog>

<footer class="text-xs text-faint" style="text-align:center;padding:1rem 0">
    <a href="https://sbpp.github.io/" target="_blank" rel="noopener">SourceBans++</a>
    {$version}{$git}
</footer>

<script src="{$theme_url}/js/lucide.min.js"></script>
<script src="{$theme_url}/js/theme.js"></script>

</body>
</html>
