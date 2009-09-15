          <div id="admin-page-menu">
            <ul>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
              <li class="active"><a class="back" href="#">{$lang_back}</a></li>
            </ul>
            <br />
            <div align="center">
              <img alt="{$page_title}" src="themes/{$theme_dir}/images/admin/bans.png" title="{$page_title}" />
            </div>
          </div>
          <form action="{$active}" id="admin-page-content" method="post">
            <fieldset>
              <h3>{$lang_edit_ban|ucwords}</h3>
              <p>{$lang_help_desc}</p>
              <label for="name">{help_icon title="$lang_name" desc="This is the name of the player that was banned."}{$lang_name}</label>
              <input class="submit-fields" {nid id="name"} value="{$ban_name}" />
              <label for="type">{help_icon title="Ban Type" desc="Choose whether to ban by Steam ID or IP address."}Ban Type</label>
              <select class="submit-fields" {nid id="type"}>
                <option value="{$smarty.const.STEAM_BAN_TYPE}">Steam ID</option>
                <option value="{$smarty.const.IP_BAN_TYPE}">{$lang_ip_address}</option>
              </select>
              <label for="steam">{help_icon title="Steam ID" desc="This is the Steam ID of the player that is banned"}Steam ID</label>
              <input class="submit-fields" {nid id="steam"} value="{$ban_steam}" />
              <label for="ip">{help_icon title="$lang_ip_address" desc="This is the IP of the player that is banned"}{$lang_ip_address}</label>
              <input class="submit-fields" {nid id="ip"} value="{$ban_ip}" />
              <label for="reason">{help_icon title="$lang_reason" desc="The reason that this player was banned."}{$lang_reason}</label>
              <select class="submit-fields" {nid id="reason"}>
                <option value=""> -- Select Reason -- </option>
                <optgroup label="Hacking">
                  <option value="Aimbot">Aimbot</option>
                  <option value="Antirecoil">Antirecoil</option>
                  <option value="Wallhack">Wallhack</option>
                  <option value="Spinhack">Spinhack</option>
                  <option value="Multi-Hack">Multi-Hack</option>
                  <option value="No Smoke">No Smoke</option>
                  <option value="No Flash">No Flash</option>
                </optgroup>
                <optgroup label="Behavior">
                  <option value="Team Killing">Team Killing</option>
                  <option value="Team Flashing">Team Flashing</option>
                  <option value="Spamming Mic/Chat">Spamming Mic/Chat</option>
                  <option value="Inappropriate Spray">Inappropriate Spray</option>
                  <option value="Inappropriate Language">Inappropriate Language</option>
                  <option value="Inappropriate Name">Inappropriate Name</option>
                  <option value="Ignoring Admins">Ignoring Admins</option>
                  <option value="Team Stacking">Team Stacking</option>
                </optgroup>
                <option value="other">Other Reason</option>
              </select>
              <textarea class="submit-fields" cols="30" {nid id="reason_other"} rows="5">{$ban_reason}</textarea>
              <label for="length">{help_icon title="$lang_length" desc="Select how long you want to ban this person for."}{$lang_length}</label>
              <select class="inputbox" {nid id="length"}>
                <option value="0">{$lang_permanent}</option>
                <optgroup label="{$lang_minutes}">
                  <option{if $ban_length == 1} selected="selected"{/if} value="1">1 {$lang_minute}</option>
                  <option{if $ban_length == 5} selected="selected"{/if} value="5">5 {$lang_minutes}</option>
                  <option{if $ban_length == 10} selected="selected"{/if} value="10">10 {$lang_minutes}</option>
                  <option{if $ban_length == 15} selected="selected"{/if} value="15">15 {$lang_minutes}</option>
                  <option{if $ban_length == 30} selected="selected"{/if} value="30">30 {$lang_minutes}</option>
                  <option{if $ban_length == 45} selected="selected"{/if} value="45">45 {$lang_minutes}</option>
                </optgroup>
                <optgroup label="{$lang_hours}">
                  <option{if $ban_length == 60} selected="selected"{/if} value="60">1 {$lang_hour}</option>
                  <option{if $ban_length == 120} selected="selected"{/if} value="120">2 {$lang_hours}</option>
                  <option{if $ban_length == 180} selected="selected"{/if} value="180">3 {$lang_hours}</option>
                  <option{if $ban_length == 240} selected="selected"{/if} value="240">4 {$lang_hours}</option>
                  <option{if $ban_length == 480} selected="selected"{/if} value="480">8 {$lang_hours}</option>
                  <option{if $ban_length == 720} selected="selected"{/if} value="720">12 {$lang_hours}</option>
                </optgroup>
                <optgroup label="{$lang_days}">
                  <option{if $ban_length == 1440} selected="selected"{/if} value="1440">1 {$lang_day}</option>
                  <option{if $ban_length == 2880} selected="selected"{/if} value="2880">2 {$lang_days}</option>
                  <option{if $ban_length == 4320} selected="selected"{/if} value="4320">3 {$lang_days}</option>
                  <option{if $ban_length == 5760} selected="selected"{/if} value="5760">4 {$lang_days}</option>
                  <option{if $ban_length == 7200} selected="selected"{/if} value="7200">5 {$lang_days}</option>
                  <option{if $ban_length == 8640} selected="selected"{/if} value="8640">6 {$lang_days}</option>
                </optgroup>
                <optgroup label="{$lang_weeks}">
                  <option{if $ban_length == 10080} selected="selected"{/if} value="10080">1 {$lang_week}</option>
                  <option{if $ban_length == 20160} selected="selected"{/if} value="20160">2 {$lang_weeks}</option>
                  <option{if $ban_length == 30240} selected="selected"{/if} value="30240">3 {$lang_weeks}</option>
                </optgroup>
                <optgroup label="{$lang_months}">
                  <option{if $ban_length == 43200} selected="selected"{/if} value="43200">1 {$lang_month}</option>
                  <option{if $ban_length == 86400} selected="selected"{/if} value="86400">2 {$lang_months}</option>
                  <option{if $ban_length == 129600} selected="selected"{/if} value="129600">3 {$lang_months}</option>
                  <option{if $ban_length == 259200} selected="selected"{/if} value="259200">6 {$lang_months}</option>
                  <option{if $ban_length == 518400} selected="selected"{/if} value="518400">12 {$lang_months}</option>
                </optgroup>
              </select>
              <label for="demo">{help_icon title="$lang_demo" desc="Click here to upload a demo with this ban submission."}{$lang_demo} (<a href="#" id="add_demo">{$lang_add}</a>)</label>
              {foreach from=$ban_demos item=demo key=demo_id}
              <div id="demo_{$demo_id}">
                <input class="submit-fields" readonly="readonly" value="{$demo}" /> (<a class="delete_demo" href="#" rel="{$demo_id}">{$lang_delete}</a>)
              </div>
              {/foreach}
              <input class="submit-fields" name="demo[]" id="demo" type="file" />
              <div class="center">
                <input name="id" type="hidden" value="{$smarty.get.id}" />
                <input class="btn ok" type="submit" value="{$lang_save}" />
                <input class="back btn cancel" type="button" value="{$lang_back}" />
              </div>
            </fieldset>
          </form>