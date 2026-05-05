{*
    SourceBans++ 2026 — page / page_admin_settings_features.tpl

    "Features" sub-tab on the admin Settings page. Pair:
    Sbpp\View\AdminFeaturesView + web/pages/admin.settings.php (which
    routes by ?section= and renders one View per request — see
    sibling page_admin_settings_settings.tpl for the rationale).

    Variable contract (kept in sync by SmartyTemplateRule):
        Permission gates:
            $can_web_settings — gates the entire body.
            $can_owner — currently unused in this section but kept
                across all settings views for parity.
        Section nav: $active_section.
        Toggles: $export_public, $enable_kickit,
            $enable_groupbanning, $enable_friendsbanning,
            $enable_adminrehashing, $enable_steamlogin,
            $enable_normallogin, $enable_publiccomments.
        Steam Web API key probe: $steamapi (true when STEAMAPIKEY
            is defined and non-empty; gates Group/Friends banning
            inputs the same way the legacy theme did).

    Testability hooks:
        - Sub-nav links: data-testid="settings-tab-<key>".
        - Each toggle row: data-testid="setting-row" + data-key="<key>".
        - Save button: data-testid="settings-save".
*}
<div class="p-6">
    <div class="mb-6">
        <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">Settings</h1>
        <p class="text-sm text-muted m-0 mt-2">Optional features and integrations.</p>
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
                <form action="?p=admin&amp;c=settings&amp;section=features" method="post" class="space-y-4">
                    {csrf_field}
                    <input type="hidden" name="settingsGroup" value="features">

                    <div class="card">
                        <div class="card__header"><div><h3>Bans</h3><p>Public exports, KickIt, group / friend banning.</p></div></div>
                        <div class="card__body space-y-3">
                            <label class="flex items-center justify-between gap-3 p-3" style="border:1px solid var(--border);border-radius:var(--radius-md)" data-testid="setting-row" data-key="config.exportpublic">
                                <span class="text-sm">
                                    <span class="font-medium">Public ban export</span>
                                    <span class="block text-xs text-muted mt-2">Lets unauthenticated visitors download the full ban list.</span>
                                </span>
                                <input type="checkbox" name="export_public" id="export_public"{if $export_public} checked{/if}>
                            </label>

                            <label class="flex items-center justify-between gap-3 p-3" style="border:1px solid var(--border);border-radius:var(--radius-md)" data-testid="setting-row" data-key="config.enablekickit">
                                <span class="text-sm">
                                    <span class="font-medium">KickIt</span>
                                    <span class="block text-xs text-muted mt-2">Auto-kick a player when their ban lands.</span>
                                </span>
                                <input type="checkbox" name="enable_kickit" id="enable_kickit"{if $enable_kickit} checked{/if}>
                            </label>

                            <label class="flex items-center justify-between gap-3 p-3" style="border:1px solid var(--border);border-radius:var(--radius-md)" data-testid="setting-row" data-key="config.enablegroupbanning">
                                <span class="text-sm">
                                    <span class="font-medium">Steam group banning</span>
                                    <span class="block text-xs text-muted mt-2">
                                        Ban every member of a Steam community group.
                                        {if NOT $steamapi}<br><span style="color:var(--warning)">Requires a Steam Web API key in <code>config.php</code>.</span>{/if}
                                    </span>
                                </span>
                                <input type="checkbox" name="enable_groupbanning" id="enable_groupbanning"{if $enable_groupbanning} checked{/if}{if NOT $steamapi} disabled{/if}>
                            </label>

                            <label class="flex items-center justify-between gap-3 p-3" style="border:1px solid var(--border);border-radius:var(--radius-md)" data-testid="setting-row" data-key="config.enablefriendsbanning">
                                <span class="text-sm">
                                    <span class="font-medium">Steam friends banning</span>
                                    <span class="block text-xs text-muted mt-2">
                                        Ban every Steam friend of a player.
                                        {if NOT $steamapi}<br><span style="color:var(--warning)">Requires a Steam Web API key in <code>config.php</code>.</span>{/if}
                                    </span>
                                </span>
                                <input type="checkbox" name="enable_friendsbanning" id="enable_friendsbanning"{if $enable_friendsbanning} checked{/if}{if NOT $steamapi} disabled{/if}>
                            </label>

                            <label class="flex items-center justify-between gap-3 p-3" style="border:1px solid var(--border);border-radius:var(--radius-md)" data-testid="setting-row" data-key="config.enableadminrehashing">
                                <span class="text-sm">
                                    <span class="font-medium">Auto admin rehash</span>
                                    <span class="block text-xs text-muted mt-2">Push admin/group changes to servers immediately.</span>
                                </span>
                                <input type="checkbox" name="enable_adminrehashing" id="enable_adminrehashing"{if $enable_adminrehashing} checked{/if}>
                            </label>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card__header"><div><h3>Login</h3><p>Sign-in surfaces exposed to admins.</p></div></div>
                        <div class="card__body space-y-3">
                            <label class="flex items-center justify-between gap-3 p-3" style="border:1px solid var(--border);border-radius:var(--radius-md)" data-testid="setting-row" data-key="config.enablesteamlogin">
                                <span class="text-sm">
                                    <span class="font-medium">Steam OpenID login</span>
                                    <span class="block text-xs text-muted mt-2">Show "Sign in through Steam" on the login page.</span>
                                </span>
                                <input type="checkbox" name="enable_steamlogin" id="enable_steamlogin"{if $enable_steamlogin} checked{/if}>
                            </label>

                            <label class="flex items-center justify-between gap-3 p-3" style="border:1px solid var(--border);border-radius:var(--radius-md)" data-testid="setting-row" data-key="config.enablenormallogin">
                                <span class="text-sm">
                                    <span class="font-medium">Username/password login</span>
                                    <span class="block text-xs text-muted mt-2">Disable to require Steam login for all admins.</span>
                                </span>
                                <input type="checkbox" name="enable_normallogin" id="enable_normallogin"{if $enable_normallogin} checked{/if}>
                            </label>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card__header"><div><h3>Comments</h3><p>Visibility of admin commentary on bans / submissions.</p></div></div>
                        <div class="card__body space-y-3">
                            <label class="flex items-center justify-between gap-3 p-3" style="border:1px solid var(--border);border-radius:var(--radius-md)" data-testid="setting-row" data-key="config.enablepubliccomments">
                                <span class="text-sm">
                                    <span class="font-medium">Public admin comments</span>
                                    <span class="block text-xs text-muted mt-2">Show admin comments on a ban to anonymous visitors.</span>
                                </span>
                                <input type="checkbox" name="enable_publiccomments" id="enable_publiccomments"{if $enable_publiccomments} checked{/if}>
                            </label>
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
    </div>
</div>
