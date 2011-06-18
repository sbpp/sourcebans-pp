          <div id="admin-page-menu">
            <ul>
              {if $user->permission_list_groups}
              <li id="tab-list"><a href="#list">{$language->list_groups}</a></li>
              {/if}
              {if $user->permission_add_groups}
              <li id="tab-add"><a href="#add">{$language->add_group}</a></li>
              {/if}
              {if $user->permission_import_groups}
              <li id="tab-import"><a href="#import">{$language->import_groups}</a></li>
              {/if}
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
            </ul>
            <br />
            <div class="center">
              <img alt="{$action_title}" src="{$uri->base}/themes/{$theme}/images/admin/groups.png" title="{$action_title}" />
            </div>
          </div>
          <div id="admin-page-content">
            {if $user->permission_list_groups}
            <div id="pane-list">
              <h3>{$language->groups}</h3>
              Click on a group to view its permissions.<br /><br />
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td colspan="4">
                    <table width="100%" cellpadding="0" cellspacing="0" class="front-module-header">
                      <tr>
                        <td>{$language->server_groups}</td>
                        <td class="right">{$language->total}: <span id="server_group_count">{$server_groups|@count}</span></td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <th width="40%">{$language->name}</th>
                  <th width="25%">{$language->admins_in_group}</th>
                  <th width="30%">{$language->action}</th>
                </tr>
                {foreach from=$server_groups item=group key=group_id}
                <tr class="opener tbl_out">
                  <td style="border-bottom: solid 1px #ccc">{$group->name}</td>
                  <td style="border-bottom: solid 1px #ccc">{$group->admin_count}</td>
                  <td style="border-bottom: solid 1px #ccc">
                    {if $user->permission_edit_groups}
                    <a href="{build_uri controller=groups action=edit id=$group_id type=$smarty.const.SERVER_GROUPS}">{$language->edit}</a>
                    {/if}
                    {if $user->permission_edit_groups && $user->permission_delete_groups}
                    -
                    {/if}
                    {if $user->permission_delete_groups}
                    <a href="#" onclick="DeleteGroup({$group_id}, '{$group->name}', 'srv');">{$language->delete}</a>
                    {/if}
                  </td>
                </tr>
                <tr>
                  <td colspan="7" align="center">
                    <div class="opener">
                      <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                        <tr>
                          <th colspan="3">{$language->details}</th>
                        </tr>
                        <tr>
                          <td width="20%" class="listtable_1">Permissions</td>
                          <td class="listtable_1 permissions">
                            <h4>{$language->server_permissions}</h4>
                            {if empty($group->flags)}
                            <em>{$language->none}</em>
                            {else}
                            <ul>
                              {if $group->permission_reservation}
                              <li>{$language->reservation_desc}</li>
                              {/if}
                              {if $group->permission_generic}
                              <li>{$language->generic_desc}</li>
                              {/if}
                              {if $group->permission_kick}
                              <li>{$language->kick_desc}</li>
                              {/if}
                              {if $group->permission_ban}
                              <li>{$language->ban_desc}</li>
                              {/if}
                              {if $group->permission_unban}
                              <li>{$language->unban_desc}</li>
                              {/if}
                              {if $group->permission_slay}
                              <li>{$language->slay_desc}</li>
                              {/if}
                              {if $group->permission_changemap}
                              <li>{$language->changemap_desc}</li>
                              {/if}
                              {if $group->permission_cvar}
                              <li>{$language->cvar_desc}</li>
                              {/if}
                              {if $group->permission_config}
                              <li>{$language->config_desc}</li>
                              {/if}
                              {if $group->permission_chat}
                              <li>{$language->chat_desc}</li>
                              {/if}
                              {if $group->permission_vote}
                              <li>{$language->vote_desc}</li>
                              {/if}
                              {if $group->permission_password}
                              <li>{$language->password_desc}</li>
                              {/if}
                              {if $group->permission_rcon}
                              <li>{$language->rcon_desc}</li>
                              {/if}
                              {if $group->permission_cheats}
                              <li>{$language->cheats_desc}</li>
                              {/if}
                              {if $group->permission_custom1}
                              <li>{$language->custom1_desc}</li>
                              {/if}
                              {if $group->permission_custom2}
                              <li>{$language->custom2_desc}</li>
                              {/if}
                              {if $group->permission_custom3}
                              <li>{$language->custom3_desc}</li>
                              {/if}
                              {if $group->permission_custom4}
                              <li>{$language->custom4_desc}</li>
                              {/if}
                              {if $group->permission_custom5}
                              <li>{$language->custom5_desc}</li>
                              {/if}
                              {if $group->permission_custom6}
                              <li>{$language->custom6_desc}</li>
                              {/if}
                            </ul>
                            {/if}
                          </td>
                        </tr>
                      </table>
                    </div>
                  </td>
                </tr>
                {/foreach}
              </table>
              <br /><br />
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td colspan="4">
                    <table width="100%" cellpadding="0" cellspacing="0" class="front-module-header">
                      <tr>
                        <td>{$language->web_groups}</td>
                        <td class="right">{$language->total}: <span id="web_group_count">{$web_groups|@count}</span></td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <th width="40%">{$language->name}</th>
                  <th width="25%">{$language->admins_in_group}</th>
                  <th width="30%">{$language->action}</th>
                </tr>
                {foreach from=$web_groups item=group key=group_id}
                <tr class="opener tbl_out">
                  <td style="border-bottom: solid 1px #ccc">{$group->name}</td>
                  <td style="border-bottom: solid 1px #ccc">{$group->admin_count}</td>
                  <td style="border-bottom: solid 1px #ccc">
                    {if $user->permission_edit_groups}
                    <a href="{build_uri controller=groups action=edit id=$group_id type=$smarty.const.WEB_GROUPS}">{$language->edit}</a>
                    {/if}
                    {if $user->permission_delete_groups}
                    - <a href="#" onclick="DeleteGroup({$group_id}, '{$group->name}', 'web');">{$language->delete}</a>
                    {/if}
                  </td>
                </tr>
                <tr>
                  <td colspan="7" align="center">
                    <div class="opener">
                      <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                        <tr>
                          <th colspan="3">{$language->details}</th>
                        </tr>
                        <tr>
                          <td width="20%" class="listtable_1">Permissions</td>
                          <td class="listtable_1 permissions">
                            <h4>{$language->web_permissions}</h4>
                            {if !$group->flags}
                            <em>{$language->none}</em>
                            {else}
                            <ul>
                              {if $group->permission_list_admins}
                              <li>{$language->list_admins}</li>
                              {/if}
                              {if $group->permission_add_admins}
                              <li>{$language->add_admins}</li>
                              {/if}
                              {if $group->permission_edit_admins}
                              <li>{$language->edit_admins}</li>
                              {/if}
                              {if $group->permission_delete_admins}
                              <li>{$language->delete_admins}</li>
                              {/if}
                              {if $group->permission_import_admins}
                              <li>{$language->import_admins}</li>
                              {/if}
                              {if $group->permission_list_groups}
                              <li>{$language->list_groups}</li>
                              {/if}
                              {if $group->permission_add_groups}
                              <li>{$language->add_groups}</li>
                              {/if}
                              {if $group->permission_edit_groups}
                              <li>{$language->edit_groups}</li>
                              {/if}
                              {if $group->permission_delete_groups}
                              <li>{$language->delete_groups}</li>
                              {/if}
                              {if $group->permission_import_groups}
                              <li>{$language->import_groups}</li>
                              {/if}
                              {if $group->permission_list_games}
                              <li>{$language->list_games}</li>
                              {/if}
                              {if $group->permission_add_games}
                              <li>{$language->add_games}</li>
                              {/if}
                              {if $group->permission_edit_games}
                              <li>{$language->edit_games}</li>
                              {/if}
                              {if $group->permission_delete_games}
                              <li>{$language->delete_games}</li>
                              {/if}
                              {if $group->permission_list_servers}
                              <li>{$language->list_servers}</li>
                              {/if}
                              {if $group->permission_add_servers}
                              <li>{$language->add_servers}</li>
                              {/if}
                              {if $group->permission_edit_servers}
                              <li>{$language->edit_servers}</li>
                              {/if}
                              {if $group->permission_delete_servers}
                              <li>{$language->delete_servers}</li>
                              {/if}
                              {if $group->permission_add_bans}
                              <li>{$language->add_bans}</li>
                              {/if}
                              {if $group->permission_edit_all_bans}
                              <li>{$language->edit_all_bans}</li>
                              {elseif $group->permission_edit_group_bans}
                              <li>{$language->edit_group_bans}</li>
                              {elseif $group->permission_edit_own_bans}
                              <li>{$language->edit_own_bans}</li>
                              {/if}
                              {if $group->permission_unban_all_bans}
                              <li>{$language->unban_all_bans}</li>
                              {elseif $group->permission_unban_group_bans}
                              <li>{$language->unban_group_bans}</li>
                              {elseif $group->permission_unban_own_bans}
                              <li>{$language->unban_own_bans}</li>
                              {/if}
                              {if $group->permission_delete_bans}
                              <li>{$language->delete_bans}</li>
                              {/if}
                              {if $group->permission_import_bans}
                              <li>{$language->import_bans}</li>
                              {/if}
                              {if $group->permission_ban_protests}
                              <li>{$language->ban_protests}</li>
                              {/if}
                              {if $group->permission_ban_submissions}
                              <li>{$language->ban_submissions}</li>
                              {/if}
                              {if $group->permission_notify_prot}
                              <li>{$language->notify_protests}</li>
                              {/if}
                              {if $group->permission_notify_sub}
                              <li>{$language->notify_submissions}</li>
                              {/if}
                              {if $group->permission_settings}
                              <li>{$language->settings}</li>
                              {/if}
                            </ul>
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
            {if $user->permission_add_groups}
            <form action="" id="pane-add" method="post">
              <fieldset>
                <h3>{$language->add_group|ucwords}</h3>
                <label for="name">{help_icon title="`$language->name`" desc="Type the name of the new group you want to create."}{$language->name}</label>
                <input class="submit-fields" {nid id="name"} />
                <label for="type">{help_icon title="`$language->type`" desc="This defines the type of group you are about to create. This helps identify and catagorize the groups list."}{$language->type}</label>
                <select class="submit-fields group_type_select" {nid id="type"}>
                  <option value="0">Please Select...</option>
                  <option value="{$smarty.const.SERVER_GROUPS}">{$language->server_group}</option>
                  <option value="{$smarty.const.WEB_GROUPS}">{$language->web_group}</option>
                </select>
                <table align="center" cellspacing="0" cellpadding="4" class="group_type" id="group_type_{$smarty.const.SERVER_GROUPS}" width="90%">
                  <tr>
                    <td colspan="2" class="tablerow4">{$language->name}</td>
                    <td class="tablerow4">Flag</td>
                    <td colspan="2" class="tablerow4">Purpose</td>
                  </tr>
                  <tr>
                    <td colspan="2" class="tablerow2">Root (Full Server Access)</td>
                    <td class="tablerow2" align="center">{$smarty.const.SM_ROOT}</td>
                    <td class="tablerow2">Magically enables all flags.</td>
                    <td align="center" class="tablerow2"><input id="permission_root" name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_ROOT}" /></td>
                  </tr>
                  <tr>
                    <td colspan="5" class="tablerow4">Standard Server Permissions</td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Reservation</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_RESERVATION}</td>
                    <td class="tablerow1">{$language->reservation_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_RESERVATION}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Generic</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_GENERIC}</td>
                    <td class="tablerow1">{$language->generic_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_GENERIC}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Kick</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_KICK}</td>
                    <td class="tablerow1">{$language->kick_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_KICK}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Ban</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_BAN}</td>
                    <td class="tablerow1">{$language->ban_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_BAN}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Unban</td>
                    <td align="center" class="tablerow1">{$smarty.const.SM_UNBAN}</td>
                    <td class="tablerow1">{$language->unban_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_UNBAN}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Slay</td>
                    <td align="center" class="tablerow1">{$smarty.const.SM_SLAY}</td>
                    <td class="tablerow1">{$language->slay_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_SLAY}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Change Map</td>
                    <td align="center" class="tablerow1">{$smarty.const.SM_CHANGEMAP}</td>
                    <td class="tablerow1">{$language->changemap_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CHANGEMAP}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Cvar</td>
                    <td align="center" class="tablerow1">{$smarty.const.SM_CVAR}</td>
                    <td class="tablerow1">{$language->cvar_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CVAR}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Config</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CONFIG}</td>
                    <td class="tablerow1">{$language->config_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CONFIG}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Chat</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CHAT}</td>
                    <td class="tablerow1">{$language->chat_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CHAT}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Vote</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_VOTE}</td>
                    <td class="tablerow1">{$language->vote_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_VOTE}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Password</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_PASSWORD}</td>
                    <td class="tablerow1">{$language->password_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_PASSWORD}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">RCON</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_RCON}</td>
                    <td class="tablerow1">{$language->rcon_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_RCON}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Cheats</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CHEATS}</td>
                    <td class="tablerow1">{$language->cheats_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CHEATS}" /></td>
                  </tr>
                  <tr>
                    <td colspan="5" class="tablerow4">Custom Server Permissions</td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->custom1_desc}</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM1}</td>
                    <td class="tablerow1">&nbsp;</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM1}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->custom2_desc}</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM2}</td>
                    <td class="tablerow1">&nbsp;</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM2}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->custom3_desc}</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM3}</td>
                    <td class="tablerow1">&nbsp;</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM3}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->custom4_desc}</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM4}</td>
                    <td class="tablerow1">&nbsp;</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM4}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->custom5_desc}</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM5}</td>
                    <td class="tablerow1">&nbsp;</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM5}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->custom6_desc}</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM6}</td>
                    <td class="tablerow1">&nbsp;</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM6}" /></td>
                  </tr>
                  <tr>
                    <td colspan="5" class="tablerow4">{$language->immunity_level}</td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->immunity_level}</td>
                    <td class="tablerow1" align="center"></td>
                    <td class="tablerow1">Choose the immunity level. The higher the number, the more immunity.</td>
                    <td align="center" class="tablerow1"><input {nid id="immunity"} maxlength="3" value="0" /></td>
                  </tr>
                </table>
                <table align="center" cellspacing="0" cellpadding="4" class="group_type" id="group_type_{$smarty.const.WEB_GROUPS}" width="90%">
                  <tr>
                    <td colspan="2" class="tablerow2">Owner (Full Web Access)</td>
                    <td align="center" class="tablerow2"><input id="permission_owner" name="web_flags[]" type="checkbox" value="OWNER" /></td>
                  </tr>
                  <tr>
                    <td colspan="2" class="tablerow4">{$language->admins}</td>
                    <td align="center" class="tablerow4"><input {nid id="permission_admins"} type="checkbox" value="-1" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->list_admins}</td>
                    <td align="center" class="tablerow1"><input id="permission_list_admins" name="web_flags[]" type="checkbox" value="LIST_ADMINS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->add_admins}</td>
                    <td align="center" class="tablerow1"><input id="permission_add_admins" name="web_flags[]" type="checkbox" value="ADD_ADMINS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->edit_admins}</td>
                    <td align="center" class="tablerow1"><input id="permission_edit_admins" name="web_flags[]" type="checkbox" value="EDIT_ADMINS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->delete_admins}</td>
                    <td align="center" class="tablerow1"><input id="permission_delete_admins" name="web_flags[]" type="checkbox" value="DELETE_ADMINS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->import_admins}</td>
                    <td align="center" class="tablerow1"><input id="permission_import_admins" name="web_flags[]" type="checkbox" value="IMPORT_ADMINS" /></td>
                  </tr>
                  <tr>
                    <td colspan="2" class="tablerow4">{$language->groups}</td>
                    <td align="center" class="tablerow4"><input {nid id="permission_groups"} type="checkbox" value="-1" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->list_groups}</td>
                    <td align="center" class="tablerow1"><input id="permission_list_groups" name="web_flags[]" type="checkbox" value="LIST_GROUPS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->add_groups}</td>
                    <td align="center" class="tablerow1"><input id="permission_add_groups" name="web_flags[]" type="checkbox" value="ADD_GROUPS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->edit_groups}</td>
                    <td align="center" class="tablerow1"><input id="permission_edit_groups" name="web_flags[]" type="checkbox" value="EDIT_GROUPS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->delete_groups}</td>
                    <td align="center" class="tablerow1"><input id="permission_delete_groups" name="web_flags[]" type="checkbox" value="DELETE_GROUPS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->import_groups}</td>
                    <td align="center" class="tablerow1"><input id="permission_import_groups" name="web_flags[]" type="checkbox" value="IMPORT_GROUPS" /></td>
                  </tr>
                  <tr>
                    <td colspan="2" class="tablerow4">{$language->games}</td>
                    <td align="center" class="tablerow4"><input {nid id="permission_games"} type="checkbox" value="-1" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->list_games}</td>
                    <td align="center" class="tablerow1"><input id="permission_list_games" name="web_flags[]" type="checkbox" value="LIST_GAMES" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->add_games}</td>
                    <td align="center" class="tablerow1"><input id="permission_add_games" name="web_flags[]" type="checkbox" value="ADD_GAMES" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->edit_games}</td>
                    <td align="center" class="tablerow1"><input id="permission_edit_games" name="web_flags[]" type="checkbox" value="EDIT_GAMES" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->delete_games}</td>
                    <td align="center" class="tablerow1"><input id="permission_delete_games" name="web_flags[]" type="checkbox" value="DELETE_GAMES" /></td>
                  </tr>
                  <tr class="tablerow4">
                    <td colspan="2" class="tablerow4">{$language->servers}</td>
                    <td align="center" class="tablerow4"><input {nid id="permission_servers"} type="checkbox" value="-1" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->list_servers}</td>
                    <td align="center" class="tablerow1"><input id="permission_list_servers" name="web_flags[]" type="checkbox" value="LIST_SERVERS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->add_servers}</td>
                    <td align="center" class="tablerow1"><input id="permission_add_servers" name="web_flags[]" type="checkbox" value="ADD_SERVERS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->edit_servers}</td>
                    <td align="center" class="tablerow1"><input id="permission_edit_servers" name="web_flags[]" type="checkbox" value="EDIT_SERVERS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->delete_servers}</td>
                    <td align="center" class="tablerow1"><input id="permission_delete_servers" name="web_flags[]" type="checkbox" value="DELETE_SERVERS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->import_servers}</td>
                    <td align="center" class="tablerow1"><input id="permission_import_servers" name="web_flags[]" type="checkbox" value="IMPORT_SERVERS" /></td>
                  </tr>
                  <tr>
                    <td colspan="2" class="tablerow4">{$language->bans}</td>
                    <td align="center" class="tablerow4"><input {nid id="permission_bans"} type="checkbox" value="-1" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->add_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_add_bans" name="web_flags[]" type="checkbox" value="ADD_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->edit_own_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_edit_own_bans" name="web_flags[]" type="checkbox" value="EDIT_OWN_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->edit_group_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_edit_group_bans" name="web_flags[]" type="checkbox" value="EDIT_GROUP_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->edit_all_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_edit_all_bans" name="web_flags[]" type="checkbox" value="EDIT_ALL_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->unban_own_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_unban_own_bans" name="web_flags[]" type="checkbox" value="UNBAN_OWN_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->unban_group_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_unban_group_bans" name="web_flags[]" type="checkbox" value="UNBAN_GROUP_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->unban_all_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_unban_all_bans" name="web_flags[]" type="checkbox" value="UNBAN_ALL_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->delete_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_delete_bans" name="web_flags[]" type="checkbox" value="DELETE_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->import_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_import_bans" name="web_flags[]" type="checkbox" value="IMPORT_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->ban_protests}</td>
                    <td align="center" class="tablerow1"><input id="permission_ban_protests" name="web_flags[]" type="checkbox" value="BAN_PROTESTS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$language->ban_submissions}</td>
                    <td align="center" class="tablerow1"><input id="permission_ban_submissions" name="web_flags[]" type="checkbox" value="BAN_SUBMISSIONS" /></td>
                  </tr>
                  <tr>
                    <td colspan="2" class="tablerow4">{$language->email_notifications}</td>
                    <td align="center" class="tablerow4"><input {nid id="permission_notify"} type="checkbox" value="-1" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->notify_protests}</td>
                    <td align="center" class="tablerow1"><input id="permission_notify_prot" name="web_flags[]" type="checkbox" value="NOTIFY_PROT" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$language->notify_submissions}</td>
                    <td align="center" class="tablerow1"><input id="permission_notify_sub" name="web_flags[]" type="checkbox" value="NOTIFY_SUB" /></td>
                  </tr>
                  <tr>
                    <td colspan="2" class="tablerow4">{$language->settings}</td>
                    <td align="center" class="tablerow4"><input id="permission_settings" name="web_flags[]" type="checkbox" value="SETTINGS" /></td>
                  </tr>
                </table>
                <div class="center">
                  <input name="action" type="hidden" value="add" />
                  <input class="btn ok" type="submit" value="{$language->save}" />
                  <input class="back btn cancel" type="button" value="{$language->back}" />
                </div>
              </fieldset>
            </form>
            {/if}
            {if $user->permission_import_groups}
            <form action="" enctype="multipart/form-data" id="pane-import" method="post">
              <fieldset>
                <h3>{$language->import_groups|ucwords}</h3>
                <p>{$language->help_desc}</p>
                <label for="file">{help_icon title="`$language->file`" desc="Select the admin_groups.cfg file to upload and add groups."}{$language->file}</label>
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