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
              <h3>{$language->edit_groups|ucwords}</h3>
              <p>{$language->help_desc}</p>
              <p>Choose the new groups that you want <strong>{$admin->name}</strong> to appear in.</p>
              <label>{help_icon title="`$language->server_groups`" desc="Choose the groups you want this admin to appear in for server admin permissions"}{$language->server_groups}</label>
              <table width="90%" cellspacing="0" cellpadding="4" align="center">
                {foreach from=$server_groups item=group}
                <tr>
                  <td class="tablerow1" colspan="2">{$group->name}</td>
                  <td align="center" class="tablerow1"><input{if in_array($group, $admin->srv_groups)} checked="checked"{/if} name="srv_groups[]" type="checkbox" value="{$group->id}" /></td>
                </tr>
                {/foreach}
              </table>
              <label for="web_group">{help_icon title="`$language->web_group`" desc="Choose the group you want this admin to appear in for web permissions"}{$language->web_group}</label>
              <select class="submit-fields" {nid id="web_group"}>
                <option value="-1">{$language->none}</option>
                <optgroup label="{$language->groups}">
                  {foreach from=$web_groups item=group}
                  <option{if $admin->group_id == $group->id} selected="selected"{/if} value="{$group->id}">{$group->name|escape}</option>
                  {/foreach}
                </optgroup>
              </select>
              <div class="center">
                <input name="id" type="hidden" value="{$uri->id}" />
                <input class="btn ok" type="submit" value="{$language->save}" />
                <input class="back btn cancel" type="button" value="{$language->back}" />
              </div>
            </fieldset>
          </form>