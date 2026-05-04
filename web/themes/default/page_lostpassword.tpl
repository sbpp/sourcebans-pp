<div id="lostpassword">
    <div id="login-content">

        <div id="msg-blue msg-red" style="display:none;">
            <i><img src="./images/info.png" alt="Warning" /></i>
            <b>Information</b>
            <br />
            If your email is registered, you will receive a password reset link shortly. Please check your email inbox (and spam).
        </div>

        <h4>
            Please type your email address in the box below to have your password reset.
        </h4><br />

        <div id="loginPasswordDiv">
            <label for="email">Your E-Mail Address:</label><br />
            <input id="email" class="loginmedium" type="text" name="email" value="" />
        </div>

        <div id="loginSubmit">
            {sb_button text=Ok onclick="sb.api.call(Actions.AuthLostPassword, {email: sb.$id('email').value}).then(applyApiResponse);" class=ok id=alogin submit=false}
        </div>

    </div>
</div>
