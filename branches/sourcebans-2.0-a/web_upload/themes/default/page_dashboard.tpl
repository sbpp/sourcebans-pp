          <div class="front-module-intro">
            <h3>{$dashboard_title}</h3>
            {$dashboard_text}
          </div>
          <div id="front-servers">
{include file='page_servers.tpl'}

          </div>
          <table class="flLeft front-module listtable">
            <tr>
              <td colspan="4">
                <div class="front-module-header">
                  <div class="flLeft">{$lang_latest_bans}</div>
                  <div class="flRight">{$lang_total_bans|ucwords}: {$total_bans}</div>
                </div>
              </td>
            </tr>
            <tr>
              <th class="nobold icon">MOD</th>
              <th class="date">{$lang_date}/{$lang_time}</th>
              <th>{$lang_name}</th>
              <th class="short_length">{$lang_length}</th>
            </tr>
            {foreach from=$bans item=ban name=ban}
            <tr class="tbl_out" onclick="window.location = '{build_url _=banlist.php}#^{$smarty.foreach.ban.index}';">
              <td class="listtable_1 center">{if !$ban.mod_icon}<img alt="Web Ban" class="icon" src="images/games/web.png" title="Web Ban" />{else}<img alt="{$ban.mod_name|escape}" class="icon" src="images/games/{$ban.mod_icon}" title="{$ban.mod_name|escape}" />{/if}</td>
              <td class="listtable_1 center">{$ban.time|date_format:$date_format}</td>
              <td class="listtable_1">
                {if empty($ban.name)}
                <em class="not_applicable">no nickname present</em>
                {else}
                {$ban.name|utf8_truncate:25|escape}
                {/if}
              </td>
              <td class="listtable_1{if !empty($ban.status)}_unbanned{/if}">{$ban.length|strtok:','}{if !empty($ban.status)} (<abbr title="{$ban.status}">{$ban.status|utf8_truncate:1:''}</abbr>){/if}</td>
            </tr>
            {/foreach}
          </table>
          <table class="flRight front-module listtable">
            <tr>
              <td colspan="3">
                <div class="front-module-header">
                  <div class="flLeft">{$lang_latest_blocked}</div>
                  <div class="flRight">{$lang_total_blocked|ucwords}: {$total_blocks}</div>
                </div>
              </td>
            </tr>
            <tr>
              <th class="icon">&nbsp;</th>
              <th class="date">{$lang_date}/{$lang_time}</th>
              <th>{$lang_name}</th>
            </tr>
            {foreach from=$blocks item=block}
            <tr class="tbl_out"{if !$log_nopopup} onclick="ShowBox('error', '{$block.name|escape:quotes}', 'This user tried to enter a SourceBans protected server at: {$block.date}&lt;div class=center&gt;&lt;a href=\'{build_url _=banlist.php search=$block.steam type=steamid}\'&gt;Click here for ban details.&lt;/a&gt;&lt;/div&gt;', '{build_url _=banlist.php search=$block.steam type=steamid}');"{/if}>
              <td class="listtable_1 icon"><img alt="Blocked Player" class="icon" src="images/forbidden.png" title="Blocked Player" /></td>
              <td class="listtable_1 center">{$block.time|date_format:$date_format}</td>
              <td class="listtable_1">{$block.name|utf8_truncate:42|escape}</td>
            </tr>
            {/foreach}
          </table>