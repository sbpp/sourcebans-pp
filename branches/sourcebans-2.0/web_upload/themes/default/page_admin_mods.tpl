          <div id="admin-page-menu">
            <ul>
              {if $permission_list_mods}
              <li id="tab-list"><a href="#list">{$lang_list_mods}</a></li>
              {/if}
              {if $permission_add_mods}
              <li id="tab-add"><a href="#add">{$lang_add_mod}</a></li>
              {/if}
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
            </ul>
            <br />
            <div align="center">
              <img alt="{$page_title}" src="themes/{$theme_dir}/images/admin/mods.png" title="{$page_title}" />
            </div>
          </div>
          <div id="admin-page-content">
            {if $permission_list_mods}
            <div id="pane-list">
              <h3>{$lang_mods} ({$mod_count})</h3>
              <table width="100%" cellpadding="1">
                <tr>
                  <th>{$lang_name}</td>
                  <th width="150">{$lang_folder}</td>
                  <th width="35">{$lang_icon}</td>
                  <th width="150">{$lang_action}</td>
                </tr>
                {foreach from=$mods item=mod key=mod_id}
                <tr>
                  <td style="border-bottom: solid 1px #ccc">{$mod.name|escape}</td>
                  <td style="border-bottom: solid 1px #ccc">{$mod.folder}</td>
                  <td style="border-bottom: solid 1px #ccc"><img alt="{$mod.name|escape}" class="icon" src="images/games/{$mod.icon}" title="{$mod.name|escape}" /></td>
                  <td style="border-bottom: solid 1px #ccc">
                    {if $permission_edit_mods}
                    <a href="admin_mods_edit.php?id={$mod_id}">{$lang_edit}</a>
                    {/if}
                    {if $permission_edit_mods && $permission_delete_mods}
                    -
                    {/if}
                    {if $permission_delete_mods}
                    <a href="#" onclick="DeleteMod({$mod_id}, '{$mod.name|escape}');">{$lang_delete}</a>
                    {/if}
                  </td>
                </tr>
                {/foreach}
              </table>
            </div>
            {/if}
            {if $permission_add_mods}
            <form action="{$active}" enctype="multipart/form-data" id="pane-add" method="post">
              <fieldset>
                <h3>{$lang_add_mod|ucwords}</h3>
                <p>{$lang_help_desc}</p>
                <label for="name">{help_icon title="$lang_name" desc="Type the name of the mod you are adding."}{$lang_name}</label>
                <input class="submit-fields" {nid id="name"} />
                <div class="badentry" id="name.msg"></div>
                <label for="folder">{help_icon title="$lang_folder" desc="Type the folder of this mod. For example, Counter-Strike: Source's folder is 'cstrike'"}{$lang_folder}</label>
                <input class="submit-fields" {nid id="folder"} />
                <div class="badentry" id="folder.msg"></div>
                <label for="icon">{help_icon title="$lang_icon" desc="Click here to upload an icon to associate with this mod."}{$lang_icon}</label>
                <input class="submit-fields" {nid id="icon"} type="file" />
                <div class="badentry" id="icon.msg"></div>
                <div class="center">
                  <input class="btn ok" type="submit" value="{$lang_save}" />
                  <input class="back btn cancel" type="button" value="{$lang_back}" />
                </div>
              </fieldset>
            </form>
            {/if}
          </div>