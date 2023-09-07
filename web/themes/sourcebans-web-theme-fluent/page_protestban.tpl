<div class="flex flex-jc:center flex-ai:center">
    <div class="layout_box layout_box_medium">
        <div class="layout_box_title">
            <h2><i class="fas fa-user-clock"></i> Appeal a Ban</h2>
        </div>

        <div class="padding">
            <div>
                In order to appeal a ban, you must make sure you are banned via clicking <a href="index.php?p=banlist"
                    class="text:bold">here</a> to see if you are banned and for what
                reason.
            </div>
            <div class="margin-bottom">
                If you are indeed on our ban list and you feel it is unjust or any other circumstances, please fill
                out the appeal format below.
            </div>

            <form action="index.php?p=protest" method="post">
                <input type="hidden" name="subprotest" value="1">

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

                <div id="steam.row" class="margin-bottom:half">
                    <label for="SteamID" class="form-label form-label:bottom">
                        Your SteamID <span class="mandatory">*</span>
                    </label>
                    <input type="text" id="SteamID" name="SteamID" size="40" maxlength="64" value="{$steam_id}"
                        class="form-input form-full" />
                </div>

                <div id="ip.row" class="margin-bottom:half" style="display: none;">
                    <label for="Ip" class="form-label form-label:bottom">
                        Your IP
                    </label>
                    <input type="text" id="Ip" name="IP" size="40" maxlength="64" value="{$ip}"
                        class="form-input form-full" />
                </div>

                <div class="margin-bottom:half">
                    <label for="PlayerName" class="form-label form-label:bottom">
                        Name <span class="mandatory">*</span>
                    </label>
                    <input type="text" id="PlayerName" size="40" maxlength="70" name="PlayerName" value="{$player_name}"
                        class="form-input form-full" />
                </div>

                <div class="margin-bottom:half">
                    <label for="BanReason" class="form-label form-label:bottom">
                        Reason why you should be unbanned <span class="mandatory">*</span>: (Be as descriptive
                        as possible)
                    </label>
                    <textarea id="BanReason" name="BanReason" cols="30" rows="5"
                        class="form-text form-full input">{$reason}</textarea>
                </div>

                <div class="margin-bottom:half">
                    <label for="EmailAddr" class="form-label form-label:bottom">
                        Your Email <span class="mandatory">*</span>
                    </label>
                    <input type="text" id="EmailAddr" size="40" maxlength="70" name="EmailAddr" value="{$player_email}"
                        class="form-input form-full" />
                </div>

                <div class="flex">
                    {sb_button text="Submit" class="button button-primary flex:11" id="alogin" submit=true}
                </div>
            </form>

            <div class="margin-top">
                <h3>What happens after I post my appeal?</h3>
                <p>
                    The staff team will be notified of your appeal. They will then review if the ban is conclusive.
                    After reviewing you will get a reply, which usally means within 24 hours.
                </p>

                <h3>Note:</h3>
                <p>
                    Sending emails with threats to our admins, scolding or shouting will not get you unbanned and you
                    will be permanently denied from using any of our services.
                </p>
            </div>
        </div>
    </div>
</div>