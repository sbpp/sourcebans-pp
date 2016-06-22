/*
 @description		mootools based context menu
 @author			Daniel Niquet | http://utils.softr.net
 @based in			http://thinkweb2.com/projects/prototype
 @version			0.5
 @date				8/25/07
 @requires			mootools 1.11
*/
contextMenoo = new Class({
	options:{
		selector: '.contextmenu', className: '.protoMenu', pageOffset: 25, fade: false, headline: 'Menu'
	},
	initialize: function (op) {
		this.setOptions(op);
		this.cont=new Element('div',{'class': this.options.className});
		this.Fade=this.cont.effect('opacity');
		
		this.cont.adopt(new Element('b', {'class': 'head'}).setHTML(this.options.headline));
		this.options.menuItems.each(function(item){
			this.cont.adopt(item.separator ? 
				new Element('div', {'class': 'separator'}) :
				new Element('a', {	'href': '#', 'title': item.name, 'onclick': 'return false;', 'class': item.disabled ? 'disabled' : ''}).addEvent('click', this.onClick.bindWithEvent(this,[item.callback])).setHTML(item.name))
		}.bind(this));
		this.Fade.set(0);
		$(document.body).adopt(this.cont);
		document.addEvents({
			'click':this.hide.bind(this),'contextmenu':this.hide.bind(this)
		});

		$$(this.options.selector).each(function(el){
			el.addEvent(window.opera?'click':'contextmenu',function(e){
				if(window.opera && !e.ctrlKey) return;
				this.show(e);
			}.bind(this));
		},this);
	},
	hide: function(){this.Fade.set(0);},
	show: function(e) {
		e=new Event(e).stop();
		var oCont=this.cont.getCoordinates(),
		size = {'height':window.getHeight(), 'width':window.getWidth(), 'top': window.getScrollTop(),'cW':oCont.width, 'cH':oCont.height};

		this.cont.setStyles({
			left: ((e.page.x + size.cW + this.options.pageOffset) > size.width ? (size.width - size.cW - this.options.pageOffset) : e.page.x),
			top: ((e.page.y - size.top + size.cH) > size.height && (e.page.y - size.top) > size.cH ? (e.page.y - size.cH) : e.page.y)
		});
		this.Fade.set(0);
		this.options.fade?this.Fade.start(0,1):this.Fade.set(1);
	},
	onClick:function(e,args){
		if (args && !e.target.hasClass('disabled')) this.Fade.set(0);args();
	}
});
contextMenoo.implement(new Options());

function AddContextMenu(select, classNames, fader, headl, oLinks)
{
	window.addEvent('domready', function(){

		var menuObj = new contextMenoo({
			selector: select,
			className: classNames,
			fade: fader,
			menuItems: oLinks,
			headline: headl
		});
		
	});
}