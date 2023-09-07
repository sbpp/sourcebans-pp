<form action="" method="post">
    <input type="hidden" name="settingsGroup" value="mainsettings" />
    <div class="admin_tab_content_title">
        <h2><i class="fas fa-cogs"></i> Main Settings</h2>
    </div>

    <div class="padding">
        <div class="margin-bottom">
            For more information or help regarding a certain subject move your mouse over the
            question mark.
        </div>

        <div class="margin-bottom:half">
            <label for="template_title" class="form-label form-label:bottom">
                Title
            </label>

            <input type="text" TABINDEX=1 class="form-input form-full" id="template_title" name="template_title"
                value="{$config_title}" />

            <div class="form-desc">
                Define the title shown in the title of your browser.
            </div>
        </div>

        <div class="margin-bottom:half">
            <label for="template_logo" class="form-label form-label:bottom">
                Path to logo
            </label>

            <input type="text" TABINDEX=2 class="form-input form-full" id="template_logo" name="template_logo"
                value="{$config_logo}" />

            <div class="form-desc">
                Here you can define a new location for the logo, so you can use your own image.
            </div>
        </div>

        <div class="margin-bottom:half">
            <label for="config_password_minlength" class="form-label form-label:bottom">
                Min password length
            </label>

            <input type="text" TABINDEX=3 class="form-input form-full" id="config_password_minlength"
                name="config_password_minlength" value="{$config_min_password}" />

            <div class="form-desc">
                Define the shortest length a password can be.
            </div>

            <div id="minpasslength.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <label for="config_dateformat" class="form-label form-label:bottom">
                Date format <a href="http://www.php.net/date" target="_blank" rel="noopener">See: PHP date()</a>
            </label>

            <input type="text" TABINDEX=4 class="form-input form-full" id="config_dateformat" name="config_dateformat"
                value="{$config_dateformat}" />

            <div class="form-desc">
                Here you can change the date format, displayed in the banlist and other pages.
            </div>
        </div>

        <div class="margin-bottom:half">
            <label for="auth_maxlife" class="form-label form-label:bottom">
                Auth Maxlife <span class="text:italic">(in minutes)</span>
            </label>

            <input type="text" TABINDEX=4 class="form-input form-full" id="auth_maxlife" name="auth_maxlife"
                value="{$auth_maxlife}" />

            <div class="form-desc">
                Max lifetime for auth tokens.
            </div>
        </div>

        <div class="margin-bottom:half">
            <label for="auth_maxlife_remember" class="form-label form-label:bottom">
                Auth Maxlife (remember me) <span class="text:italic">(in minutes)</span>
            </label>

            <input type="text" TABINDEX=4 class="form-input form-full" id="auth_maxlife_remember"
                name="auth_maxlife_remember" value="{$auth_maxlife_remember}" />

            <div class="form-desc">
                Max lifetime for auth tokens with remember me enabled.
            </div>
        </div>

        <div class="margin-bottom:half">
            <label for="auth_maxlife_steam" class="form-label form-label:bottom">
                Auth Maxlife (steam login) <span class="text:italic">(in minutes)</span>
            </label>


            <input type="text" TABINDEX=4 class="form-input form-full" id="auth_maxlife_steam" name="auth_maxlife_steam"
                value="{$auth_maxlife_steam}" />

            <div class="form-desc">
                Max lifetime for auth tokens via steam login.
            </div>
        </div>

        <div class="margin-bottom:half">
            <input type="checkbox" TABINDEX=6 name="config_debug" class="form-check" id="config_debug" />

            <label for="config_debug" class="form-label form-label:left">
                Debugmode
            </label>

            <div class="form-desc">
                Check this box to enable the debugmode permanently.
            </div>
        </div>
    </div>

    <div class="admin_tab_content_title">
        <h2><i class="fas fa-home"></i> Dashboard Settings</h2>
    </div>

    <div class="padding">
        <div class="margin-bottom:half">
            <label for="dash_intro_title" class="form-label form-label:bottom">
                Intro Title
            </label>

            <input type="text" TABINDEX=7 class="form-input form-full" id="dash_intro_title" name="dash_intro_title"
                value="{$config_dash_title}" />

            <div class="form-desc">
                Set the title for the dashboard introduction.
            </div>

            <div id="dash.intro.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <label for="dash_intro_text" class="form-label form-label:bottom">
                Intro Text
            </label>

            <textarea TABINDEX=6 cols="80" rows="20" class="form-text" id="dash_intro_text"
                name="dash_intro_text">{$config_dash_text}</textarea>

            <div class="form-desc">
                Set the text for the dashboard introduction.
            </div>
        </div>

        <div class="margin-bottom:half">
            <input type="checkbox" TABINDEX=8 name="dash_nopopup" class="form-check" id="dash_nopopup" />

            <label for="dash_nopopup" class="form-label form-label:left">
                Disable Log Popup
            </label>

            <div class="form-desc">
                Check this box to disable the log info popup and use direct link.
            </div>
        </div>
    </div>

    <div class="admin_tab_content_title">
        <h2><i class="fas fa-sliders-h"></i> Page Settings</h2>
    </div>

    <div class="padding">
        <div class="margin-bottom:half">
            <input type="checkbox" TABINDEX=9 name="enable_protest" class="form-check" id="enable_protest" />

            <label for="enable_protest" class="form-label form-label:left">
                Enable Protest Ban
            </label>

            <div class="form-desc">
                Check this box to enable the protest ban page.
            </div>
        </div>

        <div class="margin-bottom:half">
            <input type="checkbox" TABINDEX=10 name="enable_submit" class="form-check" id="enable_submit" />

            <label for="enable_submit" class="form-label form-label:left">
                Enable Submit Ban
            </label>

            <div class="form-desc">
                Check this box to enable the submit ban page.
            </div>
        </div>

        <div class="margin-bottom:half">
            <input type="checkbox" TABINDEX=10 name="enable_commslist" class="form-check" id="enable_commslist" />

            <label for="enable_commslist" class="form-label form-label:left">
                Enable Commslist
            </label>

            <div class="form-desc">
                Check this box to enable the commslist page.
            </div>
        </div>

        <div class="margin-bottom:half">
            <input type="checkbox" TABINDEX=9 name="protest_emailonlyinvolved" class="form-check"
                id="protest_emailonlyinvolved" />

            <label for="protest_emailonlyinvolved" class="form-label form-label:left">
                Only Send One Email
            </label>

            <div class="form-desc">
                Check this box to only send the protest notification email to the admin who banned the protesting
                player.
            </div>
        </div>

        <div class="margin-bottom:half">
            <label for="default_page" class="form-label form-label:bottom">
                Default Page
            </label>

            <select class="form-select form-full" TABINDEX=11 name="default_page" id="default_page">
                <option value="0">Dashboard</option>
                <option value="1">Ban List</option>
                <option value="2">Servers</option>
                <option value="3">Submit a ban</option>
                <option value="4">Protest a ban</option>
            </select>

            <div class="form-desc">
                Choose the page that will be the first page people will see.
            </div>
        </div>

        <div class="margin-bottom:half">
            <label for="clearcache" class="form-label form-label:bottom">
                Clear Cache
            </label>

            {sb_button text="Clear Cache" onclick="xajax_ClearCache();" class="button button-light" id="clearcache" submit=false}

            <div class="form-desc">
                Click this button, to clean the cache folder.
            </div>

            <div id="clearcache.msg"></div>
        </div>
    </div>

    <div class="admin_tab_content_title">
        <h2><i class="fas fa-ban"></i> Banlist Settings</h2>
    </div>

    <div class="padding">
        <div class="margin-bottom:half">
            <label for="banlist_bansperpage" class="form-label form-label:bottom">
                Items Per Page
            </label>

            <input type="text" TABINDEX=12 class="form-input form-full" id="banlist_bansperpage"
                name="banlist_bansperpage" value="{$config_bans_per_page}" />

            <div class="form-desc">
                Choose how many items to show on each page.
            </div>

            <div id="bansperpage.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <input type="checkbox" TABINDEX=13 name="banlist_hideadmname" class="form-check" id="banlist_hideadmname" />

            <label for="banlist_hideadmname" class="form-label form-label:left">
                Hide Admin Name
            </label>

            <div class="form-desc">
                Check this box, if you want to hide the name of the admin in the baninfo.
            </div>

            <div id="banlist_hideadmname.msg" class="message message:error margin-top:half" style="display: none;">
            </div>
        </div>

        <div class="margin-bottom:half">
            <input type="checkbox" TABINDEX=14 name="banlist_nocountryfetch" class="form-check"
                id="banlist_nocountryfetch" />

            <label for="banlist_nocountryfetch" class="form-label form-label:left">
                No Country Research
            </label>

            <div class="form-desc">
                Check this box, if you don't want to display the country out of an IP in the banlist. Use if you
                encounter display problems.
            </div>

            <div id="banlist_nocountryfetch.msg" class="message message:error margin-top:half" style="display: none;">
            </div>
        </div>

        <div class="margin-bottom:half">
            <input type="checkbox" TABINDEX=15 name="banlist_hideplayerips" class="form-check"
                id="banlist_hideplayerips" />

            <label for="banlist_hideplayerips" class="form-label form-label:left">
                Hide Player IP
            </label>

            <div class="form-desc">
                Check this box, if you want to hide the player IP from the public.
            </div>

            <div id="banlist_hideplayerips.msg" class="message message:error margin-top:half" style="display: none;">
            </div>
        </div>

        <div class="margin-bottom:half">
            <label for="banlist_hideplayerips" class="form-label form-label:right">
                Custom Banreasons
            </label>

            <table width="100%" border="0" style="border-collapse:collapse;" id="custom.reasons" name="custom.reasons">
                {foreach from=$bans_customreason item="creason"}
                    <tr>
                        <td><input type="text" class="textbox" name="bans_customreason[]" id="bans_customreason[]"
                                value="{$creason}" /></td>
                    </tr>
                {/foreach}
                <tr>
                    <td><input type="text" class="textbox" name="bans_customreason[]" id="bans_customreason[]" /></td>
                </tr>
                <tr>
                    <td><input type="text" class="textbox" name="bans_customreason[]" id="bans_customreason[]" /></td>
                </tr>
            </table>
            <a href="javascript:void(0)" onclick="MoreFields();" title="Add more fields">[+]</a>

            <div class="form-desc">
                Type the custom banreasons you want to appear in the dropdown menu.
            </div>

            <div id="bans_customreason.msg" class="message message:error margin-top:half" style="display: none;">
            </div>
        </div>
    </div>
    
	<div class="admin_tab_content_title">
        <h2><i class="fa-solid fa-paper-plane"></i></i> Mails Settings</h2>
    </div>

    <div class="padding">
        <div class="margin-bottom">
            If leave blank, mails functions will not work and return an error 500.
        </div>

        <div class="margin-bottom:half">
            <label for="mail_host" class="form-label form-label:bottom">
                Host
            </label>

            <input type="text" TABINDEX=16 class="form-input form-full" id="mail_host" name="mail_host"
                value="{$config_smtp[0]}" />

            <div class="form-desc">
                Enter your Host.
            </div>
			
			<div id="mailhost.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <label for="mail_user" class="form-label form-label:bottom">
                UserName
            </label>

            <input type="text" TABINDEX=17 class="form-input form-full" id="mail_user" name="mail_user"
                value="{$config_smtp[1]}" />

            <div class="form-desc">
                Enter your UserName.
            </div>
			
			<div id="mail_user.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

        <div class="margin-bottom:half">
            <label for="mail_pass" class="form-label form-label:bottom">
                Password
            </label>

            <input type="text" TABINDEX=18 class="form-input form-full" id="mail_pass"
                name="mail_pass" placeholder="*******" />

            <div class="form-desc">
                Enter your password.
            </div>

            <div id="mail_pass.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>
		
		<div class="margin-bottom:half">
            <label for="mail_port" class="form-label form-label:bottom">
                Port
            </label>

            <input type="text" TABINDEX=19 class="form-input form-full" id="mail_port"
                name="mail_port" value="{$config_smtp[2]}" />

            <div class="form-desc">
                Enter the port used.
            </div>

            <div id="mail_port.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>
		
		<div class="margin-bottom:half">
			<input type="checkbox" TABINDEX=20 name="mail_verify_peer" class="form-check" id="mail_verify_peer" />
			
            <label for="mail_verify_peer" class="form-label form-label:bottom">
                Verify SSL Certificate
            </label>

            <div class="form-desc">
                Require verification of SSL certificate used.
            </div>

            <div id="mail_verify_peer.msg" class="message message:error margin-top:half" style="display: none;"></div>
        </div>

    </div>

    <div class="padding flex flex-ai:center flex-jc:space-between">
        {sb_button text="Save Changes" class="button button-success" id="asettings" submit=true}
        {sb_button text="Back" class="button button-light" id="aback"}
    </div>
</form>
<script type="text/javascript" src="./includes/tinymce/tinymce.min.js"></script>
{literal}
    <script language="javascript" type="text/javascript">
        tinyMCE.init({
            selector: "textarea",
            height: 500,
            theme : "silver",
            plugins : "advlist, autolink, lists, link, image, charmap, print, preview, hr, anchor, pagebreak, searchreplace, wordcount, visualblocks, visualchars, code, fullscreen, insertdatetime, media, nonbreaking, save, table, directionality, emoticons, template, paste, textpattern, imagetools, codesample, toc",
            extended_valid_elements : "a[name|href|target|title|onclick],img[class|src|border=0|alt|title|hspace|vspace|width|height|align|onmouseover|onmouseout|name],hr[class|width|size|noshade],font[face|size|color|style],span[class|align|style]"
        });
    </script>
{/literal}
