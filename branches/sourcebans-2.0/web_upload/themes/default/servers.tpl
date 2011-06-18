            <h3>{$language->servers|ucwords}</h3>
            <table class="listtable servers">
              <tr>
                <th class="nobold icon">
                  {if $sort != "game"}
                  <a href="{build_query sort=game}">{$language->game}</a>
                  {elseif $order == "desc"}
                  <a class="sort_desc" href="{build_query order=asc sort=game}">{$language->game}</a>
                  {else}
                  <a class="sort_asc" href="{build_query order=desc sort=game}">{$language->game}</a>
                  {/if}
                </th>
                <th class="nobold icon">
                  {if $sort != "os"}
                  <a href="{build_query sort=os}">OS</a>
                  {elseif $order == "desc"}
                  <a class="sort_desc" href="{build_query order=asc sort=os}">OS</a>
                  {else}
                  <a class="sort_asc" href="{build_query order=desc sort=os}">OS</a>
                  {/if}
                </th>
                <th class="nobold icon">
                  {if $sort != "secure"}
                  <a href="{build_query sort=secure}">VAC</a>
                  {elseif $order == "desc"}
                  <a class="sort_desc" href="{build_query order=asc sort=secure}">VAC</a>
                  {else}
                  <a class="sort_asc" href="{build_query order=desc sort=secure}">VAC</a>
                  {/if}
                </th>
                <th>
                  {if $sort != "hostname"}
                  <a href="{build_query sort=hostname}">{$language->hostname}</a>
                  {elseif $order == "desc"}
                  <a class="sort_desc" href="{build_query order=asc sort=hostname}">{$language->hostname}</a>
                  {else}
                  <a class="sort_asc" href="{build_query order=desc sort=hostname}">{$language->hostname}</a>
                  {/if}
                </th>
                <th class="left info">
                  {if $sort != "numplayers"}
                  <a href="{build_query sort=numplayers}">{$language->players}</a>
                  {elseif $order == "desc"}
                  <a class="sort_desc" href="{build_query order=asc sort=numplayers}">{$language->players}</a>
                  {else}
                  <a class="sort_asc" href="{build_query order=desc sort=numplayers}">{$language->players}</a>
                  {/if}
                </th>
                <th class="left info">
                  {if $sort != "map"}
                  <a href="{build_query sort=map}">{$language->map}</a>
                  {elseif $order == "desc"}
                  <a class="sort_desc" href="{build_query order=asc sort=map}">{$language->map}</a>
                  {else}
                  <a class="sort_asc" href="{build_query order=desc sort=map}">{$language->map}</a>
                  {/if}
                </th>
              </tr>
              {foreach from=$servers item=server name=server}
              <tr class="opener tbl_out"{if $uri->controller != "servers"} onclick="window.location = '{build_uri controller=servers}#^{$smarty.foreach.server.index}';"{/if}>
                <td class="listtable_1 icon"><img alt="{$server->game->name|escape}" class="icon" src="{$uri->base}/images/games/{$server->game->icon}" title="{$server->game->name|escape}" /></td>
                <td class="listtable_1 icon"><img alt="{$language->unknown}" class="icon" id="os_{$server->id}" src="{$uri->base}/images/server_small.png" title="{$language->unknown}" /></td>
                <td class="listtable_1 icon"><img alt="Valve Anti-Cheat" class="icon" id="vac_{$server->id}" src="{$uri->base}/images/shield.png" title="Valve Anti-Cheat" /></td>
                <td class="listtable_1" id="host_{$server->id}">{if isset($server->hostname)}{$server->hostname}{else}Error connecting ({$server->ip}:{$server->port}){/if}</td>
                <td class="listtable_1" id="players_{$server->id}">{if isset($server->numplayers)}{$server->numplayers}/{$server->maxplayers}{else}N/A{/if}</td>
                <td class="listtable_1" id="map_{$server->id}">{if isset($server->map)}{$server->map}{else}N/A{/if}</td>
              </tr>
              {if $uri->controller == "servers"}
              <tr>
                <td colspan="7" align="center">
                  <div class="opener">
                    <table class="listtable" width="90%">
                      <tr>
                        <td class="listtable_2" id="playerlist_{$server->id}" valign="top">
                          {if empty($server->players)}
                          <h3>{$language->no_players}</h3>
                          {else}
                          <table class="listtable" width="100%">
                            <tr>
                              <th>{$language->name}</th>
                              <th width="10%">{$language->score}</th>
                              <th width="40%">{$language->time}</th>
                            </tr>
                            {foreach from=$server->players item=player}
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
                          {if isset($server->map)}
                          <img alt="{$server->map}" id="mapimg_{$server->id}" src="{$uri->base}/images/maps/{$server->game->folder}/{$server->map}.jpg" title="{$server->map}" />
                          {else}
                          <img alt="{$language->unknown}" id="mapimg_{$server->id}" src="{$uri->base}/images/maps/unknown.jpg" title="{$language->unknown}" />
                          {/if}
                          <br /><br />
                          <div class="center">
                            <strong>IP:{$language->port} - {$server->ip}:{$server->port}</strong><br />
                            <input class="btn connect" rel="{$server->ip}:{$server->port}" type="button" value="{$language->connect}" />
                            <input class="btn refresh" rel="{$server->id}" type="button" value="{$language->refresh}" />
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