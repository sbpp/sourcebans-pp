---
title: Network ports
description: Which ports SourceBans++ needs open between the web panel and your game servers.
sidebar:
  order: 4
  label: Network ports
---

For the web panel to manage a game server it has to reach two things
on that server: the **query port** (UDP, to read the map and player
list) and the **RCON port** (TCP, to push admin commands). On a stock
Source server those are the same number — usually `27015`.

## What needs to be open

From the **web panel's perspective**:

- **Outgoing UDP** to each game server's port — the panel sends an
  `A2S_INFO` query and waits for the reply.
- **Outgoing TCP** to each game server's port — the panel opens an
  RCON session to push commands.

From the **game server's perspective**:

- **Incoming UDP** on its port — for the panel's query and for
  players joining.
- **Incoming TCP** on its port — for the panel's RCON connection.

On a typical setup these are all the **same port** (`27015` by
default), just two different protocols. If your firewall lets you
specify one or the other, you usually need both.

## Behind NAT / a home router

If you're hosting at home or anywhere with a NAT-ing router, you'll
need port-forwards on **both UDP and TCP** for the game server's
port to its LAN IP. Most consumer routers expose this under
"Port forwarding" or "Virtual servers".

The web panel doesn't need any inbound ports of its own beyond HTTP
(80) or HTTPS (443).

## Troubleshooting

If your panel shows the server as offline or with no player list,
the most common cause is a firewall blocking one of these ports.

The walkthrough is in
[Server connection issues](/troubleshooting/debugging-connection/) —
it includes a small `sb_debug_connection.php` tool you can run from
the panel host to test the UDP and RCON sides independently.
