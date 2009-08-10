          <form action="{$active}" id="lostpassword" method="post">
            <fieldset>
              <h4>Please type your email address in the box below to have your password reset.</h4>
              <br />
              <div id="loginPasswordDiv">
                <label for="email">Your E-Mail Address:</label><br />
                <input class="loginmedium" {nid id="email"} />
              </div>
              <div id="loginSubmit">
                <input class="btn ok" type="submit" value="{$lang_submit}" />
              </div>
            </fieldset>
          </form>