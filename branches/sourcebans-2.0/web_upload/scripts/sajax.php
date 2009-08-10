<?php
header('Content-Type: text/javascript; charset=UTF-8');
require_once '../init.php';
require_once LIB_DIR   . 'sajax/sajax.php';
require_once UTILS_DIR . 'sajax.php';

if(isset($_POST['func'])):
  echo sAJAX::call($_POST['func'], isset($_POST['args']) ? json_decode(stripslashes($_POST['args'])) : null);
else:
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

<?php
  foreach(sAJAX::getFunctions() as $func):
?>
function x_<?php echo $func; ?>() { sajax_call('<?php echo $func; ?>', x_<?php echo $func; ?>.arguments); }
<?php 
  endforeach;
endif;
?>