          <div id="admin-page-menu">
            <ul>
              {if $user->permission_list_admins}
              <li id="tab-list"><a href="#list">{$language->list_admins}</a></li>
              {/if}
              {if $user->permission_add_admins}
              <li id="tab-add"><a href="#add">{$language->add_admin}</a></li>
              {/if}
              {if $user->permission_import_admins}
              <li id="tab-import"><a href="#import">{$language->import_admins}</a></li>
              {/if}
              {if $user->permission_list_overrides}
              <li id="tab-overrides"><a href="#overrides">Overrides</a></li>
              {/if}
              {if $user->permission_list_actions}
              <li id="tab-actions"><a href="#actions">{$language->actions_log}</a></li>
              {/if}
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
            </ul>
            <br />
            <div class="center">
              <img alt="{$action_title}" src="{$uri->base}/themes/{$theme}/images/admin/admins.png" title="{$action_title}" />
            </div>
          </div>
          <div id="admin-page-content">
            {if $user->permission_list_admins}
            <div id="pane-list">
              <h3>{$language->admins} (<span id="admins_count">{$total}</span>)</h3>
              <p>Click on an admin to see more detailed information and actions to perform on them.</p>
              <div class="center">
                <table class="listtable" cellpadding="0" cellspacing="0" width="80%">
                  <tr class="sea_open">
                    <th class="left" colspan="3">{$language->advanced_search} <span class="normal">({$language->click})</span></th>
                  </tr>
                  <tr>
                    <td>
                      <form action="" class="panel" method="get">
                        <fieldset>
                          <table class="listtable" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                              <td class="listtable_1" width="8%" align="center"><input id="name_" name="type" type="radio" value="name" /></td>
                              <td class="listtable_1" width="26%">{$language->name}</td>
                              <td class="listtable_1" width="66%"><input id="nick" value="" onmouseup="$('name_').checked = true" class="sea_inputbox" style="width: 249px;" /></td>
                            </tr>
                            <tr>
                              <td align="center" class="listtable_1"><input id="steam_" type="radio" name="type" value="steam" /></td>
                              <td class="listtable_1">Steam ID</td>
                              <td class="listtable_1">
                                <input id="steam" onmouseup="$('steam_').checked = true" class="sea_inputbox" style="width: 145px;" />
                                <select id="steam_match" onmouseup="$('steam_').checked = true" class="sea_inputbox" style="width: 100px;">
                                  <option label="exact" value="0">Exact Match</option>
                                  <option label="partial" value="1">Partial Match</option>
                                </select>
                              </td>
                            </tr>
                            <tr>
                              <td align="center" class="listtable_1"><input id="srvadmgroup_" type="radio" name="type" value="srvadmgroup" /></td>
                              <td class="listtable_1">{$language->server_group}</td>
                              <td class="listtable_1">
                                <select id="srvadmgroup" onmouseup="$('srvadmgroup_').checked = true" class="sea_inputbox" style="width: 251px;">
                                  {foreach from=$server_groups item=group}
                                  <option value="{$group->id}">{$group->name}</option>
                                  {/foreach}
                                </select>
                              </td>
                            </tr>
                            <tr>
                              <td align="center" class="listtable_1"><input id="webadmgroup_" type="radio" name="type" value="webadmgroup" /></td>
                              <td class="listtable_1">{$language->web_group}</td>
                              <td class="listtable_1">
                                <select id="webadmgroup" onmouseup="$('webadmgroup_').checked = true" class="sea_inputbox" style="width: 251px;">
                                  {foreach from=$web_groups item=group}
                                  <option value="{$group->id}">{$group->name}</option>
                                  {/foreach}
                                </select>
                              </td>
                            </tr>
                            <tr>
                              <td class="listtable_1" align="center"><input id="admsrvflags_" name="type" type="radio" value="admsrvflags" /></td>
                              <td class="listtable_1">{$language->server_permissions}</td>
                              <td class="listtable_1">
                                <select id="admsrvflag" name="admsrvflag" size="5" multiple="multiple" onmouseup="$('admsrvflags_').checked = true" class="sea_inputbox" style="width: 251px;">
                                  <option value="{$smarty.const.SM_ROOT}">Root (Full Server Access)</option>
                                  <option value="{$smarty.const.SM_RESERVATION}">{$language->reservation_desc}</option>
                                  <option value="{$smarty.const.SM_GENERIC}">{$language->generic_desc}</option>
                                  <option value="{$smarty.const.SM_KICK}">{$language->kick_desc}</option>
                                  <option value="{$smarty.const.SM_BAN}">{$language->ban_desc}</option>
                                  <option value="{$smarty.const.SM_UNBAN}">{$language->unban_desc}</option>
                                  <option value="{$smarty.const.SM_SLAY}">{$language->slay_desc}</option>
                                  <option value="{$smarty.const.SM_CHANGEMAP}">{$language->changemap_desc}</option>
                                  <option value="{$smarty.const.SM_CVAR}">{$language->cvar_desc}</option>
                                  <option value="{$smarty.const.SM_CONFIG}">{$language->config_desc}</option>
                                  <option value="{$smarty.const.SM_CHAT}">{$language->chat_desc}</option>
                                  <option value="{$smarty.const.SM_VOTE}">{$language->vote_desc}</option>
                                  <option value="{$smarty.const.SM_PASSWORD}">{$language->password_desc}</option>
                                  <option value="{$smarty.const.SM_RCON}">{$language->rcon_desc}</option>
                                  <option value="{$smarty.const.SM_CHEATS}">{$language->cheats_desc}</option>
                                  <option value="{$smarty.const.SM_CUSTOM1}">{$language->custom1_desc}</option>
                                  <option value="{$smarty.const.SM_CUSTOM2}">{$language->custom2_desc}</option>
                                  <option value="{$smarty.const.SM_CUSTOM3}">{$language->custom3_desc}</option>
                                  <option value="{$smarty.const.SM_CUSTOM4}">{$language->custom4_desc}</option>
                                  <option value="{$smarty.const.SM_CUSTOM5}">{$language->custom5_desc}</option>
                                  <option value="{$smarty.const.SM_CUSTOM6}">{$language->custom6_desc}</option>
                                </select>
                              </td>
                            </tr>
                            <tr>
                              <td class="listtable_1" align="center"><input id="admwebflags_" name="type" type="radio" value="admwebflags" /></td>
                              <td class="listtable_1">{$language->web_permissions}</td>
                              <td class="listtable_1">
                                <select id="admwebflag" name="admwebflag" size="5" multiple="multiple" onmouseup="$('admwebflags_').checked = true" class="sea_inputbox" style="width: 251px;">
                                  <option value="OWNER">Owner (Full Web Access)</option>
                                  <option value="LIST_ADMINS">{$language->list_admins}</option>
                                  <option value="ADD_ADMINS">{$language->add_admins}</option>
                                  <option value="EDIT_ADMINS">{$language->edit_admins}</option>
                                  <option value="DELETE_ADMINS">{$language->delete_admins}</option>
                                  <option value="IMPORT_ADMINS">{$language->import_admins}</option>
                                  <option value="LIST_GROUPS">{$language->list_groups}</option>
                                  <option value="ADD_GROUPS">{$language->add_groups}</option>
                                  <option value="EDIT_GROUPS">{$language->edit_groups}</option>
                                  <option value="DELETE_GROUPS">{$language->delete_groups}</option>
                                  <option value="IMPORT_GROUPS">{$language->import_groups}</option>
                                  <option value="LIST_GAMES">{$language->list_games}</option>
                                  <option value="ADD_GAMES">{$language->add_games}</option>
                                  <option value="EDIT_GAMES">{$language->edit_games}</option>
                                  <option value="DELETE_GAMES">{$language->delete_games}</option>
                                  <option value="LIST_SERVERS">{$language->list_servers}</option>
                                  <option value="ADD_SERVERS">{$language->add_servers}</option>
                                  <option value="EDIT_SERVERS">{$language->edit_servers}</option>
                                  <option value="DELETE_SERVERS">{$language->delete_servers}</option>
                                  <option value="IMPORT_SERVERS">{$language->import_servers}</option>
                                  <option value="ADD_BANS">{$language->add_bans}</option>
                                  <option value="EDIT_OWN_BANS">{$language->edit_own_bans}</option>
                                  <option value="EDIT_GROUP_BANS">{$language->edit_group_bans}</option>
                                  <option value="EDIT_ALL_BANS">{$language->edit_all_bans}</option>
                                  <option value="UNBAN_OWN_BANS">{$language->unban_own_bans}</option>
                                  <option value="UNBAN_GROUP_BANS">{$language->unban_group_bans}</option>
                                  <option value="UNBAN_ALL_BANS">{$language->unban_all_bans}</option>
                                  <option value="DELETE_BANS">{$language->delete_bans}</option>
                                  <option value="IMPORT_BANS">{$language->import_bans}</option>
                                  <option value="BAN_PROTESTS">{$language->ban_protests}</option>
                                  <option value="BAN_SUBMISSIONS">{$language->ban_submissions}</option>
                                  <option value="NOTIFY_PROT">{$language->notify_protests}</option>
                                  <option value="NOTIFY_SUB">{$language->notify_submissions}</option>
                                  <option value="SETTINGS">{$language->settings}</option>
                                </select>
                              </td>
                            </tr>
                            <tr>
                              <td class="listtable_1" align="center"><input id="server_" name="type" type="radio" value="server" /></td>
                              <td class="listtable_1">{$language->server}</td>
                              <td class="listtable_1">
                                <select id="server" onmouseup="$('server_').checked = true" class="sea_inputbox" style="width: 251px;">
                                  {foreach from=$servers item=server}
                                  <option id="host_{$server->id}" value="{$server->id}">Querying Server Data...</option>
                                  {/foreach}
                                </select>
                              </td>
                            </tr>
                            <tr>
                              <td class="center" colspan="3"><input class="ok btn" type="submit" value="{$language->search}" /></td>
                            </tr>
                          </table>
                        </fieldset>
                      </form>
                    </td>
                  </tr>
                </table>
              </div>
              <div id="bans-nav">
                {eval var=$language->displaying_results}
                {if $total_pages > 1}
                {if $uri->page > 1}
                | <strong><a href="{build_query page=$uri->page-1}"><img alt="{$language->prev|ucfirst}" src="{$uri->base}/images/left.gif" style="vertical-align: middle;" title="{$language->prev|ucfirst}" /> {$language->prev}</a></strong>
                {/if}
                {if $uri->page < $total_pages}
                | <strong><a href="{if isset($uri->page)}{build_query page=$uri->page+1}{else}{build_query page=2}{/if}">{$language->next} <img alt="{$language->next|ucfirst}" src="{$uri->base}/images/right.gif" style="vertical-align: middle;" title="{$language->next|ucfirst}" /></a></strong>
                {/if}
                <select onchange="window.location = '{build_query page=''}' + this.options[this.selectedIndex].value;">
                  {section loop=$total_pages name=page}
                  <option{if $uri->page == $smarty.section.page.iteration} selected="selected"{/if} value="{$smarty.section.page.iteration}">{$smarty.section.page.iteration}</option>
                  {/section}
                </select>
                {/if}
              </div>
              <table width="99%" cellspacing="0" cellpadding="0" align="center">
                <tr>
                  <th class="icon"><input {nid id="admins_select"} type="checkbox" value="-1" /></th>
                  <th>
                    {if $sort != "name"}
                    <a href="{build_query sort=name}">{$language->name}</a>
                    {elseif $order == "desc"}
                    <a class="sort_desc" href="{build_query order=asc sort=name}">{$language->name}</a>
                    {else}
                    <a class="sort_asc" href="{build_query order=desc sort=name}">{$language->name}</a>
                    {/if}
                  </th>
                  <th width="30%">
                    {if $sort != "srv_groups"}
                    <a href="{build_query sort=srv_groups}">{$language->server_groups}</a>
                    {elseif $order == "desc"}
                    <a class="sort_desc" href="{build_query order=asc sort=srv_groups}">{$language->server_groups}</a>
                    {else}
                    <a class="sort_asc" href="{build_query order=desc sort=srv_groups}">{$language->server_groups}</a>
                    {/if}
                  </th>
                  <th width="30%">
                    {if $sort != "web_group"}
                    <a href="{build_query sort=web_group}">{$language->web_group}</a>
                    {elseif $order == "desc"}
                    <a class="sort_desc" href="{build_query order=asc sort=web_group}">{$language->web_group}</a>
                    {else}
                    <a class="sort_asc" href="{build_query order=desc sort=web_group}">{$language->web_group}</a>
                    {/if}
                  </th>
                </tr>
                {foreach from=$admins item=admin}
                <tr class="opener tbl_out">
                  <td class="listtable_1 icon"><input name="admins[]" type="checkbox" value="{$admin->id}" /></td>
                  <td class="admin-row">{$admin->name} (<a href="{build_uri controller=bans search=$admin->id type=admin}" title="Show bans">{$admin->ban_count} bans</a> | <a href="{build_uri controller=bans search=$admin->id type=nodemo}" title="Show bans without demo">{$admin->nodemo_count} w.d.</a>)</td>
                  <td class="admin-row">
                    {foreach from=$admin->srv_groups item=group name=server_groups}
                    {$group->name}{if !$smarty.foreach.server_groups.last}, {/if}
                    {foreachelse}
                    {$language->none}
                    {/foreach}
                  </td>
                  <td class="admin-row">{$admin->web_group->name|default:$language->none}</td>
                </tr>
                <tr>
                  <td colspan="4">
                    <div class="opener" align="center">
                      <table width="100%" cellspacing="0" cellpadding="3" bgcolor="#eaebeb">
                        <tr>
                          <td width="35%" class="front-module-line"><strong>{$language->server_permissions}</strong></td>
                          <td width="35%" class="front-module-line"><strong>{$language->web_permissions}</strong></td>
                          <td width="30%" class="front-module-line" valign="top"><strong>{$language->action}</strong></td>
                        </tr>
                        <tr>
                          <td class="permissions" valign="top">
                            {if empty($admin->srv_flags)}
                            <em>{$language->none}</em>
                            {else}
                            <ul>
                              {if $admin->permission_reservation}
                              <li>{$language->reservation_desc}</li>
                              {/if}
                              {if $admin->permission_generic}
                              <li>{$language->generic_desc}</li>
                              {/if}
                              {if $admin->permission_kick}
                              <li>{$language->kick_desc}</li>
                              {/if}
                              {if $admin->permission_ban}
                              <li>{$language->ban_desc}</li>
                              {/if}
                              {if $admin->permission_unban}
                              <li>{$language->unban_desc}</li>
                              {/if}
                              {if $admin->permission_slay}
                              <li>{$language->slay_desc}</li>
                              {/if}
                              {if $admin->permission_changemap}
                              <li>{$language->changemap_desc}</li>
                              {/if}
                              {if $admin->permission_cvar}
                              <li>{$language->cvar_desc}</li>
                              {/if}
                              {if $admin->permission_config}
                              <li>{$language->config_desc}</li>
                              {/if}
                              {if $admin->permission_chat}
                              <li>{$language->chat_desc}</li>
                              {/if}
                              {if $admin->permission_vote}
                              <li>{$language->vote_desc}</li>
                              {/if}
                              {if $admin->permission_password}
                              <li>{$language->password_desc}</li>
                              {/if}
                              {if $admin->permission_rcon}
                              <li>{$language->rcon_desc}</li>
                              {/if}
                              {if $admin->permission_cheats}
                              <li>{$language->cheats_desc}</li>
                              {/if}
                              {if $admin->permission_custom1}
                              <li>{$language->custom1_desc}</li>
                              {/if}
                              {if $admin->permission_custom2}
                              <li>{$language->custom2_desc}</li>
                              {/if}
                              {if $admin->permission_custom3}
                              <li>{$language->custom3_desc}</li>
                              {/if}
                              {if $admin->permission_custom4}
                              <li>{$language->custom4_desc}</li>
                              {/if}
                              {if $admin->permission_custom5}
                              <li>{$language->custom5_desc}</li>
                              {/if}
                              {if $admin->permission_custom6}
                              <li>{$language->custom6_desc}</li>
                              {/if}
                            </ul>
                            {/if}
                          </td>
                          <td class="permissions" valign="top">
                            {if empty($admin->web_flags)}
                            <em>{$language->none}</em>
                            {else}
                            <ul>
                              {if $admin->permission_list_admins}
                              <li>{$language->list_admins}</li>
                              {/if}
                              {if $admin->permission_add_admins}
                              <li>{$language->add_admins}</li>
                              {/if}
                              {if $admin->permission_edit_admins}
                              <li>{$language->edit_admins}</li>
                              {/if}
                              {if $admin->permission_delete_admins}
                              <li>{$language->delete_admins}</li>
                              {/if}
                              {if $admin->permission_import_admins}
                              <li>{$language->import_admins}</li>
                              {/if}
                              {if $admin->permission_list_groups}
                              <li>{$language->list_groups}</li>
                              {/if}
                              {if $admin->permission_add_groups}
                              <li>{$language->add_groups}</li>
                              {/if}
                              {if $admin->permission_edit_groups}
                              <li>{$language->edit_groups}</li>
                              {/if}
                              {if $admin->permission_delete_groups}
                              <li>{$language->delete_groups}</li>
                              {/if}
                              {if $admin->permission_import_groups}
                              <li>{$language->import_groups}</li>
                              {/if}
                              {if $admin->permission_list_games}
                              <li>{$language->list_games}</li>
                              {/if}
                              {if $admin->permission_add_games}
                              <li>{$language->add_games}</li>
                              {/if}
                              {if $admin->permission_edit_games}
                              <li>{$language->edit_games}</li>
                              {/if}
                              {if $admin->permission_delete_games}
                              <li>{$language->delete_games}</li>
                              {/if}
                              {if $admin->permission_list_servers}
                              <li>{$language->list_servers}</li>
                              {/if}
                              {if $admin->permission_add_servers}
                              <li>{$language->add_servers}</li>
                              {/if}
                              {if $admin->permission_edit_servers}
                              <li>{$language->edit_servers}</li>
                              {/if}
                              {if $admin->permission_delete_servers}
                              <li>{$language->delete_servers}</li>
                              {/if}
                              {if $admin->permission_add_bans}
                              <li>{$language->add_bans}</li>
                              {/if}
                              {if $admin->permission_edit_all_bans}
                              <li>{$language->edit_all_bans}</li>
                              {elseif $user->permission_edit_group_bans}
                              <li>{$language->edit_group_bans}</li>
                              {elseif $user->permission_edit_own_bans}
                              <li>{$language->edit_own_bans}</li>
                              {/if}
                              {if $admin->permission_unban_all_bans}
                              <li>{$language->unban_all_bans}</li>
                              {elseif $admin->permission_unban_group_bans}
                              <li>{$language->unban_group_bans}</li>
                              {elseif $admin->permission_unban_own_bans}
                              <li>{$language->unban_own_bans}</li>
                              {/if}
                              {if $admin->permission_delete_bans}
                              <li>{$language->delete_bans}</li>
                              {/if}
                              {if $admin->permission_import_bans}
                              <li>{$language->import_bans}</li>
                              {/if}
                              {if $admin->permission_ban_protests}
                              <li>{$language->ban_protests}</li>
                              {/if}
                              {if $admin->permission_ban_submissions}
                              <li>{$language->ban_submissions}</li>
                              {/if}
                              {if $admin->permission_notify_prot}
                              <li>{$language->notify_protests}</li>
                              {/if}
                              {if $admin->permission_notify_sub}
                              <li>{$language->notify_submissions}</li>
                              {/if}
                              {if $admin->permission_settings}
                              <li>{$language->settings}</li>
                              {/if}
                            </ul>
                            {/if}
                          </td>
                          <td width="30%" valign="top">
                            <ul class="ban-edit">
                              {if $user->permission_edit_admins}
                              <li><a href="{build_uri controller=admins action=editdetails id=$admin->id}"><img alt="{$language->edit_details|ucwords}" class="icon" src="{$uri->base}/images/details.png" title="{$language->edit_details|ucwords}" /> {$language->edit_details|ucwords}</a></li>
                              <li><a href="{build_uri controller=admins action=editgroups id=$admin->id}"><img alt="{$language->edit_groups|ucwords}" class="icon" src="{$uri->base}/images/groups.png" title="{$language->edit_groups|ucwords}" /> {$language->edit_groups|ucwords}</a></li>
                              {/if}
                              {if $user->permission_delete_admins}
                              <li><a href="#" onclick="DeleteAdmin({$admin->id}, '{$admin->name}');"><img alt="{$language->delete_admin|ucwords}" class="icon" src="{$uri->base}/images/delete.png" title="{$language->delete_admin|ucwords}" /> {$language->delete_admin|ucwords}</a></li>
                              {/if}
                            </ul>
                            <div class="front-module-line" style="padding: 3px;">{$language->immunity_level}: <strong>{$admin->srv_immunity}</strong></div>
                            <div class="front-module-line" style="padding: 3px;">{$language->last_visit}: <strong><small>{if empty($admin->visit_time)}{$language->unknown}{else}{$admin->visit_time|date_format:$settings->date_format}{/if}</small></strong></div>
                          </td>
                        </tr>
                      </table>
                    </div>
                  </td>
                </tr>
                {/foreach}
              </table>
              <ul class="context-menu">
                <li><a href="#">{$language->delete}</a></li>
                <li><a href="#">Send e-mail</a></li>
                <li>
                  <a href="#">Add to server group</a>
                  <ul>
                    {foreach from=$server_groups item=group}
                    <li><a href="#">{$group->name}</a></li>
                    {/foreach}
                  </ul>
                </li>
                <li>
                  <a href="#">Set web group</a>
                  <ul>
                    {foreach from=$web_groups item=group}
                    <li><a href="#">{$group->name}</a></li>
                    {/foreach}
                    <li><a href="#">{$language->none|strtolower}</a></li>
                  </ul>
                </li>
              </ul>
            </div>
            {/if}
            {if $user->permission_add_admins}
            <form action="" id="pane-add" method="post">
              <fieldset>
                <h3>{$language->details}</h3>
                <p>{$language->help_desc}</p>
                <div>
                  <label for="name">{help_icon title="`$language->username`" desc="This is the username the admin will use to login to their admin panel. Also this will identify the admin on any bans they make."}{$language->username}</label>
                  <input class="submit-fields" {nid id="name"} />
                  <span class="mandatory">*</span>
                </div>
                <div>
                  <label for="auth">{help_icon title="`$language->type`" desc="This is the admin's authentication type."}{$language->type}</label>
                  <select class="submit-fields" {nid id="auth"}>
                    <option value="{$smarty.const.STEAM_AUTH_TYPE}">Steam ID</option>
                    <option value="{$smarty.const.IP_AUTH_TYPE}">{$language->ip_address}</option>
                    <option value="{$smarty.const.NAME_AUTH_TYPE}">{$language->name}</option>
                  </select>
                  <span class="mandatory">*</span>
                </div>
                <div>
                  <label for="identity">{help_icon title="Steam ID" desc="This is the admin's Steam ID, IP address or name. This must be set so that admins can use their admin rights ingame."}<span id="identity-label">Steam ID</span></label>
                  <input class="submit-fields" {nid id="identity"} value="STEAM_" />
                  <span class="mandatory">*</span>
                </div>
                <div>
                  <label for="email">{help_icon title="`$language->email_address`" desc="Set the admin's e-mail address. This will be used for sending out any automated messages from the system, and for use when you forget your password."}{$language->email_address}</label>
                  <input class="submit-fields" {nid id="email"} />
                </div>
                <div>
                  <label for="password">{help_icon title="`$language->password`" desc="The password the admin will need to access the admin panel."}{$language->password}</label>
                  <input class="submit-fields" {nid id="password"} type="password" />
                </div>
                <div>
                  <label for="password_confirm">{help_icon title="`$language->confirm_password`" desc="$language->confirm_password_desc"}{$language->confirm_password}</label>
                  <input class="submit-fields" {nid id="password_confirm"} type="password" />
                </div>
                <div>
                  <label for="srv_password">{help_icon title="Server Admin Password" desc="If this box is checked, you will need to specify this password in the game server before you can use your admin rights."}Use as admin password?</label>
                  <input {nid id="srv_password"} type="checkbox" />
                </div>
                <div>
                  <label for="activation">{help_icon title="Activation E-mail" desc="If this box is checked, an e-mail will be sent to the admin to activate his/her account."}Send activation e-mail?</label>
                  <input {nid id="activation"} type="checkbox" />
                </div>
                <h3>{$language->groups}</h3>
                <div>
                  <label for="srv_groups">{help_icon title="`$language->server_groups`" desc="<strong>Custom Permisions:</strong><br />Select this to choose custom permissions for this admin.<br /><br /><strong>New Group:</strong><br />Select this to choose custom permissions and then save the permissions as a new group.<br /><br /><strong>Groups:</strong><br />Select a pre-made group to add the admin to."}{$language->server_groups}</label>
                  <select class="submit-fields" {nid id="srv_groups"}>
                    <option value="0">{$language->none}</option>
                    <optgroup label="{$language->groups}">
                      {foreach from=$server_groups item=group}
                      <option value="{$group->id}">{$group->name|escape}</option>
                      {/foreach}
                    </optgroup>
                  </select>
                </div>
                <div>
                  <label for="web_group">{help_icon title="`$language->web_group`" desc="<strong>Custom Permisions:</strong><br />Select this to choose custom permissions for this admin.<br /><br /><strong>New Group:</strong><br />Select this to choose cusrom permissions and then save the permissions as a new group.<br /><br /><strong>Groups:</strong><br />Select a pre-made group to add the admin to."}{$language->web_group}</label>
                  <select class="submit-fields" {nid id="web_group"}>
                    <option value="0">{$language->none}</option>
                    <optgroup label="{$language->groups}">
                      {foreach from=$web_groups item=group}
                      <option value="{$group->id}">{$group->name|escape}</option>
                      {/foreach}
                    </optgroup>
                  </select>
                </div>
                <div class="center">
                  <input name="action" type="hidden" value="add" />
                  <input class="btn ok" type="submit" value="{$language->save}" />
                  <input class="back btn cancel" type="button" value="{$language->back}" />
                </div>
              </fieldset>
            </form>
            {/if}
            {if $user->permission_import_admins}
            <form action="" enctype="multipart/form-data" id="pane-import" method="post">
              <fieldset>
                <h3>{$language->import_admins|ucwords}</h3>
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
            {if $user->permission_list_overrides}
            <form action="" enctype="multipart/form-data" id="pane-overrides" method="post">
              <fieldset>
                <h3>Overrides</h3>
                <table align="center" cellspacing="0" cellpadding="4" id="overrides" width="90%">
                  <tr>
                    <td class="tablerow4">{$language->type}</td>
                    <td class="tablerow4">{$language->name}</td>
                    <td class="tablerow4">Flags</td>
                  </tr>
                  {foreach from=$overrides item=override}
                  <tr>
                    <td class="tablerow1">
                      <select name="override_type[]">
                        <option{if $override->type == "command"} selected="selected"{/if} value="command">{$language->command}</option>
                        <option{if $override->type == "group"} selected="selected"{/if} value="group">{$language->group}</option>
                      </select>
                    </td>
                    <td class="tablerow1"><input name="override_name[]" value="{$override->name}" /></td>
                  <td class="tablerow1"><input name="override_flags[]" value="{$override->flags}" /></td>
                  </tr>
                  {/foreach}
                  <tr>
                    <td class="tablerow1">
                      <select name="override_type[]">
                        <option value="command">{$language->command}</option>
                        <option value="group">{$language->group}</option>
                      </select>
                    </td>
                    <td class="tablerow1"><input id="override_name" name="override_name[]" /></td>
                  <td class="tablerow1"><input name="override_flags[]" /></td>
                  </tr>
                </table>
                <div class="center">
                  <input name="action" type="hidden" value="overrides" />
                  <input class="btn ok" type="submit" value="{$language->save}" />
                  <input class="back btn cancel" type="button" value="{$language->back}" />
                </div>
              </fieldset>
            </form>
            {/if}
            {if $user->permission_list_actions}
            <div id="pane-actions">
              <h3>{$language->actions_log}{if $user->permission_clear_actions} ( <a id="clear_actions" href="#">Clear Actions</a> ){/if}</h3>
              <p>Click on a row to see more details about the event.</p>
              <div id="bans-nav">{$page_numbers}</div>
              <br /><br />
              <table width="100%" cellspacing="0" cellpadding="0" align="center" class="listtable">
                <tr>
                  <th width="5%" align="center">{$language->admin}</th>
                  <th width="28%" align="center">{$language->message}</th>
                  <th width="28%" align="center">{$language->name}</th>
                  <th>{$language->date}/{$language->time}</th>
                </tr>
                {foreach from=$actions item=action}
                <tr class="opener tbl_out">
                  <td class="listtable_1">{$action->admin->name}</td>
                  <td class="listtable_1">{$action->message|escape}</td>
                  <td class="listtable_1">
                    {if empty($action->name)}
                    <em class="not_applicable">{$language->not_applicable}.</em>
                    {else}
                    {$action->name}
                    {/if}
                  </td>
                  <td class="listtable_1">{$action->insert_time|date_format:$settings->date_format}</td>
                </tr>
                <tr>
                  <td colspan="7" align="center">
                    <div class="opener">
                      <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                        <tr>
                          <th colspan="3">{$language->details}</th>
                        </tr>
                        <tr>
                          <td class="listtable_1" width="20%">{$language->game}</td>
                          <td class="listtable_1">{$action->server->game->name}</td>
                        </tr>
                        <tr>
                          <td class="listtable_1">{$language->server}</td>
                          <td class="listtable_1">{$action->server->ip}:{$action->server->port}</td>
                        </tr>
                        <tr>
                          <td class="listtable_1">Steam ID</td>
                          <td class="listtable_1">
                            {if empty($action->steam)}
                            <em class="not_applicable">{$language->not_applicable}.</em>
                            {else}
                            <a href="http://steamcommunity.com/profiles/{$action->community_id}">{$action->steam}</a>
                            {/if}
                          </td>
                        </tr>
                        <tr>
                          <td class="listtable_1">{$language->ip_address}</td>
                          <td class="listtable_1">
                            {if empty($action->ip)}
                            <em class="not_applicable">{$language->not_applicable}.</em>
                            {else}
                            {$action->ip}
                            {/if}
                          </td>
                        </tr>
                      </table>
                    </div>
                  </td>
                </tr>
                {/foreach}
              </table>
            </div>
            {/if}
          </div>