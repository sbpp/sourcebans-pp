<?php
if(isset($_POST['name']))
{
  require_once '../bootstrap.php';
  Includes::requireOnce('../ajax.php');
  
  exit(json_encode(call_user_func_array(
    array('AjaxFunctions', $_POST['name']),
    isset($_POST['args']) ? json_decode(stripslashes($_POST['args'])) : null
  )));
}

header('Content-Type: text/javascript; charset=UTF-8');
?>
function x()
{
  // Convert array-like object to array
  var args = Array.prototype.slice.call(arguments);
  var name = args.shift();
  
  new Request.JSON({
    url: '<?php echo $_SERVER['SCRIPT_NAME']; ?>',
    onComplete: function(res) {
      callback = args[args.length - 1];
      if(typeof(callback) == 'function')
        callback(res);
    }
  }).post({'name': name, 'args': JSON.encode(args)});
}