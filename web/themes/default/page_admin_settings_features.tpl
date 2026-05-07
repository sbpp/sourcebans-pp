{*
    SourceBans++ 2026 — page / page_admin_settings_features.tpl

    "Features" sub-tab on the admin Settings page. Pair:
    Sbpp\View\AdminFeaturesView + web/pages/admin.settings.php (which
    routes by ?section= and renders one View per request — see
    sibling page_admin_settings_settings.tpl for the rationale).

    #1259 — sidebar lifted into a shared partial: the inline
    `<nav>` block + `grid-template-columns:14rem 1fr` shell that
    used to wrap this template's content is now driven by
    `core/admin_sidebar.tpl` via `web/includes/AdminTabs.php`. The
    page handler (admin.settings.php) opens the shell BEFORE this
    template renders. See AGENTS.md "Sub-paged admin routes".

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
