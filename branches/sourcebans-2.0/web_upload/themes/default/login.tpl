          <form action="" id="login" method="post">
            <fieldset>
              <div id="loginUsernameDiv">
                <label for="username">{$language->username}:</label>
                <input class="loginmedium" {nid id="username"} />
              </div>
              <div id="loginPasswordDiv">
                <label for="password">{$language->password}:</label>
                <input class="loginmedium" {nid id="password"} type="password" />
              </div>
              <div id="loginRememberMe">
                <input checked="checked" class="checkbox" {nid id="remember"} type="checkbox" />
                <label class="checkbox" for="remember">{$language->remember_me}</label>
              </div>
              <div id="loginSubmit">
                <input class="btn ok" type="submit" value="{$language->login}" />
              </div>
              <div id="loginOtherlinks">
                <a href="{build_uri controller=index}">Back to the homepage</a> - <a href="{build_uri controller=lostpassword}">Lost your password?</a>
              </div>
            </fieldset>
          </form>