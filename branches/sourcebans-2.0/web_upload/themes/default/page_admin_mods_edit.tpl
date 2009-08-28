          <div id="admin-page-menu">
            <ul>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
              <li class="active"><a class="back" href="#">{$lang_back}</a></li>
            </ul>
            <br />
            <div align="center">
              <img alt="{$page_title}" src="themes/{$theme_dir}/images/admin/mods.png" title="{$page_title}" />
            </div>
          </div>
          <form action="{$active}" id="admin-page-content" method="post">
            <fieldset>
              <h3>{$lang_edit_mod|ucwords}</h3>
              <p>{$lang_help_desc}</p>
              <label for="name">{help_icon title="$lang_name" desc="Type the name of the mod you are adding."}{$lang_name}</label>
              <input class="submit-fields" {nid id="name"} value="{$mod_name}" />
              <label for="folder">{help_icon title="$lang_folder" desc="Type the name of this mod's folder. For example, Counter-Strike: Source's mod folder is 'cstrike'"}{$lang_folder}</label>
              <input class="submit-fields" {nid id="folder"} value="{$mod_folder}" />
              <label for="icon">{help_icon title="$lang_icon" desc="Click here to upload an icon to associate with this mod."}{$lang_icon}</label>
              <input class="submit-fields" {nid id="icon"} type="file" />
                {if $mod_icon}
                Uploaded: <strong>{$mod_icon}</strong>
                {/if}
              </div>
              <div class="center">
                <input name="id" type="hidden" value="{$smarty.get.id}" />
                <input class="btn ok" type="submit" value="{$lang_save}" />
                <input class="back btn cancel" type="button" value="{$lang_back}" />
              </div>
            </fieldset>
          </form>