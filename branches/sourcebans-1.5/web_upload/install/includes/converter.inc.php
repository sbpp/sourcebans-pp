<?php
	define('IN_SB', true);
	require_once("../config.php");
	
	include_once("../includes/adodb/adodb.inc.php");
	include_once("../includes/adodb/adodb-errorhandler.inc.php");
	

	function convertAmxbans($fromdsn,$todsn,$fromprefix,$toprefix)
	{
    	set_time_limit(0); //Never time out
    	ob_start();
    	$olddb = ADONewConnection($fromdsn);
        if (!$olddb)
        {
            die("Failed to connect to AMX Bans database");
        }
       	$olddb->Execute("SET NAMES utf8");

       	echo "Converting ".$fromprefix."_bans... ";
       	ob_flush();
		flush();
    	$res = $olddb->Execute("SELECT `player_ip`, `player_id`, `player_nick`, `ban_created`, `ban_length`, `ban_reason`, `admin_ip` FROM `".$fromprefix."_bans`");
    	$time = $olddb->GetRow("SELECT UNIX_TIMESTAMP() AS time FROM ".$fromprefix."_bans");
		
    	$newdb = ADONewConnection($todsn);
		 if (!$newdb)
        {
            die("Failed to connect to SourceBans database");
        }
		$newdb->Execute("SET NAMES utf8");
		$ins = $newdb->Prepare("INSERT INTO ".$toprefix."_bans(ip,authid,name,created,ends,length,reason,adminIp,aid) VALUES (?,?,?,?,?,?,?,?,?)");
    	
    	while (!$res->EOF)
    	{
        	$vals = array($res->fields[0],$res->fields[1],$res->fields[2],$res->fields[3],($res->fields[4] == 0 ? 0 : $res->fields[3]+$res->fields[4]),
							$res->fields[4],$res->fields[5],$res->fields[6],0);
    		
    		if($time > (intval($res->fields[3]) + intval($res->fields[4])) && $res->fields[4] != 0)
    		{
    			$res->MoveNext();
    			continue;
    		}
    			
    		foreach ($vals as $ind=>$cur)
    		{
        		if (is_null($cur))
        		{
            		$vals[$ind] = '';
        		}
    		}
    		$newdb->Execute($ins,$vals);
    		$res->MoveNext();
    	}
    	echo "OK<br>";
  	/* 
    	echo "Converting ".$fromprefix."_banhistory... ";
    	ob_flush();
    	$res = $olddb->Execute("SELECT player_ip, player_id, player_nick, ban_created, ban_length, ban_reason, admin_ip, admin_id, admin_nick
    				,server_ip, server_name, unban_created FROM ".$fromprefix."_banhistory");
    	$ins = $newdb->Prepare("INSERT INTO ".$toprefix."_banhistory(Type,ip,authid,name,created,ends,length,reason,adminIp,Adminid,RemovedOn,RemovedBy) VALUES ('U',?,?,?,?,?,?,?,?,?,?,?)");
    	while (!$res->EOF)
    	{
        	$vals = array($res->fields[0],$res->fields[1],$res->fields[2],$res->fields[3],($res->fields[4] == 0 ? 0 : $res->fields[3]+$res->fields[4])
    			,$res->fields[4],$res->fields[5],$res->fields[6],$res->fields[7],$res->fields[8],$res->fields[7]);
    		
    		foreach ($vals as $ind=>$cur)
    		{
        		if (is_null($cur))
        		{
            		$vals[$ind] = '';
        		}
    		}
    		$newdb->Execute($ins,$vals);
    		$res->MoveNext();
    	}
        echo "OK<br>";
        
   	echo "Converting ".$fromprefix."_levels... ";
    	ob_flush();
    	$res = $olddb->Execute("SELECT level, bans_add, bans_edit, bans_delete, bans_unban, bans_import, bans_export, amxadmins_view, amxadmins_edit
    	            , webadmins_view, webadmins_edit, permissions_edit, servers_edit FROM ".$fromprefix."_levels");
    	$ins = $newdb->Prepare("INSERT INTO ".$toprefix."_groups(type,name,flags) VALUES (1,?,?)");
    	$levelconvert = array();
    	while (!$res->EOF)
    	{
        	$acc = 0;
        	if ($res->fields[1] == 'yes' || $res->fields[2] == 'yes' || $res->fields[3] == 'yes' || $res->fields[4] == 'yes')
        	{
            	$acc |= ADMIN_WEB_BANS;
        	}
        	// amxadmins_view is ignored
        	if ($res->fields[6] == 'yes')
        	{
            	$acc |= ADMIN_SERVER_ADMINS;
        	}
        	// webadmins_view is ignored
        	if ($res->fields[8] == 'yes')
        	{
            	$acc |= ADMIN_WEB_AGROUPS;
        	}
        	if ($res->fields[9] == 'yes')
        	{
            	$acc |= ADMIN_WEB_AGROUPS | ADMIN_SERVER_AGROUPS;
        	}
        	if ($res->fields[10] == 'yes')
        	{
            	$acc |= ADMIN_SERVER_ADD | ADMIN_SERVER_REMOVE | ADMIN_SERVER_GROUPS;
        	}
        	if ($res->fields[0] == '1')
        	{
            	$acc |= ADMIN_OWNER;
        	}
          	$newdb->Execute($ins,array("AMXBANS_".$res->fields[0],$acc));   	
            $levelconvert[$res->fields[0]] = $newdb->Insert_ID();
        	$res->MoveNext();	
    	}
    	echo "OK<br>";
    	
    	echo "Converting ".$fromprefix."_admins... ";
    	ob_flush();
    	$res = $olddb->Execute("SELECT username, level FROM ".$fromprefix."_webadmins");
    	$ins = $newdb->Prepare("INSERT INTO ".$toprefix."_admins(user,name,gid) VALUES (?,?,?)");
    	while (!$res->EOF)
    	{
        	$newdb->Execute($ins,array($res->fields[0],$res->fields[0],$levelconvert[$res->fields[1]]));
        	$res->MoveNext();
    	}
    	echo "OK<br>"; */
    }
?>