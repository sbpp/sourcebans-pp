<?php
/**
 * System log handler
 * 
 * @author    SteamFriends, InterWave Studios, GameConnect
 * @copyright (C)2007-2011 GameConnect.net.  All rights reserved.
 * @link      http://www.sourcebans.net
 * @package   SourceBans
 * @version   $Id$
 */
class CSystemLog
{
  protected $log_list = array();
  protected $type = '';
  protected $title = '';
  protected $msg = '';
  protected $aid = 0;
  protected $host = '';
  protected $created = 0;
  protected $parent_function = '';
  protected $query = '';
  
  function __construct($type = '', $title = '', $msg = '', $done = true, $HideDebug = false)
  {
    global $userbank;
    if(!$userbank || empty($type) || empty($title) || empty($msg))
      return;
    
    //if (!$HideDebug && ((isset($_GET['debug']) && $_GET['debug'] == 1) || defined('DEVELOPER_MODE')))
    //{
    //  echo 'CSystemLog: ' . $mg;
    //}
    
    $this->type = $type;
    $this->title = $title;
    $this->msg = $msg;
    $this->aid =  $userbank->GetAid() ? $userbank->GetAid() : -1;
    $this->host = $_SERVER['REMOTE_ADDR'];
    $this->created = time(); 
    $this->parent_function = $this->_getCaller();
    $this->query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
    
    if($done)
    {
      $this->WriteLog();
    }
  }
  
  
  public function AddLogItem($type, $title, $msg)
  {
    $this->log_list[] = array(
      'type' => $type,
      'title' => $title,
      'msg' => $msg,
      'aid' =>  SB_AID,
      'host' => $_SERVER['REMOTE_ADDR'],
      'created' => time(),
      'parent_function' => $this->_getCaller(),
      'query' => $_SERVER['QUERY_STRING'],
    );
  }
  
  
  public function WriteLogEntries()
  {
    if(!isset($GLOBALS['db']))
      return;
    
    $q = $GLOBALS['db']->Prepare('INSERT INTO ' . DB_PREFIX . '_log (type, title, message, function, query, aid, host, created)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    
    foreach(array_unique($this->log_list) as $logentry)
    {
      if(empty($logentry['query']))
      {
        $logentry['query'] = 'N/A';
      }
      
      $GLOBALS['db']->Execute($q, array(
        $logentry['type'],
        $logentry['title'],
        $logentry['msg'],
        (string)$logentry['parent_function'],
        $logentry['query'],
        $logentry['aid'],
        $logentry['host'],
        $logentry['created'],
      ));
    }
    
    unset($this->log_list);
  }
  
  public function WriteLog()
  {
    if(!isset($GLOBALS['db']))
      return;
    
    $q = $GLOBALS['db']->Prepare('INSERT INTO ' . DB_PREFIX . '_log (type, title, message, function, query, aid, host, created)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    
    if(empty($this->query))
    {
      $this->query = 'N/A';
    }
    
    $GLOBALS['db']->Execute($q, array(
      $this->type,
      $this->title,
      $this->msg,
      (string)$this->parent_function,
      $this->query,
      $this->aid,
      $this->host,
      $this->created,
    ));
  }
  
  
  public function _getCaller()
  {
    $bt = debug_backtrace();
    
    $functions  = isset($bt[2]['file']) ? $bt[2]['file'] . ' - ' . $bt[2]['line'] . '<br />' : '';
    $functions .= isset($bt[3]['file']) ? $bt[3]['file'] . ' - ' . $bt[3]['line'] . '<br />' : '';
    $functions .= isset($bt[4]['file']) ? $bt[4]['file'] . ' - ' . $bt[4]['line'] . '<br />' : ''; 
    $functions .= isset($bt[5]['file']) ? $bt[5]['file'] . ' - ' . $bt[5]['line'] . '<br />' : '';
    $functions .= isset($bt[6]['file']) ? $bt[6]['file'] . ' - ' . $bt[6]['line'] . '<br />' : '';
    return $functions;
  }
  
  
  public function GetAll($start, $limit, $searchstring = '')
  {
    if(!isset($GLOBALS['db']))
      return false;
    
    return $GLOBALS['db']->GetAll('SELECT    ad.user, l.type, l.title, l.message, l.function, l.query, l.host, l.created, l.aid
                                   FROM      ' . DB_PREFIX . '_log AS l
                                   LEFT JOIN ' . DB_PREFIX . '_admins AS ad ON l.aid = ad.aid
                                   ' . $searchstring . '
                                   ORDER BY  l.created DESC 
                                   LIMIT     ' . (int)$start . ', ' . (int)$limit);
  }
  
  
  public function LogCount($searchstring = '')
  {
    return $GLOBALS['db']->GetOne('SELECT COUNT(l.lid)
                                   FROM ' . DB_PREFIX . '_log AS l ' . $searchstring);
  }
  
  
  public function CountLogList()
  {
    return count($this->log_list);
  }
}