---
title: Plugin setup
description: Wiring the SourceMod side of SourceBans++ — databases.cfg, sourcebans.cfg, and the optional companion plugins.
sidebar:
  order: 2
---

:::note
This page is **new content authored for the docs migration** (#1333) —
the legacy `sbpp.github.io` site never had a dedicated plugin-setup
page. The configs and plugin list below are sourced from the live
`game/addons/sourcemod/scripting/` tree and should be verifiable
against your release zip; if you spot a divergence, please open a PR
or drop a note in `#help-support` on Discord.
:::

The plugin half of SourceBans++ is what actually applies bans /
mutes / gags in-game and reports new admin actions back to the panel.
This page covers the two config files the plugin needs and the
optional companion plugins that round out the experience.

## Required configs

### `databases.cfg`

Edit `addons/sourcemod/configs/databases.cfg` and add a `sourcebans`
section pointing at the same database the panel uses:

```ini
"sourcebans"
{
    "driver"        "default"
    "host"          "EDITME_DATABASE_HOST_EDITME"
    "database"      "EDITME_DATABASE_EDITME"
    "user"          "EDITME_USERNAME_EDITME"
    "pass"          "EDITME_PASSWORD_EDITME"
    "port"          "3306" // EDIT IF NEEDED
}
```

:::caution
The web panel will offer to generate this section for you, but it's a
best-effort suggestion — review the values before pasting. If the
panel and the game server are on different hosts, double-check the
`host` is reachable from the game server's network and that your DB
is configured to allow remote connections.
:::

### `sourcebans.cfg`

`addons/sourcemod/configs/sourcebans/sourcebans.cfg` carries
per-server tunables. The most important field is **`ServerID`** — the
numeric ID the panel assigns when you
[add the server](/setup/adding-server/). Without it, the plugin can
record bans against the wrong server (or no server at all).

After editing, reload the map or restart the game server so the
plugin re-reads the config.

## Common companion plugins

These ship in the same release as the core plugin (one `.sp` source
per plugin under `game/addons/sourcemod/scripting/`); load only what
you need.

- **`sbpp_main.smx`** — the core plugin. Required.
- **`sbpp_comms.smx`** — communication blocks (mute / gag).
- **`sbpp_checker.smx`** — checks newly-connected clients against
  the panel's ban list (catches bans applied while the player was
  offline).
- **`sbpp_report.smx`** — in-game `!report` command.
- **`sbpp_sleuth.smx`** — alt-account detection helper.
- **`sbpp_admcfg.smx`** — admin-config loader. Reads admin / group /
  override files from the panel into SourceMod (replaces SourceMod's
  stock `admin-flatfile.smx` + `sb_admcfg.smx` from older builds).

Plus the standalone Discord forwarder shipped from
[`sbpp/discord-forward`](https://github.com/sbpp/discord-forward) —
see [Discord forward setup](/integrations/discord-forward-setup/).

## Verifying

After your first ban via the panel or in-game, the
`addons/sourcemod/logs/sourcebans/` directory should grow a new log
entry. If it doesn't, the plugin can't reach the database — see:

- [Could not find driver](/troubleshooting/could-not-find-driver/) —
  the SourceMod-side `dbi.mysql.ext.so` extension is missing or
  failing to load.
- [Database errors](/troubleshooting/database-errors/) — the
  database is reachable but the queries themselves fail.
- [Debugging connection](/troubleshooting/debugging-connection/) —
  the panel can't reach the game server (server-list / RCON
  failures).
