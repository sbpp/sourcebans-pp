// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2016 Sarabveer Singh <me@sarabveer.me>
//
//  SourceBans++ is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, per version 3 of the License.
//
//  SourceBans++ is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with SourceBans++. If not, see <http://www.gnu.org/licenses/>.
//
//  This file is based off work covered by the following copyright(s):
//
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************
function FadeElIn(id, time)
{
	$(document.getElementById(id)).setStyle('display', 'block');
	var myEffects = $(id).effects({duration: time, transition:Fx.Transitions.Sine.easeIn});
	myEffects.start({'opacity': [1]});
	setTimeout("$(document.getElementById('" + id + "')).setOpacity(1);", time);
	return;
}

function FadeElOut(id, time)
{
	var myEffects = $(id).effects({duration: time, transition:Fx.Transitions.Sine.easeOut});
	myEffects.start({'opacity': [0]});
	var d = id;
	setTimeout("$(document.getElementById('" + d + "')).setStyle('display', 'none');$(document.getElementById('" + d + "')).setOpacity(0);", time);

	return;
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

function TabToReload()
{
	var url = window.location.toString();
	var nurl = "window.location = '" + url.replace("#^" + url[url.length-1],"") + "'";
	$('admin_tab_0').setProperty('onclick', nurl);
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
