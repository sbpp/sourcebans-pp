          <div id="admin-page-menu">
            <ul>
              <li id="tab-permissions"><a href="#permissions">{$lang_view_permissions}</a></li>
              <li id="tab-settings"><a href="#settings">{$lang_settings}</a></li>
              <li id="tab-password"><a href="#password">{$lang_change_password}</a></li>
              <li id="tab-srvpassword"><a href="#srvpassword">Server Password</a></li>
              <li id="tab-email"><a href="#email">{$lang_change_email}</a></li>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
            </ul>
            <br />
            <div align="center">
              <img alt="{$page_title}" src="themes/{$theme_dir}/images/admin/account.png" title="{$page_title}" />
            </div>
          </div>
          <div id="admin-page-content">
            <div id="pane-permissions">
              <h3>{$lang_your_permissions|ucwords}</h3>
              <p>{$lang_your_permissions_desc}</p>
              <div class="permissions">
                <h4>{$lang_server_permissions}</h4>
                {if empty($user_srv_flags)}
                <em>{$lang_none}</em>
                {else}
                <ul>
                  {if $permission_reservation}
                  <li>{$lang_reservation_desc}</li>
                  {/if}
                  {if $permission_generic}
                  <li>{$lang_generic_desc}</li>
                  {/if}
                  {if $permission_kick}
                  <li>{$lang_kick_desc}</li>
                  {/if}
                  {if $permission_ban}
                  <li>{$lang_ban_desc}</li>
                  {/if}
                  {if $permission_unban}
                  <li>{$lang_unban_desc}</li>
                  {/if}
                  {if $permission_slay}
                  <li>{$lang_slay_desc}</li>
                  {/if}
                  {if $permission_changemap}
                  <li>{$lang_changemap_desc}</li>
                  {/if}
                  {if $permission_cvar}
                  <li>{$lang_cvar_desc}</li>
                  {/if}
                  {if $permission_config}
                  <li>{$lang_config_desc}</li>
                  {/if}
                  {if $permission_chat}
                  <li>{$lang_chat_desc}</li>
                  {/if}
                  {if $permission_vote}
                  <li>{$lang_vote_desc}</li>
                  {/if}
                  {if $permission_password}
                  <li>{$lang_password_desc}</li>
                  {/if}
                  {if $permission_rcon}
                  <li>{$lang_rcon_desc}</li>
                  {/if}
                  {if $permission_cheats}
                  <li>{$lang_cheats_desc}</li>
                  {/if}
                  {if $permission_custom1}
                  <li>{$lang_custom1_desc}</li>
                  {/if}
                  {if $permission_custom2}
                  <li>{$lang_custom2_desc}</li>
                  {/if}
                  {if $permission_custom3}
                  <li>{$lang_custom3_desc}</li>
                  {/if}
                  {if $permission_custom4}
                  <li>{$lang_custom4_desc}</li>
                  {/if}
                  {if $permission_custom5}
                  <li>{$lang_custom5_desc}</li>
                  {/if}
                  {if $permission_custom6}
                  <li>{$lang_custom6_desc}</li>
                  {/if}
                </ul>
                {/if}
              </div>
              <div class="permissions">
                <h4>{$lang_web_permissions}</h4>
                {if !$user_web_flags}
                <em>{$lang_none}</em>
                {else}
                <ul>
                  {if $permission_list_admins}
                  <li>{$lang_list_admins}</li>
                  {/if}
                  {if $permission_add_admins}
                  <li>{$lang_add_admins}</li>
                  {/if}
                  {if $permission_edit_admins}
                  <li>{$lang_edit_admins}</li>
                  {/if}
                  {if $permission_delete_admins}
                  <li>{$lang_delete_admins}</li>
                  {/if}
                  {if $permission_import_admins}
                  <li>{$lang_import_admins}</li>
                  {/if}
                  {if $permission_list_groups}
                  <li>{$lang_list_groups}</li>
                  {/if}
                  {if $permission_add_groups}
                  <li>{$lang_add_groups}</li>
                  {/if}
                  {if $permission_edit_groups}
                  <li>{$lang_edit_groups}</li>
                  {/if}
                  {if $permission_delete_groups}
                  <li>{$lang_delete_groups}</li>
                  {/if}
                  {if $permission_import_groups}
                  <li>{$lang_import_groups}</li>
                  {/if}
                  {if $permission_list_mods}
                  <li>{$lang_list_mods}</li>
                  {/if}
                  {if $permission_add_mods}
                  <li>{$lang_add_mods}</li>
                  {/if}
                  {if $permission_edit_mods}
                  <li>{$lang_edit_mods}</li>
                  {/if}
                  {if $permission_delete_mods}
                  <li>{$lang_delete_mods}</li>
                  {/if}
                  {if $permission_list_servers}
                  <li>{$lang_list_servers}</li>
                  {/if}
                  {if $permission_add_servers}
                  <li>{$lang_add_servers}</li>
                  {/if}
                  {if $permission_edit_servers}
                  <li>{$lang_edit_servers}</li>
                  {/if}
                  {if $permission_delete_servers}
                  <li>{$lang_delete_servers}</li>
                  {/if}
                  {if $permission_add_bans}
                  <li>{$lang_add_bans}</li>
                  {/if}
                  {if $permission_edit_all_bans}
                  <li>{$lang_edit_all_bans}</li>
                  {elseif $permission_edit_group_bans}
                  <li>{$lang_edit_group_bans}</li>
                  {elseif $permission_edit_own_bans}
                  <li>{$lang_edit_own_bans}</li>
                  {/if}
                  {if $permission_unban_all_bans}
                  <li>{$lang_unban_all_bans}</li>
                  {elseif $permission_unban_group_bans}
                  <li>{$lang_unban_group_bans}</li>
                  {elseif $permission_unban_own_bans}
                  <li>{$lang_unban_own_bans}</li>
                  {/if}
                  {if $permission_delete_bans}
                  <li>{$lang_delete_bans}</li>
                  {/if}
                  {if $permission_import_bans}
                  <li>{$lang_import_bans}</li>
                  {/if}
                  {if $permission_ban_protests}
                  <li>{$lang_ban_protests}</li>
                  {/if}
                  {if $permission_ban_submissions}
                  <li>{$lang_ban_submissions}</li>
                  {/if}
                  {if $permission_notify_prot}
                  <li>{$lang_notify_protests}</li>
                  {/if}
                  {if $permission_notify_sub}
                  <li>{$lang_notify_submissions}</li>
                  {/if}
                  {if $permission_settings}
                  <li>{$lang_settings}</li>
                  {/if}
                </ul>
                {/if}
              </div>
            </div>
            <form action="{$active}" id="pane-settings" method="post">
              <fieldset>
                <h3>{$lang_settings}</h3>
                <label for="language">{help_icon title="$lang_language" desc="$lang_language_desc"}{$lang_language}</label>
                <select class="submit-fields" {nid id="language"}>
                  {foreach from=$languages item=language}
                  <option{if $language.code == $user_language} selected="selected"{/if} value="{$language.code}">{$language.name}</option>
                  {/foreach}
                </select>
                <label for="theme">{help_icon title="$lang_theme" desc="$lang_theme_desc"}{$lang_theme}</label>
                <select class="submit-fields" {nid id="theme"}>
                  {foreach from=$themes item=theme}
                  <option{if $theme.dir == $user_theme} selected="selected"{/if} value="{$theme.dir}">{$theme.name}</option>
                  {/foreach}
                </select>
                <div class="center">
                  <input name="action" type="hidden" value="settings" />
                  <input class="btn ok" type="submit" value="{$lang_save}" />
                  <input class="back btn cancel" type="button" value="{$lang_back}" />
                </div>
              </fieldset>
            </form>
            <form action="{$active}" id="pane-password" method="post">
              <fieldset>
                <h3>{$lang_change_password|ucwords}</h3>
                <label for="password_current">{help_icon title="$lang_current_password" desc="$lang_current_password_desc"}{$lang_current_password}</label>
                <input class="submit-fields" id="password_current" name="current" type="password" />
                <div id="password_current.msg" class="badentry"></div>
                <br /><br />
                <label for="password">{help_icon title="$lang_new_password" desc="$lang_new_password_desc<br /><br /><em>Min Length: $min_pass_len</em>"}{$lang_new_password}</label>
                <input class="submit-fields" {nid id="password"} type="password" />
                <div id="password.msg" class="badentry"></div>
                <label for="password_confirm">{help_icon title="$lang_confirm_password" desc="$lang_confirm_password_desc"}{$lang_confirm_password}</label>
                <input class="submit-fields" {nid id="password_confirm"} type="password" />
                <div id="password_confirm.msg" class="badentry"></div>
                <div class="center">
                  <input name="action" type="hidden" value="password" />
                  <input class="btn ok" type="submit" value="{$lang_save}" />
                  <input class="back btn cancel" type="button" value="{$lang_back}" />
                </div>
              </fieldset>
            </form>
            <form action="{$active}" id="pane-srvpassword" method="post">
              <fieldset>
                <h3>{$lang_change_server_password|ucwords}</h3>
                <label for="srvpassword">{help_icon title="$lang_new_password" desc="$lang_new_password_desc<br /><br /><em>Min Length: 0</em>"}{$lang_new_password}</label>
                <input class="submit-fields" {nid id="srvpassword"} type="password" />
                <div id="srvpassword.msg" class="badentry"></div>
                <label for="srvpassword_confirm">{help_icon title="$lang_confirm_password" desc="$lang_confirm_password_desc"}{$lang_confirm_password}</label>
                <input class="submit-fields" {nid id="srvpassword_confirm"} type="password" />
                <div id="srvpassword_confirm.msg" class="badentry"></div>
                <div class="center">
                  <input name="action" type="hidden" value="srvpassword" />
                  <input class="btn ok" type="submit" value="{$lang_save}" />
                  <input class="back btn cancel" type="button" value="{$lang_back}" />
                </div>
              </fieldset>
            </form>
            <form action="{$active}" id="pane-email" method="post">
              <fieldset>
                <h3>{$lang_change_email|ucwords}</h3>
                <label for="email_current">{help_icon title="$lang_current_email" desc="$lang_current_email_desc"}{$lang_current_email}</label>
                <input class="submit-fields" {nid id="email_current"} readonly="readonly" value="{$user_email}" />
                <label for="email">{help_icon title="$lang_new_email" desc="$lang_new_email_desc"}{$lang_new_email}</label>
                <input class="submit-fields" {nid id="email"} />
                <div id="email.msg" class="badentry"></div>
                <label for="email_confirm">{help_icon title="$lang_confirm_email" desc="$lang_confirm_email_desc"}{$lang_confirm_email}</label>
                <input class="submit-fields" {nid id="email_confirm"} />
                <div id="email_confirm.msg" class="badentry"></div>
                <div class="center">
                  <input name="action" type="hidden" value="email" />
                  <input class="btn ok" type="submit" value="{$lang_save}" />
                  <input class="back btn cancel" type="button" value="{$lang_back}" />
                </div>
              </fieldset>
            </form>
          </div>