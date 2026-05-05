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
    state. The C1/C2 tickets wire theme.js (vendored at A1) to
    populate the inner markup and toggle visibility via
    sb.api.call(Actions.BansDetail / Actions.BansSearch).

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

{* Palette scaffold — C2 wires inner markup via sb.api.call(Actions.BansSearch). *}
<dialog id="palette-root"
        aria-label="Command palette"
        hidden></dialog>

<footer class="text-xs text-faint" style="text-align:center;padding:1rem 0">
    <a href="https://sbpp.github.io/" target="_blank" rel="noopener">SourceBans++</a>
    {$version}{$git}
</footer>

<script src="{$theme_url}/js/lucide.min.js"></script>
<script src="{$theme_url}/js/theme.js"></script>

</body>
</html>
