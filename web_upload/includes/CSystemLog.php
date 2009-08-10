<?php
/**
 * =============================================================================
 * System log handler
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: CSystemLog.php 153 2008-09-16 20:46:23Z peace-maker $
 * =============================================================================
 */

class CSystemLog {
	var $log_list = array();
	var $type = "";
	var $title = "";
	var $msg = "";
	var $aid = 0;
	var $host = "";
	var $created = 0;
	var $parent_function = "";
	var $query = "";
	
	function CSystemLog($tpe="", $ttl="", $mg="", $done=true, $HideDebug = false)
	{
		global $userbank;
		if(!empty($tpe) && !empty($ttl) && !empty($mg))
		{
			$this->type = $tpe;
			$this->title = $ttl;
			$this->msg = $mg;
			// if (!$HideDebug && ((isset($_GET['debug']) && $_GET['debug'] == 1) || defined("DEVELOPER_MODE")))
			// {
				// echo "CSystemLog: " . $mg;
			// }
			
			if( !$userbank )
				return false;
			
			$this->aid =  $userbank->GetAid()?$userbank->GetAid():"-1";
			$this->host = $_SERVER['REMOTE_ADDR'];
			$this->created = time(); 
			$this->parent_function = $this->_getCaller();
			$this->query = isset($_SERVER['QUERY_STRING'])?$_SERVER['QUERY_STRING']:'';
			if(isset($done) && $done == true)
				$this->WriteLog();
		}				
	}
	
	function AddLogItem($tpe, $ttl, $mg)
	{
		$item = array();
		$item['type'] = $tpe;
		$item['title'] = $ttl;
		$item['msg'] = $mg;
		$item['aid'] =  SB_AID;
		$item['host'] = $_SERVER['REMOTE_ADDR'];
		$item['created'] = time(); 
		$item['parent_function'] = $this->_getCaller();
		$item['query'] = $_SERVER['QUERY_STRING'];
		
		array_push($this->log_list, $item);
	}
	
	function WriteLogEntries()
	{
		$this->log_list = array_unique($this->log_list);
		foreach($this->log_list as $logentry)
		{
			if(!$logentry['query'])
				$logentry['query'] = "N/A";
			if(isset($GLOBALS['db']))
			{
				$sm_log_entry = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_log(type,title,message, function, query, aid, host, created)
						VALUES (?,?,?,?,?,?,?,?)");
				$GLOBALS['db']->Execute($sm_log_entry,array($logentry['type'], $logentry['title'], $logentry['msg'], (string)$logentry['parent_function'],$logentry['query'], $logentry['aid'], $logentry['host'], $logentry['created']));
			}
		}
		unset($this->log_list);
	}
	
	function WriteLog()
	{
		if(!$this->query)
			$this->query = "N/A";
		if(isset($GLOBALS['db']))
		{
			$sm_log_entry = $GLOBALS['db']->Prepare("INSERT INTO ".DB_PREFIX."_log(type,title,message, function, query, aid, host, created)
						VALUES (?,?,?,?,?,?,?,?)");
			$GLOBALS['db']->Execute($sm_log_entry,array($this->type, $this->title, $this->msg, (string)$this->parent_function,$this->query, $this->aid, $this->host, $this->created));
		}
	}
	
	function _getCaller()
	{
		$bt = debug_backtrace();
	
		$functions = isset($bt[2]['file'])?$bt[2]['file'] . " - " . $bt[2]['line'] . "<br />":'';
		$functions .= isset($bt[3]['file'])?$bt[3]['file'] . " - " . $bt[3]['line'] . "<br />":'';
		$functions .= isset($bt[4]['file'])?$bt[4]['file'] . " - " . $bt[4]['line'] . "<br />":''; 
		$functions .= isset($bt[5]['file'])?$bt[5]['file'] . " - " . $bt[5]['line'] . "<br />":'';
		$functions .= isset($bt[6]['file'])?$bt[6]['file'] . " - " . $bt[6]['line'] . "<br />":'';
		return $functions;
	}
	
	function GetAll($start, $limit, $searchstring="")
	{
		if( !is_object($GLOBALS['db']) )
				return false;
				
		$start = (int)$start;
		$limit = (int)$limit;
		$sm_logs = $GLOBALS['db']->GetAll("SELECT ad.user, l.type, l.title, l.message, l.function, l.query, l.host, l.created, l.aid 
										   FROM ".DB_PREFIX."_log AS l
										   LEFT JOIN ".DB_PREFIX."_admins AS ad ON l.aid = ad.aid
										   ".$searchstring."
										   ORDER BY l.created DESC 
										   LIMIT $start, $limit");
		return $sm_logs;
	}
	
	function LogCount($searchstring="")
	{
		$sm_logs = $GLOBALS['db']->GetRow("SELECT count(l.lid) AS count FROM ".DB_PREFIX."_log AS l".$searchstring);
		return $sm_logs[0];
	}
	
	function CountLogList()
	{
		return count($this->log_list);
	}
	
}

?>
