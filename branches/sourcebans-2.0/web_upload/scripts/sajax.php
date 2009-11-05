<?php
header('Content-Type: text/javascript; charset=UTF-8');
require_once '../init.php';
require_once LIB_DIR   . 'sajax/sajax.php';
require_once UTILS_DIR . 'sajax.php';

if(isset($_POST['func']))
  exit(sAJAX::call($_POST['func'], isset($_POST['args']) ? json_decode(stripslashes($_POST['args'])) : null));
?>
function sajax_call(func, args)
{
  // Convert array-like object to array
  args = Array.prototype.slice.call(args);
  
  new Request.JSON({
    url: 'scripts/sajax.php',
    onComplete: function(res) {
      callback = args[args.length - 1];
      if(typeof(callback) == 'function')
        callback(res);
    }
  }).post({'func': func, 'args': JSON.encode(args)});
}

<?php foreach(sAJAX::getFunctions() as $func): ?>
function x_<?php echo $func; ?>() { sajax_call('<?php echo $func; ?>', arguments); }
<?php endforeach; ?>