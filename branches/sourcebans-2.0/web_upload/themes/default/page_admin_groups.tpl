          <div id="admin-page-menu">
            <ul>
              {if $permission_list_groups}
              <li id="tab-list"><a href="#list">{$lang_list_groups}</a></li>
              {/if}
              {if $permission_add_groups}
              <li id="tab-add"><a href="#add">{$lang_add_group}</a></li>
              {/if}
              {if $permission_import_groups}
              <li id="tab-import"><a href="#import">{$lang_import_groups}</a></li>
              {/if}
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
            </ul>
            <br />
            <div align="center">
              <img alt="{$page_title}" src="themes/{$theme_dir}/images/admin/groups.png" title="{$page_title}" />
            </div>
          </div>
          <div id="admin-page-content">
            {if $permission_list_groups}
            <div id="pane-list">
              <h3>{$lang_groups}</h3>
              Click on a group to view its permissions.<br /><br />
              <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td colspan="4">
                    <table width="100%" cellpadding="0" cellspacing="0" class="front-module-header">
                      <tr>
                        <td>{$lang_server_groups}</td>
                        <td class="right">{$lang_total}: {$server_group_count}</td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <th width="40%">{$lang_name}</th>
                  <th width="25%">{$lang_admins_in_group}</th>
                  <th width="30%">{$lang_action}</th>
                </tr>
                {foreach from=$server_groups item=group key=group_id}
                <tr class="opener tbl_out">
                  <td style="border-bottom: solid 1px #ccc">{$group.name}</td>
                  <td style="border-bottom: solid 1px #ccc">{$group.admin_count}</td>
                  <td style="border-bottom: solid 1px #ccc">
                    {if $permission_edit_groups}
                    <a href="admin_groups_edit.php?type={$smarty.const.SERVER_GROUPS}&amp;id={$group_id}">{$lang_edit}</a>
                    {/if}
                    {if $permission_edit_groups && $permission_delete_groups}
                    -
                    {/if}
                    {if $permission_delete_groups}
                    <a href="#" onclick="DeleteGroup({$group_id}, '{$group.name}', 'srv');">{$lang_delete}</a>
                    {/if}
                  </td>
                </tr>
                <tr>
                  <td colspan="7" align="center">
                    <div class="opener">
                      <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                        <tr>
                          <th colspan="3">{$lang_details}</th>
                        </tr>
                        <tr>
                          <td width="20%" class="listtable_1">Permissions</td>
                          <td class="listtable_1 permissions">
                            <h4>{$lang_server_permissions}</h4>
                            {if empty($group.flags)}
                            <em>{$lang_none}</em>
                            {else}
                            <ul>
                              {if $group.permission_reservation}
                              <li>{$lang_reservation_desc}</li>
                              {/if}
                              {if $group.permission_generic}
                              <li>{$lang_generic_desc}</li>
                              {/if}
                              {if $group.permission_kick}
                              <li>{$lang_kick_desc}</li>
                              {/if}
                              {if $group.permission_ban}
                              <li>{$lang_ban_desc}</li>
                              {/if}
                              {if $group.permission_unban}
                              <li>{$lang_unban_desc}</li>
                              {/if}
                              {if $group.permission_slay}
                              <li>{$lang_slay_desc}</li>
                              {/if}
                              {if $group.permission_changemap}
                              <li>{$lang_changemap_desc}</li>
                              {/if}
                              {if $group.permission_cvar}
                              <li>{$lang_cvar_desc}</li>
                              {/if}
                              {if $group.permission_config}
                              <li>{$lang_config_desc}</li>
                              {/if}
                              {if $group.permission_chat}
                              <li>{$lang_chat_desc}</li>
                              {/if}
                              {if $group.permission_vote}
                              <li>{$lang_vote_desc}</li>
                              {/if}
                              {if $group.permission_password}
                              <li>{$lang_password_desc}</li>
                              {/if}
                              {if $group.permission_rcon}
                              <li>{$lang_rcon_desc}</li>
                              {/if}
                              {if $group.permission_cheats}
                              <li>{$lang_cheats_desc}</li>
                              {/if}
                              {if $group.permission_custom1}
                              <li>{$lang_custom1_desc}</li>
                              {/if}
                              {if $group.permission_custom2}
                              <li>{$lang_custom2_desc}</li>
                              {/if}
                              {if $group.permission_custom3}
                              <li>{$lang_custom3_desc}</li>
                              {/if}
                              {if $group.permission_custom4}
                              <li>{$lang_custom4_desc}</li>
                              {/if}
                              {if $group.permission_custom5}
                              <li>{$lang_custom5_desc}</li>
                              {/if}
                              {if $group.permission_custom6}
                              <li>{$lang_custom6_desc}</li>
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
                        <td>{$lang_web_groups}</td>
                        <td class="right">{$lang_total}: {$web_group_count}</td>
                      </tr>
                    </table>
                  </td>
                </tr>
                <tr>
                  <th width="40%">{$lang_name}</th>
                  <th width="25%">{$lang_admins_in_group}</th>
                  <th width="30%">{$lang_action}</th>
                </tr>
                {foreach from=$web_groups item=group key=group_id}
                <tr class="opener tbl_out">
                  <td style="border-bottom: solid 1px #ccc">{$group.name}</td>
                  <td style="border-bottom: solid 1px #ccc">{$group.admin_count}</td>
                  <td style="border-bottom: solid 1px #ccc">
                    {if $permission_edit_groups}
                    <a href="admin_groups_edit.php?type={$smarty.const.WEB_GROUPS}&amp;id={$group_id}">{$lang_edit}</a>
                    {/if}
                    {if $permission_delete_groups}
                    - <a href="#" onclick="DeleteGroup({$group_id}, '{$group.name}', 'web');">{$lang_delete}</a>
                    {/if}
                  </td>
                </tr>
                <tr>
                  <td colspan="7" align="center">
                    <div class="opener">
                      <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                        <tr>
                          <th colspan="3">{$lang_details}</th>
                        </tr>
                        <tr>
                          <td width="20%" class="listtable_1">Permissions</td>
                          <td class="listtable_1 permissions">
                            <h4>{$lang_web_permissions}</h4>
                            {if !$group.flags}
                            <em>{$lang_none}</em>
                            {else}
                            <ul>
                              {if $group.permission_list_admins}
                              <li>{$lang_list_admins}</li>
                              {/if}
                              {if $group.permission_add_admins}
                              <li>{$lang_add_admins}</li>
                              {/if}
                              {if $group.permission_edit_admins}
                              <li>{$lang_edit_admins}</li>
                              {/if}
                              {if $group.permission_delete_admins}
                              <li>{$lang_delete_admins}</li>
                              {/if}
                              {if $group.permission_import_admins}
                              <li>{$lang_import_admins}</li>
                              {/if}
                              {if $group.permission_list_groups}
                              <li>{$lang_list_groups}</li>
                              {/if}
                              {if $group.permission_add_groups}
                              <li>{$lang_add_groups}</li>
                              {/if}
                              {if $group.permission_edit_groups}
                              <li>{$lang_edit_groups}</li>
                              {/if}
                              {if $group.permission_delete_groups}
                              <li>{$lang_delete_groups}</li>
                              {/if}
                              {if $group.permission_import_groups}
                              <li>{$lang_import_groups}</li>
                              {/if}
                              {if $group.permission_list_mods}
                              <li>{$lang_list_mods}</li>
                              {/if}
                              {if $group.permission_add_mods}
                              <li>{$lang_add_mods}</li>
                              {/if}
                              {if $group.permission_edit_mods}
                              <li>{$lang_edit_mods}</li>
                              {/if}
                              {if $group.permission_delete_mods}
                              <li>{$lang_delete_mods}</li>
                              {/if}
                              {if $group.permission_list_servers}
                              <li>{$lang_list_servers}</li>
                              {/if}
                              {if $group.permission_add_servers}
                              <li>{$lang_add_servers}</li>
                              {/if}
                              {if $group.permission_edit_servers}
                              <li>{$lang_edit_servers}</li>
                              {/if}
                              {if $group.permission_delete_servers}
                              <li>{$lang_delete_servers}</li>
                              {/if}
                              {if $group.permission_add_bans}
                              <li>{$lang_add_bans}</li>
                              {/if}
                              {if $group.permission_edit_all_bans}
                              <li>{$lang_edit_all_bans}</li>
                              {elseif $permission_edit_group_bans}
                              <li>{$lang_edit_group_bans}</li>
                              {elseif $permission_edit_own_bans}
                              <li>{$lang_edit_own_bans}</li>
                              {/if}
                              {if $group.permission_unban_all_bans}
                              <li>{$lang_unban_all_bans}</li>
                              {elseif $permission_unban_group_bans}
                              <li>{$lang_unban_group_bans}</li>
                              {elseif $permission_unban_own_bans}
                              <li>{$lang_unban_own_bans}</li>
                              {/if}
                              {if $group.permission_delete_bans}
                              <li>{$lang_delete_bans}</li>
                              {/if}
                              {if $group.permission_import_bans}
                              <li>{$lang_import_bans}</li>
                              {/if}
                              {if $group.permission_ban_protests}
                              <li>{$lang_ban_protests}</li>
                              {/if}
                              {if $group.permission_ban_submissions}
                              <li>{$lang_ban_submissions}</li>
                              {/if}
                              {if $group.permission_notify_prot}
                              <li>{$lang_notify_protests}</li>
                              {/if}
                              {if $group.permission_notify_sub}
                              <li>{$lang_notify_submissions}</li>
                              {/if}
                              {if $group.permission_settings}
                              <li>{$lang_settings}</li>
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
            {if $permission_add_groups}
            <form action="{$active}" id="pane-add" method="post">
              <fieldset>
                <h3>{$lang_add_group|ucwords}</h3>
                <label for="name">{help_icon title="$lang_name" desc="Type the name of the new group you want to create."}{$lang_name}</label>
                <input class="submit-fields" {nid id="name"} />
                <div class="badentry" id="name.msg"></div>
                <label for="type">{help_icon title="$lang_type" desc="This defines the type of group you are about to create. This helps identify and catagorize the groups list."}{$lang_type}</label>
                <select class="submit-fields group_type_select" {nid id="type"}>
                  <option value="0">Please Select...</option>
                  <option value="{$smarty.const.SERVER_GROUPS}">{$lang_server_group}</option>
                  <option value="{$smarty.const.WEB_GROUPS}">{$lang_web_group}</option>
                </select>
                <div class="badentry" id="type.msg"></div>
                <table align="center" cellspacing="0" cellpadding="4" class="group_type" id="group_type_{$smarty.const.SERVER_GROUPS}" width="90%">
                  <tr>
                    <td colspan="2" class="tablerow4">{$lang_name}</td>
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
                    <td class="tablerow1">{$lang_reservation_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_RESERVATION}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Generic</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_GENERIC}</td>
                    <td class="tablerow1">{$lang_generic_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_GENERIC}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Kick</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_KICK}</td>
                    <td class="tablerow1">{$lang_kick_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_KICK}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Ban</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_BAN}</td>
                    <td class="tablerow1">{$lang_ban_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_BAN}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Unban</td>
                    <td align="center" class="tablerow1">{$smarty.const.SM_UNBAN}</td>
                    <td class="tablerow1">{$lang_unban_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_UNBAN}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Slay</td>
                    <td align="center" class="tablerow1">{$smarty.const.SM_SLAY}</td>
                    <td class="tablerow1">{$lang_slay_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_SLAY}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Change Map</td>
                    <td align="center" class="tablerow1">{$smarty.const.SM_CHANGEMAP}</td>
                    <td class="tablerow1">{$lang_changemap_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CHANGEMAP}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Cvar</td>
                    <td align="center" class="tablerow1">{$smarty.const.SM_CVAR}</td>
                    <td class="tablerow1">{$lang_cvar_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CVAR}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Config</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CONFIG}</td>
                    <td class="tablerow1">{$lang_config_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CONFIG}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Chat</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CHAT}</td>
                    <td class="tablerow1">{$lang_chat_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CHAT}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Vote</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_VOTE}</td>
                    <td class="tablerow1">{$lang_vote_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_VOTE}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Password</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_PASSWORD}</td>
                    <td class="tablerow1">{$lang_password_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_PASSWORD}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">RCON</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_RCON}</td>
                    <td class="tablerow1">{$lang_rcon_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_RCON}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">Cheats</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CHEATS}</td>
                    <td class="tablerow1">{$lang_cheats_desc}</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CHEATS}" /></td>
                  </tr>
                  <tr>
                    <td colspan="5" class="tablerow4">Custom Server Permissions</td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_custom1_desc}</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM1}</td>
                    <td class="tablerow1">&nbsp;</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM1}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_custom2_desc}</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM2}</td>
                    <td class="tablerow1">&nbsp;</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM2}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_custom3_desc}</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM3}</td>
                    <td class="tablerow1">&nbsp;</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM3}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_custom4_desc}</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM4}</td>
                    <td class="tablerow1">&nbsp;</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM4}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_custom5_desc}</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM5}</td>
                    <td class="tablerow1">&nbsp;</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM5}" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_custom6_desc}</td>
                    <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM6}</td>
                    <td class="tablerow1">&nbsp;</td>
                    <td align="center" class="tablerow1"><input name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM6}" /></td>
                  </tr>
                  <tr>
                    <td colspan="5" class="tablerow4">{$lang_immunity_level}</td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_immunity_level}</td>
                    <td class="tablerow1" align="center"></td>
                    <td class="tablerow1">Choose the immunity level. The higher the number, the more immunity.</td>
                    <td align="center" class="tablerow1"><input maxlength="3" {nid id="immunity"} style="width: 25px; text-align: center;" value="0" /></td>
                  </tr>
                </table>
                <table align="center" cellspacing="0" cellpadding="4" class="group_type" id="group_type_{$smarty.const.WEB_GROUPS}" width="90%">
                  <tr>
                    <td colspan="2" class="tablerow2">Owner (Full Web Access)</td>
                    <td align="center" class="tablerow2"><input id="permission_owner" name="web_flags[]" type="checkbox" value="ADMIN_OWNER" /></td>
                  </tr>
                  <tr>
                    <td colspan="2" class="tablerow4">{$lang_admins}</td>
                    <td align="center" class="tablerow4"><input {nid id="permission_admins"} type="checkbox" value="-1" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_list_admins}</td>
                    <td align="center" class="tablerow1"><input id="permission_list_admins" name="web_flags[]" type="checkbox" value="ADMIN_LIST_ADMINS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_add_admins}</td>
                    <td align="center" class="tablerow1"><input id="permission_add_admins" name="web_flags[]" type="checkbox" value="ADMIN_ADD_ADMINS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_edit_admins}</td>
                    <td align="center" class="tablerow1"><input id="permission_edit_admins" name="web_flags[]" type="checkbox" value="ADMIN_EDIT_ADMINS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_delete_admins}</td>
                    <td align="center" class="tablerow1"><input id="permission_delete_admins" name="web_flags[]" type="checkbox" value="ADMIN_DELETE_ADMINS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_import_admins}</td>
                    <td align="center" class="tablerow1"><input id="permission_import_admins" name="web_flags[]" type="checkbox" value="ADMIN_IMPORT_ADMINS" /></td>
                  </tr>
                  <tr>
                    <td colspan="2" class="tablerow4">{$lang_groups}</td>
                    <td align="center" class="tablerow4"><input {nid id="permission_groups"} type="checkbox" value="-1" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_list_groups}</td>
                    <td align="center" class="tablerow1"><input id="permission_list_groups" name="web_flags[]" type="checkbox" value="ADMIN_LIST_GROUPS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_add_groups}</td>
                    <td align="center" class="tablerow1"><input id="permission_add_groups" name="web_flags[]" type="checkbox" value="ADMIN_ADD_GROUPS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_edit_groups}</td>
                    <td align="center" class="tablerow1"><input id="permission_edit_groups" name="web_flags[]" type="checkbox" value="ADMIN_EDIT_GROUPS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_delete_groups}</td>
                    <td align="center" class="tablerow1"><input id="permission_delete_groups" name="web_flags[]" type="checkbox" value="ADMIN_DELETE_GROUPS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_import_groups}</td>
                    <td align="center" class="tablerow1"><input id="permission_import_groups" name="web_flags[]" type="checkbox" value="ADMIN_IMPORT_GROUPS" /></td>
                  </tr>
                  <tr>
                    <td colspan="2" class="tablerow4">{$lang_mods}</td>
                    <td align="center" class="tablerow4"><input {nid id="permission_mods"} type="checkbox" value="-1" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_list_mods}</td>
                    <td align="center" class="tablerow1"><input id="permission_list_mods" name="web_flags[]" type="checkbox" value="ADMIN_LIST_MODS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_add_mods}</td>
                    <td align="center" class="tablerow1"><input id="permission_add_mods" name="web_flags[]" type="checkbox" value="ADMIN_ADD_MODS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_edit_mods}</td>
                    <td align="center" class="tablerow1"><input id="permission_edit_mods" name="web_flags[]" type="checkbox" value="ADMIN_EDIT_MODS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_delete_mods}</td>
                    <td align="center" class="tablerow1"><input id="permission_delete_mods" name="web_flags[]" type="checkbox" value="ADMIN_DELETE_MODS" /></td>
                  </tr>
                  <tr class="tablerow4">
                    <td colspan="2" class="tablerow4">{$lang_servers}</td>
                    <td align="center" class="tablerow4"><input {nid id="permission_servers"} type="checkbox" value="-1" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_list_servers}</td>
                    <td align="center" class="tablerow1"><input id="permission_list_servers" name="web_flags[]" type="checkbox" value="ADMIN_LIST_SERVERS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_add_servers}</td>
                    <td align="center" class="tablerow1"><input id="permission_add_servers" name="web_flags[]" type="checkbox" value="ADMIN_ADD_SERVERS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_edit_servers}</td>
                    <td align="center" class="tablerow1"><input id="permission_edit_servers" name="web_flags[]" type="checkbox" value="ADMIN_EDIT_SERVERS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_delete_servers}</td>
                    <td align="center" class="tablerow1"><input id="permission_delete_servers" name="web_flags[]" type="checkbox" value="ADMIN_DELETE_SERVERS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_import_servers}</td>
                    <td align="center" class="tablerow1"><input id="permission_import_servers" name="web_flags[]" type="checkbox" value="ADMIN_IMPORT_SERVERS" /></td>
                  </tr>
                  <tr>
                    <td colspan="2" class="tablerow4">{$lang_bans}</td>
                    <td align="center" class="tablerow4"><input {nid id="permission_bans"} type="checkbox" value="-1" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_add_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_add_bans" name="web_flags[]" type="checkbox" value="ADMIN_ADD_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_edit_own_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_edit_own_bans" name="web_flags[]" type="checkbox" value="ADMIN_EDIT_OWN_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_edit_group_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_edit_group_bans" name="web_flags[]" type="checkbox" value="ADMIN_EDIT_GROUP_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_edit_all_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_edit_all_bans" name="web_flags[]" type="checkbox" value="ADMIN_EDIT_ALL_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_unban_own_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_unban_own_bans" name="web_flags[]" type="checkbox" value="ADMIN_UNBAN_OWN_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_unban_group_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_unban_group_bans" name="web_flags[]" type="checkbox" value="ADMIN_UNBAN_GROUP_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_unban_all_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_unban_all_bans" name="web_flags[]" type="checkbox" value="ADMIN_UNBAN_ALL_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_delete_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_delete_bans" name="web_flags[]" type="checkbox" value="ADMIN_DELETE_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_import_bans}</td>
                    <td align="center" class="tablerow1"><input id="permission_import_bans" name="web_flags[]" type="checkbox" value="ADMIN_IMPORT_BANS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_ban_protests}</td>
                    <td align="center" class="tablerow1"><input id="permission_ban_protests" name="web_flags[]" type="checkbox" value="ADMIN_BAN_PROTESTS" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td width="15%">&nbsp;</td>
                    <td class="tablerow1">{$lang_ban_submissions}</td>
                    <td align="center" class="tablerow1"><input id="permission_ban_submissions" name="web_flags[]" type="checkbox" value="ADMIN_BAN_SUBMISSIONS" /></td>
                  </tr>
                  <tr>
                    <td colspan="2" class="tablerow4">{$lang_email_notifications}</td>
                    <td align="center" class="tablerow4"><input {nid id="permission_notify"} type="checkbox" value="-1" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_notify_protests}</td>
                    <td align="center" class="tablerow1"><input id="permission_notify_prot" name="web_flags[]" type="checkbox" value="ADMIN_NOTIFY_PROT" /></td>
                  </tr>
                  <tr class="tablerow1">
                    <td>&nbsp;</td>
                    <td class="tablerow1">{$lang_notify_submissions}</td>
                    <td align="center" class="tablerow1"><input id="permission_notify_sub" name="web_flags[]" type="checkbox" value="ADMIN_NOTIFY_SUB" /></td>
                  </tr>
                  <tr>
                    <td colspan="2" class="tablerow4">{$lang_settings}</td>
                    <td align="center" class="tablerow4"><input id="permission_settings" name="web_flags[]" type="checkbox" value="ADMIN_SETTINGS" /></td>
                  </tr>
                </table>
                <div class="center">
                  <input name="action" type="hidden" value="add" />
                  <input class="btn ok" type="submit" value="{$lang_save}" />
                  <input class="back btn cancel" type="button" value="{$lang_back}" />
                </div>
              </fieldset>
            </form>
            {/if}
            {if $permission_import_groups}
            <form action="{$active}" enctype="multipart/form-data" id="pane-import" method="post">
              <fieldset>
                <h3>{$lang_import_groups|ucwords}</h3>
                <p>{$lang_help_desc}</p>
                <label for="file">{help_icon title="$lang_file" desc="Select the admin_groups.cfg file to upload and add groups."}{$lang_file}</label>
                <input class="submit-fields" {nid id="file"} type="file" />
                <div class="badentry" id="file.msg"></div>
                <div class="center">
                  <input name="action" type="hidden" value="import" />
                  <input class="btn ok" type="submit" value="{$lang_save}" />
                  <input class="back btn cancel" type="button" value="{$lang_back}" />
                </div>
              </fieldset>
            </form>
            {/if}
          </div>