<div class="layout_box flex:11 admin_tab_content tabcontent" id="List admins" style="display: block;">
    {if not $permission_listadmin}
        Access Denied
    {else}
        <div class="admin_tab_content_title">
            <h2><i class="fas fa-user-shield"></i> Admins - {$admin_count}</h2>
        </div>

        <div class="padding">
            <span>Click on an admin to see more detailed information and actions to perform on them.</span>

            {load_template file="admin.admins.search"}

            <div class="flex flex-jc:end flex-ai:center margin-bottom:half">
                {$admin_nav}
            </div>

            <div class="table">
                <div class="table_box">
                    <table class="table_box">
                        <thead>
                            <tr>
                                <th class="text:left">Name</th>
                                <th class="text:left">Server Admin Group</th>
                                <th class="text:left">Web Admin Group</th>
                                <th class="text:left">Immunity Level</th>
                                <th class="text:left">Last Visited</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$admins item="admin"}
                                <tr class="collapse">
                                    <td>{$admin.user} (<a href="./index.php?p=banlist&advSearch={$admin.aid}&advType=admin"
                                            title="Show bans">{$admin.bancount} bans</a> | <a
                                            href="./index.php?p=banlist&advSearch={$admin.aid}&advType=nodemo"
                                            title="Show bans without demo">{$admin.nodemocount} w.d.</a>)</td>
                                    <td>{$admin.server_group}</td>
                                    <td>{$admin.web_group}</td>
                                    <td>{$admin.immunity}</td>
                                    <td>{$admin.lastvisit}</td>
                                </tr>
                                <tr class="table_hide">
                                    <td colspan="8">
                                        <div class="collapse_content">
                                            <div class="padding:half flex">
                                                <ul class="ban_action">
                                                    {if $permission_editadmin}
                                                        <li class="button button-light">
                                                            <a href="index.php?p=admin&c=admins&o=editdetails&id={$admin.aid}">
                                                                <i class="fas fa-clipboard-list"></i> Edit Details
                                                            </a>
                                                        </li>
                                                        <li class="button button-light">
                                                            <a href="index.php?p=admin&c=admins&o=editpermissions&id={$admin.aid}">
                                                                <i class="fas fa-edit fa-lg"></i> Edit Permissions
                                                            </a>
                                                        </li>
                                                        <li class="button button-light">
                                                            <a href="index.php?p=admin&c=admins&o=editservers&id={$admin.aid}">
                                                                <i class="fas fa-server"></i> Edit Server Access
                                                            </a>
                                                        </li>
                                                        <li class="button button-light">
                                                            <a href="index.php?p=admin&c=admins&o=editgroup&id={$admin.aid}">
                                                                <i class="fas fa-users"></i> Edit Groups
                                                            </a>
                                                        </li>
                                                    {/if}
                                                    {if $permission_deleteadmin}
                                                        <li class="button button-important">
                                                            <a href="#" onclick="RemoveAdmin({$admin.aid}, '{$admin.user}');">
                                                                <i class="fas fa-trash"></i> Delete Admin
                                                            </a>
                                                        </li>
                                                    {/if}
                                                </ul>

                                                <div class="flex:11 margin-right">
                                                    <h3>Server Admin Permissions</h3>
                                                    <ul>
                                                        {if $admin.server_flag_string}
                                                            {foreach from=$admin.server_flag_string item="permission"}
                                                                <li>{$permission}</li>
                                                            {/foreach}
                                                        {else}
                                                            <li>None</li>
                                                        {/if}
                                                    </ul>
                                                </div>

                                                <div class="flex:11">
                                                    <h3>Web Admin Permissions</h3>
                                                    <ul>
                                                        {if $admin.web_flag_string}
                                                            {foreach from=$admin.web_flag_string item="permission"}
                                                                <li>{$permission}</li>
                                                            {/foreach}
                                                        {else}
                                                            <li>None</li>
                                                        {/if}
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <script type="text/javascript" src="themes/{$theme}/scripts/collapse.js"></script>
    {/if}
</div>