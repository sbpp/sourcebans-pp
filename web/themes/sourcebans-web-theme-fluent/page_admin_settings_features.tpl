<div class="admin_tab_content_title">
    <h2><i class="fas fa-cogs"></i> Sourcebans++ Features</h2>
</div>

<div class="padding">
    <div class="margin-bottom">
        For more information or help regarding a certain subject move your mouse over the question mark.
    </div>

    <form action="" method="post">
        <input type="hidden" name="settingsGroup" value="features" />

        <div class="margin-bottom:half">
            <input type="checkbox" name="export_public" class="form-check" id="export_public" />

            <label for="export_public" class="form-label form-label:left">
                Enable Public Bans
            </label>

            <div class="form-desc">
                Check this box to enable the entire ban list to be publically downloaded and shared.
            </div>
        </div>

        <div class="margin-bottom:half">
            <input type="checkbox" name="enable_kickit" class="form-check" id="enable_kickit" />

            <label for="export_public" class="form-label form-label:left">
                Enable KickIt
            </label>

            <div class="form-desc">
                Check this box to kick a player when a ban is posted.
            </div>
        </div>

        <div class="margin-bottom:half">
            {if $steamapi}
                <input type="checkbox" name="enable_groupbanning" class="form-check" id="enable_groupbanning" />
            {else}
                <input type="checkbox" name="enable_groupbanning" class="form-check" id="enable_groupbanning" disabled />
            {/if}

            <label for="enable_groupbanning" class="form-label form-label:left">
                Enable Group Banning
            </label>

            {if !$steamapi}
                <div class="message message:error margin-top:half">
                    You haven't set a valid steamapi key in the config.
                </div>
            {/if}

            <div class="form-desc">
                Check this box, if you want to enable banning of whole steam community groups.
            </div>

            <div id="enable_groupbanning.msg" class="message message:error margin-top:half" style="display: none;">
            </div>
        </div>

        <div class="margin-bottom:half">
            <input type="checkbox" name="enable_friendsbanning" class="form-check" id="enable_friendsbanning" />

            <label for="enable_friendsbanning" class="form-label form-label:left">
                Enable Friend Banning
            </label>

            <div class="form-desc">
                Check this box, if you want to enable banning all steam community friends of a player.
            </div>

            <div id="enable_friendsbanning.msg" class="message message:error margin-top:half" style="display: none;">
            </div>
        </div>

        <div class="margin-bottom:half">
            <input type="checkbox" name="enable_adminrehashing" class="form-check" id="enable_adminrehashing" />

            <label for="enable_adminrehashing" class="form-label form-label:left">
                Enable Admin Rehashing
            </label>

            <div class="form-desc">
                Check this box, if you want to enable the admin rehashing everytime an admin/group has been changed.
            </div>

            <div id="enable_adminrehashing.msg" class="message message:error margin-top:half" style="display: none;">
            </div>
        </div>

        <div class="margin-bottom:half">
            <input type="checkbox" name="enable_steamlogin" class="form-check" id="enable_steamlogin" />

            <label for="enable_steamlogin" class="form-label form-label:left">
                Enable Normal Login
            </label>

            <div class="form-desc">
                Check this box, if you want to enable the Normal login option on the login form.
            </div>

            <div id="enable_steamlogin.msg" class="message message:error margin-top:half" style="display: none;">
            </div>
        </div>

        <div class="margin-bottom:half">
            <input type="checkbox" name="enable_publiccomments" class="form-check" id="enable_publiccomments" />

            <label for="enable_publiccomments" class="form-label form-label:left">
                Enable Public Comments
            </label>

            <div class="form-desc">
                Check this box, if you want to make admin comments on bans viewable by everyone.
            </div>

            <div id="enable_publiccomments.msg" class="message message:error margin-top:half" style="display: none;">
            </div>
        </div>

        <div class="flex flex-ai:center flex-jc:space-between">
            {sb_button text="Save Changes" class="button button-success" id="fsettings" submit=true}
            {sb_button text="Back" class="button button-light" id="fback"}
        </div>
    </form>
</div>