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
