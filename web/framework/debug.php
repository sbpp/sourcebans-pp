<?php

Flight::map('debugConnection', function ($ip, $port, $rcon = null) {
    if (!Flight::get('debug.functions')) {
        Flight::redirect('/');
    }

    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        throw new Exception('Invalid IPv4!');
    }

    if (!filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
        throw new Exception('Invalid port!');
    }

    print("[+] SourceBans++ Connection Debug starting for server ".$ip.":".$port.'<br/>');
    print("[+] Trying to establish UDP connection <br/>");
    $sock = @fsockopen('udp://'.$ip, $port, $err['no'], $err['str'], 5);

    if (!$sock) {
        print("[-] Error connecting #".$err['no'].": ".$err['str']);
        exit(0);
    }

    print("[+] UDP connection successfull! <br />");
    stream_set_timeout($sock, 1);

    print("[+] Trying to write to the socket <br />");
    $write = fwrite($sock, "\xFF\xFF\xFF\x54Source Engine Query\0");

    if ($write === false) {
        print("[-] Error writing.");
        exit(0);
    }

    print("[+] Successfully requested server info. (That doesn\'t mean anything on an UDP stream.) Reading...<br />");
    $packet = fread($sock, 1480);

    if (empty($packet)) {
        print("[-] Error getting server info. Can\'t read from UDP stream. Port is possibly blocked.");
        exit(0);
    }

    if (substr($packet, 5, (strpos(substr($packet, 5), "\0") - 1)) == "Banned by server") {
        print("[-] Got an response, but this webserver\'s ip is banned by the server.");
        exit(0);
    }

    $packet = substr($packet, 6);
    $hostname = substr($packet, 0, strpos($packet, "\0"));
    print("[+] Got an response! Server: ".$hostname."<br/>");
    fclose($sock);

    print("[+] Trying to establish TCP connection <br />");
    $sock = @fsockopen($ip, $port, $err['no'], $err['str'], 5);
    if (!$sock) {
        print("[-] Error connecting #".$err['no'].': '.$err['str']);
        exit(0);
    }
    print("[+] TCP connection successfull! <br />");
    if (is_null($rcon)) {
        print("[o] Stopping here since no rcon password specified.");
        exit(0);
    }
    stream_set_timeout($sock, 2);
    $data = pack("VV", 0, 03) . $rcon . chr(0) . '' . chr(0);
    $data = pack("V", strlen($data)) . $data;

    print("[+] Trying to write to TCP socket and authenticate via rcon <br />");
    $write = fwrite($sock, $data, strlen($data));

    if ($write === false) {
        print("[-] Error writing.");
        exit(0);
    }
    print("[+] Successfully sent authentication request. Reading...<br />");
    $size = fread($sock, 4);
    if (!$size) {
        print("[-] Error reading.");
        exit(0);
    }
    print("[+] Got an response! <br />");
    $size = unpack('V1Size', $size);
    $packet = fread($sock, $size['Size']);
    $size = fread($sock, 4);
    $size = unpack('V1Size', $size);
    $packet = fread($sock, $size['Size']);
    $ret = unpack("V1ID/V1Reponse/a*S1/a*S2", $packet);

    if (empty($ret) || (isset($ret['ID']) && $ret['ID'] == -1)) {
        print("[-] Bad password! Don\'t try this too often or your webserver will get banned by the gameserver.");
        exit(0);
    }
    print("[+] Password correct!");
    fclose($sock);
});
