          <form action="" method="post">
            <fieldset>
              <h3>{$language->add_comment|ucwords}</h3>
              <label for="message">{help_icon title="`$language->comment`" desc="Type the text you would like to say."}{$language->comment}</label>
              <textarea class="comment_message submit-fields" {nid id="message"}></textarea>
              <a class="toggle_mce" href="#" rel="message">Enable/Disable WYSIWYG editor</a>
              <div class="center">
                <input name="ban_id" type="hidden" value="{$uri->id}" />
                <input name="type" type="hidden" value="{$uri->type}" />
                <input class="btn ok" type="submit" value="{$language->submit}" />
                <input class="back btn cancel" type="button" value="{$language->back}" />
              </div>
{foreach from=$comments item=comment}
              <hr />
              <strong>{$comment->admin->name}</strong>
              <strong class="right">{$comment->insert_time|date_format:$settings->date_format}</strong>
              <p>{$comment->message}</p>
{if !empty($comment->edit_admin->name)}
              <span class="comment_edit">last edit {$comment->edit_time|date_format:$settings->date_format} by {$comment->edit_admin->name}</span>
{/if}
{/foreach}
            </fieldset>
          </form>