<div class="margin-bottom">
  <div class="table padding">
    <div class="table_box">
      <table>
        <tbody>
          <tr class="collapse">
            <td class="text:center">
              <span class="text:bold">Advanced Search</span> (Click)
            </td>
          </tr>
          <tr class="table_hide">
            <td>
              <div class="collapse_content flex flex-jc:center">
                <div class="padding">
                  <div class="margin-bottom:half">
                    <input id="name_" name="search_type" class="form-radio" type="radio" value="name" />

                    <label for="nick" class="form-label form-label:bottom">
                      Nickname
                    </label>

                    <input class="form-input form-full" type="text" id="nick" value=""
                      onmouseup="$('name_').checked = true" />
                  </div>

                  <div class="margin-bottom:half">
                    <input id="steam_" type="radio" name="search_type" class="form-radio" value="radiobutton" />

                    <label for="steam_match" class="form-label form-label:bottom form-label:right">
                      Steam ID
                    </label>

                    <div class="flex">
                      <input class="form-input form-full margin-right" type="text" id="steamid" value=""
                        onmouseup="$('steam_').checked = true" />

                      <select class="form-select form-full" id="steam_match" onmouseup="$('steam_').checked = true">
                        <option value="0" selected>Exact Match</option>
                        <option value="1">Partial Match</option>
                      </select>
                    </div>
                  </div>

                  {if $can_editadmin}
                    <div class="margin-bottom:half">
                      <input id="admemail_" name="search_type" class="form-radio" type="radio" value="radiobutton" />


                      <label for="admemail" class="form-label form-label:bottom">
                        Email
                      </label>

                      <input class="form-input form-full" type="text" id="admemail" value=""
                        onmouseup="$('admemail_').checked = true" />
                    </div>
                  {/if}

                  <div class="margin-bottom:half">
                    <input id="webgroup_" type="radio" name="search_type" class="form-radio" value="radiobutton" />

                    <label for="webgroup" class="form-label form-label:bottom form-label:right">
                      Web Group
                    </label>

                    <select class="form-select form-full" id="webgroup" onmouseup="$('webgroup_').checked = true">
                      {foreach from=$webgroup_list item="webgrp"}
                        <option label="{$webgrp.name}" value="{$webgrp.gid}">{$webgrp.name}</option>
                      {/foreach}
                    </select>
                  </div>

                  <div class="margin-bottom:half">
                    <input id="srvadmgroup_" type="radio" name="search_type" class="form-radio" value="radiobutton" />

                    <label for="srvadmgroup" class="form-label form-label:bottom form-label:right">
                      Server Admin Group
                    </label>

                    <select class="form-select form-full" id="srvadmgroup" onmouseup="$('srvadmgroup_').checked = true">
                      {foreach from=$srvadmgroup_list item="srvadmgrp"}
                        <option label="{$srvadmgrp.name}" value="{$srvadmgrp.name}">{$srvadmgrp.name}
                        </option>
                      {/foreach}
                    </select>
                  </div>

                  <div class="margin-bottom:half">
                    <input id="srvgroup_" type="radio" name="search_type" class="form-radio" value="radiobutton" />

                    <label for="srvgroup" class="form-label form-label:bottom form-label:right">
                      Server Group
                    </label>

                    <select class="form-select form-full" id="srvgroup" onmouseup="$('srvgroup_').checked = true">
                      {foreach from=$srvgroup_list item="srvgrp"}
                        <option label="{$srvgrp.name}" value="{$srvgrp.gid}">{$srvgrp.name}</option>
                      {/foreach}
                    </select>
                  </div>

                  <div class="margin-bottom:half">
                    <input id="admwebflags_" name="search_type" type="radio" class="form-radio" value="radiobutton" />

                    <label for="admwebflag" class="form-label form-label:bottom form-label:right">
                      Web Permissions
                    </label>

                    <select class="form-select form-full" id="admwebflag" name="admwebflag"
                      onblur="getMultiple(this, 1);" size="5" multiple onmouseup="$('admwebflags_').checked = true">
                      {foreach from=$admwebflag_list item="admwebflag"}
                        <option label="{$admwebflag.name}" value="{$admwebflag.flag}">{$admwebflag.name}
                        </option>
                      {/foreach}
                    </select>
                  </div>

                  <div class="margin-bottom:half">
                    <input id="admsrvflags_" name="search_type" type="radio" class="form-radio" value="radiobutton">

                    <label for="admwebflag" class="form-label form-label:bottom form-label:right">
                      Server Permissions
                    </label>

                    <select class="form-select form-full" id="admwebflag" name="admsrvflag"
                      onblur="getMultiple(this, 2);" size="5" multiple onmouseup="$('admsrvflags_').checked = true">
                      {foreach from=$admsrvflag_list item="admsrvflag"}
                        <option label="{$admsrvflag.name}" value="{$admsrvflag.flag}">{$admsrvflag.name}
                        </option>
                      {/foreach}
                    </select>
                  </div>

                  <div class="margin-bottom:half">
                    <input id="admin_on_" name="search_type" type="radio" class="form-radio" value="radiobutton">


                    <label for="server" class="form-label form-label:bottom form-label:right">
                      Server
                    </label>


                    <select class="form-select form-full" id="server" onmouseup="$('admin_on_').checked = true">
                      {foreach from=$server_list item="server"}
                      <option value="{$server.sid}" id="ss{$server.sid}">Retrieving Hostname...
                        ({$server.ip}:{$server.port})</option>
                      {/foreach}
                    </select>
                  </div>

                  <div class="flex">
                    {sb_button text="Search" onclick="search_admins();" class="ok" id="button button-primary flex:11" submit=false}
                  </div>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

{$server_script}
<script>
  InitAccordion('tr.sea_open', 'div.panel', 'mainwrapper');
</script>