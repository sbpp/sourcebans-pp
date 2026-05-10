{*
    SourceBans++ 2026 — chrome / footer.tpl

    Closes <main> + the page footer + .main + .app, then mounts the
    drawer + palette scaffolds and loads vendored Lucide + theme.js.
    Pair: web/pages/core/footer.php (assigns $version, $git, $query —
    same contract as web/themes/default/core/footer.tpl).

    Layout note (#1271 — actual fix): `<footer class="app-footer">`
    lives INSIDE `<div class="app">` (specifically as the last flex
    item of `<div class="main">`, after `</main>`). `.sidebar` is
    `position: sticky; top: 0; height: 100vh` and its sticky
    containing block is `.app`; if the footer were a sibling of `.app`
    (the pre-fix shape), `.app`'s height would fall short of the
    document by `footerHeight` and the sticky sidebar would release at
    the bottom — brand cut off, on barely-tall pages the entire
    scroll range would be in the release phase and the sidebar would
    appear to track the scroll. Keeping the footer inside `.app` makes
    the sticky containing block extend through the whole document so
    `.sidebar` stays pinned at viewport y=0 to the very bottom. See
    `responsive/sidebar-sticky.spec.ts` for the regression guard +
    AGENTS.md "Where to find what".

    The drawer + palette scaffolds use semantic <aside> + <dialog> with
    role/aria-label per the issue's "Testability hooks" rule that
    "state belongs in attributes, not just styling" — both ship with
    the `hidden` boolean attribute as the deterministic terminal
    state. C1 wires the drawer to sb.api.call(Actions.BansDetail);
    C2 (this PR) wires the palette input + result list to
    sb.api.call(Actions.BansSearch). The `data-palette-open`
    attribute mirrors the dialog's open/closed state for tests +
    CSS so selectors don't have to probe the `hidden` property.
    Both are intentionally siblings of `.app` (not nested inside)
    because they're conceptually top-layer overlays — the drawer is
    `position: fixed; right: 0; top: 0; height: 100%` (right-pinned
    panel, not full-bleed; `inset: 0` is on the separate
    `.drawer-backdrop`) and `<dialog>` promotes to the top layer
    when `showModal()`-ed. Keeping them outside `.app` is
    defensiveness against the CSS containing-block scoping rules:
    a future refactor that declares `transform` / `filter` /
    `contain: layout` on `.app` (or any descendant in the drawer's
    would-be ancestry) would re-establish the containing block for
    `position: fixed` descendants, suddenly positioning the drawer
    relative to that ancestor instead of the viewport. The
    structural-fix concern that motivated #1271 (sidebar's sticky
    CB short of the document) does NOT apply to the drawer —
    `position: fixed` removes the element from flow, so the drawer
    cannot grow `.app`'s height regardless of where it nests.

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

{*
    Footer (#1207 CC-5, CC-6, SET-2; #1271 layout fix — see file-level
    comment above for why it lives here, inside `.main`/`.app`, instead
    of as a body-level sibling of `.app`).

    `data-version="{$version}"` mirrors the resolved SB_VERSION constant
    (the user-visible string minus the `| Git: …` suffix). Telemetry,
    bug reports, and E2E specs key off the attribute so they can
    distinguish dev installs (`data-version="dev"` — the third-tier
    fallback in init.php) from release tarball installs
    (`data-version="2.1.0"` etc.) without parsing the visible text.

    `data-testid="app-footer"` is the testability hook for SET-2's
    save-button-doesn't-overlap-footer assertion at mobile width.

    Footer link points at the sbpp/sourcebans-pp repo (CC-6) instead of
    the marketing site so a self-hoster's first instinct ("show me the
    code") lands them on the place that hosts both the issue tracker
    and the install instructions. Link styling is muted by default
    (matches the surrounding footer text colour) and only reveals the
    accent colour + underline on `:hover` / `:focus-visible` so the
    chrome's footer reads as a single line of text instead of having
    a stranded blue underlined word in the middle of it. The
    `.app-footer` class adds the SET-2 separator (top border + extra
    margin/padding) so the "Save changes" row above no longer reads
    as overlapping the credit on mobile.
*}
    <footer class="app-footer sbpp-footer" data-version="{$version}" data-testid="app-footer">
        <a href="https://github.com/sbpp/sourcebans-pp" target="_blank" rel="noopener">SourceBans++</a>
        {$version}{$git}
    </footer>
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

{*
    Issue #1304: server-render the palette's "Navigate" entries as a
    JSON blob theme.js reads at boot. Pre-fix the entry list lived as
    a hardcoded `NAV_ITEMS` array in `web/themes/default/js/theme.js`
    with no permission check, leaking admin entries (`Admin panel`,
    `Add ban`) to logged-out + partial-permission users (clicking either
    landed them on the "you must be logged in" / 403 surface).

    The blob is built by `Sbpp\View\PaletteActions::for($userbank)` in
    `web/pages/core/footer.php` and assigned as `$palette_actions_json`.
    The encoder uses JSON_HEX_TAG / _AMP / _APOS / _QUOT so the content
    can never break out of the surrounding `<script>` element regardless
    of what a future label / href adds (`</script>` would otherwise let
    the blob escape its container — defense-in-depth on top of the
    catalog being all-ASCII today).

    `<script type="application/json">` is the standards-blessed shape
    for embedded data: browsers don't execute the body, JSON.parse
    consumes it as text content. theme.js falls back to an empty list
    (palette renders only the player-search half) when the blob is
    absent, so a chrome-only render in test contexts doesn't crash.

    `data-testid="palette-actions"` is the testability hook the e2e
    spec anchors on instead of probing the script element by tag.
*}
{* nofilter: server-encoded JSON from PaletteActions::for($userbank) using JSON_HEX_TAG|_AMP|_APOS|_QUOT — every potentially-dangerous char (<>&'") is escaped as a \uXXXX sequence, so the blob can't break out of its <script> wrapper or carry HTML entities that would corrupt JSON.parse. *}
<script type="application/json" id="palette-actions" data-testid="palette-actions">{$palette_actions_json nofilter}</script>

<script src="{$theme_url}/js/lucide.min.js"></script>
<script src="{$theme_url}/js/theme.js"></script>

</body>
</html>
