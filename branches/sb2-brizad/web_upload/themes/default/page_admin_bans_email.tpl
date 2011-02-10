          <div id="admin-page-menu">
            <ul>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
              <li class="active"><a class="back" href="#">{$lang_back}</a></li>
            </ul>
            <br />
            <div align="center">
              <img alt="{$page_title}" src="themes/{$theme_dir}/images/admin/bans.png" title="{$page_title}" />
            </div>
          </div>
          <form action="{$active}" id="admin-page-content" method="post">
            <fieldset>
              <h3>Email Player <em>({$smarty.get.email})</em></h3>
              <label for="subject">{help_icon title="Subject" desc="Type the subject of the email."}Subject</label>
              <input class="submit-fields" {nid id="subject"} />
              <label for="message">{help_icon title="$lang_message" desc="Type your message here."}{$lang_message}</label>
              <textarea class="submit-fields" cols="35" {nid id="message"} rows="7"></textarea>
              <div class="center">
                <input name="email" type="hidden" value="{$smarty.get.email}" />
                <input class="btn ok" type="submit" value="{$lang_submit}" />
                <input class="back btn cancel" type="button" value="{$lang_back}" />
              </div>
            </fieldset>
          </form>