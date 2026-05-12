---
title: Plugin setup
description: Wire up the SourceMod side of SourceBans++ — databases.cfg, sourcebans.cfg, and the optional companion plugins.
sidebar:
  order: 2
---

The SourceMod plugins are what actually enforce bans, mutes, and gags
in-game and report new admin actions back to the panel. This page
covers the two config files every install needs, and the optional
companion plugins that round out the experience.

If you're installing for the first time, the
[Quickstart](/getting-started/quickstart/) covers this in context. If
you've already got the panel running and you're rolling out the plugin
to a new server, this is the page you want.

## Required configs

The plugin needs two config files filled in: one to tell SourceMod how
to reach the database, and one to tell SourceBans++ which server it's
running on.

### `databases.cfg`

`addons/sourcemod/configs/databases.cfg` is a SourceMod-wide file that
lists every database any plugin on the server can talk to. Add a
`sourcebans` block pointing at the same database your web panel uses:

```ini
"sourcebans"
{
    "driver"        "default"
    "host"          "EDITME_DATABASE_HOST"
    "database"      "EDITME_DATABASE_NAME"
    "user"          "EDITME_DATABASE_USER"
    "pass"          "EDITME_DATABASE_PASSWORD"
    "port"          "3306"
}
```

:::caution
The web panel offers to generate this block for you, but treat it as
a starting point — review every value before pasting. The panel
guesses based on its own config, which is often wrong for game
servers on a different host.

If the panel and the game server live on **different networks**,
your DB will also need to allow the game server's IP in the user
grant — see
[Database setup → Granting permission](/setup/mariadb/#granting-permission).
:::

### `sourcebans.cfg`

`addons/sourcemod/configs/sourcebans/sourcebans.cfg` is the per-server
config. The single most important field is **`ServerID`** — the
numeric ID the panel assigned when you
[added the server](/setup/adding-server/). Without a correct
`ServerID`, bans applied from in-game won't show up against the right
server in the panel.

The rest of the file controls in-game admin menu behaviour (ban
durations, default reasons, immunity flags, …). The defaults are
sensible; tweak as you go.

After editing either file, **reload the map or restart the game
server** so SourceMod re-reads the configs.

## Companion plugins

A SourceBans++ release ships several `.smx` files. Only `sbpp_main.smx`
is mandatory; the others are opt-in based on what you want:

| Plugin                | What it does | Most installs want it? |
| --------------------- | ------------ | ---------------------- |
| `sbpp_main.smx`       | The core. Ban / unban, in-game admin menu, panel write-back. | **Yes** — required. |
| `sbpp_comms.smx`      | Communication blocks (mute / gag). | Yes if you care about voice / text moderation. |
| `sbpp_checker.smx`    | Re-checks every connecting client against the panel's ban list — catches bans issued while the player was offline. | Yes — closes a real loophole. |
| `sbpp_report.smx`     | In-game `!report` command for players. | Up to you. |
| `sbpp_sleuth.smx`     | Detects alt accounts by recording IP / SteamID associations. | Useful for larger communities. |
| `sbpp_admcfg.smx`     | Loads admin / group / override definitions from the panel into SourceMod. Replaces SourceMod's stock `admin-flatfile.smx` and pre-1.6 `sb_admcfg.smx`. | Yes if you want panel-managed admins. |

Drop the plugins you want into `addons/sourcemod/plugins/` and remove
the ones you don't (SourceMod loads everything it finds, so an unused
plugin still consumes a server slot).

### Discord notifications

For a Discord-channel forwarder, install the separate
[`sbpp/discord-forward`](https://github.com/sbpp/discord-forward)
plugin. Setup is in
[Discord notifications](/integrations/discord-forward-setup/).

## Verifying

After your first ban from in-game (or from the panel), check:

- `addons/sourcemod/logs/sourcebans/` should grow a log entry.
- The new ban should appear in the panel's **Bans** page within a
  few seconds.
- A subsequent connection attempt by the banned player should be
  rejected with the ban message.

If any of these don't happen:

- **Plugin can't reach the database** — see
  [Driver not found](/troubleshooting/could-not-find-driver/).
- **Database is reachable but queries fail** — see
  [Database errors](/troubleshooting/database-errors/).
- **Panel doesn't show the server's player list** — see
  [Server connection issues](/troubleshooting/debugging-connection/).
