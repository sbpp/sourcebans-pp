var step = 1;

window.addEvent('domready', function() {
  $$('.step').each(function(el) {
    if(el.id != 'step-1')
      el.set('opacity', 0);
  });
  
  $('back').addEvent('click', function(e) {
    $('step-' + step).fade('out');
    $('step-' + --step).fade('in');
    if(!$chk($('step-' + (step - 1))))
      this.disabled      = true;
    if($chk($('step-'  + (step + 1))))
      $('next').disabled = false;
  });
  $('next').addEvent('click', function(e) {
    $('step-' + step).fade('out');
    $('step-' + ++step).fade('in');
    if($chk($('step-'  + (step - 1))))
      $('back').disabled = false;
    if(!$chk($('step-' + (step + 1))))
      this.disabled      = true;
  });
});