          <div id="admin-page-menu">
            <ul>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
              <li class="active"><a class="back" href="#">{$language->back}</a></li>
            </ul>
            <br />
            <div class="center">
              <img alt="{$action_title}" src="{$uri->base}/themes/{$theme}/images/admin/bans.png" title="{$action_title}" />
            </div>
          </div>
          <form action="" id="admin-page-content" method="post">
            <fieldset>
              <h3>Email Player <em>({$uri->email})</em></h3>
              <label for="subject">{help_icon title="`$language->subject`" desc="Type the subject of the email."}Subject</label>
              <input class="submit-fields" {nid id="subject"} />
              <label for="message">{help_icon title="`$language->message`" desc="Type your message here."}{$language->message}</label>
              <textarea class="submit-fields" cols="35" {nid id="message"} rows="7"></textarea>
              <div class="center">
                <input name="email" type="hidden" value="{$uri->email}" />
                <input class="btn ok" type="submit" value="{$language->submit}" />
                <input class="back btn cancel" type="button" value="{$language->back}" />
              </div>
            </fieldset>
          </form>