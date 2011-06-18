          <div id="admin-page-menu">
            <ul>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
              <li class="active"><a class="back" href="#">{$language->back}</a></li>
            </ul>
            <br />
            <div class="center">
              <img alt="{$action_title}" src="{$uri->base}/themes/{$theme}/images/admin/servers.png" title="{$action_title}" />
            </div>
          </div>
          <form action="" id="admin-page-content" method="post">
            <fieldset>
              <h3>{$language->edit_server|ucwords}</h3>
              <p>{$language->help_desc}</p>
              <div>
                <label for="ip">{help_icon title="`$language->ip_address`" desc="This is the IP address to your server. You can also type a domain, if you have one setup."}{$language->ip_address}</label>
                <input class="submit-fields" {nid id="ip"} value="{$server->ip}" />
              </div>
              <div>
                <label for="port">{help_icon title="`$language->port`" desc="This is the port that the server is running off. &lt;br /&gt;&lt;br /&gt;&lt;em&gt;Default: 27015&lt;/em&gt;"}{$language->port}</label>
                <input class="submit-fields" {nid id="port"} value="{if empty($server->port)}27015{else}{$server->port}{/if}" />
              </div>
              <div>
                <label for="rcon">{help_icon title="`$language->rcon_password`" desc="This is your server's RCON password. This can be found in your server.cfg file next to &lt;em&gt;rcon_password&lt;/em&gt;.&lt;br /&gt;&lt;br /&gt;This will be used to allow admins to administrate the server through the web interface."}{$language->rcon_password}</label>
                <input class="submit-fields" {nid id="rcon"} type="password" value="{if !empty($server->rcon)}xxxxxxxxxx{/if}" />
              </div>
              <div>
                <label for="rcon_confirm">{help_icon title="`$language->confirm_rcon_password`" desc="Please re-type your rcon password to avoid 'typos'."}{$language->confirm_rcon_password}</label>
                <input class="submit-fields" {nid id="rcon_confirm"} type="password" value="{if !empty($server->rcon)}xxxxxxxxxx{/if}" />
              </div>
              <div>
                <label for="game">{help_icon title="$language->game" desc="Select the game that your server is currently running."}{$language->game}</label>
                <select class="submit-fields" {nid id="game"}>
                  {foreach from=$games item=game}
                  <option{if $game->id == $server->game->id} selected="selected"{/if} value="{$game->id}">{$game->name|escape}</option>
                  {/foreach}
                </select>
              </div>
              <div>
                <label for="enabled">{help_icon title="`$language->enabled`" desc="Enables the server to be shown on the public servers list."}{$language->enabled}</label>
                <input{if $server->enabled} checked="checked"{/if} {nid id="enabled"} type="checkbox" /> 
              </div>
              <div>
                <label for="groups">{help_icon title="`$language->server_groups`" desc="Choose a group to add this server to. Server groups are used for adding admins to specific sets of servers."}{$language->server_groups}</label>
                {foreach from=$groups item=group}
                <div class="srv_group">
                  <label align="right" for="group_{$group->id}">{$group->name|escape}</label>
                  <input{if in_array($group->id, $server->groups)} checked="checked"{/if} id="group_{$group->id}" name="groups[]" type="checkbox" value="{$group->id}" />
                </div>
                {/foreach}
              </div>
              <div class="center">
                <input name="id" type="hidden" value="{$uri->id}" />
                <input class="btn ok" type="submit" value="{$language->save}" />
                <input class="back btn cancel" type="button" value="{$language->back}" />
              </div>
            </fieldset>
          </form>