---
title: Ports
description: Required firewall ports for the panel and game server.
sidebar:
  order: 3
---

The panel reaches your game servers over UDP (server query / map name
/ player list) and TCP (RCON). Both have to be reachable from the host
the panel runs on, and the game server itself needs its own listening
ports open to the public internet.

## Web panel

- **UDP incoming** on the game server port — needed so the panel can
  read server info / player list back when it polls.
- **TCP outgoing** on the game server port — needed for RCON.

The "incoming" / "outgoing" wording is from the panel host's
perspective: the panel sends a UDP query, the game server replies, so
the panel needs to **receive** UDP back; the panel opens an outbound
TCP connection for RCON, so it needs to **send** TCP out.

## Game server

- **Server port (UDP & TCP)** — Source's standard combination. The
  default for most game mods is `27015`; it varies by mod and by
  how many servers your host runs on the same IP.

If you're behind a NAT / home router, you'll need port-forwards on
both protocols.

## Troubleshooting

If the panel shows the server as offline or the player list empty, see
[Debugging connection](/troubleshooting/debugging-connection/) — it
walks through the UDP / TCP / `listip` checklist with the included
`sb_debug_connection.php` tool.
