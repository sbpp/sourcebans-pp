<div class="admin_tab_content_title">
    <h2><i class="fas fa-cogs"></i> Mod Details</h2>
</div>

<div class="padding">
    <div class="margin-bottom">
        For more information or help regarding a certain subject move your mouse over the question mark.
    </div>

    <form action="" method="post">
        <input type="hidden" name="insert_type" value="add">

        <div class="margin-bottom:half">
            <label for="name" class="form-label form-label:bottom">
                Mod Name
            </label>

            <input type="hidden" id="icon_hid" name="icon_hid" value="{$mod_icon}">
            <input type="text" TABINDEX=1 class="form-input form-full" id="name" name="name" value="{$name}" />

            <div class="form-desc">
                Type the name of the mod you are adding.
            </div>

            <div id="name.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <label for="folder" class="form-label form-label:bottom">
                Mod Folder
            </label>

            <input type="text" TABINDEX=2 class="form-input form-full" id="folder" name="folder" value="{$folder}" />

            <div class="form-desc">
                Type the name of this mods folder. For example, Counter-Strike: Source's mod folder is 'cstrike'.
            </div>

            <div id="folder.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <label for="steam_universe" class="form-label form-label:bottom">
                Steam Universe Number
            </label>

            <input type="text" TABINDEX=3 class="form-input form-full" id="steam_universe" name="steam_universe"
                value="{$steam_universe}" />

            <div class="form-desc">
                (STEAM_<span class="text:bold">X</span>:Y:Z) Some games display the steamid differently than others.
                Type the first number in the SteamID (<span class="text:bold">X</span>) depending on how it's rendered
                by this mod. (Default: 0).
            </div>
        </div>

        <div class="margin-bottom:half">
            <input type="checkbox" TABINDEX=4 id="enabled" class="form-check" name="enabled" value="1" />

            <label for="enabled" class="form-label form-label:left">
                Mod Enabled
            </label>

            <div class="form-desc">
                Select if this mod is enabled and assignable to bans and servers.
            </div>
        </div>

        <div class="margin-bottom:half">
            <label for="enabled" class="form-label form-label:button">
                Upload Icon
            </label>

            {sb_button text="Upload MOD Icon" onclick="childWindow=open('pages/admin.uploadicon.php','upload','resizable=yes,width=300,height=130');" class="button button-primary" id="upload" submit=false}

            <div class="form-desc">
                Click here to upload an icon to associate with this mod.
            </div>

            {if $mod_icon}
                <div id="icon.msg" class="message message:info margin-top:half">
                    <span class="text:bold">Uploaded:</span> {$mod_icon}
                </div>
            {/if}
        </div>

        <div class="flex flex-ai:center flex-jc:space-between">
            {sb_button text="Save Changes" class="button button-success" id="editmod" submit=true}
            {sb_button text="Back" onclick="history.go(-1)" class="button button-light" id="back" submit=false}
        </div>
    </form>
</div>