          <h3>Please select an option to administer.</h3>
          <ul class="admin">
            {if $user_permission_admins}
            <li><a class="admins" href="{build_url _=admin_admins.php}">{$lang_admins}</a></li>
            {/if}
            {if $user_permission_bans}
            <li><a class="bans" href="{build_url _=admin_bans.php}">{$lang_bans}</a></li>
            {/if}
            {if $user_permission_groups}
            <li><a class="groups" href="{build_url _=admin_groups.php}">{$lang_groups}</a></li>
            {/if}
            {if $user_permission_mods}
            <li><a class="mods" href="{build_url _=admin_mods.php}">{$lang_mods}</a></li>
            {/if}
            {if $user_permission_servers}
            <li><a class="servers" href="{build_url _=admin_servers.php}">{$lang_servers}</a></li>
            {/if}
            {if $user_permission_settings}
            <li><a class="settings" href="{build_url _=admin_settings.php}">{$lang_settings}</a></li>
            {/if}
          </ul>
          <table width="100%" cellpadding="3" cellspacing="0">
            <tr>
              <td width="33%" align="center"><h3>{$lang_version_information}</h3></td>
              <td width="33%" align="center"><h3>{$lang_admin_information}</h3></td>
              <td width="33%" align="center"><h3>{$lang_ban_information}</h3></td>
            </tr>
            <tr>
              <td>{$lang_latest_release}: <strong id="relver">{$lang_please_wait}...</strong></td>
              <td>{$lang_total_admins}: <strong>{$total_admins}</strong></td>
              <td>{$lang_total_bans}: <strong>{$total_bans}</strong></td>
            </tr>
            <tr>
              <td>
                {if $sb_svn}
                Latest SVN: <strong id="svnrev">{$lang_please_wait}...</strong>
                {/if}
              </td>
              <td>&nbsp;</td>
              <td>{$lang_connection_blocks}: <strong>{$total_blocks}</strong></td>
            </tr>
            <tr>
              <td id="versionmsg">{$lang_please_wait}...</td>
              <td>&nbsp;</td>
              <td>{$lang_total_demo_size}: <strong>{$demosize}</strong></td>
            </tr>
            <tr>
              <td width="33%" align="center"><h3>{$lang_server_information}</h3></td>
              <td width="33%" align="center"><h3>{$lang_protest_information}</h3></td>
              <td width="33%" align="center"><h3>{$lang_submission_information}</h3></td>
            </tr>
            <tr>
              <td>{$lang_total_servers}: <strong>{$total_servers}</strong></td>
              <td>{$lang_total_protests}: <strong>{$total_protests}</strong></td>
              <td>{$lang_total_submissions}: <strong>{$total_submissions}</strong></td>
            </tr>
            <tr>
              <td>&nbsp;</td>
              <td>{$lang_archived_protests}: <strong>{$total_archived_protests}</strong></td>
              <td>{$lang_archived_submissions}: <strong>{$total_archived_submissions}</strong></td>
            </tr>
            <tr>
              <td colspan="3">&nbsp;</td>
            </tr>
          </table>