<div align="center">
	<table width="80%" cellpadding="0" class="listtable" cellspacing="0">
		<tr class="sea_open">
			<td width="2%" height="16" class="listtable_top" colspan="3"><b>Advanced Search</b> (Click)</td>
	  	</tr>
	  	<tr>
	  		<td>
	  		<div class="panel">
	  			<table width="100%" cellpadding="0" class="listtable" cellspacing="0">
			    <tr>
					<td class="listtable_1" width="8%" align="center"><input id="name" name="search_type" type="radio" value="name"></td>
			        <td class="listtable_1" width="26%">Nickname</td>
			        <td class="listtable_1" width="66%"><input type="text" id="nick" value="" onmouseup="$('name').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 250px;"></td>
				</tr>       
			    <tr>
			        <td align="center" class="listtable_1" ><input id="steam_" type="radio" name="search_type" value="radiobutton"></td>
			        <td class="listtable_1" >Steam ID</td>
			        <td class="listtable_1" >
			            <input type="text" id="steamid" value="" onmouseup="$('steam_').checked = true"style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 150px;"><select id="steam_match" onmouseup="$('steam_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 100px;">
				        <option label="exact" value="0" selected>Exact Match</option>
				        <option label="partial" value="1">Partial Match</option>
				    </select>
				</td>
			    </tr>
			    <tr>
			        <td align="center" class="listtable_1" ><input id="reason_" type="radio" name="search_type" value="radiobutton"></td>
			        <td class="listtable_1" >Reason</td>
			        <td class="listtable_1" ><input type="text" id="ban_reason" value="" onmouseup="$('reason_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 250px;"></td>
			    </tr>
				<tr>
					<td align="center" class="listtable_1" ><input id="date" type="radio" name="search_type" value="radiobutton"></td>
			        <td class="listtable_1" >Date</td>
			        <td class="listtable_1" >
			        	<input type="text" id="day" value="DD" onmouseup="$('date').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 75px;">
			            <input type="text" id="month" value="MM" onmouseup="$('date').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 75px;">
			            <input type="text" id="year" value="YY" onmouseup="$('date').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 75px;"> 
			        </td>
			  	</tr>
				<tr>
			        <td align="center" class="listtable_1" ><input id="length_" type="radio" name="search_type" value="radiobutton"></td>
			        <td class="listtable_1" >Length</td>
			        <td class="listtable_1" >
						<table border="0" cellpadding="0" cellspacing="0">
							<tr>
								<td>
						            <select id="length_type" onmouseup="$('length_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 40px;">
										<option value="e" title="equal to">=</option>
										<option value="h" title="greater">&gt;</option>
										<option value="l" title="smaller">&lt;</option>
										<option value="eh" title="equal to or greater">&gt;=</option>
										<option value="el" title="equal to or smaller">&lt;=</option>
									</select>
								</td>
								<td>
									<input type="text" id="other_length" name="other_length" onmouseup="$('length_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 190px;display:none;">
								</td>
								<td>
									<select id="length" onmouseup="$('length_').checked = true" onchange="switch_length(this);" onmouseover="if(this.options[this.selectedIndex].value=='other')$('length').setStyle('width', '210px');if(this.options[this.selectedIndex].value=='other')this.focus();" onblur="if(this.options[this.selectedIndex].value=='other')$('length').setStyle('width', '20px');" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 210px;">
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
											<option value="40320">1 month</option>
											<option value="80640">2 months</option>
											<option value="120960">3 months</option>
											<option value="241920">6 months</option>
											<option value="483840">12 months</option>
										</optgroup>
										<option value="other">Other length in minutes</option>
									</select>
								</td>
							</tr>
						</table>
					</td>
			    </tr>
				<tr>
			        <td align="center" class="listtable_1" ><input id="ban_type_" type="radio" name="search_type" value="radiobutton"></td>
			        <td class="listtable_1" >Type</td>
			        <td class="listtable_1" >
			            <select id="ban_type" onmouseup="$('ban_type_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 250px;">
					        <option value="1" selected>Mute</option>
					        <option value="2">Gag</option>
						</select>
					</td>
			    </tr>
                {if !$hideadminname}
			    <tr>
			    	<td class="listtable_1"  align="center"><input id="admin" name="search_type" type="radio" value="radiobutton"></td>
			        <td class="listtable_1" >Admin</td>
			        <td class="listtable_1" >
						<select id="ban_admin" onmouseup="$('admin').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 250px;">
							{foreach from="$admin_list" item="admin}
								<option label="{$admin.user}" value="{$admin.aid}">{$admin.user}</option>
							{/foreach}
						</select>           
					</td> 
				</tr>
                {/if}
			    <tr>
			    	<td class="listtable_1"  align="center"><input id="where_banned" name="search_type" type="radio" value="radiobutton"></td>
					<td class="listtable_1" >Server</td>
			        <td class="listtable_1" >
						<select id="server" onmouseup="$('where_banned').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 250px;">
						<option label="Web Ban" value="0">Web Ban</option>
							{foreach from="$server_list" item="server}
								<option value="{$server.sid}" id="ss{$server.sid}">Retrieving Hostname... ({$server.ip}:{$server.port})</option>
							{/foreach}
						</select>            
					</td>
			    </tr>
				{if $is_admin}
				<tr>
			        <td align="center" class="listtable_1" ><input id="comment_" type="radio" name="search_type" value="radiobutton"></td>
			        <td class="listtable_1" >Comment</td>
			        <td class="listtable_1" ><input type="text" id="ban_comment" value="" onmouseup="$('comment_').checked = true" style="border: 1px solid #000000; font-size: 12px; background-color: rgb(215, 215, 215);width: 250px;"></td>
			    </tr>
				{/if}
			    <tr>
				    <td> </td>
				    <td> </td>
			        <td>{sb_button text="Search" onclick="search_blocks();" class="ok" id="searchbtn" submit=false}</td>
			    </tr>
			   </table>
			   </div>
		  </td>
		</tr>
	</table>
</div>
{$server_script}
<script>InitAccordion('tr.sea_open', 'div.panel', 'mainwrapper');</script>
