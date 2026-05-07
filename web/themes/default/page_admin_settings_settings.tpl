{*
    SourceBans++ 2026 — page / page_admin_settings_settings.tpl

    "Main settings" sub-tab on the admin Settings page. Pair:
    Sbpp\View\AdminSettingsView + web/pages/admin.settings.php (which
    routes by ?section=settings|features|logs|themes and renders one
    View per request — no AdminTabs JS tab-switching, no .tabcontent
    wrapper divs). The active section is reflected in the sub-nav at
    the top of every settings template via aria-current="page".

    Variable contract (kept in sync by SmartyTemplateRule):
        Permission gates:
            $can_web_settings — gates the entire body. Computed via
                Perms::for in admin.settings.php from
                ADMIN_OWNER|ADMIN_WEB_SETTINGS.
            $can_owner — kept for parity with sibling settings views;
                this template doesn't gate any UI on it directly but
                the View constructor requires it for cross-tab parity.
        Section nav: $active_section.
        Settings values: $config_title, $config_logo,
            $config_min_password, $config_dateformat,
            $config_dash_title, $config_dash_text, $auth_maxlife,
            $auth_maxlife_remember, $auth_maxlife_steam,
            $auth_maxlife_human, $auth_maxlife_remember_human,
            $auth_maxlife_steam_human (#1232 — server-rendered first
            paint for the per-input duration echoes; the page-tail JS
            mirrors `Sbpp\Util\Duration::humanizeMinutes()` so the
            spans update live as the operator types),
            $config_debug, $enable_submit, $enable_protest,
            $enable_commslist, $protest_emailonlyinvolved,
            $dash_lognopopup, $config_default_page,
            $config_bans_per_page, $banlist_hideadmname,
            $banlist_nocountryfetch, $banlist_hideplayerips,
            $bans_customreason (list), $config_smtp (tuple
            [host, user, port]), $config_smtp_verify_peer,
            $config_mail_from_email, $config_mail_from_name.

    Testability hooks:
        - Sub-nav <a> elements: data-testid="settings-tab-<key>"
          + aria-current="page" on the active tab.
        - Each <label>/<input> row: data-testid="setting-row" +
          data-key="<setting.key>" so end-to-end tests can target
          a setting by its persisted name (e.g. "template.title")
          without depending on form-input ordering.
        - Save button: data-testid="settings-save".

    Security note: dash.intro.text is rendered as a PLAIN <textarea>
    by design. The previous TinyMCE WYSIWYG was the source of #1113's
    stored-XSS — admins typed raw HTML, the dashboard emitted it via
    nofilter, every visitor executed it. The value now flows through
    Sbpp\Markup\IntroRenderer (CommonMark, html_input=escape,
    allow_unsafe_links=false) on render. Re-introducing TinyMCE,
    CKEditor, or any other HTML editor here re-opens the vector.
    Documented in AGENTS.md "Anti-patterns" + "Admin-authored display
    text". The help icon links to the CommonMark cheat-sheet so admins
    can discover the syntax without losing the safe-on-render contract.
*}
<div class="p-6">
    <div class="mb-6">
        <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">Settings</h1>
        <p class="text-sm text-muted m-0 mt-2">Site-wide configuration. Changes apply immediately.</p>
    </div>

    <div class="grid gap-4" style="grid-template-columns:14rem 1fr;align-items:start">
        <nav aria-label="Settings sections" role="tablist">
            <a class="sidebar__link" href="?p=admin&amp;c=settings&amp;section=settings"
               role="tab"
               data-testid="settings-tab-settings"
               {if $active_section == 'settings'}aria-current="page"{/if}>
                <i data-lucide="settings"></i> Main
            </a>
            <a class="sidebar__link" href="?p=admin&amp;c=settings&amp;section=features"
               role="tab"
               data-testid="settings-tab-features"
               {if $active_section == 'features'}aria-current="page"{/if}>
                <i data-lucide="toggle-right"></i> Features
            </a>
            <a class="sidebar__link" href="?p=admin&amp;c=settings&amp;section=logs"
               role="tab"
               data-testid="settings-tab-logs"
               {if $active_section == 'logs'}aria-current="page"{/if}>
                <i data-lucide="scroll-text"></i> System Log
            </a>
            <a class="sidebar__link" href="?p=admin&amp;c=settings&amp;section=themes"
               role="tab"
               data-testid="settings-tab-themes"
               {if $active_section == 'themes'}aria-current="page"{/if}>
                <i data-lucide="palette"></i> Themes
            </a>
        </nav>

        <div>
            {if NOT $can_web_settings}
                <div class="card">
                    <div class="card__body">
                        <p class="text-muted">Access denied. <code>ADMIN_WEB_SETTINGS</code> required.</p>
                    </div>
                </div>
            {else}
                <form action="?p=admin&amp;c=settings&amp;section=settings" method="post" class="space-y-4" id="form-settings-main">
                    {csrf_field}
                    <input type="hidden" name="settingsGroup" value="mainsettings">

                    <div class="card">
                        <div class="card__header"><div><h3>General</h3><p>Site identity, password rules, and dates.</p></div></div>
                        <div class="card__body space-y-4">
                            <div data-testid="setting-row" data-key="template.title">
                                <label class="label" for="template_title">Site title</label>
                                <input class="input" type="text" id="template_title" name="template_title" value="{$config_title}">
                            </div>
                            <div data-testid="setting-row" data-key="template.logo">
                                <label class="label" for="template_logo">Logo path</label>
                                <input class="input" type="text" id="template_logo" name="template_logo" value="{$config_logo}">
                            </div>
                            <div data-testid="setting-row" data-key="config.password.minlength">
                                <label class="label" for="config_password_minlength">Minimum password length</label>
                                <input class="input" type="number" min="1" id="config_password_minlength" name="config_password_minlength" value="{$config_min_password}">
                            </div>
                            <div data-testid="setting-row" data-key="config.dateformat">
                                <label class="label" for="config_dateformat">Date format <span class="text-muted text-xs">(<a href="https://www.php.net/manual/en/datetime.format.php" target="_blank" rel="noopener">PHP date()</a>)</span></label>
                                <input class="input" type="text" id="config_dateformat" name="config_dateformat" value="{$config_dateformat}">
                            </div>
                            <div data-testid="setting-row" data-key="config.debug">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="config_debug" name="config_debug"{if $config_debug} checked{/if}>
                                    <span class="text-sm font-medium">Enable debug mode</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {*
                        #1207 ADM-7: token-lifetime inputs stacked,
                        with per-input help.

                        Pre-fix shape: a 3-column `display: grid` with
                        the labels "Default", "Remember me",
                        "Steam login" side-by-side. The labels were
                        unequal width and the inputs themselves were
                        narrower than their labels at desktop
                        ("the input boxes are narrower than their
                        labels"); on mobile the grid collapsed to a
                        single column but the explanatory copy
                        ("Token lifetimes (in minutes)") stayed on
                        the card header so the user couldn't tell
                        which input the unit applied to.

                        Fix:

                          - Use a `<fieldset>` + `<legend>` so the
                            section heading is the form group's a11y
                            label (replacing the card-level `<h3>`
                            chrome — the outer `.card` chrome is kept
                            for visual consistency with sibling
                            settings sections).
                          - Each input lives in its own `<div
                            data-testid="setting-row">` with a
                            description paragraph below the input so
                            the unit ("in minutes") and the meaning
                            ("when the user ticks Remember me", "when
                            signed in via the Continue with Steam
                            button") sit next to the field they apply
                            to. The paragraphs are tied to the
                            inputs via `aria-describedby` so screen
                            readers announce them as the input's
                            description.
                          - The labels are renamed away from
                            "Default" / "Remember me" / "Steam login"
                            to "Default sign-in" / "\"Remember me\"
                            sign-in" / "Steam sign-in" so users
                            scanning the form don't have to read the
                            section heading to know what "Default"
                            applies to.
                          - The vertical stack layout works the same
                            on desktop and mobile, so the
                            help-text-detached-from-the-card-header
                            bug at <=768px goes away too.
                    *}
                    <div class="card">
                        <fieldset class="settings-fieldset"
                                  data-testid="settings-token-lifetimes">
                            <legend class="settings-fieldset__legend">
                                <span class="settings-fieldset__title">Authentication</span>
                                <span class="settings-fieldset__hint">Session token lifetimes, measured in minutes. Set a value to <code>0</code> to disable a sign-in path.</span>
                            </legend>
                            <div class="settings-fieldset__body space-y-5">
                                {*
                                    #1232: per-input duration echo.

                                    Each input gets a `data-duration-input`
                                    marker attribute and an adjacent muted
                                    span keyed by `data-duration-echo-for=
                                    "<input-id>"`. The span carries
                                    `aria-live="polite"` so screen-reader
                                    users hear the conversion as they edit.

                                    The span is server-rendered with the
                                    `*_human` props from `AdminSettingsView`
                                    (populated by
                                    `Sbpp\Util\Duration::humanizeMinutes()`
                                    in admin.settings.php) so the page works
                                    without JS. The page-tail
                                    `<script>` re-implements the same
                                    formula and updates the span on every
                                    `input` event — see the IIFE near the
                                    bottom of this template.

                                    The span sits between the input and the
                                    `.settings-fieldset__help` paragraph.
                                    On desktop the input is clamped at
                                    18rem (`.settings-fieldset__input`)
                                    so the inline span flows next to it
                                    on the same line; on mobile (<=768px)
                                    the input expands to 100% and the
                                    span wraps below — both shapes are
                                    fine because the span is short.
                                *}
                                <div data-testid="setting-row" data-key="auth.maxlife">
                                    <label class="label" for="auth_maxlife">Default sign-in</label>
                                    <input class="input settings-fieldset__input"
                                           type="number" min="0"
                                           id="auth_maxlife"
                                           name="auth_maxlife"
                                           value="{$auth_maxlife}"
                                           data-duration-input
                                           aria-describedby="auth_maxlife_help">
                                    <span class="text-muted text-xs"
                                          data-duration-echo-for="auth_maxlife"
                                          aria-live="polite">{$auth_maxlife_human}</span>
                                    <p class="settings-fieldset__help"
                                       id="auth_maxlife_help"
                                       data-testid="setting-help-auth.maxlife">
                                        How long a regular sign-in session lasts before the user is signed out, in minutes.
                                    </p>
                                </div>
                                <div data-testid="setting-row" data-key="auth.maxlife.remember">
                                    <label class="label" for="auth_maxlife_remember">&ldquo;Remember me&rdquo; sign-in</label>
                                    <input class="input settings-fieldset__input"
                                           type="number" min="0"
                                           id="auth_maxlife_remember"
                                           name="auth_maxlife_remember"
                                           value="{$auth_maxlife_remember}"
                                           data-duration-input
                                           aria-describedby="auth_maxlife_remember_help">
                                    <span class="text-muted text-xs"
                                          data-duration-echo-for="auth_maxlife_remember"
                                          aria-live="polite">{$auth_maxlife_remember_human}</span>
                                    <p class="settings-fieldset__help"
                                       id="auth_maxlife_remember_help"
                                       data-testid="setting-help-auth.maxlife.remember">
                                        Used when the user ticks the &ldquo;Remember me&rdquo; checkbox on the login form. Typically much longer than the default session.
                                    </p>
                                </div>
                                <div data-testid="setting-row" data-key="auth.maxlife.steam">
                                    <label class="label" for="auth_maxlife_steam">Steam sign-in</label>
                                    <input class="input settings-fieldset__input"
                                           type="number" min="0"
                                           id="auth_maxlife_steam"
                                           name="auth_maxlife_steam"
                                           value="{$auth_maxlife_steam}"
                                           data-duration-input
                                           aria-describedby="auth_maxlife_steam_help">
                                    <span class="text-muted text-xs"
                                          data-duration-echo-for="auth_maxlife_steam"
                                          aria-live="polite">{$auth_maxlife_steam_human}</span>
                                    <p class="settings-fieldset__help"
                                       id="auth_maxlife_steam_help"
                                       data-testid="setting-help-auth.maxlife.steam">
                                        Lifetime of a session opened via the &ldquo;Continue with Steam&rdquo; button.
                                    </p>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <div class="card">
                        <div class="card__header"><div><h3>Dashboard intro</h3><p>Public landing-page header and body.</p></div></div>
                        <div class="card__body space-y-4">
                            <div data-testid="setting-row" data-key="dash.intro.title">
                                <label class="label" for="dash_intro_title">Intro title</label>
                                <input class="input" type="text" id="dash_intro_title" name="dash_intro_title" value="{$config_dash_title}">
                            </div>
                            <div data-testid="setting-row" data-key="dash.intro.text">
                                <label class="label" for="dash_intro_text">
                                    Intro body
                                    <span class="text-muted text-xs">
                                        — Markdown supported
                                        (<a href="https://commonmark.org/help/"
                                            target="_blank"
                                            rel="noopener"
                                            data-testid="dash-intro-cheatsheet-link">CommonMark cheat-sheet</a>).
                                        Raw HTML is escaped on render.
                                    </span>
                                </label>
                                {*
                                    #1207 SET-1: live preview pane.

                                    The textarea on the left stays a plain
                                    <textarea> by design (re-introducing a
                                    WYSIWYG was the source of #1113 — see
                                    AGENTS.md "Anti-patterns"). The preview
                                    pane on the right calls the new
                                    `system.preview_intro_text` JSON action
                                    which pipes the value through
                                    `Sbpp\Markup\IntroRenderer` server-side,
                                    so the rendered HTML matches what the
                                    public dashboard would emit byte-for-byte.

                                    Layout: the grid drops to one column
                                    below 768px so the preview stacks
                                    underneath on mobile rather than
                                    crushing the textarea.

                                    The preview frame's `nofilter` is the
                                    only `nofilter` use here; safety
                                    annotation is on the `{$rendered}` line
                                    below for the initial server-rendered
                                    paint, and the JS-side update goes
                                    through `el.innerHTML = r.data.html`
                                    where `r.data.html` is the same
                                    IntroRenderer output (`html_input:
                                    'escape'`, `allow_unsafe_links:
                                    'false'`).
                                *}
                                <div class="dash-intro-editor"
                                     data-testid="dash-intro-editor">
                                    <textarea class="textarea"
                                              id="dash_intro_text"
                                              name="dash_intro_text"
                                              rows="10"
                                              style="width:100%;font-family:var(--font-mono);font-size:var(--fs-sm)"
                                              data-testid="dash-intro-textarea">{$config_dash_text}</textarea>
                                    <div class="dash-intro-preview"
                                         data-testid="dash-intro-preview"
                                         data-loading="false"
                                         aria-label="Markdown preview">
                                        <div class="dash-intro-preview__label">Preview</div>
                                        {*
                                            aria-live sits on the body (not the wrapper)
                                            so assistive tech announces only the rendered
                                            content on each keystroke — not the static
                                            "Preview" label above it.
                                        *}
                                        <div class="dash-intro-preview__body"
                                             data-testid="dash-intro-preview-body"
                                             aria-live="polite">
                                            {if $config_dash_text_preview != ''}
                                                {* nofilter: $config_dash_text_preview is `IntroRenderer::renderIntroText()` output (CommonMark + html_input=escape + allow_unsafe_links=false) — the same render path the public dashboard uses, see AGENTS.md "Admin-authored display text". *}
                                                {$config_dash_text_preview nofilter}
                                            {else}
                                                <p class="text-xs text-muted m-0" data-empty>Type Markdown on the left to see how it renders to visitors.</p>
                                            {/if}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div data-testid="setting-row" data-key="dash.lognopopup">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="dash_nopopup" name="dash_nopopup"{if $dash_lognopopup} checked{/if}>
                                    <span class="text-sm font-medium">Disable log popup (use direct links instead)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card__header"><div><h3>Public pages</h3><p>Which extra pages visitors can reach.</p></div></div>
                        <div class="card__body space-y-3">
                            <div data-testid="setting-row" data-key="config.enableprotest">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="enable_protest" name="enable_protest"{if $enable_protest} checked{/if}>
                                    <span class="text-sm font-medium">Enable ban-protest page</span>
                                </label>
                            </div>
                            <div data-testid="setting-row" data-key="config.enablesubmit">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="enable_submit" name="enable_submit"{if $enable_submit} checked{/if}>
                                    <span class="text-sm font-medium">Enable ban-submission page</span>
                                </label>
                            </div>
                            <div data-testid="setting-row" data-key="config.enablecomms">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="enable_commslist" name="enable_commslist"{if $enable_commslist} checked{/if}>
                                    <span class="text-sm font-medium">Enable comms list</span>
                                </label>
                            </div>
                            <div data-testid="setting-row" data-key="protest.emailonlyinvolved">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="protest_emailonlyinvolved" name="protest_emailonlyinvolved"{if $protest_emailonlyinvolved} checked{/if}>
                                    <span class="text-sm font-medium">Only email the original ban admin on new protests</span>
                                </label>
                            </div>
                            <div data-testid="setting-row" data-key="config.defaultpage">
                                <label class="label" for="default_page">Default landing page</label>
                                <select class="select" id="default_page" name="default_page">
                                    <option value="0"{if $config_default_page == 0} selected{/if}>Dashboard</option>
                                    <option value="1"{if $config_default_page == 1} selected{/if}>Ban list</option>
                                    <option value="2"{if $config_default_page == 2} selected{/if}>Servers</option>
                                    <option value="3"{if $config_default_page == 3} selected{/if}>Submit a ban</option>
                                    <option value="4"{if $config_default_page == 4} selected{/if}>Protest a ban</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card__header"><div><h3>Ban list</h3><p>Pagination and visibility.</p></div></div>
                        <div class="card__body space-y-4">
                            <div data-testid="setting-row" data-key="banlist.bansperpage">
                                <label class="label" for="banlist_bansperpage">Bans per page</label>
                                <input class="input" type="number" min="1" id="banlist_bansperpage" name="banlist_bansperpage" value="{$config_bans_per_page}">
                            </div>
                            <div data-testid="setting-row" data-key="banlist.hideadminname">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="banlist_hideadmname" name="banlist_hideadmname"{if $banlist_hideadmname} checked{/if}>
                                    <span class="text-sm font-medium">Hide admin name from public ban info</span>
                                </label>
                            </div>
                            <div data-testid="setting-row" data-key="banlist.hideplayerips">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="banlist_hideplayerips" name="banlist_hideplayerips"{if $banlist_hideplayerips} checked{/if}>
                                    <span class="text-sm font-medium">Hide player IPs from public ban info</span>
                                </label>
                            </div>
                            <div data-testid="setting-row" data-key="banlist.nocountryfetch">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="banlist_nocountryfetch" name="banlist_nocountryfetch"{if $banlist_nocountryfetch} checked{/if}>
                                    <span class="text-sm font-medium">Skip country lookup for IPs</span>
                                </label>
                            </div>
                            <div data-testid="setting-row" data-key="bans.customreasons">
                                <label class="label">Custom ban reasons</label>
                                <p class="text-xs text-muted m-0 mb-2">Each line becomes an option in the ban-reason dropdown. Leave blank to remove.</p>
                                <div id="custom-reasons" class="space-y-3">
                                    {foreach from=$bans_customreason item="creason"}
                                        {* nofilter: bans.customreasons round-trips through htmlspecialchars in admin.settings.php before serialize() into sb_settings, so the value is already entity-encoded; auto-escape would double-encode (matches legacy default theme). Admin-only input + already-escaped on store. *}
                                        <input class="input" type="text" name="bans_customreason[]" value="{$creason nofilter}">
                                    {/foreach}
                                    <input class="input" type="text" name="bans_customreason[]" placeholder="Add another reason…">
                                    <input class="input" type="text" name="bans_customreason[]" placeholder="Add another reason…">
                                </div>
                                <button type="button" class="btn btn--ghost btn--sm mt-2" onclick="addCustomReason();">
                                    <i data-lucide="plus"></i> Add row
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card__header"><div><h3>SMTP</h3><p>Outbound email transport.</p></div></div>
                        <div class="card__body space-y-4">
                            <div class="grid gap-4" style="grid-template-columns:2fr 1fr">
                                <div data-testid="setting-row" data-key="smtp.host">
                                    <label class="label" for="mail_host">Host</label>
                                    {* config_smtp[0]=host, [1]=user, [2]=port — the array shape mirrors Config::getMulti(['smtp.host','smtp.user','smtp.port']) so the legacy default theme can keep using the same View. *}
                                    <input class="input" type="text" id="mail_host" name="mail_host" value="{$config_smtp[0]}">
                                </div>
                                <div data-testid="setting-row" data-key="smtp.port">
                                    <label class="label" for="mail_port">Port</label>
                                    <input class="input" type="number" min="0" id="mail_port" name="mail_port" value="{$config_smtp[2]}">
                                </div>
                            </div>
                            <div class="grid gap-4" style="grid-template-columns:1fr 1fr">
                                <div data-testid="setting-row" data-key="smtp.user">
                                    <label class="label" for="mail_user">Username</label>
                                    <input class="input" type="text" id="mail_user" name="mail_user" value="{$config_smtp[1]}" autocomplete="off">
                                </div>
                                <div data-testid="setting-row" data-key="smtp.pass">
                                    <label class="label" for="mail_pass">Password <span class="text-muted text-xs">(leave blank to keep current)</span></label>
                                    <input class="input" type="password" id="mail_pass" name="mail_pass" value="" autocomplete="new-password">
                                </div>
                            </div>
                            <div data-testid="setting-row" data-key="smtp.verify_peer">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="mail_verify_peer" name="mail_verify_peer"{if $config_smtp_verify_peer} checked{/if}>
                                    <span class="text-sm font-medium">Verify TLS peer certificate</span>
                                </label>
                            </div>
                            <div class="grid gap-4" style="grid-template-columns:1fr 1fr">
                                <div data-testid="setting-row" data-key="config.mail.from_email">
                                    <label class="label" for="mail_from_email">From email</label>
                                    <input class="input" type="email" id="mail_from_email" name="mail_from_email" value="{$config_mail_from_email}">
                                </div>
                                <div data-testid="setting-row" data-key="config.mail.from_name">
                                    <label class="label" for="mail_from_name">From name</label>
                                    <input class="input" type="text" id="mail_from_name" name="mail_from_name" value="{$config_mail_from_name}">
                                </div>
                            </div>
                        </div>
                    </div>

                    {*
                        #1207 SET-2: `.settings-actions` keeps clear of
                        the page footer on mobile. The class adds a
                        bottom margin so the "Save changes" button no
                        longer reads as overlapping the
                        `<footer class="app-footer">` underneath.
                    *}
                    <div class="settings-actions flex items-center justify-end gap-2">
                        <button type="button" class="btn btn--secondary btn--sm" onclick="clearCacheBtn();">
                            <i data-lucide="brush"></i> Clear cache
                        </button>
                        <button type="submit" class="btn btn--primary" data-testid="settings-save">
                            <i data-lucide="save"></i> Save changes
                        </button>
                    </div>
                </form>
            {/if}
        </div>
    </div>
</div>

<script>
{literal}
// @ts-check
(function () {
    'use strict';

    /**
     * Append a blank custom-reason input next to the existing ones. The
     * server iterates `bans_customreason[]` order-independently and drops
     * empty entries, so users can grow the list freely without managing
     * indexes.
     */
    window.addCustomReason = function () {
        var box = document.getElementById('custom-reasons');
        if (!box) return;
        var inp = document.createElement('input');
        inp.className = 'input';
        inp.type = 'text';
        inp.name = 'bans_customreason[]';
        inp.placeholder = 'Add another reason…';
        box.appendChild(inp);
    };

    /**
     * Wire the "Clear cache" button to the existing system.clear_cache
     * JSON action. We purposely don't change the page (the user is mid-edit
     * on the form), just toast a confirmation.
     */
    window.clearCacheBtn = function () {
        if (!window.sb || !window.sb.api || !window.Actions) return;
        window.sb.api.call(window.Actions.SystemClearCache, {}).then(function () {
            window.alert('Cache cleared.');
        });
    };

    /**
     * #1207 SET-1: live Markdown preview.
     *
     * Debounced input handler that POSTs the textarea's current value
     * to `system.preview_intro_text`, receives the rendered HTML back
     * (rendered by `Sbpp\Markup\IntroRenderer` — same path the public
     * dashboard uses), and patches the preview pane in place. The
     * `[data-loading]` flips to `true` while a call is in flight so
     * the contract from `_base.ts`'s `waitForReady()` (data-loading
     * settles to "false" before tests proceed) keeps holding.
     *
     * The first render comes from PHP (see the `{if
     * $config_dash_text|trim != ''}` block above); JS only kicks in
     * once the user starts typing, so the page works without JS too —
     * the saved-form bounce flow re-renders the page with fresh PHP
     * markup either way.
     */
    var ta = /** @type {HTMLTextAreaElement|null} */ (document.getElementById('dash_intro_text'));
    var preview = /** @type {HTMLElement|null} */ (
        document.querySelector('[data-testid="dash-intro-preview"]')
    );
    var previewBody = /** @type {HTMLElement|null} */ (
        document.querySelector('[data-testid="dash-intro-preview-body"]')
    );

    if (ta && preview && previewBody && window.sb && window.sb.api && window.Actions) {
        var emptyHtml = '<p class="text-xs text-muted m-0" data-empty>Type Markdown on the left to see how it renders to visitors.</p>';

        /** @type {number|null} */
        var pending = null;
        /** @type {string} */
        var lastSent = ta.value;

        function refresh() {
            if (!ta || !preview || !previewBody) return;
            var current = ta.value;
            if (current === lastSent && pending === null) return;
            lastSent = current;
            if (current.trim() === '') {
                previewBody.innerHTML = emptyHtml;
                preview.setAttribute('data-loading', 'false');
                return;
            }
            preview.setAttribute('data-loading', 'true');
            window.sb.api.call(window.Actions.SystemPreviewIntroText, { markdown: current }).then(function (r) {
                if (!preview || !previewBody) return;
                preview.setAttribute('data-loading', 'false');
                if (r && r.ok && r.data && typeof r.data.html === 'string') {
                    // r.data.html is IntroRenderer output (CommonMark
                    // with html_input=escape, allow_unsafe_links=false);
                    // safe to drop in via innerHTML for the same reason
                    // the server-rendered first paint above is safe
                    // behind `nofilter`. Caveat: do NOT swap this for
                    // a third-party Markdown library — see AGENTS.md
                    // "Admin-authored display text" for the contract.
                    previewBody.innerHTML = r.data.html;
                }
            }, function () {
                if (preview) preview.setAttribute('data-loading', 'false');
            });
        }

        ta.addEventListener('input', function () {
            if (pending !== null) window.clearTimeout(pending);
            pending = window.setTimeout(function () {
                pending = null;
                refresh();
            }, 200);
        });
    }

    /**
     * #1232: live "minutes -> human" echo for the Authentication
     * fieldset. Mirrors `Sbpp\Util\Duration::humanizeMinutes()` (PHP)
     * so the muted span next to each `[data-duration-input]` updates
     * as the operator types. The first paint is server-rendered (see
     * `$auth_maxlife_human` etc. in the template), so this block only
     * runs when the user actually edits a value.
     *
     * @param {number} minutes
     * @returns {string}
     */
    function humanizeMinutes(minutes) {
        if (!Number.isFinite(minutes) || minutes <= 0) return 'disabled';
        var n = Math.floor(minutes);
        if (n < 60) return n === 1 ? '1 minute' : n + ' minutes';
        if (n < 1440) {
            if (n % 60 === 0) {
                var h = n / 60;
                return h === 1 ? '1 hour' : h + ' hours';
            }
            var hStr = trimZero(n / 60);
            return '\u2248 ' + hStr + ' ' + (hStr === '1' ? 'hour' : 'hours');
        }
        if (n % 1440 === 0) {
            var d = n / 1440;
            return d === 1 ? '1 day' : d + ' days';
        }
        var dStr = trimZero(n / 1440);
        return '\u2248 ' + dStr + ' ' + (dStr === '1' ? 'day' : 'days');
    }

    /**
     * Format a number to one decimal and drop a trailing `.0` so values
     * that round to a whole number render without a redundant decimal.
     * Mirrors the PHP helper's `trimZero()`.
     *
     * @param {number} value
     * @returns {string}
     */
    function trimZero(value) {
        var s = (Math.round(value * 10) / 10).toFixed(1);
        return s.charAt(s.length - 1) === '0' && s.charAt(s.length - 2) === '.'
            ? s.slice(0, -2)
            : s;
    }

    var durationInputs = /** @type {NodeListOf<HTMLInputElement>} */ (
        document.querySelectorAll('[data-duration-input]')
    );
    durationInputs.forEach(function (input) {
        var echo = /** @type {HTMLElement|null} */ (
            document.querySelector('[data-duration-echo-for="' + input.id + '"]')
        );
        if (!echo) return;
        input.addEventListener('input', function () {
            // Empty input -> treat as 0 (matches the "disabled" sentinel
            // the PHP helper returns; the `<input type=number>` clears
            // to "" rather than "0" on backspace).
            var raw = input.value.trim();
            var n = raw === '' ? 0 : parseInt(raw, 10);
            echo.textContent = humanizeMinutes(Number.isNaN(n) ? 0 : n);
        });
    });
})();
{/literal}
</script>
