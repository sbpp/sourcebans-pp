<table style="width: 101%; margin: 0 0 -2px -2px;">
    <tr>
        <td colspan="3" class="listtable_top"><b>Please select an option to administer</b></td>
    </tr>
</table>
<div id="cpanel">
    <ul>
        {if $access_admins}
            <li>
                <a href="index.php?p=admin&amp;c=admins">
                    <i class="fas fa-user fa-5x"></i>
                    <br/><br/>
                    <b>Admin Settings</b>
                </a>
            </li>
        {/if}
        {if $access_servers}
            <li>
                <a href="index.php?p=admin&amp;c=servers">
                    <i class="fas fa-server fa-5x"></i>
                    <br/><br/>
                    <b>Server Settings</b>
                </a>
            </li>
        {/if}
        {if $access_bans}
            <li>
                <a href="index.php?p=admin&amp;c=bans">
                    <i class="fas fa-times fa-5x"></i>
                    <br/><br/>
                    <b>Bans</b>
                </a>
            </li>
        {/if}
        {if $access_bans}
            <li>
                <a href="index.php?p=admin&amp;c=comms">
                    <i class="fas fa-volume-off fa-5x"></i>
                    <br/><br/>
                    <b>Comms</b>
                </a>
            </li>
        {/if}
        {if $access_groups}
            <li>
                <a href="index.php?p=admin&amp;c=groups">
                    <i class="fas fa-users fa-5x"></i>
                    <br/><br/>
                    <b>Group Settings</b>
                </a>
            </li>
        {/if}
        {if $access_settings}
            <li>
                <a href="index.php?p=admin&amp;c=settings">
                    <i class="fas fa-cogs fa-5x"></i>
                    <br/><br/>
                    <b>Webpanel Settings</b>
                </a>
            </li>
        {/if}
        {if $access_mods}
            <li>
                <a href="index.php?p=admin&amp;c=mods">
                    <i class="fas fa-cubes fa-5x"></i>
                    <br/><br/>
                    <b>Manage Mods</b>
                </a>
            </li>
        {/if}
    </ul>
</div>
<br />

<table width="100%" border="0" cellpadding="3" cellspacing="0">
    <tr>
        <td width="33%" class="listtable_top" align="center" style="border-right: 3px solid #CFCAC6;">Version Information</td>
        <td width="33%" class="listtable_top" align="center" style="border-right: 3px solid #CFCAC6; border-left: 3px solid #CFCAC6;">Admin Information</td>
        <td width="33%" class="listtable_top" align="center" style="border-left: 3px solid #CFCAC6;">Ban Information</td>
    </tr>
    <tr>
        <td class="listtable_1" style="border-right: 3px solid #CFCAC6;">Latest release: <strong id='relver'>Please Wait...</strong></td>
        <td class="listtable_1" style="border-right: 3px solid #CFCAC6; border-left: 3px solid #CFCAC6;">Total admins: <strong>{$total_admins}</strong></td>
        <td class="listtable_1" style="border-left: 3px solid #CFCAC6;">Total bans: <strong>{$total_bans}</strong></td>
    </tr>
    <tr>
        <td class="listtable_1" style="border-right: 3px solid #CFCAC6;">
            {if $dev}
                Latest Git: <strong id='svnrev'>Please Wait...</strong>
            {/if}
        </td>
        <td class="listtable_1" style="border-right: 3px solid #CFCAC6; border-left: 3px solid #CFCAC6;">&nbsp;</td>
		<td class="listtable_1" style="border-left: 3px solid #CFCAC6;">Total comms: <strong>{$total_comms}</strong></td>
    </tr>
    <tr>
        <td class="listtable_1" id='versionmsg' style="border-right: 3px solid #CFCAC6;">Please Wait...</td>
        <td class="listtable_1" style="border-right: 3px solid #CFCAC6; border-left: 3px solid #CFCAC6;"><strong> </strong></td>
		<td class="listtable_1" style="border-left: 3px solid #CFCAC6;">Connections Blocked: <strong>{$total_blocks}</strong></td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
    </tr>
    <tr>
        <td width="33%" class="listtable_top" align="center" style="border-right: 3px solid #CFCAC6;">Server Information</td>
        <td width="33%" class="listtable_top" align="center" style="border-right: 3px solid #CFCAC6; border-left: 3px solid #CFCAC6;">Protest Information</td>
        <td width="33%" class="listtable_top" align="center" style="border-left: 3px solid #CFCAC6;">Submission Information</td>
    </tr>
    <tr>
        <td class="listtable_1" style="border-right: 3px solid #CFCAC6;">Total Servers: <strong>{$total_servers}</strong></td>
        <td class="listtable_1" style="border-right: 3px solid #CFCAC6; border-left: 3px solid #CFCAC6;">Pending Protests: <strong>{$total_protests}</strong></td>
        <td class="listtable_1" style="border-left: 3px solid #CFCAC6;">Pending Submissions: <strong>{$total_submissions}</strong></td>
    </tr>
    <tr>
        <td class="listtable_1" style="border-right: 3px solid #CFCAC6;">Total demo size: <strong>{$demosize}</td>
        <td class="listtable_1" style="border-right: 3px solid #CFCAC6; border-left: 3px solid #CFCAC6;">Archived Protests: <strong>{$archived_protests}</strong></td>
        <td class="listtable_1" style="border-left: 3px solid #CFCAC6;">Archived Submissions: <strong>{$archived_submissions}</strong></td>
    </tr>
    <tr>
        <td class="listtable_1" style="border-right: 3px solid #CFCAC6;">&nbsp;</td>
        <td class="listtable_1" style="border-right: 3px solid #CFCAC6; border-left: 3px solid #CFCAC6;">&nbsp;</td>
        <td class="listtable_1" style="border-left: 3px solid #CFCAC6;">&nbsp;</td>
    </tr>
</table>
<script type="text/javascript">xajax_CheckVersion();</script>
