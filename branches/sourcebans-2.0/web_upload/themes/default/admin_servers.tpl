          <div id="admin-page-menu">
            <ul>
              {if $user->permission_list_servers}
              <li id="tab-list"><a href="#list">{$language->list_servers}</a></li>
              {/if}
              {if $user->permission_add_servers}
              <li id="tab-add"><a href="#add">{$language->add_server}</a></li>
              {/if}
              {if $user->permission_import_servers}
              <li id="tab-import"><a href="#import">{$language->import_servers}</a></li>
              {/if}
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
            </ul>
            <br />
            <div class="center">
              <img alt="{$action_title}" src="{$uri->base}/themes/{$theme}/images/admin/servers.png" title="{$action_title}" />
            </div>
          </div>
          <div id="admin-page-content">
            {if $user->permission_list_servers}
            <div id="pane-list">
              <h3>{$language->servers} (<span id="server_count">{$servers|@count}</span>)</h3>
              {if $user->permission_config}
              <p>To view the database config file you need to upload to your game server, click <a href="{build_uri controller=servers action=config}">here</a>.</p>
              {/if}
              <table width="100%" cellpadding="1">
                <tr>
                  <th class="nobold icon">{$language->game}</th>
                  <th width="48%">{$language->hostname}</th>
                  <th width="6%">{$language->players}</th>
                  <th>{$language->action}</th>
                </tr>
                {foreach from=$servers item=server}
                <tr>
                  <td class="center" style="border-bottom: solid 1px #ccc"><img src="{$uri->base}/images/games/{$server->game->icon}" alt="{$server->game->name|escape}" title="{$server->game->name|escape}" /></td>
                  <td id="host_{$server->id}" style="border-bottom: solid 1px #ccc">Querying Server Data...</td>
                  <td id="players_{$server->id}" style="border-bottom: solid 1px #ccc">N/A</td>
                  <td style="border-bottom: solid 1px #ccc">
                    {if $user->permission_rcon}
                    <a href="{build_uri controller=servers action=rcon id=$server->id}">RCON</a> -
                    {/if}
                    <a href="{build_uri controller=servers action=admins id=$server->id}">{$language->admins}</a>
                    {if $user->permission_edit_servers}
                    - <a href="{build_uri controller=servers action=edit id=$server->id}">{$language->edit}</a>
                    {/if}
                    {if $user->permission_delete_servers}
                    - <a href="#" onclick="DeleteServer({$server->id}, $('host_{$server->id}').get('text'));">{$language->delete}</a>
                    {/if}
                  </td>
                </tr>
                {/foreach}
              </table>
            </div>
            {/if}
            {if $user->permission_add_servers}
            <form action="" id="pane-add" method="post">
              <fieldset>
                <h3>{$language->add_server|ucwords}</h3>
                <p>{$language->help_desc}</p>
                <div>
                  <label for="ip">{help_icon title="`$language->ip_address`" desc="This is the IP address to your server. You can also type a domain, if you have one setup."}{$language->ip_address}</label>
                  <input class="submit-fields" {nid id="ip"} />
                </div>
                <div>
                  <label for="port">{help_icon title="`$language->port`" desc="This is the port that the server is running off. &lt;br /&gt;&lt;br /&gt;&lt;em&gt;Default: 27015&lt;/em&gt;"}{$language->port}</label>
                  <input class="submit-fields" {nid id="port"} value="27015" />
                </div>
                <div>
                  <label for="rcon">{help_icon title="`$language->rcon_password`" desc="This is your servers RCON password. This can be found in your server.cfg file next to &lt;em&gt;rcon_password&lt;/em&gt;.&lt;br /&gt;&lt;br /&gt;This will be used to allow admins to administrate the server though the web interface."}{$language->rcon_password}</label>
                  <input class="submit-fields" {nid id="rcon"} type="password" />
                </div>
                <div>
                  <label for="rcon_confirm">{help_icon title="`$language->confirm_rcon_password`" desc="Please re-type your rcon password to avoid 'typos'"}{$language->confirm_rcon_password}</label>
                  <input class="submit-fields" {nid id="rcon_confirm"} type="password" />
                </div>
                <div>
                  <label for="game">{help_icon title="$language->game" desc="Select the game that your server is currently running."}{$language->game}</label>
                  <select class="submit-fields" {nid id="game"}>
                    <option value="-2">Please Select...</option>
                    {foreach from=$games item=game}
                    <option value="{$game->id}">{$game->name|escape}</option>
                    {/foreach}
                  </select>
                </div>
                <div>
                  <label for="enabled">{help_icon title="`$language->enabled`" desc="Enables the server to be shown on the public servers list."}{$language->enabled}</label>
                  <input checked="checked" {nid id="enabled"} type="checkbox" /> 
                </div>
                <div>
                  <label for="groups">{help_icon title="`$language->server_groups`" desc="Choose a group to add this server to. Server groups are used for adding admins to specific sets of servers."}{$language->server_groups}</label>
                  {foreach from=$server_groups item=group}
                  <div class="srv_group">
                    <input id="group_{$group->id}" name="groups[]" type="checkbox" value="{$group->id}" />
                    <label for="group_{$group->id}">{$group->name}</label>
                  </div>
                  {/foreach}
                </div>
                <div class="center">
                  <input name="action" type="hidden" value="add" />
                  <input class="btn ok" type="submit" value="{$language->save}" />
                  <input class="back btn cancel" type="button" value="{$language->back}" />
                </div>
              </fieldset>
            </form>
            {/if}
            {if $user->permission_import_servers}
            <form action="" enctype="multipart/form-data" id="pane-import" method="post">
              <fieldset>
                <h3>{$language->import_servers|ucwords}</h3>
                <p>{$language->help_desc}</p>
                <label for="file">{help_icon title="`$language->file`" desc="Select the admins.cfg file to upload and add admins."}{$language->file}</label>
                <input class="submit-fields" {nid id="file"} type="file" />
                <div class="center">
                  <input name="action" type="hidden" value="import" />
                  <input class="btn ok" type="submit" value="{$language->save}" />
                  <input class="back btn cancel" type="button" value="{$language->back}" />
                </div>
              </fieldset>
            </form>
            {/if}
          </div>