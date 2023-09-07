<div class="flex flex-jc:center flex-ai:center">
    <div class="layout_box_small layout_box">
        <div class="layout_box_title">
            <h2><i class="fas fa-key"></i> Lost your password</h2>
        </div>
        <div class="padding">
            <div class="margin-bottom:half">
                Please type your email address in the box below to have your password reset.
            </div>

            <div id="msg-red" class="message message:error margin-bottom:half" style="display:none;">
                The email address you supplied is not registered on the system.
            </div>

            <div id="msg-blue" class="message message:info margin-bottom:half" style="display:none;">
                Please check your email inbox (and spam) for a link which will help you reset your password.
            </div>

            <div class="margin-bottom:half">
                <label for="email" class="form-label form-label:bottom">
                    Email Address
                </label>
                <input id="email" class="form-input form-full" type="text" name="email" />
            </div>

            <div class="flex margin-top">
                {sb_button text="Recover Password" onclick="xajax_LostPassword($('email').value);" class="button button-success flex:11" id="alogin" submit=false}
            </div>
        </div>
    </div>
</div>