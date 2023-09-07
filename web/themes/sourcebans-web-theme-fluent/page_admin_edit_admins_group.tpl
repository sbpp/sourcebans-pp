<div class="layout_box flex:11 admin_tab_content">
    <div class="admin_tab_content_title">
        <h2><i class="fas fa-user-edit"></i> Admin Groups</h2>
    </div>

    <div class="padding">
        <div>
            For more information or help regarding a certain subject move your mouse over the question mark.
        </div>
        <div class="margin-bottom:half">
            Choose the new groups that you want <span class="text:bold">{$group_admin_name}</span> to appear in.
        </div>

        <form action="" method="post">
            <div class="margin-bottom:half">
                <label for="wg" class="form-label form-label:bottom">
                    Web Admin Group
                </label>

                <select name="wg" id="wg" class="form-select form-full">
                    <option value="-1">No Group</option>
                    <optgroup label="Groups" style="font-weight:bold;">
                        {foreach from=$web_lst item="wg"}
                            <option value="{$wg.gid}" {if $wg.gid == $group_admin_id} selected="selected" {/if}>
                                {$wg.name}</option>
                        {/foreach}
                    </optgroup>
                </select>

                <div id="wgroup.msg" class="message message:error margin-top:half" style="display: none;"></div>
            </div>

            <div class="margin-bottom:half">
                <label for="sg" class="form-label form-label:bottom">
                    Server Admin Group
                </label>

                <select name="sg" id="sg" class="form-select form-full">
                    <option value="-1">No Group</option>

                    <optgroup label="Groups" style="font-weight:bold;">
                        {foreach from=$group_lst item="sg"}
                            <option value="{$sg.id}" {if $sg.id == $server_admin_group_id} selected="selected" {/if}>
                                {$sg.name}</option>
                        {/foreach}
                    </optgroup>
                </select>

                <div id="sgroup.msg" class="message message:error margin-top:half" style="display: none;"></div>
            </div>

            <div class="flex flex-ai:center flex-jc:space-between margin-top">
                {sb_button text="Save Changes" class="button button-success" id="agroups" submit=true}
                {sb_button text="Back" onclick="history.go(-1)" class="button button-light" id="aback"}
            </div>
        </form>
    </div>
</div>
</div>