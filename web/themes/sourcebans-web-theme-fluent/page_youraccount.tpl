<div class="layout_box flex:11 admin_tab_content tabcontent" id="View Permissions" style="display: block;">
    <div class="admin_tab_content_title">
        <h2><i class="fas fa-address-card"></i> Your Permissions</h2>
    </div>

    <div class="padding">
        <div class="margin-bottom">
            The following is a list of the permissions that you have on this system.
        </div>

        <div class="flex m:flex-fd:column">
            <div class="flex:11">
                <h3 style="color: var(--table-permanent-text);">Web Permissions</h3>

                <ul>
                    -{if $web_permissions}-
                        -{foreach from=$web_permissions item="permission"}-
                            <li>-{$permission}-</li>
                        -{/foreach}-
                    -{else}-
                        <li class="text:italic">None</li>
                    -{/if}-
                </ul>
            </div>

            <div class="flex:11">
                <h3 style="color: var(--table-unbanned-text);">Server Permissions</h3>

                <ul>
                    -{if $server_permissions}-
                        -{foreach from=$server_permissions item="permission"}-
                            <li>-{$permission}-</li>
                        -{/foreach}-
                    -{else}-
                        <li class="text:italic">None</li>
                    -{/if}-
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="layout_box flex:11 admin_tab_content tabcontent" id="Change Password">
    <div class="admin_tab_content_title">
        <h2><i class="fas fa-key"></i> Change Password</h2>
    </div>

    <div class="padding">
        <div class="margin-bottom:half">
            <label for="current" class="form-label form-label:bottom">
                Current Password
            </label>

            <input type="password" onblur="xajax_CheckPassword(-{$user_aid}-, $('current').value);"
                class="form-input form-full" id="current" name="current" />

            <div class="form-desc">
                We need to know your current password to verify its you.
            </div>

            <div id="current.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom">
            <label for="pass1" class="form-label form-label:bottom">
                New Password
            </label>

            <input class="form-input form-full" type="password" onkeyup="checkYourAcctPass();" id="pass1" value=""
                name="pass1" />

            <div class="form-desc">
                Type your new password here.
            </div>

            <div id="pass1.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <label for="pass2" class="form-label form-label:bottom">
                Confirm Password
            </label>

            <input type="password" onkeyup="checkYourAcctPass();" class="form-input form-full" id="pass2"
                name="pass2" />

            <div class="form-desc">
                Type your new password here.
            </div>

            <div id="pass2.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="flex flex-ai:center flex-jc:space-between">
            <input type="submit" onclick="xajax_CheckPassword(-{$user_aid}-, $('current').value);dispatch();"
                name="button" class="button button-success" id="button" value="Save" />
            <input type="submit" onclick="history.go(-1)" name="button" class="button button-light" id="button"
                value="Cancel" />
        </div>
    </div>
</div>


<div class="layout_box flex:11 admin_tab_content tabcontent" id="Server Password">
    <div class="admin_tab_content_title">
        <h2><i class="fab fa-steam-symbol"></i> Change Server Password</h2>
    </div>

    <div class="padding">
        <div class="margin-bottom">
            <div>
                You will need to specify this password in the game server before you can use your admin rights.
            </div>
            <div>
                Click <a href="http://wiki.alliedmods.net/Adding_Admins_%28SourceMod%29#Passwords"
                    title="SourceMod Password Info" target="_blank" rel="noopener" class="text:bold">here</a> for more
                info.
            </div>
        </div>

        -{if $srvpwset}-
            <div class="margin-bottom:half">
                <label for="scurrent" class="form-label form-label:bottom">
                    Current Server Password
                </label>

                <input type="password" onblur="xajax_CheckSrvPassword(-{$user_aid}-, $('scurrent').value);"
                    class="form-input form-full" id="scurrent" name="scurrent" />

                <div class="form-desc">
                    We need to know your current password to verify its you.
                </div>

                <div id="scurrent.msg" class="message message:error margin-top:half" style="display: none;"></div>
            </div>
        -{/if}-

        <div class="margin-bottom:half">
            <label for="spass1" class="form-label form-label:bottom">
                New Password
            </label>

            <input class="form-input form-full" type="password" onkeyup="checkYourSrvPass();" id="spass1" value=""
                name="spass1" />

            <div class="form-desc">
                Type your new server password here.
            </div>

            <div id="spass1.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <label for="spass2" class="form-label form-label:bottom">
                Confirm Password
            </label>

            <input type="password" onkeyup="checkYourSrvPass();" class="form-input form-full" id="spass2"
                name="spass2" />

            <div class="form-desc">
                Please type your new server password again to avoid a mistake.
            </div>

            <div id="spass2.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        -{if $srvpwset}-
            <div class="margin-bottom:half">
                <input type="checkbox" id="delspass" class="form-check" name="delspass" />

                <label for="delspass" class="form-label form-label:left">
                    Remove Password
                </label>

                <div class="form-desc">
                    Check this box, if you want to delete your server password.
                </div>
            </div>
        -{/if}-

        <div class="flex flex-ai:center flex-jc:space-between">
            <input type="submit"
                onclick="-{if $srvpwset}-xajax_CheckSrvPassword(-{$user_aid}-, $('scurrent').value);-{/if}-srvdispatch();"
                name="button" class="button button-success" id="button" value="Save" />
            <input type="submit" onclick="history.go(-1)" name="button" class="button button-light" id="button"
                value="Cancel" />
        </div>
    </div>
</div>


<div class="layout_box flex:11 admin_tab_content tabcontent" id="Change Email">
    <div class="admin_tab_content_title">
        <h2><i class="fas fa-envelope"></i> Change E-Mail</h2>
    </div>

    <div class="padding">
        <div class="margin-bottom:half">
            <label class="form-label form-label:bottom">
                Current E-Mail
            </label>

            <div class="text:bold">
                -{$email}-
            </div>

            <div class="form-desc">
                This is your current saved E-mail address.
            </div>
        </div>

        <div class="margin-bottom:half">
            <label for="emailpw" class="form-label form-label:bottom">
                Password
            </label>

            <input class="form-input form-full" type="password" id="emailpw" value="" name="emailpw" />

            <div class="form-desc">
                Type your password here.
            </div>

            <div id="emailpw.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <label for="email1" class="form-label form-label:bottom">
                New E-mail
            </label>

            <input class="form-input form-full" type="text" id="email1" value="" name="email1" />

            <div class="form-desc">
                Type your new email address here.
            </div>

            <div id="email1.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <label for="email2" class="form-label form-label:bottom">
                Confirm E-mail
            </label>

            <input type="text" class="form-input form-full" id="email2" name="email2" />

            <div class="form-desc">
                Please type your new email address again to avoid a mistake.
            </div>

            <div id="email2.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="flex flex-ai:center flex-jc:space-between">
            <input type="submit" onclick="checkmail();" name="button" class="button button-success" id="button"
                value="Save" />
            <input type="submit" onclick="history.go(-1)" name="button" class="button button-light" id="button"
                value="Cancel" />
        </div>
    </div>
</div>

<script>
    let error = 0;

    const set_error = (count) => {
        error = count;
    }

    const checkYourAcctPass = () => {
        let err = 0;

        if($('pass1').value.length < -{$min_pass_len}-)
        {
            $('pass1.msg').setStyle('display', 'block');
            $('pass1.msg').setHTML('Your password must be atleast -{$min_pass_len}- letters long');
            err++;
        } else {
            $('pass1.msg').setStyle('display', 'none');
        }
        if ($('pass2').value != "" && $('pass2').value != $('pass1').value) {
            $('pass2.msg').setStyle('display', 'block');
            $('pass2.msg').setHTML('Your passwords dont match');
            err++;
        } else {
            $('pass2.msg').setStyle('display', 'none');
        }
        if (err > 0) {
            set_error(1);
            return false;
        } else {
            set_error(0);
            return true;
        }
    }

    const dispatch = () => {
        if ($('current.msg').innerHTML == "Incorrect password.") {
            alert("Incorrect Password");
            return false;
        }
        if (checkYourAcctPass() && error == 0) {
            xajax_ChangePassword(-{$user_aid}-, $('pass2').value, $('current').value);
        }
    }

    const checkYourSrvPass = () => {
        if (!$('delspass') || $('delspass').checked == false) {
            var err = 0;

            if($('spass1').value.length < -{$min_pass_len}-)
            {
                $('spass1.msg').setStyle('display', 'block');
                $('spass1.msg').setHTML('Your password must be atleast -{$min_pass_len}- letters long');
                err++;
            } else {
                $('spass1.msg').setStyle('display', 'none');
            }
            if ($('spass2').value != "" && $('spass2').value != $('spass1').value) {
                $('spass2.msg').setStyle('display', 'block');
                $('spass2.msg').setHTML('Your passwords dont match');
                err++;
            } else {
                $('spass2.msg').setStyle('display', 'none');
            }
            if (err > 0) {
                set_error(1);
                return false;
            } else {
                set_error(0);
                return true;
            }
        } else {
            set_error(0);
            return true;
        }
    }

    const srvdispatch = () => {
        -{if $srvpwset}-
            if ($('scurrent.msg').innerHTML == "Incorrect password.") {
                alert("Incorrect Password");
                return false;
            }
        -{/if}-
        if (checkYourSrvPass() && error == 0 && (!$('delspass') || $('delspass').checked == false)) {
            xajax_ChangeSrvPassword(-{$user_aid}-, $('spass2').value);
        }
        if ($('delspass').checked == true) {
            xajax_ChangeSrvPassword(-{$user_aid}-, 'NULL');
        }
    }

    const checkmail = () => {
        let err = 0;
        if ($('email1').value == "") {
            $('email1.msg').setStyle('display', 'block');
            $('email1.msg').setHTML('Please type the new E-mail.');
            err++;
        } else {
            $('email1.msg').setStyle('display', 'none');
        }

        if ($('email2').value == "") {
            $('email2.msg').setStyle('display', 'block');
            $('email2.msg').setHTML('Please retype the new E-mail.');
            err++;
        } else {
            $('email2.msg').setStyle('display', 'none');
        }

        if (err == 0 && $('email2').value != $('email1').value) {
            $('email2.msg').setStyle('display', 'block');
            $('email2.msg').setHTML('The typed E-mails doesn\'t match.');
            err++;
        }

        if ($('emailpw').value == "") {
            $('emailpw.msg').setStyle('display', 'block');
            $('emailpw.msg').setHTML('Please type your password.');
            err++;
        } else {
            $('emailpw.msg').setStyle('display', 'none');
        }

        if (err > 0) {
            set_error(1);
            return false;
        } else {
            set_error(0);
        }
        if (error == 0) {
            xajax_ChangeEmail(-{$user_aid}-, $('email2').value, $('emailpw').value);
        }
    }
</script>