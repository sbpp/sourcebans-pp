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
            <h3>Database Config</h3>
            <p>The following config file is the file your game server needs to be able to communicate with the database. You need to copy its contents and place it in this file on your game server: <strong>/[mod]/addons/sourcemod/configs/databases.cfg</strong></p>
            <table cellpadding="3" width="90%">
              <tr>
                <td>
                  <textarea cols="60" rows="20" readonly="readonly">{literal}"Databases"
{
	"driver_default"		"mysql"
	
	"sourcebans"
	{{/literal}
		"driver"		"mysql"
		"host"			"{if $smarty.const.DB_HOST == 'localhost'}{$smarty.server.SERVER_ADDR}{else}{$smarty.const.DB_HOST}{/if}"
		"database"		"{$smarty.const.DB_NAME}"
		"user"			"{$smarty.const.DB_USER}"
		"pass"			"{$smarty.const.DB_PASS}"
		//"timeout"		"0"
		"port"			"{$smarty.const.DB_PORT}"
	{literal}}
	
	"storage-local"
	{
		"driver"		"sqlite"
		"database"		"sourcemod-local"
	}
}{/literal}</textarea>
                </td>
              </tr>
              <tr>
                <td align="center"><input class="back btn cancel" type="button" value="{$language->back}" /></td>
              </tr>
            </table>
          </div>