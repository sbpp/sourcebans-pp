            <h3>{$lang_servers_list}</h3>
            <table class="listtable servers">
              <tr>
                <th class="nobold icon"><a href="{build_query sort=mod_name}">MOD</a></th>
                <th class="nobold icon"><a href="{build_query sort=os}">OS</a></th>
                <th class="nobold icon"><a href="{build_query sort=secure}">VAC</a></th>
                <th><a href="{build_query sort=hostname}">{$lang_hostname}</a></th>
                <th class="left info"><a href="{build_query sort=numplayers}">{$lang_players}</a></th>
                <th class="left info"><a href="{build_query sort=map}">{$lang_map}</a></th>
              </tr>
              {foreach from=$servers item=server key=server_id name=server}
              <tr class="opener tbl_out"{if $active != "servers.php"} onclick="window.location = '{build_url _=servers.php}#^{$smarty.foreach.server.index}';"{/if}>
                <td class="listtable_1 icon"><img alt="{$server.mod_name|escape}" class="icon" src="images/games/{$server.mod_icon}" title="{$server.mod_name|escape}" /></td>
                <td class="listtable_1 icon"><img alt="{$lang_unknown}" class="icon" id="os_{$server_id}" src="images/server_small.png" title="{$lang_unknown}" /></td>
                <td class="listtable_1 icon"><img alt="Valve Anti-Cheat" class="icon" id="vac_{$server_id}" src="images/shield.png" style="display: none;" title="Valve Anti-Cheat" /></td>
                <td class="listtable_1" id="host_{$server_id}">{if isset($server.hostname)}{$server.hostname}{else}Error connecting ({$server.ip}:{$server.port}){/if}</td>
                <td class="listtable_1" id="players_{$server_id}">{if isset($server.numplayers)}{$server.numplayers}/{$server.maxplayers}{else}N/A{/if}</td>
                <td class="listtable_1" id="map_{$server_id}">{if isset($server.map)}{$server.map}{else}N/A{/if}</td>
              </tr>
              {if $active == "servers.php"}
              <tr>
                <td colspan="7" align="center">
                  <div class="opener">
                    <table class="listtable" width="90%">
                      <tr>
                        <td class="listtable_2" id="playerlist_{$server_id}" valign="top">
                          {if empty($server.players)}
                          <h3>{$lang_no_players}</h3>
                          {else}
                          <table class="listtable" width="100%">
                            <tr>
                              <th>{$lang_name}</th>
                              <th width="10%">{$lang_score}</th>
                              <th width="40%">{$lang_time}</th>
                            </tr>
                            {foreach from=$server.players item=player}
                            <tr>
                              <td class="listtable_1">{$player.name|escape}</td>
                              <td class="listtable_1">{$player.score}</td>
                              <td class="listtable_1">{$player.time}</td>
                            </tr>
                            {/foreach}
                          </table>
                          {/if}
                        </td>
                        <td class="listtable_2 mapimg">
                          {if isset($server.map)}
                          <img alt="{$server.map}" id="mapimg_{$server_id}" src="images/maps/{$server.mod_folder}/{$server.map}.jpg" title="{$server.map}" />
                          {else}
                          <img alt="{$lang_unknown}" id="mapimg_{$server_id}" src="images/maps/unknown.jpg" title="{$lang_unknown}" />
                          {/if}
                          <br /><br />
                          <div align="center">
                            <strong>IP:{$lang_port} - {$server.ip}:{$server.port}</strong><br />
                            <input class="btn connect" rel="{$server.ip}:{$server.port}" type="button" value="{$lang_connect}" />
                            <input class="btn refresh" rel="{$server_id}" type="button" value="{$lang_refresh}" />
                          </div>
                          <br />
                        </td>
                      </tr>
                    </table>
                  </div>
                </td>
              </tr>
              {/if}
              {/foreach}
            </table>