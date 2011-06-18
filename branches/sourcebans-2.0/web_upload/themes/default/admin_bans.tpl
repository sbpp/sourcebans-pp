          <div id="admin-page-menu">
            <ul>
              {if $user->permission_add_bans}
              <li id="tab-add"><a href="#add">{$language->add_ban}</a></li>
              {/if}
              {if $user->permission_import_bans}
              <li id="tab-import"><a href="#import">{$language->import_bans}</a></li>
              {/if}
              {if $user->permission_protests}
              <li id="tab-protests"><a href="#protests/active">{$language->ban_protests}</a></li>
              {/if}
              {if $user->permission_submissions}
              <li id="tab-submissions"><a href="#submissions/active">{$language->ban_submissions}</a></li>
              {/if}
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
            </ul>
            <br />
            <div align="center">
              <img alt="{$action_title}" src="{$uri->base}/themes/{$theme}/images/admin/bans.png" title="{$action_title}" />
            </div>
          </div>
          <div id="admin-page-content">
            {if $user->permission_add_bans}
            <form action="" enctype="multipart/form-data" id="pane-add" method="post">
              <fieldset>
                <h3>{$language->add_ban|ucwords}</h3>
                <p>{$language->help_desc}</p>
                <div>
                  <label for="name">{help_icon title="`$language->name`" desc="Type the nickname of the person that you are banning."}{$language->name}</label>
                  <input class="submit-fields" {nid id="name"} />
                </div>
                <div>
                  <label for="type">{help_icon title="`$language->type`" desc="Choose whether to ban by Steam ID or IP address."}{$language->type}</label>
                  <select class="submit-fields" {nid id="type"}>
                    <option value="{$smarty.const.STEAM_BAN_TYPE}">Steam ID</option>
                    <option value="{$smarty.const.IP_BAN_TYPE}">{$language->ip_address}</option>
                  </select>
                </div>
                <div>
                  <label for="steam">{help_icon title="Steam ID" desc="The Steam ID of the person to ban."}Steam ID</label>
                  <input class="submit-fields" {nid id="steam"} />
                  <a class="ban_count" href="{build_uri controller=bans search=null type=steam}"><span><span id="steam_count">0</span> {$language->bans|strtolower}</span></a>
                </div>
                <div>
                  <label for="ip">{help_icon title="`$language->ip_address`" desc="Type the IP address of the person you want to ban."}{$language->ip_address}</label>
                  <input class="submit-fields" {nid id="ip"} />
                  <a class="ban_count" href="{build_uri controller=bans search=null type=ip}"><span><span id="ip_count">0</span> {$language->bans|strtolower}</span></a>
                </div>
                <div>
                  <label for="reason">{help_icon title="`$language->reason`" desc="Explain in detail, why this ban is being made."}{$language->reason}</label>
                  <select class="submit-fields" {nid id="reason"}>
                    <option value=""> -- Select Reason -- </option>
                    <optgroup label="Hacking">
                      <option value="Aimbot">Aimbot</option>
                      <option value="Antirecoil">Antirecoil</option>
                      <option value="Wallhack">Wallhack</option>
                      <option value="Spinhack">Spinhack</option>
                      <option value="Multi-Hack">Multi-Hack</option>
                      <option value="No Smoke">No Smoke</option>
                      <option value="No Flash">No Flash</option>
                    </optgroup>
                    <optgroup label="Behavior">
                      <option value="Team Killing">Team Killing</option>
                      <option value="Team Flashing">Team Flashing</option>
                      <option value="Spamming Mic/Chat">Spamming Mic/Chat</option>
                      <option value="Inappropriate Spray">Inappropriate Spray</option>
                      <option value="Inappropriate Language">Inappropriate Language</option>
                      <option value="Inappropriate Name">Inappropriate Name</option>
                      <option value="Ignoring Admins">Ignoring Admins</option>
                      <option value="Team Stacking">Team Stacking</option>
                    </optgroup>
                    <option value="other">Other Reason</option>
                  </select>
                  <textarea class="submit-fields" cols="30" {nid id="reason_other"} rows="5"></textarea>
                </div>
                <div>
                  <label for="length">{help_icon title="`$language->length`" desc="Select how long you want to ban this person for."}{$language->length}</label>
                  <select class="submit-fields" {nid id="length"}>
                    <option value="0">{$language->permanent}</option>
                    <optgroup label="{$language->minutes}">
                      <option value="1">1 {$language->minute}</option>
                      <option value="5">5 {$language->minutes}</option>
                      <option value="10">10 {$language->minutes}</option>
                      <option value="15">15 {$language->minutes}</option>
                      <option value="30">30 {$language->minutes}</option>
                      <option value="45">45 {$language->minutes}</option>
                    </optgroup>
                    <optgroup label="{$language->hours}">
                      <option value="60">1 {$language->hour}</option>
                      <option value="120">2 {$language->hours}</option>
                      <option value="180">3 {$language->hours}</option>
                      <option value="240">4 {$language->hours}</option>
                      <option value="480">8 {$language->hours}</option>
                      <option value="720">12 {$language->hours}</option>
                    </optgroup>
                    <optgroup label="{$language->days}">
                      <option value="1440">1 {$language->day}</option>
                      <option value="2880">2 {$language->days}</option>
                      <option value="4320">3 {$language->days}</option>
                      <option value="5760">4 {$language->days}</option>
                      <option value="7200">5 {$language->days}</option>
                      <option value="8640">6 {$language->days}</option>
                    </optgroup>
                    <optgroup label="{$language->weeks}">
                      <option value="10080">1 {$language->week}</option>
                      <option value="20160">2 {$language->weeks}</option>
                      <option value="30240">3 {$language->weeks}</option>
                    </optgroup>
                    <optgroup label="{$language->months}">
                      <option value="43200">1 {$language->month}</option>
                      <option value="86400">2 {$language->months}</option>
                      <option value="129600">3 {$language->months}</option>
                      <option value="259200">6 {$language->months}</option>
                      <option value="518400">12 {$language->months}</option>
                    </optgroup>
                  </select>
                </div>
                <div>
                  <label for="demo">{help_icon title="`$language->demo`" desc="Click here to upload a demo with this ban submission."}{$language->demo} (<a href="#" id="add_demo">{$language->add}</a>)</label>
                  <input class="demo submit-fields" name="demo[]" id="demo" type="file" />
                </div>
                <div class="center">
                  <input name="action" type="hidden" value="add" />
                  <input class="btn ok" type="submit" value="{$language->save}" />
                  <input class="back btn cancel" type="button" value="{$language->back}" />
                </div>
              </fieldset>
            </form>
            {/if}
            {if $user->permission_import_bans}
            <form action="" enctype="multipart/form-data" id="pane-import" method="post">
              <fieldset>
                <h3>{$language->import_bans|ucwords}</h3>
                <p>{$language->help_desc}</p>
                <label for="file">{help_icon title="`$language->file`" desc="Select the banned_user.cfg or banned_ip.cfg file to upload and add bans."}{$language->file}</label>
                <input class="submit-fields" {nid id="file"} type="file" />
                <div class="center">
                  <input name="action" type="hidden" value="import" />
                  <input class="btn ok" type="submit" value="{$language->save}" />
                  <input class="back btn cancel" type="button" value="{$language->back}" />
                </div>
              </fieldset>
            </form>
            {/if}
            {if $user->permission_protests}
            <div id="pane-protests">
              <div id="tabsWrapper">
                <ul id="tabs">
                  <li id="tab-protests-active"><span class="tabfill"><a class="tips" href="#protests/active" title="Show Active :: Show active protests.">{$language->active}</a></span></li>
                  <li id="tab-protests-archive"><span class="tabfill"><a class="tips" href="#protests/archive" title="Show Archive :: Show archived protests.">{$language->archive}</a></span></li>
                </ul>
              </div>
              <div id="pane-protests-active">
                <h3>{$language->ban_protests|ucwords} (<span id="protests_count">{$total_protests}</span>)</h3>
                <p>Click a player's nickname to view information about their ban</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <th width="35%">{$language->name}</th>
                    <th>Steam ID</th>
                    <th>{$language->action}</th>
                  </tr>
                  {foreach from=$protests item=protest}
                  <tr id="protest_{$protest->id}" class="opener2 tbl_out">
                    <td class="toggler" style="border-bottom: solid 1px #ccc">{$protest->ban->name}</td>
                    <td style="border-bottom: solid 1px #ccc">{$protest->ban->steam}</td>
                    <td style="border-bottom: solid 1px #ccc">
                      {if $user->permission_edit_bans}
                      <a href="#" onclick="ArchiveProtest({$protest->id}, '{$protest->steam}');">{$language->to_archive}</a> -
                      <a href="#" onclick="DeleteProtest({$protest->id}, '{$protest->steam}');">{$language->delete}</a> -
                      {/if}
                      <a href="{build_uri controller=bans action=email email=$protest->email}">{$language->contact}</a>
                    </td>
                  </tr>
                  <tr id="protest_{$protest->id}">
                    <td colspan="4" align="center" id="ban_details_{$protest->id}">
                      <div class="opener2">
                        <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                          <tr>
                            <th colspan="3">{$language->details}</th>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->name}</td>
                            <td class="listtable_1">{$protest->ban->name}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Steam ID</td>
                            <td class="listtable_1">{$protest->ban->steam}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->ip_address}</td>
                            <td class="listtable_1">
                              {if empty($protest->ban->ip)}
                              <em class="not_applicable">no IP address present</em>
                              {else}
                              {$protest->ban->ip}
                              {/if}
                            </td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->invoked_on}</td>
                            <td class="listtable_1">{$protest->ban->insert_time|date_format:$settings->date_format}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->expires_on}</td>
                            <td class="listtable_1">
                              {if $protest->ban->length}
                              {$protest->ban->insert_time+$protest->ban->length*60|date_format:$settings->date_format}
                              {else}
                              <em class="not_applicable">{$language->not_applicable}.</em>
                              {/if}
                            </td>
                          </tr>
                          <tr>
                            <td class="listtable_1">{$language->reason}</td>
                            <td class="listtable_1">{$protest->ban->reason}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">{$language->admin}</td>
                            <td class="listtable_1">{$protest->ban->admin->name}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">{$language->server}</td>
                            {if isset($protest->ban->server)}
                            <td class="listtable_1" id="host_{$protest->ban->server->id}">Querying Server Data...</td>
                            {else}
                            <td class="listtable_1">SourceBans</td>
                            {/if}
                          </tr>
                          <tr>
                            <td class="listtable_1">Protester IP</td>
                            <td class="listtable_1">{$protest->ip}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">Protested on</td>
                            <td class="listtable_1">{$protest->insert_time|date_format:$settings->date_format}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">Protest message</td>
                            <td class="listtable_1">{$protest->reason}</td>
                          </tr>
                          {if $user->permission_list_comments}
                          <tr>
                            <td class="listtable_1" width="20%">{$language->comments}</td>
                            <td class="listtable_1" height="60">
                              <table width="100%">
                                {foreach from=$protest->comments item=comment key=comment_id name=comment}
                                <tr>
                                  <td><strong>{$comment->name}</strong></td>
                                  <td align="right"><strong>{$comment->insert_time|date_format:$settings->date_format}</strong></td>
                                  {if $user->perrmission_edit_comments || $comment->admin->id == $user->id}
                                  <td align="right">
                                    <a href="{build_uri controller=comments action=edit id=$comment_id}" class="tips" title="<img src='images/edit.gif' alt='' style='vertical-align:middle' /> :: {$language->edit_comment|ucwords}"><img src='images/edit.gif' alt='' style='vertical-align:middle' /></a>
                                    <a href="#" class="tip" title="<img alt='' src='images/delete.gif' style='vertical-align:middle' /> :: {$language->delete_comment|ucwords}" onclick="DeleteComment('{$comment_id}', '{$smarty.const.BAN_TYPE}', '-1');"><img src="images/delete.gif" alt="{$language->delete_comment|ucwords}" style="vertical-align: middle" /></a>
                                  </td>
                                  {/if}
                                </tr>
                                <tr>
                                  <td colspan="2">
                                    {$comment->message}
                                  </td>
                                </tr>
                                {if !empty($comment->edit_admin->name)}
                                <tr>
                                  <td class="comment_edit" colspan="3">
                                    last edit {$comment->edit_time|date_format:$settings->date_format} by {$comment->edit_admin->name}
                                  </td>
                                </tr>
                                {/if}
                                {if !$smarty.foreach.comment.last}
                                <tr><td colspan="3"><hr /></td></tr>
                                {/if}
                                {foreachelse}
                                <tr><td>{$language->none}</td></tr>
                                {/foreach}
                              </table>
                            </td>
                          </tr>
                          {/if}
                        </table>
                      </div>
                    </td>
                  </tr>
                  {/foreach}
                </table>
              </div>
              <div id="pane-protests-archive">
                <h3>{$language->archived_protests|ucwords} (<span id="archived_protests_count">{$total_archived_protests}</span>)</h3>
                <p>Click a player's nickname to view information about their ban</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <th width="35%">{$language->name}</th>
                    <th>Steam ID</th>
                    <th>{$language->action}</th>
                  </tr>
                  {foreach from=$archived_protests item=protest}
                  <tr id="protest_{$protest->id}" class="opener2 tbl_out">
                    <td class="toggler" style="border-bottom: solid 1px #ccc">{$protest->ban->name}</td>
                    <td style="border-bottom: solid 1px #ccc">{$protest->ban->steam}</td>
                    <td style="border-bottom: solid 1px #ccc">
                      {if $user->permission_edit_bans}
                      <a href="#" onclick="RestoreProtest({$protest->id}, '{$protest->steam}');">{$language->restore}</a> -
                      <a href="#" onclick="DeleteProtest({$protest->id}, '{$protest->steam}');">{$language->delete}</a> -
                      {/if}
                      <a href="{build_uri controller=bans action=email email=$protest->email}">{$language->contact}</a>
                    </td>
                  </tr>
                  <tr id="protest_{$protest->id}">
                    <td colspan="4" align="center" id="ban_details_{$protest->id}">
                      <div class="opener2">
                        <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                          <tr>
                            <th colspan="3">{$language->details}</th>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->name}</td>
                            <td class="listtable_1">{$protest->ban->name}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Steam ID</td>
                            <td class="listtable_1">{$protest->ban->steam}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->ip_address}</td>
                            <td class="listtable_1">
                              {if empty($protest->ban->ip)}
                              <em class="not_applicable">no IP address present</em>
                              {else}
                              {$protest->ip}
                              {/if}
                            </td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->invoked_on}</td>
                            <td class="listtable_1">{$protest->ban->insert_time|date_format:$settings->date_format}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->expires_on}</td>
                            <td class="listtable_1">
                              {if $protest->ban->length}
                              {$protest->ban->insert_time+$protest->ban->length*60|date_format:$settings->date_format}
                              {else}
                              <em class="not_applicable">{$language->not_applicable}.</em>
                              {/if}
                            </td>
                          </tr>
                          <tr>
                            <td class="listtable_1">{$language->reason}</td>
                            <td class="listtable_1">{$protest->ban->reason}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">{$language->admin}</td>
                            <td class="listtable_1">{$protest->admin->name}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">{$language->server}</td>
                            {if isset($protest->ban->server)}
                            <td class="listtable_1" id="host_{$protest->ban->server->id}">Querying Server Data...</td>
                            {else}
                            <td class="listtable_1">SourceBans</td>
                            {/if}
                          </tr>
                          <tr>
                            <td class="listtable_1">Protester IP</td>
                            <td class="listtable_1">{$protest->ip}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">Protested on</td>
                            <td class="listtable_1">{$protest->insert_time|date_format:$settings->date_format}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">Protest reason</td>
                            <td class="listtable_1">{$protest->reason}</td>
                          </tr>
                          {if $user->permission_list_comments}
                          <tr>
                            <td class="listtable_1" width="20%">{$language->comments}</td>
                            <td class="listtable_1" height="60">
                              <table width="100%">
                                {foreach from=$protest->comments item=comment key=comment_id name=comment}
                                <tr>
                                  <td><strong>{$comment->name}</strong></td>
                                  <td align="right"><strong>{$comment->insert_time|date_format:$settings->date_format}</strong></td>
                                  {if $user->perrmission_edit_comments || $comment->admin->id == $user->id}
                                  <td align="right">
                                    <a href="{build_uri controller=comments action=edit id=$comment->id}" class="tips" title="<img src='images/edit.gif' alt='' style='vertical-align:middle' /> :: {$language->edit_comment|ucwords}"><img src='images/edit.gif' alt='' style='vertical-align:middle' /></a>
                                    <a href="#" class="tip" title="<img src='images/delete.gif' alt='' style='vertical-align:middle' /> :: {$language->delete_comment|ucwords}" onclick="DeleteComment('{$comment_id}', '{$smarty.const.BAN_TYPE}', '-1');"><img src="images/delete.gif" alt="{$language->delete_comment|ucwords}" style="vertical-align: middle" /></a>
                                  </td>
                                  {/if}
                                </tr>
                                <tr>
                                  <td colspan="2">
                                    {$comment->message}
                                  </td>
                                </tr>
                                {if !empty($comment->edit_admin->name)}
                                <tr>
                                  <td class="comment_edit" colspan="3">
                                    last edit {$comment->edit_time|date_format:$settings->date_format} by {$comment->edit_admin->name}
                                  </td>
                                </tr>
                                {/if}
                                {if !$smarty.foreach.comment.last}
                                <tr><td colspan="3"><hr /></td></tr>
                                {/if}
                                {foreachelse}
                                <tr><td>{$language->none}</td></tr>
                                {/foreach}
                              </table>
                            </td>
                          </tr>
                          {/if}
                        </table>
                      </div>
                    </td>
                  </tr>
                  {/foreach}
                </table>
              </div>
            </div>
            {/if}
            {if $user->permission_submissions}
            <div id="pane-submissions">
              <div id="tabsWrapper">
                <ul id="tabs">
                  <li id="tab-submissions-active"><span class="tabfill"><a class="tips" href="#submissions/active" title="Show Active :: Show active submissions.">{$language->active}</a></span></li>
                  <li id="tab-submissions-archive"><span class="tabfill"><a class="tips" href="#submissions/archive" title="Show Archive :: Show archived submissions.">{$language->archive}</a></span></li>
                </ul>
              </div>
              <div id="pane-submissions-active">
                <h3>{$language->ban_submissions|ucwords} (<span id="subcount">{$total_submissions}</span>)</h3>
                <p>Click a player's nickname to view information about their submission</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <th width="35%">{$language->name}</th>
                    <th>Steam ID</th>
                    <th>{$language->action}</th>
                  </tr>
                  {foreach from=$submissions item=submission}
                  <tr id="sid_{$submission->id}" class="opener3 tbl_out">
                    <td style="border-bottom: solid 1px #ccc">{$submission->name}</td>
                    <td style="border-bottom: solid 1px #ccc">{$submission->steam}</td>
                    <td style="border-bottom: solid 1px #ccc">
                      <a href="#" onclick="BanSubmission({$submission->id});">{$language->to_ban}</a> -
                      {if $user->permission_edit_bans}
                      <a href="#" onclick="ArchiveSubmission({$submission->id}, '{$submission->steam}');">{$language->to_archive}</a> -
                      <a href="#" onclick="DeleteSubmission({$submission->id}, '{$submission->steam}');">{$language->delete}</a> -
                      {/if}
                      <a href="{build_uri controller=bans action=email email=$submission->email}">{$language->contact}</a>
                    </td>
                  </tr>
                  <tr id="sid_{$submission->id}a">
                    <td colspan="3">
                      <div class="opener3" width="100%" align="center">
                        <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                          <tr>
                            <th colspan="3">{$language->details}</th>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->name}</td>
                            <td class="listtable_1">{$submission->name}</td>
                            <td width="30%" rowspan="11" class="listtable_2 opener">
                              <ul class="ban-edit">
                                <li>{$submission->demo}</li>
                              </ul>
                            </td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Steam ID</td>
                            <td class="listtable_1">{$submission->steam}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->ip_address}</td>
                            <td class="listtable_1">
                              {if empty($submission->ip)}
                              <em class="not_applicable">no IP address present</em>
                              {else}
                              {$submission->ip}
                              {/if}
                            </td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->reason}</td>
                            <td class="listtable_1">{$submission->reason}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->server}</td>
                            {if isset($submission->server)}
                            <td class="listtable_1" id="host_{$submission->server->id}">Querying Server Data...</td>
                            {else}
                            <td class="listtable_1">SourceBans</td>
                            {/if}
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Submitter</td>
                            <td class="listtable_1">{$submission->subname}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Submitter IP</td>
                            <td class="listtable_1">{$submission->subip}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->submitted_on}</td>
                            <td class="listtable_1">{$submission->insert_time|date_format:$settings->date_format}</td>
                          </tr>
                        </table>
                      </div>
                    </td>
                  </tr>
                  {/foreach}
                </table>
              </div>
              <div id="pane-submissions-archive">
                <h3>{$language->archived_submissions|ucwords} (<span id="subcount">{$total_archived_submissions}</span>)</h3>
                <p>Click a player's nickname to view information about their submission</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <th width="35%">{$language->name}</th>
                    <th>Steam ID</th>
                    <th>{$language->action}</th>
                  </tr>
                  {foreach from=$archived_submissions item=sub}
                  <tr id="sid_{$submission->id}" class="opener3 tbl_out">
                    <td style="border-bottom: solid 1px #ccc">{$submission->name}</td>
                    <td style="border-bottom: solid 1px #ccc">{$submission->steam}</td>
                    <td style="border-bottom: solid 1px #ccc">
                      <a href="#" onclick="BanSubmission({$submission->id});">{$language->to_ban}</a> -
                      {if $user->permission_edit_bans}
                      <a href="#" onclick="RestoreSubmission({$submission->id}, '{$submission->steam}');">{$language->restore}</a> -
                      <a href="#" onclick="DeleteSubmission({$submission->id}, '{$submission->steam}');">{$language->delete}</a> -
                      {/if}
                      <a href="{build_uri controller=bans action=email email=$submission->email}">{$language->contact}</a>
                    </td>
                  </tr>
                  <tr id="sid_{$submission->id}a">
                    <td colspan="3">
                      <div class="opener3" width="100%" align="center">
                        <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                          <tr>
                            <th colspan="3">{$language->details}</th>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->name}</td>
                            <td class="listtable_1">{$submission->name}</td>
                            <td width="30%" rowspan="11" class="listtable_2 opener">
                              <ul class="ban-edit">
                                <li>{$submission->demo}</li>
                              </ul>
                            </td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Steam ID</td>
                            <td class="listtable_1">{$submission->steam}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->ip_address}</td>
                            <td class="listtable_1">{$submission->ip}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->reason}</td>
                            <td class="listtable_1">{$submission->reason}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->server}</td>
                            {if isset($submission->server)}
                            <td class="listtable_1" id="host_{$submission->server->id}">Querying Server Data...</td>
                            {else}
                            <td class="listtable_1">SourceBans</td>
                            {/if}
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Submitter</td>
                            <td class="listtable_1">{$submission->subname}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Submitter IP</td>
                            <td class="listtable_1">{$submission->subip}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$language->submitted_on}</td>
                            <td class="listtable_1">{$submission->insert_time|date_format:$settings->date_format}</td>
                          </tr>
                        </table>
                      </div>
                    </td>
                  </tr>
                  {/foreach}
                </table>
              </div>
            </div>
            {/if}
          </div>