<?php
/*************************************************************************
	This file is part of SourceBans++
	
	Copyright © 2014-2016 SourceBans++ Dev Team <https://github.com/sbpp>

	SourceBans++ is licensed under a
	Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

	You should have received a copy of the license along with this
	work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
	IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
	THE SOFTWARE.

	This program is based off work covered by the following copyright(s): 
		SourceBans 1.4.11
		Copyright © 2007-2014 SourceBans Team - Part of GameConnect
		Licensed under CC BY-NC-SA 3.0
		Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
*************************************************************************/

if(!isset($_SESSION)) // We are using AJAX so need a new session, as we bypass some stuff before :o
	session_start();

class CServerInfo
{
  private $raw;
  private $address;
  private $port;
  private $socket = false;
  private $isfsock = true;

  /** Constants of the data we need for a query */
  const QUERY_HEADER = "\xFF\xFF\xFF\xFF";
  const A2A_PING = "\x69";
  const A2A_PING_RESPONSE = "\x6A";
  const A2S_SERVERQUERY_GETCHALLENGE = "\x57";
  const A2S_SERVERQUERY_GETCHALLENGE_RESPONSE = "\x41";
  const A2S_INFO = "\x54Source Engine Query\0";
  const A2S_INFO_RESPONSE = "\x49";
  const A2S_INFO_RESPONSE_OLD = "\x6D";
  const A2S_PLAYER = "\x55";
  const A2S_PLAYER_RESPONSE = "\x44";
  const A2S_RULES = "\x56";
  const A2S_RULES_RESPONSE = "\x45";
  
  public function __construct($address, $port)
  {
    $this->address = $address;
    $this->port = $port;
    $this->challenge = self::QUERY_HEADER;
  }
  
  public function getInfo()
  {
    $socket = $this->_getSocket();
    if($socket === false)
      return array();
    $packet = $this->_request($socket, self::A2S_INFO);
    if(empty($packet))
      return array();
    
    $this->raw = $packet;
    $ret = array();
    $type = $this->_getraw(1);
    if($type == self::A2S_INFO_RESPONSE)
    { // New protocol for Source and Goldsrc
      $this->_getbyte();  // Version
      $ret['hostname'] = $this->_getnullstr();  
      $ret['map'] = $this->_getnullstr();
      $ret['gamename'] = $this->_getnullstr();
      $ret['gamedesc'] = $this->_getnullstr();
      $this->_getushort(); // AppId
      $ret['numplayers'] = $this->_getbyte();
      $ret['maxplayers'] = $this->_getbyte();
      $ret['botcount'] = $this->_getbyte();
      $ret['dedicated'] = $this->_getraw(1);
      $ret['os'] = $this->_getraw(1);
      $ret['password'] = $this->_getbyte();
      $ret['secure'] = $this->_getbyte();
    }
    else if($type == self::A2S_INFO_RESPONSE_OLD)
    { // Legacy Goldsrc support
      $this->_getnullstr(); // GameIP
      $ret['hostname'] = $this->_getnullstr();
      $ret['map'] = $this->_getnullstr();
      $ret['gamename'] = $this->_getnullstr();
      $ret['gamedesc'] = $this->_getnullstr();
      $ret['numplayers'] = $this->_getbyte();
      $ret['maxplayers'] = $this->_getbyte();
      $this->_getbyte();  // Version
      $ret['dedicated'] = $this->_getraw(1);
      $ret['os'] = $this->_getraw(1);
      $ret['password'] = $this->_getbyte();
      if($this->_getbyte())  // IsMod
      {
        $this->_getnullstr();
        $this->_getnullstr();
        $this->_getbyte();
        $this->_getlong();
        $this->_getlong();
        $this->_getbyte();
        $this->_getbyte();
      }
      $ret['secure'] = $this->_getbyte();
      $ret['botcount'] = $this->_getbyte();
    }
    else
    {
      $_SESSION['getInfo.' . $this->address . '.' . $this->port] = array();
      return $_SESSION['getInfo.' . $this->address . '.' . $this->port];
    }
    $_SESSION['getInfo.' . $this->address . '.' . $this->port] = $ret;
    return $ret;        
  }
  
  public function getPlayers()
  {
    $socket = $this->_getSocket();
    if($socket === false)
      return array();
    $packet = $this->_requestWithChallenge($socket, self::A2S_PLAYER, self::A2S_PLAYER_RESPONSE);
    if(empty($packet))
      return array();
    
    $this->raw = $packet;
    $count = $this->_getbyte();   
    $players = array();
    for($i = 0; $i < $count; $i++)
    {
      // Warning: As of September 29, 2009, Left4Dead2 returns an index of zero for all players.
      // Need the index for the server context menu...
      $index = $this->_getbyte();
      if($index == 0)
        $index = $i;
      $temp = array('index' => $index,
                    'name'  => $this->_getnullstr(),
                    'kills' => $this->_getlong(),
                    'time'  => SecondsToString((int)$this->_getfloat(), true));
      
      if(!empty($temp['name']))
        $players[] = $temp;
    }
    
    array_qsort($players, 'kills', SORT_DESC);
    return $players;
  }
  
  public function getRules()
  {
    $socket = $this->_getSocket();
    if($socket === false)
      return array();
    $packet = $this->_requestWithChallenge($socket, self::A2S_RULES, self::A2S_RULES_RESPONSE);
    if(empty($packet))
      return array();
    
    $this->raw = $packet;
    $nump = $this->_getushort(); 
    $ret = array();
	
    for($i = 0; $i < $nump; $i++)
    {
      $name  = $this->_getnullstr();
      $value = $this->_getnullstr();
      
      if(!empty($name))
        $ret[$name] = $value;
    }
    
    ksort($ret);
    return $ret;
  }

  private function _getSocket()
  {
    if($this->socket !== false)
      return $this->socket;

    try
    {
      if (defined('BIND_IP') && function_exists('socket_create') && function_exists('socket_bind'))
      {

        $this->isfsock = false;
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($this->socket, BIND_IP);

        socket_connect($this->socket, $this->address, $this->port);

        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array("sec"=>1, "usec"=>0));
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array("sec"=>1, "usec"=>0));
      }
      else
      {
        $this->socket    = @fsockopen("udp://".$this->address, $this->port, $errno, $errstr, 2);
        if(!$this->socket)
          return false;
        stream_set_timeout($this->socket, 1);
      }
    }
    catch (Exception $err) { }
    
    if($this->socket === false)
      return false;
      
    return $this->socket;
  }
  
  private function _request($socket, $code, $reply = null)
  {
    if ($this->isfsock)
      fwrite($socket, self::QUERY_HEADER . $code);
    else
      socket_write($socket, self::QUERY_HEADER . $code, strlen(self::QUERY_HEADER . $code));

    $packet = $this->_readsplit($socket);
    if(empty($packet))
      return "";
    
    $this->raw = $packet;
    $magic = $this->_getlong();
    if($magic != -1)
      return "";
    
    $response = $this->_getraw(1);
    if($reply == null)
      return substr($packet, 4);  // Skip magic as it was checked
    else if($response == $reply)
      return substr($packet, 5);  // Skip magic and type as it was checked
    
    return "";
  }
  
  private function _requestWithChallenge($socket, $code, $reply = null)
  {
    $maxretries = 5;
    while(--$maxretries >= 0)
    {
      if ($this->isfsock)
        fwrite($socket, self::QUERY_HEADER . $code . $this->challenge); // do the request with challenge id = -1
      else
        socket_write($socket, self::QUERY_HEADER . $code . $this->challenge, strlen(self::QUERY_HEADER . $code . $this->challenge));

      $packet = $this->_readsplit($socket);
      if(empty($packet))
        return "";
      
      $this->raw = $packet;
      $magic = $this->_getlong();
      if($magic != -1)
        return "";
      
      $response = $this->_getraw(1);
      if($response == self::A2S_SERVERQUERY_GETCHALLENGE_RESPONSE)
      {
        $this->challenge = $this->_getraw(4);
      }
      else if($reply == null)
      {
        return substr($packet, 4);  // Skip magic as it was checked
      }
      else if($response == $reply)
      {
        return substr($packet, 5);  // Skip magic and type as it was checked
      }
    }
    
    return "";
  }
	
  private function _readsplit($socket)
  {
    if ($this->isfsock)
      $packet = fread($socket, 1480);
    else
      $packet = socket_read($socket, 1480);

    if(empty($packet))
      return "";
  	
    $this->raw = $packet;
    $type = $this->_getlong();
    if($type == -2)
    {
      // Parse first header
      $reqid = $this->_getlong();
      $packets = $this->_getushort();
      $numpackets = $packets & 0xFF;
      $curpacket = $packets >> 8;

      if($reqid >= 0)	// Dummy value telling how big the split is (hardcoded to 1248), Orangebox or later
        $this->_skip(2);

      $data = array();
      $tstart = microtime(true);
  		
      // Sanity
      if($curpacket >= $numpackets)
        return "";
  		
      // Compressed?
      if($curpacket == 0 && $reqid < 0)
      {
        $sizeuncompressed = $this->_getlong();
        $crc = $this->_getlong();
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
          $data = implode("", $data);
  				
          // Uncompress if necessary
          if($reqid < 0)
          {
            $data = bzdecompress($data);

            if(strlen($data) != $sizeuncompressed)
              return "";
 					
            // TODO: CRC32 check
            return $data;
          }
  				
          // Not compressed
          return $data;
        }
  			
        // Check the timeout over several receives
        if(microtime(true) - $tstart >= 2.0)	// 2s
          return "";
  			
        // Receive next packet
        if ($this->isfsock)
          $packet = fread($socket, 1480);
        else
          $packet = socket_read($socket, 1480);

        if(empty($packet))
          return "";
  			
        // Parse packet
        $this->raw = $packet;
        $_type = $this->_getlong();
        if($_type != -2)
          return "";
  			
        $_reqid = $this->_getlong();
        $_packets = $this->_getushort();
        $_numpackets = $_packets & 0xFF;
        $curpacket = $_packets >> 8;

        if($reqid >= 0)	// Dummy value telling how big the split is (hardcoded to 1248), Orangebox or later
  	  $this->_skip(2);
  			
        // Sanity check
        if($_reqid != $reqid || $_numpackets != $numpackets || $curpacket >= $numpackets)
          return "";
  			
        // Compressed?
        if($curpacket == 0 && $reqid < 0)
        {
          $sizeuncompressed = $this->_getlong();
          $crc = $this->_getlong();
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
      return "";
    }
  }
  
  private function _getraw($count)
  {
    $data = substr($this->raw, 0, $count);
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

