<?php
header('Content-Type: text/javascript; charset=UTF-8');
require_once 'init.php';
require_once LIB_DIR . 'sajax/sajax.php';
require_once 'includes/sajax.php';

if(isset($_POST['func']))
  exit(sAJAX::call($_POST['func'], isset($_POST['args']) ? json_decode(stripslashes($_POST['args'])) : null));
?>
function sajax_call(func, args)
{
  var data = new Array();
  for(var i = 0; i < args.length; i++)
    data.push(args[i]);
  
  new Request.JSON({
    url: 'scripts/sajax.php',
    onComplete: function(res) {
      callback = args[args.length - 1];
      if(typeof(callback) == 'function')
        callback(res);
    }
  }).post({'func': func, 'args': JSON.encode(data)});
}

<?php foreach(sAJAX::getFunctions() as $func): ?>
function x_<?php echo $func; ?>() { sajax_call('<?php echo $func; ?>', x_<?php echo $func; ?>.arguments); }
<?php endforeach; ?>

var step = 1;

window.addEvent('domready', function() {
  $$('.step').each(function(el) {
    if(el.id != 'step-' + step)
      el.fade('hide');
  });
  
  $('back').addEvent('click', function(e) {
    $('step-' + step).fade('out');
    $('step-' + --step).fade('in');
    if(!$chk($('step-' + (step - 1))))
      this.disabled      = true;
    if($chk($('step-'  + (step + 1))))
      $('next').disabled = false;
    
    if(step != 4)
      $('next').set('value', 'Next >>');
  }).disabled = true;
  $('next').addEvent('click', function(e) {
    $('step-' + step).fade('out');
    $('step-' + ++step).fade('in');
    if($chk($('step-'  + (step - 1))))
      $('back').disabled = false;
    if(!$chk($('step-' + (step + 1))))
      this.disabled      = true;
    
    if(step == 4)
      this.set('value', 'Finish');
  }).disabled = false;
});