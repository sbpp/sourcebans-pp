          <div id="admin-page-menu">
            <ul>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
              <li class="active"><a class="back" href="#">{$language->back}</a></li>
            </ul>
            <br />
            <div class="center">
              <img alt="{$action_title}" src="{$uri->base}/themes/{$theme}/images/admin/admins.png" title="{$action_title}" />
            </div>
          </div>
          <form action="" id="admin-page-content" method="post">
            <fieldset>
              <h3>{$language->edit_details|ucwords}</h3>
              <label for="name">{help_icon title="`$language->username`" desc="This is the username the admin will use to login-to their admin panel. Also this will identify the admin on any bans they make."}{$language->username}</label>
              <input class="submit-fields" {nid id="name"} value="{$admin->name}" />
              <label for="type">{help_icon title="`$language->type`" desc="This is the admin's authentication type."}{$language->type}</label>
              <select class="submit-fields" {nid id="auth"} >
                <option{if $admin->type == $smarty.const.STEAM_AUTH_TYPE} selected="selected"{/if} value="{$smarty.const.STEAM_AUTH_TYPE}">Steam ID</option>
                <option{if $admin->type == $smarty.const.IP_AUTH_TYPE} selected="selected"{/if} value="{$smarty.const.IP_AUTH_TYPE}">{$language->ip_address}</option>
                <option{if $admin->type == $smarty.const.NAME_AUTH_TYPE} selected="selected"{/if} value="{$smarty.const.NAME_AUTH_TYPE}">{$language->name}</option>
              </select>
              <label for="steam">{help_icon title="`$language->identity`" desc="This is the admin's Steam ID. This must be set so that admins can use their admin rights ingame."}{$language->identity}</label>
              <input class="submit-fields" {nid id="identity"} value="{$admin->identity}" />
              <label for="email">{help_icon title="`$language->email_address`" desc="Set the admin's e-mail address. This will be used for sending out any automated messages from the system, and for use when you forget your password."}{$language->email_address}</label>
              <input class="submit-fields" {nid id="email"} value="{$admin->email}" />
              {if $user->permission_change_pass}
              <label for="password">{help_icon title="`$language->password`" desc="The password the admin will need to access the admin panel."}{$language->password}</label>
              <input class="submit-fields" {nid id="password"} type="password" />
              <label for="password_confirm">{help_icon title="`$language->confirm_password`" desc="Type your password again to confirm."}{$language->confirm_password}</label>
              <input class="submit-fields" {nid id="password_confirm"} type="password" />
              {/if}
              <div class="center">
                <input name="id" type="hidden" value="{$uri->id}" />
                <input class="btn ok" type="submit" value="{$language->save}" />
                <input class="back btn cancel" type="button" value="{$language->back}" />
              </div>
            </fieldset>
          </form>