          <div id="admin-page-menu">
            <ul>
              <li id="tab-settings"><a href="#settings">{$lang_settings}</a></li>
              <li id="tab-plugins"><a href="#plugins">Plugins</a></li>
              <li id="tab-themes"><a href="#themes">{$lang_themes}</a></li>
              <li id="tab-logs"><a href="#logs">{$lang_system_log}</a></li>
              {foreach from=$admin_tabs item=tab}
              <li{if !empty($tab.id)} id="tab-{$tab.id}"{/if}><a href="{$tab.url}">{$tab.name}</a></li>
              {/foreach}
            </ul>
            <br />
            <div align="center">
              <img alt="{$page_title}" src="themes/{$theme_dir}/images/admin/settings.png" title="{$page_title}" />
            </div>
          </div>
          <div id="admin-page-content">
            <form action="{$active}" id="pane-settings" method="post">
              <fieldset>
                <h3>Main Settings</h3>
                <p>{$lang_help_desc}</p>
                <div>
                  <label for="password_minlength">{help_icon title="Min password length" desc="Define the shortest length a password can be."}Min password length</label>
                  <input class="submit-fields" {nid id="password_minlength"} value="{$config_min_password}" />
                </div>
                <div>
                  <label for="dateformat">{help_icon title="Date format" desc="Here you can change the date format, displayed in the banlist and other pages."}Date format</label>
                  <input class="submit-fields" {nid id="dateformat"} value="{$config_dateformat}" />
                  <a href="http://www.php.net/strftime">See: PHP strftime()</a>
                </div>
                <div>
                  <label for="language">{help_icon title="$lang_language" desc="Choose your language here."}{$lang_language}</label>
                  <select class="submit-fields" {nid id="language"}>
                    {foreach from=$languages item=language}
                    <option{if $language.code == $user_language} selected="selected"{/if} value="{$language.code}">{$language.name}</option>
                    {/foreach}
                  </select>
                </div>
                <div>
                  <label for="timezone">{help_icon title="Timezone" desc="Here you can change the default timezone that SourceBans displays times in"}{$lang_timezone}</label>
                  <select {nid id="timezone"}>
                    <option value="-12"{if $config_timezone == -12} selected="selected"{/if}>(GMT -12:00) Eniwetok, Kwajalein</option>
                    <option value="-11"{if $config_timezone == -11} selected="selected"{/if}>(GMT -11:00) Midway Island, Samoa</option>
                    <option value="-10"{if $config_timezone == -10} selected="selected"{/if}>(GMT -10:00) Hawaii</option>
                    <option value="-9"{if $config_timezone == -9} selected="selected"{/if}>(GMT -9:00) Alaska</option>
                    <option value="-8"{if $config_timezone == -8} selected="selected"{/if}>(GMT -8:00) Pacific Time (US &amp; Canada)</option>
                    <option value="-7"{if $config_timezone == -7} selected="selected"{/if}>(GMT -7:00) Mountain Time (US &amp; Canada)</option>
                    <option value="-6"{if $config_timezone == -6} selected="selected"{/if}>(GMT -6:00) Central Time (US &amp; Canada), Mexico City</option>
                    <option value="-5"{if $config_timezone == -5} selected="selected"{/if}>(GMT -5:00) Eastern Time (US &amp; Canada), Bogota, Lima</option>
                    <option value="-4"{if $config_timezone == -4} selected="selected"{/if}>(GMT -4:00) Atlantic Time (Canada), Caracas, La Paz</option>
                    <option value="-3.5"{if $config_timezone == -3.5} selected="selected"{/if}>(GMT -3:30) Newfoundland</option>
                    <option value="-3"{if $config_timezone == -3} selected="selected"{/if}>(GMT -3:00) Brazil, Buenos Aires, Georgetown</option>
                    <option value="-2"{if $config_timezone == -2} selected="selected"{/if}>(GMT -2:00) Mid-Atlantic</option>
                    <option value="-1"{if $config_timezone == -1} selected="selected"{/if}>(GMT -1:00 hour) Azores, Cape Verde Islands</option>
                    <option value="0"{if !$config_timezone} selected="selected"{/if}>(GMT) Western Europe Time, London, Lisbon, Casablanca</option>
                    <option value="1"{if $config_timezone == 1} selected="selected"{/if}>(GMT +1:00) Brussels, Copenhagen, Madrid, Paris</option>
                    <option value="2"{if $config_timezone == 2} selected="selected"{/if}>(GMT +2:00) Kaliningrad, South Africa</option>
                    <option value="3"{if $config_timezone == 3} selected="selected"{/if}>(GMT +3:00) Baghdad, Riyadh, Moscow, St. Petersburg</option>
                    <option value="3.5"{if $config_timezone == 3.5} selected="selected"{/if}>(GMT +3:30) Tehran</option>
                    <option value="4"{if $config_timezone == 4} selected="selected"{/if}>(GMT +4:00) Abu Dhabi, Muscat, Baku, Tbilisi</option>
                    <option value="4.5"{if $config_timezone == 4.5} selected="selected"{/if}>(GMT +4:30) Kabul</option>
                    <option value="5"{if $config_timezone == 5} selected="selected"{/if}>(GMT +5:00) Ekaterinburg, Islamabad, Karachi, Tashkent</option>
                    <option value="5.5"{if $config_timezone == 5.5} selected="selected"{/if}>(GMT +5:30) Bombay, Calcutta, Madras, New Delhi</option>
                    <option value="6"{if $config_timezone == 6} selected="selected"{/if}>(GMT +6:00) Almaty, Dhaka, Colombo</option>
                    <option value="7"{if $config_timezone == 7} selected="selected"{/if}>(GMT +7:00) Bangkok, Hanoi, Jakarta</option>
                    <option value="8"{if $config_timezone == 8} selected="selected"{/if}>(GMT +8:00) Beijing, Perth, Singapore, Hong Kong</option>
                    <option value="9"{if $config_timezone == 9} selected="selected"{/if}>(GMT +9:00) Tokyo, Seoul, Osaka, Sapporo, Yakutsk</option>
                    <option value="9.5"{if $config_timezone == 9.5} selected="selected"{/if}>(GMT +9:30) Adelaide, Darwin</option>
                    <option value="10"{if $config_timezone == 10} selected="selected"{/if}>(GMT +10:00) Eastern Australia, Guam, Vladivostok</option>
                    <option value="11"{if $config_timezone == 11} selected="selected"{/if}>(GMT +11:00) Magadan, Solomon Islands, New Caledonia</option>
                    <option value="12"{if $config_timezone == 12} selected="selected"{/if}>(GMT +12:00) Auckland, Wellington, Fiji, Kamchatka</option>
                  </select>
                </div>
                <div>
                  <label for="summertime">{help_icon title="$lang_summer_time" desc="Check this box to enable summer time."}{$lang_summer_time}</label>
                  <input{if $config_summertime} checked="checked"{/if} {nid id="summertime"} type="checkbox" value="1" />
                </div>
                <div>
                  <label for="debug">{help_icon title="Debug Mode" desc="Check this box to enable debug mode permanently."}Debug Mode</label>
                  <input{if $config_debug} checked="checked"{/if} {nid id="debug"} type="checkbox" value="1" />
                </div>
                <h3>Dashboard Settings</h3>
                <div>
                  <label for="intro_title">{help_icon title="Intro Title" desc="Set the title for the dashboard introduction."}Intro Title</label>
                  <input class="submit-fields" {nid id="intro_title"} value="{$config_dash_title}" />
                </div>
                <div>
                  <label for="intro_text">{help_icon title="Intro Text" desc="Set the text for the dashboard introduction."}Intro Text</label>
                  <textarea cols="80" {nid id="intro_text"} rows="20">{$config_dash_text}</textarea>
                  <a class="toggle_mce" href="#" rel="intro_text">Enable/Disable WYSIWYG editor</a>
                </div>
                <div>
                  <label for="log_nopopup">{help_icon title="Disable Log Popup" desc="Check this box to disable the log info popup and use a direct link."}Disable Log Popup</label>
                  <input{if $config_nopopup} checked="checked"{/if} {nid id="log_nopopup"} type="checkbox" value="1" />
                </div>
                <h3>Page Settings</h3>
                <div>
                  <label for="enable_protest">{help_icon title="Enable Protest Ban" desc="Check this box to enable the protest ban page."}Enable Protest Ban</label>
                  <input{if $config_enableprotest} checked="checked"{/if} {nid id="enable_protest"} type="checkbox" value="1" />
                </div>
                <div>
                  <label for="enable_submit">{help_icon title="Enable Submit Ban" desc="Check this box to enable the submit ban page."}Enable Submit Ban</label>
                  <input{if $config_enablesubmit} checked="checked"{/if} {nid id="enable_submit"} type="checkbox" value="1" />
                </div>
                <div>
                  <label for="default_page">{help_icon title="Default Page" desc="Choose the page that will be the first page people will see."}Default Page</label>
                  <select class="inputbox" {nid id="default_page"}>
                    <option value="0">{$lang_dashboard|ucwords}</option>
                    <option value="1">{$lang_ban_list|ucwords}</option>
                    <option value="2">{$lang_servers|ucwords}</option>
                    <option value="3">{$lang_submit_ban|ucwords}</option>
                    <option value="4">{$lang_protest_ban|ucwords}</option>
                  </select>
                </div>
                <div>
                  <label for="clear_cache">{help_icon title="Clear Cache" desc="Click this button to empty the themes_c folder."}Clear Cache</label>
                  <input class="btn cancel" {nid id="clear_cache"} type="button" value="Clear Cache" />
                </div>
                <div id="clear_cache.msg"></div>
                <h3>Banlist Settings</h3>
                <div>
                  <label for="bansperpage">{help_icon title="Items per page" desc="Choose how many items to show on each page."}Items Per Page</label>
                  <input class="submit-fields" {nid id="bansperpage"} value="{$config_bansperpage}" />
                </div>
                <div>
                  <label for="export_public">{help_icon title="Enable Public Bans Export" desc="Check this box to enable the entire ban list to be publically downloaded and shared."}Enable Public Bans Export</label>
                  <input{if $config_exportpublic} checked="checked"{/if} {nid id="export_public"} type="checkbox" value="1" />
                </div>
                <div>
                  <label for="hide_adminname">{help_icon title="Hide Admin Name" desc="Check this box, if you want to hide the name of the admin on the ban list."}Hide Admin Name</label>
                  <input{if $config_hideadminname} checked="checked"{/if} {nid id="hide_adminname"} type="checkbox" value="1" />
                </div>
                <h3>E-mail Settings</h3>
                <div>
                  <label for="enable_smtp">{help_icon title="Enable SMTP" desc="Check this box to enable SMTP "}Enable SMTP</label>
                  <input{if $config_enablesmtp} checked="checked"{/if} {nid id="enable_smtp"} type="checkbox" value="1" />
                </div>
                <div>
                  <label for="smtp_host">{help_icon title="SMTP Host" desc="Fill in your SMTP host here."}SMTP Host</label>
                  <input class="submit-fields" {nid id="smtp_host"} value="{$config_smtp_host}" />
                </div>
                <div>
                  <label for="smtp_port">{help_icon title="SMTP Port" desc="Fill in your SMTP port here."}SMTP Port</label>
                  <input class="submit-fields" {nid id="smtp_port"} value="{$config_smtp_port}" />
                </div>
                <div>
                  <label for="smtp_username">{help_icon title="SMTP Username" desc="Fill in your SMTP username here."}SMTP Username</label>
                  <input class="submit-fields" {nid id="smtp_username"} value="{$config_smtp_username}" />
                </div>
                <div>
                  <label for="smtp_password">{help_icon title="SMTP Password" desc="Fill in your SMTP password here."}SMTP Password</label>
                  <input class="submit-fields" {nid id="smtp_password"} value="{$config_smtp_password}" />
                </div>
                <div>
                  <label for="smtp_secure">{help_icon title="SMTP Secure" desc="Select the type of SMTP secure authentication here."}SMTP Secure</label>
                  <select class="submit-fields" {nid id="smtp_secure"}>
                    <option value="">{$lang_none}</option>
                    <option{if $config_smtp_secure == "ssl"} selected="selected"{/if} value="ssl">SSL</option>
                    <option{if $config_smtp_secure == "tls"} selected="selected"{/if} value="tls">TLS</option>
                  </select>
                </div>
                <div class="center">
                  <input name="action" type="hidden" value="settings" />
                  <input class="btn ok" type="submit" value="{$lang_save}" />
                  <input class="back btn cancel" type="button" value="{$lang_back}" />
                </div>
              </fieldset>
            </form>
            <form action="{$active}" id="pane-plugins" method="post">
              <fieldset>
                <h3>Plugins</h3>
                <table class="listtable">
                  <tr>
                    <th>Plugin</th>
                    <th class="center" width="75">{$lang_enabled}</th>
                    <th class="center" width="75">{$lang_disabled}</th>
                  </tr>
                  {foreach from=$plugins item=plugin key=class}
                  <tr>
                    <td class="listtable_1" style="padding: 5px">
                      <a class="flRight italic" href="{$plugin.url}">{$plugin.author}</a>
                      <span class="underline">{$plugin.name} <strong>{$plugin.version}</strong></span>
                      <div style="text-align: justify">{$plugin.desc}</div>
                    </td>
                    <td class="center listtable_1"><input{if $plugin.enabled} checked="checked"{/if} name="{$class}" type="radio" value="1" /></td>
                    <td class="center listtable_1"><input{if !$plugin.enabled} checked="checked"{/if} name="{$class}" type="radio" value="0" /></td>
                  </tr>
                  {/foreach}
                </table>
                <div class="center">
                  <input name="action" type="hidden" value="plugins" />
                  <input class="btn ok" type="submit" value="{$lang_save}" />
                  <input class="back btn cancel" type="button" value="{$lang_back}" />
                </div>
              </fieldset>
            </form>
            <div id="pane-themes">
              <h3>{$lang_themes}</h3>
              <div id="current-theme-holder">
                <h4 class="largetitle">Selected Theme: <span id="theme.name">{$theme_name}</span></h4>
                <img alt="{$theme_name}" id="current-theme-screenshot" src="themes/{$theme_dir}/screenshot.jpg" title="{$theme_name}" />
                <div id="current-theme-details">
                  <p>
                    <strong>{$lang_author}:</strong>
                    <br /><span id="theme_author">{$theme_author}</span>
                  </p>
                  <p>
                    <strong>{$lang_version}:</strong>
                    <br /><span id="theme_version">{$theme_version}</span>
                  </p>
                  <p>
                    <strong>{$lang_link}:</strong>
                    <br /><a href="{$theme_link}" id="theme_link">{$theme_link}</a>
                  </p>
                  <input class="btn flRight ok" {nid id="theme_apply"} type="button" value="Apply Theme" />
                </div>
              </div>
              <br />
              <h4 class="largetitle">Available Themes</h4>
              <p>Click a theme below to see details about it.</p>
              <ul id="theme-list">
                {foreach from=$themes item=theme}
                <li><a class="select_theme" href="#" rel="{$theme.dir}">{$theme.name}</a></li>
                {/foreach}
              </ul>
            </div>
            <div id="pane-logs">
              <h3>{$lang_system_log}{if $permission_clear_logs} ( <a id="clear_logs" href="#">Clear Log</a> ){/if}</h3>
              Click on a row to see more details about the event.
              <br /><br />
              <div align="center">
                <table width="80%" cellpadding="0" class="listtable" cellspacing="0">
                  <tr class="sea_open">
                    <th colspan="3"><strong>{$lang_advanced_search}</strong> ({$lang_click})</th>
                  </tr>
                  <tr>
                    <td>
                      <form action="{$active}" class="panel" method="get">
                        <table width="100%" cellpadding="0" class="listtable" cellspacing="0">
                          <tr>
                            <td class="listtable_1" width="8%" align="center"><input id="admin_" name="type" type="radio" value="admin" /></td>
                            <td class="listtable_1" width="26%">{$lang_admin}</td>
                            <td class="listtable_1" width="66%">
                              <select id="admin" onmouseup="$('admin_').checked = true" class="sea_inputbox" style="width: 251px;">
                                <option value="0">Guest</option>
                                {foreach from=$admins item=admin key=admin_id}
                                <option value="{$admin_id}">{$admin.name}</option>
                                {/foreach}
                              </select>
                            </td>
                          </tr>
                          <tr>
                            <td class="listtable_1" align="center"><input id="message_" name="type" type="radio" value="message" /></td>
                            <td class="listtable_1">Event</td>
                            <td class="listtable_1"><input id="message" value="" onmouseup="$('message_').checked = true" class="sea_inputbox" style="width: 249px;" /></td>
                          </tr>
                          <tr>
                            <td align="center" class="listtable_1"><input id="date_" type="radio" name="type" value="date" /></td>
                            <td class="listtable_1">{$lang_date}</td>
                            <td class="listtable_1">
                              <input id="day" value="DD" onmouseup="$('date_').checked = true" class="sea_inputbox" style="width: 25px;">.<input id="month" value="MM" onmouseup="$('date_').checked = true" class="sea_inputbox" style="width: 25px;">.<input id="year" value="YYYY" onmouseup="$('date_').checked = true" class="sea_inputbox" style="width: 40px;">
                              &nbsp;<input id="fhour" value="00" onmouseup="$('date_').checked = true" class="sea_inputbox" style="width: 25px;">:<input id="fminute" value="00" onmouseup="$('date_').checked = true" class="sea_inputbox" style="width: 25px;">
                              -&nbsp;<input id="thour" value="23" onmouseup="$('date_').checked = true" class="sea_inputbox" style="width: 25px;">:<input id="tminute" value="59" onmouseup="$('date_').checked = true" class="sea_inputbox" style="width: 25px;">
                            </td>
                          </tr>
                          <tr>
                            <td align="center" class="listtable_1"><input id="type_" type="radio" name="type" value="type" /></td>
                            <td class="listtable_1">{$lang_type}</td>
                            <td class="listtable_1">
                              <select id="type" onmouseup="$('type_').checked = true" class="sea_inputbox" style="width: 251px;">
                                <option value="{$smarty.const.ERROR_LOG_TYPE}">{$lang_error}</option>
                                <option selected="selected" value="{$smarty.const.INFO_LOG_TYPE}">{$lang_information}</option>
                                <option value="{$smarty.const.WARNING_LOG_TYPE}">{$lang_warning}</option>
                              </select>
                            </td>
                          </tr>
                          <tr>
                            <td align="center" colspan="3"><input class="btn ok" type="submit" value="{$lang_search}" /></td>
                          </tr>
                        </table>
                      </form>
                    </td>
                  </tr>
                </table>
              </div>
              <div id="banlist-nav">
                {eval var=$lang_displaying_results}
                {if $total_pages > 1}
                <select onchange="window.location = 'admin_settings.php?page=' + this.options[this.selectedIndex].value;">
                  {section loop=$total_pages name=page}
                  <option{if $smarty.get.page == $smarty.section.page.iteration} selected="selected"{/if} value="{$smarty.section.page.iteration}">{$smarty.section.page.iteration}</option>
                  {/section}
                </select>
                {/if}
              </div>
              <br /><br />
              <table width="100%" cellspacing="0" cellpadding="0" align="center" class="listtable">
                <tr>
                  <th width="5%" align="center">{$lang_type}</th>
                  <th width="28%" align="center">Event</th>
                  <th width="28%" align="center">{$lang_admin}</th>
                  <th>{$lang_date}/{$lang_time}</th>
                </tr>
                {foreach from=$logs item=log}
                <tr class="opener tbl_out">
                  <td align="center" class="listtable_1">
                    {if $log.type     == $smarty.const.ERROR_LOG_TYPE}
                    <img alt="{$lang_error}" src="themes/{$theme_dir}/images/admin/error.png" title="{$lang_error}" />
                    {elseif $log.type == $smarty.const.INFO_LOG_TYPE}
                    <img alt="{$lang_information}" src="themes/{$theme_dir}/images/admin/help.png" title="{$lang_information}" />
                    {elseif $log.type == $smarty.const.WARNING_LOG_TYPE}
                    <img alt="{$lang_warning}" src="themes/{$theme_dir}/images/admin/warning.png" title="{$lang_warning}" />
                    {/if}
                  </td>
                  <td class="listtable_1">{$log.title}</td>
                  <td class="listtable_1">{$log.admin_name}</td>
                  <td class="listtable_1">{$log.date}</td>
                </tr>
                <tr>
                  <td colspan="7" align="center">
                    <div class="opener">
                      <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                        <tr>
                          <th colspan="3">{$lang_details}</th>
                        </tr>
                        <tr>
                          <td class="listtable_1" width="20%">Message</td>
                          <td class="listtable_1">{$log.message}</td>
                        </tr>
                        <tr>
                          <td class="listtable_1">Parent Function</td>
                          <td class="listtable_1">{$log.function}</td>
                        </tr>
                        <tr>
                          <td class="listtable_1">Query String</td>
                          <td class="listtable_1">{$log.query}</td>
                        </tr>
                        <tr>
                          <td class="listtable_1">{$lang_ip_address}</td>
                          <td class="listtable_1">{$log.admin_ip}</td>
                        </tr>
                      </table>
                    </div>
                  </td>
                </tr>
                {/foreach}
              </table>
            </div>
            {foreach from=$admin_panes item=pane}
            <div id="pane-{$pane.id}">
              {$pane.html}
            </div>
            {/foreach}
          </div>