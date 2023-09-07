<div class="admin_tab_content_title">
    <h2><i class="fas fa-envelope"></i> Email Player  <i>({$email_addr})</i></h2>
</div>

<div class="padding">
    <div class="margin-bottom">
        <table width="90%" style="border-collapse:collapse;" id="group.details" cellpadding="3">
            <tr>
                <td valign="top" width="35%">
                <div class="rowdesc">{help_icon title="Subject" message="Type the subject of the email."}Subject </div>
                </td>
                
            <td><div align="left">
                <input type="text" TABINDEX=1 class="textbox" id="subject" name="subject" />
            </div><div id="subject.msg" class="badentry"></div></td>
            </tr>
            <tr>
            <td valign="top"><div class="rowdesc">{help_icon title="Message" message="Type your message here."}Message </div></td>
            <td><div align="left">
                <textarea class="textbox" TABINDEX=2 cols="35" rows="7" id="message" name="message"></textarea>
            </div><div id="message.msg" class="badentry"></div></td>
            </tr>
            

        <tr>
            <td>&nbsp;</td>
            <td>
                    {sb_button text="Send Email" onclick="$email_js" class="ok" id="aemail" submit=false}
                &nbsp;
                    {sb_button text="Back" onclick="history.go(-1)" class="cancel" id="back" submit=false}
                </td>
            </tr>
        </table>
    </div>
</div>