{if NOT $permission_addban}
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
        <h2><i class="fas fa-microphone-alt-slash"></i> Add Block</h2>
    </div>

    <div class="padding">
        <div id="msg-green" class="message message:succes margin-bottom:half" style="display: none;">
            <h3>Block Added</h3>
            <div>The new admin has been successfully added to the system.</div>
            <div class="text:italic">Redirecting back to comms page</div>
        </div>

        <div class="margin-bottom">
            For more information or help regarding a certain subject move your mouse over the question mark.
        </div>

        <div class="margin-bottom:half">
            <label for="nickname" class="form-label form-label:bottom">
                Nickname
            </label>

            <input type="hidden" id="fromsub" value="" />
            <input type="text" TABINDEX=1 class="form-input form-full" id="nickname" name="nickname" />

            <div class="form-desc">
                Type the nickname of the person that you are banning.
            </div>
            <div id="nick.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <label for="steam" class="form-label form-label:bottom">
                Steam ID / Community ID
            </label>

            <input type="text" TABINDEX=3 class="form-input form-full" id="steam" name="steam" />

            <div class="form-desc">
                The Steam ID or Community ID of the person to ban.
            </div>
            <div id="steam.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <label for="type" class="form-label form-label:bottom">
                Block Type
            </label>

            <select id="type" name="type" TABINDEX=2 class="form-select form-full">
                <option value="1">Voice</option>
                <option value="2">Chat</option>
                <option value="3">Chat &amp; Voice</option>
            </select>
        </div>

        <div class="margin-bottom:half">
            <label for="listReason" class="form-label form-label:bottom">
                Block Reason
            </label>

            <select id="listReason" name="listReason" TABINDEX=4 class="form-select form-full"
                onChange="changeReason(this[this.selectedIndex].value);">
                <option value="" selected> -- Select Reason -- </option>
                <optgroup label="Violation">
                    <option value="Obscene language">Obscene language</option>
                    <option value="Insult players">Insult players</option>
                    <option value="Admin disrespect">Admin disrespect</option>
                    <option value="Inappropriate Language">Inappropriate Language</option>
                    <option value="Trading">Trading</option>
                    <option value="Spam in chat/voice">Spam</option>
                    <option value="Advertisement">Advertisement</option>
                </optgroup>
                <option value="other">Other Reason</option>
            </select>

            <div id="dreason" style="display:none;">
                <textarea class="form-text margin-top:half" TABINDEX=4 cols="30" rows="5" id="txtReason"
                    name="txtReason"></textarea>
            </div>

            <div class="form-desc">
                Explain in detail, why this block is being made.
            </div>
            <div id="reason.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <label for="banlength" class="form-label form-label:bottom">
                Block Length
            </label>

            <select id="banlength" TABINDEX=5 class="form-select form-full">
                <option value="0">Permanent</option>
                <optgroup label="minutes">
                    <option value="1">1 minute</option>
                    <option value="5">5 minutes</option>
                    <option value="10">10 minutes</option>
                    <option value="15">15 minutes</option>
                    <option value="30">30 minutes</option>
                    <option value="45">45 minutes</option>
                </optgroup>
                <optgroup label="hours">
                    <option value="60">1 hour</option>
                    <option value="120">2 hours</option>
                    <option value="180">3 hours</option>
                    <option value="240">4 hours</option>
                    <option value="480">8 hours</option>
                    <option value="720">12 hours</option>
                </optgroup>
                <optgroup label="days">
                    <option value="1440">1 day</option>
                    <option value="2880">2 days</option>
                    <option value="4320">3 days</option>
                    <option value="5760">4 days</option>
                    <option value="7200">5 days</option>
                    <option value="8640">6 days</option>
                </optgroup>
                <optgroup label="weeks">
                    <option value="10080">1 week</option>
                    <option value="20160">2 weeks</option>
                    <option value="30240">3 weeks</option>
                </optgroup>
                <optgroup label="months">
                    <option value="43200">1 month</option>
                    <option value="86400">2 months</option>
                    <option value="129600">3 months</option>
                    <option value="259200">6 months</option>
                    <option value="518400">12 months</option>
                </optgroup>
            </select>

            <div id="length.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="flex flex-ai:center flex-jc:space-between margin-top">
            {sb_button text="Add block" onclick="ProcessBan();" class="button button-success" id="aban" submit=false}
            {sb_button text="Back" onclick="history.go(-1)" class="button button-light" id="aback"}
        </div>
    </div>
{/if}