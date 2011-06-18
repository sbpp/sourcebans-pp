          <h3>{$language->bans} (<span id="bans_count">{$total}</span>)</h3>
          <br />
          <div class="center">
            <table width="80%" class="listtable" cellpadding="0" cellspacing="0">
              <tr class="sea_open">
                <th class="left" colspan="3">{$language->advanced_search} <span class="normal">({$language->click})</span></th>
              </tr>
              <tr>
                <td>
                  <form action="" class="panel" method="get">
                    <fieldset>
                      <table class="listtable" cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                          <td class="listtable_1 center" width="8%"><input id="name" name="type" type="radio" value="name" /></td>
                          <td class="listtable_1" width="26%">{$language->name}</td>
                          <td class="listtable_1" width="66%"><input name="search" onmouseup="$('name').checked = true" class="sea_inputbox" style="width: 249px;" /></td>
                        </tr>
                        <tr>
                          <td class="listtable_1 center"><input id="steam" name="type" type="radio" value="steam" /></td>
                          <td class="listtable_1">Steam ID</td>
                          <td class="listtable_1"><input name="search" onmouseup="$('steam').checked = true" class="sea_inputbox" style="width: 249px;" /></td>
                        </tr>
                        <tr>
                          <td class="listtable_1 center"><input id="ip" name="type" type="radio" value="ip" /></td>
                          <td class="listtable_1">{$language->ip_address}</td>
                          <td class="listtable_1"><input name="search" onmouseup="$('ip').checked = true" class="sea_inputbox" style="width: 249px;" /></td>
                        </tr>
                        <tr>
                          <td class="listtable_1 center"><input id="reason" name="type" type="radio" value="reason" /></td>
                          <td class="listtable_1">{$language->reason}</td>
                          <td class="listtable_1"><input name="search" onmouseup="$('reason').checked = true" class="sea_inputbox" style="width: 249px;" /></td>
                        </tr>
                        <tr>
                          <td class="listtable_1 center"><input id="date" name="type" type="radio" value="date" /></td>
                          <td class="listtable_1">{$language->date}</td>
                          <td class="listtable_1">
                            <input id="day" value="DD" onmouseup="$('date').checked = true" class="sea_inputbox" style="width: 79px;" />
                            <input id="month" value="MM" onmouseup="$('date').checked = true" class="sea_inputbox" style="width: 79px;" />
                            <input id="year" value="YY" onmouseup="$('date').checked = true" class="sea_inputbox" style="width: 79px;" />
                          </td>
                        </tr>
                        <tr>
                          <td class="listtable_1 center"><input id="admin" name="type" type="radio" value="admin" /></td>
                          <td class="listtable_1">{$language->admin}</td>
                          <td class="listtable_1">
                            <select name="search" onmouseup="$('admin').checked = true" class="sea_inputbox" style="width: 251px;">
                              <option value="0">CONSOLE</option>
                              {foreach from=$admins item=admin}
                              <option value="{$admin->id}">{$admin->name|escape}</option>
                              {/foreach}
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td class="listtable_1 center"><input id="server" name="type" type="radio" value="server" /></td>
                          <td class="listtable_1">{$language->server}</td>
                          <td class="listtable_1">
                            <select name="search" onmouseup="$('server').checked = true" class="sea_inputbox" style="width: 251px;">
                              <option value="0">SourceBans</option>
                              {foreach from=$servers item=server}
                              <option id="host_{$server->id}" value="{$server->id}">Querying Server Data...</option>
                              {/foreach}
                            </select>
                          </td>
                        </tr>
                        <tr>
                          <td class="center" colspan="3"><input class="btn ok" type="submit" value="{$language->search}" /></td>
                        </tr>
                      </table>
                    </fieldset>
                  </form>
                </td>
              </tr>
            </table>
          </div>
          <br />
          <div id="bans-nav">
            {eval var=$language->displaying_results}
            {if $total_pages > 1}
            {if $uri->page > 1}
            | <strong><a href="{build_query page=$uri->page-1}"><img alt="{$language->prev|ucfirst}" src="{$uri->base}/images/left.gif" style="vertical-align: middle" title="{$language->prev|ucfirst}" /> {$language->prev}</a></strong>
            {/if}
            {if $uri->page < $total_pages}
            | <strong><a href="{if !empty($uri->page)}{build_query page=$uri->page+1}{else}{build_query page=2}{/if}">{$language->next} <img alt="{$language->next|ucfirst}" src="{$uri->base}/images/right.gif" style="vertical-align: middle" title="{$language->next|ucfirst}" /></a></strong>
            {/if}
            <select onchange="window.location = '{build_query page=''}' + this.options[this.selectedIndex].value;">
              {section loop=$total_pages name=page}
              <option{if $uri->page == $smarty.section.page.iteration} selected="selected"{/if} value="{$smarty.section.page.iteration}">{$smarty.section.page.iteration}</option>
              {/section}
            </select>
            {/if}
          </div>
          <a href="{if isset($uri->hideinactive)}{build_query hideinactive=null}{else}{build_query hideinactive='true'}{/if}">{if isset($uri->hideinactive)}{$language->show_inactive}{else}{$language->hide_inactive}{/if}</a>
          <form action="" id="bans" method="post">
            <fieldset>
              <table width="100%" cellspacing="0" cellpadding="0" class="listtable flCenter">
                <tr>
                  <th class="icon"><input {nid id="bans_select"} type="checkbox" value="-1" /></th>
                  <th class="date">
                    {if $sort != "game"}
                    <a href="{build_query sort=game}">{$language->game}</a>
                    {elseif $order == "desc"}
                    <a class="sort_desc" href="{build_query order=asc sort=game}">{$language->game}</a>
                    {else}
                    <a class="sort_asc" href="{build_query order=desc sort=game}">{$language->game}</a>
                    {/if}
                    {if !$settings->bans_hide_ip}
                    /
                    {if $sort != "country"}
                    <a href="{build_query sort=country}">{$language->country}</a>
                    {elseif $order == "desc"}
                    <a class="sort_desc" href="{build_query order=asc sort=country}">{$language->country}</a>
                    {else}
                    <a class="sort_asc" href="{build_query order=desc sort=country}">{$language->country}</a>
                    {/if}
                  </th>
                  {/if}
                  <th class="date">
                    {if $sort != "time"}
                    <a href="{build_query sort=time}">{$language->date}/{$language->time}</a>
                    {elseif $order == "desc"}
                    <a class="sort_desc" href="{build_query order=asc sort=time}">{$language->date}/{$language->time}</a>
                    {else}
                    <a class="sort_asc" href="{build_query order=desc sort=time}">{$language->date}/{$language->time}</a>
                    {/if}
                  </th>
                  <th>
                    {if $sort != "name"}
                    <a href="{build_query sort=name}">{$language->name}</a>
                    {elseif $order == "desc"}
                    <a class="sort_desc" href="{build_query order=asc sort=name}">{$language->name}</a>
                    {else}
                    <a class="sort_asc" href="{build_query order=desc sort=name}">{$language->name}</a>
                    {/if}
                  </th>
                  {if !$settings->bans_hide_admin}
                  <th width="11%">
                    {if $sort != "admin"}
                    <a href="{build_query sort=admin}">{$language->admin}</a>
                    {elseif $order == "desc"}
                    <a class="sort_desc" href="{build_query order=asc sort=admin}">{$language->admin}</a>
                    {else}
                    <a class="sort_asc" href="{build_query order=desc sort=admin}">{$language->admin}</a>
                    {/if}
                  </th>
                  {/if}
                  <th class="length">
                    {if $sort != "length"}
                    <a href="{build_query sort=length}">{$language->length}</a>
                    {elseif $order == "desc"}
                    <a class="sort_desc" href="{build_query order=asc sort=length}">{$language->length}</a>
                    {else}
                    <a class="sort_asc" href="{build_query order=desc sort=length}">{$language->length}</a>
                    {/if}
                  </th>
                </tr>
                {foreach from=$bans item=ban key=ban_id}
                <tr class="opener tbl_out">
                  <td class="listtable_1 icon"><input name="bans[]" type="checkbox" value="{$ban_id}" /></td>
                  <td class="listtable_1 date">
                    {if isset($ban->server)}
                    <img alt="{$ban->server->game->name|escape}" class="icon" src="{$uri->base}/images/games/{$ban->server->game->icon}" title="{$ban->server->game->name|escape}" />
                    {else}
                    <img alt="SourceBans" class="icon" src="{$uri->base}/images/games/web.png" title="SourceBans" />
                    {/if}
                    {if !$settings->bans_hide_ip}
                    {if empty($ban->country->code)}
                    <img alt="{$language->unknown}" class="icon" src="{$uri->base}/images/countries/unknown.gif" title="{$language->unknown}" />
                    {else}
                    <img alt="{$ban->country->name|escape}" class="icon" src="{$uri->base}/images/countries/{$ban->country->code|strtolower}.gif" title="{$ban->country->name|escape}" />
                    {/if}
                    {/if}
                  </td>
                  <td class="listtable_1 date">{$ban->insert_time|date_format:$settings->date_format}</td>
                  <td class="listtable_1">
                    {if empty($ban->name)}
                    <em class="not_applicable">no nickname present</em>
                    {else}
                    {$ban->name|escape}
                    {/if}
                  </td>
                  {if !$settings->bans_hide_admin}
                  <td class="listtable_1">{$ban->admin->name|escape}</td>
                  {/if}
                  <td class="listtable_1{if !empty($ban->status)}_unbanned{/if} length">{$ban->length*60|time_format}{if !empty($ban->status)} ({$ban->status}){/if}</td>
                </tr>
                <tr>
                  <td colspan="7" align="center">
                    <div class="opener">
                      <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                        <tr>
                          <th class="bold" colspan="3">{$language->details}</th>
                        </tr>
                        <tr>
                          <td class="listtable_1" width="20%">{$language->name}</td>
                          <td class="listtable_1">
                            {if empty($ban->name)}
                            <em class="not_applicable">no nickname present</em>
                            {else}
                            {$ban->name|escape}
                            {/if}
                          </td>
                          {if $user->permission_bans} 
                          <td width="30%" rowspan="12" class="listtable_2 opener">
                            <ul class="ban-edit">
                              {if !empty($ban->status)}
                              <li><a href="#" onclick="RebanBan({$ban_id}, '{$ban->name}'); return false;"><img alt="{$language->reban|ucwords}" class="icon" src="{$uri->base}/images/forbidden.gif" title="{$language->reban|ucwords}" /> {$language->reban|ucwords}</a></li>
                              {/if}
                              <li><a href="{if $ban->demo_count}{build_uri controller=getdemo id=$ban_id type=$smarty.const.BAN_TYPE}{else}#{/if}"><img alt="{$language->demo}" class="icon" src="{$uri->base}/images/demo.gif" title="{$language->demo}" /> {if $ban->demo_count}Review Demo{else}No Demos{/if}</a></li>
                              <li><a href="{build_uri controller=comments action=add id=$ban_id type=$smarty.const.BAN_TYPE}"><img alt="{$language->add_comment|ucwords}" class="icon" src="{$uri->base}/images/details.gif" title="{$language->add_comment|ucwords}" /> {$language->add_comment|ucwords}</a></li>
                              {if $user->permission_edit_all_bans || ($user->permission_edit_own_bans && $ban->admin->id == $user->id)}
                              <li><a href="{build_uri controller=bans action=edit id=$ban_id}"><img alt="{$language->edit_ban|ucwords}" src="{$uri->base}/images/edit.gif" class="icon" title="{$language->edit_ban|ucwords}" /> {$language->edit_ban|ucwords}</a></li>
                              {/if}
                              {if empty($ban->status) && ($user->permission_unban_all_bans || ($user->permission_unban_own_bans && $ban->admin_id == $user->id))}
                              <li><a href="#" onclick="UnbanBan({$ban_id}, '{$ban->name}'); return false;"><img alt="{$language->unban|ucwords}" class="icon" src="{$uri->base}/images/locked.gif" title="{$language->unban|ucwords}" /> {$language->unban|ucwords}</a></li>
                              {/if}
                              {if $user->permission_delete_bans || ($user->permission_edit_own_bans && $ban->admin_id == $user->id)}
                              <li><a href="#" onclick="DeleteBan({$ban_id}, '{$ban->name}'); return false;"><img alt="{$language->delete_ban|ucwords}" class="icon" src="{$uri->base}/images/delete.gif" title="{$language->delete_ban|ucwords}" /> {$language->delete_ban|ucwords}</a></li>
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
                            {if empty($ban->steam)}
                            <em class="not_applicable">no Steam ID present</em>
                            {else}
                            {$ban->steam}
                            {/if}
                          </td>
                        </tr>
                        {if !empty($ban->steam)}
                        <tr>
                          <td class="listtable_1">Steam Community</td>
                          <td class="listtable_1"><a href="http://steamcommunity.com/profiles/{$ban->community_id}">{$ban->community_id}</a></td>
                        </tr>
                        {/if}
                        {if !$settings->bans_hide_ip}
                        <tr>
                          <td class="listtable_1">{$language->ip_address}</td>
                          <td class="listtable_1">
                            {if empty($ban->ip)}
                            <em class="not_applicable">no IP address present</em>
                            {else}
                            {$ban->ip}
                            {/if}
                          </td>
                        </tr>
                        {/if}
                        <tr>
                          <td class="listtable_1">{$language->invoked_on}</td>
                          <td class="listtable_1">{$ban->insert_time|date_format:$settings->date_format}</td>
                        </tr>
                        <tr>
                          <td class="listtable_1">{$language->length}</td>
                          <td class="listtable_1">{$ban->length*60|time_format} ({if !empty($ban->status)}{$ban->status}{else}<span id="expires_{$ban_id}" title="{$ban->insert_time+$ban->length*60}"></span>{/if})</td>
                        </tr>
                        <tr>
                          <td class="listtable_1">{$language->expires_on}</td>
                          <td class="listtable_1">
                            {if $ban->length}
                            {$ban->insert_time+$ban->length*60|date_format:$settings->date_format}
                            {else}
                            <em class="not_applicable">{$language->not_applicable}.</em>
                            {/if}
                          </td>
                        </tr>
                        <tr>
                          <td class="listtable_1">{$language->reason}</td>
                          <td class="listtable_1">{$ban->reason|escape}</td>
                        </tr>
                        {if !$settings->bans_hide_admin}
                        <tr>
                          <td class="listtable_1">{$language->admin}</td>
                          <td class="listtable_1">{$ban->admin->name|escape}{if $user->permission_list_admins} ({$ban->admin->ip}){/if}</td>
                        </tr>
                        {/if}
                        <tr>
                          <td class="listtable_1">{$language->server}</td>
                          <td class="listtable_1"{if isset($ban->server)} id="host_{$ban->server->id}"{/if}>Querying Server Data...</td>
                        </tr>
                        {if isset($ban->unban_admin)}
                        <tr>
                          <td class="listtable_1">Unban reason</td>
                          <td class="listtable_1">
                            {if empty($ban->unban_reason)}
                            <em class="not_applicable">no reason present</em>
                            {else}
                            {$ban->unban_reason}
                            {/if}
                          </td>
                        </tr>
                        <tr>
                          <td class="listtable_1">{$language->unbanned_by}</td>
                          <td class="listtable_1">{$ban->unban_admin->name}</td>
                        </tr>
                        {/if}
                        <tr>
                          <td class="listtable_1">{$language->total_bans}</td>
                          <td class="listtable_1">{$ban->ban_count} <a href="{build_query search=$ban->steam type=steam}">({$language->search|strtolower})</a></td>
                        </tr>
                        {if $user->permission_list_comments}
                        <tr>
                          <td class="listtable_1" width="20%">{$language->comments}</td>
                          <td class="listtable_1" height="60">
                            <table width="100%">
                              {foreach from=$ban->comments item=comment key=comment_id name=comment}
                              <tr>
                                <td><strong>{$comment->admin->name}</strong></td>
                                <td align="right"><strong>{$comment->insert_time|date_format:$settings->date_format}</strong></td>
                                {if $user->permission_edit_comments || $comment->admin->id == $user->id}
                                <td align="right">
                                  <a href="{build_uri controller=comments action=edit id=$comment_id}" class="tips" title="<img src='images/edit.gif' alt='' style='vertical-align:middle' /> :: {$language->edit_comment|ucwords}"><img src='images/edit.gif' alt='' style='vertical-align:middle' /></a>
                                  <a href="#" class="tips" title="<img src='images/delete.gif' alt='' style='vertical-align:middle' /> :: {$language->delete_comment|ucwords}" onclick="DeleteComment('{$comment_id}', '{$smarty.const.BAN_TYPE}', '-1');"><img src="{$uri->base}/images/delete.gif" alt="{$language->delete_comment|ucwords}" style="vertical-align: middle" /></a>
                                </td>
                                {/if}
                              </tr>
                              <tr>
                                <td colspan="2">
                                  {$comment->message}
                                </td>
                              </tr>
                              {if !empty($comment->edit_admin->name)}
                              <tr>
                                <td class="comment_edit" colspan="3">
                                  last edit {$comment->edit_time|date_format:$settings->date_format} by {$comment->edit_admin->name}
                                </td>
                              </tr>
                              {/if}
                              {if !$smarty.foreach.comment.last}
                              <tr><td colspan="3"><hr /></td></tr>
                              {/if}
                              {foreachelse}
                              <tr><td>{$language->none}</td></tr>
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
              <ul class="context-menu">
                <li><a href="#">{$language->unban}</a></li>
                <li><a href="#">{$language->delete}</a></li>
              </ul>
            </fieldset>
          </form>