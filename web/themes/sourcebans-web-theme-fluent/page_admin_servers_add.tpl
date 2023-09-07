<div class="layout_box flex:11 admin_tab_content tabcontent" id="Add new server">
    {if not $permission_addserver}
        Access Denied
    {else}
        <div class="admin_tab_content_title">
            <h2><i class="fas fa-plus"></i> Server Details</h2>
        </div>

        <div class="padding">
            <div class="margin-bottom">
                For more information or help regarding a certain subject move your mouse over the question mark.
            </div>

            <input type="hidden" name="insert_type" value="add">
            <div class="margin-bottom:half">
                <label for="address" class="form-label form-label:bottom">
                    Server IP/Domain
                </label>
                <input type="text" TABINDEX=1 class="form-input form-full" id="address" name="address" value="{$ip}" />
                <div id="address.msg" class="message message:error margin-top:half" style="display: none;"></div>
            </div>

            <div class="margin-bottom:half">
                <label for="port" class="form-label form-label:bottom">
                    Server Port
                </label>
                <input type="text" TABINDEX=2 class="form-input form-full" id="port" name="port"
                    value="{if $port}{$port}{else}27015{/if}" />
                <div id="port.msg" class="message message:error margin-top:half" style="display: none;"></div>
            </div>

            <div class="margin-bottom:half">
                <label for="rcon" class="form-label form-label:bottom">
                    RCON Password
                </label>
                <input type="password" TABINDEX=3 class="form-input form-full" id="rcon" name="rcon" value="{$rcon}" />
                <div id="rcon.msg" class="message message:error margin-top:half" style="display: none;"></div>
            </div>

            <div class="margin-bottom:half">
                <label for="rcon2" class="form-label form-label:bottom">
                    RCON Password (Confirm)
                </label>
                <input type="password" TABINDEX=4 class="form-input form-full" id="rcon2" name="rcon2" value="{$rcon}" />
                <div id="rcon2.msg" class="message message:error margin-top:half" style="display: none;"></div>
            </div>

            <div class="margin-bottom:half">
                <label for="mod" class="form-label form-label:bottom">
                    Server MOD
                </label>

                <select name="mod" TABINDEX=5 onchange="" id="mod" class="form-select form-full">
                    {if !$edit_server}
                        <option value="-2">Please Select...</option>
                    {/if}
                    {foreach from=$modlist item="mod"}
                        <option value='{$mod.mid}'>{$mod.name}</option>
                    {/foreach}
                </select>

                <div id="mod.msg" class="message message:error margin-top:half" style="display: none;"></div>
            </div>

            <div class="margin-bottom:half">
                <label for="enabled" class="form-label form-label:bottom">
                    Enabled
                </label>
                <input type="checkbox" id="enabled" class="form-check" name="enabled" checked="checked" />
                <div id="enabled.msg" class="message message:error margin-top:half" style="display: none;"></div>
            </div>

            {if $grouplist}
                <div class="margin-bottom:half">
                    <label class="form-label form-label:bottom">
                        Server Groups
                    </label>

                    <ul class="form_ul margin-top">
                        {foreach from=$grouplist item="group"}
                            <li class="margin-bottom:half">
                                <input type="checkbox" class="form-check" value="{$group.gid}" id="g_{$group.gid}"
                                    name="groups[]" />
                                <label for="g_{$group.gid}" class="form-label form-label:right">
                                    {$group.name}
                                </label>
                            </li>
                        {/foreach}
                    </ul>
                    <div id="nsgroup" class="message message:error margin-top:half" style="display: none;"></div>
                </div>
            {/if}

            <div class="flex flex-ai:center flex-jc:space-between margin-top">
                {if $edit_server}
                    {sb_button text=$submit_text onclick="process_edit_server();" class="button button-success" id="aserver" submit=false}
                {else}
                    {sb_button text=$submit_text onclick="process_add_server();" class="button button-success" id="aserver" submit=false}
                {/if}

                {sb_button text="Back" onclick="history.go(-1)" class="button button-light" id="back" submit=false}
            </div>
        </div>
    {/if}
</div>
</div>