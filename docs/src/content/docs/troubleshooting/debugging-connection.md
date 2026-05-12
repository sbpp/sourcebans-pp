---
title: Server connection issues
description: Why the panel can't read your game server's info / players, and how to fix it.
sidebar:
  order: 4
  label: Server connection
---

When the panel shows a game server as offline — or as online but
with an empty player list — it can't reach the server's query
(UDP) or RCON (TCP) port. This page walks through the checks in
order, from "is the server actually online?" to "test it with the
diagnostic tool."

If you haven't yet, [Network ports](/setup/ports/) lists what
needs to be open and from which direction.

## Quick checklist

Before reaching for the diagnostic tool, confirm the basics:

- **Your firewall isn't blocking the game server's port** on UDP
  or TCP, from the panel host's perspective.
- **The game server is online to people outside your network.**
  You can join it in-game from off-network.
- **You can use RCON from in-game** (e.g.
  `rcon_password yourpass; rcon status` in the developer console).
  If this fails too, see [TCP errors](#tcp-errors) below.

If all three pass and the panel still can't read the server, run
the diagnostic tool.

## The diagnostic tool

A small `sb_debug_connection.php` ships in the panel's web root.
It tests the UDP query and RCON sides independently and reports
exactly what fails.

### Configure

Open `sb_debug_connection.php` in your editor and fill in the
server details near the top:

```php
$serverip = "";    // e.g. "1.2.3.4"
$serverport = "";  // defaults to 27015 if left empty
$serverrcon = "";  // optional; leave empty to only test serverinfo
```

### Run

Navigate to the file in your browser:

```
https://example.com/sb_debug_connection.php
```

If everything's wired right, it'll print the server's info (map,
hostname, player count), the result of the RCON handshake (if you
filled in a password), and the player list.

If something fails, it'll tell you which step — that maps directly
to the sections below.

:::caution
The diagnostic tool exposes server info and (if you filled it in)
the RCON password. **Delete `sb_debug_connection.php` when you're
done** — don't leave it on a production install.
:::

## UDP errors

> The panel can't read server info / player list back from the game
> server.

UDP is the protocol Source uses for its A2S queries — the panel
sends a small "tell me about yourself" packet and waits for the
reply.

Common causes:

- **A firewall is blocking UDP**, either on the panel host
  (outgoing UDP to the game server's port) or on the game server
  host (incoming UDP from the panel's IP). Open it in both
  directions.

- **The game server is behind NAT** without a UDP port-forward.
  Add one for the game server's port on UDP.

- **The game server has `listip`-banned the panel's IP.** Source
  game servers maintain a separate IP block list (`cfg/banned_ip.cfg`)
  that silently drops packets from blocked IPs. If you see the
  panel's IP in there, remove the line and run `removeip <IP>`
  via RCON.

- **The game server isn't bound to a public IP.** If `srcds` is
  started without `-ip`, it can default to a private interface.
  See [Bind-IP fix](#bind-ip-fix) below.

## TCP errors

> The panel can read server info but can't open an RCON session.

RCON uses TCP. The panel reads server info via UDP (which works)
but the TCP handshake to push admin commands fails.

Common causes:

- **A firewall is blocking TCP** on the game server's port from
  the panel's host. UDP and TCP are usually allowed together, but
  some restrictive firewalls treat them separately.

- **The RCON password is wrong** in the panel. Edit the server
  under **Admin Panel → Server Settings**, fix the password, save.

- **The game server isn't listening for RCON at all.** If you can't
  RCON from in-game either, the server needs `-usercon` in its
  launch parameters to enable RCON for in-game use too.

- **The game server isn't bound to a public IP.** See
  [Bind-IP fix](#bind-ip-fix) below.

## Bind-IP fix

If your dedicated server has multiple IPs, or runs on a host with
both public and private interfaces, `srcds` can default to listening
only on the loopback or private interface. The panel can't reach a
loopback-bound server from another host.

The fix is to explicitly bind to the public IP via the `-ip`
launch parameter:

```sh
./srcds_run -game tf -ip 1.2.3.4 +port 27015 ...
```

Restart the server, then re-test from the panel.

## Empty player list specifically

If the panel shows the server as online with the right map and
hostname but **the player list is empty** even when you can see
players in-game, the server's hiding the player list from external
queries. The fix is one line in `server.cfg`:

```
host_players_show 2
```

Reload the map (or restart the server) and the panel will see the
full player list on its next poll.

## Still stuck?

Post in `#help-support` with:

- The output of `sb_debug_connection.php`.
- Whether you can RCON from in-game.
- Where the panel and the game server are hosted (same machine,
  same network, different providers, …).
- The game server's launch parameters and `server.cfg` (redacting
  the RCON password).

We'll narrow it down from there.
