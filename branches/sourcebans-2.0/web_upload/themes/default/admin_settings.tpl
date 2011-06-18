          <div id="admin-page-menu">
            <ul>
              <li id="tab-settings"><a href="#settings">{$language->settings}</a></li>
              <li id="tab-plugins"><a href="#plugins">Plugins</a></li>
              <li id="tab-themes"><a href="#themes">{$language->themes}</a></li>
              <li id="tab-logs"><a href="#logs">{$language->system_log}</a></li>
              {foreach from=$admin_tabs item=tab}
              <li{if isset($tab->id)} id="tab-{$tab->id}"{/if}><a href="{$tab->uri}">{$tab->name}</a></li>
              {/foreach}
            </ul>
            <br />
            <div class="center">
              <img alt="{$action_title}" src="{$uri->base}/themes/{$theme}/images/admin/settings.png" title="{$action_title}" />
            </div>
          </div>
          <div id="admin-page-content">
            <form action="" id="pane-settings" method="post">
              <fieldset>
                <h3>{$language->general}</h3>
                <p>{$language->help_desc}</p>
                <div>
                  <label for="password_min_length">{help_icon title="Min password length" desc="Define the shortest length a password can be."}Min password length</label>
                  <input class="submit-fields" {nid id="password_min_length"} value="{$settings->password_min_length}" />
                </div>
                <div>
                  <label for="date_format">{help_icon title="`$language->date_format`" desc="Here you can change the date format, displayed in the bans and other pages."}{$language->date_format}</label>
                  <input class="submit-fields" {nid id="date_format"} value="{$settings->date_format}" />
                  <a href="http://www.php.net/strftime">See: PHP strftime()</a>
                </div>
                <div>
                  <label for="language">{help_icon title="`$language->language`" desc="`$language->language_desc`"}{$language->language}</label>
                  <select class="submit-fields" {nid id="language"}>
                    {foreach from=$languages item=_language}
                    <option{if $settings->language == $_language} selected="selected"{/if} value="{$_language}">{$_language->getInfo('name')}</option>
                    {/foreach}
                  </select>
                </div>
                <div>
                  <label for="timezone">{help_icon title="`$language->timezone`" desc="Here you can change the default timezone that SourceBans displays times in"}{$language->timezone}</label>
                  <select {nid id="timezone"}>
                    <option value="-12"{if $settings->timezone == -12} selected="selected"{/if}>(GMT -12:00) Eniwetok, Kwajalein</option>
                    <option value="-11"{if $settings->timezone == -11} selected="selected"{/if}>(GMT -11:00) Midway Island, Samoa</option>
                    <option value="-10"{if $settings->timezone == -10} selected="selected"{/if}>(GMT -10:00) Hawaii</option>
                    <option value="-9"{if $settings->timezone == -9} selected="selected"{/if}>(GMT -9:00) Alaska</option>
                    <option value="-8"{if $settings->timezone == -8} selected="selected"{/if}>(GMT -8:00) Pacific Time (US &amp; Canada)</option>
                    <option value="-7"{if $settings->timezone == -7} selected="selected"{/if}>(GMT -7:00) Mountain Time (US &amp; Canada)</option>
                    <option value="-6"{if $settings->timezone == -6} selected="selected"{/if}>(GMT -6:00) Central Time (US &amp; Canada), Mexico City</option>
                    <option value="-5"{if $settings->timezone == -5} selected="selected"{/if}>(GMT -5:00) Eastern Time (US &amp; Canada), Bogota, Lima</option>
                    <option value="-4"{if $settings->timezone == -4} selected="selected"{/if}>(GMT -4:00) Atlantic Time (Canada), Caracas, La Paz</option>
                    <option value="-3.5"{if $settings->timezone == -3.5} selected="selected"{/if}>(GMT -3:30) Newfoundland</option>
                    <option value="-3"{if $settings->timezone == -3} selected="selected"{/if}>(GMT -3:00) Brazil, Buenos Aires, Georgetown</option>
                    <option value="-2"{if $settings->timezone == -2} selected="selected"{/if}>(GMT -2:00) Mid-Atlantic</option>
                    <option value="-1"{if $settings->timezone == -1} selected="selected"{/if}>(GMT -1:00 {$language->hour}) Azores, Cape Verde Islands</option>
                    <option value="0"{if !$settings->timezone} selected="selected"{/if}>(GMT) Western Europe Time, London, Lisbon, Casablanca</option>
                    <option value="1"{if $settings->timezone == 1} selected="selected"{/if}>(GMT +1:00) Brussels, Copenhagen, Madrid, Paris</option>
                    <option value="2"{if $settings->timezone == 2} selected="selected"{/if}>(GMT +2:00) Kaliningrad, South Africa</option>
                    <option value="3"{if $settings->timezone == 3} selected="selected"{/if}>(GMT +3:00) Baghdad, Riyadh, Moscow, St. Petersburg</option>
                    <option value="3.5"{if $settings->timezone == 3.5} selected="selected"{/if}>(GMT +3:30) Tehran</option>
                    <option value="4"{if $settings->timezone == 4} selected="selected"{/if}>(GMT +4:00) Abu Dhabi, Muscat, Baku, Tbilisi</option>
                    <option value="4.5"{if $settings->timezone == 4.5} selected="selected"{/if}>(GMT +4:30) Kabul</option>
                    <option value="5"{if $settings->timezone == 5} selected="selected"{/if}>(GMT +5:00) Ekaterinburg, Islamabad, Karachi, Tashkent</option>
                    <option value="5.5"{if $settings->timezone == 5.5} selected="selected"{/if}>(GMT +5:30) Bombay, Calcutta, Madras, New Delhi</option>
                    <option value="6"{if $settings->timezone == 6} selected="selected"{/if}>(GMT +6:00) Almaty, Dhaka, Colombo</option>
                    <option value="7"{if $settings->timezone == 7} selected="selected"{/if}>(GMT +7:00) Bangkok, Hanoi, Jakarta</option>
                    <option value="8"{if $settings->timezone == 8} selected="selected"{/if}>(GMT +8:00) Beijing, Perth, Singapore, Hong Kong</option>
                    <option value="9"{if $settings->timezone == 9} selected="selected"{/if}>(GMT +9:00) Tokyo, Seoul, Osaka, Sapporo, Yakutsk</option>
                    <option value="9.5"{if $settings->timezone == 9.5} selected="selected"{/if}>(GMT +9:30) Adelaide, Darwin</option>
                    <option value="10"{if $settings->timezone == 10} selected="selected"{/if}>(GMT +10:00) Eastern Australia, Guam, Vladivostok</option>
                    <option value="11"{if $settings->timezone == 11} selected="selected"{/if}>(GMT +11:00) Magadan, Solomon Islands, New Caledonia</option>
                    <option value="12"{if $settings->timezone == 12} selected="selected"{/if}>(GMT +12:00) Auckland, Wellington, Fiji, Kamchatka</option>
                  </select>
                </div>
                <div>
                  <label for="summer_time">{help_icon title="`$language->summer_time`" desc="Check this box to enable summer time."}{$language->summer_time}</label>
                  <input{if $settings->summer_time} checked="checked"{/if} {nid id="summer_time"} type="checkbox" value="1" />
                </div>
                <div>
                  <label for="enable_debug">{help_icon title="Debug Mode" desc="Check this box to enable debug mode permanently."}Debug Mode</label>
                  <input{if $settings->enable_debug} checked="checked"{/if} {nid id="enable_debug"} type="checkbox" value="1" />
                </div>
                <h3>{$language->dashboard}</h3>
                <div>
                  <label for="dashboard_title">{help_icon title="`$language->title`" desc="Set the title for the dashboard introduction."}{$language->title}</label>
                  <input class="submit-fields" {nid id="dashboard_title"} value="{$settings->dashboard_title}" />
                </div>
                <div>
                  <label for="dashboard_text">{help_icon title="Text" desc="Set the text for the dashboard introduction."}Text</label>
                  <textarea cols="80" {nid id="dashboard_text"} rows="20">{$settings->dashboard_text}</textarea>
                  <a class="toggle_mce" href="#" rel="dashboard_text">Enable/Disable WYSIWYG editor</a>
                </div>
                <div>
                  <label for="disable_log_popup">{help_icon title="Disable Log Popup" desc="Check this box to disable the log info popup and use a direct link."}Disable Log Popup</label>
                  <input{if $settings->disable_log_popup} checked="checked"{/if} {nid id="disable_log_popup"} type="checkbox" value="1" />
                </div>
                <h3>{$language->page}</h3>
                <div>
                  <label for="default_page">{help_icon title="Default Page" desc="Choose the page that will be the first page people will see."}Default Page</label>
                  <select class="inputbox" {nid id="default_page"}>
                    <option{if $settings->default_page == "dashboard"} selected="selected"{/if} value="dashboard">{$language->dashboard|ucwords}</option>
                    <option{if $settings->default_page == "bans"} selected="selected"{/if} value="bans">{$language->bans|ucwords}</option>
                    <option{if $settings->default_page == "servers"} selected="selected"{/if} value="servers">{$language->servers|ucwords}</option>
                    <option{if $settings->default_page == "submitban"} selected="selected"{/if} value="submitban">{$language->submit_ban|ucwords}</option>
                    <option{if $settings->default_page == "protestban"} selected="selected"{/if} value="protestban">{$language->protest_ban|ucwords}</option>
                  </select>
                </div>
                <div>
                  <label for="items_per_page">{help_icon title="Items per page" desc="Choose how many items to show on each page."}Items Per Page</label>
                  <input class="submit-fields" {nid id="items_per_page"} value="{$settings->items_per_page}" />
                </div>
                <div>
                  <label for="enable_protest">{help_icon title="Enable Protest Ban" desc="Check this box to enable the protest ban page."}Enable Protest Ban</label>
                  <input{if $settings->enable_protest} checked="checked"{/if} {nid id="enable_protest"} type="checkbox" value="1" />
                </div>
                <div>
                  <label for="enable_submit">{help_icon title="Enable Submit Ban" desc="Check this box to enable the submit ban page."}Enable Submit Ban</label>
                  <input{if $settings->enable_submit} checked="checked"{/if} {nid id="enable_submit"} type="checkbox" value="1" />
                </div>
                <div>
                  <label for="clear_cache">{help_icon title="Clear Cache" desc="Click this button to clear the database and themes cache."}Clear Cache</label>
                  <input class="btn cancel" {nid id="clear_cache"} type="button" value="Clear Cache" />
                </div>
                <div id="clear_cache.msg"></div>
                <h3>{$language->bans}</h3>
                <div>
                  <label for="bans_public_export">{help_icon title="Enable Public Export" desc="Check this box to enable the entire ban list to be publically downloaded and shared."}Enable Public Export</label>
                  <input{if $settings->bans_public_export} checked="checked"{/if} {nid id="bans_public_export"} type="checkbox" value="1" />
                </div>
                <div>
                  <label for="bans_hide_admin">{help_icon title="Hide Admins" desc="Check this box, if you want to hide the admins on the ban list."}Hide Admins</label>
                  <input{if $settings->bans_hide_admin} checked="checked"{/if} {nid id="bans_hide_admin"} type="checkbox" value="1" />
                </div>
                <div>
                  <label for="bans_hide_ip">{help_icon title="Hide IP Adresses" desc="Check this box, if you want to hide the IP addresses on the ban list."}Hide IP Addresses</label>
                  <input{if $settings->bans_hide_ip} checked="checked"{/if} {nid id="bans_hide_ip"} type="checkbox" value="1" />
                </div>
                <h3>{$language->email}</h3>
                <div>
                  <label for="enable_smtp">{help_icon title="Enable SMTP" desc="Check this box to enable SMTP "}Enable SMTP</label>
                  <input{if $settings->enable_smtp} checked="checked"{/if} {nid id="enable_smtp"} type="checkbox" value="1" />
                </div>
                <div>
                  <label for="smtp_host">{help_icon title="SMTP Host" desc="Fill in your SMTP host here."}SMTP Host</label>
                  <input class="submit-fields" {nid id="smtp_host"} value="{$settings->smtp_host}" />
                </div>
                <div>
                  <label for="smtp_port">{help_icon title="SMTP Port" desc="Fill in your SMTP port here."}SMTP Port</label>
                  <input class="submit-fields" {nid id="smtp_port"} value="{$settings->smtp_port}" />
                </div>
                <div>
                  <label for="smtp_username">{help_icon title="SMTP Username" desc="Fill in your SMTP username here."}SMTP Username</label>
                  <input class="submit-fields" {nid id="smtp_username"} value="{$settings->smtp_username}" />
                </div>
                <div>
                  <label for="smtp_password">{help_icon title="SMTP Password" desc="Fill in your SMTP password here."}SMTP Password</label>
                  <input class="submit-fields" {nid id="smtp_password"} value="{$settings->smtp_password}" />
                </div>
                <div>
                  <label for="smtp_secure">{help_icon title="SMTP Secure" desc="Select the type of SMTP secure authentication here."}SMTP Secure</label>
                  <select class="submit-fields" {nid id="smtp_secure"}>
                    <option value="">{$language->none}</option>
                    <option{if $settings->smtp_secure == "ssl"} selected="selected"{/if} value="ssl">SSL</option>
                    <option{if $settings->smtp_secure == "tls"} selected="selected"{/if} value="tls">TLS</option>
                  </select>
                </div>
                <div class="center">
                  <input name="action" type="hidden" value="settings" />
                  <input class="btn ok" type="submit" value="{$language->save}" />
                  <input class="back btn cancel" type="button" value="{$language->back}" />
                </div>
              </fieldset>
            </form>
            <form action="" id="pane-plugins" method="post">
              <fieldset>
                <h3>Plugins</h3>
                <table class="listtable">
                  <tr>
                    <th>Plugin</th>
                    <th class="center" width="75">{$language->enabled}</th>
                    <th class="center" width="75">{$language->disabled}</th>
                  </tr>
                  {foreach from=$plugins item=plugin key=class}
                  <tr>
                    <td class="listtable_1 plugin_row">
                      <a class="flRight italic" href="{$plugin->url}">{$plugin->author}</a>
                      <span class="underline">{$plugin->name} <strong>{$plugin->version}</strong></span>
                      <div class="plugin_desc">{$plugin->description}</div>
                    </td>
                    <td class="center listtable_1"><input{if $plugin->enabled} checked="checked"{/if} name="plugins[{$class}]" type="radio" value="1" /></td>
                    <td class="center listtable_1"><input{if !$plugin->enabled} checked="checked"{/if} name="plugins[{$class}]" type="radio" value="0" /></td>
                  </tr>
                  {/foreach}
                </table>
                <div class="center">
                  <input name="action" type="hidden" value="plugins" />
                  <input class="btn ok" type="submit" value="{$language->save}" />
                  <input class="back btn cancel" type="button" value="{$language->back}" />
                </div>
              </fieldset>
            </form>
            <div id="pane-themes">
              <h3>{$language->themes}</h3>
              <div id="current-theme-holder">
                <h4 class="largetitle">Selected Theme: <span id="theme_name">{$theme->name}</span></h4>
                <img alt="{$theme->name}" id="current-theme-screenshot" src="{$uri->base}/themes/{$theme}/screenshot.jpg" title="{$theme->name}" />
                <div id="current-theme-details">
                  <p>
                    <strong>{$language->author}:</strong>
                    <br /><span id="theme_author">{$theme->author}</span>
                  </p>
                  <p>
                    <strong>{$language->version}:</strong>
                    <br /><span id="theme_version">{$theme->version}</span>
                  </p>
                  <p>
                    <strong>{$language->link}:</strong>
                    <br /><a href="{$theme->link}" id="theme_link">{$theme->link}</a>
                  </p>
                  <input class="btn flRight ok" id="apply_theme" type="button" value="Apply Theme" />
                </div>
              </div>
              <br />
              <h4 class="largetitle">Available Themes</h4>
              <p>Click a theme below to see details about it.</p>
              <ul id="theme-list">
                {foreach from=$themes item=_theme}
                <li><a class="select_theme" href="#" rel="{$_theme}">{$_theme->name}</a></li>
                {/foreach}
              </ul>
            </div>
            <div id="pane-logs">
              <h3>{$language->system_log}{if $user->permission_clear_logs} ( <a id="clear_logs" href="#">Clear Log</a> ){/if}</h3>
              Click on a row to see more details about the event.
              <br /><br />
              <div class="center">
                <table width="80%" cellpadding="0" class="listtable" cellspacing="0">
                  <tr class="sea_open">
                    <th colspan="3">{$language->advanced_search} <span class="normal">({$language->click})</span></th>
                  </tr>
                  <tr>
                    <td>
                      <form action="" class="panel" method="get">
                        <table width="100%" cellpadding="0" class="listtable" cellspacing="0">
                          <tr>
                            <td class="listtable_1" width="8%" align="center"><input id="admin_" name="type" type="radio" value="admin" /></td>
                            <td class="listtable_1" width="26%">{$language->admin}</td>
                            <td class="listtable_1" width="66%">
                              <select id="admin" onmouseup="$('admin_').checked = true" class="sea_inputbox" style="width: 251px;">
                                <option value="0">{$language->guest}</option>
                                {foreach from=$admins item=admin}
                                <option value="{$admin->id}">{$admin->name}</option>
                                {/foreach}
                              </select>
                            </td>
                          </tr>
                          <tr>
                            <td class="listtable_1" align="center"><input id="title_" name="type" type="radio" value="title" /></td>
                            <td class="listtable_1">{$language->title}</td>
                            <td class="listtable_1"><input id="title" value="" onmouseup="$('title_').checked = true" class="sea_inputbox" style="width: 249px;" /></td>
                          </tr>
                          <tr>
                            <td align="center" class="listtable_1"><input id="date_" type="radio" name="type" value="date" /></td>
                            <td class="listtable_1">{$language->date}</td>
                            <td class="listtable_1">
                              <input id="day" value="DD" onmouseup="$('date_').checked = true" class="sea_inputbox" style="width: 25px;" />.<input id="month" value="MM" onmouseup="$('date_').checked = true" class="sea_inputbox" style="width: 25px;" />.<input id="year" value="YYYY" onmouseup="$('date_').checked = true" class="sea_inputbox" style="width: 40px;" />
                              &nbsp;<input id="fhour" value="00" onmouseup="$('date_').checked = true" class="sea_inputbox" style="width: 25px;" />:<input id="fminute" value="00" onmouseup="$('date_').checked = true" class="sea_inputbox" style="width: 25px;" />
                              -&nbsp;<input id="thour" value="23" onmouseup="$('date_').checked = true" class="sea_inputbox" style="width: 25px;" />:<input id="tminute" value="59" onmouseup="$('date_').checked = true" class="sea_inputbox" style="width: 25px;" />
                            </td>
                          </tr>
                          <tr>
                            <td align="center" class="listtable_1"><input id="type_" type="radio" name="type" value="type" /></td>
                            <td class="listtable_1">{$language->type}</td>
                            <td class="listtable_1">
                              <select id="type" onmouseup="$('type_').checked = true" class="sea_inputbox" style="width: 251px;">
                                <option value="{$smarty.const.ERROR_LOG_TYPE}">{$language->error}</option>
                                <option selected="selected" value="{$smarty.const.INFO_LOG_TYPE}">{$language->information}</option>
                                <option value="{$smarty.const.WARNING_LOG_TYPE}">{$language->warning}</option>
                              </select>
                            </td>
                          </tr>
                          <tr>
                            <td align="center" colspan="3"><input class="btn ok" type="submit" value="{$language->search}" /></td>
                          </tr>
                        </table>
                      </form>
                    </td>
                  </tr>
                </table>
              </div>
              <div id="bans-nav">
                {eval var=$language->displaying_results}
                {if $total_pages > 1}
                <select onchange="window.location = '{build_uri controller=admin action=settings page=''}' + this.options[this.selectedIndex].value;">
                  {section loop=$total_pages name=page}
                  <option{if $uri->page == $smarty.section.page.iteration} selected="selected"{/if} value="{$smarty.section.page.iteration}">{$smarty.section.page.iteration}</option>
                  {/section}
                </select>
                {/if}
              </div>
              <br /><br />
              <table width="100%" cellspacing="0" cellpadding="0" align="center" class="listtable">
                <tr>
                  <th width="5%" align="center">{$language->type}</th>
                  <th width="28%" align="center">{$language->title}</th>
                  <th width="28%" align="center">{$language->admin}</th>
                  <th>{$language->date}/{$language->time}</th>
                </tr>
                {foreach from=$logs item=log}
                <tr class="opener tbl_out">
                  <td align="center" class="listtable_1">
                    {if $log->type     == $smarty.const.ERROR_LOG_TYPE}
                    <img alt="{$language->error}" src="{$uri->base}/themes/{$theme}/images/admin/error.png" title="{$language->error}" />
                    {elseif $log->type == $smarty.const.INFO_LOG_TYPE}
                    <img alt="{$language->information}" src="{$uri->base}/themes/{$theme}/images/admin/help.png" title="{$language->information}" />
                    {elseif $log->type == $smarty.const.WARNING_LOG_TYPE}
                    <img alt="{$language->warning}" src="{$uri->base}/themes/{$theme}/images/admin/warning.png" title="{$language->warning}" />
                    {/if}
                  </td>
                  <td class="listtable_1">{$log->title}</td>
                  <td class="listtable_1">{$log->admin->name}</td>
                  <td class="listtable_1">{$log->date}</td>
                </tr>
                <tr>
                  <td colspan="7" align="center">
                    <div class="opener">
                      <table width="80%" cellspacing="0" cellpadding="0" class="listtable">
                        <tr>
                          <th colspan="3">{$language->details}</th>
                        </tr>
                        <tr>
                          <td class="listtable_1" width="20%">{$language->message}</td>
                          <td class="listtable_1">{$log->message}</td>
                        </tr>
                        <tr>
                          <td class="listtable_1">Parent Function</td>
                          <td class="listtable_1">{$log->function}</td>
                        </tr>
                        <tr>
                          <td class="listtable_1">Query String</td>
                          <td class="listtable_1">{$log->query}</td>
                        </tr>
                        <tr>
                          <td class="listtable_1">{$language->ip_address}</td>
                          <td class="listtable_1">{$log->admin->ip}</td>
                        </tr>
                      </table>
                    </div>
                  </td>
                </tr>
                {/foreach}
              </table>
            </div>
            {foreach from=$admin_panes item=pane}
            <div id="pane-{$pane->id}">
              {$pane->content}
            </div>
            {/foreach}
          </div>