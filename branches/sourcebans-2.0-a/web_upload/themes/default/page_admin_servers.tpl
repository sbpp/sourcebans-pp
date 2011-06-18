          <div id="admin-page-menu">
            <ul>
              {if $permission_list_servers}
              <li id="tab-list"><a href="#list">{$lang_list_servers}</a></li>
              {/if}
              {if $permission_add_servers}
              <li id="tab-add"><a href="#add">{$lang_add_server}</a></li>
              {/if}
              {if $permission_import_servers}
              <li id="tab-import"><a href="#import">{$lang_import_servers}</a></li>
              {/if}
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
            </ul>
            <br />
            <div align="center">
              <img alt="{$page_title}" src="themes/{$theme_dir}/images/admin/servers.png" title="{$page_title}" />
            </div>
          </div>
          <div id="admin-page-content">
            {if $permission_list_servers}
            <div id="pane-list">
              <h3>{$lang_servers} (<span id="server_count">{$servers|@count}</span>)</h3>
              {if $permission_config}
              <p>To view the database config file you need to upload to your game server, click <a href="{build_url _=admin_servers_config.php}">here</a>.</p>
              {/if}
              <table width="100%" cellpadding="1">
                <tr>
                  <th width="48%">{$lang_hostname}</td>
                  <th width="6%">{$lang_players}</td>
                  <th width="5%">Mod</td>
                  <th>{$lang_action}</td>
                </tr>
                {foreach from=$servers item=server key=server_id}
                <tr>
                  <td id="host_{$server_id}" style="border-bottom: solid 1px #ccc">Querying Server Data...</td>
                  <td id="players_{$server_id}" style="border-bottom: solid 1px #ccc">N/A</td>
                  <td style="border-bottom: solid 1px #ccc"><img src="images/games/{$server.mod_icon}" alt="{$server.mod_name|escape}" title="{$server.mod_name|escape}" /></td>
                  <td style="border-bottom: solid 1px #ccc">
                    {if $permission_rcon}
                    <a href="{build_url _=admin_servers_rcon.php id=$server_id}">RCON</a> -
                    {/if}
                    <a href="{build_url _=admin_servers_admins.php id=$server_id}">{$lang_admins}</a>
                    {if $permission_edit_servers}
                    - <a href="{build_url _=admin_servers_edit.php id=$server_id}">{$lang_edit}</a>
                    {/if}
                    {if $permission_delete_servers}
                    - <a href="#" onclick="DeleteServer({$server_id}, $('host_{$server_id}').get('text'));">{$lang_delete}</a>
                    {/if}
                  </td>
                </tr>
                {/foreach}
              </table>
            </div>
            {/if}
            {if $permission_add_servers}
            <form action="{$active}" id="pane-add" method="post">
              <fieldset>
                <h3>{$lang_add_server|ucwords}</h3>
                <p>{$lang_help_desc}</p>
                <div>
                  <label for="ip">{help_icon title="$lang_ip_address" desc="This is the IP address to your server. You can also type a domain, if you have one setup."}{$lang_ip_address}</label>
                  <input class="submit-fields" {nid id="ip"} />
                </div>
                <div>
                  <label for="port">{help_icon title="$lang_port" desc="This is the port that the server is running off. &lt;br /&gt;&lt;br /&gt;&lt;em&gt;Default: 27015&lt;/em&gt;"}{$lang_port}</label>
                  <input class="submit-fields" {nid id="port"} value="27015" />
                </div>
                <div>
                  <label for="rcon">{help_icon title="$lang_rcon_password" desc="This is your servers RCON password. This can be found in your server.cfg file next to &lt;em&gt;rcon_password&lt;/em&gt;.&lt;br /&gt;&lt;br /&gt;This will be used to allow admins to administrate the server though the web interface."}{$lang_rcon_password}</label>
                  <input class="submit-fields" {nid id="rcon"} type="password" />
                </div>
                <div>
                  <label for="rcon_confirm">{help_icon title="$lang_confirm_rcon_password" desc="Please re-type your rcon password to avoid 'typos'"}{$lang_confirm_rcon_password}</label>
                  <input class="submit-fields" {nid id="rcon_confirm"} type="password" />
                </div>
                <div>
                  <label for="mod">{help_icon title="Server Mod" desc="Select the mod that your server is currently running."}Server Mod</label>
                  <select class="submit-fields" {nid id="mod"}>
                    <option value="-2">Please Select...</option>
                    {foreach from=$mods item=mod key=mod_id}
                    <option value="{$mod_id}">{$mod.name|escape}</option>
                    {/foreach}
                  </select>
                </div>
                <div>
                  <label for="enabled">{help_icon title="$lang_enabled" desc="Enables the server to be shown on the public servers list."}{$lang_enabled}</label>
                  <input checked="checked" {nid id="enabled"} type="checkbox" /> 
                </div>
                <div>
                  <label for="groups">{help_icon title="$lang_server_groups" desc="Choose a group to add this server to. Server groups are used for adding admins to specific sets of servers."}{$lang_server_groups}</label>
                  {foreach from=$server_groups item=group key=group_id}
                  <div class="srv_group">
                    <label align="right" for="group_{$group_id}">{$group.name}</label>
                    <input id="group_{$group_id}" name="groups[]" type="checkbox" value="{$group_id}" />
                  </div>
                  {/foreach}
                </div>
                <div class="center">
                  <input name="action" type="hidden" value="add" />
                  <input class="btn ok" type="submit" value="{$lang_save}" />
                  <input class="back btn cancel" type="button" value="{$lang_back}" />
                </div>
              </fieldset>
            </form>
            {/if}
            {if $permission_import_servers}
            <form action="{$active}" enctype="multipart/form-data" id="pane-import" method="post">
              <fieldset>
                <h3>{$lang_import_servers|ucwords}</h3>
                <p>{$lang_help_desc}</p>
                <label for="file">{help_icon title="$lang_file" desc="Select the admins.cfg file to upload and add admins."}{$lang_file}</label>
                <input class="submit-fields" {nid id="file"} type="file" />
                <div class="center">
                  <input name="action" type="hidden" value="import" />
                  <input class="btn ok" type="submit" value="{$lang_save}" />
                  <input class="back btn cancel" type="button" value="{$lang_back}" />
                </div>
              </fieldset>
            </form>
            {/if}
          </div>