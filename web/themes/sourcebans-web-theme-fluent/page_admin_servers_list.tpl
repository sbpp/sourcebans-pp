<div class="layout_box flex:11 admin_tab_content tabcontent" id="List servers" style="display: block;">
    {if NOT $permission_list}
        Access Denied
    {else}
        <div class="admin_tab_content_title">
            <h2><i class="fas fa-server"></i> Servers (<span id="srvcount">{$server_count}</span>)</h2>
        </div>

        <div class="padding">
            <div class="table table_box">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mod</th>
                            <th class="text:left">Hostname</th>
                            <th>Players</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {foreach from=$server_list item="server"}
                            <script>
                                xajax_ServerHostPlayers({$server.sid});
                            </script>

                            <tr id="sid_{$server.sid}" {if $server.enabled==0}{/if}>
                                <td class="text:center">
                                    {$server.sid}
                                </td>
                                <td class="text:center">
                                    <img src="images/games/{$server.icon}" alt="{$server.icon}" />
                                </td>
                                <td id="host_{$server.sid}">
                                    <span class="text:italic">
                                        Querying Server Data...
                                    </span>
                                </td>
                                <td id="players_{$server.sid}" class="text:center">
                                    <span class="text:italic">
                                        N/A
                                    </span>
                                </td>
                                <td>
                                    <ul class="list-reset list-margin:right flex flex:ai:center flex-jc:center">
                                        {if $server.rcon_access}
                                            <li>
                                                <a class="button button-light"
                                                    href="index.php?p=admin&c=servers&o=rcon&id={$server.sid}">
                                                    RCON
                                                </a>
                                            </li>
                                        {/if}
                                        <li>
                                            <a class="button button-light"
                                                href="index.php?p=admin&c=servers&o=admincheck&id={$server.sid}">
                                                Admins
                                            </a>
                                        </li>
                                        {if $permission_editserver}
                                            <li>
                                                <a class="button button-light"
                                                    href="index.php?p=admin&c=servers&o=edit&id={$server.sid}">
                                                    Edit
                                                </a>
                                            </li>
                                        {/if}
                                        {if $pemission_delserver}
                                            <li>
                                                <button class="button button-light"
                                                    onclick="RemoveServer({$server.sid}, '{$server.ip}:{$server.port}');">
                                                    Delete
                                                </button>
                                            </li>
                                        {/if}
                                    </ul>
                                </td>
                            </tr>
                        {/foreach}
                    </tbody>
                </table>
            </div>

            {if $permission_addserver}
                <div class="margin-top">
                    <button class="button button-light" title="Click here to upload a screenshot of a map. Use the mapname as filename." id="upload"
                        onclick="childWindow=open('pages/admin.uploadmapimg.php','upload','resizable=yes,width=300,height=130');">
                        Upload Map Image
                    </button>
                    <div id="mapimg.msg" class="message message:error margin-top:half" style="display: none;"></div>
                </div>
            {/if}
        {/if}
    </div>
</div>