/**
 * =============================================================================
 * JavaScript functions, and AJAX calls
 * 
 * @author InterWave Studios Development Team
 * @version 2.0.0
 * @copyright SourceBans (C)2008 InterWaveStudios.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: sourcebans.js 24 2007-11-06 18:17:05Z olly $
 * =============================================================================
 */

function InitAccordion(opener, element, container, num)
{
  return new Accordion(opener, element, {
    display: num == null ? -1 : num,
    opacity: true,
    alwaysHide: true,
    transition: Fx.Transitions.Quart.easeOut,
    onActive: function(toggler, element) {
      toggler.setStyle('cursor',           'pointer');
      toggler.setStyle('background-color', '');
    },
    onBackground: function(toggler, element) {
      toggler.setStyle('cursor',           'pointer');
      toggler.setStyle('background-color', '');    
    }
  }, $(container)).hideAll();
}

function MarkPasswordField(el)
{
  el.password = el.value;
  
  el.addEvent('blur',  function(e) {
    if(this.value == '')
      this.value = this.password;
  });
  el.addEvent('focus', function(e) {
    if(this.value == this.password)
      this.value = '';
  });
}

function Shrink(id, time, height)
{
  $(id).effects({
    duration: time,
    transition: Fx.Transitions.Bounce.easeOut
  }).start({'height': [height]});
}

function SlideUp(id)
{
  new Fx.Slide(id).slideOut().chain(function() {
    $(id).remove();
  });
}

function SubmitForm(el, cb_complete)
{
  new Element('iframe', {
    'name': 'submit_form',
    'events': {
      'load': function() {
        if(typeof(cb_complete) == 'function')
          cb_complete(this.contentWindow.document.body.innerHTML);
        
        $('ajax-indicator').setStyle('display', 'none');
        this.dispose();
      }
    },
    'styles': {
      'display': 'none'
    }
  }).inject(el);
  
  $('ajax-indicator').setStyle('display', 'block');
  $(el).set('target', 'submit_form').submit();
}

function UpdateCheckBox(objCheckbox)
{
  // Other Arguments is individual items not available in the range
  if(arguments.length < 2)
    return;
  
  for(var i = 1; i < arguments.length; i++)
  {
    if($(arguments[i]))
      $(arguments[i]).checked = objCheckbox.checked;
  }
}


Accordion.implement({
  hideAll: function() {
    var obj       = {};
    this.previous = -1;
    this.elements.each(function(el, i) {
      obj[i] = {};
      this.fireEvent('onBackground', [this.togglers[i], el]);
      for(var fx in this.effects) obj[i][fx] = 0;
    }, this);
    return this.start(obj);
  },
  showAll: function() {
    var obj       = {};
    this.previous = -1;
    this.elements.each(function(el, i) {
      obj[i] = {};
      this.fireEvent('onActive', [this.togglers[i], el]);
      for(var fx in this.effects) obj[i][fx] = el[this.effects[fx]];
    }, this);
    return this.start(obj);
  }
});