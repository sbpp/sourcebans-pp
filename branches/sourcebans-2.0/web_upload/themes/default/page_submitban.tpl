          <h3>{$lang_submit_ban|ucwords}</h3>
          <p>Here you will be able to submit a ban for a player who is breaking the rules of the gameserver. When submitting a ban we request you to fill out all the fields to be as descriptive as possible in your comments. This will ensure that your ban submission is processed much faster.</p>
          <p>For a short explanation on how to create a demo, click <a id="demo_howto" href="#">here</a>.</p>
          <form action="{$active}" enctype="multipart/form-data" id="submit-main" method="post">
            <fieldset>
              <label for="steam">Steam ID:</label>
              <input class="submit-fields" maxlength="64" {nid id="steam"} value="STEAM_" />
              <label for="ip">{$lang_ip_address}:</label>
              <input class="submit-fields" maxlength="64" {nid id="ip"} />
              <label for="name">{$lang_name} <span class="mandatory">*</span>:</label>
              <input class="submit-fields" maxlength="64" {nid id="name"} />
              <label for="reason">{$lang_reason} (Please write down a descriptive comment. So NO comments like: "hacking") <span class="mandatory">*</span>:</label>
              <textarea class="submit-fields" cols="30" {nid id="reason"} rows="5"></textarea>
              <label for="name">{$lang_name} <span class="mandatory">*</span>:</label>
              <input class="submit-fields" maxlength="64" {nid id="subname"} />
              <label for="subemail">{$lang_email_address} <span class="mandatory">*</span>:</label>
              <input class="submit-fields" maxlength="70" {nid id="subemail"} />
              <label for="server">{$lang_server} <span class="mandatory">*</span>:</label>
              <select class="submit-fields" {nid id="server"}>
                <option value="-1">-- Select Server --</option>
                {foreach from=$servers item=server key=server_id}
                <option id="host_{$server_id}" value="{$server_id}">Querying Server Data...</option>
                {/foreach}
                <option value="0">Other server / Not listed here</option>
              </select>
              <label for="demo">{$lang_demo}:</label>
              <input class="submit-fields" {nid id="demo"} type="file" />
              <p>Note: Only DEM, <a href="http://www.rarlab.com">RAR</a> or <a href="http://www.winzip.com">ZIP</a> allowed.</p>
              <span class="mandatory">*</span> = {$lang_mandatory}
              <input class="btn ok" type="submit" value="{$lang_submit}" />
            </fieldset>
            <h4>What happens if someone gets banned?</h4>
            <p>If someone gets banned, the specific Steam ID will be included in this SourceBans database and everytime this player tries to connect to one of our servers he/she will be blocked and will receive a message that they are blocked by SourceBans.</p>
          </form>