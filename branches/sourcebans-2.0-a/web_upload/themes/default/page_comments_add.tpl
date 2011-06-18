          <form action="{$active}" method="post">
            <fieldset>
              <h3>{$lang_add_comment|ucwords}</h3>
              <label for="message">{help_icon title="$lang_comment" desc="Type the text you would like to say."}{$lang_comment}</label>
              <textarea class="comment_message submit-fields" {nid id="message"}></textarea>
              <a class="toggle_mce" href="#" rel="message">Enable/Disable WYSIWYG editor</a>
              <div class="center">
                <input name="bid" type="hidden" value="{$smarty.get.id}" />
                <input name="type" type="hidden" value="{$smarty.get.type}" />
                <input class="btn ok" type="submit" value="{$lang_submit}" />
                <input class="back btn cancel" type="button" value="{$lang_back}" />
              </div>
              {foreach from=$comments item=comment}
              <hr />
              <strong>{$comment.name}</strong>
              <strong class="right">{$comment.time|date_format:$date_format}</strong>
              <p>{$comment.message}</p>
              {if !empty($comment.edit_admin_name)}
              <span class="comment_edit">last edit {$comment.edit_time|date_format:$date_format} by {$comment.edit_admin_name}</span>
              {/if}
              {/foreach}
            </fieldset>
          </form>