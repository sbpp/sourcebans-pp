<?php
class AjaxFunctions extends BaseDataObject
{
  public function __call($name, $arguments)
  {
    if(!Util::in_array($name, $this->_data))
      throw new Exception('The function "' . $name . '" is not registered and cannot be called.');
    
    return json_encode(call_user_func_array($name, $arguments));
  }
  
  
  public function register($name)
  {
    if(Util::in_array($name, $this->_data))
      throw new Exception('The function "' . $name . '" is already registered.');
    
    $this->_data[] = $name;
  }
}