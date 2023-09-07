<div class="layout_box flex:11 admin_tab_content">
    <div class="admin_tab_content_title">
        <h2><i class="fas fa-server"></i> Admin Server Access</h2>
    </div>

    <div class="padding">
        <div>
            Please select the servers and/or groups of servers you want this admin to have access to.
        </div>

        {if $row_count < 1}
            <div class="message message:error margin-top:half">
                You need to add a server or a server group, before you can setup
                admin server permissions
            </div>
        {else}
            <form action="" method="post">
                {if $group_list}
                    <h3>Server Groups</h3>

                    {foreach from=$group_list item="group"}
                        <div class="margin-bottom:half">
                            <input type="checkbox" id="group_{$group.gid}" class="form-check" name="group[]" value="g{$group.gid}"
                                onclick="" />
                            <label for="group_{$group.gid}" class="form-label:left">
                                {$group.name}
                            </label>
                        </div>
                    {/foreach}
                {/if}

                {if $server_list}
                    <h3>Servers</h3>

                    {foreach from=$server_list item="server"}
                        <div class="margin-bottom:half">
                            <input type="checkbox" class="form-check" name="servers[]" id="server_{$server.sid}"
                                value="s{$server.sid}" onclick="" />
                            <label for="server_{$server.sid}" id="host_{$server.sid}" class="form-label:left">
                                Please Wait...
                            </label>
                        </div>
                    {/foreach}

                {/if}

                <div class="flex flex-ai:center flex-jc:space-between margin-top">
                    {if $row_count > 0}
                        {sb_button text="Save Changes" class="button button-success" id="editadminserver" submit=true}
                    {/if}

                    {sb_button text="Back" onclick="history.go(-1)" class="button button-light" id="aback"}
                </div>
            </form>
        {/if}

        <script>
            {foreach from=$assigned_servers item="asrv"}
                if($('server_{$asrv.0}'))$('server_{$asrv.0}').checked = true;
                if($('group_{$asrv[1]}'))$('group_{$asrv[1]}').checked = true;
            {/foreach}
            {foreach from=$server_list item="server"}
                xajax_ServerHostPlayers({$server.sid}, "id", "host_{$server.sid}");
            {/foreach}
        </script>
    </div>
</div>
</div>