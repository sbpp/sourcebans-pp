          <div class="front-module-intro">
            <h3>{$settings->dashboard_title}</h3>
            {$settings->dashboard_text}
          </div>
          <div id="front-servers">
{include file='servers.tpl'}

          </div>
          <table class="flLeft front-module listtable">
            <tr>
              <td colspan="4">
                <div class="front-module-header">
                  <div class="flLeft">{$language->latest_bans}</div>
                  <div class="flRight">{$language->total_bans|ucwords}: {$total_bans}</div>
                </div>
              </td>
            </tr>
            <tr>
              <th class="nobold icon">{$language->game}</th>
              <th class="date">{$language->date}/{$language->time}</th>
              <th>{$language->name}</th>
              <th class="short_length">{$language->length}</th>
            </tr>
{foreach from=$bans item=ban name=ban}
            <tr class="tbl_out" onclick="window.location = '{build_uri controller=bans}#^{$smarty.foreach.ban.index}';">
              <td class="listtable_1 center">
                {if isset($ban->server)}
                <img alt="{$ban->server->game->name|escape}" class="icon" src="{$uri->base}/images/games/{$ban->server->game->icon}" title="{$ban->server->game->name|escape}" />
                {else}
                <img alt="SourceBans" class="icon" src="{$uri->base}/images/games/web.png" title="SourceBans" />
                {/if}
              </td>
              <td class="listtable_1 center">{$ban->insert_time|date_format:$settings->date_format}</td>
              <td class="listtable_1">
{if empty($ban->name)}
                <em class="not_applicable">no nickname present</em>
{else}
                {$ban->name|utf8_truncate:25|escape}
{/if}
              </td>
              <td class="listtable_1{if !empty($ban->status)}_unbanned{/if}">{$ban->length*60|time_format|strtok:','}{if !empty($ban->status)} (<abbr title="{$ban->status}">{$ban->status|utf8_truncate:1:''}</abbr>){/if}</td>
            </tr>
{/foreach}
          </table>
          <table class="flRight front-module listtable">
            <tr>
              <td colspan="3">
                <div class="front-module-header">
                  <div class="flLeft">{$language->latest_blocked}</div>
                  <div class="flRight">{$language->total_blocked|ucwords}: {$total_blocks}</div>
                </div>
              </td>
            </tr>
            <tr>
              <th class="icon">&nbsp;</th>
              <th class="date">{$language->date}/{$language->time}</th>
              <th>{$language->name}</th>
            </tr>
{foreach from=$blocks item=block}
            <tr class="tbl_out"{if !$settings->disable_log_popup} onclick="ShowBox('error', '{$block->ban->name|escape:quotes}', 'This user tried to enter a SourceBans protected server at: {$block->date}&lt;div class=center&gt;&lt;a href=\'{build_uri controller=bans search=$block->ban->steam type=steam}\'&gt;Click here for ban details.&lt;/a&gt;&lt;/div&gt;', '{build_uri controller=bans search=$block->ban->steam type=steam}');"{/if}>
              <td class="listtable_1 icon"><img alt="Blocked Player" class="icon" src="{$uri->base}/images/forbidden.gif" title="Blocked Player" /></td>
              <td class="listtable_1 center">{$block->insert_time|date_format:$settings->date_format}</td>
              <td class="listtable_1">{$block->ban->name|utf8_truncate:42|escape}</td>
            </tr>
{/foreach}
          </table>