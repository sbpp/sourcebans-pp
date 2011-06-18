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
          <div id="admin-page-content">
            <h3>Admins on this server ({$admins|@count})</h3>
            <table cellpadding="1" cellspacing="1" id="server_admins_{$uri->id}" width="100%">
              <tr>
                <th width="50%">{$language->name}</th>
                <th width="50%">Steam ID</th>
              </tr>
              {foreach from=$admins item=admin key=admin_id}
              <tr id="admin_{$admin_id}">
                <td style="border-bottom: solid 1px #ccc">{$admin.name}</td>
                <td style="border-bottom: solid 1px #ccc">{$admin.identity}</td>
              </tr>
              {/foreach}
            </table>
          </div>