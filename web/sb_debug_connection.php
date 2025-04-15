<?php
/*************************************************************************
This file is part of SourceBans++

SourceBans++ (c) 2014-2024 by SourceBans++ Dev Team

The SourceBans++ Web panel is licensed under a
Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.

You should have received a copy of the license along with this
work.  If not, see <http://creativecommons.org/licenses/by-nc-sa/3.0/>.

This program is based off work covered by the following copyright(s):
SourceBans 1.4.11
Copyright © 2007-2014 SourceBans Team - Part of GameConnect
Licensed under CC-BY-NC-SA 3.0
Page: <http://www.sourcebans.net/> - <http://www.gameconnect.net/>
 *************************************************************************/
/**
 * SourceBans "Error Connecting()" Debug
 * Checks for the ports being forwarded correctly
 */

/**
 * Config part
 * Change to IP and port of the gameserver you want to test
 */
$serverip = "";
$serverport = "";
$serverrcon = ""; // Leave empty if you're only testing the serverinfo connection

/*******
 * Don't change below here 
*******/

// Track errors for dynamic troubleshooting
$errors = [];

// Validate server IP and port
if (empty($serverip)) {
    $errors[] = "no_server_ip";
}

// Use default port if not specified
if (empty($serverport)) {
    $serverport = 27015;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SourceBans++ Connection Debug</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f0f0f0;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }

        h1 {
            color: #2c3e50;
            text-align: center;
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        h2 {
            color: #2c3e50;
            margin-top: 30px;
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }

        .server-info {
            background-color: #2c3e50;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .log-container {
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .log-line {
            font-family: Consolas, Monaco, 'Courier New', monospace;
            margin-bottom: 4px;
            line-height: 1.5;
        }

        .success {
            color: #28a745;
        }

        .error {
            color: #dc3545;
            font-weight: bold;
        }

        .warning {
            color: #fd7e14;
        }

        .info {
            color: #17a2b8;
        }

        .troubleshooting {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            border-left: 4px solid #28a745;
        }

        .troubleshooting.has-errors {
            border-left: 4px solid #dc3545;
        }

        .troubleshooting h2 {
            margin-top: 0;
            border-bottom: none;
            padding-bottom: 0;
        }

        .troubleshooting.has-errors h2 {
            color: #dc3545;
        }

        .troubleshooting:not(.has-errors) h2 {
            color: #28a745;
        }

        .troubleshooting ul, .troubleshooting ol {
            padding-left: 25px;
            margin: 15px 0;
        }

        .troubleshooting li {
            margin-bottom: 10px;
        }

        .docs-link {
            background-color: #2c3e50;
            color: white;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .docs-link h3 {
            color: white;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 20px;
        }

        .docs-link p {
            margin-bottom: 15px;
            font-size: 16px;
        }

        .doc-button, .discord-button {
            display: inline-block;
            text-decoration: none;
            font-weight: bold;
            padding: 10px 20px;
            border-radius: 4px;
            transition: all 0.2s ease;
            margin: 5px;
        }

        .doc-button {
            background-color: white;
            color: #2c3e50;
        }

        .discord-button {
            background-color: #7289DA;
            color: white;
        }

        .doc-button:hover, .discord-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .doc-button:hover {
            background-color: #f0f0f0;
        }

        .discord-button:hover {
            background-color: #5e78d5;
        }

        .sdr-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeeba;
            border-left: 4px solid #ffc107;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .sdr-warning h3 {
            color: #856404;
            margin-top: 0;
            margin-bottom: 10px;
        }

        .sdr-warning ul {
            padding-left: 25px;
            margin: 10px 0;
        }

        .sdr-warning li {
            margin-bottom: 5px;
        }

        .sdr-warning p:last-child {
            margin-bottom: 0;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #eee;
            color: #777;
            font-size: 14px;
        }

        .config-error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 16px;
            border: 1px solid #f5c6cb;
        }

        .config-error code {
            background-color: #f5c6cb;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>SourceBans++ Connection Debug</h1>

        <?php if (in_array("no_server_ip", $errors)): ?>
        <div class="config-error">
            <strong>Configuration Error:</strong> No server IP has been specified.<br />Please edit this file and set the <code>$serverip</code> variable at the top of the file.
        </div>
        <?php else: ?>

        <div class="server-info">
            Testing connection to server: <?php echo $serverip . ':' . $serverport; ?>
        </div>

        <h2>System Information</h2>
        <div class="log-container">
            <div class="log-line">PHP Version: <?php echo phpversion(); ?></div>
            <div class="log-line">Operating System: <?php echo PHP_OS; ?></div>
            <div class="log-line">Web Server: <?php echo $_SERVER['SERVER_SOFTWARE']; ?></div>
        </div>

        <?php
        // Check if socket functions are available
        if (!function_exists('fsockopen')) {
            $errors[] = "fsockopen_unavailable";
            echo '<h2>Error</h2>';
            echo '<div class="log-container">';
            echo '<div class="log-line error">fsockopen function is not available. Check PHP configuration.</div>';
            echo '</div>';
        } else {
        ?>

        <h2>UDP Test</h2>
        <div class="log-container">
<?php
$isBanned = false;
echo '<div class="log-line info">[+] Attempting UDP connection to ' . $serverip . ':' . $serverport . '</div>';

// Try to create socket with error handling
$sock = @fsockopen("udp://" . $serverip, $serverport, $errno, $errstr, 2);

if (!$sock) {
    $errors[] = "udp_connection_failed";
    echo '<div class="log-line error">[-] Connection error #' . $errno . ': ' . $errstr . '</div>';
    echo '<div class="log-line warning">Suggestion: Check if outgoing UDP connections are allowed by your firewall.</div>';
} else {
    echo '<div class="log-line success">[+] UDP connection successful!</div>';

    stream_set_timeout($sock, 1);
    echo '<div class="log-line info">[+] Attempting to write to socket</div>';

    // Use error suppression and check for errors
    $written = @fwrite($sock, "\xFF\xFF\xFF\xFF\x54Source Engine Query\0");

    if ($written === false) {
        $errors[] = "udp_write_failed";
        $error = error_get_last();
        echo '<div class="log-line error">[-] Write error: ' . $error['message'] . '</div>';
        echo '<div class="log-line warning">Suggestion: This is likely a permission issue. Check if your web server has permission to make outgoing UDP connections.</div>';
    } else {
        echo '<div class="log-line success">[+] Successfully wrote ' . $written . ' bytes to socket.</div>';
        echo '<div class="log-line info">[+] Server info request sent. Reading...</div>';
        $packet = fread($sock, 1480);

        if (empty($packet)) {
            $errors[] = "udp_read_failed";
            echo '<div class="log-line error">[-] Unable to read server info. Port might be blocked.</div>';
            echo '<div class="log-line warning">Suggestion: The game server might not be responding or might be blocking incoming connections.</div>';
        } else {
            if (substr($packet, 5, (strpos(substr($packet, 5), "\0") - 1)) == "Banned by server") {
                $errors[] = "server_banned";
                echo '<div class="log-line error">[-] This web server\'s IP is banned by the game server.</div>';
                $isBanned = true;
            } else {
                $packet = substr($packet, 6);
                $hostname = substr($packet, 0, strpos($packet, "\0"));
                echo '<div class="log-line success">[+] Response received! Server: ' . htmlspecialchars($hostname) . '</div>';
            }
        }
    }
    fclose($sock);
}
?>
        </div>

        <h2>TCP Test</h2>
        <div class="log-container">
<?php
echo '<div class="log-line info">[+] Attempting TCP connection to ' . $serverip . ':' . $serverport . '</div>';
$sock = @fsockopen($serverip, $serverport, $errno, $errstr, 2);

if (!$sock) {
    $errors[] = "tcp_connection_failed";
    echo '<div class="log-line error">[-] Connection error #' . $errno . ': ' . $errstr . '</div>';
    echo '<div class="log-line warning">Suggestion: Check if outgoing TCP connections are allowed by your firewall.</div>';
} else {
    echo '<div class="log-line success">[+] TCP connection successful!</div>';

    if (empty($serverrcon)) {
        echo '<div class="log-line warning">[o] Stopping here since no RCON password was specified.</div>';
    } else if ($isBanned) {
        echo '<div class="log-line warning">[o] Stopping here since this IP is banned by the game server.</div>';
    } else {
        stream_set_timeout($sock, 2);
        $data = pack("VV", 0, 03) . $serverrcon . chr(0) . '' . chr(0);
        $data = pack("V", strlen($data)) . $data;

        echo '<div class="log-line info">[+] Attempting RCON authentication via TCP</div>';
        $written = @fwrite($sock, $data, strlen($data));

        if ($written === false) {
            $errors[] = "tcp_write_failed";
            $error = error_get_last();
            echo '<div class="log-line error">[-] Write error: ' . $error['message'] . '</div>';
            echo '<div class="log-line warning">Suggestion: This is likely a permission issue. Check if your web server has permission to make outgoing TCP connections.</div>';
        } else {
            echo '<div class="log-line info">[+] Authentication request sent. Reading...</div>';
            $size = fread($sock, 4);

            if (!$size) {
                $errors[] = "tcp_read_failed";
                echo '<div class="log-line error">[-] Read error.</div>';
            } else {
                echo '<div class="log-line success">[+] Response received!</div>';
                $size = unpack('V1Size', $size);
                $packet = fread($sock, $size["Size"]);
                $size = fread($sock, 4);
                $size = unpack('V1Size', $size);
                $packet = fread($sock, $size["Size"]);
                $ret = unpack("V1ID/V1Reponse/a*S1/a*S2", $packet);

                if (empty($ret) || (isset($ret['ID']) && $ret['ID'] == -1)) {
                    $errors[] = "rcon_auth_failed";
                    echo '<div class="log-line error">[-] Incorrect password. Avoid too many attempts to prevent being banned.</div>';
                } else {
                    echo '<div class="log-line success">[+] Password correct!</div>';
                }
            }
        }
    }
    fclose($sock);
}
?>
        </div>

        <div class="troubleshooting <?php echo !empty($errors) && !in_array("no_server_ip", $errors) ? 'has-errors' : ''; ?>">
            <h2>Troubleshooting Recommendations</h2>

            <?php if (empty($errors) || (count($errors) == 1 && in_array("no_server_ip", $errors))): ?>
                <p>All tests completed successfully! Your server appears to be properly configured for SourceBans++.</p>
            <?php else: ?>
                <p>Based on the test results, here are some recommendations to fix the issues:</p>
                <ul>
                <?php if (in_array("fsockopen_unavailable", $errors)): ?>
                    <li>The <code>fsockopen</code> function is not available on your server. Contact your hosting provider to enable this PHP function, as it's required for SourceBans++ to connect to game servers.</li>
                <?php endif; ?>

                <?php if (in_array("udp_connection_failed", $errors)): ?>
                    <li><strong>UDP Connection Failed:</strong> Your web server cannot establish a UDP connection to the game server. This could be due to:
                        <ul>
                            <li>Firewall restrictions on your web server blocking outgoing UDP connections</li>
                            <li>The game server being offline or unreachable</li>
                            <li>Incorrect server IP or port</li>
                        </ul>
                        <p>From the official documentation:</p>
                        <ul>
                            <li>Make sure your host is not blocking UDP Incoming</li>
                            <li>If hosting locally, make sure you are port forwarding correctly</li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if (in_array("tcp_connection_failed", $errors)): ?>
                    <li><strong>TCP Connection Failed:</strong> Your web server cannot establish a TCP connection to the game server. This could be due to:
                        <ul>
                            <li>Firewall restrictions on your web server blocking outgoing TCP connections</li>
                            <li>The game server being offline or unreachable</li>
                            <li>Incorrect server IP or port</li>
                        </ul>
                        <p>From the official documentation:</p>
                        <ul>
                            <li>Make sure your host is not blocking TCP Outgoing</li>
                            <li>Make sure your server is explicitly IP binded, do so using <code>-ip</code> launch parameter</li>
                            <li>If you are unable to use RCON through in-game console, append <code>-usercon</code> to launch parameter</li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if (in_array("udp_write_failed", $errors) || in_array("tcp_write_failed", $errors)): ?>
                    <li><strong>"Operation not permitted" error detected:</strong> Your web server cannot send data to the game server. This is typically caused by:
                        <ul>
                            <li>Security restrictions on your hosting (common on shared hosting)</li>
                            <li>SELinux or AppArmor policies blocking outgoing connections</li>
                            <li>PHP configuration restrictions</li>
                        </ul>
                        <p>Possible solutions:</p>
                        <ul>
                            <li>Contact your hosting provider to allow outgoing UDP/TCP connections</li>
                            <li>If you have server access, check and modify firewall rules</li>
                            <li>Consider moving to a VPS or dedicated server where you have more control</li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if (in_array("udp_read_failed", $errors) || in_array("tcp_read_failed", $errors)): ?>
                    <li><strong>Cannot read response from server:</strong> Connection was established but no response was received. This could be due to:
                        <ul>
                            <li>The game server firewall blocking incoming connections from your web server</li>
                            <li>The game server not running Source engine or being misconfigured</li>
                            <li>Network issues between your web server and the game server</li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if (in_array("server_banned", $errors)): ?>
                    <li><strong>Your web server IP is banned on the game server:</strong> 
                        <p>From the official documentation:</p>
                        <ul>
                            <li>Make sure your game server did not ban your web server's IP using <code>listip</code></li>
                            <li>If banned: remove it from <code>cfg/banned_ip.cfg</code> and using <code>removeip IP</code> via RCON</li>
                        </ul>
                    </li>
                <?php endif; ?>

                <?php if (in_array("rcon_auth_failed", $errors)): ?>
                    <li><strong>RCON authentication failed:</strong> The RCON password you provided is incorrect. Double-check the password in your server configuration.</li>
                <?php endif; ?>
                </ul>

                <p><strong>From the official documentation checklist:</strong></p>
                <ul>
                    <li>Your host is not blocking any traffic on your game server port (UDP Incoming & TCP Outgoing)</li>
                    <li>Your game server is online to people outside of your network w/o any error on binding</li>
                    <li>You can connect to server in-game</li>
                    <li>You can use RCON through in-game console</li>
                </ul>

                <p><strong>General advice:</strong></p>
                <ul>
                    <li>If you're on shared hosting and experiencing "Operation not permitted" errors, these restrictions are often permanent. Consider using a VPS instead.</li>
                    <li>Make sure both your web server and game server have their firewalls configured to allow the necessary connections.</li>
                    <li>If possible, host your website and game server on the same network to minimize connectivity issues.</li>
                </ul>
            <?php endif; ?>
        </div>

        <div class="docs-link" style="margin-bottom: 20px;">
            <h3>Official Documentation</h3>
            <p>For more detailed information on debugging connection issues, please refer to the official SourceBans++ documentation:</p>
            <a href="https://sbpp.github.io/docs/debugging_connection/" target="_blank" class="doc-button">View Documentation</a>
            
            <h3 style="margin-top: 20px;">Need More Help?</h3>
            <p>Join the SourceBans++ Discord community for additional support:</p>
            <a href="https://discord.gg/4Bhj6NU" target="_blank" class="discord-button">Join Discord</a>
        </div>

        <div class="sdr-warning">
            <h3>Important Note About Steam Datagram Relay (SDR)</h3>
            <p>SourceBans++ does <strong>not</strong> support servers using Steam Datagram Relay (SDR) for the following reasons:</p>
            <ul>
                <li>SDR uses randomized public IP addresses which are not compatible with SourceBans++ connection methods</li>
                <li>RCON commands cannot be sent through SDR connections</li>
                <li>Using the server's real IP address as a workaround in SourceBans++ would expose this IP in the public web UI, compromising server security</li>
            </ul>
            <p>If your server uses SDR, you will need to disable it for SourceBans++ integration to work properly.</p>
        </div>
        
        <?php } // End of fsockopen check ?>
        <?php endif; // End of server IP check ?>
        
        <div class="footer">
            SourceBans++ Connection Debug Tool &copy; <?php echo date('Y'); ?>
        </div>
    </div>
</body>
</html>
