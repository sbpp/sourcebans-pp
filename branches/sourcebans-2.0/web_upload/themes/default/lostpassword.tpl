          {if isset($password)}
          <h4>Password Reset</h4>
          <p>Your password has been reset to:</p>
          <p><strong>{$password}</strong></p>
          <p>Please login using this password, then <a href="{build_uri controller=account}#password">Change the password</a>.</p>
          {else}
          <form action="" id="login" method="post">
            <fieldset>
              <h4>Please type your email address in the box below to have your password reset.</h4>
              <br />
              <div id="loginPasswordDiv">
                <label for="email">{$language->email_address}:</label><br />
                <input class="loginmedium" {nid id="email"} />
              </div>
              <div id="loginSubmit">
                <input class="btn ok" type="submit" value="{$language->submit}" />
              </div>
            </fieldset>
          </form>
          {/if}