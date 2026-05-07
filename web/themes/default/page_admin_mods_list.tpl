{*
    SourceBans++ 2026 — page / page_admin_mods_list.tpl

    First tab of the admin "Mods" page. Pair: Sbpp\View\AdminModsListView
    + web/pages/admin.mods.php (renders this and page_admin_mods_add.tpl
    inside the AdminTabs scaffold).

    Variable contract (kept in sync by SmartyTemplateRule):
        - $permission_listmods   — gate the whole tab body.
        - $permission_editmods   — gate the per-row "Edit" link.
        - $permission_deletemods — gate the per-row "Delete" button.
        - $mod_count             — total mods configured.
        - $mod_list              — :prefix_mods rows (mid, name,
                                   modfolder, icon, steam_universe,
                                   enabled).

    Variable names match the default theme's page_admin_mods_list.tpl
    (legacy `permission_*` style). The dual-theme PHPStan matrix
    (#1123 A2) cross-checks both templates against the View, so both
    have to agree on names until D1 retires the legacy theme.

    Testability hooks:
        - Each row carries data-testid="mod-row" + data-id="{$mod.mid}"
          so end-to-end tests can target a specific mod without parsing
          inner markup.
        - The Delete button uses data-mod-name / data-mod-id to feed
          RemoveMod() so admin-controlled mod names can never escape
          out of an inline JS string literal (#1113-style hardening).
*}
<div class="page-section">
{if NOT $permission_listmods}
    <div class="card">
        <div class="card__body">
            <p class="text-muted">Access denied.</p>
        </div>
    </div>
{else}
    <div class="card">
        <div class="card__header">
            <div>
                <h3>Server Mods</h3>
                <p>{$mod_count} configured</p>
            </div>
        </div>
        {if $mod_count > 0}
            <table class="table" data-testid="mods-table">
                <thead>
                    <tr>
                        <th style="width:40%">Name</th>
                        <th>Folder</th>
                        <th><span title="SteamID Universe (X of STEAM_X:Y:Z)">SU</span></th>
                        <th>Status</th>
                        {if $permission_editmods || $permission_deletemods}
                            <th style="text-align:right">Actions</th>
                        {/if}
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$mod_list item=mod}
                        <tr id="mid_{$mod.mid}" data-testid="mod-row" data-id="{$mod.mid}">
                            <td>
                                <div class="flex items-center gap-3">
                                    <img src="images/games/{$mod.icon}"
                                         alt=""
                                         width="20"
                                         height="20"
                                         loading="lazy"
                                         onerror="this.style.visibility='hidden'">
                                    <span class="font-medium">{$mod.name}</span>
                                </div>
                            </td>
                            <td><span class="font-mono text-xs">{$mod.modfolder}</span></td>
                            <td class="tabular-nums">{$mod.steam_universe}</td>
                            <td>
                                {if $mod.enabled}
                                    <span class="pill pill--online">Enabled</span>
                                {else}
                                    <span class="pill pill--offline">Disabled</span>
                                {/if}
                            </td>
                            {if $permission_editmods || $permission_deletemods}
                                <td style="text-align:right">
                                    <div class="flex justify-end gap-2">
                                        {if $permission_editmods}
                                            <a class="btn btn--ghost btn--sm"
                                               href="index.php?p=admin&c=mods&o=edit&id={$mod.mid|escape:'url'}"
                                               data-testid="editmod-link">Edit</a>
                                        {/if}
                                        {if $permission_deletemods}
                                            <button class="btn btn--ghost btn--sm"
                                                    type="button"
                                                    data-testid="deletemod-btn"
                                                    data-mod-id="{$mod.mid}"
                                                    data-mod-name="{$mod.name}"
                                                    onclick="RemoveMod(this.dataset.modName, this.dataset.modId);">Delete</button>
                                        {/if}
                                    </div>
                                </td>
                            {/if}
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {else}
            <div class="card__body">
                <p class="text-muted">No mods configured yet.</p>
            </div>
        {/if}
    </div>
{/if}
</div>
