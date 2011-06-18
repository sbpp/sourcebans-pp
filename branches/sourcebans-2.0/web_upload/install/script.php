<?php
header('Content-Type: text/javascript; charset=UTF-8');
/*require_once 'bootstrap.php';
require_once SITE_DIR . 'includes/AjaxFunctions.php';
require_once 'includes/AjaxFunctions.php';*/

if(isset($_POST['func']))
  exit(sAJAX::call($_POST['func'], isset($_POST['args']) ? json_decode(stripslashes($_POST['args'])) : null));
?>
function sajax_call(func, args)
{
  var data = new Array();
  for(var i = 0; i < args.length; i++)
    data.push(args[i]);
  
  new Request.JSON({
    url: 'script.php',
    onComplete: function(res) {
      callback = args[args.length - 1];
      if(typeof(callback) == 'function')
        callback(res);
    }
  }).post({'func': func, 'args': JSON.encode(data)});
}

<?php //foreach(sAJAX::getFunctions() as $func): ?>
function x_<?php echo $func; ?>() { sajax_call('<?php echo $func; ?>', x_<?php echo $func; ?>.arguments); }
<?php //endforeach; ?>

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
    if(this.get('value') == 'Finish')
    {
      x_SetupDatabase($('db_host').get('value'),
                      $('db_port').get('value'),
                      $('db_user').get('value'),
                      $('db_pass').get('value'),
                      $('db_name').get('value'),
                      $('db_prefix').get('value'));
      x_SetupAdmin($('username').get('value'),
                   $('password').get('value'),
                   $('password_confirm').get('value'),
                   $('email').get('value'),
                   $('auth').get('value'),
                   $('identity').get('value'));
    }
    
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