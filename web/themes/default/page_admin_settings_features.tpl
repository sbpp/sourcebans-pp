{*
    SourceBans++ 2026 — page / page_admin_settings_features.tpl

    "Features" sub-tab on the admin Settings page. Pair:
    Sbpp\View\AdminFeaturesView + web/pages/admin.settings.php (which
    routes by ?section= and renders one View per request — see
    sibling page_admin_settings_settings.tpl for the rationale).

    #1259 — sidebar lifted into a shared partial: the inline
    `<nav>` block + `grid-template-columns:14rem 1fr` shell that
    used to wrap this template's content is now driven by
    `core/admin_sidebar.tpl` via `web/includes/View/AdminTabs.php`. The
    page handler (admin.settings.php) opens the shell BEFORE this
    template renders. See AGENTS.md "Sub-paged admin routes".

    #1256 — row anatomy aligned with Settings → Main
    (page_admin_settings_settings.tpl). Each toggle row is now
    `<div data-testid="setting-row" data-key="…"> <label
    class="flex items-center gap-2"><input><span class="text-sm
    font-medium"></span></label> <p
    class="settings-fieldset__help" id="…_help"
    data-testid="setting-help-…">…</p> </div>` — the label-pair
    shape Settings → Main uses for its inline checkboxes (e.g.
    `config.debug`), with the description copy promoted to a
    sibling `<p>` paragraph wired via `aria-describedby` so screen
    readers announce it as the input's description (the same wiring
    Settings → Main's auth/token-lifetime block — #1207 ADM-7 —
    uses for its number inputs). The pre-#1256 inline
    `style="border:…;border-radius:…"` was dropped: the outer
    `.card` is the only chrome now, and the per-row borders that
    painted the card-in-card double-bordered look are gone. Card
    body rhythm bumped from `space-y-3` to `space-y-4` to match
    Settings → Main's card-body spacing now that the rows have a
    sibling paragraph beneath the label-pair. The native checkbox
    paint is unified panel-wide via the global
    `input[type="checkbox"]` rule in
    `web/themes/default/css/theme.css` (also #1256).

    Variable contract (kept in sync by SmartyTemplateRule):
        Permission gates:
            $can_web_settings — gates the entire body.
            $can_owner — currently unused in this section but kept
                across all settings views for parity.
        Toggles: $export_public, $enable_kickit,
            $enable_groupbanning, $enable_friendsbanning,
            $enable_adminrehashing, $enable_steamlogin,
            $enable_normallogin, $enable_publiccomments.
        Steam Web API key probe: $steamapi (true when STEAMAPIKEY
            is defined and non-empty; gates Group/Friends banning
            inputs the same way the legacy theme did).

    Testability hooks:
        - Sidebar links: data-testid="admin-tab-<slug>" (#1259 — the
          legacy `settings-tab-<slug>` was renamed to the cross-page
          `admin-tab-<slug>` shape now that the chrome is shared with
          servers / mods / groups).
        - Each toggle row: data-testid="setting-row" + data-key="<key>".
        - Each help paragraph: data-testid="setting-help-<key>" (#1256).
        - Save button: data-testid="settings-save".

    #1266 — outer `.p-6` removed; the page inset lives on
    `.admin-sidebar-shell` so both grid columns share the same top y.
*}
<div>
    <div class="mb-6">
        <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">Settings</h1>
        <p class="text-sm text-muted m-0 mt-2">Optional features and integrations.</p>
    </div>

    {if NOT $can_web_settings}
        <div class="card">
            <div class="card__body">
                <p class="text-muted">Access denied. <code>ADMIN_WEB_SETTINGS</code> required.</p>
            </div>
        </div>
    {else}
                <form action="?p=admin&amp;c=settings&amp;section=features" method="post" class="space-y-4">
                    {csrf_field}
                    <input type="hidden" name="settingsGroup" value="features">

                    <div class="card">
                        <div class="card__header"><div><h3>Bans</h3><p>Public exports, KickIt, group / friend banning.</p></div></div>
                        <div class="card__body space-y-4">
                            <div data-testid="setting-row" data-key="config.exportpublic">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="export_public" name="export_public"{if $export_public} checked{/if} aria-describedby="export_public_help">
                                    <span class="text-sm font-medium">Public ban export</span>
                                </label>
                                <p class="settings-fieldset__help" id="export_public_help" data-testid="setting-help-config.exportpublic">
                                    Lets unauthenticated visitors download the full ban list.
                                </p>
                            </div>

                            <div data-testid="setting-row" data-key="config.enablekickit">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="enable_kickit" name="enable_kickit"{if $enable_kickit} checked{/if} aria-describedby="enable_kickit_help">
                                    <span class="text-sm font-medium">KickIt</span>
                                </label>
                                <p class="settings-fieldset__help" id="enable_kickit_help" data-testid="setting-help-config.enablekickit">
                                    Auto-kick a player when their ban lands.
                                </p>
                            </div>

                            <div data-testid="setting-row" data-key="config.enablegroupbanning">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="enable_groupbanning" name="enable_groupbanning"{if $enable_groupbanning} checked{/if}{if NOT $steamapi} disabled{/if} aria-describedby="enable_groupbanning_help">
                                    <span class="text-sm font-medium">Steam group banning</span>
                                </label>
                                <p class="settings-fieldset__help" id="enable_groupbanning_help" data-testid="setting-help-config.enablegroupbanning">
                                    Ban every member of a Steam community group.
                                    {if NOT $steamapi}<br><span style="color:var(--warning)">Requires a Steam Web API key in <code>config.php</code>.</span>{/if}
                                </p>
                            </div>

                            <div data-testid="setting-row" data-key="config.enablefriendsbanning">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="enable_friendsbanning" name="enable_friendsbanning"{if $enable_friendsbanning} checked{/if}{if NOT $steamapi} disabled{/if} aria-describedby="enable_friendsbanning_help">
                                    <span class="text-sm font-medium">Steam friends banning</span>
                                </label>
                                <p class="settings-fieldset__help" id="enable_friendsbanning_help" data-testid="setting-help-config.enablefriendsbanning">
                                    Ban every Steam friend of a player.
                                    {if NOT $steamapi}<br><span style="color:var(--warning)">Requires a Steam Web API key in <code>config.php</code>.</span>{/if}
                                </p>
                            </div>

                            <div data-testid="setting-row" data-key="config.enableadminrehashing">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="enable_adminrehashing" name="enable_adminrehashing"{if $enable_adminrehashing} checked{/if} aria-describedby="enable_adminrehashing_help">
                                    <span class="text-sm font-medium">Auto admin rehash</span>
                                </label>
                                <p class="settings-fieldset__help" id="enable_adminrehashing_help" data-testid="setting-help-config.enableadminrehashing">
                                    Push admin/group changes to servers immediately.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card__header"><div><h3>Login</h3><p>Sign-in surfaces exposed to admins.</p></div></div>
                        <div class="card__body space-y-4">
                            <div data-testid="setting-row" data-key="config.enablesteamlogin">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="enable_steamlogin" name="enable_steamlogin"{if $enable_steamlogin} checked{/if} aria-describedby="enable_steamlogin_help">
                                    <span class="text-sm font-medium">Steam OpenID login</span>
                                </label>
                                <p class="settings-fieldset__help" id="enable_steamlogin_help" data-testid="setting-help-config.enablesteamlogin">
                                    Show "Sign in through Steam" on the login page.
                                </p>
                            </div>

                            <div data-testid="setting-row" data-key="config.enablenormallogin">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="enable_normallogin" name="enable_normallogin"{if $enable_normallogin} checked{/if} aria-describedby="enable_normallogin_help">
                                    <span class="text-sm font-medium">Username/password login</span>
                                </label>
                                <p class="settings-fieldset__help" id="enable_normallogin_help" data-testid="setting-help-config.enablenormallogin">
                                    Disable to require Steam login for all admins.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card__header"><div><h3>Comments</h3><p>Visibility of admin commentary on bans / submissions.</p></div></div>
                        <div class="card__body space-y-4">
                            <div data-testid="setting-row" data-key="config.enablepubliccomments">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="enable_publiccomments" name="enable_publiccomments"{if $enable_publiccomments} checked{/if} aria-describedby="enable_publiccomments_help">
                                    <span class="text-sm font-medium">Public admin comments</span>
                                </label>
                                <p class="settings-fieldset__help" id="enable_publiccomments_help" data-testid="setting-help-config.enablepubliccomments">
                                    Show admin comments on a ban to anonymous visitors.
                                </p>
                            </div>
                        </div>
                    </div>

                    {*
                        #1126 — anonymous opt-out telemetry. Default-on; the
                        help paragraph below is the only in-panel disclosure
                        surface (no first-login modal — see issue body), so
                        the copy summarises every payload category and links
                        to README.md's `## Privacy & telemetry` section for
                        the field-by-field breakdown. Tone is matter-of-fact;
                        no marketing, no apology copy.

                        On opt-out (toggle 1 → 0), admin.settings.php's POST
                        handler clears `telemetry.instance_id` so a re-enable
                        mints a fresh ID and the Worker can't link the two
                        states. Enable / disable transitions are also audit-
                        logged once via `Log::add(LogType::Message, ...)`.
                    *}
                    <div class="card">
                        <div class="card__header"><div><h3>Privacy</h3><p>Anonymous telemetry that helps us prioritise releases.</p></div></div>
                        <div class="card__body space-y-4">
                            <div data-testid="setting-row" data-key="telemetry.enabled">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" id="telemetry_enabled" name="telemetry_enabled"{if $telemetry_enabled} checked{/if} aria-describedby="telemetry_enabled_help">
                                    <span class="text-sm font-medium">Anonymous telemetry</span>
                                </label>
                                <p class="settings-fieldset__help" id="telemetry_enabled_help" data-testid="setting-help-telemetry.enabled">
                                    Sends one anonymous ping per day to a SourceBans++ collector
                                    so maintainers can see what versions, environments, and feature
                                    toggles are in real-world use. The payload covers four categories:
                                    panel (version, git SHA, dev flag, theme — <code>default</code> or
                                    <code>custom</code>); environment (PHP <code>major.minor</code>,
                                    DB engine + <code>major.minor</code>, web server family, OS family);
                                    scale (counts of admins, enabled servers, active and total bans /
                                    comms, and 30-day submissions / protests); and feature toggles
                                    (every checkbox above plus SMTP-configured / Steam-API-key-set /
                                    GeoIP-present yes/no). A random per-install ID is included so
                                    pings can be deduplicated; <strong>no</strong> hostnames, IPs,
                                    admin names, SteamIDs, or ban reasons are ever sent.
                                    <a href="https://github.com/sbpp/sourcebans-pp/blob/main/README.md#privacy--telemetry" target="_blank" rel="noopener noreferrer">
                                        Read the full field list and SQL behind each count
                                    </a>.
                                    Disabling clears the random ID, so re-enabling later issues
                                    a fresh one.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2">
                        <button type="submit" class="btn btn--primary" data-testid="settings-save">
                            <i data-lucide="save"></i> Save changes
                        </button>
                    </div>
                </form>
    {/if}
</div>
