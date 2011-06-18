/*
 @description		mootools based context menu
 @author			Daniel Niquet | http://utils.softr.net / Jannik Hartung | http://www.interwavestudios.com
 @based in			http://thinkweb2.com/projects/prototype
 @version			0.7
 @date				03/18/09
 @requires			mootools 1.20
*/
contextMenoo = new Class({
	Implements:Options,
	options:{
		selector: '.contextmenu', className: '.protoMenu', pageOffset: 25, fade: false, headline: 'Menu'
	},
	initialize: function (op) {
		this.setOptions(op);
		this.cont=new Element('div',{'class': this.options.className});
		
		this.cont.adopt(new Element('b', {'class': 'head'}).set('html', this.options.headline));
		this.options.menuItems.each(function(item){
			this.cont.adopt(item.separator ? 
				new Element('div', {'class': 'separator'}) :
				item.disabled ? 
				new Element('a', {	'href': '#', 'title': item.name, 'class': 'disabled', 'onclick': 'return false;'}).set('html', item.name):
				new Element('a', {	'href': '#', 'title': item.name, 'onclick': 'return false;'}).addEvent('click', this.onClick.bindWithEvent(this,[item.callback])).set('html', item.name))
		}.bind(this));
		this.cont.set('opacity', 0);
		$(document.body).adopt(this.cont);
		document.addEvents({
			'click':this.hide.bind(this),'contextmenu':this.hide.bind(this)
		});

		$$(this.options.selector).each(function(el){
			el.addEvent(window.opera?'click':'contextmenu',function(e){
				if(window.opera && !e.control) return;
				this.show(e);
			}.bind(this));
		},this);
	},
	hide: function(){this.cont.set('opacity', 0);},
	show: function(e) {
		e=new Event(e).stop();
		var oCont=this.cont.getCoordinates(),
		size = {'height':window.getHeight(), 'width':window.getWidth(), 'top': window.getScrollTop(),'cW':oCont.width, 'cH':oCont.height};

		this.cont.setStyles({
			left: ((e.page.x + size.cW + this.options.pageOffset) > size.width ? (size.width - size.cW - this.options.pageOffset) : e.page.x),
			top: ((e.page.y - size.top + size.cH) > size.height && (e.page.y - size.top) > size.cH ? (e.page.y - size.cH) : e.page.y)
		});
		this.cont.set('opacity', 0);
		this.options.fade?this.cont.fade(1):this.cont.set('opacity', 1);
	},
	onClick:function(e,args){
		if (args && !e.target.hasClass('disabled')) this.cont.set('opacity', 0);args();
	}
});