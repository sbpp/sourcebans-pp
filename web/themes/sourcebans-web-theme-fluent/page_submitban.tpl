<div class="flex flex-jc:center flex-ai:center">
    <div class="layout_box layout_box_medium">
        <div class="layout_box_title">
            <h2><i class="fas fa-flag-checkered"></i> Submit a Report</h2>
        </div>

        <div class="padding">
            <div>
                In order to keep our servers running smoothly, offenders of our rules should be punished and we
                can't always be on call to help.
            </div>
            <div>
                When submitting a player report, we ask you to fill out the report as detailed as possible to help
                ban the offender as this will help us process your report quickly.
            </div>

            <div class="margin-top:half margin-bottom">
                If you are unsure on how to record evidence within in-game, please click
                <a href="javascript:void(0)"
                    onclick="ShowBox('How To Record Evidence', 'The best way to record evidence on someone breaking the rules would be to use Shadow Play or Plays.TV. Both pieces of software will record your game 24/7 with little to no impact on your game and you simply press a keybind to record the last X amount of minutes of gameplay which is perfect for catching rule breakers.<br /><br /> Alternatively, you can use the old method of using demos. While you are spectating the offending player, press the ` key on your keyboard to show the Developers Console. If this does not show, you will need to go into your Game Settings and enable this. Then type `record [demoname]` and hit enter, the file will then be in your mod folder of your game directory.', 'blue', '', true);">here</a>
                for an explanation.
            </div>

            <form action="index.php?p=submit" method="post" enctype="multipart/form-data">
                <input type="hidden" name="subban" value="1">

                <div class="margin-bottom:half">
                    <label for="Type" class="form-label form-label:bottom">
                        Ban Type
                    </label>
                    <select id="Type" name="Type" class="form-select form-full"
                        onChange="changeType(this[this.selectedIndex].value);">
                        <option value="0">Steam ID</option>
                        <option value="1">IP Address</option>
                    </select>
                </div>

                <div class="margin-bottom:half">
                    <label for="SteamID" class="form-label form-label:bottom">
                        Players Steam ID
                    </label>
                    <input type="text" id="SteamID" name="SteamID" size="40" maxlength="64" value="{$STEAMID}"
                        class="form-input form-full" />
                </div>

                <div class="margin-bottom:half">
                    <label for="BanIP" class="form-label form-label:bottom">
                        Players IP
                    </label>
                    <input type="text" id="BanIP" name="BanIP" size="40" maxlength="64" value="{$ban_ip}"
                        class="form-input form-full" />
                </div>

                <div class="margin-bottom:half">
                    <label for="PlayerName" class="form-label form-label:bottom">
                        Players Nickname <span class="mandatory">*</span>
                    </label>
                    <input type="text" id="PlayerName" size="40" maxlength="70" name="PlayerName" value="{$player_name}"
                        class="form-input form-full" />
                </div>

                <div class="margin-bottom:half">
                    <label for="BanReason" class="form-label form-label:bottom">
                        Comments <span class="mandatory">*</span> (Please write down a
                        descriptive comment. So NO comments like: "hacking")
                    </label>
                    <textarea id="BanReason" name="BanReason" class="form-text form-full">{$ban_reason}</textarea>
                </div>

                <div class="margin-bottom:half">
                    <label for="SubmitName" class="form-label form-label:bottom">
                        Your Name
                    </label>
                    <input type="text" id="SubmitName" size="40" maxlength="70" name="SubmitName"
                        value="{$subplayer_name}" class="form-input form-full" />
                </div>

                <div class="margin-bottom:half">
                    <label for="EmailAddr" class="form-label form-label:bottom">
                        Your Email <span class="mandatory">*</span>
                    </label>
                    <input type="text" id="EmailAddr" size="40" maxlength="70" name="EmailAddr" value="{$player_email}"
                        class="form-input form-full" />
                </div>

                <div class="margin-bottom:half">
                    <label for="server" class="form-label form-label:bottom">
                        Server <span class="mandatory">*</span>
                    </label>
                    <select id="server" name="server" class="form-select form-full">
                        <option value="-1">-- Select Server --</option>
                        {foreach from=$server_list item="server"}
                            <option value="{$server.sid}" {if $server_selected==$server.sid}selected{/if}>
                                {$server.hostname}</option>
                        {/foreach}
                        <option value="0">Other server / Not listed here</option>
                    </select>
                </div>

                <div class="margin-bottom:half">
                    <label for="demo_file" class="form-label form-label:bottom">
                        Upload demo
                    </label>

                    <input name="demo_file" id="demo_file" type="file" size="25" class="form-file form-full" />
                    <div class="form-desc">
                        Note: Only DEM, ZIP, RAR, 7Z, BZ2 or GZ allowed.
                    </div>
                </div>

                <div class="flex">
                    {sb_button text="Submit" class="button button-primary flex:11" id="save" submit=true}
                </div>
            </form>

            <div class="margin-top">
                <h3>What happens if someone gets banned?</h3>
                <p>
                    If someone you reported gets banned, the SteamID or IP will be included onto the ban on the main
                    bans list and everytime they try to connect to any server they will be blocked from joining and
                    it will be logged into our database.
                </p>
            </div>
        </div>
    </div>
</div>