<?php
/**
 * SourceBans "Error Connecting()" Debug
 * Checks for the ports being forwarded correctly
 */

/** 
 * Config part
 * Change to IP and port of the gameserver you want to test
 */
$serverip = "";
$serverport = 27015;
$serverrcon = ""; // You only need to specify this, if you want to test the rcon tcp connection either! Leave blank if it's only the serverinfo erroring.


/******* Don't change below here *******/

if(empty($serverip) || empty($serverport))
	die('[-] No server information set. Open up this file and specify your gameserver\'s IP and port.');

echo '[+] SourceBans "Error Connecting()" Debug starting for server ' . $serverip . ':' . $serverport . '<br /><br />';

// Check for UDP connection being available and writable
echo '[+] Trying to establish UDP connection<br />';
$sock = @fsockopen("udp://" . $serverip, $serverport, $errno, $errstr, 2);

$isBanned = false;

if(!$sock)
{
	echo '[-] Error connecting #' . $errno . ': ' . $errstr . '<br />';
}
else
{
	echo '[+] UDP connection successfull!<br />';
	
	stream_set_timeout($sock, 1);
	// Try to get serverinformation
	echo '[+] Trying to write to the socket<br />';
	$written = fwrite($sock, "\xFF\xFF\xFF\xFF\x54Source Engine Query\0");
	if($written === false)
	{
		echo '[-] Error writing.<br />';
	}
	else
	{
		echo '[+] Successfully requested server info. (That doesn\'t mean anything on an UDP stream.) Reading...<br />';
		$packet = fread($sock, 1480);
		
		if(empty($packet))
		{
			echo '[-] Error getting server info. Can\'t read from UDP stream. Port is possibly blocked.<br />';
		}
		else
		{
            if(substr($packet, 5, (strpos(substr($packet, 5), "\0")-1)) == "Banned by server")
            {
                echo '[-] Got an response, but this webserver\'s ip is banned by the server.<br />';
                $isBanned = true;
            }
            else
            {
                $packet = substr($packet, 6);
                $hostname = substr($packet, 0, strpos($packet, "\0"));
                echo '[+] Got an response! Server: ' . $hostname . ' <br />';
            }
		}
	}
	fclose($sock);
}

echo '<br />';

// Check for TCP connection being available and writeable
echo '[+] Trying to establish TCP connection<br />';
$sock = @fsockopen($serverip, $serverport, $errno, $errstr, 2);
if(!$sock)
{
	echo '[-] Error connecting #' . $errno . ': ' . $errstr . '<br />';
}
else
{
	echo '[+] TCP connection successfull!<br />';
	if(empty($serverrcon))
	{
		echo '[o] Stopping here since no rcon password specified.';
	}
    else if($isBanned)
    {
        echo '[o] Stopping here since this ip is banned by the gameserver.';
    }
	else
	{
		stream_set_timeout($sock, 2);
		$data = pack("VV", 0, 03) . $serverrcon . chr(0) . '' . chr(0);
		$data = pack("V", strlen($data)) . $data;
		
		echo '[+] Trying to write to TCP socket and authenticate via rcon<br />';
		$written = fwrite($sock, $data, strlen($data));
		
		if($written === false)
		{
			echo '[-] Error writing.<br />';
		}
		else
		{
			echo '[+] Successfully sent authentication request. Reading...<br />';
			$size = fread($sock, 4);
			if(!$size)
			{
				echo '[-] Error reading.<br />';
			}
			else
			{
				echo '[+] Got an response! <br />';
				$size = unpack('V1Size', $size);
				$packet = fread($sock, $size["Size"]);
				$size = fread($sock, 4);
				$size = unpack('V1Size', $size);
				$packet = fread($sock, $size["Size"]);
				$ret = unpack("V1ID/V1Reponse/a*S1/a*S2", $packet);
				if(empty($ret) || (isset($ret['ID']) && $ret['ID'] == -1))
				{
					echo '[-] Bad password ;) Don\'t try this too often or your webserver will get banned by the gameserver.<br />';
				}
				else
				{
					echo '[+] Password correct!';
				}
			}
		}
	}
	fclose($sock);
}
?>