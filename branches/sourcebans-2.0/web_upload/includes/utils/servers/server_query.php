<?php
/**
 * =============================================================================
 * Query servers for details, map, players, etc
 * 
 * @author InterWave Studios, IceMatrix
 * @version 2.0.0
 * @copyright SourceBans (C)2007-2009 InterWaveStudios.com.  All rights reserved.
 * @package SourceBans
 * @link http://www.sourcebans.net
 * 
 * @version $Id: server_query.php 249 2009-03-25 12:40:00Z tsunami $
 * =============================================================================
 */

require_once UTILS;

class CServerQuery
{
  private $raw;
  private $address;
  private $port;
  private $socket = false;
  
  /** Constants of the data we need for a query */
  const QUERY_HEADER                          = "\xFF\xFF\xFF\xFF";
  const A2A_PING                              = "\x69";
  const A2A_PING_RESPONSE                     = "\x6A";
  const A2S_SERVERQUERY_GETCHALLENGE          = "\x57";
  const A2S_SERVERQUERY_GETCHALLENGE_RESPONSE = "\x41";
  const A2S_INFO                              = "\x54Source Engine Query\0";
  const A2S_INFO_RESPONSE                     = "\x49";
  const A2S_INFO_RESPONSE_OLD                 = "\x6D";
  const A2S_PLAYER                            = "\x55";
  const A2S_PLAYER_RESPONSE                   = "\x44";
  const A2S_RULES                             = "\x56";
  const A2S_RULES_RESPONSE                    = "\x45";
  
  public function __construct($address, $port)
  {
    $this->address   = $address;
    $this->port      = $port;
    $this->challenge = "\xFF\xFF\xFF\xFF";
  }
  
  public function getInfo()
  {
    $socket = $this->_getSocket();
    $packet = $this->_request($socket, self::A2S_INFO);
    if(empty($packet))
      return array();
    
    $this->raw = $packet;
    $info      = array();
    $type      = $this->_getraw(1);
    if($type == self::A2S_INFO_RESPONSE)
    { // New protocol for Source and Goldsrc
      $this->_getbyte();  // Version
      $info['hostname']   = $this->_getnullstr();
      $info['map']        = $this->_getnullstr();
      $info['gamename']   = $this->_getnullstr();
      $info['gamedesc']   = $this->_getnullstr();
      $this->_getushort(); // AppId
      $info['numplayers'] = $this->_getbyte();
      $info['maxplayers'] = $this->_getbyte();
      $info['botcount']   = $this->_getbyte();
      $info['dedicated']  = $this->_getraw(1);
      $info['os']         = $this->_getraw(1);
      $info['password']   = $this->_getbyte();
      $info['secure']     = $this->_getbyte();
    }
    else if($type == self::A2S_INFO_RESPONSE_OLD)
    { // Legacy Goldsrc support
      $this->_getnullstr(); // GameIP
      $info['hostname']   = $this->_getnullstr();
      $info['map']        = $this->_getnullstr();
      $info['gamename']   = $this->_getnullstr();
      $info['gamedesc']   = $this->_getnullstr();
      $info['numplayers'] = $this->_getbyte();
      $info['maxplayers'] = $this->_getbyte();
      $this->_getbyte();    // Version
      $info['dedicated']  = $this->_getraw(1);
      $info['os']         = $this->_getraw(1);
      $info['password']   = $this->_getbyte();
      if($this->_getbyte()) // IsMod
      {
        $this->_getnullstr();
        $this->_getnullstr();
        $this->_getbyte();
        $this->_getlong();
        $this->_getlong();
        $this->_getbyte();
        $this->_getbyte();
      }
      $info['secure']     = $this->_getbyte();
      $info['botcount']   = $this->_getbyte();
    }
    else
      return array();
    
    return $info;        
  }
  
  public function getPlayers()
  {
    $socket = $this->_getSocket();
    $packet = $this->_requestWithChallenge($socket, self::A2S_PLAYER, self::A2S_PLAYER_RESPONSE);
    if(empty($packet))
      return array();
    
    $this->raw = $packet;
    $count     = $this->_getbyte();   
    $players   = array();
    for($i = 0; $i < $count; $i++)
    {
      $player = array('index' => $this->_getbyte(),
                      'name'  => $this->_getnullstr(),
                      'score' => $this->_getlong(),
                      'time'  => Util::SecondsToString((int)$this->_getfloat(), true));
      
      if(!empty($player['name']))
        $players[] = $player;
    }
    
    Util::array_qsort($players, 'score', SORT_DESC);
    return $players;
  }
  
  public function getRules()
  {
    $socket = $this->_getSocket();
    $packet = $this->_requestWithChallenge($socket, self::A2S_RULES, self::A2S_RULES_RESPONSE);
    if(empty($packet))
      return array();
    
    $this->raw = $packet;
    $count     = $this->_getushort(); 
    $rules     = array();
    
    for($i = 0; $i < $count; $i++)
    {
      $name  = $this->_getnullstr();
      $value = $this->_getnullstr();
      
      if(!empty($name))
        $rules[$name] = $value;
    }
    
    ksort($rules);
    return $rules;
  }
  
  private function _getSocket()
  {
    if($this->socket !== false)
      return $this->socket;
    
    $this->socket = fsockopen('udp://' . $this->address, $this->port);
    if($this->socket === false)
      return false;
      
    stream_set_timeout($this->socket, 1);
    return $this->socket;
  }
  
  private function _request($socket, $code, $reply = null)
  {
    fwrite($socket, self::QUERY_HEADER . $code);
    $packet = $this->_readsplit($socket);
    if(empty($packet))
      return '';
    
    $this->raw = $packet;
    $magic     = $this->_getlong();
    if($magic != -1)
      return '';
    
    $response = $this->_getraw(1);
    if($reply == null)
      return substr($packet, 4);  // Skip magic as it was checked
    else if($response == $reply)
      return substr($packet, 5);  // Skip magic and type as it was checked
    
    return '';
  }
  
  private function _requestWithChallenge($socket, $code, $reply = null)
  {
    $maxretries = 5;
    while(--$maxretries >= 0)
    {
      fwrite($socket, self::QUERY_HEADER . $code . $this->challenge); // do the request with challenge id = -1
      $packet = $this->_readsplit($socket);
      if(empty($packet))
        return '';
      
      $this->raw = $packet;
      $magic     = $this->_getlong();
      if($magic != -1)
        return '';
      
      $response = $this->_getraw(1);
      if($response == self::A2S_SERVERQUERY_GETCHALLENGE_RESPONSE)
        $this->challenge = $this->_getraw(4);
      else if($reply == null)
        return substr($packet, 4);  // Skip magic as it was checked
      else if($response == $reply)
        return substr($packet, 5);  // Skip magic and type as it was checked
    }
    
    return '';
  }
  
  private function _readsplit($socket)
  {
    $packet = fread($socket, 1480);
    if(empty($packet))
      return '';
    
    $this->raw = $packet;
    $type      = $this->_getlong();
    if($type == -2)
    {
      // Parse first header
      $reqid      = $this->_getlong();
      $packets    = $this->_getushort();
      $numpackets = $packets & 0xFF;
      $curpacket  = $packets >> 8;
      if($reqid >= 0)  // Dummy value telling how big the split is (hardcoded to 1248), Orangebox or later
        $this->_skip(2);
      $data   = array();
      $tstart = microtime(true);
      
      // Sanity
      if($curpacket >= $numpackets)
        return '';
      
      // Compressed?
      if(!$curpacket && $reqid < 0)
      {
        $sizeuncompressed = $this->_getlong();
        $crc              = $this->_getlong();
      }
      
      while(true)
      {
        // Split already received (duplicate)?
        if(!array_key_exists($curpacket, $data))
          $data[$curpacket] = $this->raw;
        
        // Finished?
        if(count($data) >= $numpackets)
        {
          // Join the parts
          ksort($data);
          $data = implode('', $data);
          
          // Uncompress if necessary
          if($reqid < 0)
          {
            $data = bzdecompress($data);
            if(strlen($data) != $sizeuncompressed)
              return '';
            
            // TODO: CRC32 check
            return $data;
          }
          
          // Not compressed
          return $data;
        }
        
        // Check the timeout over several receives
        if(microtime(true) - $tstart >= 2.0)  // 2s
          return '';
        
        // Receive next packet
        $packet = fread($socket, 1480);
        if(empty($packet))
          return '';
        
        // Parse packet
        $this->raw = $packet;
        $_type     = $this->_getlong();
        if($_type != -2)
          return '';
        
        $_reqid      = $this->_getlong();
        $_packets    = $this->_getushort();
        $_numpackets = $_packets & 0xFF;
        $curpacket   = $_packets >> 8;
        if($reqid >= 0)  // Dummy value telling how big the split is (hardcoded to 1248), Orangebox or later
          $this->_skip(2);
        
        // Sanity check
        if($_reqid != $reqid || $_numpackets != $numpackets || $curpacket >= $numpackets)
          return '';
        
        // Compressed?
        if(!$curpacket && $reqid < 0)
        {
          $sizeuncompressed = $this->_getlong();
          $crc              = $this->_getlong();
        }
      }  
    }
    else if($type == -1)
    {
      // Non-split packet
      return $packet;
    }
    else
    {
      // Invalid
      return '';
    }
  }
  
  private function _getraw($count)
  {
    $data      = substr($this->raw, 0, $count);
    $this->raw = substr($this->raw, $count);
    return $data;
  }
  
  private function _getbyte() 
  {
    $byte = $this->_getraw(1);
    return ord($byte);
  }
  
  private function _getfloat() 
  {
    $f = @unpack('f1float', $this->_getraw(4));
    return $f['float'];
  }
  
  private function _getlong() 
  {
    $lo   = $this->_getushort();
    $hi   = $this->_getushort();
    $long = ($hi << 16) | $lo;
    if ($long & 0x80000000 && $long > 0)  // This is special for register size >32 bits
      return -((~$long & 0xFFFFFFFF) + 1);
    else
      return $long;                       // 32-bit handles negative values implicitly
  }
  
  private function _getnullstr() 
  {
    if (empty($this->raw)) 
      return '';
    
    $end       = strpos($this->raw, "\0");
    $str       = substr($this->raw, 0, $end);
    $this->raw = substr($this->raw, $end + 1);
    return $str;
  }
  
  private function _getushort() 
  {
    $lo    = $this->_getbyte();
    $hi    = $this->_getbyte();
    $short = ($hi << 8) | $lo;
    return $short;
  }
  
  private function _getshort() 
  {
    $short = $this->_getushort();
    if ($short & 0x8000)
      return -((~$short & 0xFFFF) + 1);
    else
      return $short;
  }
  
  private function _skip($c)
  {
    $this->raw = substr($this->raw, $c);
  }
}
?>