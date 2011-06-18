          <div id="admin-page-menu">
            <ul>
              <li id="tab-permissions"><a href="#permissions">{$language->view_permissions}</a></li>
              <li id="tab-settings"><a href="#settings">{$language->settings}</a></li>
              <li id="tab-email"><a href="#email">{$language->email}</a></li>
              <li id="tab-password"><a href="#password">{$language->password}</a></li>
              <li id="tab-srvpassword"><a href="#srvpassword">{$language->server_password}</a></li>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
            </ul>
            <br />
            <div class="center">
              <img alt="{$action_title}" src="{$uri->base}/themes/{$theme}/images/admin/account.png" title="{$action_title}" />
            </div>
          </div>
          <div id="admin-page-content">
            <div id="pane-permissions">
              <h3>{$language->your_permissions|ucwords}</h3>
              <p>{$language->your_permissions_desc}</p>
              <div class="permissions">
                <h4>{$language->server_permissions}</h4>
                {if empty($user->srv_flags)}
                <em>{$language->none}</em>
                {else}
                <ul>
                  {if $user->permission_reservation}
                  <li>{$language->reservation_desc}</li>
                  {/if}
                  {if $user->permission_generic}
                  <li>{$language->generic_desc}</li>
                  {/if}
                  {if $user->permission_kick}
                  <li>{$language->kick_desc}</li>
                  {/if}
                  {if $user->permission_ban}
                  <li>{$language->ban_desc}</li>
                  {/if}
                  {if $user->permission_unban}
                  <li>{$language->unban_desc}</li>
                  {/if}
                  {if $user->permission_slay}
                  <li>{$language->slay_desc}</li>
                  {/if}
                  {if $user->permission_changemap}
                  <li>{$language->changemap_desc}</li>
                  {/if}
                  {if $user->permission_cvar}
                  <li>{$language->cvar_desc}</li>
                  {/if}
                  {if $user->permission_config}
                  <li>{$language->config_desc}</li>
                  {/if}
                  {if $user->permission_chat}
                  <li>{$language->chat_desc}</li>
                  {/if}
                  {if $user->permission_vote}
                  <li>{$language->vote_desc}</li>
                  {/if}
                  {if $user->permission_password}
                  <li>{$language->password_desc}</li>
                  {/if}
                  {if $user->permission_rcon}
                  <li>{$language->rcon_desc}</li>
                  {/if}
                  {if $user->permission_cheats}
                  <li>{$language->cheats_desc}</li>
                  {/if}
                  {if $user->permission_custom1}
                  <li>{$language->custom1_desc}</li>
                  {/if}
                  {if $user->permission_custom2}
                  <li>{$language->custom2_desc}</li>
                  {/if}
                  {if $user->permission_custom3}
                  <li>{$language->custom3_desc}</li>
                  {/if}
                  {if $user->permission_custom4}
                  <li>{$language->custom4_desc}</li>
                  {/if}
                  {if $user->permission_custom5}
                  <li>{$language->custom5_desc}</li>
                  {/if}
                  {if $user->permission_custom6}
                  <li>{$language->custom6_desc}</li>
                  {/if}
                </ul>
                {/if}
              </div>
              <div class="permissions">
                <h4>{$language->web_permissions}</h4>
                {if !$user->is_admin()}
                <em>{$language->none}</em>
                {else}
                <ul>
                  {if $user->permission_list_admins}
                  <li>{$language->list_admins}</li>
                  {/if}
                  {if $user->permission_add_admins}
                  <li>{$language->add_admins}</li>
                  {/if}
                  {if $user->permission_edit_admins}
                  <li>{$language->edit_admins}</li>
                  {/if}
                  {if $user->permission_delete_admins}
                  <li>{$language->delete_admins}</li>
                  {/if}
                  {if $user->permission_import_admins}
                  <li>{$language->import_admins}</li>
                  {/if}
                  {if $user->permission_list_groups}
                  <li>{$language->list_groups}</li>
                  {/if}
                  {if $user->permission_add_groups}
                  <li>{$language->add_groups}</li>
                  {/if}
                  {if $user->permission_edit_groups}
                  <li>{$language->edit_groups}</li>
                  {/if}
                  {if $user->permission_delete_groups}
                  <li>{$language->delete_groups}</li>
                  {/if}
                  {if $user->permission_import_groups}
                  <li>{$language->import_groups}</li>
                  {/if}
                  {if $user->permission_list_games}
                  <li>{$language->list_games}</li>
                  {/if}
                  {if $user->permission_add_games}
                  <li>{$language->add_games}</li>
                  {/if}
                  {if $user->permission_edit_games}
                  <li>{$language->edit_games}</li>
                  {/if}
                  {if $user->permission_delete_games}
                  <li>{$language->delete_games}</li>
                  {/if}
                  {if $user->permission_list_servers}
                  <li>{$language->list_servers}</li>
                  {/if}
                  {if $user->permission_add_servers}
                  <li>{$language->add_servers}</li>
                  {/if}
                  {if $user->permission_edit_servers}
                  <li>{$language->edit_servers}</li>
                  {/if}
                  {if $user->permission_delete_servers}
                  <li>{$language->delete_servers}</li>
                  {/if}
                  {if $user->permission_add_bans}
                  <li>{$language->add_bans}</li>
                  {/if}
                  {if $user->permission_edit_all_bans}
                  <li>{$language->edit_all_bans}</li>
                  {elseif $user->permission_edit_group_bans}
                  <li>{$language->edit_group_bans}</li>
                  {elseif $user->permission_edit_own_bans}
                  <li>{$language->edit_own_bans}</li>
                  {/if}
                  {if $user->permission_unban_all_bans}
                  <li>{$language->unban_all_bans}</li>
                  {elseif $user->permission_unban_group_bans}
                  <li>{$language->unban_group_bans}</li>
                  {elseif $user->permission_unban_own_bans}
                  <li>{$language->unban_own_bans}</li>
                  {/if}
                  {if $user->permission_delete_bans}
                  <li>{$language->delete_bans}</li>
                  {/if}
                  {if $user->permission_import_bans}
                  <li>{$language->import_bans}</li>
                  {/if}
                  {if $user->permission_ban_protests}
                  <li>{$language->ban_protests}</li>
                  {/if}
                  {if $user->permission_ban_submissions}
                  <li>{$language->ban_submissions}</li>
                  {/if}
                  {if $user->permission_notify_prot}
                  <li>{$language->notify_protests}</li>
                  {/if}
                  {if $user->permission_notify_sub}
                  <li>{$language->notify_submissions}</li>
                  {/if}
                  {if $user->permission_settings}
                  <li>{$language->settings}</li>
                  {/if}
                </ul>
                {/if}
              </div>
            </div>
            <form action="" id="pane-settings" method="post">
              <fieldset>
                <h3>{$language->settings}</h3>
                <label for="language">{help_icon title="`$language->language`" desc="`$language->language_desc`"}{$language->language}</label>
                <select class="submit-fields" {nid id="language"}>
                  {foreach from=$languages item=_language}
                  <option{if $user->language == $_language} selected="selected"{/if} value="{$_language}">{$_language->getInfo('name')}</option>
                  {/foreach}
                </select>
                <label for="theme">{help_icon title="`$language->theme`" desc="`$language->theme_desc`"}{$language->theme}</label>
                <select class="submit-fields" {nid id="theme"}>
                  {foreach from=$themes item=_theme}
                  <option{if $user->theme == $_theme} selected="selected"{/if} value="{$_theme}">{$_theme->name}</option>
                  {/foreach}
                </select>
                <div class="center">
                  <input name="action" type="hidden" value="settings" />
                  <input class="btn ok" type="submit" value="{$language->save}" />
                  <input class="back btn cancel" type="button" value="{$language->back}" />
                </div>
              </fieldset>
            </form>
            <form action="" id="pane-email" method="post">
              <fieldset>
                <h3>{$language->email}</h3>
                <label for="email_current">{help_icon title="`$language->current_email`" desc="`$language->current_email_desc`"}{$language->current_email}</label>
                <input class="submit-fields" {nid id="email_current"} readonly="readonly" value="{$user->email}" />
                <label for="email">{help_icon title="`$language->new_email`" desc="`$language->new_email_desc`"}{$language->new_email}</label>
                <input class="submit-fields" {nid id="email"} />
                <label for="email_confirm">{help_icon title="`$language->confirm_email`" desc="`$language->confirm_email_desc`"}{$language->confirm_email}</label>
                <input class="submit-fields" {nid id="email_confirm"} />
                <div class="center">
                  <input name="action" type="hidden" value="email" />
                  <input class="btn ok" type="submit" value="{$language->save}" />
                  <input class="back btn cancel" type="button" value="{$language->back}" />
                </div>
              </fieldset>
            </form>
            <form action="" id="pane-password" method="post">
              <fieldset>
                <h3>{$language->password}</h3>
                <label for="password_current">{help_icon title="`$language->current_password`" desc="`$language->current_password_desc`"}{$language->current_password}</label>
                <input class="submit-fields" id="password_current" name="current" type="password" />
                <br /><br />
                <label for="password">{help_icon title="`$language->new_password`" desc="`$language->new_password_desc`<br /><br /><em>Min Length: `$settings->password_min_length`</em>"}{$language->new_password}</label>
                <input class="submit-fields" {nid id="password"} type="password" />
                <label for="password_confirm">{help_icon title="`$language->confirm_password`" desc="`$language->confirm_password_desc`"}{$language->confirm_password}</label>
                <input class="submit-fields" {nid id="password_confirm"} type="password" />
                <div class="center">
                  <input name="action" type="hidden" value="password" />
                  <input class="btn ok" type="submit" value="{$language->save}" />
                  <input class="back btn cancel" type="button" value="{$language->back}" />
                </div>
              </fieldset>
            </form>
            <form action="" id="pane-srvpassword" method="post">
              <fieldset>
                <h3>{$language->server_password|ucwords}</h3>
                <label for="srv_password">{help_icon title="`$language->new_password`" desc="`$language->new_password_desc`<br /><br /><em>Min Length: 0</em>"}{$language->new_password}</label>
                <input class="submit-fields" {nid id="srv_password"} type="password" />
                <label for="srv_password_confirm">{help_icon title="`$language->confirm_password`" desc="`$language->confirm_password_desc`"}{$language->confirm_password}</label>
                <input class="submit-fields" {nid id="srv_password_confirm"} type="password" />
                <div class="center">
                  <input name="action" type="hidden" value="srv_password" />
                  <input class="btn ok" type="submit" value="{$language->save}" />
                  <input class="back btn cancel" type="button" value="{$language->back}" />
                </div>
              </fieldset>
            </form>
          </div>