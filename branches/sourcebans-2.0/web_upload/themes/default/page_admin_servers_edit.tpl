          <div id="admin-page-menu">
            <ul>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
              <li class="active"><a class="back" href="#">{$lang_back}</a></li>
            </ul>
            <br />
            <div align="center">
              <img alt="{$page_title}" src="themes/{$theme_dir}/images/admin/servers.png" title="{$page_title}" />
            </div>
          </div>
          <form action="{$active}" id="admin-page-content" method="post">
            <fieldset>
              <h3>{$lang_edit_server|ucwords}</h3>
              <p>{$lang_help_desc}</p>
              <div>
                <label for="ip">{help_icon title="$lang_ip_address" desc="This is the IP address to your server. You can also type a domain, if you have one setup."}{$lang_ip_address}</label>
                <input class="submit-fields" {nid id="ip"} value="{$server_ip}" />
              </div>
              <div>
                <label for="port">{help_icon title="$lang_port" desc="This is the port that the server is running off. &lt;br /&gt;&lt;br /&gt;&lt;em&gt;Default: 27015&lt;/em&gt;"}{$lang_port}</label>
                <input class="submit-fields" {nid id="port"} value="{if empty($server_port)}27015{else}{$server_port}{/if}" />
              </div>
              <div>
                <label for="rcon">{help_icon title="RCON Password" desc="This is your server's RCON password. This can be found in your server.cfg file next to &lt;em&gt;rcon_password&lt;/em&gt;.&lt;br /&gt;&lt;br /&gt;This will be used to allow admins to administrate the server through the web interface."}RCON Password</label>
                <input class="submit-fields" {nid id="rcon"} type="password" value="{if !empty($server_rcon)}xxxxxxxxxx{/if}" />
              </div>
              <div>
                <label for="rcon_confirm">{help_icon title="RCON Password (Confirm)" desc="Please re-type your rcon password to avoid 'typos'."}RCON Password (Confirm)</label>
                <input class="submit-fields" {nid id="rcon_confirm"} type="password" value="{if !empty($server_rcon)}xxxxxxxxxx{/if}" />
              </div>
              <div>
                <label for="mod">{help_icon title="Server Mod" desc="Select the mod that your server is currently running."}Server Mod</label>
                <select class="submit-fields" {nid id="mod"}>
                  {foreach from=$mods item=mod key=mod_id}
                  <option{if $mod_id == $server_mod} selected="selected"{/if} value="{$mod_id}">{$mod.name|escape}</option>
                  {/foreach}
                </select>
              </div>
              <div>
                <label for="enabled">{help_icon title="$lang_enabled" desc="Enables the server to be shown on the public servers list."}{$lang_enabled}</label>
                <input{if $server_enabled} checked="checked"{/if} {nid id="enabled"} type="checkbox" /> 
              </div>
              <div>
                <label for="groups">{help_icon title="$lang_server_groups" desc="Choose a group to add this server to. Server groups are used for adding admins to specific sets of servers."}{$lang_server_groups}</label>
                {foreach from=$groups item=group key=group_id}
                <div class="srv_group">
                  <label align="right" for="group_{$group_id}">{$group.name}</label>
                  <input{if in_array($group_id, $server_groups)} checked="checked"{/if} id="group_{$group_id}" name="groups[]" type="checkbox" value="{$group_id}" />
                </div>
                {/foreach}
              </div>
              <div class="center">
                <input name="id" type="hidden" value="{$smarty.get.id}" />
                <input class="btn ok" type="submit" value="{$lang_save}" />
                <input class="back btn cancel" type="button" value="{$lang_back}" />
              </div>
            </fieldset>
          </form>