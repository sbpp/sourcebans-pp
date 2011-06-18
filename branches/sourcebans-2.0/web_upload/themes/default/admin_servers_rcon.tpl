          <div id="admin-page-menu">
            <ul>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
              <li class="active"><a class="back" href="#">{$language->back}</a></li>
            </ul>
            <br />
            <div class="center">
              <img alt="{$action_title}" src="{$uri->base}/themes/{$theme}/images/admin/servers.png" title="{$action_title}" />
            </div>
          </div>
          <form action="" id="admin-page-content" method="post">
            <fieldset>
              <h3>RCON Console</h3>
              <div id="rcon_out">
                <pre>
















<div id="rcon_con">***********************************************************
**                                                       **
* SourceBans RCON console                                 *
* Type your comand in the box below and hit enter         *
* Type 'clr' to clear the console                         *
**                                                       **
***********************************************************</div></pre>
              </div>
              <br />
              Command:
              <input name="id" type="hidden" value="{$uri->id}" />
              <input {nid id="rcon_cmd"} />
              <input {nid id="rcon_btn"} type="button" value="Send" />
            </fieldset>
          </form>