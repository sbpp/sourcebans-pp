{*
    SourceBans++ 2026 — admin/admins add

    Pair: web/pages/admin.admins.php (renders this + the list tab) and
    web/includes/View/AdminAdminsAddView.php.

    Form submission stays on the legacy ProcessAddAdmin() helper to keep
    the JSON-API contract identical to the default theme. The CSRF
    protection comes from {csrf_field}; xajax/sb-callback are NOT
    reintroduced.

    #1207 ADM-3 — section wrapper
    -----------------------------
    Renders inside the cross-template `.admin-admins-shell` opened by
    page_admin_admins_list.tpl. The `<section id="add-admin">` is the
    anchor target for the "Add admin" entry in the page-level ToC; its
    `scroll-margin-top` (set in admins-toc CSS) clears the sticky
    topbar after a hash-jump.
*}
<div class="card-tab" id="Add new admin">
    {if !$can_add_admins}
        <div class="card">
            <div class="card__body">
                <p class="text-sm text-muted m-0">Access denied.</p>
            </div>
        </div>
    {else}
        <section id="add-admin" class="admin-admins-section" data-testid="admin-admins-section-add-admin" aria-labelledby="add-admin-heading">
        <div class="mb-4">
            <h2 id="add-admin-heading" style="font-size:var(--fs-xl);font-weight:600;margin:0">Add new admin</h2>
            <p class="text-sm text-muted m-0 mt-2">Hover the help icons for field-level guidance.</p>
        </div>

        <div id="msg-green" class="card" style="display:none;border-left:3px solid var(--success)">
            <div class="card__body">
                <div class="flex items-center gap-3">
                    <i data-lucide="check-circle-2" style="color:var(--success)"></i>
                    <div>
                        <div class="font-semibold">Admin added</div>
                        <div class="text-sm text-muted">The new admin has been successfully added. Redirecting…</div>
                    </div>
                </div>
            </div>
        </div>

        <form id="add-admin-form" method="post" action="" class="space-y-4" autocomplete="off"
              onsubmit="event.preventDefault(); if (typeof ProcessAddAdmin === 'function') ProcessAddAdmin();">
            {csrf_field}

            <div class="card">
                <div class="card__header">
                    <div>
                        <h3>Identity</h3>
                        <p>The login name and Steam ID identify this admin in the panel and on game servers.</p>
                    </div>
                </div>
                <div class="card__body space-y-3">
                    <div class="grid gap-4" style="grid-template-columns:1fr 1fr">
                        <div>
                            <label class="label" for="adminname">Admin login</label>
                            <input class="input" id="adminname" name="adminname" type="text"
                                   tabindex="1" data-testid="admin-add-name" autocomplete="off">
                            <div id="name.msg" class="text-xs" style="color:var(--danger);margin-top:0.25rem"></div>
                        </div>
                        <div>
                            <label class="label" for="steam">Steam ID</label>
                            <input class="input font-mono" id="steam" name="steam" type="text"
                                   tabindex="2" value="STEAM_0:" data-testid="admin-add-steam" autocomplete="off">
                            <div id="steam.msg" class="text-xs" style="color:var(--danger);margin-top:0.25rem"></div>
                        </div>
                    </div>
                    <div>
                        <label class="label" for="email">Email <span class="text-faint" style="font-weight:400">(required for web access)</span></label>
                        <input class="input" id="email" name="email" type="email"
                               tabindex="3" data-testid="admin-add-email" autocomplete="off">
                        <div id="email.msg" class="text-xs" style="color:var(--danger);margin-top:0.25rem"></div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card__header">
                    <div>
                        <h3>Authentication</h3>
                        <p>Web-panel password and optional in-game admin password.</p>
                    </div>
                </div>
                <div class="card__body space-y-3">
                    <div class="grid gap-4" style="grid-template-columns:1fr 1fr">
                        <div>
                            <label class="label" for="password">Password <span class="text-faint" style="font-weight:400">(required for web access)</span></label>
                            <div class="flex gap-2">
                                <input class="input" id="password" name="password" type="password"
                                       tabindex="4" data-testid="admin-add-password" autocomplete="new-password">
                                <button type="button" class="btn btn--ghost btn--icon"
                                        title="Generate random password"
                                        aria-label="Generate random password"
                                        onclick="if (typeof LoadGeneratePassword === 'function') LoadGeneratePassword(); return false;">
                                    <i data-lucide="refresh-cw" style="width:14px;height:14px"></i>
                                </button>
                            </div>
                            <div id="password.msg" class="text-xs" style="color:var(--danger);margin-top:0.25rem"></div>
                        </div>
                        <div>
                            <label class="label" for="password2">Confirm password</label>
                            <input class="input" id="password2" name="password2" type="password"
                                   tabindex="5" data-testid="admin-add-password2" autocomplete="new-password">
                            <div id="password2.msg" class="text-xs" style="color:var(--danger);margin-top:0.25rem"></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mt-2" style="border-top:1px solid var(--border);padding-top:0.75rem">
                        <input type="checkbox" id="a_useserverpass" name="a_useserverpass"
                               tabindex="6" data-testid="admin-add-useserverpass"
                               onclick="var el = document.getElementById('a_serverpass'); if (el) el.disabled = !this.checked;">
                        <label for="a_useserverpass" class="text-sm font-medium" style="margin:0">Set in-game admin password</label>
                        <input class="input" id="a_serverpass" name="a_serverpass" type="password"
                               style="max-width:14rem;margin-left:auto" disabled tabindex="7"
                               data-testid="admin-add-serverpass" autocomplete="new-password"
                               aria-label="In-game admin password">
                    </div>
                    <div id="a_serverpass.msg" class="text-xs" style="color:var(--danger)"></div>
                </div>
            </div>

            <div class="card">
                <div class="card__header">
                    <div>
                        <h3>Server access</h3>
                        <p>Servers and server groups the admin will be able to administer in-game.</p>
                    </div>
                </div>
                <div class="card__body space-y-3">
                    {if !$group_list && !$server_list}
                        <p class="text-sm text-muted m-0"><em>No servers or server groups have been added yet.</em></p>
                    {else}
                        {if $group_list}
                            <div class="text-xs text-faint" style="text-transform:uppercase;letter-spacing:0.06em;font-weight:600">Server groups</div>
                            <div class="grid gap-2" style="grid-template-columns:repeat(auto-fill,minmax(14rem,1fr))">
                                {foreach $group_list as $group}
                                    <label class="flex items-center gap-2 p-3"
                                           style="border:1px solid var(--border);border-radius:var(--radius-md)">
                                        <input type="checkbox" id="add-server-group-{$group.gid}" name="group[]" value="g{$group.gid}"
                                               data-testid="admin-add-server-group">
                                        <span class="text-sm">{$group.name|escape}</span>
                                    </label>
                                {/foreach}
                            </div>
                        {/if}
                        {if $server_list}
                            <div class="text-xs text-faint" style="text-transform:uppercase;letter-spacing:0.06em;font-weight:600;margin-top:0.5rem">Individual servers</div>
                            <div class="grid gap-2" style="grid-template-columns:repeat(auto-fill,minmax(18rem,1fr))">
                                {foreach $server_list as $server}
                                    <label class="flex items-center gap-2 p-3"
                                           style="border:1px solid var(--border);border-radius:var(--radius-md)">
                                        <input type="checkbox" id="servers[]" name="servers[]" value="s{$server.sid}"
                                               data-testid="admin-add-server">
                                        <span class="text-sm font-mono" id="sa{$server.sid}">{$server.ip|escape}:{$server.port|escape}</span>
                                    </label>
                                {/foreach}
                            </div>
                        {/if}
                    {/if}
                </div>
            </div>

            <div class="card">
                <div class="card__header">
                    <div>
                        <h3>Permissions</h3>
                        <p>Pre-made groups, custom flags, or no permissions. New-group choice opens an inline editor.</p>
                    </div>
                </div>
                <div class="card__body space-y-3">
                    <div>
                        <label class="label" for="serverg">Server admin group</label>
                        <select class="select" id="serverg" name="serverg" tabindex="8"
                                data-testid="admin-add-serverg"
                                onchange="if (typeof update_server === 'function') update_server();">
                            <option value="-2">Please select…</option>
                            <option value="-3">No permissions</option>
                            <option value="c">Custom permissions</option>
                            <option value="n">New admin group</option>
                            <optgroup label="Groups" style="font-weight:bold">
                                {foreach $server_admin_group_list as $server_wg}
                                    <option value="{$server_wg.id}">{$server_wg.name|escape}</option>
                                {/foreach}
                            </optgroup>
                        </select>
                        <div id="server.msg" class="text-xs" style="color:var(--danger);margin-top:0.25rem"></div>
                        <div id="serverperm" style="overflow:hidden"></div>
                    </div>
                    <div>
                        <label class="label" for="webg">Web admin group</label>
                        <select class="select" id="webg" name="webg" tabindex="9"
                                data-testid="admin-add-webg"
                                onchange="if (typeof update_web === 'function') update_web();">
                            <option value="-2">Please select…</option>
                            <option value="-3">No permissions</option>
                            <option value="c">Custom permissions</option>
                            <option value="n">New admin group</option>
                            <optgroup label="Groups" style="font-weight:bold">
                                {foreach $server_group_list as $server_g}
                                    <option value="{$server_g.gid}">{$server_g.name|escape}</option>
                                {/foreach}
                            </optgroup>
                        </select>
                        <div id="web.msg" class="text-xs" style="color:var(--danger);margin-top:0.25rem"></div>
                        <div id="webperm" style="overflow:hidden"></div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" class="btn btn--ghost btn--sm"
                        onclick="history.go(-1);" data-testid="admin-add-back">Back</button>
                <button type="submit" class="btn btn--primary btn--sm" id="aadmin"
                        data-testid="admin-add-submit"><i data-lucide="user-plus"></i> Add admin</button>
            </div>
        </form>

        {* nofilter: server-built `<script>LoadServerHost('SID', 'id', 'saSID');…</script>` from `:prefix_servers.sid` integer column, no user input — see admin.admins.php. *}
        {$server_script nofilter}
        </section>
    {/if}
</div>
