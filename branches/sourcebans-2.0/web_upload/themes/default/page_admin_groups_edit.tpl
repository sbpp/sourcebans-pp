          <div id="admin-page-menu">
            <ul>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
              <li class="active"><a class="back" href="#">{$lang_back}</a></li>
            </ul>
            <br />
            <div align="center">
              <img alt="{$page_title}" src="themes/{$theme_dir}/images/admin/groups.png" title="{$page_title}" />
            </div>
          </div>
          <form action="{$active}" id="admin-page-content" method="post">
            <fieldset>
              <h3>{$lang_edit_group|ucwords}</h3>
              <p>{$lang_help_desc}</p>
              <label for="name">{help_icon title="$lang_name" desc="Type the name of the new group you want to create."}{$lang_name}</label>
              <input class="submit-fields" {nid id="name"} value="{$group_name}" />
              <div id="name.msg" class="badentry"></div>
              {if $smarty.get.type == $smarty.const.SERVER_ADMIN_GROUPS}
              <label for="permissions">{help_icon title="$lang_server_permissions" desc="Choose the group's permissions here."}{$lang_server_permissions}</label>
              <table align="center" cellspacing="0" cellpadding="4" class="group_type" id="group_type_{$smarty.const.SERVER_ADMIN_GROUPS}" width="90%">
                <tr>
                  <td colspan="2" class="tablerow4">{$lang_name}</td>
                  <td class="tablerow4">Flag</td>
                  <td colspan="2" class="tablerow4">Purpose</td>
                </tr>
                <tr>
                  <td colspan="2" class="tablerow2">Root (Full Server Access)</td>
                  <td class="tablerow2" align="center">{$smarty.const.SM_ROOT}</td>
                  <td class="tablerow2">Magically enables all flags.</td>
                  <td align="center" class="tablerow2"><input{if $group_permission_root} checked="checked"{/if} id="permission_root" name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_ROOT}" /></td>
                </tr>
                <tr>
                  <td colspan="5" class="tablerow4">Standard Server Permissions</td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Reservation</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_RESERVATION}</td>
                  <td class="tablerow1">{$lang_reservation_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_reservation} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_RESERVATION}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Generic</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_GENERIC}</td>
                  <td class="tablerow1">{$lang_generic_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_generic} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_GENERIC}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Kick</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_KICK}</td>
                  <td class="tablerow1">{$lang_kick_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_kick} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_KICK}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Ban</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_BAN}</td>
                  <td class="tablerow1">{$lang_ban_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_ban} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_BAN}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Unban</td>
                  <td align="center" class="tablerow1">{$smarty.const.SM_UNBAN}</td>
                  <td class="tablerow1">{$lang_unban_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_unban} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_UNBAN}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Slay</td>
                  <td align="center" class="tablerow1">{$smarty.const.SM_SLAY}</td>
                  <td class="tablerow1">{$lang_slay_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_slay} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_SLAY}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Change Map</td>
                  <td align="center" class="tablerow1">{$smarty.const.SM_CHANGEMAP}</td>
                  <td class="tablerow1">{$lang_changemap_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_changemap} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CHANGEMAP}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Cvar</td>
                  <td align="center" class="tablerow1">{$smarty.const.SM_CVAR}</td>
                  <td class="tablerow1">{$lang_cvar_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_cvar} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CVAR}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Config</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CONFIG}</td>
                  <td class="tablerow1">{$lang_config_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_config} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CONFIG}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Chat</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CHAT}</td>
                  <td class="tablerow1">{$lang_chat_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_chat} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CHAT}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Vote</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_VOTE}</td>
                  <td class="tablerow1">{$lang_vote_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_vote} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_VOTE}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Password</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_PASSWORD}</td>
                  <td class="tablerow1">{$lang_password_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_password} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_PASSWORD}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">RCON</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_RCON}</td>
                  <td class="tablerow1">{$lang_rcon_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_rcon} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_RCON}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Cheats</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CHEATS}</td>
                  <td class="tablerow1">{$lang_cheats_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_cheats} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CHEATS}" /></td>
                </tr>
                <tr>
                  <td colspan="5" class="tablerow4">Custom Server Permissions</td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_custom1_desc}</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM1}</td>
                  <td class="tablerow1">&nbsp;</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_custom1} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM1}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_custom2_desc}</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM2}</td>
                  <td class="tablerow1">&nbsp;</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_custom2} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM2}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_custom3_desc}</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM3}</td>
                  <td class="tablerow1">&nbsp;</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_custom3} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM3}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_custom4_desc}</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM4}</td>
                  <td class="tablerow1">&nbsp;</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_custom4} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM4}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_custom5_desc}</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM5}</td>
                  <td class="tablerow1">&nbsp;</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_custom5} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM5}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_custom6_desc}</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM6}</td>
                  <td class="tablerow1">&nbsp;</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_custom6} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM6}" /></td>
                </tr>
                <tr>
                  <td colspan="5" class="tablerow4">{$lang_immunity_level}</td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_immunity_level}</td>
                  <td class="tablerow1" align="center"></td>
                  <td class="tablerow1">Choose the immunity level. The higher the number, the more immunity.</td>
                  <td align="center" class="tablerow1"><input {nid id="immunity"} maxlength="3" style="width: 25px; text-align: center;" value="{$group_immunity}" /></td>
                </tr>
              </table>
              <label for="overrides">{help_icon title="Overrides" desc="Choose the group's overrides here."}Overrides</label>
              <table align="center" cellspacing="0" cellpadding="4" id="overrides" width="90%">
                <tr>
                  <td class="tablerow4">{$lang_type}</td>
                  <td class="tablerow4">{$lang_name}</td>
                  <td class="tablerow4">{$lang_access}</td>
                </tr>
                {foreach from=$group_overrides item=override}
                <tr>
                  <td class="tablerow1">
                    <select name="override_type[]">
                      <option{if $override.type == "command"} selected="selected"{/if} value="command">{$lang_command}</option>
                      <option{if $override.type == "group"} selected="selected"{/if} value="group">{$lang_group}</option>
                    </select>
                  </td>
                  <td class="tablerow1"><input name="override_name[]" value="{$override.name}" /></td>
                  <td class="tablerow1">
                    <select name="override_access[]">
                      <option{if $override.access == "allow"} selected="selected"{/if} value="allow">{$lang_allow}</option>
                      <option{if $override.access == "deny"} selected="selected"{/if} value="deny">{$lang_deny}</option>
                    </select>
                  </td>
                </tr>
                {/foreach}
                <tr>
                  <td class="tablerow1">
                    <select name="override_type[]">
                      <option value="command">{$lang_command}</option>
                      <option value="group">{$lang_group}</option>
                    </select>
                  </td>
                  <td class="tablerow1"><input name="override_name[]" /></td>
                  <td class="tablerow1">
                    <select name="override_access[]">
                      <option value="allow">{$lang_allow}</option>
                      <option value="deny">{$lang_deny}</option>
                    </select>
                  </td>
                </tr>
              </table>
              {/if}
              {if $smarty.get.type == $smarty.const.WEB_ADMIN_GROUPS}
              <label for="permissions">{help_icon title="$lang_web_permissions" desc="Choose the group's permissions here."}{$lang_web_permissions}</label>
              <table align="center" cellspacing="0" cellpadding="4" class="group_type" id="group_type_{$smarty.const.WEB_ADMIN_GROUPS}" width="90%">
                <tr>
                  <td colspan="2" class="tablerow2">Owner (Full Web Access)</td>
                  <td align="center" class="tablerow2"><input{if $group_permission_owner} checked="checked"{/if} id="permission_owner" name="web_flags[]" type="checkbox" value="ADMIN_OWNER" /></td>
                </tr>
                <tr>
                  <td colspan="2" class="tablerow4">{$lang_admins}</td>
                  <td align="center" class="tablerow4"><input{if $group_permission_admins} checked="checked"{/if} {nid id="permission_admins"} type="checkbox" value="-1" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_list_admins}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_list_admins} checked="checked"{/if} id="permission_list_admins" name="web_flags[]" type="checkbox" value="ADMIN_LIST_ADMINS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_add_admins}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_add_admins} checked="checked"{/if} id="permission_add_admins" name="web_flags[]" type="checkbox" value="ADMIN_ADD_ADMINS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_edit_admins}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_edit_admins} checked="checked"{/if} id="permission_edit_admins" name="web_flags[]" type="checkbox" value="ADMIN_EDIT_ADMINS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_delete_admins}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_delete_admins} checked="checked"{/if} id="permission_delete_admins" name="web_flags[]" type="checkbox" value="ADMIN_DELETE_ADMINS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_import_admins}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_import_admins} checked="checked"{/if} id="permission_import_admins" name="web_flags[]" type="checkbox" value="ADMIN_IMPORT_ADMINS" /></td>
                </tr>
                <tr>
                  <td colspan="2" class="tablerow4">{$lang_groups}</td>
                  <td align="center" class="tablerow4"><input{if $group_permission_groups} checked="checked"{/if} {nid id="permission_groups"} type="checkbox" value="-1" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_list_groups}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_list_groups} checked="checked"{/if} id="permission_list_groups" name="web_flags[]" type="checkbox" value="ADMIN_LIST_GROUPS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_add_groups}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_add_groups} checked="checked"{/if} id="permission_add_groups" name="web_flags[]" type="checkbox" value="ADMIN_ADD_GROUPS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_edit_groups}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_edit_groups} checked="checked"{/if} id="permission_edit_groups" name="web_flags[]" type="checkbox" value="ADMIN_EDIT_GROUPS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_delete_groups}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_delete_groups} checked="checked"{/if} id="permission_delete_groups" name="web_flags[]" type="checkbox" value="ADMIN_DELETE_GROUPS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_import_groups}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_import_groups} checked="checked"{/if} id="permission_import_groups" name="web_flags[]" type="checkbox" value="ADMIN_IMPORT_GROUPS" /></td>
                </tr>
                <tr>
                  <td colspan="2" class="tablerow4">{$lang_mods}</td>
                  <td align="center" class="tablerow4"><input{if $group_permission_mods} checked="checked"{/if} {nid id="permission_mods"} type="checkbox" value="-1" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_list_mods}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_list_mods} checked="checked"{/if} id="permission_list_mods" name="web_flags[]" type="checkbox" value="ADMIN_LIST_MODS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_add_mods}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_add_mods} checked="checked"{/if} id="permission_add_mods" name="web_flags[]" type="checkbox" value="ADMIN_ADD_MODS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_edit_mods}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_edit_mods} checked="checked"{/if} id="permission_edit_mods" name="web_flags[]" type="checkbox" value="ADMIN_EDIT_MODS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_delete_mods}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_delete_mods} checked="checked"{/if} id="permission_delete_mods" name="web_flags[]" type="checkbox" value="ADMIN_DELETE_MODS" /></td>
                </tr>
                <tr class="tablerow4">
                  <td colspan="2" class="tablerow4">{$lang_servers}</td>
                  <td align="center" class="tablerow4"><input{if $group_permission_servers} checked="checked"{/if} {nid id="permission_servers"} type="checkbox" value="-1" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_list_servers}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_list_servers} checked="checked"{/if} id="permission_list_servers" name="web_flags[]" type="checkbox" value="ADMIN_LIST_SERVERS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_add_servers}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_add_servers} checked="checked"{/if} id="permission_add_servers" name="web_flags[]" type="checkbox" value="ADMIN_ADD_SERVERS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_edit_servers}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_edit_servers} checked="checked"{/if} id="permission_edit_servers" name="web_flags[]" type="checkbox" value="ADMIN_EDIT_SERVERS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_delete_servers}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_delete_servers} checked="checked"{/if} id="permission_delete_servers" name="web_flags[]" type="checkbox" value="ADMIN_DELETE_SERVERS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_import_servers}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_import_servers} checked="checked"{/if} id="permission_import_servers" name="web_flags[]" type="checkbox" value="ADMIN_IMPORT_SERVERS" /></td>
                </tr>
                <tr>
                  <td colspan="2" class="tablerow4">{$lang_bans}</td>
                  <td align="center" class="tablerow4"><input{if $group_permission_bans} checked="checked"{/if} {nid id="permission_bans"} type="checkbox" value="-1" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_add_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_add_bans} checked="checked"{/if} id="permission_add_bans" name="web_flags[]" type="checkbox" value="ADMIN_ADD_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_edit_own_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_edit_own_bans} checked="checked"{/if} id="permission_edit_own_bans" name="web_flags[]" type="checkbox" value="ADMIN_EDIT_OWN_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_edit_group_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_edit_group_bans} checked="checked"{/if} id="permission_edit_group_bans" name="web_flags[]" type="checkbox" value="ADMIN_EDIT_GROUP_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_edit_all_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_edit_all_bans} checked="checked"{/if} id="permission_edit_all_bans" name="web_flags[]" type="checkbox" value="ADMIN_EDIT_ALL_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_unban_own_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_unban_own_bans} checked="checked"{/if} id="permission_unban_own_bans" name="web_flags[]" type="checkbox" value="ADMIN_UNBAN_OWN_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_unban_group_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_unban_group_bans} checked="checked"{/if} id="permission_unban_group_bans" name="web_flags[]" type="checkbox" value="ADMIN_UNBAN_GROUP_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_unban_all_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_unban_all_bans} checked="checked"{/if} id="permission_unban_all_bans" name="web_flags[]" type="checkbox" value="ADMIN_UNBAN_ALL_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_delete_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_delete_bans} checked="checked"{/if} id="permission_delete_bans" name="web_flags[]" type="checkbox" value="ADMIN_DELETE_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_import_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_import_bans} checked="checked"{/if} id="permission_import_bans" name="web_flags[]" type="checkbox" value="ADMIN_IMPORT_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_ban_protests}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_ban_protests} checked="checked"{/if} id="permission_ban_protests" name="web_flags[]" type="checkbox" value="ADMIN_BAN_PROTESTS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$lang_ban_submissions}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_ban_submissions} checked="checked"{/if} id="permission_ban_submissions" name="web_flags[]" type="checkbox" value="ADMIN_BAN_SUBMISSIONS" /></td>
                </tr>
                <tr>
                  <td colspan="2" class="tablerow4">{$lang_email_notifications}</td>
                  <td align="center" class="tablerow4"><input{if $group_permission_notify} checked="checked"{/if} {nid id="permission_notify"} type="checkbox" value="-1" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_notify_protests}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_notify_prot} checked="checked"{/if} id="permission_notify_prot" name="web_flags[]" type="checkbox" value="ADMIN_NOTIFY_PROT" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$lang_notify_submissions}</td>
                  <td align="center" class="tablerow1"><input{if $group_permission_notify_sub} checked="checked"{/if} id="permission_notify_sub" name="web_flags[]" type="checkbox" value="ADMIN_NOTIFY_SUB" /></td>
                </tr>
                <tr>
                  <td colspan="2" class="tablerow4">{$lang_settings}</td>
                  <td align="center" class="tablerow4"><input{if $group_permission_settings} checked="checked"{/if} id="permission_settings" name="web_flags[]" type="checkbox" value="ADMIN_SETTINGS" /></td>
                </tr>
              </table>
              {/if}
              <div class="center">
                <input class="btn ok" type="submit" value="{$lang_save}" />
                <input class="back btn cancel" type="button" value="{$lang_back}" />
              </div>
            </fieldset>
          </form>