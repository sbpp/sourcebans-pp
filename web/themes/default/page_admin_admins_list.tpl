<div id="admin-page-content">
    <div class="tabcontent" id="List admins">
        {if not $permission_listadmin}
            Access Denied
        {else}
            <h3>Admins (<span id="admincount">{$admin_count}</span>)</h3>
            Click on an admin to see more detailed information and actions to perform on them.<br /><br />

            {php} require (TEMPLATES_PATH . "/admin.admins.search.php");{/php}

            <div id="banlist-nav">
                {$admin_nav}
            </div>
            <div id="banlist">
                <table width="99%" cellspacing="0" cellpadding="0" align="center">
                    <tr>
                        <td width="34%" class="listtable_top"><b>Name</b></td>
                        <td width="33%" class="listtable_top"><b>Server Admin Group </b></td>
                        <td width="33%" class="listtable_top"><b>Web Admin Group</b></td>
                    </tr>
                    {foreach from="$admins" item="admin"}
                        <tr onmouseout="this.className='tbl_out'" onmouseover="this.className='tbl_hover'" class="tbl_out opener">
                            <td class="listtable_1" style="padding:3px;">{$admin.user} (<a href="./index.php?p=banlist&advSearch={$admin.aid}&advType=admin" title="Show bans">{$admin.bancount} bans</a> | <a href="./index.php?p=banlist&advSearch={$admin.aid}&advType=nodemo" title="Show bans without demo">{$admin.nodemocount} w.d.</a>)</td>
                            <td class="listtable_1" style="padding:3px;">{$admin.server_group}</td>
                            <td class="listtable_1" style="padding:3px;">{$admin.web_group}</td>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <div class="opener" align="center" border="1">
                                    <table width="100%" cellspacing="0" cellpadding="3" bgcolor="#eaebeb">
                                        <tr>
                                            <td align="left" colspan="3" class="front-module-header">
                                                <b>Admin Details of {$admin.user}</b>
                                            </td>
                                        </tr>
                                        <tr align="left">
                                            <td width="35%" class="front-module-line"><b>Server Admin Permissions</b></td>
                                            <td width="35%" class="front-module-line"><b>Web Admin Permissions</b></td>
                                            <td width="30%" valign="top" class="front-module-line"><b>Action</b></td>
                                        </tr>
                                        <tr align="left">
                                            <td valign="top">
                                                <span style='font-size:10px;color:#1b75d1;'>Web Permissions</span>
                                                <br/>
                                                {if $admin.server_flag_string}
                                                    {foreach from=$admin.server_flag_string item=permission}
                                                        &bull; {$permission} <br/>
                                                    {/foreach}
                                                {else}
                                                    <i>None</i>
                                                {/if}
                                            </td>
                                            <td valign="top">
                                                <span style='font-size:10px;color:#1b75d1;'>Server Permissions</span>
                                                <br/>
                                                {if $admin.web_flag_string}
                                                    {foreach from=$admin.web_flag_string item=permission}
                                                        &bull; {$permission} <br/>
                                                    {/foreach}
                                                {else}
                                                    <i>None</i>
                                                {/if}
                                            </td>
                                            <td width="30%" valign="top">
                                                <div class="ban-edit">
                                                    <ul>
                                                        {if $permission_editadmin}
                                                            <li>
                                                                <a href="index.php?p=admin&c=admins&o=editdetails&id={$admin.aid}"><i class="fas fa-clipboard-list fa-lg"></i> Edit Details</a>
                                                            </li>
                                                            <li>
                                                                <a href="index.php?p=admin&c=admins&o=editpermissions&id={$admin.aid}"><i class="fas fa-subscript fa-lg"></i> Edit Permissions</a>
                                                            </li>
                                                            <li>
                                                                <a href="index.php?p=admin&c=admins&o=editservers&id={$admin.aid}"><i class="fas fa-server fa-lg"></i> Edit Server Access</a>
                                                            </li>
                                                            <li>
                                                                <a href="index.php?p=admin&c=admins&o=editgroup&id={$admin.aid}"><i class="fas fa-users fa-lg"></i> Edit Groups</a>
                                                            </li>
                                                        {/if}
                                                        {if $permission_deleteadmin}
                                                            <li>
                                                                <a href="#" onclick="RemoveAdmin({$admin.aid}, '{$admin.user}');"><i class="fas fa-trash fa-lg"></i> Delete Admin</a>
                                                            </li>
                                                        {/if}
                                                    </ul>
                                                </div>
                                                <div class="front-module-line" style="padding:3px;">Immunity Level: <b>{$admin.immunity}</b></div>
                                                <div class="front-module-line" style="padding:3px;">Last Visited: <b><small>{$admin.lastvisit}</small></b></div>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    {/foreach}
                </table>
            </div>
            <script type="text/javascript">InitAccordion('tr.opener', 'div.opener', 'mainwrapper');</script>
        {/if}
    </div>
