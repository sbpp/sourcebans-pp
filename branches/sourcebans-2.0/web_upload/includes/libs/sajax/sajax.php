<?php
class sAJAX
{
  private static $functions = array();
  
  public static function call($name, $args)
  {
    if(!isset(self::$functions[$name]))
      throw new Exception('The function name "' . $name . '" is not registered and cannot be called.');
    
    return json_encode(call_user_func_array(self::$functions[$name], $args));
  }
  
  public static function getFunctions()
  {
    return self::$functions;
  }
  
  public static function register($name, $func)
  {
    if(isset(self::$functions[$name]))
      throw new Exception('The function name "' . $name . '" is already registered.');
    
    self::$functions[$name] = $func;
  }
}
?>