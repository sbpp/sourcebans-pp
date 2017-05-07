/*************************************************************************
	This file is part of SourceBans++

	Copyright © 2014-2016 SourceBans++ Dev Team <https://github.com/sbpp>

	SourceBans++ is licensed under a
	Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

	You should have received a copy of the license along with this
	work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

	This program is based off work covered by the following copyright(s):
		SourceBans 1.4.11
		Copyright © 2007-2014 SourceBans Team - Part of GameConnect
		Licensed under CC BY-NC-SA 3.0
		Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/


var ADMIN_LIST_ADMINS = 	(1<<0);

var ADMIN_ADD_ADMINS = 		(1<<1);

var ADMIN_EDIT_ADMINS = 	(1<<2);
var ADMIN_DELETE_ADMINS = 	(1<<3);

var ADMIN_LIST_SERVERS = 	(1<<4);
var ADMIN_ADD_SERVER = 		(1<<5);
var ADMIN_EDIT_SERVERS = 	(1<<6);
var ADMIN_DELETE_SERVERS = 	(1<<7);

var ADMIN_ADD_BAN = 		(1<<8);
var ADMIN_EDIT_OWN_BANS = 	(1<<10);
var ADMIN_EDIT_GROUP_BANS = (1<<11);
var ADMIN_EDIT_ALL_BANS = 	(1<<12);
var ADMIN_BAN_PROTESTS = 	(1<<13);
var ADMIN_BAN_SUBMISSIONS = (1<<14);
var ADMIN_DELETE_BAN = 		(1<<25);
var ADMIN_UNBAN = 			(1<<26);
var ADMIN_BAN_IMPORT =		(1<<27);
var ADMIN_UNBAN_OWN_BANS =	(1<<30);
var ADMIN_UNBAN_GROUP_BANS =(1<<31);

var ADMIN_NOTIFY_SUB =		(1<<28);
var ADMIN_NOTIFY_PROTEST =	(1<<29);

var ADMIN_LIST_GROUPS = 	(1<<15);
var ADMIN_ADD_GROUP = 		(1<<16);
var ADMIN_EDIT_GROUPS = 	(1<<17);
var ADMIN_DELETE_GROUPS = 	(1<<18);

var ADMIN_WEB_SETTINGS = 	(1<<19);

var ADMIN_LIST_MODS = 		(1<<20);
var ADMIN_ADD_MODS = 		(1<<21);
var ADMIN_EDIT_MODS = 		(1<<22);
var ADMIN_DELETE_MODS = 	(1<<23);

var ADMIN_OWNER = 			(1<<24);

var accordion;

function ProcessAdminTabs()
{
	var url = window.location.toString();
	var pos = url.indexOf('^')+1;
	var tabNo = url.charAt(pos);
	SwapPane(tabNo);

	var upos = url.indexOf('~')+1;
	var utabNo = url.charAt(upos+1);
	var utabType = url.charAt(upos)
	Swap2ndPane(utabNo, utabType);

	if(parseInt(pos) == 0)
	{
		return -1;
	}
	else
	{
		return tabNo;
	}
}

function Swap2ndPane(id, ttype)
{
	var i = 0;
	var i2 = 0;
	if(document.getElementById("utab-" + ttype + id))
	{
		while($(document.getElementById(ttype + i)))
		{
			$(document.getElementById(ttype + i)).setStyle('display', 'none');
			i++;
		}
		while(i2 < 50)
		{
			if($("utab-" + ttype + i2))
			{
				$("utab-" + ttype + i2).removeClass('active');
				$("utab-" + ttype + i2).addClass('nonactive');
			}
			i2++;
		}
		$(document.getElementById("utab-" + ttype + id)).addClass('active');
		$(document.getElementById(ttype + id)).setStyle('display', 'block');
	}
}

function SwapPane(id)
{
	var i = 0;
	var i2 = 0;
	if(document.getElementById("tab-" + id))
	{
		while($(document.getElementById(i)))
		{
			$(document.getElementById(i)).setStyle('display', 'none');
			i++;
		}
		while(i2 < 50)
		{
			if($("tab-" + i2))
			{
				$("tab-" + i2).removeClass('active');
			}
			i2++;
		}
		$(document.getElementById("tab-" + id)).addClass('active');
		$(document.getElementById(id)).setStyle('display', 'block');
	}
}

function InitAccordion(opener, element, container, num)
{
	// IE6 got no window.addEventListener
	if (window.addEventListener) {
		window.addEventListener("load", function () {
				InitAccordion(opener, element, container, num);
		}, false);
	} else {
		window.attachEvent('onload', function () {
				InitAccordion(opener, element, container, num);
		});
	}

	if(num == null)
		num = -1;
	var ExtendedAccordion = Accordion.extend({
	showAll: function() {
		var obj = {};
		 this.previous = -1;
		this.elements.each(function(el, i){
			obj[i] = {};
			this.fireEvent('onActive', [this.togglers[i], el]);
			for (var fx in this.effects) obj[i][fx] = el[this.effects[fx]];
		}, this);
		return this.start(obj);
	},
	hideAll: function() {
		var obj = {};
		 this.previous = -1;
		this.elements.each(function(el, i){
			obj[i] = {};
			this.fireEvent('onBackground', [this.togglers[i], el]);
			for (var fx in this.effects) obj[i][fx] = 0;
		}, this);
		return this.start(obj);
	}
  });

	accordion = new ExtendedAccordion(opener, element, {
		opacity: true,
		alwaysHide: true,
		display: num,
		transition:Fx.Transitions.Quart.easeOut,
		onActive: function(toggler, element){
			toggler.setStyle('cursor', 'pointer');
			toggler.setStyle('background-color', '');
		},

		onBackground: function(toggler, element){
			toggler.setStyle('cursor', 'pointer');
			toggler.setStyle('background-color', '');
		}
	}, $(container));
	accordion.hideAll();
}

function ScrollRcon()
{
	var objDiv = document.getElementById("rcon");
	objDiv.scrollTop = objDiv.scrollHeight;
	//alert(objDiv.scrollTop);
}

function Shrink(id, time, height)
{
	var myEffects = $(document.getElementById(id)).effects({duration: time, transition:Fx.Transitions.Bounce.easeOut});
	myEffects.start({'height': [height]});
}

function FadeElOut(id, time)
{
	var myEffects = $(id).effects({duration: time, transition:Fx.Transitions.Sine.easeOut});
	myEffects.start({'opacity': [0]});
	var d = id;
	setTimeout("$(document.getElementById('" + d + "')).setStyle('display', 'none');$(document.getElementById('" + d + "')).setOpacity(0);", time);

	return;
}
function FadeElIn(id, time)
{
	$(document.getElementById(id)).setStyle('display', 'block');
	var myEffects = $(id).effects({duration: time, transition:Fx.Transitions.Sine.easeIn});
	myEffects.start({'opacity': [1]});
	setTimeout("$(document.getElementById('" + id + "')).setOpacity(1);", time);
	return;
}
function FXShow(id)
{
	$(document.getElementById(id)).setStyle('display', 'block');
}
function FXHide(id)
{
	$(document.getElementById(id)).setStyle('display', 'none');
}
function DoLogin(redir)
{
	var err = 0;
	var nopw = 0;
	if(!$('loginUsername').value)
	{
		$('loginUsername.msg').setHTML('You must enter your loginname!');
		$('loginUsername.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('loginUsername.msg').setHTML('');
		$('loginUsername.msg').setStyle('display', 'none');
	}

	if(!$('loginPassword').value)
	{
		$('loginPassword.msg').setHTML('You must enter your password!');
		$('loginPassword.msg').setStyle('display', 'block');
		nopw = 1;
	}else
	{
		$('loginPassword.msg').setHTML('');
		$('loginPassword.msg').setStyle('display', 'none');
	}

	if(err)
		return 0;

	if(redir == "undefined")
		redir = "";
	xajax_Plogin(document.getElementById('loginUsername').value,
				document.getElementById('loginPassword').value,
				 document.getElementById('loginRememberMe').checked,
				 redir,
				 nopw);
}

function SlideUp(id)
{
	var slider = new Fx.Slide(id);
	slider.slideOut().chain(
						function(){
							$(id).remove();
						}
		);
}

function RemoveGroup(id, name, type)
{
	var noPerm = confirm("Are you sure you want to delete the group: '" + name +"'?");
	if(noPerm == false)
	{
		return;
	}
	xajax_RemoveGroup(id, type);
}

function RemoveAdmin(id, name)
{
	var noPerm = confirm("Are you sure you want to delete '" + name +"'?");
	if(noPerm == false)
	{
		return;
	}
	xajax_RemoveAdmin(id);
}

function RemoveSubmission(id, name, archiv)
{
	if(archiv == '2') {
		var noPerm = confirm("Are you sure you want to restore the ban submission for '" + name + "' from the archive?");
	}
	else if(archiv == '1') {
		var noPerm = confirm("Are you sure you want to move the ban submission for '" + name +"' to the archive?");
	}
	else {
		var noPerm = confirm("Are you sure you want to delete the ban submission for '" + name +"'?");
	}
	if(noPerm == false)
		return;

	xajax_RemoveSubmission(id, archiv);
}

function RemoveProtest(id, name, archiv)
{
	if(archiv == '2') {
		var noPerm = confirm("Are you sure you want to restore the ban protest for '" + name + "' from the archive?");
	}
	else if(archiv == '1') {
		var noPerm = confirm("Are you sure you want to move the ban protest for '" + name +"' to the archive?");
	}
	else {
		var noPerm = confirm("Are you sure you want to delete the ban protest for '" + name +"'?");
	}
	if(noPerm == false)
	{
		return;
	}
	xajax_RemoveProtest(id, archiv);
}

function RemoveServer(id, name)
{
	var noPerm = confirm("Are you sure you want to delete the server: '" + name +"'?");
	if(noPerm == false)
	{
		return;
	}
	xajax_RemoveServer(id);
}

function RemoveBan(id, key, page, name, confirm, bulk)
{
	if(confirm==0) {
		ShowBox('Delete Ban', 'Are you sure you want to delete the ban'+(bulk=="true"?"s":"")+' for '+(bulk=="true"?"those players":"\'"+ name +"\'")+'?', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" onclick="RemoveBan(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'1\''+(bulk=="true"?", \'true\'":"")+');" name="rban" class="btn ok" onmouseover="ButtonOver(\'rban\')" onmouseout="ButtonOver(\'rban\')" id="rban" value="Remove Ban" />&nbsp;<input type="button" onclick="closeMsg(\'\');$(\'bulk_action\').options[0].selected=true;" name="astop" class="btn cancel" onmouseover="ButtonOver(\'astop\')" onmouseout="ButtonOver(\'astop\')" id="astop" value="Cancel" />');
	} else if(confirm==1) {
		if(page != "")
			var pagelink = page;
		else
			var pagelink = "";
		window.location = "index.php?p=banlist" + pagelink + "&a=delete&id="+ id +"&key="+ key +(bulk=="true"?"&bulk=true":"");
	}
}

function UnbanBan(id, key, page, name, popup, bulk)
{
	if(popup==1) {
		ShowBox('Unban Reason', '<b>Please give a short comment, why you are going to unban '+(bulk=="true"?"those players":"\'"+ name +"\'")+'!</b><br><textarea rows="3" cols="40" name="ureason" id="ureason" style="overflow:auto;"></textarea><br><div id="ureason.msg" class="badentry"></div>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" onclick="UnbanBan(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'0\''+(bulk=="true"?", \'true\'":"")+');" name="uban" class="btn ok" onmouseover="ButtonOver(\'uban\')" onmouseout="ButtonOver(\'uban\')" id="uban" value="Unban Ban" />&nbsp;<input type="button" onclick="closeMsg(\'\');$(\'bulk_action\').options[0].selected=true;" name="astop" class="btn cancel" onmouseover="ButtonOver(\'astop\')" onmouseout="ButtonOver(\'astop\')" id="astop" value="Cancel" />');
	} else if(popup==0) {
		if(page != "")
			var pagelink = page;
		else
			var pagelink = "";
		reason = $('ureason').value;
		if(reason == "") {
			$('ureason.msg').setHTML("Please leave a comment.");
			$('ureason.msg').setStyle('display', 'block');
			return;
		} else {
			$('ureason.msg').setHTML('');
			$('ureason.msg').setStyle('display', 'none');
		}
		window.location = "index.php?p=banlist" + pagelink + "&a=unban&id="+ id +"&key="+ key +"&ureason="+ reason +(bulk=="true"?"&bulk=true":"");
	}
}

function BoxToSrvMask()
{
	var string = "";
	if(document.getElementById('s1'))
	{
		if(document.getElementById('s1').checked)
			string += "a";
		if(document.getElementById('s23').checked)
			string +=  "b";
		if(document.getElementById('s2').checked)
			string += "c";
		if(document.getElementById('s3').checked)
			string += "d";
		if(document.getElementById('s4').checked)
			string += "e";
		if(document.getElementById('s5').checked)
			string += "f";
		if(document.getElementById('s6').checked)
			string += "g";
		if(document.getElementById('s7').checked)
			string += "h";
		if(document.getElementById('s8').checked)
			string += "i";
		if(document.getElementById('s9').checked)
			string += "j";
		if(document.getElementById('s10').checked)
			string += "k";
		if(document.getElementById('s11').checked)
			string += "l";
		if(document.getElementById('s12').checked)
			string += "m";
		if(document.getElementById('s13').checked)
			string += "n";
		if(document.getElementById('s17').checked)
			string += "o";
		if(document.getElementById('s18').checked)
			string += "p";
		if(document.getElementById('s19').checked)
			string += "q";
		if(document.getElementById('s20').checked)
			string += "r";
		if(document.getElementById('s21').checked)
			string += "s";
		if(document.getElementById('s22').checked)
			string += "t";
		if(document.getElementById('s14').checked)
			string += "z";
		if(document.getElementById('immunity').value)
			string += "#" + $('immunity').value;
	}
	return string;
}

function BoxToMask()
{
	var Mask = 0;
	if(document.getElementById('p4'))
	{
		if(document.getElementById('p4').checked)
			Mask |= ADMIN_LIST_ADMINS;
		if(document.getElementById('p5').checked)
			Mask |= ADMIN_ADD_ADMINS;
		if(document.getElementById('p6').checked)
			Mask |= ADMIN_EDIT_ADMINS;
		if(document.getElementById('p7').checked)
			Mask |= ADMIN_DELETE_ADMINS;

		if(document.getElementById('p9').checked)
			Mask |= ADMIN_LIST_SERVERS;
		if(document.getElementById('p10').checked)
			Mask |= ADMIN_ADD_SERVER;
		if(document.getElementById('p11').checked)
			Mask |= ADMIN_EDIT_SERVERS;
		if(document.getElementById('p12').checked)
			Mask |= ADMIN_DELETE_SERVERS;

		if(document.getElementById('p14').checked)
			Mask |= ADMIN_ADD_BAN;
		if(document.getElementById('p16').checked)
			Mask |= ADMIN_EDIT_OWN_BANS;
		if(document.getElementById('p17').checked)
			Mask |= ADMIN_EDIT_GROUP_BANS;
		if(document.getElementById('p18').checked)
			Mask |= ADMIN_EDIT_ALL_BANS;
		if(document.getElementById('p19').checked)
			Mask |= ADMIN_BAN_PROTESTS;
		if(document.getElementById('p20').checked)
			Mask |= ADMIN_BAN_SUBMISSIONS;
		if(document.getElementById('p38').checked)
			Mask |= ADMIN_UNBAN_OWN_BANS;
		if(document.getElementById('p39').checked)
			Mask |= ADMIN_UNBAN_GROUP_BANS;
		if(document.getElementById('p32').checked)
			Mask |= ADMIN_UNBAN;
		if(document.getElementById('p33').checked)
			Mask |= ADMIN_DELETE_BAN;
		if(document.getElementById('p34').checked)
			Mask |= ADMIN_BAN_IMPORT;

		if(document.getElementById('p36').checked)
			Mask |= ADMIN_NOTIFY_SUB;
		if(document.getElementById('p37').checked)
			Mask |= ADMIN_NOTIFY_PROTEST;

		if(document.getElementById('p22').checked)
			Mask |= ADMIN_LIST_GROUPS;
		if(document.getElementById('p23').checked)
			Mask |= ADMIN_ADD_GROUP;
		if(document.getElementById('p24').checked)
			Mask |= ADMIN_EDIT_GROUPS;
		if(document.getElementById('p25').checked)
			Mask |= ADMIN_DELETE_GROUPS;

		if(document.getElementById('p26').checked)
			Mask |= ADMIN_WEB_SETTINGS;

		if(document.getElementById('p28').checked)
			Mask |= ADMIN_LIST_MODS;
		if(document.getElementById('p29').checked)
			Mask |= ADMIN_ADD_MODS;
		if(document.getElementById('p30').checked)
			Mask |= ADMIN_EDIT_MODS;
		if(document.getElementById('p31').checked)
			Mask |= ADMIN_DELETE_MODS;

		if(document.getElementById('p2').checked)
			Mask |= ADMIN_OWNER;
	}
	return Mask;
}

function UpdateCheckBox(tgl, start, stop)
{
	for(var i=start;i<=stop;i++)
	{
		if($('p' + i))
		{
			if($('p' + tgl).checked == true)
				$('p' + i).checked = true;
			else
				$('p' + i).checked = false;
		}
	}

	// Other Arguments is individual items not available in the range
	if (arguments.length > 3)
	{
		for(var lp = 4; lp <= arguments.length; lp++)
		{
			if ($('p' + arguments[lp - 1]))
			{
				$('p' + arguments[lp - 1]).checked = $('p' + tgl).checked;
			}
		}
	}
}

function ProcessGroup()
{
	var Mask = BoxToMask();
	var Smask = BoxToSrvMask();
	xajax_AddGroup(document.getElementById('groupname').value, document.getElementById('grouptype').value, Mask, Smask);
}

function update_web()
{
	$('webperm').setHTML('');

	if(document.getElementById('webg').value == "c" || document.getElementById('webg').value == "n") {
		$('web.msg').setHTML('Please Wait...');
		$('web.msg').setStyle('display', 'block');
	}

	if(document.getElementById('webg').value == "c")
		var height = 390;
	else if(document.getElementById('webg').value == "n")
		var height = 410;
	else
	{
		$('webperm').setHTML('');
		var height = 1;
	}
	Shrink('webperm', 1000, height);

	if(document.getElementById('webg').value == "c" || document.getElementById('webg').value == "n")
		setTimeout("xajax_UpdateAdminPermissions(1, document.getElementById('webg').value)",1000);
	else {
		$('web.msg').setHTML('');
		$('web.msg').setStyle('display', 'none');
	}
}

function update_server_groups()
{
	$('nsgroup').setHTML('');

	if(document.getElementById('serverg').value == "n")
	{
		$('group.msg').setHTML('Please Wait...');
		$('group.msg').setStyle('display', 'block');
		var height = 50;
		Shrink('nsgroup', 500, height);
		setTimeout("xajax_AddServerGroupName()",500);
	}
	else
	{
		height = 5;
		Shrink('nsgroup', 500, height);
		$('group.msg').setHTML('');
		$('group.msg').setStyle('display', 'none');
	}
}

function ProcessAddAdmin()
{
	var Mask = BoxToMask();
	var srvMask = BoxToSrvMask();
	var server_a_pass = "-1";

	var el = document.getElementsByName('group[]');
	var grp = "";
  	for(i=0;i<el.length;i++){
    	if(el[i].checked){
       		grp = grp + "," + el[i].value;
    	}
  	}

  	var el = document.getElementsByName('servers[]');
	var svr = "";
  	for(i=0;i<el.length;i++){
    	if(el[i].checked){
       		svr = svr + "," + el[i].value;
    	}
  	}

    var serverg = document.getElementById('serverg').value;
  	if(serverg == "-3")
  	{
  		//serverg = "c";
  		srvMask = "";
  	}
    var webg = document.getElementById('webg').value;
  	if(webg == "-3")
  	{
  		//webg = "c";
  		Mask = 0;
  	}

  	if(document.getElementById('a_useserverpass').checked)
  		server_a_pass = document.getElementById('a_serverpass').value;

	if(document.getElementById('webname') && !document.getElementById('servername'))
	xajax_AddAdmin(Mask,srvMask, document.getElementById('adminname').value, //Admin name
					document.getElementById('steam').value, //Admin Steam
					document.getElementById('email').value, // Email
					document.getElementById('password').value,//passwrds
					document.getElementById('password2').value,
					serverg, //servergroup
					webg,
					server_a_pass,
					document.getElementById('webname').value,
					0,
					grp,
					svr); //server / server group
	else if(!document.getElementById('webname') && document.getElementById('servername'))
	xajax_AddAdmin(Mask,srvMask, document.getElementById('adminname').value, //Admin name
					document.getElementById('steam').value, //Admin Steam
					document.getElementById('email').value, // Email
					document.getElementById('password').value,//passwrds
					document.getElementById('password2').value,
					serverg, //servergroup
					webg,
					server_a_pass,
					0,
					document.getElementById('servername').value,
					grp,
					svr);
	else if(document.getElementById('webname') && document.getElementById('servername'))
	xajax_AddAdmin(Mask,srvMask, document.getElementById('adminname').value, //Admin name
					document.getElementById('steam').value, //Admin Steam
					document.getElementById('email').value, // Email
					document.getElementById('password').value,//passwrds
					document.getElementById('password2').value,
					serverg, //servergroup
					webg,
					server_a_pass,
					document.getElementById('webname').value,
					document.getElementById('servername').value,
					grp,
					svr);
	else
	xajax_AddAdmin(Mask,srvMask, document.getElementById('adminname').value, //Admin name
					document.getElementById('steam').value, //Admin Steam
					document.getElementById('email').value, // Email
					document.getElementById('password').value,//passwrds
					document.getElementById('password2').value,
					serverg, //servergroup
					webg,
					server_a_pass,
					0,
					0,
					grp,
					svr);

}

function ProcessEditAdminPermissions()
{
	var Mask = BoxToMask();
	var srvMask = BoxToSrvMask();
	var aid = $('admin_id').value;

	if($('immunity'))
	{
	 	if(IsNumeric($('immunity').value))
			xajax_EditAdminPerms(aid, Mask, srvMask);
		else
			ShowBox("Error", "Immunity must be a numerical value (0-9)", "red", "", true);
	}else
		xajax_EditAdminPerms(aid, Mask, srvMask);
}

function ProcessEditGroup(type, name)
{

	var Mask = BoxToMask();
	var srvMask = BoxToSrvMask();
	var group = $('group_id').value;

	if(name == "")
	{
		ShowBox("Error", "You have to type a name for the group.", "red", "", true);
		$('groupname.msg').innerHTML = 'You have to type a name for the group.';
		$('groupname.msg').setStyle('display', 'block');
		return;
	}
	else
	{
		$('groupname.msg').innerHTML = '';
		$('groupname.msg').setStyle('display', 'none');
	}

	if($('immunity') && !IsNumeric($('immunity').value))
	{
		ShowBox("Error", "Immunity must be a numerical value (0-9)", "red", "", true);
		return;
	}

	var overrides = [];
	var new_override = {};

	// Handle group overrides
	if(type == "srv")
	{
		var override_id = document.group_overrides_form.elements["override_id[]"];
		// Are there any old overrides to change?
		if(override_id != null)
		{
			var override_type = document.group_overrides_form.elements["override_type[]"];
			var override_name = document.group_overrides_form.elements["override_name[]"];
			var override_access = document.group_overrides_form.elements["override_access[]"];

			// Make sure they're arrays!
			if($type(override_id) == "element")
				override_id = [override_id];
			if($type(override_type) == "element")
				override_type = [override_type];
			if($type(override_name) == "element")
				override_name = [override_name];
			if($type(override_access) == "element")
				override_access = [override_access];

			overrides = {};

			for(var i=0;i<override_id.length;i++)
			{
				overrides[i] = {'id': override_id[i].value, 'type': override_type[i][override_type[i].selectedIndex].value, 'name': override_name[i].value, 'access': override_access[i][override_access[i].selectedIndex].value};
			}
		}

		new_override = {'type': $('new_override_type')[$('new_override_type').selectedIndex].value, 'name': $('new_override_name').value, 'access': $('new_override_access')[$('new_override_access').selectedIndex].value};
	}

	xajax_EditGroup(group, Mask, srvMask, type, name, JSON.stringify(overrides), JSON.stringify(new_override));
}

function update_server()
{
	$('serverperm').setHTML('');

	if(document.getElementById('serverg').value == "c" || document.getElementById('serverg').value == "n") {
		$('server.msg').setHTML('Please Wait...');
		$('server.msg').setStyle('display', 'block');
	}

	if(document.getElementById('serverg').value == "c")
		var height = 580;
	else if(document.getElementById('serverg').value == "n")
		var height = 590;
	else
	{
		$('serverperm').setHTML('');
		var height = 1;
	}
	Shrink('serverperm', 1000, height);

	if(document.getElementById('serverg').value == "c" || document.getElementById('serverg').value == "n")
		setTimeout("xajax_UpdateAdminPermissions(2, document.getElementById('serverg').value)",1000);
	else {
		$('server.msg').setHTML('');
		$('server.msg').setStyle('display', 'none');
	}
}

function process_add_server()
{
	var el = document.getElementsByName('groups[]');
	var grp = "";
  	for(i=0;i<el.length;i++){
    	if(el[i].checked){
       		grp = grp + "," + el[i].value;
    	}
  	}
	xajax_AddServer(document.getElementById('address').value,
				document.getElementById('port').value,
				document.getElementById('rcon').value,
				document.getElementById('rcon2').value,
				document.getElementById('mod').value,
				document.getElementById('enabled').checked,
				grp,
				-1);

}

function process_edit_server()
{
    if($('rcon').value != $('rcon2').value)
    {
        $('rcon2.msg').innerHTML = 'Passwords don\'t match.';
        $('rcon2.msg').setStyle('display', 'block');
        return;
    }

    $('rcon2.msg').setStyle('display', 'none');
	document.forms.editserver.submit();
}

function search_bans()
{
	var type = "";
	var input = "";
	if($('name').checked)
	{
		type = "name";
		input = $('nick').value;
	}
	if($('steam_').checked)
	{
		type = (document.getElementById('steam_match').value == "1" ? "steam" : "steamid");
		input = $('steamid').value;
	}
	if($('ip_').checked)
	{
		type = "ip";
		input = $('ip').value;
	}
	if($('reason_').checked)
	{
		type = "reason";
		input = $('ban_reason').value;
	}
	if($('date').checked)
	{
		type = "date";
		input = $('day').value + "," + $('month').value + "," + $('year').value;
	}
	if($('length_').checked)
	{
		type = "length";
		if($('length').value=="other")
			var length = $('other_length').value;
		else
			var length = $('length').value
		input = $('length_type').value + "," + length;
	}
	if($('ban_type_').checked)
	{
		type = "btype";
		input = $('ban_type').value;
	}
	if($('bancount').checked)
	{
		type = "bancount";
		input = $('timesbanned').value;
	}
	if($('admin').checked)
	{
		type = "admin";
		input = $('ban_admin').value;
	}
	if($('where_banned').checked)
	{
		type = "where_banned";
		input = $('server').value;
	}
	if($('comment_').checked)
	{
		type = "comment";
		input = $('ban_comment').value;
	}
	if(type!="" && input!="")
		window.location = "index.php?p=banlist&advSearch=" + input + "&advType=" + type;
}
var webSelected = new Array();
var srvSelected = new Array();
function getMultiple(ob, type) {
	if(type==1) {
		while (ob.selectedIndex != -1)
		{
			webSelected.push(ob.options[ob.selectedIndex].value);
			ob.options[ob.selectedIndex].selected = false;
		}
	}
	if(type==2) {
		while (ob.selectedIndex != -1)
		{
			srvSelected.push(ob.options[ob.selectedIndex].value);
			ob.options[ob.selectedIndex].selected = false;
		}
	}
}
function search_admins()
{
	var type = "";
	var input = "";
	if($('name_').checked)
	{
		type = "name";
		input = $('nick').value;
	}
	if($('steam_').checked)
	{
		type = (document.getElementById('steam_match').value == "1" ? "steam" : "steamid");
		input = $('steamid').value;
	}
	if($('admemail_').checked)
	{
		type = "admemail";
		input = $('admemail').value;
	}
	if($('webgroup_').checked)
	{
		type = "webgroup";
		input = $('webgroup').value;
	}
	if($('srvadmgroup_').checked)
	{
		type = "srvadmgroup";
		input = $('srvadmgroup').value;
	}
	if($('srvgroup_').checked)
	{
		type = "srvgroup";
		input = $('srvgroup').value;
	}
	if($('admwebflags_').checked)
	{
		type = "admwebflag";
		input = webSelected.toString();
	}
	if($('admsrvflags_').checked)
	{
		type = "admsrvflag";
		input = srvSelected.toString();
	}
	if($('admin_on_').checked)
	{
		type = "server";
		input = $('server').value;
	}
	if(type!="" && input!="")
		window.location = "index.php?p=admin&c=admins&advSearch=" + input + "&advType=" + type;
}

function search_log()
{
	var type = "";
	var input = "";
	if($('admin_').checked)
	{
		type = "admin";
		input = $('admin').value;
	}
	if($('message_').checked)
	{
		type = "message";
		input = $('message').value;
	}
	if($('date_').checked)
	{
		type = "date";
		input = $('day').value + "," + $('month').value + "," + $('year').value + "," + $('fhour').value + "," + $('fminute').value + "," + $('thour').value + "," + $('tminute').value;
	}
	if($('type_').checked)
	{
		type = "type";
		input = $('type').value;
	}
	if(type!="" && input!="")
		window.location = "index.php?p=admin&c=settings&advSearch=" + input + "&advType=" + type + "#^2";
}
var icname = "";
function icon(name)
{
	$('icon.msg').setHTML("Uploaded: <b>" + name + "</b>");
	icname = name;
	if($('icon_hid'))
		$('icon_hid').value = name;
}
function ProcessMod()
{
	var err = 0;
	if(!$('name').value)
	{
		$('name.msg').setHTML('You must enter the name of the mod you are adding.');
		$('name.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('name.msg').setHTML('');
		$('name.msg').setStyle('display', 'none');
	}

	if(!$('folder').value)
	{
		$('folder.msg').setHTML('You must enter mod\'s folder name.');
		$('folder.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('folder.msg').setHTML('');
		$('folder.msg').setStyle('display', 'none');
	}

	if(err)
		return 0;

	xajax_AddMod($('name').value,
				 $('folder').value,
				 icname,
				 $('steam_universe').value,
				 $('enabled').checked);
}
function ShowBox(title, msg, color, redir, noclose)
{
	var type = "";

	if(color == "red")
		color = "error";
	else if(color == "blue")
		color = "info";
	else if(color == "green")
		color = "ok";

	$('dialog-title').setProperty("class", color);

	$('dialog-icon').setProperty("class", 'icon-'+color);

	$('dialog-title').setHTML(title);
	$('dialog-content-text').setHTML(msg);
	FadeElIn('dialog-placement', 750);

	var jsCde = "closeMsg('" + redir + "');";
	$('dialog-control').setHTML("<input name='dialog-close' onclick=\""+jsCde+"\" class='btn ok' onmouseover=\"ButtonOver('dialog-close')\" onmouseout='ButtonOver(\"dialog-close\")' id=\"dialog-close\" value=\"OK\" type=\"button\">");
	$('dialog-control').setStyle('display', 'block');

	if(!noclose)
	{
		if(redir)
			setTimeout("window.location='" + redir + "'",5000);
		else
		{
			setTimeout("FadeElOut('dialog-placement', 750);",5000);
		}
	}
}
function closeMsg(redir)
{
	if(redir.toString().length > 0 && redir != "undefined")
		window.location = redir;
	else
	{
		FadeElOut('dialog-placement', 750);
	}
}

function TabToReload()
{
	var url = window.location.toString();
	var nurl = "window.location = '" + url.replace("#^" + url[url.length-1],"") + "'";
	$('admin_tab_0').setProperty('onclick', nurl);
}


function toggleMCE(id) {
	var elm = document.getElementById(id);
	if (tinyMCE.getInstanceById(id) == null)
		tinyMCE.execCommand('mceAddControl', false, id);
	else
		tinyMCE.execCommand('mceRemoveControl', false, id);
}

function CheckEmail(type, id)
{
	var err = 0;
	if($('subject').value == "") {
		$('subject.msg').setHTML("You must type a subject for the email.");
		$('subject.msg').setStyle('display', 'block');
		err++;
	} else {
		$('subject.msg').setHTML('');
		$('subject.msg').setStyle('display', 'none');
	}

	if($('message').value == "") {
		$('message.msg').setHTML("You must type a message for the email.");
		$('message.msg').setStyle('display', 'block');
		err++;
	} else {
		$('message.msg').setHTML('');
		$('message.msg').setStyle('display', 'none');
	}

	if(err>0)
		return;
	xajax_SendMail($('subject').value, $('message').value, type, id);
}

function IsNumeric(sText)
{
   var ValidChars = "0123456789.";
   var IsNumber=true;
   var Char;

	for (i = 0; i < sText.length && IsNumber == true; i++)
	{
		Char = sText.charAt(i);
  		if (ValidChars.indexOf(Char) == -1)
		{
			IsNumber = false;
     	}
  	}
   	return IsNumber;
}

function ButtonOver(el)
{
	if($(el))
	{
		if($(el).hasClass('btn'))
		{
			$(el).removeClass('btn');
			$(el).addClass('btnhvr');
		}
		else
		{
			$(el).removeClass('btnhvr');
			$(el).addClass('btn');
		}
	}
}

function ClearLogs()
{
	var noPerm = confirm("Are you sure you want to delete all of the log entries?");
	if(noPerm == false)
	{
		return;
	}
	window.location = "index.php?p=admin&c=settings&log_clear=true#^2";
}

function RemoveMod(name, id)
{
	var noPerm = confirm("Are you sure you want to delete '" + name +"'?");
	if(noPerm == false)
		return;
	xajax_RemoveMod(id);
}

function UpdateGroupPermissionCheckBoxes()
{
	$('perms').setHTML('');
	if(document.getElementById('grouptype').value != 3 && document.getElementById('grouptype').value != 0) {
		$('type.msg').setHTML('Please Wait...');
		$('type.msg').setStyle('display', 'block');
	}
	if(document.getElementById('grouptype').value == 1)
	{
		var height = 285;
	}else if(document.getElementById('grouptype').value == 2)
	{
		var height = 435;
	}else
	{
		$('type.msg').setStyle('display', 'none');
		var height = 2;
	}
	Shrink('perms', 1000, height);
	if(document.getElementById('grouptype').value != 3 && document.getElementById('grouptype').value != 0)
		setTimeout("xajax_UpdateGroupPermissions(document.getElementById('grouptype').value)",1000);
}

function changePage(newPage, type, advSearch, advType)
{
	nextPage = newPage.options[newPage.selectedIndex].value
	if(advSearch!="" && advType !="") {
		var searchlink = "&advSearch="+advSearch+"&advType="+advType;
	} else {
		var searchlink ="";
	}
	 if (nextPage != 0)
	 {
		if(type == "A")
            window.location = "index.php?p=admin&c=admins"+searchlink+"&page="+nextPage;
		if(type == "B")
            window.location = "index.php?p=banlist"+searchlink+"&page="+nextPage;
		if(type == "C")
            window.location = "index.php?p=commslist"+searchlink+"&page="+nextPage;
		if(type == "L")
            window.location = "index.php?p=admin&c=settings"+searchlink+"&page="+nextPage+"#^2";
        if(type == "P")
            window.location = "index.php?p=admin&c=bans&ppage="+nextPage+"#^1";
        if(type == "PA")
            window.location = "index.php?p=admin&c=bans&papage="+nextPage+"#^1~p1";
        if(type == "S")
            window.location = "index.php?p=admin&c=bans&spage="+nextPage+"#^2";
        if(type == "SA")
            window.location = "index.php?p=admin&c=bans&sapage="+nextPage+"#^2~s1";
	 }
}

function ShowKickBox(check, type)
{
	ShowBox('Ban Added', 'The ban has been successfully added<br><iframe id="srvkicker" frameborder="0" width="100%" src="pages/admin.kickit.php?check='+check+'&type='+type+'"></iframe>', 'green', 'index.php?p=admin&c=bans', true);
}

function ShowRehashBox(servers, title, msg, color, redir)
{
	// Don't show anything sm_rehash related, if there are no servers to rcon.
	if(servers == '')
	{
		ShowBox(title, msg, color, redir, true);
		return;
	}
	msg = msg + '<br /><hr /><i>Rehashing Admin and Group data on all related servers...</i><div id="rehashDiv" name="rehashDiv" width="100%"></div>';
	ShowBox(title, msg, color, redir, true);
	$('dialog-control').setStyle('display', 'none');
	xajax_RehashAdmins(servers);
}

function ProcessComment()
{
	var err = 0;
	if($('commenttext').value == "")
	{
		$('commenttext.msg').setHTML('You have to type your comment');
		$('commenttext.msg').setStyle('display', 'block');
		err++;
	}else
	{
		$('commenttext.msg').setHTML('');
		$('commenttext.msg').setStyle('display', 'none');
		err = 0;
	}

	if(err)
		return 0;

	if($('cid').value == -1)
	{
		xajax_AddComment($('bid').value,
					 $('ctype').value,
					 $('commenttext').value,
					 $('page').value);
	}
	else
	{
		xajax_EditComment($('cid').value,
					 $('ctype').value,
					 $('commenttext').value,
					 $('page').value);
	}
}

function RemoveComment(cid, type, page)
{
	var checkUp = confirm("Are you sure you want to delete the comment?");
	if(checkUp == false)
		return;
	xajax_RemoveComment(cid, type, page);
}


// drag and drop function, make the dialog window movable!
var ns4=document.layers;
var ie4=document.all;
var ns6=document.getElementById&&!document.all;

//NS 4
var dragswitch=0;
var nsx;
var nsy;
var nstemp;
function drag_drop_ns(name)
{
	if(!ns4)
		return;
	temp=eval(name);
	temp.captureEvents(Event.MOUSEDOWN | Event.MOUSEUP);
	temp.onmousedown=gons;
	temp.onmousemove=dragns;
	temp.onmouseup=stopns;
}
function gons(e)
{
	temp.captureEvents(Event.MOUSEMOVE);
	nsx=e.x;
	nsy=e.y;
}
function dragns(e)
{
	if(dragswitch==1) {
		temp.moveBy(e.x-nsx,e.y-nsy);
		return false;
	}
}
function stopns()
{
	temp.releaseEvents(Event.MOUSEMOVE);
}

//IE4 || NS6
function drag_drop(e)
{
	if(ie4&&dragapproved) {
		crossobj.style.left=tempx+event.clientX-offsetx+'px';
		crossobj.style.top=tempy+event.clientY-offsety+'px';
		return false;
	}
	else if(ns6&&dragapproved) {
		crossobj.style.left=tempx+e.clientX-offsetx+'px';
		crossobj.style.top=tempy+e.clientY-offsety+'px';
		return false;
	}
}
function initializiere_drag(e)
{
	crossobj=ns6? document.getElementById("dialog-placement") : document.all["dialog-placement"];
	var firedobj=ns6? e.target : event.srcElement;
	var topelement=ns6? "HTML" : "BODY";

	while (firedobj!=null&&firedobj.tagName!=topelement&&firedobj.id!="dragbar") {
		firedobj=ns6? firedobj.parentNode : firedobj.parentElement;
	}
	if(firedobj!=null&&firedobj.id=="dragbar")
	{
		offsetx=ie4? event.clientX : e.clientX;
		offsety=ie4? event.clientY : e.clientY;
		tempx=parseInt(crossobj.style.left);
		tempy=parseInt(crossobj.style.top);
		dragapproved=true;
		document.onmousemove=drag_drop;
	}

}
document.onmousedown=initializiere_drag;
document.onmouseup=new Function("dragapproved=false");

function TickSelectAll()
{
	for(var i=0;$('chkb_' + i);i++)
	{
		if($('tickswitch').value==0)
			$('chkb_' + i).checked = true;
		else
			$('chkb_' + i).checked = false;
	}
	if($('tickswitch').value==0) {
		$('tickswitch').value=1;
		$('tickswitch').setProperty('title','Deselect All');
		$('tickswitchlink').setProperty('title','Deselect All');
		$('tickswitchlink').innerHTML = 'Deselect All';
	} else {
		$('tickswitch').value=0;
		$('tickswitch').setProperty('title','Select All');
		$('tickswitchlink').setProperty('title','Select All');
		$('tickswitchlink').innerHTML = 'Select All';
	}
}

function BulkEdit(action, bankey)
{
	option = action.options[action.selectedIndex].value
	ids = new Array();
	for(var i=0;$('chkb_' + i);i++)
	{
		if($('chkb_' + i).checked===true)
			ids.push($('chkb_' + i).value);
	}
	switch(option)
	{
		case "U":
			UnbanBan(ids, bankey, "", "Bulk Unban", "1", "true");
		break;
		case "D":
			RemoveBan(ids, bankey, "", "Bulk Delete", "0", "true");
		break;
	}
}

function BanFriendsProcess(fid, name)
{
	var checkUp = confirm("Are you sure you want to ban all steam community friends of '"+name+"'?");
	if(checkUp == false)
		return;
	ShowBox("Banning friends of "+name, "Banning all steam community friends of '"+name+"'.<br />Please wait...<br />This can last very long, depending on the amount of friends.", 'blue', '', true);
	$('dialog-control').setStyle('display', 'none');
	xajax_BanFriends(fid, name);
}

function OpenMessageBox(sid, name, popup)
{
	if(popup==1) {
		ShowBox('Send Message', '<b>Please type the message you want to send to <br>\''+name+'\'.</b><br>You need to have basechat.smx enabled as we use<br><i>&lt;sm_psay&gt;</i>.<br><textarea rows="3" cols="40" name="ingamemsg" id="ingamemsg" style="overflow:auto;"></textarea><br><div id="ingamemsg.msg" class="badentry"></div>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" name="ingmsg" class="btn ok" onmouseover="ButtonOver(\'ingmsg\')" onmouseout="ButtonOver(\'ingmsg\')" id="ingmsg" value="Send Message" />&nbsp;<input type="button" onclick="closeMsg(\'\');" name="astop" class="btn cancel" onmouseover="ButtonOver(\'astop\')" onmouseout="ButtonOver(\'astop\')" id="astop" value="Cancel" />');
		$('ingmsg').addEvent('click', function(){OpenMessageBox(sid, name, 0);});
	} else if(popup==0) {
		message = $('ingamemsg').value;
		if(message == "") {
			$('ingamemsg.msg').setHTML("Please type your message.");
			$('ingamemsg.msg').setStyle('display', 'block');
			return;
		} else {
			$('ingamemsg.msg').setHTML('');
			$('ingamemsg.msg').setStyle('display', 'none');
		}
		$('dialog-control').setStyle('display', 'none');
		$('ingamemsg').readOnly = true;
		xajax_SendMessage(sid, name, message);
	}
}

function KickPlayerConfirm(sid, name, conf)
{
	if(conf==0)	{
		ShowBox('Kick Player', '<b>Are you sure you want to kick player  <br>\''+name+'\'?</b>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" name="kbutton" class="btn ok" onmouseover="ButtonOver(\'kbutton\')" onmouseout="ButtonOver(\'kbutton\')" id="kbutton" value="Yes" />&nbsp;<input type="button" onclick="closeMsg(\'\');" name="astop" class="btn cancel" onmouseover="ButtonOver(\'astop\')" onmouseout="ButtonOver(\'astop\')" id="astop" value="No" />');
		$('kbutton').addEvent('click', function(){KickPlayerConfirm(sid, name, 1);});
	} else if(conf==1) {
		$('dialog-control').setStyle('display', 'none');
		xajax_KickPlayer(sid, name);
	}
}

function mapimg(filename)
{
	$('mapimg.msg').setHTML("Uploaded: <b>" + filename + "</b>");
}

function selectLengthTypeReason(length, type, reason)
{
	for(var i=0; i<=$('banlength').length ; i++) {
		if($('banlength').options[i].value == (length / 60)) {
			$('banlength').options[i].selected=true;
			break;
		}
	}
	$('type').options[type].selected = true;
	for(var i=0;i<=$('listReason').length;i++)	{
		if($('listReason').options[i].innerHTML == reason) {
			$('listReason').options[i].selected=true;
			break;
		}
		if($('listReason').options[i].value == 'other') {
			$('txtReason').value = reason;
			$('dreason').style.display = 'block';
			$('listReason').options[i].selected=true;
			break;
		}
	}
}

function ViewCommunityProfile(sid, name)
{
    ShowBox('View Community Profile', 'Generating Community Profile link for "'+name+'", please wait...', 'blue', '', true);
    $('dialog-control').setStyle('display', 'none');
    xajax_ViewCommunityProfile(sid, name);
}

// Thanks to http://phpjs.org/functions/addslashes:303
function addslashes (str)
{
	return (str + '').replace(/[\\"']/g, '\\$&').replace(/\u0000/g, '\\0');
}

function RemoveBlock(id, key, page, name, confirm)
{
	if(confirm==0) {
		ShowBox('Delete Block', 'Are you sure you want to delete the block for '+ name + '?', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" onclick="RemoveBlock(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'1\''+');" name="rban" class="btn ok" onmouseover="ButtonOver(\'rban\')" onmouseout="ButtonOver(\'rban\')" id="rban" value="Remove Block" />&nbsp;<input type="button" onclick="closeMsg(\'\');$(\'bulk_action\').options[0].selected=true;" name="astop" class="btn cancel" onmouseover="ButtonOver(\'astop\')" onmouseout="ButtonOver(\'astop\')" id="astop" value="Cancel" />');
	} else if(confirm==1) {
		if(page != "")
			var pagelink = page;
		else
			var pagelink = "";
		window.location = "index.php?p=commslist" + pagelink + "&a=delete&id="+ id +"&key="+ key;
	}
}

function UnGag(id, key, page, name, popup)
{
	if(popup==1) {
		ShowBox('UnGag Reason', '<b>Please give a short comment, why you are going to ungag '+"\'"+ name +"\'"+'!</b><br><textarea rows="3" cols="40" name="ureason" id="ureason" style="overflow:auto;"></textarea><br><div id="ureason.msg" class="badentry"></div>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" onclick="UnGag(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'0\''+');" name="uban" class="btn ok" onmouseover="ButtonOver(\'uban\')" onmouseout="ButtonOver(\'uban\')" id="uban" value="UnGag Player" />&nbsp;<input type="button" onclick="closeMsg(\'\');" name="astop" class="btn cancel" onmouseover="ButtonOver(\'astop\')" onmouseout="ButtonOver(\'astop\')" id="astop" value="Cancel" />');
	} else if(popup==0) {
		if(page != "")
			var pagelink = page;
		else
			var pagelink = "";
		reason = $('ureason').value;
		if(reason == "") {
			$('ureason.msg').setHTML("Please leave a comment.");
			$('ureason.msg').setStyle('display', 'block');
			return;
		} else {
			$('ureason.msg').setHTML('');
			$('ureason.msg').setStyle('display', 'none');
		}
		window.location = "index.php?p=commslist" + pagelink + "&a=ungag&id="+ id +"&key="+ key +"&ureason="+ reason;
	}
}

function UnMute(id, key, page, name, popup)
{
	if(popup==1) {
		ShowBox('UnMute Reason', '<b>Please give a short comment, why you are going to unmute '+"\'"+ name +"\'"+'!</b><br><textarea rows="3" cols="40" name="ureason" id="ureason" style="overflow:auto;"></textarea><br><div id="ureason.msg" class="badentry"></div>', 'blue', '', true);
		$('dialog-control').setHTML('<input type="button" onclick="UnMute(\''+id+'\', \''+key+'\', \''+page+'\', \''+addslashes(name.replace(/\'/g,'\\\''))+'\', \'0\''+');" name="uban" class="btn ok" onmouseover="ButtonOver(\'uban\')" onmouseout="ButtonOver(\'uban\')" id="uban" value="UnMute Player" />&nbsp;<input type="button" onclick="closeMsg(\'\');" name="astop" class="btn cancel" onmouseover="ButtonOver(\'astop\')" onmouseout="ButtonOver(\'astop\')" id="astop" value="Cancel" />');
	} else if(popup==0) {
		if(page != "")
			var pagelink = page;
		else
			var pagelink = "";
		reason = $('ureason').value;
		if(reason == "") {
			$('ureason.msg').setHTML("Please leave a comment.");
			$('ureason.msg').setStyle('display', 'block');
			return;
		} else {
			$('ureason.msg').setHTML('');
			$('ureason.msg').setStyle('display', 'none');
		}
		window.location = "index.php?p=commslist" + pagelink + "&a=unmute&id="+ id +"&key="+ key +"&ureason="+ reason;
	}
}

function search_blocks()
{
	var type = "";
	var input = "";
	if($('name').checked)
	{
		type = "name";
		input = $('nick').value;
	}
	if($('steam_').checked)
	{
		type = (document.getElementById('steam_match').value == "1" ? "steam" : "steamid");
		input = $('steamid').value;
	}
	if($('reason_').checked)
	{
		type = "reason";
		input = $('ban_reason').value;
	}
	if($('date').checked)
	{
		type = "date";
		input = $('day').value + "," + $('month').value + "," + $('year').value;
	}
	if($('length_').checked)
	{
		type = "length";
		if($('length').value=="other")
			var length = $('other_length').value;
		else
			var length = $('length').value
		input = $('length_type').value + "," + length;
	}
	if($('ban_type_').checked)
	{
		type = "btype";
		input = $('ban_type').value;
	}
	if($('bancount').checked)
	{
		type = "bancount";
		input = $('timesbanned').value;
	}
	if($('admin').checked)
	{
		type = "admin";
		input = $('ban_admin').value;
	}
	if($('where_banned').checked)
	{
		type = "where_banned";
		input = $('server').value;
	}
	if($('comment_').checked)
	{
		type = "comment";
		input = $('ban_comment').value;
	}
	if(type!="" && input!="")
		window.location = "index.php?p=commslist&advSearch=" + input + "&advType=" + type;
}

function ShowBlockBox(check, type, length)
{
	ShowBox('Block Added', 'The block has been successfully added<br><iframe id="srvkicker" frameborder="0" width="100%" src="pages/admin.blockit.php?check='+check+'&type='+type+'&length='+length+'"></iframe>', 'green', 'index.php?p=admin&c=comms', true);
}
