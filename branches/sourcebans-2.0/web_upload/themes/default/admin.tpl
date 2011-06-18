          <h3>Please select an option to administer.</h3>
          <ul class="admin">
            {if $user->permission_admins}
            <li><a class="admins" href="{build_uri controller=admin action=admins}">{$language->admins}</a></li>
            {/if}
            {if $user->permission_bans}
            <li><a class="bans" href="{build_uri controller=admin action=bans}">{$language->bans}</a></li>
            {/if}
            {if $user->permission_groups}
            <li><a class="groups" href="{build_uri controller=admin action=groups}">{$language->groups}</a></li>
            {/if}
            {if $user->permission_servers}
            <li><a class="servers" href="{build_uri controller=admin action=servers}">{$language->servers}</a></li>
            {/if}
            {if $user->permission_games}
            <li><a class="games" href="{build_uri controller=admin action=games}">{$language->games}</a></li>
            {/if}
            {if $user->permission_settings}
            <li><a class="settings" href="{build_uri controller=admin action=settings}">{$language->settings}</a></li>
            {/if}
          </ul>
          <table width="100%" cellpadding="3" cellspacing="0">
            <tr>
              <td width="33%" align="center"><h3>{$language->version_information}</h3></td>
              <td width="33%" align="center"><h3>{$language->admin_information}</h3></td>
              <td width="33%" align="center"><h3>{$language->ban_information}</h3></td>
            </tr>
            <tr>
              <td>{$language->latest_release}: <strong id="relver">{$language->please_wait}...</strong></td>
              <td>{$language->total_admins}: <strong>{$total_admins}</strong></td>
              <td>{$language->total_bans}: <strong>{$total_bans}</strong></td>
            </tr>
            <tr>
              <td>
                {if $sb_svn}
                Latest SVN: <strong id="svnrev">{$language->please_wait}...</strong>
                {/if}
              </td>
              <td>&nbsp;</td>
              <td>{$language->connection_blocks}: <strong>{$total_blocks}</strong></td>
            </tr>
            <tr>
              <td id="versionmsg">{$language->please_wait}...</td>
              <td>&nbsp;</td>
              <td>{$language->total_demo_size}: <strong>{$demosize}</strong></td>
            </tr>
            <tr>
              <td width="33%" align="center"><h3>{$language->server_information}</h3></td>
              <td width="33%" align="center"><h3>{$language->protest_information}</h3></td>
              <td width="33%" align="center"><h3>{$language->submission_information}</h3></td>
            </tr>
            <tr>
              <td>{$language->total_servers}: <strong>{$total_servers}</strong></td>
              <td>{$language->total_protests}: <strong>{$total_protests}</strong></td>
              <td>{$language->total_submissions}: <strong>{$total_submissions}</strong></td>
            </tr>
            <tr>
              <td>&nbsp;</td>
              <td>{$language->archived_protests}: <strong>{$total_archived_protests}</strong></td>
              <td>{$language->archived_submissions}: <strong>{$total_archived_submissions}</strong></td>
            </tr>
            <tr>
              <td colspan="3">&nbsp;</td>
            </tr>
          </table>