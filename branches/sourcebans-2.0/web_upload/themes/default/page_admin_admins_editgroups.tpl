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
              <h3>{$lang_edit_groups|ucwords}</h3>
              <p>{$lang_help_desc}</p>
              <p>Choose the new groups that you want <strong>{$admin_name}</strong> to appear in.</p>
              <label>{help_icon title="$lang_server_groups" desc="Choose the groups you want this admin to appear in for server admin permissions"}{$lang_server_groups}</label>
              <table width="90%" cellspacing="0" cellpadding="4" align="center">
                {foreach from=$server_groups item=group key=group_id}
                <tr>
                  <td class="tablerow1" colspan="2">{$group.name}</td>
                  <td align="center" class="tablerow1"><input name="srv_groups[]" type="checkbox" value="{$group_id}" /></td>
                </tr>
                {/foreach}
              </table>
              <div class="badentry" id="srv_groups.msg"></div>
              <label for="web_group">{help_icon title="$lang_web_group" desc="Choose the group you want this admin to appear in for web permissions"}{$lang_web_group}</label>
              <select class="submit-fields" {nid id="web_group"}>
                <option value="-1">{$lang_none}</option>
                <optgroup label="{$lang_groups}">
                  {foreach from=$web_groups item=group key=group_id}
                  <option{if $group_id == $admin_web_group} selected="selected"{/if} value="{$group_id}">{$group.name|escape}</option>
                  {/foreach}
                </optgroup>
              </select>
              <div class="badentry" id="web_group.msg"></div>
              <div class="center">
                <input class="btn ok" type="submit" value="{$lang_save}" />
                <input class="back btn cancel" type="button" value="{$lang_back}" />
              </div>
            </fieldset>
          </form>