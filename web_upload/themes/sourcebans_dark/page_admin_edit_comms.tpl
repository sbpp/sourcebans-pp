<form action="" method="post">
	<div id="admin-page-content">
		<div id="0">
			<div id="msg-green" style="display:none;">
				<i><img src="./images/yay.png" alt="Warning" /></i>
				<b>Block updated</b>
				<br />
				The block details have been updated.<br /><br />
				<i>Redirecting back to blocks page</i>
			</div>
			<div id="add-group">
		<h3>Block Details</h3>
		For more information or help regarding a certain subject move your mouse over the question mark.<br /><br />
		<input type="hidden" name="insert_type" value="add">
			<table width="90%" border="0" style="border-collapse:collapse;" id="group.details" cellpadding="3">
			  <tr>
			    <td valign="top" width="35%">
				  <div class="rowdesc">
				    -{help_icon title="Player name" message="This is the name of the player that was blocked."}-Player name
				  </div>
				</td>
			    <td>
				  <div align="left">
			        <input type="text" class="submit-fields" id="name" name="name" value="-{$ban_name}-" />
			      </div>
			      <div id="name.msg" class="badentry"></div></td>
			  </tr>

			  <tr>
    			<td valign="top">
    			  <div class="rowdesc">
    				-{help_icon title="Steam ID" message="This is the Steam ID of the player that is banned. You may want to type a Community ID either."}-Steam ID
    			  </div>
    			</td>
    		 	<td>
    			  <div align="left">
      				<input value="-{$ban_authid}-" type="text" TABINDEX=2 class="submit-fields" id="steam" name="steam" />
    			  </div>
    			  <div id="steam.msg" class="badentry"></div>
    			</td>
  			  </tr>
  			  <tr>
    		<td valign="top" width="35%">
    			<div class="rowdesc">
    				-{help_icon title="Block Type" message="Choose what to block - chat or voice"}-Block Type
    			</div>
    		</td>
    		<td>
    			<div align="left">
    				<select id="type" name="type" TABINDEX=2 class="submit-fields">
						<option value="1">Voice</option>
						<option value="2">Chat</option>
					</select>
    			</div>
    		</td>
 		  </tr>
 		  <tr>
    		<td valign="top" width="35%">
    			<div class="rowdesc">
    				-{help_icon title="Block Reason" message="Explain in detail, why this block is being made."}-Block Reason
    			</div>
    		</td>
    		<td>
    			<div align="left">
    				<select id="listReason" name="listReason" TABINDEX=4 class="submit-fields" onChange="changeReason(this[this.selectedIndex].value);">
    					<option value="" selected> -- Select Reason -- </option>
					<optgroup label="Violation">
						<option value="Obscene language">Obscene language</option>
						<option value="Insult players">Insult players</option>
                        <option value="Admin disrespect">Admin disrespect</option>
                        <option value="Inappropriate Language">Inappropriate Language</option>
						<option value="Trading">Trading</option>
						<option value="Spam in chat/voice">Spam</option>
						<option value="Advertisement">Advertisement</option>
					</optgroup>
					<option value="other">Custom</option>
				</select>

				<div id="dreason" style="display:none;">
     					<textarea class="submit-fields" TABINDEX=4 cols="30" rows="5" id="txtReason" name="txtReason"></textarea>
     				</div>
    			</div>
    			<div id="reason.msg" class="badentry"></div>
    		</td>
      </tr>
      <tr>
			    <td valign="top" width="35%"><div class="rowdesc">-{help_icon title="Block Length" message="Select how long you want to block this person for."}-Block Length</div></td>
			    <td><div align="left">
			      <select id="banlength" name="banlength" TABINDEX=4 class="submit-fields">
									 <option value="0">Permanent</option>
                        <optgroup label="minutes">
                            <option value="1">1 minute</option>
                            <option value="5">5 minutes</option>
                            <option value="10">10 minutes</option>
                            <option value="15">15 minutes</option>
                            <option value="30">30 minutes</option>
                            <option value="45">45 minutes</option>
                        </optgroup>
                        <optgroup label="hours">
                            <option value="60">1 hour</option>
                            <option value="120">2 hours</option>
                            <option value="180">3 hours</option>
                            <option value="240">4 hours</option>
                            <option value="480">8 hours</option>
                            <option value="720">12 hours</option>
                        </optgroup>
                        <optgroup label="days">
                            <option value="1440">1 day</option>
                            <option value="2880">2 days</option>
                            <option value="4320">3 days</option>
                            <option value="5760">4 days</option>
                            <option value="7200">5 days</option>
                            <option value="8640">6 days</option>
                        </optgroup>
                        <optgroup label="weeks">
                            <option value="10080">1 week</option>
                            <option value="20160">2 weeks</option>
                            <option value="30240">3 weeks</option>
                        </optgroup>
                        <optgroup label="months">
                            <option value="43200">1 month</option>
                            <option value="86400">2 months</option>
                            <option value="129600">3 months</option>
                            <option value="259200">6 months</option>
                            <option value="518400">12 months</option>
						</optgroup>
				  </select>
			    </div><div id="length.msg" class="badentry"></div></td>
			  </tr>
			  <tr>
			    <td>&nbsp;</td>
			    <td>
			      <input type="hidden" name="did" id="did" value="" />
			      <input type="hidden" name="dname" id="dname" value="" />
			      	-{sb_button text="Save changes" class="ok" id="editban" submit=true}-
			     	 &nbsp;
			     	 -{sb_button text="Back" onclick="history.go(-1)" class="cancel" id="back" submit=false}-
			      </td>
			  </tr>
        </table>
       </div>
		</div>
	</div>
</form>
