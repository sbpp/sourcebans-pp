          <form action="{$active}" id="login" method="post">
            <fieldset>
              <div id="loginUsernameDiv">
                <label for="username">{$lang_username}:</label><br />
                <input class="loginmedium" {nid id="username"} />
              </div>
              <div id="loginPasswordDiv">
                <label for="password">{$lang_password}:</label><br />
                <input class="loginmedium" {nid id="password"} type="password" />
              </div>
              <div id="loginRememberMe">
                <input checked="checked" class="checkbox" {nid id="remember"} type="checkbox" />
                <label class="checkbox" for="remember">{$lang_remember_me}</label>
              </div>
              <div id="loginSubmit">
                <input class="btn ok" type="submit" value="{$lang_login}" />
              </div>
              <div id="loginOtherlinks">
                <a href="index.php">Back to the homepage</a> - <a href="lostpassword.php">Lost your password?</a>
              </div>
            </fieldset>
          </form>