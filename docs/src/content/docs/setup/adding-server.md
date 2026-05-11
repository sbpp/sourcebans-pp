---
title: Adding a server
description: How to register a game server with SourceBans++ and wire the SourceMod plugin's ServerID.
sidebar:
  order: 1
---

How to import a game server into SourceBans++. The handshake is in two
halves: register the server in the web panel to get a `ServerID`, then
write that ID into the plugin's config so the plugin knows where to
report bans / blocks back to.

## Adding a server to the web panel

1. Navigate to **Admin Panel** → **Server Settings** → **Add new server**.

2. Fill in the server details with a correct `RCON Password` and click
   **Add Server**.

3. After the page reloads, **note the `ID` column** for the new row —
   that's the `ServerID` you'll write into the plugin config below.

## Configuring SourceBans++'s ServerID

1. Navigate to `addons/sourcemod/configs/sourcebans/`.

2. Edit `sourcebans.cfg` and set `ServerID` to the value you noted above.

3. Save, re-upload the file, and reload the map (or restart the
   server) so the plugin picks up the new config.

:::tip
If the panel can't reach the game server after this — empty player list,
RCON failures — see [Debugging connection](/troubleshooting/debugging-connection/)
for the firewall / bind-IP / RCON checklist.
:::
