<div class="layout_box flex:11 admin_tab_content">
    <div class="admin_tab_content_title">
        <h2><i class="fas fa-user-edit"></i> Admin Details</h2>
    </div>

    <div class="padding">
        <form action="" method="post">
            <div class="margin-bottom:half">
                <label for="adminname" class="form-label form-label:bottom">
                    Admin Login
                </label>
                <input type="text" class="form-input form-full" id="adminname" name="adminname" value="{$user}" />
                <div id="adminname.msg" class="message message:error margin-top:half" style="display: none;"></div>
            </div>

            <div class="margin-bottom:half">
                <label for="steam" class="form-label form-label:bottom">
                    Admin STEAM ID
                </label>
                <input type="text" class="form-input form-full" id="steam" name="steam" value="{$authid}" />
                <div id="steam.msg" class="message message:error margin-top:half" style="display: none;"></div>
            </div>

            <div class="margin-bottom:half">
                <label for="email" class="form-label form-label:bottom">
                    Admin Email
                </label>
                <input type="text" class="form-input form-full" id="email" name="email" value="{$email}" />
                <div id="email.msg" class="message message:error margin-top:half" style="display: none;"></div>
            </div>

            {if $change_pass}
                <div class="margin-bottom:half">
                    <label for="password" class="form-label form-label:bottom">
                        Admin Password
                    </label>
                    <input type="password" class="form-input form-full" id="password" name="password" />
                    <div class="flex margin-top:half">
                        <button id="password_generate" class="button button-light button:line flex:11 margin-right:half">
                          <i class="fas fa-sync"></i> Generate random password
                        </button>
            
                        <button id="password_show" class="button button-light button:line flex:11">
                          <i class="fas fa-eye"></i> Show password
                        </button>
                    </div>
                    <div id="password.msg" class="message message:error margin-top:half" style="display: none;"></div>
                </div>

                <div class="margin-bottom:half">
                    <label for="password2" class="form-label form-label:bottom">
                        Admin Password (confirm)
                    </label>
                    <input type="password" class="form-input form-full" id="password2" name="password2" />
                    <div id="password2.msg" class="message message:error margin-top:half" style="display: none;"></div>
                </div>

                <div class="margin-bottom:half">
                    <label for="a_serverpass" class="form-label form-label:bottom">
                        Server Password <a href="http://wiki.alliedmods.net/Adding_Admins_%28SourceMod%29#Passwords"
                            rel="noopener">More</a>
                    </label>

                    <div class="flex flex-ai:center">
                        <input type="checkbox" id="a_useserverpass" class="form-check margin-right:half"
                            name="a_useserverpass" {if $a_spass} checked="checked" {/if} TABINDEX=6
                            onclick="$('a_serverpass').disabled = !$(this).checked;" />

                        <input type="password" TABINDEX=7 class="form-input form-full" name="a_serverpass" id="a_serverpass"
                            {if !$a_spass} disabled="disabled" {/if} />
							

                        <div id="a_serverpass.msg" class="message message:error margin-top:half" style="display: none;">
                        </div>
                    </div>
                </div>
                {literal}
                <script>
                    document.querySelector('#password_generate').addEventListener('click', el => {
                    el.preventDefault();
                    xajax_generatePassword();
                    });

                    document.querySelector('#password_show').addEventListener('click', el => {
                    el.preventDefault();
                    $('password').type = 'text';
                    });
                </script>
                {/literal}
            {/if}

            <div class="flex flex-ai:center flex-jc:space-between margin-top">
                {sb_button text="Save Changes" class="button button-success" id="editmod" submit=true}
                {sb_button text="Back" onclick="history.go(-1)" class="button button-light" id="back" submit=false}
            </div>
        </form>
    </div>
</div>
