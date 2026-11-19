{*
    SourceBans++ (c) 2014-2026 SourceBans++ Dev Team
    Licensed under the Elastic License 2.0.
    See LICENSE.txt for the full license text and THIRD-PARTY-NOTICES.txt for attributions.

    Shared add/edit comment surface for bans, comm blocks, submissions,
    and protests. Both parent Views expose the same scratch-pad properties.
*}
<div class="card" style="max-width:42rem;margin:1.5rem auto">
    <div class="card__header">
        <div>
            <h1 style="margin:0;font-size:var(--fs-base);font-weight:600;color:var(--text)">{$commenttype} comment</h1>
            {if $ctype == 'S' || $ctype == 'P'}
            <p>Visible to admins with access to this moderation queue.</p>
            {else}
            <p>Public visibility follows the public comments setting.</p>
            {/if}
        </div>
    </div>
    <div class="card__body">
        <form id="banlist-comment-form"
              method="post"
              data-bid="{$comment}"
              data-ctype="{$ctype}"
              data-cid="{$cid}"
              data-page="{$page}">
            {csrf_field}
            <label class="label" for="banlist-comment-text">Comment</label>
            <textarea class="textarea"
                      id="banlist-comment-text"
                      name="commenttext"
                      rows="6"
                      required
                      aria-required="true"
                      {if !$canedit}disabled{/if}>{$commenttext}</textarea>
            <p class="text-xs"
               data-testid="comment-editor-error"
               role="alert"
               hidden
               style="color:var(--danger);margin:0.25rem 0 0"></p>
            <div class="flex gap-2 mt-4">
                {if $canedit}
                <button class="btn btn--primary" type="submit" data-testid="comment-editor-submit">
                    {$commenttype} comment
                </button>
                {/if}
                <a class="btn btn--secondary"
                   href="{if $ctype == 'C'}index.php?p=commslist{if $page > 0}&amp;page={$page}{/if}{elseif $ctype == 'S'}index.php?p=admin&amp;c=bans&amp;section=submissions{elseif $ctype == 'P'}index.php?p=admin&amp;c=bans&amp;section=protests{else}index.php?p=banlist{if $page > 0}&amp;page={$page}{/if}{/if}"
                   data-testid="comment-editor-back">Back</a>
            </div>
        </form>

        <div class="mt-6">
            {foreach from=$othercomments item=com name=othercomments}
                {if $smarty.foreach.othercomments.first}
                <h3 style="font-size:var(--fs-base);font-weight:600;margin:0 0 0.5rem">Other comments</h3>
                {/if}
                <div class="mt-4" style="border-top:1px solid var(--border);padding-top:0.75rem">
                    <div class="flex items-center justify-between">
                        {* Comment authors are admin usernames. The parent handler clears them when hideadminname applies. *}
                        {if $hideadminname}
                            <i class="text-faint">Hidden</i>
                        {elseif !empty($com.comname)}
                            <strong>{$com.comname|escape}</strong>
                        {else}
                            <i class="text-faint">deleted admin</i>
                        {/if}
                        <span class="text-xs text-muted">{$com.added}</span>
                    </div>
                    {* nofilter: $com.commenttxt is server-built HTML produced by encodePreservingBr (htmlspecialchars per text segment, only `<br/>` survives) plus a URL-wrap regex that wraps already-escaped URLs in `<a>` tags. *}
                    <div class="text-sm mt-2">{$com.commenttxt nofilter}</div>
                    {if !empty($com.edittime)}
                    <div class="text-xs text-faint mt-2">
                        last edit {$com.edittime} by
                        {if $hideadminname}
                            <i class="text-faint">Hidden</i>
                        {elseif !empty($com.editname)}
                            {$com.editname|escape}
                        {else}
                            <i>deleted admin</i>
                        {/if}
                    </div>
                    {/if}
                </div>
            {/foreach}
        </div>
    </div>
</div>
