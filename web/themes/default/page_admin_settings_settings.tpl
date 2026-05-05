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

                    <div class="card">
                        <div class="card__header"><div><h3>Authentication</h3><p>Token lifetimes (in minutes).</p></div></div>
                        <div class="card__body space-y-4">
                            <div class="grid gap-4" style="grid-template-columns:repeat(3,1fr)">
                                <div data-testid="setting-row" data-key="auth.maxlife">
                                    <label class="label" for="auth_maxlife">Default</label>
                                    <input class="input" type="number" min="0" id="auth_maxlife" name="auth_maxlife" value="{$auth_maxlife}">
                                </div>
                                <div data-testid="setting-row" data-key="auth.maxlife.remember">
                                    <label class="label" for="auth_maxlife_remember">Remember me</label>
                                    <input class="input" type="number" min="0" id="auth_maxlife_remember" name="auth_maxlife_remember" value="{$auth_maxlife_remember}">
                                </div>
                                <div data-testid="setting-row" data-key="auth.maxlife.steam">
                                    <label class="label" for="auth_maxlife_steam">Steam login</label>
                                    <input class="input" type="number" min="0" id="auth_maxlife_steam" name="auth_maxlife_steam" value="{$auth_maxlife_steam}">
                                </div>
                            </div>
                        </div>
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
                                        — Markdown supported (<a href="https://commonmark.org/help/" target="_blank" rel="noopener">CommonMark cheat-sheet</a>). Raw HTML is escaped on render.
                                    </span>
                                </label>
                                <textarea class="textarea" id="dash_intro_text" name="dash_intro_text" rows="10" style="width:100%;font-family:var(--font-mono);font-size:var(--fs-sm)">{$config_dash_text}</textarea>
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

                    <div class="flex items-center justify-end gap-2">
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
})();
{/literal}
</script>
