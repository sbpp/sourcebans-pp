---
title: Debugging connection
description: Troubleshooting server-list and RCON connection issues between the panel and your game server.
sidebar:
  order: 4
---

When the panel shows your game server as offline (or the player list
is empty even though players are connected), the panel can't reach the
game server's query / RCON ports. This page walks through the checks
in order.

If you haven't yet, start with the [Ports](/setup/ports/) reference for
the firewall protocols + directions.

## Check list

Before reaching for the debug tool, confirm the basics:

- Your host isn't blocking traffic on the game server port —
  **UDP incoming** and **TCP outgoing** from the panel's perspective.
- Your game server is online to people **outside** of your network,
  with no error binding the port.
- You can connect to the server in-game from outside.
- You can use RCON through the in-game console — if not, see
  [TCP error](#tcp-error) below.

## Using the debug tool

In SourceBans++'s root directory there's a connection-debug tool
named `sb_debug_connection.php`.

Edit this section of that file with the corresponding server info:

```php
/**
 * Config part
 * Change to IP and port of the gameserver you want to test
 */
$serverip = "";
$serverport = ""; // Defaults to 27015 if left empty
$serverrcon = ""; // Leave empty if you're only testing the serverinfo connection
```

Once edited, navigate to the file in your browser. If everything is
wired right, it will attempt to connect to the specified server and
report what it finds (server info, RCON handshake, player list).

:::caution
This tool exposes diagnostic info about your game server (including
the RCON password if you fill it in). Delete `sb_debug_connection.php`
when you're done — don't leave it accessible on a production install.
:::

## UDP error

The panel can't read server info / player list back from the game
server.

- Make sure your host isn't blocking **UDP incoming** on the panel's
  side and **UDP outgoing** on the game server's side.
- If hosting locally, make sure your router / firewall is forwarding
  the UDP port.
- Make sure the game server hasn't `listip`-banned your web server's
  IP. If it has, remove the line from `cfg/banned_ip.cfg` and run
  `removeip <IP>` via RCON.

## TCP error

The panel can read server info but can't open an RCON session.

- Make sure your host isn't blocking **TCP outgoing** to the game
  server.
- Make sure the game server is **explicitly bound** to a public IP via
  the `-ip` launch parameter — otherwise it may be listening only on
  the loopback / private interface.
- If you can't use RCON via the in-game console either, append
  `-usercon` to the launch parameter so the server enables RCON for
  in-game use.
