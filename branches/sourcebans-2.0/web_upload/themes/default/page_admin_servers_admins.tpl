          <div id="admin-page-menu">
            <ul>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
              <li class="active"><a class="back" href="#">{$lang_back}</a></li>
            </ul>
            <br />
            <div align="center">
              <img alt="{$page_title}" src="themes/{$theme_dir}/images/admin/servers.png" title="{$page_title}" />
            </div>
          </div>
          <div id="admin-page-content">
            <h3>Admins on this server ({$admin_count})</h3>
            <table cellpadding="1" cellspacing="1" id="server_admins_{$smarty.get.id}" width="100%">
              <tr>
                <th width="50%">{$lang_name}</th>
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