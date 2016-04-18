<?php
// *************************************************************************
//  This file is part of SourceBans++.
//
//  Copyright (C) 2014-2016 Sarabveer Singh <me@sarabveer.me>
//
//  SourceBans++ is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, per version 3 of the License.
//
//  SourceBans++ is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with SourceBans++. If not, see <http://www.gnu.org/licenses/>.
//
//  This file is based off work covered by the following copyright(s):  
//
//   SourceBans 1.4.11
//   Copyright (C) 2007-2015 SourceBans Team - Part of GameConnect
//   Licensed under GNU GPL version 3, or later.
//   Page: <http://www.sourcebans.net/> - <https://github.com/GameConnect/sourcebansv1>
//
// *************************************************************************
 
 class CUpdater
 {
	var $store=0;
	
	function __construct()
	{
		if(!is_numeric($this->getCurrentRevision()))
		{			
			$this->_updateVersionNumber(0); // Set at 0 initially, this will cause all database updates to be run
		}
		else if($this->getCurrentRevision() == -1) // They have some fubar version fix it for them :|
		{
			$GLOBALS['db']->Execute("INSERT INTO `".DB_PREFIX."_settings` (`setting`, `value`) VALUES ('config.version', '0')");
		}
	}
	
	function getLatestPackageVersion()
	{
		$retval = 0;
		foreach($this->_getStore() as $version => $key)
		{
			if( $version > $retval )
				$retval = $version;
		}
		return $retval;
	}
	
	function doUpdates()
	{
		$retstr = "";
		$error = false;
		$i = 0;
		foreach($this->_getStore() as $version => $key)
		{
			if( $version > $this->getCurrentRevision() )
			{
				$i++;
				$retstr .= "Running update: <b>v" . $version . "</b>... ";
				if( !include (ROOT . "updater/data/" . $key))
				{
					// OHSHI! Something went tits up :(
					$retstr .= "<b>Error executing: /updater/data/" . $key . ". Stopping Update!</b>";
					$error = true;
					break;
				}
				else
				{
					// File was executed successfully 
					$retstr .= "Done.<br /><br />";
					$this->_updateVersionNumber($version);
				}
			}
		}
		if( $i == 0 )
			$retstr .= "<br />Nothing to update...";
		else
		{
			if(!$error)
				$retstr .= "<br />Updated successfully. Please delete the /updater folder.";
			else
				$retstr .= "<br />Update Failed.";
		}
		return $retstr;
	}
	
	function getCurrentRevision()
	{
		return (isset($GLOBALS['config']['config.version']))?$GLOBALS['config']['config.version']:-1;
	}
	
	function needsUpdate()
	{
		return($this->getLatestPackageVersion() > $this->getCurrentRevision());
	}
	
	function _getStore()
	{
		if($this->store==0)
			return include ROOT . "/updater/store.php";
		else
			return $this->store;
	}
	
	function _updateVersionNumber($rev)
	{
		$ret = $GLOBALS['db']->Execute("UPDATE ".DB_PREFIX."_settings SET value = ? WHERE setting = 'config.version';", array((int)$rev));
		return !(empty($ret));
	}
	
 }

?>
