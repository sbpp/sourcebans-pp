<?php
require_once READERS_DIR . 'logs.php';

class LogsWriter
{
  /**
   * Adds a log
   *
   * @param  string $type    The type of the log
   * @param  string $title   The title of the log
   * @param  string $message The message of the log
   * @return The id of the added log
   */
  public static function add($type, $title, $message)
  {
    if(empty($type)    || !is_string($type))
      throw new Exception('Invalid type specified.');
    if(empty($title)   || !is_string($title))
      throw new Exception('Invalid title specified.');
    if(empty($message) || !is_string($message))
      throw new Exception('Invalid message specified.');
    
    $db        = Env::get('db');
    $userbank  = Env::get('userbank');
    $bt        = debug_backtrace();
    $function  = isset($bt[2]['file']) ? $bt[2]['file'] . ' - ' . $bt[2]['line'] . "\n" : '';
    $function .= isset($bt[3]['file']) ? $bt[3]['file'] . ' - ' . $bt[3]['line'] . "\n" : '';
    $function .= isset($bt[4]['file']) ? $bt[4]['file'] . ' - ' . $bt[4]['line'] . "\n" : ''; 
    $function .= isset($bt[5]['file']) ? $bt[5]['file'] . ' - ' . $bt[5]['line'] . "\n" : '';
    $function .= isset($bt[6]['file']) ? $bt[6]['file'] . ' - ' . $bt[6]['line'] . "\n" : '';
    
    $db->Execute('INSERT INTO ' . Env::get('prefix') . '_log (type, title, message, function, query, admin_id, admin_ip, time)
                  VALUES      (?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP())',
                  array($type, $title, $message, $function, $_SERVER['QUERY_STRING'], $userbank->GetID(), $_SERVER['REMOTE_ADDR']));
    
    $id          = $db->Insert_ID();
    $logs_reader = new LogsReader();
    $logs_reader->removeCacheFile();
    
    SBPlugins::call('OnAddLog', $id, $type, $title, $message);
    
    return $id;
  }
  
  
  /**
   * Clears the logs
   */
  public static function clear()
  {
    $db = Env::get('db');
    
    $db->Execute('TRUNCATE TABLE ' . Env::get('prefix') . '_log');
    
    $logs_reader = new LogsReader();
    $logs_reader->removeCacheFile();
    
    SBPlugins::call('OnClearLogs');
  }
}
?>