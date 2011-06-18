          <div id="admin-page-menu">
            <ul>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
              <li class="active"><a class="back" href="#">{$lang_back}</a></li>
            </ul>
            <br />
            <div align="center">
              <img alt="{$page_title}" src="themes/{$theme_dir}/images/admin/admins.png" title="{$page_title}" />
            </div>
          </div>
          <form action="{$active}" id="admin-page-content" method="post">
            <fieldset>
              <h3>{$lang_edit_details|ucwords}</h3>
              <label for="name">{help_icon title="$lang_username" desc="This is the username the admin will use to login-to their admin panel. Also this will identify the admin on any bans they make."}{$lang_username}</label>
              <input class="submit-fields" {nid id="name"} value="{$admin_name}" />
              <label for="type">{help_icon title="$lang_type" desc="This is the admin's authentication type."}{$lang_type}</label>
              <select class="submit-fields" {nid id="auth"} >
                <option{if $admin_type == $smarty.const.STEAM_AUTH_TYPE} selected="selected"{/if} value="{$smarty.const.STEAM_AUTH_TYPE}">Steam ID</option>
                <option{if $admin_type == $smarty.const.IP_AUTH_TYPE} selected="selected"{/if} value="{$smarty.const.IP_AUTH_TYPE}">{$lang_ip_address}</option>
                <option{if $admin_type == $smarty.const.NAME_AUTH_TYPE} selected="selected"{/if} value="{$smarty.const.NAME_AUTH_TYPE}">{$lang_name}</option>
              </select>
              <label for="steam">{help_icon title="$lang_identity" desc="This is the admin's Steam ID. This must be set so that admins can use their admin rights ingame."}{$lang_identity}</label>
              <input class="submit-fields" {nid id="identity"} value="{$admin_identity}" />
              <label for="email">{help_icon title="$lang_email_address" desc="Set the admin's e-mail address. This will be used for sending out any automated messages from the system, and for use when you forget your password."}{$lang_email_address}</label>
              <input class="submit-fields" {nid id="email"} value="{$admin_email}" />
              {if $permission_change_pass}
              <label for="password">{help_icon title="$lang_password" desc="The password the admin will need to access the admin panel."}{$lang_password}</label>
              <input class="submit-fields" {nid id="password"} type="password" />
              <label for="password_confirm">{help_icon title="$lang_confirm_password" desc="Type your password again to confirm."}{$lang_confirm_password}</label>
              <input class="submit-fields" {nid id="password_confirm"} type="password" />
              {/if}
              <div class="center">
                <input name="id" type="hidden" value="{$smarty.get.id}" />
                <input class="btn ok" type="submit" value="{$lang_save}" />
                <input class="back btn cancel" type="button" value="{$lang_back}" />
              </div>
            </fieldset>
          </form>