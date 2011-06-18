          <div id="admin-page-menu">
            <ul>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
              <li class="active"><a class="back" href="#">{$language->back}</a></li>
            </ul>
            <br />
            <div class="center">
              <img alt="{$action_title}" src="{$uri->base}/themes/{$theme}/images/admin/groups.png" title="{$action_title}" />
            </div>
          </div>
          <form action="" id="admin-page-content" method="post">
            <fieldset>
              <h3>{$language->edit_group|ucwords}</h3>
              <p>{$language->help_desc}</p>
              <label for="name">{help_icon title="`$language->name`" desc="Type the name of the new group you want to create."}{$language->name}</label>
              <input class="submit-fields" {nid id="name"} value="{$group->name}" />
              {if $uri->type == $smarty.const.SERVER_GROUPS}
              <label for="permissions">{help_icon title="`$language->server_permissions`" desc="Choose the group's permissions here."}{$language->server_permissions}</label>
              <table align="center" cellspacing="0" cellpadding="4" class="group->type" id="group_type_{$smarty.const.SERVER_GROUPS}" width="90%">
                <tr>
                  <td colspan="2" class="tablerow4">{$language->name}</td>
                  <td class="tablerow4">Flag</td>
                  <td colspan="2" class="tablerow4">Purpose</td>
                </tr>
                <tr>
                  <td colspan="2" class="tablerow2">Root (Full Server Access)</td>
                  <td class="tablerow2" align="center">{$smarty.const.SM_ROOT}</td>
                  <td class="tablerow2">Magically enables all flags.</td>
                  <td align="center" class="tablerow2"><input{if $group->permission_root} checked="checked"{/if} id="permission_root" name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_ROOT}" /></td>
                </tr>
                <tr>
                  <td colspan="5" class="tablerow4">Standard Server Permissions</td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Reservation</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_RESERVATION}</td>
                  <td class="tablerow1">{$language->reservation_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_reservation} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_RESERVATION}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Generic</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_GENERIC}</td>
                  <td class="tablerow1">{$language->generic_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_generic} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_GENERIC}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Kick</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_KICK}</td>
                  <td class="tablerow1">{$language->kick_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_kick} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_KICK}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Ban</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_BAN}</td>
                  <td class="tablerow1">{$language->ban_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_ban} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_BAN}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Unban</td>
                  <td align="center" class="tablerow1">{$smarty.const.SM_UNBAN}</td>
                  <td class="tablerow1">{$language->unban_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_unban} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_UNBAN}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Slay</td>
                  <td align="center" class="tablerow1">{$smarty.const.SM_SLAY}</td>
                  <td class="tablerow1">{$language->slay_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_slay} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_SLAY}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Change Map</td>
                  <td align="center" class="tablerow1">{$smarty.const.SM_CHANGEMAP}</td>
                  <td class="tablerow1">{$language->changemap_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_changemap} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CHANGEMAP}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Cvar</td>
                  <td align="center" class="tablerow1">{$smarty.const.SM_CVAR}</td>
                  <td class="tablerow1">{$language->cvar_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_cvar} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CVAR}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Config</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CONFIG}</td>
                  <td class="tablerow1">{$language->config_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_config} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CONFIG}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Chat</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CHAT}</td>
                  <td class="tablerow1">{$language->chat_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_chat} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CHAT}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Vote</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_VOTE}</td>
                  <td class="tablerow1">{$language->vote_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_vote} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_VOTE}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Password</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_PASSWORD}</td>
                  <td class="tablerow1">{$language->password_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_password} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_PASSWORD}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">RCON</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_RCON}</td>
                  <td class="tablerow1">{$language->rcon_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_rcon} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_RCON}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">Cheats</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CHEATS}</td>
                  <td class="tablerow1">{$language->cheats_desc}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_cheats} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CHEATS}" /></td>
                </tr>
                <tr>
                  <td colspan="5" class="tablerow4">Custom Server Permissions</td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->custom1_desc}</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM1}</td>
                  <td class="tablerow1">&nbsp;</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_custom1} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM1}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->custom2_desc}</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM2}</td>
                  <td class="tablerow1">&nbsp;</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_custom2} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM2}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->custom3_desc}</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM3}</td>
                  <td class="tablerow1">&nbsp;</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_custom3} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM3}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->custom4_desc}</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM4}</td>
                  <td class="tablerow1">&nbsp;</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_custom4} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM4}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->custom5_desc}</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM5}</td>
                  <td class="tablerow1">&nbsp;</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_custom5} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM5}" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->custom6_desc}</td>
                  <td class="tablerow1" align="center">{$smarty.const.SM_CUSTOM6}</td>
                  <td class="tablerow1">&nbsp;</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_custom6} checked="checked"{/if} name="srv_flags[]" type="checkbox" value="{$smarty.const.SM_CUSTOM6}" /></td>
                </tr>
                <tr>
                  <td colspan="5" class="tablerow4">{$language->immunity_level}</td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->immunity_level}</td>
                  <td class="tablerow1" align="center"></td>
                  <td class="tablerow1">Choose the immunity level. The higher the number, the more immunity.</td>
                  <td align="center" class="tablerow1"><input {nid id="immunity"} maxlength="3" value="{$group->immunity}" /></td>
                </tr>
              </table>
              <label for="overrides">{help_icon title="Overrides" desc="Choose the group's overrides here."}Overrides (<a id="add_override" href="#">Add</a>)</label>
              <table align="center" cellspacing="0" cellpadding="4" id="overrides" width="90%">
                <tr>
                  <td class="tablerow4">{$language->type}</td>
                  <td class="tablerow4">{$language->name}</td>
                  <td class="tablerow4">{$language->access}</td>
                </tr>
                {foreach from=$group->overrides item=override}
                <tr>
                  <td class="tablerow1">
                    <select name="override_type[]">
                      <option{if $override->type == "command"} selected="selected"{/if} value="command">{$language->command}</option>
                      <option{if $override->type == "group"} selected="selected"{/if} value="group">{$language->group}</option>
                    </select>
                  </td>
                  <td class="tablerow1"><input name="override_name[]" value="{$override->name}" /></td>
                  <td class="tablerow1">
                    <select name="override_access[]">
                      <option{if $override->access == "allow"} selected="selected"{/if} value="allow">{$language->allow}</option>
                      <option{if $override->access == "deny"} selected="selected"{/if} value="deny">{$language->deny}</option>
                    </select>
                  </td>
                </tr>
                {/foreach}
                <tr id="override">
                  <td class="tablerow1">
                    <select name="override_type[]">
                      <option value="command">{$language->command}</option>
                      <option value="group">{$language->group}</option>
                    </select>
                  </td>
                  <td class="tablerow1"><input name="override_name[]" /></td>
                  <td class="tablerow1">
                    <select name="override_access[]">
                      <option value="allow">{$language->allow}</option>
                      <option value="deny">{$language->deny}</option>
                    </select>
                  </td>
                </tr>
              </table>
              {/if}
              {if $uri->type == $smarty.const.WEB_GROUPS}
              <label for="permissions">{help_icon title="`$language->web_permissions`" desc="Choose the group's permissions here."}{$language->web_permissions}</label>
              <table align="center" cellspacing="0" cellpadding="4" class="group->type" id="group_type_{$smarty.const.WEB_GROUPS}" width="90%">
                <tr>
                  <td colspan="2" class="tablerow2">Owner (Full Web Access)</td>
                  <td align="center" class="tablerow2"><input{if $group->permission_owner} checked="checked"{/if} id="permission_owner" name="web_flags[]" type="checkbox" value="OWNER" /></td>
                </tr>
                <tr>
                  <td colspan="2" class="tablerow4">{$language->admins}</td>
                  <td align="center" class="tablerow4"><input{if $group->permission_admins} checked="checked"{/if} {nid id="permission_admins"} type="checkbox" value="-1" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->list_admins}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_list_admins} checked="checked"{/if} id="permission_list_admins" name="web_flags[]" type="checkbox" value="LIST_ADMINS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->add_admins}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_add_admins} checked="checked"{/if} id="permission_add_admins" name="web_flags[]" type="checkbox" value="ADD_ADMINS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->edit_admins}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_edit_admins} checked="checked"{/if} id="permission_edit_admins" name="web_flags[]" type="checkbox" value="EDIT_ADMINS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->delete_admins}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_delete_admins} checked="checked"{/if} id="permission_delete_admins" name="web_flags[]" type="checkbox" value="DELETE_ADMINS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->import_admins}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_import_admins} checked="checked"{/if} id="permission_import_admins" name="web_flags[]" type="checkbox" value="IMPORT_ADMINS" /></td>
                </tr>
                <tr>
                  <td colspan="2" class="tablerow4">{$language->groups}</td>
                  <td align="center" class="tablerow4"><input{if $group->permission_groups} checked="checked"{/if} {nid id="permission_groups"} type="checkbox" value="-1" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->list_groups}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_list_groups} checked="checked"{/if} id="permission_list_groups" name="web_flags[]" type="checkbox" value="LIST_GROUPS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->add_groups}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_add_groups} checked="checked"{/if} id="permission_add_groups" name="web_flags[]" type="checkbox" value="ADD_GROUPS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->edit_groups}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_edit_groups} checked="checked"{/if} id="permission_edit_groups" name="web_flags[]" type="checkbox" value="EDIT_GROUPS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->delete_groups}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_delete_groups} checked="checked"{/if} id="permission_delete_groups" name="web_flags[]" type="checkbox" value="DELETE_GROUPS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->import_groups}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_import_groups} checked="checked"{/if} id="permission_import_groups" name="web_flags[]" type="checkbox" value="IMPORT_GROUPS" /></td>
                </tr>
                <tr>
                  <td colspan="2" class="tablerow4">{$language->games}</td>
                  <td align="center" class="tablerow4"><input{if $group->permission_games} checked="checked"{/if} {nid id="permission_games"} type="checkbox" value="-1" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->list_games}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_list_games} checked="checked"{/if} id="permission_list_games" name="web_flags[]" type="checkbox" value="LIST_GAMES" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->add_games}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_add_games} checked="checked"{/if} id="permission_add_games" name="web_flags[]" type="checkbox" value="ADD_GAMES" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->edit_games}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_edit_games} checked="checked"{/if} id="permission_edit_games" name="web_flags[]" type="checkbox" value="EDIT_GAMES" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->delete_games}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_delete_games} checked="checked"{/if} id="permission_delete_games" name="web_flags[]" type="checkbox" value="DELETE_GAMES" /></td>
                </tr>
                <tr class="tablerow4">
                  <td colspan="2" class="tablerow4">{$language->servers}</td>
                  <td align="center" class="tablerow4"><input{if $group->permission_servers} checked="checked"{/if} {nid id="permission_servers"} type="checkbox" value="-1" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->list_servers}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_list_servers} checked="checked"{/if} id="permission_list_servers" name="web_flags[]" type="checkbox" value="LIST_SERVERS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->add_servers}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_add_servers} checked="checked"{/if} id="permission_add_servers" name="web_flags[]" type="checkbox" value="ADD_SERVERS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->edit_servers}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_edit_servers} checked="checked"{/if} id="permission_edit_servers" name="web_flags[]" type="checkbox" value="EDIT_SERVERS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->delete_servers}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_delete_servers} checked="checked"{/if} id="permission_delete_servers" name="web_flags[]" type="checkbox" value="DELETE_SERVERS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->import_servers}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_import_servers} checked="checked"{/if} id="permission_import_servers" name="web_flags[]" type="checkbox" value="IMPORT_SERVERS" /></td>
                </tr>
                <tr>
                  <td colspan="2" class="tablerow4">{$language->bans}</td>
                  <td align="center" class="tablerow4"><input{if $group->permission_bans} checked="checked"{/if} {nid id="permission_bans"} type="checkbox" value="-1" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->add_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_add_bans} checked="checked"{/if} id="permission_add_bans" name="web_flags[]" type="checkbox" value="ADD_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->edit_own_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_edit_own_bans} checked="checked"{/if} id="permission_edit_own_bans" name="web_flags[]" type="checkbox" value="EDIT_OWN_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->edit_group_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_edit_group_bans} checked="checked"{/if} id="permission_edit_group_bans" name="web_flags[]" type="checkbox" value="EDIT_GROUP_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->edit_all_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_edit_all_bans} checked="checked"{/if} id="permission_edit_all_bans" name="web_flags[]" type="checkbox" value="EDIT_ALL_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->unban_own_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_unban_own_bans} checked="checked"{/if} id="permission_unban_own_bans" name="web_flags[]" type="checkbox" value="UNBAN_OWN_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->unban_group_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_unban_group_bans} checked="checked"{/if} id="permission_unban_group_bans" name="web_flags[]" type="checkbox" value="UNBAN_GROUP_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->unban_all_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_unban_all_bans} checked="checked"{/if} id="permission_unban_all_bans" name="web_flags[]" type="checkbox" value="UNBAN_ALL_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->delete_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_delete_bans} checked="checked"{/if} id="permission_delete_bans" name="web_flags[]" type="checkbox" value="DELETE_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->import_bans}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_import_bans} checked="checked"{/if} id="permission_import_bans" name="web_flags[]" type="checkbox" value="IMPORT_BANS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->ban_protests}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_ban_protests} checked="checked"{/if} id="permission_ban_protests" name="web_flags[]" type="checkbox" value="BAN_PROTESTS" /></td>
                </tr>
                <tr class="tablerow1">
                  <td width="15%">&nbsp;</td>
                  <td class="tablerow1">{$language->ban_submissions}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_ban_submissions} checked="checked"{/if} id="permission_ban_submissions" name="web_flags[]" type="checkbox" value="BAN_SUBMISSIONS" /></td>
                </tr>
                <tr>
                  <td colspan="2" class="tablerow4">{$language->email_notifications}</td>
                  <td align="center" class="tablerow4"><input{if $group->permission_notify} checked="checked"{/if} {nid id="permission_notify"} type="checkbox" value="-1" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->notify_protests}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_notify_prot} checked="checked"{/if} id="permission_notify_prot" name="web_flags[]" type="checkbox" value="NOTIFY_PROT" /></td>
                </tr>
                <tr class="tablerow1">
                  <td>&nbsp;</td>
                  <td class="tablerow1">{$language->notify_submissions}</td>
                  <td align="center" class="tablerow1"><input{if $group->permission_notify_sub} checked="checked"{/if} id="permission_notify_sub" name="web_flags[]" type="checkbox" value="NOTIFY_SUB" /></td>
                </tr>
                <tr>
                  <td colspan="2" class="tablerow4">{$language->settings}</td>
                  <td align="center" class="tablerow4"><input{if $group->permission_settings} checked="checked"{/if} id="permission_settings" name="web_flags[]" type="checkbox" value="SETTINGS" /></td>
                </tr>
              </table>
              {/if}
              <div class="center">
                <input name="id" type="hidden" value="{$uri->id}" />
                <input name="type" type="hidden" value="{$uri->type}" />
                <input class="btn ok" type="submit" value="{$language->save}" />
                <input class="back btn cancel" type="button" value="{$language->back}" />
              </div>
            </fieldset>
          </form>