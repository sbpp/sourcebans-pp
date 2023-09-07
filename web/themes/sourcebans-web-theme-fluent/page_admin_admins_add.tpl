<div class="layout_box flex:11 admin_tab_content tabcontent" id="Add new admin">
  {if NOT $permission_addadmin}
    <section class="error padding">
      <i class="fas fa-exclamation-circle"></i>
      <div class="error_title">Oops, there's a problem (╯°□°）╯︵ ┻━┻</div>

    <div class="error_content">
      Access Denied!
    </div>

    <div class="error_code">
      Error code: <span class="text:bold">403 Forbidden</span>
    </div>
  </section>
  {else}
  <div class="admin_tab_content_title">
    <h2><i class="fas fa-user-plus"></i> Add new admin</h2>
  </div>

  <div class="padding">
    <div id="msg-green" class="message message:succes margin-bottom:half" style="display: none;">
      <h3>Admin Added</h3>
      <div>The new admin has been successfully added to the system.</div>
      <div class="text:italic">Redirecting back to admins page</div>
    </div>

    <div id="add-group">
      <div class="margin-bottom:half">
        For more information or help regarding a certain subject move your mouse over the
        question mark.
      </div>

      <form class="form">
        <div class="margin-bottom:half">
          <label for="adminname" class="form-label form-label:bottom">
            Admin Login
          </label>
          <input type="text" TABINDEX=1 class="form-input form-full" id="adminname" name="adminname" />
          <div id="name.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
          <label for="steam" class="form-label form-label:bottom">
            Admin Steam ID
          </label>
          <input type="text" TABINDEX=2 value="STEAM_0:" class="form-input form-full" id="steam" name="steam" />
          <div id="steam.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
          <label for="email" class="form-label form-label:bottom">
            Admin Email
          </label>

          <input type="text" TABINDEX=3 class="form-input form-full" id="email" name="email" />
          <div id="email.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
          <label for="password" class="form-label form-label:bottom">
            Admin Password
          </label>

          <input type="password" TABINDEX=4 class="form-input form-full" id="password" name="password" />

          <div class="flex margin-top:half">
            <button id="password_generate" class="button button-light button:line flex:11 margin-right:half">
              <i class="fas fa-sync"></i> Generate random password
            </button>

            <button id="password_show" class="button button-light button:line flex:11">
              <i class="fas fa-eye"></i> Show password
            </button>
          </div>

          <div id="password.msg" class="message message:error margin-top:half" style="display: none;">
          </div>

          {literal}
          <script>
            document.querySelector('#password_generate').addEventListener('click', el => {
              el.preventDefault();
              xajax_generatePassword();
            });

            document.querySelector('#password_show').addEventListener('click', el => {
              el.preventDefault();
              $('password').type = 'text';
            });
          </script>
          {/literal}
        </div>

        <div class="margin-bottom:half">
          <label for="password2" class="form-label form-label:bottom">
            Admin Password (confirm)
          </label>

          <input type="password" TABINDEX=5 class="form-input form-full" id="password2" name="password2" />
          <div id="password2.msg" class="message message:error margin-top:half" style="display: none;">
          </div>
        </div>

        <div class="margin-bottom:half">
          <label for="a_serverpass" class="form-label form-label:bottom">
            Server Password
          </label>

          <input type="checkbox" id="a_useserverpass" class="form-check" name="a_useserverpass" TABINDEX=6
            onclick="$('a_serverpass').disabled = !$(this).checked;" />

          <input type="password" TABINDEX=7 class="form-input form-full" name="a_serverpass" id="a_serverpass"
            disabled="disabled" />
          <div class="form-desc">
            If this box is checked, you will need to specify this password in the game server before you
            can use your admin rights.
            <a href="http://wiki.alliedmods.net/Adding_Admins_%28SourceMod%29#Passwords" rel="noopener" target="_blank"
              class="text:bold">SourceMod Password Info</a>
          </div>

          <div id="a_serverpass.msg" class="message message:error margin-top:half" style="display: none;">
          </div>
        </div>

        <div class="margin-bottom:half">
          <h4 class="form-label">Group Server Access</h4>

          <ul class="list-reset">
            {foreach from=$group_list item="group"}
            <li class="margin-bottom:half">
              <input type="checkbox" id="group[{$group.gid}]" class="form-check" name="group[]" value="g{$group.gid}" />
              <label for="group[{$group.gid}]" class="form-label form-label:left">
                {$group.name} <span class="text:bold text:italic">(Group)</span>
              </label>
            </li>
            {/foreach}
          </ul>

          <h4 class="form-label">Server Access</h4>

          <ul class="list-reset">
            {foreach from=$server_list item="server"}
            <li class="margin-bottom:half">
              <input type="checkbox" name="servers[]" id="servers[{$server.sid}]" class="form-check"
                value="s{$server.sid}" />
              <label for="servers[{$server.sid}]" id="sa{$server.sid}" class="form-label form-label:left">
                Retrieving Hostname... {$server.ip}:{$server.port}
              </label>
            </li>
            {/foreach}
          </ul>
        </div>

        <div class="margin-bottom:half">
          <label for="serverg" class="form-label form-label:bottom">
            Server Admin Group
          </label>

          <select TABINDEX=8 onchange="update_server()" name="serverg" id="serverg" class="form-select form-full">
            <option value="-2">Please Select...</option>
            <option value="-3">No Permissions</option>
            <option value="c">Custom Permissions</option>
            <option value="n">New Admin Group</option>
            <optgroup label="Groups" style="font-weight:bold;">
              {foreach from=$server_admin_group_list item="server_wg"}
              <option value='{$server_wg.id}'>{$server_wg.name}</option>
              {/foreach}
            </optgroup>
          </select>
          <div id="server.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
          <label for="webg" class="form-label form-label:bottom">
            Web Admin Group
          </label>

          <select TABINDEX=9 onchange="update_web()" name="webg" id="webg" class="form-select form-full">
            <option value="-2">Please Select...</option>
            <option value="-3">No Permissions</option>
            <option value="c">Custom Permissions</option>
            <option value="n">New Admin Group</option>
            <optgroup label="Groups" style="font-weight:bold;">
              {foreach from=$server_group_list item="server_g"}
              <option value='{$server_g.gid}'>{$server_g.name}</option>
              {/foreach}
            </optgroup>
          </select>
          <div id="web.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <table width="90%" border="0" style="border-collapse:collapse;" id="group.details" cellpadding="3">
          <tr>
            <td colspan="2" id="serverperm" valign="top" style="height:5px;overflow:hidden;"></td>
          </tr>
          <tr>
            <td colspan="2" id="webperm" valign="top" style="height:5px;overflow:hidden;"></td>
          </tr>
        </table>

        <div class="flex flex-jc:space-between flex-ai:center margin-top">
          {sb_button text="Add Admin" onclick="ProcessAddAdmin();" class="button button-success" id="aadmin" submit=false}
          {sb_button text="Back" onclick="history.go(-1)" class="button button-light" id="aback"}
        </div>
      </form>
      {$server_script}
    </div>
  </div>
  {/if}
</div>