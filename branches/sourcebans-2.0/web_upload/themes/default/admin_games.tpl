          <div id="admin-page-menu">
            <ul>
              {if $user->permission_list_games}
              <li id="tab-list"><a href="#list">{$language->list_games}</a></li>
              {/if}
              {if $user->permission_add_games}
              <li id="tab-add"><a href="#add">{$language->add_game}</a></li>
              {/if}
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
            </ul>
            <br />
            <div class="center">
              <img alt="{$action_title}" src="{$uri->base}/themes/{$theme}/images/admin/games.png" title="{$action_title}" />
            </div>
          </div>
          <div id="admin-page-content">
            {if $user->permission_list_games}
            <div id="pane-list">
              <h3>{$language->games} (<span id="game_count">{$games|@count}</span>)</h3>
              <table width="100%" cellpadding="1">
                <tr>
                  <th>{$language->name}</th>
                  <th width="150">{$language->folder}</th>
                  <th width="35">{$language->icon}</th>
                  <th width="150">{$language->action}</th>
                </tr>
                {foreach from=$games item=game}
                <tr>
                  <td style="border-bottom: solid 1px #ccc">{$game->name|escape}</td>
                  <td style="border-bottom: solid 1px #ccc">{$game->folder}</td>
                  <td style="border-bottom: solid 1px #ccc"><img alt="{$game->name|escape}" class="icon" src="{$uri->base}/images/games/{$game->icon}" title="{$game->name|escape}" /></td>
                  <td style="border-bottom: solid 1px #ccc">
                    {if $user->permission_edit_games}
                    <a href="{build_uri controller=games action=edit id=$game->id}">{$language->edit}</a>
                    {/if}
                    {if $user->permission_edit_games && $user->permission_delete_games}
                    -
                    {/if}
                    {if $user->permission_delete_games}
                    <a href="#" onclick="DeleteGame({$game->id}, '{$game->name|escape}');">{$language->delete}</a>
                    {/if}
                  </td>
                </tr>
                {/foreach}
              </table>
            </div>
            {/if}
            {if $user->permission_add_games}
            <form action="" enctype="multipart/form-data" id="pane-add" method="post">
              <fieldset>
                <h3>{$language->add_game|ucwords}</h3>
                <p>{$language->help_desc}</p>
                <label for="name">{help_icon title="`$language->name`" desc="Type the name of the game you are adding."}{$language->name}</label>
                <input class="submit-fields" {nid id="name"} />
                <label for="folder">{help_icon title="`$language->folder`" desc="Type the folder of this game. For example, Counter-Strike: Source's folder is 'cstrike'"}{$language->folder}</label>
                <input class="submit-fields" {nid id="folder"} />
                <label for="icon">{help_icon title="`$language->icon`" desc="Click here to upload an icon to associate with this game."}{$language->icon}</label>
                <input class="submit-fields" {nid id="icon"} type="file" />
                <div class="center">
                  <input class="btn ok" type="submit" value="{$language->save}" />
                  <input class="back btn cancel" type="button" value="{$language->back}" />
                </div>
              </fieldset>
            </form>
            {/if}
          </div>