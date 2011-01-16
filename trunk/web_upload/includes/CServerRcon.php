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

class CServerRcon
{
  private $password;
  private $_sock = null;
  private $_id = 0;
  private $isfsock = true;

  const SERVERDATA_EXECCOMMAND = 02;
  const SERVERDATA_AUTH = 03;
  const SERVERDATA_RESPONSE_VALUE = 00;
  const SERVERDATA_AUTH_RESPONSE = 02;

  function CServerRcon ($address, $port, $password)
  {
    $this->password = $password;

    try
    {
      if (defined('BIND_IP') && function_exists('socket_create') && function_exists('socket_bind'))
      {
        $this->isfsock = false;
        $this->_sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        socket_set_option($this->_sock, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->_sock, BIND_IP);

        socket_connect($this->_sock, $address, $port);

        socket_set_option($this->_sock, SOL_SOCKET, SO_SNDTIMEO, array("sec"=>2, "usec"=>0));
        socket_set_option($this->_sock, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>2, "usec"=>0));
      }
      else
      {
        $this->_sock = @fsockopen($address, $port, $errno, $errstr, 2);
        stream_set_timeout($this->_sock, 2);
      }
    }
    catch (Exception $err) { }
  }
    
  public function Auth ()
  {
    $PackID = $this->_Write(CServerRcon::SERVERDATA_AUTH,$this->password);
    $ret = $this->_PacketRead();

    return (isset($ret[1]['ID']) && $ret[1]['ID'] == -1)?0:1;
  }

  private function _Write($cmd, $s1='', $s2='')
  {
    $id = ++$this->_id;
    $data = pack("VV",$id,$cmd).$s1.chr(0).$s2.chr(0);
    $data = pack("V",strlen($data)).$data;

    if ($this->isfsock)
      fwrite($this->_sock, $data, strlen($data));
    else
      socket_write($this->_sock, $data, strlen($data));

    return $id;
  }

  private function _sock_read($size)
  {
    if ($this->isfsock)
      return @fread($this->_sock, $size);
    else
      return socket_read($this->_sock, $size);
  }

  private function _PacketRead()
  {
    $retarray = array();

    while ($size = $this->_sock_read(4)) 
    {
      $size = unpack('V1Size',$size);

      if ($size["Size"] > 4096)
        $packet = "\x00\x00\x00\x00\x00\x00\x00\x00".$this->_sock_read(4096);
      else 
        $packet = $this->_sock_read($size["Size"]);

      array_push($retarray,unpack("V1ID/V1Reponse/a*S1/a*S2",$packet));
    }

    return $retarray;
  }

  public function Read()
  {
    $Packets = $this->_PacketRead();

    foreach($Packets as $pack) 
    {
      if (isset($ret[$pack['ID']])) 
      {
        $ret[$pack['ID']]['S1'] .= $pack['S1'];
        $ret[$pack['ID']]['S2'] .= $pack['S1'];
      }
      else
      {
        $ret[$pack['ID']] = array('Reponse' => $pack['Reponse'],
                                  'S1' => $pack['S1'],
                                  'S2' =>	$pack['S2'],);
      }
    }

    return $ret;
  }

  public function sendCommand($command)
  {
    //$command = '"'.trim(str_replace(' ','" "', $command)).'"';
    $this->_Write(CServerRcon::SERVERDATA_EXECCOMMAND,$command,'');
  }

  public function rconCommand($command)
  {
	  $this->sendCommand($command);
	  $ret = $this->Read();
	  return $ret[2]['S1'];
  }
}
