          <div id="admin-page-menu">
            <ul>
              {if $permission_add_bans}
              <li id="tab-add"><a href="#add">{$lang_add_ban}</a></li>
              {/if}
              {if $permission_import_bans}
              <li id="tab-import"><a href="#import">{$lang_import_bans}</a></li>
              {/if}
              {if $permission_protests}
              <li id="tab-protests"><a href="#protests/current">{$lang_ban_protests}</a></li>
              {/if}
              {if $permission_submissions}
              <li id="tab-submissions"><a href="#submissions/current">{$lang_ban_submissions}</a></li>
              {/if}
              <li><a href="{build_url _=banlist.php}">{$lang_ban_list}</a></li>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
            </ul>
            <br />
            <div align="center">
              <img alt="{$page_title}" src="themes/{$theme_dir}/images/admin/bans.png" title="{$page_title}" />
            </div>
          </div>
          <div id="admin-page-content">
            {if $permission_add_bans}
            <form action="{$active}" enctype="multipart/form-data" id="pane-add" method="post">
              <fieldset>
                <h3>{$lang_add_ban|ucwords}</h3>
                <p>{$lang_help_desc}</p>
                <div>
                  <label for="name">{help_icon title="$lang_name" desc="Type the nickname of the person that you are banning."}{$lang_name}</label>
                  <input class="submit-fields" {nid id="name"} />
                </div>
                <div>
                  <label for="type">{help_icon title="$lang_type" desc="Choose whether to ban by Steam ID or IP address."}{$lang_type}</label>
                  <select class="submit-fields" {nid id="type"}>
                    <option value="{$smarty.const.STEAM_BAN_TYPE}">Steam ID</option>
                    <option value="{$smarty.const.IP_BAN_TYPE}">{$lang_ip_address}</option>
                  </select>
                </div>
                <div>
                  <label for="steam">{help_icon title="Steam ID" desc="The Steam ID of the person to ban."}Steam ID</label>
                  <input class="submit-fields" {nid id="steam"} />
                  <a class="ban_count" href="{build_url _=banlist.php search=null type=steam}"><span><span id="steam_count">0</span> {$lang_bans|strtolower}</span></a>
                </div>
                <div>
                  <label for="ip">{help_icon title="$lang_ip_address" desc="Type the IP address of the person you want to ban."}{$lang_ip_address}</label>
                  <input class="submit-fields" {nid id="ip"} />
                  <a class="ban_count" href="{build_url _=banlist.php search=null type=ip}"><span><span id="ip_count">0</span> {$lang_bans|strtolower}</span></a>
                </div>
                <div>
                  <label for="reason">{help_icon title="$lang_reason" desc="Explain in detail, why this ban is being made."}{$lang_reason}</label>
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
                  <label for="length">{help_icon title="$lang_length" desc="Select how long you want to ban this person for."}{$lang_length}</label>
                  <select class="submit-fields" {nid id="length"}>
                    <option value="0">{$lang_permanent}</option>
                    <optgroup label="{$lang_minutes}">
                      <option value="1">1 {$lang_minute}</option>
                      <option value="5">5 {$lang_minutes}</option>
                      <option value="10">10 {$lang_minutes}</option>
                      <option value="15">15 {$lang_minutes}</option>
                      <option value="30">30 {$lang_minutes}</option>
                      <option value="45">45 {$lang_minutes}</option>
                    </optgroup>
                    <optgroup label="{$lang_hours}">
                      <option value="60">1 {$lang_hour}</option>
                      <option value="120">2 {$lang_hours}</option>
                      <option value="180">3 {$lang_hours}</option>
                      <option value="240">4 {$lang_hours}</option>
                      <option value="480">8 {$lang_hours}</option>
                      <option value="720">12 {$lang_hours}</option>
                    </optgroup>
                    <optgroup label="{$lang_days}">
                      <option value="1440">1 {$lang_day}</option>
                      <option value="2880">2 {$lang_days}</option>
                      <option value="4320">3 {$lang_days}</option>
                      <option value="5760">4 {$lang_days}</option>
                      <option value="7200">5 {$lang_days}</option>
                      <option value="8640">6 {$lang_days}</option>
                    </optgroup>
                    <optgroup label="{$lang_weeks}">
                      <option value="10080">1 {$lang_week}</option>
                      <option value="20160">2 {$lang_weeks}</option>
                      <option value="30240">3 {$lang_weeks}</option>
                    </optgroup>
                    <optgroup label="{$lang_months}">
                      <option value="43200">1 {$lang_month}</option>
                      <option value="86400">2 {$lang_months}</option>
                      <option value="129600">3 {$lang_months}</option>
                      <option value="259200">6 {$lang_months}</option>
                      <option value="518400">12 {$lang_months}</option>
                    </optgroup>
                  </select>
                </div>
                <div>
                  <label for="demo">{help_icon title="$lang_demo" desc="Click here to upload a demo with this ban submission."}{$lang_demo} (<a href="#" id="add_demo">{$lang_add}</a>)</label>
                  <input class="demo submit-fields" name="demo[]" id="demo" type="file" />
                </div>
                <div class="center">
                  <input name="action" type="hidden" value="add" />
                  <input class="btn ok" type="submit" value="{$lang_save}" />
                  <input class="back btn cancel" type="button" value="{$lang_back}" />
                </div>
              </fieldset>
            </form>
            {/if}
            {if $permission_import_bans}
            <form action="{$active}" enctype="multipart/form-data" id="pane-import" method="post">
              <fieldset>
                <h3>{$lang_import_bans|ucwords}</h3>
                <p>{$lang_help_desc}</p>
                <label for="file">{help_icon title="$lang_file" desc="Select the banned_user.cfg or banned_ip.cfg file to upload and add bans."}{$lang_file}</label>
                <input class="submit-fields" {nid id="file"} type="file" />
                <div class="center">
                  <input name="action" type="hidden" value="import" />
                  <input class="btn ok" type="submit" value="{$lang_save}" />
                  <input class="back btn cancel" type="button" value="{$lang_back}" />
                </div>
              </fieldset>
            </form>
            {/if}
            {if $permission_protests}
            <div id="pane-protests">
              <div id="tabsWrapper">
                <ul id="tabs">
                  <li id="tab-protests-current"><span class="tabfill"><a class="tips" href="#protests/current" title="Show Protests :: Show current protests.">Current</a></span></li>
                  <li id="tab-protests-archive"><span class="tabfill"><a class="tips" href="#protests/archive" title="Show Archive :: Show the protest archive.">{$lang_archive}</a></span></li>
                </ul>
              </div>
              <div id="pane-protests-current">
                <h3>{$lang_ban_protests|ucwords} (<span id="protests_count">{$total_protests}</span>)</h3>
                <p>Click a player's nickname to view information about their ban</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <th width="35%">{$lang_name}</th>
                    <th>Steam ID</th>
                    <th>{$lang_action}</th>
                  </tr>
                  {foreach from=$protests item=protest key=protest_id}
                  <tr id="protest_{$protest_id}" class="opener2 tbl_out">
                    <td class="toggler" style="border-bottom: solid 1px #ccc">{$protest.ban_name}</td>
                    <td style="border-bottom: solid 1px #ccc">{$protest.ban_steam}</td>
                    <td style="border-bottom: solid 1px #ccc">
                      {if $permission_edit_bans}
                      <a href="#" onclick="ArchiveProtest({$protest_id}, '{$protest.steam}');">{$lang_to_archive}</a> -
                      <a href="#" onclick="DeleteProtest({$protest_id}, '{$protest.steam}');">{$lang_delete}</a> -
                      {/if}
                      <a href="{build_url _=admin_bans_email.php email=$protest.email}">{$lang_contact}</a>
                    </td>
                  </tr>
                  <tr id="pid_{$protest_id}a">
                    <td colspan="4" align="center" id="ban_details_{$protest_id}">
                      <div class="opener2">
                        <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                          <tr>
                            <th colspan="3">{$lang_details}</th>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_name}</td>
                            <td class="listtable_1">{$protest.ban_name}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Steam ID</td>
                            <td class="listtable_1">{$protest.ban_steam}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_ip_address}</td>
                            <td class="listtable_1">
                              {if empty($protest.ban_ip)}
                              <em class="not_applicable">no IP address present</em>
                              {else}
                              {$protest.ip}
                              {/if}
                            </td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_invoked_on}</td>
                            <td class="listtable_1">{$protest.ban_time|date_format:$date_format}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_expires_on}</td>
                            <td class="listtable_1">
                              {if $protest.ban_length}
                              {$protest.ban_time+$protest.ban_length*60|date_format:$date_format}
                              {else}
                              <em class="not_applicable">{$lang_not_applicable}.</em>
                              {/if}
                            </td>
                          </tr>
                          <tr>
                            <td class="listtable_1">{$lang_reason}</td>
                            <td class="listtable_1">{$protest.ban_reason}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">{$lang_admin}</td>
                            <td class="listtable_1">{$protest.admin_name}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">{$lang_server}</td>
                            <td class="listtable_1" id="host_{$protest.server_id}">Querying Server Data...</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">Protester IP</td>
                            <td class="listtable_1">{$protest.ip}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">Protested on</td>
                            <td class="listtable_1">{$protest.time|date_format:$date_format}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">Protest message</td>
                            <td class="listtable_1">{$protest.reason}</td>
                          </tr>
                          {if $permission_list_comments}
                          <tr>
                            <td class="listtable_1" width="20%">{$lang_comments}</td>
                            <td class="listtable_1" height="60">
                              <table width="100%">
                                {foreach from=$protest.comments item=comment key=comment_id name=comment}
                                <tr>
                                  <td><strong>{$comment.name}</strong></td>
                                  <td align="right"><strong>{$comment.time|date_format:$date_format}</strong></td>
                                  {if $edit_comments || $comment.admin_id == $smarty.cookies.sb_admin_id}
                                  <td align="right">
                                    <a href="{build_url _=comments_edit.php id=$comment_id}" class="tips" title="<img src='images/edit.gif' alt='' style='vertical-align:middle' /> :: {$lang_edit_comment|ucwords}"><img src='images/edit.gif' alt='' style='vertical-align:middle' /></a>
                                    <a href="#" class="tip" title="<img alt='' src='images/delete.gif' style='vertical-align:middle' /> :: {$lang_delete_comment|ucwords}" onclick="DeleteComment('{$comment_id}', '{$smarty.const.BAN_TYPE}', '-1');"><img src="images/delete.gif" alt="{$lang_delete_comment|ucwords}" style="vertical-align: middle" /></a>
                                  </td>
                                  {/if}
                                </tr>
                                <tr>
                                  <td colspan="2">
                                    {$comment.message}
                                  </td>
                                </tr>
                                {if !empty($comment.edit_admin_name)}
                                <tr>
                                  <td class="comment_edit" colspan="3">
                                    last edit {$comment.edit_time|date_format:$date_format} by {$comment.edit_admin_name}
                                  </td>
                                </tr>
                                {/if}
                                {if !$smarty.foreach.comment.last}
                                <tr><td colspan="3"><hr /></td></tr>
                                {/if}
                                {foreachelse}
                                <tr><td>{$lang_none}</td></tr>
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
                <h3>{$lang_archived_protests|ucwords} (<span id="archived_protests_count">{$total_archived_protests}</span>)</h3>
                <p>Click a player's nickname to view information about their ban</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <th width="35%">{$lang_name}</th>
                    <th>Steam ID</th>
                    <th>{$lang_action}</th>
                  </tr>
                  {foreach from=$archived_protests item=protest key=protest_id}
                  <tr id="protest_{$protest_id}" class="opener2 tbl_out">
                    <td class="toggler" style="border-bottom: solid 1px #ccc">{$protest.ban_name}</td>
                    <td style="border-bottom: solid 1px #ccc">{$protest.ban_steam}</td>
                    <td style="border-bottom: solid 1px #ccc">
                      {if $permission_edit_bans}
                      <a href="#" onclick="RestoreProtest({$protest_id}, '{$protest.steam}');">{$lang_restore}</a> -
                      <a href="#" onclick="DeleteProtest({$protest_id}, '{$protest.steam}');">{$lang_delete}</a> -
                      {/if}
                      <a href="{build_url _=admin_bans_email.php email=$protest.email}">{$lang_contact}</a>
                    </td>
                  </tr>
                  <tr id="pid_{$protest_id}a">
                    <td colspan="4" align="center" id="ban_details_{$protest_id}">
                      <div class="opener2">
                        <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                          <tr>
                            <th colspan="3">{$lang_details}</th>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_name}</td>
                            <td class="listtable_1">{$protest.ban_name}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Steam ID</td>
                            <td class="listtable_1">{$protest.ban_steam}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_ip_address}</td>
                            <td class="listtable_1">
                              {if empty($protest.ban_ip)}
                              <em class="not_applicable">no IP address present</em>
                              {else}
                              {$protest.ip}
                              {/if}
                            </td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_invoked_on}</td>
                            <td class="listtable_1">{$protest.ban_time|date_format:$date_format}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_expires_on}</td>
                            <td class="listtable_1">
                              {if $protest.ban_length}
                              {$protest.ban_time+$protest.ban_length*60|date_format:$date_format}
                              {else}
                              <em class="not_applicable">{$lang_not_applicable}.</em>
                              {/if}
                            </td>
                          </tr>
                          <tr>
                            <td class="listtable_1">{$lang_reason}</td>
                            <td class="listtable_1">{$protest.ban_reason}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">{$lang_admin}</td>
                            <td class="listtable_1">{$protest.admin_name}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">{$lang_server}</td>
                            <td class="listtable_1" id="host_{$protest.server_id}">Querying Server Data...</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">Protester IP</td>
                            <td class="listtable_1">{$protest.ip}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">Protested on</td>
                            <td class="listtable_1">{$protest.time|date_format:$date_format}</td>
                          </tr>
                          <tr>
                            <td class="listtable_1">Protest message</td>
                            <td class="listtable_1">{$protest.reason}</td>
                          </tr>
                          {if $permission_list_comments}
                          <tr>
                            <td class="listtable_1" width="20%">{$lang_comments}</td>
                            <td class="listtable_1" height="60">
                              <table width="100%">
                                {foreach from=$protest.comments item=comment key=comment_id name=comment}
                                <tr>
                                  <td><strong>{$comment.name}</strong></td>
                                  <td align="right"><strong>{$comment.time|date_format:$date_format}</strong></td>
                                  {if $edit_comments || $comment.admin_id == $smarty.cookies.sb_admin_id}
                                  <td align="right">
                                    <a href="{build_url _=comments_edit.php id=$comment_id}" class="tips" title="<img src='images/edit.gif' alt='' style='vertical-align:middle' /> :: {$lang_edit_comment|ucwords}"><img src='images/edit.gif' alt='' style='vertical-align:middle' /></a>
                                    <a href="#" class="tip" title="<img src='images/delete.gif' alt='' style='vertical-align:middle' /> :: {$lang_delete_comment|ucwords}" onclick="DeleteComment('{$comment_id}', '{$smarty.const.BAN_TYPE}', '-1');"><img src="images/delete.gif" alt="{$lang_delete_comment|ucwords}" style="vertical-align: middle" /></a>
                                  </td>
                                  {/if}
                                </tr>
                                <tr>
                                  <td colspan="2">
                                    {$comment.message}
                                  </td>
                                </tr>
                                {if !empty($comment.edit_admin_name)}
                                <tr>
                                  <td class="comment_edit" colspan="3">
                                    last edit {$comment.edit_time|date_format:$date_format} by {$comment.edit_admin_name}
                                  </td>
                                </tr>
                                {/if}
                                {if !$smarty.foreach.comment.last}
                                <tr><td colspan="3"><hr /></td></tr>
                                {/if}
                                {foreachelse}
                                <tr><td>{$lang_none}</td></tr>
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
            {if $permission_submissions}
            <div id="pane-submissions">
              <div id="tabsWrapper">
                <ul id="tabs">
                  <li id="tab-submissions-current"><span class="tabfill"><a class="tips" href="#submissions/current" title="Show Submissions :: Show current submissions.">Current</a></span></li>
                  <li id="tab-submissions-archive"><span class="tabfill"><a class="tips" href="#submissions/archive" title="Show Archive :: Show the submission archive.">{$lang_archive}</a></span></li>
                </ul>
              </div>
              <div id="pane-submissions-current">
                <h3>{$lang_ban_submissions|ucwords} (<span id="subcount">{$total_submissions}</span>)</h3>
                <p>Click a player's nickname to view information about their submission</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <th width="35%">{$lang_name}</th>
                    <th>Steam ID</th>
                    <th>{$lang_action}</th>
                  </tr>
                  {foreach from=$submissions item=sub key=sub_id}
                  <tr id="sid_{$sub_id}" class="opener3 tbl_out">
                    <td style="border-bottom: solid 1px #ccc">{$sub.name}</td>
                    <td style="border-bottom: solid 1px #ccc">{$sub.steam}</td>
                    <td style="border-bottom: solid 1px #ccc">
                      <a href="#" onclick="BanSubmission({$sub_id});">{$lang_to_ban}</a> -
                      {if $permission_edit_bans}
                      <a href="#" onclick="ArchiveSubmission({$sub_id}, '{$sub.steam}');">{$lang_to_archive}</a> -
                      <a href="#" onclick="DeleteSubmission({$sub_id}, '{$sub.steam}');">{$lang_delete}</a> -
                      {/if}
                      <a href="{build_url _=admin_bans_email.php email=$sub.email}">{$lang_contact}</a>
                    </td>
                  </tr>
                  <tr id="sid_{$sub_id}a">
                    <td colspan="3">
                      <div class="opener3" width="100%" align="center">
                        <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                          <tr>
                            <th colspan="3">{$lang_details}</th>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_name}</td>
                            <td class="listtable_1">{$sub.name}</td>
                            <td width="30%" rowspan="11" class="listtable_2 opener">
                              <ul class="ban-edit">
                                <li>{$sub.demo}</li>
                              </ul>
                            </td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Steam ID</td>
                            <td class="listtable_1">{$sub.steam}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_ip_address}</td>
                            <td class="listtable_1">
                              {if empty($sub.ip)}
                              <em class="not_applicable">no IP address present</em>
                              {else}
                              {$sub.ip}
                              {/if}
                            </td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_reason}</td>
                            <td class="listtable_1">{$sub.reason}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_server}</td>
                            <td class="listtable_1" id="host_{$sub.server_id}">Querying Server Data...</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Submitter</td>
                            <td class="listtable_1">{$sub.subname}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Submitter IP</td>
                            <td class="listtable_1">{$sub.subip}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_submitted_on}</td>
                            <td class="listtable_1">{$sub.time|date_format:$date_format}</td>
                          </tr>
                        </table>
                      </div>
                    </td>
                  </tr>
                  {/foreach}
                </table>
              </div>
              <div id="pane-submissions-archive">
                <h3>{$lang_archived_submissions|ucwords} (<span id="subcount">{$total_archived_submissions}</span>)</h3>
                <p>Click a player's nickname to view information about their submission</p>
                <table width="100%" cellpadding="0" cellspacing="0">
                  <tr>
                    <th width="35%">{$lang_name}</th>
                    <th>Steam ID</th>
                    <th>{$lang_action}</th>
                  </tr>
                  {foreach from=$archived_submissions item=sub key=sub_id}
                  <tr id="sid_{$sub_id}" class="opener3 tbl_out">
                    <td style="border-bottom: solid 1px #ccc">{$sub.name}</td>
                    <td style="border-bottom: solid 1px #ccc">{$sub.steam}</td>
                    <td style="border-bottom: solid 1px #ccc">
                      <a href="#" onclick="BanSubmission({$sub_id});">{$lang_to_ban}</a> -
                      {if $permission_edit_bans}
                      <a href="#" onclick="RestoreSubmission({$sub_id}, '{$sub.steam}');">{$lang_restore}</a> -
                      <a href="#" onclick="DeleteSubmission({$sub_id}, '{$sub.steam}');">{$lang_delete}</a> -
                      {/if}
                      <a href="{build_url _=admin_bans_email.php email=$sub.email}">{$lang_contact}</a>
                    </td>
                  </tr>
                  <tr id="sid_{$sub_id}a">
                    <td colspan="3">
                      <div class="opener3" width="100%" align="center">
                        <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                          <tr>
                            <th colspan="3">{$lang_details}</th>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_name}</td>
                            <td class="listtable_1">{$sub.name}</td>
                            <td width="30%" rowspan="11" class="listtable_2 opener">
                              <ul class="ban-edit">
                                <li>{$sub.demo}</li>
                              </ul>
                            </td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Steam ID</td>
                            <td class="listtable_1">{$sub.steam}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_ip_address}</td>
                            <td class="listtable_1">{$sub.ip}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_reason}</td>
                            <td class="listtable_1">{$sub.reason}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_server}</td>
                            <td class="listtable_1" id="host_{$sub.server}">Querying Server Data...</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Submitter</td>
                            <td class="listtable_1">{$sub.subname}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">Submitter IP</td>
                            <td class="listtable_1">{$sub.subip}</td>
                          </tr>
                          <tr>
                            <td width="20%" class="listtable_1">{$lang_submitted_on}</td>
                            <td class="listtable_1">{$sub.time|date_format:$date_format}</td>
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