{if NOT $permission_addgroup}
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
        <h2><i class="fas fa-users"></i> New Group</h2>
    </div>

    <div class="padding">
        <div class="margin-bottom:half">
            <label for="groupname" class="form-label form-label:bottom">
                Group Name
            </label>

            <input type="text" TABINDEX=1 class="form-input form-full" id="groupname" name="groupname" />

            <div class="form-desc">
                Type the name of the new group you want to create.
            </div>
            <div id="name.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <label for="grouptype" class="form-label form-label:bottom">
                Group Type
            </label>

            <select onchange="UpdateGroupPermissionCheckBoxes()" TABINDEX=2 class="form-select form-full" name="grouptype"
                id="grouptype">
                <option value="0">Please Select...</option>
                <option value="1">Web Admin Group</option>
                <option value="2">Server Admin Group</option>
                <option value="3">Server Group</option>
            </select>

            <div class="form-desc">
                This defines the type of group you are about to create. This helps identify and catagorize the groups list.
            </div>
            <div id="type.msg" class="message message:info margin-top:half" style="display: none;"></div>

            <div class="test" colspan="2" id="perms" valign="top" style="height:5px;overflow:hidden;"></div>
        </div>

        <div class="flex flex-ai:center flex-jc:space-between margin-top">
            {sb_button text="Save Changes" onclick="ProcessGroup();" class="button button-success" id="agroup" submit=false}
            {sb_button text="Back" onclick="history.go(-1)" class="button button-light" id="back" submit=false}
        </div>
    </div>
{/if}