{*
    SourceBans++ 2026 — page / page_admin_settings_themes.tpl

    "Themes" sub-tab on the admin Settings page (B18 marquee). Pair:
    Sbpp\View\AdminThemesView + web/pages/admin.settings.php (which
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
            $can_web_settings — required to enable the "Use this
                theme" buttons. Read-only listing is shown either
                way so admins without write access still get a
                useful preview of what's installed.
            $can_owner — kept for parity with sibling settings views.
        Active selection: $current_theme_dir (matches the running
            SB_THEME constant).
        Theme list: $theme_list — list of dicts with keys dir, name,
            author, version, link, screenshot, active. Built by
            admin.settings.php's discovery loop from theme.conf.php in
            each subdirectory of SB_THEMES. No new server-side
            enumeration is added in this template.
        Currently-selected scalars: $theme_name, $theme_author,
            $theme_version, $theme_link, $theme_screenshot. Used by
            the legacy default-theme template's "current theme" panel;
            the sbpp2026 layout surfaces $theme_name/version/author/
            link in the header summary and captures-and-discards
            $theme_screenshot (see annotation).

    Wiring:
        The "Use this theme" button on each card calls
        Actions.SystemApplyTheme (action `system.apply_theme`,
        registered in _register.php with
        ADMIN_OWNER | ADMIN_WEB_SETTINGS, see web/api/handlers/system.php).
        On success the JSON envelope's `reload: true` triggers a full
        page reload via window.location.reload() so the new chrome
        paints with the right CSS bundle. We deliberately use the
        existing API action rather than introducing a new one — B
        tickets cannot touch web/api/handlers/.

    Testability hooks:
        - Sidebar links: data-testid="admin-tab-<slug>" (#1259 unified
          shape across servers / mods / groups / settings).
        - Each card: data-testid="theme-card" + data-theme="<dir>".
        - Active card carries aria-current="true" (per the issue plan).
        - "Use this theme" button: data-testid="theme-apply".
*}
<div class="p-6">
    <div class="mb-6">
        <h1 style="font-size:var(--fs-2xl);font-weight:600;margin:0">Settings</h1>
        <p class="text-sm text-muted m-0 mt-2">Pick the theme that paints the panel chrome for every visitor.</p>
    </div>
            <div class="card">
                <div class="card__header">
                    <div>
                        <h3>Installed themes</h3>
                        <p>Each card represents one directory under <code>web/themes/</code> with a <code>theme.conf.php</code> manifest.</p>
                    </div>
                    <div class="text-xs text-muted" style="text-align:right" data-testid="current-theme-summary">
                        Currently in use:<br>
                        <strong>{$theme_name}</strong> v{$theme_version} by {$theme_author}{if $theme_link != ''} · <a href="{$theme_link}" target="_blank" rel="noopener" class="text-muted">homepage</a>{/if}<br>
                        <code>{$current_theme_dir}</code>
                    </div>
                </div>
                {* nofilter: $theme_screenshot is the prebuilt "<img width=… src=themes/SB_THEME/<theme.conf.php-defined filename>>" string from admin.settings.php — server constants only, no user input. We capture-and-discard it because the sbpp2026 card grid renders its own <img> per card from $t.screenshot, but the View has to keep $theme_screenshot for the legacy default theme's "current theme" panel. Drop this whole capture and the View property when D1 retires the default theme. *}
                {capture name="legacy_theme_screenshot"}{$theme_screenshot nofilter}{/capture}
                <div class="card__body">
                    {if NOT $can_web_settings}
                        <p class="text-xs text-muted m-0 mb-4" style="padding:0.5rem 0.75rem;border:1px solid var(--border);border-radius:var(--radius-md);background:var(--bg-muted)">
                            <i data-lucide="info"></i>
                            You can browse themes but you need <code>ADMIN_WEB_SETTINGS</code> to switch the active one.
                        </p>
                    {/if}

                    <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill, minmax(18rem, 1fr))">
                        {foreach from=$theme_list item=t}
                            <article class="theme-card"
                                     data-testid="theme-card"
                                     data-theme="{$t.dir}"
                                     {if $t.active}aria-current="true"{/if}
                                     style="border:1px solid {if $t.active}var(--brand-600){else}var(--border){/if};border-radius:var(--radius-lg);overflow:hidden;background:var(--bg-surface);display:flex;flex-direction:column;{if $t.active}box-shadow:0 0 0 2px var(--brand-600) inset{/if}">
                                <div style="aspect-ratio:250 / 170;background:var(--bg-muted);display:grid;place-items:center;overflow:hidden">
                                    <img src="{$t.screenshot}"
                                         alt="{$t.name} theme screenshot"
                                         loading="lazy"
                                         style="width:100%;height:100%;object-fit:cover"
                                         onerror="this.style.display='none';this.parentElement.innerHTML='<span class=\'text-muted text-xs\'>No screenshot</span>'">
                                </div>
                                <div class="p-4 space-y-3" style="flex:1;display:flex;flex-direction:column">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <h4 class="m-0 font-semibold text-sm">{$t.name}</h4>
                                            {if $t.active}
                                                <span class="pill pill--online" title="Currently active">
                                                    <i data-lucide="check" style="width:0.75rem;height:0.75rem"></i> Active
                                                </span>
                                            {/if}
                                        </div>
                                        <p class="text-xs text-muted m-0 mt-2">by {$t.author} · v{$t.version}{if $t.link != ''} · <a href="{$t.link}" target="_blank" rel="noopener" class="text-muted" title="Open theme homepage"><i data-lucide="external-link" style="width:0.75rem;height:0.75rem;vertical-align:-1px"></i> homepage</a>{/if}</p>
                                        <p class="text-xs font-mono text-faint m-0 mt-2">{$t.dir}</p>
                                    </div>
                                    <div class="flex items-center justify-end gap-2 mt-2" style="margin-top:auto">
                                        {if $can_web_settings}
                                            {if $t.active}
                                                <button type="button" class="btn btn--secondary btn--sm" disabled aria-pressed="true">
                                                    <i data-lucide="check"></i> In use
                                                </button>
                                            {else}
                                                <button type="button"
                                                        class="btn btn--primary btn--sm"
                                                        data-testid="theme-apply"
                                                        data-theme="{$t.dir}"
                                                        onclick="applyTheme(this.dataset.theme);">
                                                    <i data-lucide="check-circle"></i> Use this theme
                                                </button>
                                            {/if}
                                        {/if}
                                    </div>
                                </div>
                            </article>
                        {/foreach}
                    </div>
                </div>
            </div>
</div>

<script>
{literal}
// @ts-check
(function () {
    'use strict';

    /**
     * Activate a theme by hitting Actions.SystemApplyTheme. The handler
     * persists `config.theme` in :prefix_settings and returns
     * { reload: true }; we honour that with a hard reload so the new
     * theme's CSS + chrome paint cleanly. Any error envelope is shown
     * via callOrAlert so admins see the failure reason instead of a
     * silent no-op.
     */
    window.applyTheme = function (theme) {
        if (!theme) return;
        if (!window.confirm('Switch the panel theme to "' + theme + '"? Every visitor will see the new theme on their next request.')) return;
        if (!window.sb || !window.sb.api || !window.Actions) return;
        window.sb.api.callOrAlert(window.Actions.SystemApplyTheme, { theme: theme }).then(function (env) {
            if (env && env.ok && env.data && env.data.reload) {
                window.location.reload();
            }
        });
    };
})();
{/literal}
</script>
