          <form action="{$active}" method="post">
            <fieldset>
              <h3>{$lang_edit_comment|ucwords}</h3>
              <label for="message">{help_icon title="$lang_comment" desc="Type the text you would like to say."}{$lang_comment}</label>
              <textarea class="submit-fields" {nid id="message"} style="width: 100%;">{$comment_message}</textarea>
              <a class="toggle_mce" href="#" rel="message">Enable/Disable WYSIWYG editor</a>
              <div class="center">
                <input name="id" type="hidden" value="{$smarty.get.id}" />
                <input name="type" type="hidden" value="{$comment_type}" />
                <input class="btn ok" type="submit" value="{$lang_save}" />
                <input class="back btn cancel" type="button" value="{$lang_back}" />
              </td>
              {foreach from=$comments item=comment key=comment_id}
              {if $comment_id != $smarty.get.id}
              <hr />
              <strong>{$comment.admin_name}</strong>
              <strong class="right">{$comment.time|date_format:$date_format}</strong>
              <p>{$comment.message}</p>
              {if !empty($comment.edit_admin_name)}
              <span style='font-size: 6pt; color: grey;'>last edit {$comment.edit_time|date_format:$date_format} by {$comment.edit_admin_name}</span>
              {/if}
              {/if}
              {/foreach}
            </fieldset>
          </form>