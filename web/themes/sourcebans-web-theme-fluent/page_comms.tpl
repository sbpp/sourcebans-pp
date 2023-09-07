{if $comment}
  <div class="flex flex-jc:center flex-ai:center">
    <div class="layout_box layout_box_medium">
      <div class="layout_box_title">
        <h2><i class="fa-solid fa-comment"></i> {$commenttype} Comment</h2>
      </div>

      <div class="padding">
        <textarea class="form-text" id="commenttext" name="commenttext" {if !$canedit}disabled{/if}>{$commenttext}</textarea>

        <div id="commenttext.msg" class="message message:error" style="display:none;"></div>

        <div class="margin-top:half flex flex-jc:space-between flex-ai:center">
          <input type="hidden" name="bid" id="bid" value="{$comment}">
          <input type="hidden" name="ctype" id="ctype" value="{$ctype}">

          {if $cid != ""}
            <input type="hidden" name="cid" id="cid" value="{$cid}">
          {else}
            <input type="hidden" name="cid" id="cid" value="-1">
          {/if}

          <input type="hidden" name="page" id="page" value="{$page}">

		{if $canedit}
          <a class="button button-important" onclick="history.go(-1)">Cancel</a>
		  <a class="button button-success" onclick="ProcessComment();">Add</a>
		{/if}
        </div>
        {foreach from=$othercomments item="com"}
            <div class="flex flex-jc:center flex-ai:center">
                <div class="layout_box layout_box_medium padding">
                    <hr>
                <div>
                    <span class=left><b>{$com.comname}</b></span>
                    <span class=right><b>{$com.added}</b></span> 	
                </div>
                    {$com.commenttxt}
                    {if $com.editname != ''}<br /><br /><i>Last edit {$com.edittime} by {$com.editname}</i>{/if}
                </div>
            </div>
        {/foreach}
      </div>
    </div>
  </div>
{else}
  {load_template file="admin.comms.search"}

  <div class="layout_box margin-bottom padding:half flex flex-jc:space-between flex-ai:center m:flex-fd:column">
    <span>
      <a href="index.php?p=commslist&hideinactive={if $hidetext == 'Hide'}true{else}false{/if}{$searchlink|smarty_htmlspecialchars}"
        title="{$hidetext} inactive">{$hidetext} inactive</a> | <i>Total Blocks: {$total_bans}</i>
    </span>
    <div class="pagination">
      <span>{$ban_nav}</span>
    </div>
  </div>

  <div class="layout_box">
    <div class="table padding">
      <div class="table_box">
        <table class="table_box">
          <thead>
            <tr>
              <th>MOD/Type</th>
              <th class="text:left">Date</th>
              <th class="text:left">Player</th>
              {if !$hideadminname}
                <th class="text:left">Admin</th>
              {/if}
              <th>Length</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$ban_list item=ban name="banlist"}
                        <tr class="collapse" {if $ban.server_id != 0}
						onclick="xajax_ServerHostPlayers({$ban.server_id}, 'id', 'host_{$ban.ban_id}');"
						{/if}
						>
                <td class="text:center">{$ban.mod_icon}</td>
				<td>{$ban.ban_date}</td>
                  {if empty($ban.player)}
                    <td class="text:italic">No nickname present</td>
                  {else}
				  <td>
                      {if $view_comments && $ban.commentdata != "None" && $ban.commentdata|@count > 0}
                          <div style="float:right">
                              {$ban.commentdata|@count} <i class="fas fa-clipboard-list fa-lg"></i>
                          </div>
                      {/if}
                      {if empty($ban.player)}
                        <span class="text:italic">No nickname present</span>
                      {else}
                        <span>{$ban.player|escape:'html'|smarty_stripslashes}</span>
                      {/if}
                    </td>
                  {/if}

                {if !$hideadminname}
                  <td>
                    {if !empty($ban.admin)}
                      <span>
                        {$ban.admin|escape:'html'}
                      </span>
                    {else}
                      <span class="text:italic">Admin deleted</span>
                    {/if}
                  </td>
                {/if}

                <td class="{$ban.class}">{$ban.banlength}</td>
              </tr>

              <tr class="table_hide">
                <td colspan="8">
                  <div class="collapse_content">
                    <div class="padding flex flex-jc:start">
                      <ul class="ban_action responsive_show:desktop">
					  {if $view_bans}
                        {if ($ban.view_edit && (!isset($ban.unbanned) || !$ban.unbanned))}
                          <li class="button button-primary">{$ban.edit_link}</li>
                        {/if}
						{if $view_comments}
                        <li class="button button-success">{$ban.addcomment}</li>
						{/if}
						{if ($ban.unbanned == false && $ban.view_unban)}
                          <li class="button button-important">{$ban.unban_link}</li>
                        {/if}
						{if isset($ban.unbanned) && $ban.reban_link != false}
                          <li class="button button-important">{$ban.reban_link}</li>
                        {/if}
                        {if $ban.view_delete}
                          <li class="button button-important">{$ban.delete_link}</li>
                        {/if}
					{else}
						<li>
							<a class="button button-success" href='index.php?p=login'>Admin ? Sign In</a>
						</li>
					{/if}
                      </ul>
                      <ul class="ban_list_detal">
                        <li>
                          <span><i class="fas fa-user"></i> Player</span>

                          {if empty($ban.player)}
                            <span class="text:italic">No nickname present</span>
                          {else}
                            <span>{$ban.player|escape:'html'|smarty_stripslashes}</span>
                          {/if}
                        </li>
                        <li>
                          <span><i class="fab fa-steam-symbol"></i> Steam ID</span>

                          {if empty($ban.steamid)}
                            <span class="text:italic">No Steam ID present</span>
                          {else}
                            <span>{$ban.steamid}</span>
                          {/if}
                        </li>
                        <li>
                          <span><i class="fab fa-steam-symbol"></i> Steam3 ID</span>

                          {if empty($ban.steamid)}
                            <span class="text:italic">No Steam3 ID present</span>
                          {else}
                            <a href="http://steamcommunity.com/profiles/{$ban.steamid3}" target="_blank"
                              rel="noopener">{$ban.steamid3}</a>
                          {/if}
                        </li>
                        <li>
                          <span><i class="fab fa-steam-symbol"></i> Steam Community</span>
                          {if empty($ban.steamid)}
                            <span class="text:italic">No Steam Community ID present</span>
                          {else}
                            <a href="http://steamcommunity.com/profiles/{$ban.communityid}" target="_blank"
                              rel="noopener">{$ban.communityid}</a>
                          {/if}
                        </li>
                        <li>
                          <span><i class="fas fa-play"></i> Invoked on</span>
                          <span>{$ban.ban_date}</span>
                        </li>
                        <li>
                          <span><i class="fas fa-hourglass-half"></i> Block length</span>
                          <span>{$ban.banlength}</span>
                        </li>
                        {if isset($ban.unbanned)}
                          <li>
                            <span><i class="fas fa-user-shield"></i> Unblock reason</span>
                            {if !isset($ban.ureason) || $ban.ureason == ""}
                              <span class="text:italic">No reason present</span>
                            {else}
                              <span>{$ban.ureason}</span>
                            {/if}
                          </li>
                          <li>
                            <span><i class="fas fa-user-shield"></i> Unblocked by Admin</span>

                            {if empty($ban.removedby)}
                              <span class="text:italic">Admin deleted</span>
                            {else}
                              <span>{$ban.removedby|escape:'html'}</span>
                            {/if}
                          </li>
                        {/if}
                        <li>
                          <span><i class="fas fa-clock"></i> Expires on</span>

                          {if $ban.expires == "never"}
                            <span class="text:italic">Not applicable</span>
                          {else}
                            <span>{$ban.expires}</span>
                          {/if}
                        </li>
                        <li>
                          <span><i class="fas fa-question"></i> Reason</span>
						  {if $ban.reason == ""}
                              <span class="text:italic">No reason present</span>
                            {else}
                          <span>{$ban.reason}</span>
						  {/if}
                        </li>
                        {if !$hideadminname}
                          <li>
                            <span><i class="fas fa-ban"></i> Blocked by Admin</span>

                            {if empty($ban.admin)}
                              <span class="text:italic">Admin deleted</span>
                            {else}
                              <span>{$ban.admin|escape:'html'}</span>
                            {/if}
                          </li>
                        {/if}
						<li>
                                                        <span><i class="fas fa-server"></i> Blocked from</span>
                                                            <span {if $ban.server_id != 0} id="host_{$ban.ban_id}"{/if}>
											{if $ban.server_id == 0}
											Web Ban
											{else}
											Please Wait...
											{/if}</span>
                                                    </li>
                        <li>
                          <span><i class="fas fa-ban"></i> Total Blocks</span>
                          <span>{$ban.prevoff_link}
						  	{if $view_bans}
                          		{if $ban.counts > 0 || $ban.commentdata != "None"}
                              		<div>&nbsp;[{$ban.counts} ]</div>
								{/if}
                      		{/if}
						</span>
                        </li>
                      </ul>

                      {if $view_comments}
                        <div class="ban_list_comments margin-left">
                          <div class="layout_box_title">
                            <h2>Comments</h2>
                          </div>
                          {if $ban.commentdata != "None"}
                            <ul>
                              {foreach from=$ban.commentdata item="commenta"}
                                <li>
                                  <div class="layout_box-child padding">
                                    <div class="ban_list_comments_header">
                                      {if !empty($commenta.comname)}
                                        <span class="text:bold">{$commenta.comname|escape:'html'}</span>
                                      {else}
                                        <span class="text:italic">Admin deleted</span>
                                      {/if}
                                      <span>{$commenta.added}</span>
                                      {if $commenta.editcomlink != ""}
                                        {$commenta.editcomlink} {$commenta.delcomlink}
                                      {/if}
                                    </div>

                                    <div class="margin-top flex flex-fd:column">
                                      {$commenta.commenttxt}

                                      {if !empty($commenta.edittime)}
                                        <span class="margin-top:half text:italic">
                                          <i class="fas fa-pencil-alt"></i> Last edit
                                          {$commenta.edittime} by {$commenta.editname}
                                        </span>
                                      {/if}
                                    </div>
                                  </div>
                                </li>
                              {/foreach}
                            </ul>
                          {else}
                            <div class="padding">
                              {$ban.commentdata}
                            </div>
                          {/if}
                        </div>
                      {/if}
                    </div>
                  </div>
                </td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="layout_box padding:half margin-top text:right">
    <span class="text:italic">SourceComms plugin &#038; integration to SourceBans made by <a
        href="https://github.com/ppalex7" class="text:bold" target="_blank" rel="noopener">Alex</a></span>
  </div>

  <script type="text/javascript" src="themes/{$theme}/scripts/collapse.js"></script>

  {literal}
    <script type="text/javascript">
      window.addEvent('domready', function() {
      {/literal}
      {if $view_bans}
        $('tickswitch').value = 0;
      {/if}
      {literal}
      });
    </script>
  {/literal}
{/if}
