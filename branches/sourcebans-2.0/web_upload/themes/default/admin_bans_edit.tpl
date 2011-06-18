          <div id="admin-page-menu">
            <ul>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
              <li class="active"><a class="back" href="#">{$language->back}</a></li>
            </ul>
            <br />
            <div class="center">
              <img alt="{$action_title}" src="{$uri->base}/themes/{$theme}/images/admin/bans.png" title="{$action_title}" />
            </div>
          </div>
          <form action="" enctype="multipart/form-data" id="admin-page-content" method="post">
            <fieldset>
              <h3>{$language->edit_ban|ucwords}</h3>
              <p>{$language->help_desc}</p>
              <label for="name">{help_icon title="`$language->name`" desc="This is the name of the player that was banned."}{$language->name}</label>
              <input class="submit-fields" {nid id="name"} value="{$ban->name}" />
              <label for="type">{help_icon title="Ban Type" desc="Choose whether to ban by Steam ID or IP address."}Ban Type</label>
              <select class="submit-fields" {nid id="type"}>
                <option value="{$smarty.const.STEAM_BAN_TYPE}">Steam ID</option>
                <option value="{$smarty.const.IP_BAN_TYPE}">{$language->ip_address}</option>
              </select>
              <label for="steam">{help_icon title="Steam ID" desc="This is the Steam ID of the player that is banned"}Steam ID</label>
              <input class="submit-fields" {nid id="steam"} value="{$ban->steam}" />
              <label for="ip">{help_icon title="`$language->ip_address`" desc="This is the IP of the player that is banned"}{$language->ip_address}</label>
              <input class="submit-fields" {nid id="ip"} value="{$ban->ip}" />
              <label for="reason">{help_icon title="`$language->reason`" desc="The reason that this player was banned."}{$language->reason}</label>
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
              <textarea class="submit-fields" cols="30" {nid id="reason_other"} rows="5">{$ban->reason}</textarea>
              <label for="length">{help_icon title="`$language->length`" desc="Select how long you want to ban this person for."}{$language->length}</label>
              <select class="inputbox" {nid id="length"}>
                <option value="0">{$language->permanent}</option>
                <optgroup label="{$language->minutes}">
                  <option{if $ban->length == 1} selected="selected"{/if} value="1">1 {$language->minute}</option>
                  <option{if $ban->length == 5} selected="selected"{/if} value="5">5 {$language->minutes}</option>
                  <option{if $ban->length == 10} selected="selected"{/if} value="10">10 {$language->minutes}</option>
                  <option{if $ban->length == 15} selected="selected"{/if} value="15">15 {$language->minutes}</option>
                  <option{if $ban->length == 30} selected="selected"{/if} value="30">30 {$language->minutes}</option>
                  <option{if $ban->length == 45} selected="selected"{/if} value="45">45 {$language->minutes}</option>
                </optgroup>
                <optgroup label="{$language->hours}">
                  <option{if $ban->length == 60} selected="selected"{/if} value="60">1 {$language->hour}</option>
                  <option{if $ban->length == 120} selected="selected"{/if} value="120">2 {$language->hours}</option>
                  <option{if $ban->length == 180} selected="selected"{/if} value="180">3 {$language->hours}</option>
                  <option{if $ban->length == 240} selected="selected"{/if} value="240">4 {$language->hours}</option>
                  <option{if $ban->length == 480} selected="selected"{/if} value="480">8 {$language->hours}</option>
                  <option{if $ban->length == 720} selected="selected"{/if} value="720">12 {$language->hours}</option>
                </optgroup>
                <optgroup label="{$language->days}">
                  <option{if $ban->length == 1440} selected="selected"{/if} value="1440">1 {$language->day}</option>
                  <option{if $ban->length == 2880} selected="selected"{/if} value="2880">2 {$language->days}</option>
                  <option{if $ban->length == 4320} selected="selected"{/if} value="4320">3 {$language->days}</option>
                  <option{if $ban->length == 5760} selected="selected"{/if} value="5760">4 {$language->days}</option>
                  <option{if $ban->length == 7200} selected="selected"{/if} value="7200">5 {$language->days}</option>
                  <option{if $ban->length == 8640} selected="selected"{/if} value="8640">6 {$language->days}</option>
                </optgroup>
                <optgroup label="{$language->weeks}">
                  <option{if $ban->length == 10080} selected="selected"{/if} value="10080">1 {$language->week}</option>
                  <option{if $ban->length == 20160} selected="selected"{/if} value="20160">2 {$language->weeks}</option>
                  <option{if $ban->length == 30240} selected="selected"{/if} value="30240">3 {$language->weeks}</option>
                </optgroup>
                <optgroup label="{$language->months}">
                  <option{if $ban->length == 43200} selected="selected"{/if} value="43200">1 {$language->month}</option>
                  <option{if $ban->length == 86400} selected="selected"{/if} value="86400">2 {$language->months}</option>
                  <option{if $ban->length == 129600} selected="selected"{/if} value="129600">3 {$language->months}</option>
                  <option{if $ban->length == 259200} selected="selected"{/if} value="259200">6 {$language->months}</option>
                  <option{if $ban->length == 518400} selected="selected"{/if} value="518400">12 {$language->months}</option>
                </optgroup>
              </select>
              <label for="demo">{help_icon title="`$language->demo`" desc="Click here to upload a demo with this ban submission."}{$language->demo} (<a href="#" id="add_demo">{$language->add}</a>)</label>
              {foreach from=$ban->demos item=demo key=demo_id}
              <div id="demo_{$demo_id}">
                <input class="submit-fields" readonly="readonly" value="{$demo}" /> (<a class="delete_demo" href="#" rel="{$demo_id}">{$language->delete}</a>)
              </div>
              {/foreach}
              <input class="submit-fields" name="demo[]" id="demo" type="file" />
              <div class="center">
                <input name="id" type="hidden" value="{$uri->id}" />
                <input class="btn ok" type="submit" value="{$language->save}" />
                <input class="back btn cancel" type="button" value="{$language->back}" />
              </div>
            </fieldset>
          </form>