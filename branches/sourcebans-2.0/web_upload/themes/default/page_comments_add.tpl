          <form action="{$active}" method="post">
            <fieldset>
              <h3>{$lang_add_comment|ucwords}</h3>
              <label for="text">{help_icon title="$lang_comment" desc="Type the text you would like to say."}{$lang_comment}</label>
              <textarea class="submit-fields" {nid id=text"} style="width: 100%; height: 250px;"></textarea>
              <a class="toggle_mce" href="#" rel="text">Enable/Disable WYSIWYG editor</a><div class="badentry" id="commenttext.msg"></div>
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
              <span style='font-size: 6pt; color: grey;'>last edit {$comment.edit_time|date_format:$date_format} by {$comment.edit_admin_name}</span>
              {/if}
              {/foreach}
            </fieldset>
          </form>