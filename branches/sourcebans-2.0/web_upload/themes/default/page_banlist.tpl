          <h3>Banlist Overview - <em>{$lang_total_bans|ucwords}: {$total}</em></h3>
          <br />
          <div align="center">
            <table width="80%" class="listtable" cellpadding="0" cellspacing="0">
              <tr class="sea_open">
                <th class="left" colspan="3">{$lang_advanced_search} <span class="normal">({$lang_click})</span></th>
              </tr>
              <tr>
                <td>
                  <form action="{$active}" class="panel" method="get">
                    <fieldset>
                      <table class="listtable" cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                          <td class="listtable_1 center" width="8%"><input id="name" name="type" type="radio" value="name" /></td>
                          <td class="listtable_1" width="26%">{$lang_name}</td>
                          <td class="listtable_1" width="66%"><input name="search" onmouseup="$('name').checked = true" class="sea_inputbox" style="width: 249px;" /></td>
                        </tr>
                        <tr>
                          <td class="listtable_1 center"><input id="steam" name="type" type="radio" value="steam" /></td>
                          <td class="listtable_1">Steam ID</td>
                          <td class="listtable_1"><input name="search" onmouseup="$('steam').checked = true" class="sea_inputbox" style="width: 249px;" /></td>
                        </tr>
                        <tr>
                          <td class="listtable_1 center"><input id="ip" name="type" type="radio" value="ip" /></td>
                          <td class="listtable_1">{$lang_ip_address}</td>
                          <td class="listtable_1"><input name="search" onmouseup="$('ip').checked = true" class="sea_inputbox" style="width: 249px;" /></td>
                        </tr>
                        <tr>
                          <td class="listtable_1 center"><input id="reason" name="type" type="radio" value="reason" /></td>
                          <td class="listtable_1">{$lang_reason}</td>
                          <td class="listtable_1"><input name="search" onmouseup="$('reason').checked = true" class="sea_inputbox" style="width: 249px;" /></td>
                        </tr>
                        <tr>
                          <td class="listtable_1 center"><input id="date" name="type" type="radio" value="date" /></td>
                          <td class="listtable_1">{$lang_date}</td>
                          <td class="listtable_1">
                            <input id="day" value="DD" onmouseup="$('date').checked = true" class="sea_inputbox" style="width: 79px;" />
                            <input id="month" value="MM" onmouseup="$('date').checked = true" class="sea_inputbox" style="width: 79px;" />
                            <input id="year" value="YY" onmouseup="$('date').checked = true" class="sea_inputbox" style="width: 79px;" />
                          </td>
                        </tr>
                        <tr>
                          <td class="listtable_1 center"><input id="admin" name="type" type="radio" value="admin" /></td>
                          <td class="listtable_1">{$lang_admin}</td>
                          <td class="listtable_1">
                            <select name="search" onmouseup="$('admin').checked = true" class="sea_inputbox" style="width: 251px;">
                              <option value="0">CONSOLE</option>
                              {foreach from=$admins item=admin key=admin_id}
                              <option value="{$admin_id}">{$admin.name|escape}</option>
                              {/foreach}
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td class="listtable_1 center"><input id="server" name="type" type="radio" value="server" /></td>
                          <td class="listtable_1">{$lang_server}</td>
                          <td class="listtable_1">
                            <select name="search" onmouseup="$('server').checked = true" class="sea_inputbox" style="width: 251px;">
                              <option value="0">SourceBans</option>
                              {foreach from=$servers item=server key=server_id}
                              <option id="host_{$server_id}" value="{$server_id}">Querying Server Data...</option>
                              {/foreach}
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td class="center" colspan="3"><input class="btn ok" type="submit" value="{$lang_search}" /></td>
                        </tr>
                      </table>
                    </fieldset>
                  </form>
                </td>
              </tr>
            </table>
          </div>
          <br />
          <div id="banlist-nav">
            {eval var=$lang_displaying_results}
            {if $total_pages > 1}
            {if $smarty.get.page > 1}
            | <strong><a href="{build_query page=$smarty.get.page-1}"><img alt="{$lang_prev|ucfirst}" src="images/left.gif" style="vertical-align: middle" title="{$lang_prev|ucfirst}" /> {$lang_prev}</a></strong>
            {/if}
            {if $smarty.get.page < $total_pages}
            | <strong><a href="{if !empty($smarty.get.page)}{build_query page=$smarty.get.page+1}{else}{build_query page=2}{/if}">{$lang_next} <img alt="{$lang_next|ucfirst}" src="images/right.gif" style="vertical-align: middle" title="{$lang_next|ucfirst}" /></a></strong>
            {/if}
            <select onchange="window.location = '{build_query page=''}' + this.options[this.selectedIndex].value;">
              {section loop=$total_pages name=page}
              <option{if $smarty.get.page == $smarty.section.page.iteration} selected="selected"{/if} value="{$smarty.section.page.iteration}">{$smarty.section.page.iteration}</option>
              {/section}
            </select>
            {/if}
          </div>
          <a href="{if isset($smarty.get.hideinactive)}{build_query hideinactive=null}{else}{build_query hideinactive='true'}{/if}">{if isset($smarty.get.hideinactive)}{$lang_show_inactive}{else}{$lang_hide_inactive}{/if}</a>
          <form action="{$active}" id="banlist" method="post">
            <fieldset>
              <table width="100%" cellspacing="0" cellpadding="0" class="listtable flCenter">
                <tr>
                  <th class="icon"><input {nid id="bans_select"} type="checkbox" value="-1" /></th>
                  <th class="date"><a href="{build_query sort=mod_name}">MOD</a>{if $smarty.get.sort == "mod_name"} <div class="sort_{if $smarty.get.order == "desc"}asc{else}desc{/if}"><span>Sort desc</div>{/if}/<a href="{build_query sort=country_name}">{$lang_country}</a>{if $smarty.get.sort == "country_name"} <div class="sort_{if $smarty.get.order == "desc"}asc{else}desc{/if}"><span>Sort desc</div>{/if}</th>
                  <th class="date"><a href="{build_query sort=created}">{$lang_date}/{$lang_time}</a>{if $smarty.get.sort == "created"} <div class="sort_{if $smarty.get.order == "desc"}asc{else}desc{/if}"><span>Sort desc</div>{/if}</th>
                  <th><a href="{build_query sort=name}">{$lang_name}</a>{if $smarty.get.sort == "name"} <div class="sort_{if $smarty.get.order == "desc"}asc{else}desc{/if}"><span>Sort desc</div>{/if}</th>
                  {if !$hide_adminname}
                  <th width="11%"><a href="{build_query sort=admin_name}">{$lang_admin}</a>{if $smarty.get.sort == "admin_name"} <div class="sort_{if $smarty.get.order == "desc"}asc{else}desc{/if}"><span>Sort desc</div>{/if}</th>
                  {/if}
                  <th class="length"><a href="{build_query sort=length}">{$lang_length}</a>{if $smarty.get.sort == "length"} <div class="sort_{if $smarty.get.order == "desc"}asc{else}desc{/if}"><span>Sort desc</div>{/if}</th>
                </tr>
                {foreach from=$bans item=ban key=ban_id}
                <tr class="opener tbl_out">
                  <td class="listtable_1 icon"><input name="bans[]" type="checkbox" value="{$ban_id}" /></td>
                  <td class="listtable_1 date">
                    <img alt="{$ban.mod_name|escape}" class="icon" src="images/games/{$ban.mod_icon}" title="{$ban.mod_name|escape}" />
                    <img alt="{if empty($ban.country_code)}{$lang_unknown}{else}{$ban.country_name|escape}{/if}" class="icon" src="images/countries/{if empty($ban.country_code)}unknown{else}{$ban.country_code}{/if}.gif" title="{if empty($ban.country_code)}{$lang_unknown}{else}{$ban.country_name|escape}{/if}" />
                  </td>
                  <td class="listtable_1 date">{$ban.created|date_format:$date_format}</td>
                  <td class="listtable_1">
                    {if empty($ban.name)}
                    <em class="not_applicable">no nickname present</em>
                    {else}
                    {$ban.name|escape}
                    {/if}
                  </td>
                  {if !$hide_adminname}
                  <td class="listtable_1">{$ban.admin_name|escape}</td>
                  {/if}
                  <td class="listtable_1{if !empty($ban.status)}_unbanned{/if} length">{$ban.length}{if !empty($ban.status)} ({$ban.status}){/if}</td>
                </tr>
                <tr>
                  <td colspan="7" align="center">
                    <div class="opener">
                      <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                        <tr>
                          <th class="bold" colspan="3">{$lang_details}</th>
                        </tr>
                        <tr>
                          <td class="listtable_1" width="20%">{$lang_name}</td>
                          <td class="listtable_1">{$ban.name|escape}</td>
                          {if $permission_bans} 
                          <td width="30%" rowspan="12" class="listtable_2 opener">
                            <ul class="ban-edit">
                              {if !empty($ban.status)}
                              <li><a href="#" onclick="RebanBan({$ban_id}, '{$ban.name}'); return false;"><img alt="{$lang_reban|ucwords}" class="icon" src="images/forbidden.gif" title="{$lang_reban|ucwords}" /> {$lang_reban|ucwords}</a></li>
                              {/if}
                              <li><a href="{if $ban.demo_count}{build_url _=getdemo.php id=$ban_id} type=$smarty.const.BAN_COMMENTS}{else}#{/if}"><img alt="{$lang_demo}" class="icon" src="images/demo.gif" title="{$lang_demo}" /> {if $ban.demo_count}Review Demo{else}No Demos{/if}</a></li>
                              <li><a href="{build_url _=comments_add.php id=$ban_id type=B}"><img alt="{$lang_add_comment|ucwords}" class="icon" src="images/details.gif" title="{$lang_add_comment|ucwords}" /> {$lang_add_comment|ucwords}</a></li>
                              {if $permission_edit_all_bans || ($permission_edit_own_bans && $ban.admin_id == $smarty.cookies.sb_admin_id)}
                              <li><a href="{build_url _=admin_bans_edit.php id=$ban_id}"><img alt="{$lang_edit_ban|ucwords}" src="images/edit.gif" class="icon" title="{$lang_edit_ban|ucwords}" /> {$lang_edit_ban|ucwords}</a></li>
                              {/if}
                              {if empty($ban.status) && ($permission_unban_all_bans || ($permission_unban_own_bans && $ban.admin_id == $smarty.cookies.sb_admin_id))}
                              <li><a href="#" onclick="UnbanBan({$ban_id}, '{$ban.name}'); return false;"><img alt="{$lang_unban|ucwords}" class="icon" src="images/locked.gif" title="{$lang_unban|ucwords}" /> {$lang_unban|ucwords}</a></li>
                              {/if}
                              {if $permission_delete_bans || ($permission_edit_own_bans && $ban.admin_id == $smarty.cookies.sb_admin_id)}
                              <li><a href="#" onclick="DeleteBan({$ban_id}, '{$ban.name}'); return false;"><img alt="{$lang_delete_ban|ucwords}" class="icon" src="images/delete.gif" title="{$lang_delete_ban|ucwords}" /> {$lang_delete_ban|ucwords}</a></li>
                              {/if}
                              {foreach from=$admin_tabs item=tab}
                              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
                              {/foreach}
                            </ul>
                          </td>
                          {/if}
                        </tr>
                        <tr>
                          <td class="listtable_1">Steam ID</td>
                          <td class="listtable_1">
                            {if empty($ban.steam)}
                            <em class="not_applicable">no Steam ID present</em>
                            {else}
                            {$ban.steam}
                            {/if}
                          </td>
                        </tr>
                        {if !empty($ban.steam)}
                        <tr>
                          <td class="listtable_1">Steam Community</td>
                          <td class="listtable_1"><a href="http://steamcommunity.com/profiles/{$ban.community_id}">{$ban.community_id}</a></td>
                        </tr>
                        {/if}
                        <tr>
                          <td class="listtable_1">{$lang_ip_address}</td>
                          <td class="listtable_1">
                            {if empty($ban.ip)}
                            <em class="not_applicable">no IP address present</em>
                            {else}
                            {$ban.ip}
                            {/if}
                          </td>
                        </tr>
                        <tr>
                          <td class="listtable_1">{$lang_invoked_on}</td>
                          <td class="listtable_1">{$ban.created|date_format:$date_format}</td>
                        </tr>
                        <tr>
                          <td class="listtable_1">{$lang_length}</td>
                          <td class="listtable_1">{$ban.length} ({if !empty($ban.status)}{$ban.status}{else}<span id="expires_{$ban_id}" title="{$ban.ends}"></span>{/if})</td>
                        </tr>
                        <tr>
                          <td class="listtable_1">{$lang_expires_on}</td>
                          <td class="listtable_1">
                            {if $ban.length == $lang_permanent}
                            <em class="not_applicable">{$lang_not_applicable}.</em>
                            {else}
                            {$ban.ends|date_format:$date_format}
                            {/if}
                          </td>
                        </tr>
                        <tr>
                          <td class="listtable_1">{$lang_reason}</td>
                          <td class="listtable_1">{$ban.reason|escape}</td>
                        </tr>
                        {if !$hide_adminname}
                        <tr>
                          <td class="listtable_1">{$lang_admin}</td>
                          <td class="listtable_1">{$ban.admin_name|escape}{if $permission_list_admins} ({$ban.admin_ip}){/if}</td>
                        </tr>
                        {/if}
                        <tr>
                          <td class="listtable_1">{$lang_server}</td>
                          <td class="listtable_1" id="host_{$ban.server_id}">Querying Server Data...</td>
                        </tr>
                        {if $ban.status == $lang_unbanned}
                        <tr>
                          <td class="listtable_1">Unban reason</td>
                          <td class="listtable_1">
                            {if empty($ban.unban_reason)}
                            <em class="not_applicable">no reason present</em>
                            {else}
                            {$ban.unban_reason}
                            {/if}
                          </td>
                        </tr>
                        <tr>
                          <td class="listtable_1">{$lang_unbanned_by}</td>
                          <td class="listtable_1">{$ban.unban_admin_name}</td>
                        </tr>
                        {/if}
                        <tr>
                          <td class="listtable_1">{$lang_total_bans}</td>
                          <td class="listtable_1">{$ban.ban_count} <a href="{build_query search=$ban.steam type=steamid}">({$lang_search|strtolower})</a></td>
                        </tr>
                        {if $permission_list_comments}
                        <tr>
                          <td class="listtable_1" width="20%">{$lang_comments}</td>
                          <td class="listtable_1" height="60">
                            <table width="100%">
                              {foreach from=$ban.comments item=comment name=comment}
                              <tr>
                                <td><strong>{$comment.name}</strong></td>
                                <td align="right"><strong>{$comment.time|date_format:$date_format}</strong></td>
                                {if $edit_comments || $comment.admin_id == $smarty.cookies.sb_admin_id}
                                <td align="right">
                                  {$comment.editcomlink}
                                  <a href="#" class="tip" title="<img src='images/delete.gif' border='0' alt='' style='vertical-align:middle' /> :: Delete Comment" onclick="DeleteComment('{$comment.cid}', 'B', '-1');"><img src="images/delete.gif" alt="Delete Comment" style="vertical-align: middle" /></a>
                                </td>
                                {/if}
                              </tr>
                              <tr>
                                <td colspan="2">
                                  {$comment.message}
                                </td>
                              </tr>
                              {if !empty($comment.edit_name)}
                              <tr>
                                <td colspan="3" style="font-size: 6pt; color: grey;">
                                  last edit {$comment.edit_time|date_format:$date_format} by {$comment.edit_admin_name}
                                </td>
                              </tr>
                              {/if}
                              {if !$smarty.foreach.comment.last}
                              <tr><td colspan="3"><hr /></td></tr>
                              {/if}
                              {foreachelse}
                              <tr><td>{$lang_none}</td></tr>
                              {/foreach}
                            </table>
                          </td>
                        </tr>
                        {/if}
                      </table>
                    </div>
                  </td>
                </tr>
                {/foreach}
              </table>
              <ul id="context-menu">
                <li><a href="#">{$lang_unban}</a></li>
                <li><a href="#">{$lang_delete}</a></li>
              </ul>
            </fieldset>
          </form>