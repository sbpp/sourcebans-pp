{if NOT $permissions_submissions}
    Access Denied!
{else}
    <h3 style="margin-top:0px;">Ban Submissions (<span id="subcount">{$submission_count}</span>)</h3>
    Click a player's nickname to view information about their submission<br /><br />
    <div id="banlist-nav">
        {* nofilter: submission_nav is server-built pagination from admin.bans.php with no $_GET interpolation in this branch *}
        {$submission_nav nofilter}
    </div>
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr  class="tbl_out">
            <td width="40%" height='16' class="listtable_top"><strong>Nickname</strong></td>
            <td width="20%" height='16' class="listtable_top"><strong>SteamID</strong></td>
            <td width="25%" height='16' class="listtable_top"><strong>Action</strong></td>
        </tr>
        {foreach from=$submission_list item="sub"}
            <tr id="sid_{$sub.subid}" class="opener3 tbl_out" {if $sub.hostname == ""}onclick="LoadServerHost('{$sub.server}', 'id', 'sub{$sub.subid}');"{/if} onmouseout="this.className='tbl_out'" onmouseover="this.className='tbl_hover'">
                {* nofilter: sub.name is wordwrap(htmlspecialchars($sub['name']), 55, "<br />", true) in admin.bans.php — already entity-escaped, only `<br />` reintroduced *}
                <td class="listtable_1" height='16'>{$sub.name nofilter}</td>
                <td class="listtable_1" height='16'>{if $sub.SteamId!=""}{$sub.SteamId}{else}{$sub.sip}{/if}</td>
                <td class="listtable_1" height='16'>
                    <a href="#" onclick="LoadSetupBan({$sub.subid});return false;">Ban</a> -
                    {if $permissions_editsub}
                        <a href="#" onclick="RemoveSubmission({$sub.subid}, '{$sub.name|smarty_stripslashes}', '1');return false;">Remove</a> -
                    {/if}
                    <a href="index.php?p=admin&c=bans&o=email&type=s&id={$sub.subid|escape:'url'}">Contact</a>
                </td>
            </tr>
            <tr id="sid_{$sub.subid}a">
                <td colspan="3">
                    <div class="opener3" width="100%" align="center">
                        <table width="90%" cellspacing="0" cellpadding="0" class="listtable">
                            <tr>
                                <td height="16" align="left" class="listtable_top" colspan="3">
                                    <b>Ban Details</b>
                                </td>
                            </tr>
                            <tr align="left">
                                <td width="20%" height="16" class="listtable_1">Player</td>
                                {* nofilter: see sub.name above — wordwrap(htmlspecialchars(...))-encoded in admin.bans.php *}
                                <td height="16" class="listtable_1">{$sub.name nofilter}</td>
                                <td width="30%" rowspan="9" class="listtable_2">
                                    <div class="ban-edit">
                                        <ul>
                                            {* nofilter: sub.demo is server-built `<a href="getdemo.php?id={URLENCODED INT}…">` HTML, sub.subaddcomment is CreateLinkR-built; no user input *}
                                            <li>{$sub.demo nofilter}</li>
                                            <li>{$sub.subaddcomment nofilter}</li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <tr align="left">
                                <td width="20%" height="16" class="listtable_1">Submitted</td>
                                <td height="16" class="listtable_1">{$sub.submitted}</td>
                            </tr>
                            <tr align="left">
                                <td width="20%" height="16" class="listtable_1">SteamID</td>
                                <td height="16" class="listtable_1">
                                    {if $sub.SteamId == ""}
                                        <i><font color="#677882">no steamid present</font></i>
                                    {else}
                                        {$sub.SteamId}
                                    {/if}
                                </td>
                            </tr>
                            <tr align="left">
                                <td width="20%" height="16" class="listtable_1">IP</td>
                                <td height="16" class="listtable_1">
                                    {if $sub.sip == ""}
                                        <i><font color="#677882">no ip address present</font></i>
                                    {else}
                                        {$sub.sip}
                                    {/if}
                                </td>
                            </tr>
                            <tr align="left">
                                <td width="20%" height="16" class="listtable_1">Reason</td>
                                {* nofilter: sub.reason is wordwrap(htmlspecialchars($sub['reason']), 55, "<br />", true) in admin.bans.php — already entity-escaped, only `<br />` reintroduced *}
                                <td height="" class="listtable_1">{$sub.reason nofilter}</td>
                            </tr>
                            <tr align="left">
                                <td width="20%" height="16" class="listtable_1">Server</td>
                                {* nofilter: sub.hostname is either "" or the static `<i><font color="#677882">Other server...</font></i>` literal in admin.bans.php — server-controlled, no user input *}
                                <td height="" class="listtable_1" id="sub{$sub.subid}">{if $sub.hostname == ""}<i>Retrieving Hostname</i>{else}{$sub.hostname nofilter}{/if}</td>
                            </tr>
                            <tr align="left">
                                <td width="20%" height="16" class="listtable_1">MOD</td>
                                <td height="" class="listtable_1">{$sub.mod}</td>
                            </tr>
                            <tr align="left">
                                <td width="20%" height="16" class="listtable_1">Submitter Name</td>
                                <td height="" class="listtable_1">
                                    {if $sub.subname == ""}
                                        <i><font color="#677882">no name present</font></i>
                                    {else}
                                        {$sub.subname}
                                    {/if}
                                </td>
                            </tr>
                            <tr align="left">
                                <td width="20%" height="16" class="listtable_1">Submitter IP</td>
                                <td height="" class="listtable_1">{$sub.ip}</td>
                            </tr>
                            <tr align="left">
                                <td width="20%" height="16" class="listtable_1">Comments</td>
                                <td height="60" class="listtable_1" colspan="3">
                                    {if $sub.commentdata != "None"}
                                        <table width="100%" border="0">
                                            {foreach from=$sub.commentdata item=commenta}
                                                {if $commenta.morecom}
                                                    <tr>
                                                        <td colspan="3">
                                                            <hr />
                                                        </td>
                                                    </tr>
                                                {/if}
                                                <tr>
                                                    <td>
                                                        {if !empty($commenta.comname)}
                                                            <b>{$commenta.comname}</b>
                                                        {else}
                                                            <i><font color="#677882">Admin deleted</font></i>
                                                        {/if}
                                                    </td><td align="right"><b>{$commenta.added}</b>
                                                    </td>
                                                    {if $commenta.editcomlink != ""}
                                                        <td align="right">
                                                            {* nofilter: editcomlink/delcomlink are CreateLinkR-built HTML / static `<a … onclick="RemoveComment(INT,'S',-1)">` from admin.bans.php with integer cid + literal subid, no user input *}
                                                            {$commenta.editcomlink nofilter} {$commenta.delcomlink nofilter}
                                                        </td>
                                                    {/if}
                                                </tr>
                                                <tr>
                                                    <td colspan="2" style="word-break: break-all;word-wrap: break-word;">
                                                        {* nofilter: commenttxt passes through encodePreservingBr (htmlspecialchars per-segment, only `<br/>` survives) + URL-wrap regex on already-escaped text in admin.bans.php *}
                                                        {$commenta.commenttxt nofilter}
                                                    </td>
                                                </tr>
                                                {if !empty($commenta.edittime)}
                                                    <tr>
                                                        <td colspan="3">
                                                            <span style="font-size:6pt;color:grey;">last edit {$commenta.edittime} by {if !empty($commenta.editname)}{$commenta.editname}{else}<i><font color="#677882">Admin deleted</font></i>{/if}</span>
                                                        </td>
                                                    </tr>
                                                {/if}
                                            {/foreach}
                                        </table>
                                    {/if}
                                    {if $sub.commentdata == "None"}
                                        {$sub.commentdata}
                                    {/if}
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        {/foreach}
    </table>
    <script>InitAccordion('tr.opener3', 'div.opener3', 'mainwrapper');</script>
{/if}
