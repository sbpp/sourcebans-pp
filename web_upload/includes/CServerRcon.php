<?php
/**
 * =============================================================================
 * Send and receive RCON packets
 * 
 * @author SteamFriends Development Team
 * @version 1.0.0
 * @copyright SourceBans (C)2007 SteamFriends.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: CServerRcon.php 117 2008-08-21 17:17:54Z peace-maker $
 * =============================================================================
 */
define("SERVERDATA_EXECCOMMAND", 02);
define("SERVERDATA_AUTH", 03);
define("SERVERDATA_RESPONSE_VALUE", 00);
define("SERVERDATA_AUTH_RESPONSE", 02);

class CServerRcon {
    var $password;
    var $_sock = null;
    var $_id = 0;

    function CServerRcon ($address,$port,$password) {
			$this->password = $password;
			$this->_sock = @fsockopen($address,$port, $errno, $errstr, 2);
			stream_set_timeout($this->_sock, 2);
    }
    
    function Auth () {
			$PackID = $this->_write(SERVERDATA_AUTH,$this->password);
			$ret = $this->_PacketRead();
			return (isset($ret[1]['ID']) && $ret[1]['ID'] == -1)?0:1;
    }

    function _Write($cmd, $s1='', $s2='') {
			$id = ++$this->_id;
			$data = pack("VV",$id,$cmd).$s1.chr(0).$s2.chr(0);
			$data = pack("V",strlen($data)).$data;
			fwrite($this->_sock,$data,strlen($data));
			return $id;
    }

    function _PacketRead() {
			$retarray = array();
			while ($size = @fread($this->_sock,4)) 
			{
			    $size = unpack('V1Size',$size);
			    if ($size["Size"] > 4096)
						$packet = "\x00\x00\x00\x00\x00\x00\x00\x00".fread($this->_sock,4096);
			    else 
						$packet = fread($this->_sock,$size["Size"]);
			    array_push($retarray,unpack("V1ID/V1Reponse/a*S1/a*S2",$packet));
			}
			return $retarray;
    }

    function Read() {
			$Packets = $this->_PacketRead();	
			foreach($Packets as $pack) 
			{
			    if (isset($ret[$pack['ID']])) 
			    {
					$ret[$pack['ID']]['S1'] .= $pack['S1'];
					$ret[$pack['ID']]['S2'] .= $pack['S1'];
			    } else {
						$ret[$pack['ID']] = array(
							'Reponse' => $pack['Reponse'],
							'S1' => $pack['S1'],
							'S2' =>	$pack['S2'],
					    );
			    }
			}
			return $ret;
    }

    function sendCommand($command) {
			//$command = '"'.trim(str_replace(' ','" "', $command)).'"';
			$this->_Write(SERVERDATA_EXECCOMMAND,$command,'');
    }

    function rconCommand($command) {
			$this->sendCommand($command);
			$ret = $this->Read();
			return $ret[2]['S1'];
    }
}

?>
