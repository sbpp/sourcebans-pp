---
title: Database errors
description: Common MySQL / MariaDB error messages you'll see during install or runtime.
sidebar:
  order: 3
---

A catalog of the database errors SourceBans++ users hit most often,
with the typical fixes for each.

If your error isn't listed here, check the
[Could not find driver](/troubleshooting/could-not-find-driver/) page
first — that's the one error that fires before any of the messages
below have a chance to.

## Access denied

If you're not self-hosting, **make sure your host allows remote
connections** — many shared hosts disable remote DB access by default.

This problem is otherwise tied to user accounts and which hosts they
permit. See [MariaDB → Granting permission](/setup/mariadb/#granting-permission)
for the canonical `CREATE USER` / `GRANT` syntax.

## Can't connect to MySQL server

Usually means there's no MySQL server running on the system, or that
the panel is using the wrong Unix socket / TCP port.

Check that the TCP port isn't blocked by a firewall or port-blocking
service.

A MySQL client on Unix can connect to the `mysqld` server two ways:

- via a Unix socket file in the filesystem (default `/tmp/mysql.sock`),
- via TCP/IP (default port `3306`).

If you're connecting from the same host, the socket path matters; if
you're connecting from a different host, the IP + port matter.

## Lost connection to MySQL server

Most commonly: `bind-address` isn't commented out (or set to `0.0.0.0`
/ `::`). See [MariaDB → Configuring](/setup/mariadb/#configuring).

It can also indicate network connectivity trouble — check the network
condition if it happens frequently. If the message includes
"during query", that's the network case.

More rarely it can fire on the **initial** connection. If your
`connect_timeout` is set to only a few seconds, increase it to ten or
more — useful on slow / long-distance connections.

## MySQL server has gone away

The most common reason: the server timed out and closed the connection.
You'll see one of these error codes (which one is OS-dependent):

| Error code           | Description |
| -------------------- | ----------- |
| `CR_SERVER_GONE_ERROR` | The client couldn't send a question to the server. |
| `CR_SERVER_LOST`       | The client wrote to the server but didn't get a full answer (or any answer). |

Other common causes:

- A query that's incorrect or too large. If `mysqld` receives a packet
  that's too large or out of order, it assumes something has gone
  wrong with the client and closes the connection. If you need big
  queries (e.g. large BLOBs), increase `max_allowed_packet` on the
  server (default: 4MB) — and the equivalent on the client end.

## Too many connections

All available connections are in use by other clients.

This shows up most often on shared hosting, where you're given the
minimal resource allotment.

The connection cap is controlled by the `max_connections` system
variable.

## Table doesn't exist

As the error suggests, the table isn't there — which usually means
your install is corrupted or the [updater](/updating/) didn't finish.

Re-run the updater (`example.com/updater`) and watch its output for
errors. If a specific table is missing, that's the migration step
that didn't complete.
