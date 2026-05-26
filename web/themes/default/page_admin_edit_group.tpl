{*
    SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
    Licensed under the Elastic License 2.0.
    See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

    "Edit group" pair: web/pages/admin.edit.group.php +
    web/includes/View/EditGroupView.php.

    Replaces the v1.x-era inline `echo` of `groups.web.perm.php` /
    `groups.server.perm.php` partials and the `ProcessEditGroup()` /
    `ButtonOver()` / `$('groupname').value` legacy JS handlers (all
    dead since sourcebans.js was removed at #1123 D1).

    Initial state is fully server-rendered; the page-tail vanilla JS
    handles the parent / child composite toggles, the dynamic
    overrides editor, and submission via `Actions.GroupsEdit`.
*}
<div class="space-y-4" data-testid="admin-edit-group">
    <div class="mb-4">
        <h1 style="font-size:var(--fs-xl);font-weight:600;margin:0">
            Edit group
        </h1>
        <p class="text-sm text-muted m-0 mt-2">
            {if $type === 'srv'}Server group: SourceMod admin flags + per-command overrides.
            {else}Web group: panel-side permissions.{/if}
        </p>
    </div>

    <form id="edit-group-form" class="space-y-4" autocomplete="off"
          data-gid="{$group_id}" data-type="{$type|escape}">
        {csrf_field}

        <div class="card">
            <div class="card__header"><div>
                <h3>Group name</h3>
            </div></div>
            <div class="card__body">
                <label class="label" for="groupname">Group name</label>
                <input class="input" id="groupname" name="groupname" type="text"
                       value="{$group_name|escape}" data-testid="group-name">
                <div id="groupname.msg" class="text-xs" style="color:var(--danger);margin-top:0.25rem"></div>
            </div>
        </div>

        {if $type === 'web'}
            {* ============ Web Admin Permissions ============ *}
            <div class="card">
                <div class="card__header"><div>
                    <h3>Web Admin Permissions</h3>
                </div></div>
                <div class="card__body">
                    <table class="table" style="margin:0;font-size:var(--fs-sm)">
                        <colgroup>
                            <col style="width:auto">
                            <col style="width:5rem">
                        </colgroup>
                        <tbody>
                            {if $is_owner_editor}
                            <tr>
                                <td><strong>Root Admin</strong> (Full access)</td>
                                <td class="text-center">
                                    <input type="checkbox" id="p2" data-perm="owner" {if $web_owner}checked{/if}>
                                </td>
                            </tr>
                            {/if}
                            <tr><td><strong>Manage Admins</strong></td>
                                <td class="text-center"><input type="checkbox" id="p3" data-parent="admins"></td></tr>
                            <tr><td class="pl-6">List Admins</td>
                                <td class="text-center"><input type="checkbox" id="p4" data-child="admins" {if $web_list_admins}checked{/if}></td></tr>
                            <tr><td class="pl-6">Add Admins</td>
                                <td class="text-center"><input type="checkbox" id="p5" data-child="admins" {if $web_add_admins}checked{/if}></td></tr>
                            <tr><td class="pl-6">Edit Admins</td>
                                <td class="text-center"><input type="checkbox" id="p6" data-child="admins" {if $web_edit_admins}checked{/if}></td></tr>
                            <tr><td class="pl-6">Delete Admins</td>
                                <td class="text-center"><input type="checkbox" id="p7" data-child="admins" {if $web_delete_admins}checked{/if}></td></tr>

                            <tr><td><strong>Server Management</strong></td>
                                <td class="text-center"><input type="checkbox" id="p8" data-parent="servers"></td></tr>
                            <tr><td class="pl-6">List Servers</td>
                                <td class="text-center"><input type="checkbox" id="p9" data-child="servers" {if $web_list_servers}checked{/if}></td></tr>
                            <tr><td class="pl-6">Add New Servers</td>
                                <td class="text-center"><input type="checkbox" id="p10" data-child="servers" {if $web_add_server}checked{/if}></td></tr>
                            <tr><td class="pl-6">Edit Servers</td>
                                <td class="text-center"><input type="checkbox" id="p11" data-child="servers" {if $web_edit_servers}checked{/if}></td></tr>
                            <tr><td class="pl-6">Delete Servers</td>
                                <td class="text-center"><input type="checkbox" id="p12" data-child="servers" {if $web_delete_servers}checked{/if}></td></tr>

                            <tr><td><strong>Ban Management</strong></td>
                                <td class="text-center"><input type="checkbox" id="p13" data-parent="bans"></td></tr>
                            <tr><td class="pl-6">Add a Ban</td>
                                <td class="text-center"><input type="checkbox" id="p14" data-child="bans" {if $web_add_ban}checked{/if}></td></tr>
                            <tr><td class="pl-6">Edit Own Bans</td>
                                <td class="text-center"><input type="checkbox" id="p16" data-child="bans" {if $web_edit_own_bans}checked{/if}></td></tr>
                            <tr><td class="pl-6">Edit Bans of Group</td>
                                <td class="text-center"><input type="checkbox" id="p17" data-child="bans" {if $web_edit_group_bans}checked{/if}></td></tr>
                            <tr><td class="pl-6">Edit All Bans</td>
                                <td class="text-center"><input type="checkbox" id="p18" data-child="bans" {if $web_edit_all_bans}checked{/if}></td></tr>
                            <tr><td class="pl-6">Ban Protests</td>
                                <td class="text-center"><input type="checkbox" id="p19" data-child="bans" {if $web_ban_protests}checked{/if}></td></tr>
                            <tr><td class="pl-6">Ban Submissions</td>
                                <td class="text-center"><input type="checkbox" id="p20" data-child="bans" {if $web_ban_submissions}checked{/if}></td></tr>
                            <tr><td class="pl-6">Unban Own Bans</td>
                                <td class="text-center"><input type="checkbox" id="p38" data-child="bans" {if $web_unban_own_bans}checked{/if}></td></tr>
                            <tr><td class="pl-6">Unban Group Bans</td>
                                <td class="text-center"><input type="checkbox" id="p39" data-child="bans" {if $web_unban_group_bans}checked{/if}></td></tr>
                            <tr><td class="pl-6">Unban All Bans</td>
                                <td class="text-center"><input type="checkbox" id="p32" data-child="bans" {if $web_unban}checked{/if}></td></tr>
                            <tr><td class="pl-6">Delete Bans</td>
                                <td class="text-center"><input type="checkbox" id="p33" data-child="bans" {if $web_delete_ban}checked{/if}></td></tr>
                            <tr><td class="pl-6">Import Bans</td>
                                <td class="text-center"><input type="checkbox" id="p34" data-child="bans" {if $web_ban_import}checked{/if}></td></tr>

                            <tr><td><strong>Group Management</strong></td>
                                <td class="text-center"><input type="checkbox" id="p21" data-parent="groups"></td></tr>
                            <tr><td class="pl-6">List Groups</td>
                                <td class="text-center"><input type="checkbox" id="p22" data-child="groups" {if $web_list_groups}checked{/if}></td></tr>
                            <tr><td class="pl-6">Add Groups</td>
                                <td class="text-center"><input type="checkbox" id="p23" data-child="groups" {if $web_add_group}checked{/if}></td></tr>
                            <tr><td class="pl-6">Edit Groups</td>
                                <td class="text-center"><input type="checkbox" id="p24" data-child="groups" {if $web_edit_groups}checked{/if}></td></tr>
                            <tr><td class="pl-6">Delete Groups</td>
                                <td class="text-center"><input type="checkbox" id="p25" data-child="groups" {if $web_delete_groups}checked{/if}></td></tr>

                            <tr><td><strong>Email Notifications</strong></td>
                                <td class="text-center"><input type="checkbox" id="p35" data-parent="notify"></td></tr>
                            <tr><td class="pl-6">Notify on Submission</td>
                                <td class="text-center"><input type="checkbox" id="p36" data-child="notify" {if $web_notify_sub}checked{/if}></td></tr>
                            <tr><td class="pl-6">Notify on Protest</td>
                                <td class="text-center"><input type="checkbox" id="p37" data-child="notify" {if $web_notify_protest}checked{/if}></td></tr>

                            <tr><td><strong>Web Panel Settings</strong></td>
                                <td class="text-center"><input type="checkbox" id="p26" {if $web_settings}checked{/if}></td></tr>

                            <tr><td><strong>Manage Mods</strong></td>
                                <td class="text-center"><input type="checkbox" id="p27" data-parent="mods"></td></tr>
                            <tr><td class="pl-6">List Mods</td>
                                <td class="text-center"><input type="checkbox" id="p28" data-child="mods" {if $web_list_mods}checked{/if}></td></tr>
                            <tr><td class="pl-6">Add Mods</td>
                                <td class="text-center"><input type="checkbox" id="p29" data-child="mods" {if $web_add_mods}checked{/if}></td></tr>
                            <tr><td class="pl-6">Edit Mods</td>
                                <td class="text-center"><input type="checkbox" id="p30" data-child="mods" {if $web_edit_mods}checked{/if}></td></tr>
                            <tr><td class="pl-6">Delete Mods</td>
                                <td class="text-center"><input type="checkbox" id="p31" data-child="mods" {if $web_delete_mods}checked{/if}></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        {else}
            {* ============ Server Admin Permissions ============ *}
            <div class="card">
                <div class="card__header"><div>
                    <h3>Server Admin Permissions</h3>
                </div></div>
                <div class="card__body">
                    <table class="table" style="margin:0;font-size:var(--fs-sm)">
                        <thead><tr>
                            <th>Permission</th>
                            <th class="text-center" style="width:4rem">Flag</th>
                            <th>Purpose</th>
                            <th class="text-center" style="width:4rem">On</th>
                        </tr></thead>
                        <tbody>
                            {if $is_owner_editor}
                            <tr>
                                <td><strong>Root Admin</strong></td>
                                <td class="text-center font-mono">z</td>
                                <td>Magically enables every flag.</td>
                                <td class="text-center"><input type="checkbox" id="s14" data-sm-flag="z" {if $sm_root}checked{/if}></td>
                            </tr>
                            {/if}
                            <tr><td>Reserved Slots</td><td class="text-center font-mono">a</td><td>Reserved-slot access.</td>
                                <td class="text-center"><input type="checkbox" id="s1" data-sm-flag="a" {if $sm_reserved_slot}checked{/if}></td></tr>
                            <tr><td>Generic</td><td class="text-center font-mono">b</td><td>Generic admin; required for admins.</td>
                                <td class="text-center"><input type="checkbox" id="s23" data-sm-flag="b" {if $sm_generic}checked{/if}></td></tr>
                            <tr><td>Kick Players</td><td class="text-center font-mono">c</td><td>Kick other players.</td>
                                <td class="text-center"><input type="checkbox" id="s2" data-sm-flag="c" {if $sm_kick}checked{/if}></td></tr>
                            <tr><td>Ban Players</td><td class="text-center font-mono">d</td><td>Ban other players.</td>
                                <td class="text-center"><input type="checkbox" id="s3" data-sm-flag="d" {if $sm_ban}checked{/if}></td></tr>
                            <tr><td>Unban Players</td><td class="text-center font-mono">e</td><td>Remove bans.</td>
                                <td class="text-center"><input type="checkbox" id="s4" data-sm-flag="e" {if $sm_unban}checked{/if}></td></tr>
                            <tr><td>Slay</td><td class="text-center font-mono">f</td><td>Slay / harm other players.</td>
                                <td class="text-center"><input type="checkbox" id="s5" data-sm-flag="f" {if $sm_slay}checked{/if}></td></tr>
                            <tr><td>Map Changes</td><td class="text-center font-mono">g</td><td>Change the map or major gameplay features.</td>
                                <td class="text-center"><input type="checkbox" id="s6" data-sm-flag="g" {if $sm_map}checked{/if}></td></tr>
                            <tr><td>Change cvars</td><td class="text-center font-mono">h</td><td>Change most cvars.</td>
                                <td class="text-center"><input type="checkbox" id="s7" data-sm-flag="h" {if $sm_cvar}checked{/if}></td></tr>
                            <tr><td>Exec Config Files</td><td class="text-center font-mono">i</td><td>Execute config files.</td>
                                <td class="text-center"><input type="checkbox" id="s8" data-sm-flag="i" {if $sm_config}checked{/if}></td></tr>
                            <tr><td>Admin Chat</td><td class="text-center font-mono">j</td><td>Special chat privileges.</td>
                                <td class="text-center"><input type="checkbox" id="s9" data-sm-flag="j" {if $sm_chat}checked{/if}></td></tr>
                            <tr><td>Start Votes</td><td class="text-center font-mono">k</td><td>Start or create votes.</td>
                                <td class="text-center"><input type="checkbox" id="s10" data-sm-flag="k" {if $sm_vote}checked{/if}></td></tr>
                            <tr><td>Password Server</td><td class="text-center font-mono">l</td><td>Set a password on the server.</td>
                                <td class="text-center"><input type="checkbox" id="s11" data-sm-flag="l" {if $sm_password}checked{/if}></td></tr>
                            <tr><td>Run RCON Commands</td><td class="text-center font-mono">m</td><td>Use RCON commands.</td>
                                <td class="text-center"><input type="checkbox" id="s12" data-sm-flag="m" {if $sm_rcon}checked{/if}></td></tr>
                            <tr><td>Enable Cheats</td><td class="text-center font-mono">n</td><td>Change sv_cheats or use cheating commands.</td>
                                <td class="text-center"><input type="checkbox" id="s13" data-sm-flag="n" {if $sm_cheats}checked{/if}></td></tr>
                            <tr>
                                <td>Immunity</td>
                                <td class="text-center font-mono">&mdash;</td>
                                <td>Higher number = more immunity.</td>
                                <td class="text-center">
                                    <input class="input" type="number" min="0" id="immunity"
                                           value="{$immunity|escape}" style="width:5rem;text-align:center">
                                </td>
                            </tr>
                            <tr><td>Custom flag &quot;o&quot;</td><td class="text-center font-mono">o</td><td>&nbsp;</td>
                                <td class="text-center"><input type="checkbox" id="s17" data-sm-flag="o" {if $sm_custom1}checked{/if}></td></tr>
                            <tr><td>Custom flag &quot;p&quot;</td><td class="text-center font-mono">p</td><td>&nbsp;</td>
                                <td class="text-center"><input type="checkbox" id="s18" data-sm-flag="p" {if $sm_custom2}checked{/if}></td></tr>
                            <tr><td>Custom flag &quot;q&quot;</td><td class="text-center font-mono">q</td><td>&nbsp;</td>
                                <td class="text-center"><input type="checkbox" id="s19" data-sm-flag="q" {if $sm_custom3}checked{/if}></td></tr>
                            <tr><td>Custom flag &quot;r&quot;</td><td class="text-center font-mono">r</td><td>&nbsp;</td>
                                <td class="text-center"><input type="checkbox" id="s20" data-sm-flag="r" {if $sm_custom4}checked{/if}></td></tr>
                            <tr><td>Custom flag &quot;s&quot;</td><td class="text-center font-mono">s</td><td>&nbsp;</td>
                                <td class="text-center"><input type="checkbox" id="s21" data-sm-flag="s" {if $sm_custom5}checked{/if}></td></tr>
                            <tr><td>Custom flag &quot;t&quot;</td><td class="text-center font-mono">t</td><td>&nbsp;</td>
                                <td class="text-center"><input type="checkbox" id="s22" data-sm-flag="t" {if $sm_custom6}checked{/if}></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {* ============ Group Overrides ============ *}
            <div class="card">
                <div class="card__header"><div>
                    <h3>Group overrides</h3>
                    <p>
                        Group overrides allow specific commands or groups of commands to be completely allowed
                        or denied to members of the group. Read about
                        <a href="http://wiki.alliedmods.net/Adding_Groups_%28SourceMod%29" target="_blank" rel="noopener noreferrer">group overrides</a>
                        in the AlliedModders wiki.
                    </p>
                    <p class="text-sm text-muted m-0 mt-2">
                        Blanking out an override's name will delete it.
                    </p>
                </div></div>
                <div class="card__body">
                    <table class="table" style="margin:0;font-size:var(--fs-sm)" id="overrides-table">
                        <thead><tr>
                            <th style="width:7rem">Type</th>
                            <th>Name</th>
                            <th style="width:7rem">Access</th>
                        </tr></thead>
                        <tbody data-overrides-body>
                            {foreach $overrides as $override}
                                <tr data-override-row data-override-id="{$override.id}">
                                    <td>
                                        <select class="input" data-override-field="type">
                                            <option value="command" {if $override.type === 'command'}selected{/if}>Command</option>
                                            <option value="group"   {if $override.type === 'group'}selected{/if}>Group</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input class="input" type="text"
                                               data-override-field="name" value="{$override.name|escape}">
                                    </td>
                                    <td>
                                        <select class="input" data-override-field="access">
                                            <option value="allow" {if $override.access === 'allow'}selected{/if}>Allow</option>
                                            <option value="deny"  {if $override.access === 'deny'}selected{/if}>Deny</option>
                                        </select>
                                    </td>
                                </tr>
                            {/foreach}
                            <tr data-override-new>
                                <td>
                                    <select class="input" id="new_override_type">
                                        <option value="command">Command</option>
                                        <option value="group">Group</option>
                                    </select>
                                </td>
                                <td><input class="input" type="text" id="new_override_name" placeholder="(blank = no new override)"></td>
                                <td>
                                    <select class="input" id="new_override_access">
                                        <option value="allow">Allow</option>
                                        <option value="deny">Deny</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        {/if}

        <div class="flex gap-2">
            <button type="submit" class="btn btn--primary"
                    data-testid="admin-edit-group-save">Save changes</button>
            <a class="btn btn--ghost" href="index.php?p=admin&amp;c=groups">Cancel</a>
        </div>
    </form>
</div>
