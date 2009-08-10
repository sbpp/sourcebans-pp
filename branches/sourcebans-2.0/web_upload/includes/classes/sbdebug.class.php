<?php

/**
 * SBDebug is used to extend Exception for logging and trace is used to log messages to a file
 * 
 * This class uses DEBUG_FILE and DEBUG_LOGLEVEL contsants for the purpose of logging categories
 * infromation to a logfile.  These constants can be definded in sourcebans root directory in
 * config.php.  It is not reqired to be defined and therefor will act as a normal Exception class.
 * <code>
 * define("DEBUG_FILE", '/home/myuser/public_html/sourcebans/themes_c/sbdebug.log');
 * define("DEBUG_LOGLEVEL", SBDebug::LL_VERBOSE);
 * </code>
 */
class SBDebug extends Exception
{
  /* Log Levels */
  const LL_VERBOSE = 1;
  const LL_ERROR = 2;
  const LL_SECUIRTY = 4;
  const LL_INFO = 8;
  const LL_WARNING = 16;

  /**
   * __toString  Overload of Exception::__toString() used to track stacktrace infromtion
   * and optionally log to a file  
   * 
   * @return string    The message of the raised exception
   */
  public function __toString()
  {
    SBDebug::logItem("Exception Raised: " . SBDebug::stackTrace($this->getMessage()), SBDebug::LL_ERROR);
    return $this->getMessage();
  }

  /**
   * logLevel checks the bitwise levels passed against the contstant for DEBUG_LOGLEVEL
   * 
   * @param integer $LogLevel    Bitwise enumeration of Log Levels
   * @return void
   */
  public static function logLevel ($LogLevel)
  {
    return (!defined('DEBUG_LOGLEVEL') || (DEBUG_LOGLEVEL & $LogLevel) != 0 || (DEBUG_LOGLEVEL & LL_VERBOSE) != 0 ? true : false);
  }
  
  /**
   * logToFile Explicitly logs infromation to a given path/filename
   * 
   * @param $FileName string     Path and File to append information to
   * @param $Desc     mixed      Can be a static string or a variable.
   * @param $LogLevel integer    Log level to which this is information is limited to by DEBUG_LOGLEVEL
   * @param $Var      mixed      A option variable to pass as a var_export
   * @return void
   */
  public static function logToFile($FileName, $Desc, $LogLevel = self::LL_INFO, $Var = null)
  {
    if (!self::logLevel($LogLevel))
      return;
    
    $fs = fopen($FileName, "a");
    $Desc = self::printVar($Desc);
    
    if (isset($Var))
      $Var = self::printVar($Var);
    
    fwrite($fs, "[" . date("m/d/Y G:i") . "] " . $Desc);
    if (!empty($Var))
      fwrite($fs, " $Var");
    
    fwrite($fs, "\n");
    fclose($fs);
  }

  /**
   * logItem Logs infromation based on DEBUG_FILE and DEBUG_LOGLEVEL
   * 
   * @param $Desc     mixed      Can be a static string or a variable.
   * @param $LogLevel integer    Log level to which this is information is limited to by DEBUG_LOGLEVEL
   * @param $Var      mixed      A option variable passed to cleanly as plain visible text using printVar
   * @return void
   */
  public static function logItem($Desc, $LogLevel = self::LL_INFO, $Var = null)
  {
    if (defined('DEBUG_FILE') && strlen(constant('DEBUG_FILE')) > 0)
      self::logToFile(DEBUG_FILE, $Desc, $LogLevel, $Var);
  }

  /**
   * printVar takes a variable and makes it readable as plain text information to be appended to a log
   * 
   * @param $Var mixed   Whatever you want to see
   */
  public static function printVar($Var)
  {
    if (is_string($Var))
      return ('"' . str_replace(array("\x00", "\x0a", "\x0d", "\x1a", "\x09"), array('\0', '\n', '\r', '\Z', '\t'), $Var) . '"');
    
    if (is_bool($Var))
    {
      if ($Var)
        return ('true');
      
      return ('false');
    }
    
    if (is_array($Var))
    {
      $result = 'array( ';
      $comma = '';
      
      foreach ($Var as $key => $value)
      {
        $result .= $comma . self::printVar($key) . ' => ' . self::printVar($value);
        $comma = ', ';
      }
      
      $result .= ' )';
      return ($result);
    }
    
    return (var_export($Var, true)); // anything else, just let php try to print it
  }

  /**
   * stackTrace method takes where the Exception was created with a message passed
   * and returns it back in readable format.
   * 
   * @param $msg string  Message to prefix with stack trace wtih
   * @param $html bool   (default: false) Use to tell stackTrace to be formatted with htmlspecialchars in <pre> tags 
   * @return string      A formatted text 
   */
  public static function stackTrace($msg, $html = false)
  {
    $trace = ($html ? $msg : htmlspecialchars_decode($msg)) . "\n";
    $backtrace = array_reverse(debug_backtrace());
    $indent = '';
    $func = '';

    foreach ($backtrace as $val)
    {
      $trace .= $indent . $val['file'] . ' on line ' . $val['line'];
      
      if ($func)
        $trace .= ' in function ' . $func;
      
      if ($val['function'] == 'include' || $val['function'] == 'require' || $val['function'] == 'include_once' || $val['function'] == 'require_once')
        $func = '';
      else
      {
        $func = $val['function'] . '(';
        
        if (isset($val['args'][0]))
        {
          $func .= ' ';
          $comma = '';
          
          foreach ($val['args'] as $val)
          {
            $func .= $comma . self::printVar($val);
            $comma = ', ';
          }
          
          $func .= ' ';
        }
        
        $func .= ')';
      }
      
      $trace .= "\n";
      $indent .= "\t";
    }

    if ($html)
      return "<pre>$trace</pre><br />\n";
    else
      return $trace;
  }
}

?>
