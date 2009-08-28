<?php
  class sAJAX
  {
    private static $functions = array();
    
    public static function call($func, $args)
    {
      if(!in_array($func, self::$functions))
        throw new sAJAXException('The function name "' . $func . '" is not registered and cannot be called.');
      
      return json_encode(call_user_func_array($func, $args));
    }
    
    public static function getFunctions()
    {
      return self::$functions;
    }
    
    public static function register($func)
    {
      if(in_array($func, self::$functions))
        throw new sAJAXException('The function name "' . $func . '" is already registered.');
      
      self::$functions[] = $func;
    }
  }
?>