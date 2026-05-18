---
title: Database errors
description: Common MySQL / MariaDB errors during install or runtime — and how to fix them.
sidebar:
  order: 3
---

The most common database errors SourceBans++ users hit, with the
fix that usually resolves each. They're ordered roughly by how
often they show up.

If the panel reports `Could not find driver` instead of one of the
errors below, it's not a SQL error — PHP can't load the MySQL driver
at all. Jump to [Driver not found](/troubleshooting/could-not-find-driver/).

## Access denied

> `SQLSTATE[HY000] [1045] Access denied for user '…'@'…' (using password: YES)`

The database rejected the credentials you provided. Three things to
check, in order:

1. **The username and password are correct.** Typos sneak in through
   copy-paste, especially special characters in passwords.

2. **The user is allowed to connect from this host.** MariaDB / MySQL
   user grants include the source host (`'user'@'192.168.1.5'`).
   A user grant of `'sourcebans'@'localhost'` won't accept a
   connection from `'192.168.1.5'` even with the right password.

   The canonical grant syntax is in
   [Database setup → Granting permission](/setup/mariadb/#granting-permission).

3. **Remote connections are allowed.** Many shared hosts disable
   remote DB access by default. If the panel and the database are
   on different hosts, your host's control panel will usually have
   a "Remote MySQL" or "Allowed hosts" setting to whitelist the
   panel's IP.

## Can't connect to MySQL server

> `Can't connect to MySQL server on '…'`

Usually one of:

- **The database server isn't running.** Check with
  `systemctl status mariadb` (or `mysql`).
- **The TCP port is blocked** by a firewall or port-blocking
  service. The default is `3306`.
- **Wrong host / port** in `config.php`. `localhost` and `127.0.0.1`
  are not always equivalent on Unix — `localhost` typically routes
  through a socket file, while `127.0.0.1` uses TCP.

If the panel and database are on the same host, the **socket path**
is what matters (default `/tmp/mysql.sock` or
`/var/run/mysqld/mysqld.sock`). If they're on different hosts, the
**IP / hostname and port** are what matter.

## Lost connection to MySQL server

> `Lost connection to MySQL server during query`

Most often: the DB's `bind-address` isn't set up to accept your
panel's connection. See
[Database setup → Configure for remote connections](/setup/mariadb/#configure-for-remote-connections).

Can also indicate **flaky network connectivity** between the panel
host and the database host. If it happens intermittently, this is
almost always it.

More rarely, the **initial connection** times out. If your
`connect_timeout` is set to only a few seconds, bump it to ten or
more — useful on slow long-distance connections.

## MySQL server has gone away

> `MySQL server has gone away` or
> `CR_SERVER_GONE_ERROR` / `CR_SERVER_LOST`

The database closed the connection before the panel finished using
it. Common causes:

- **Idle timeout.** The DB closed a long-idle connection. Usually
  benign; the panel reconnects on the next request.
- **`max_allowed_packet` exceeded.** Rare on a normal panel install,
  but possible if you have unusually long ban reasons / chat
  messages. Increase `max_allowed_packet` on the DB server.
- **The DB was restarted.** The panel's existing pool of connections
  becomes invalid until the next reconnect.

## Too many connections

> `Too many connections`

All available connection slots are in use by other clients on the
DB. Most often seen on shared hosting with a low `max_connections`
cap.

Two paths forward:

- **Raise `max_connections`** on the DB, if you have access. The
  default is usually 151; bump to 200 or 500 for a busy panel +
  multiple game servers.
- **Reduce concurrent panel users.** Rare, but a runaway script
  that opens many connections without closing them can spike the
  count.

## Table doesn't exist

> `Table 'sourcebans._foo' doesn't exist`

The table the panel tried to read isn't there. This almost always
means **the updater didn't finish** when you last upgraded.

Re-run the updater:

```
https://example.com/updater/
```

Watch the output for errors. If a specific migration step fails,
the page will say which one and why — share that in
`#help-support` if you can't decipher it.

## Incorrect string value

> `Incorrect string value: '\xF0\x9F…' for column …`

The DB or table is using the 3-byte `utf8` charset alias instead of
real `utf8mb4`. A player name with emoji or 4-byte CJK characters
won't fit.

The fix is to convert the database (and its tables) to `utf8mb4`:

```sql
ALTER DATABASE `sourcebans` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Then per table:
ALTER TABLE `_admins` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `_bans` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- (etc. for every panel table)
```

Back up your database before running these — `CONVERT TO` rewrites
every row.

## Anything else

If your error isn't here, share it in `#help-support` on our
[Discord](https://discord.gg/tzqYqmAtF5) with:

- The full error message (including the SQL state code).
- What you were doing when it fired (install, page load, save).
- The PHP and DB versions.

We'll triage from there.
