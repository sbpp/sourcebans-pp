---
title: Adding a server
description: Register a game server with SourceBans++ and wire the SourceMod plugin's ServerID.
sidebar:
  order: 1
---

Each game server you want SourceBans++ to manage gets registered with
the web panel once. The panel assigns it a numeric `ServerID`, you
write that ID into the SourceMod plugin's config on the game server,
and from that point on bans, mutes, and admin actions flow both ways.

## What you'll need

Before adding a server, have these on hand:

- The game server's **public IP and port** (`27015` is the default
  for most Source mods). If you're behind NAT, the IP is the one
  players use to connect, not your LAN IP.
- The server's **RCON password**. You can find this in your server's
  startup config (`server.cfg` or similar) under `rcon_password`.
- The **game / mod** the server runs (Team Fortress 2,
  Counter-Strike: Source, Garry's Mod, etc.). Each lives in its own
  mod folder — `tf`, `cstrike`, `garrysmod`, …

If you have RCON disabled or don't know the password, set one in
`server.cfg` and reload the server first — the panel can't manage
a server without RCON access.

## Register the server in the panel

1. Sign into the web panel as an admin with the **Add server**
   permission (owners have this by default).

2. Navigate to **Admin Panel → Server Settings → Add new server**.

3. Fill in:

   - **Game** — pick the mod from the dropdown.
   - **IP** — the server's public IP.
   - **Port** — the server's port (`27015` if you didn't change it).
   - **RCON password** — the password from `server.cfg`.

4. Click **Add server**.

5. After the page reloads, find the new row in the server list and
   **note the `ID` column**. That number is the `ServerID` you'll
   write into the SourceMod plugin's config in the next step.

The panel attempts an RCON connection right away to validate the
details. If it can't reach the server, you'll see a warning — but
the row still gets saved, so you can fix the issue (firewall, IP,
password) and the panel will reconnect on the next poll.

## Wire the plugin's `ServerID`

On the game server itself:

1. Open `addons/sourcemod/configs/sourcebans/sourcebans.cfg`.

2. Set `ServerID` to the value you noted above:

   ```ini
   "ServerID"  "5"   // example
   ```

3. Save and re-upload the file (if you're editing locally).

4. **Reload the map or restart the server** so SourceMod picks up
   the change.

If you skip the `ServerID` step, the plugin will still try to write
bans to the database but with no server-side identity — meaning the
panel can't tell them apart from bans on other servers.

## Verifying

Back in the panel's **Servers** page, your new server should show:

- A green **online** indicator within ~30 seconds.
- The current map and player count.
- The hostname the server reports.

If it doesn't:

- The panel can probably reach the server but can't read the player
  list back — see
  [Server connection issues](/troubleshooting/debugging-connection/)
  for the firewall / `listip` / RCON checklist.

- If you also can't ban from in-game, the plugin likely can't reach
  the database — see [Driver not found](/troubleshooting/could-not-find-driver/)
  and [Database errors](/troubleshooting/database-errors/).

:::tip
Adding a second or third server later? Repeat the same two halves
(panel registration + plugin `ServerID` edit) on each new server.
Each server gets its own row in the panel and its own
`ServerID` — the `databases.cfg` section stays the same across all
servers as long as they share the same database.
:::
