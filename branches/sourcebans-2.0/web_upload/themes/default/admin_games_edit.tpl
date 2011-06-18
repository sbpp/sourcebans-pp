          <div id="admin-page-menu">
            <ul>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
              <li class="active"><a class="back" href="#">{$language->back}</a></li>
            </ul>
            <br />
            <div class="center">
              <img alt="{$action_title}" src="{$uri->base}/themes/{$theme}/images/admin/games.png" title="{$action_title}" />
            </div>
          </div>
          <form action="" enctype="multipart/form-data" id="admin-page-content" method="post">
            <fieldset>
              <h3>{$language->edit_game|ucwords}</h3>
              <p>{$language->help_desc}</p>
              <label for="name">{help_icon title="`$language->name`" desc="Type the name of the game you are adding."}{$language->name}</label>
              <input class="submit-fields" {nid id="name"} value="{$game->name}" />
              <label for="folder">{help_icon title="`$language->folder`" desc="Type the name of this game's folder. For example, Counter-Strike: Source's game folder is 'cstrike'"}{$language->folder}</label>
              <input class="submit-fields" {nid id="folder"} value="{$game->folder}" />
              <label for="icon">{help_icon title="`$language->icon`" desc="Click here to upload an icon to associate with this game."}{$language->icon}</label>
              <input class="submit-fields" {nid id="icon"} type="file" />
              <div class="center">
                <input name="id" type="hidden" value="{$uri->id}" />
                <input class="btn ok" type="submit" value="{$language->save}" />
                <input class="back btn cancel" type="button" value="{$language->back}" />
              </div>
            </fieldset>
          </form>