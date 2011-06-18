          {if isset($password)}
          <h4>Password Reset</h4>
          <p>Your password has been reset to:</p>
          <p><strong>{$password}</strong></p>
          <p>Please login using this password, then <a href="{build_url _=account.php#password}">Change the password</a>.</p>
          {else}
          <form action="{$active}" id="login" method="post">
            <fieldset>
              <h4>Please type your email address in the box below to have your password reset.</h4>
              <br />
              <div id="loginPasswordDiv">
                <label for="email">{$lang_email_address}:</label><br />
                <input class="loginmedium" {nid id="email"} />
              </div>
              <div id="loginSubmit">
                <input class="btn ok" type="submit" value="{$lang_submit}" />
              </div>
            </fieldset>
          </form>
          {/if}