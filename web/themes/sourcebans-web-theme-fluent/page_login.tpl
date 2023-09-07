<div class="flex flex-jc:center flex-ai:center">
    <div class="layout_box_small layout_box">
        <div class="layout_box_title">
            <h2>Admin Login</h2>
        </div>

        <div class="padding">
            -{if $steamlogin_show == 1}-
                <div class="margin-bottom:half">
                    <label for="loginUsername" class="form-label form-label:bottom">
                        Username
                    </label>
                    <input id="loginUsername" class="form-input form-full" type="text" name="username" />
                    <div id="loginUsername.msg" class="message message:error margin-top:half" style="display: none;">
                    </div>
                </div>

                <div class="margin-bottom:half">
                    <label for="loginPassword" class="form-label form-label:bottom">
                        Password
                    </label>
                    <input id="loginPassword" class="form-input form-full" type="password" name="password" />
                    <div id="loginPassword.msg" class="message message:error margin-top:half" style="display: none;">
                    </div>
                </div>

                <div class="flex flex-jc:space-between flex-ai:center margin-top">
                    <div class="flex flex-jc:space-between flex-ai:center">
                        <span class="input_checkbox">
                            <input id="loginRememberMe" type="checkbox" name="remember" value="checked"
                                class="form-check" />
                            <label for="loginRememberMe" class="form-label form-label:left">Remember me</label>
                        </span>
                    </div>

                    -{if $steamlogin_show == 1}-
                        <a href="index.php?p=lostpassword">
                            Lost your password?
                        </a>
                    -{/if}-
                </div>
            -{/if}-

            -{if $steamlogin_show == 1}-
                <div class="flex margin-top">
                    -{sb_button text="Login" onclick=$redir class="button button-success flex:11" id="alogin" submit=false}-
                </div>
            -{/if}-

            <div class="margin-top text:center">
                <a href="index.php?p=login&o=steam">
                    <img src="images/steamlogin.png" alt="Login Steam">
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    $E('html').onkeydown = function(event) {
        var event = new Event(event);
        if (event.key == 'enter' ) -{$redir}-
    };
    $('loginRememberMeDiv').onkeydown = function(event) {
        var event = new Event(event);
        if (event.key == 'space') $('loginRememberMeDiv').checked = true;
    };
    $('button').onkeydown = function(event) {
        var event = new Event(event);
        if (event.key == 'space' ) -{$redir}-
    };
</script>