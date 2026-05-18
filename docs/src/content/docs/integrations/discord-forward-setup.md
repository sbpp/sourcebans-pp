---
title: Discord notifications
description: Forward in-game bans, reports, and comm blocks from SourceBans++ to a Discord channel.
sidebar:
  order: 1
  label: Discord notifications
---

If you want SourceBans++ to post a Discord message every time
something happens in-game — a ban, a player report, a mute / gag —
that's what
[`sbpp/discord-forward`](https://github.com/sbpp/discord-forward) is
for. It's a separate SourceMod plugin (not part of the SourceBans++
core download), so it gets installed on each game server alongside
the regular plugin set.

:::note
The Discord forwarder posts only for **in-game** events. Bans
applied from the web panel UI don't trigger Discord messages —
that's a long-standing
[feature request](https://github.com/sbpp/discord-forward/issues).
:::

## Before you start

You'll need:

- **A Discord channel webhook URL.** In Discord:
  channel → cog icon → **Integrations** → **Webhooks** → **New
  Webhook** → **Copy Webhook URL**.
  You can use the same webhook for all event types or split into
  separate channels (one for bans, one for reports, etc.).

- **Two SourceMod extensions** on each game server:
  - [SteamWorks](http://users.alliedmods.net/~kyles/builds/SteamWorks/) —
    Steam Web API access from SourceMod.
  - [SMJansson](https://forums.alliedmods.net/showthread.php?t=184604) —
    JSON support for SourceMod.

  Both go in `addons/sourcemod/extensions/` on the game server.

## Install

1. Grab the latest `sbpp_discord.smx` from
   [discord-forward Releases](https://github.com/sbpp/discord-forward/releases).

2. Upload it to `addons/sourcemod/plugins/` on each game server you
   want to forward.

3. **Reload the map or restart the server.** SourceMod will load
   the plugin and auto-generate a config file at
   `cfg/sourcemod/sbpp_discord.cfg`.

## Configure

After the first load, edit `cfg/sourcemod/sbpp_discord.cfg` (or
copy the convars to `autoexec.cfg`) and set the values you want.

### Webhook URLs

Set the URL for each event type. Leave any URL empty to disable
that channel.

| Convar | Posts to Discord when … |
| --- | --- |
| `sbpp_discord_banhook` | a player is banned in-game |
| `sbpp_discord_reporthook` | a player reports another via `!report` |
| `sbpp_discord_commshook` | a player is muted or gagged |

### Appearance and links

| Convar | What it controls |
| --- | --- |
| `sbpp_discord_username` | username shown on the webhook message (default `Sourcebans++`) |
| `sbpp_discord_pp_url` | URL to a profile picture for the webhook |
| `sbpp_website_url` | base URL of your SourceBans++ panel — embeds link admin / player names back to it when set |
| `sbpp_discord_roleid` | Discord role ID to mention when a report comes in; leave empty to disable the mention |

After saving the config, reload the map so SourceMod re-reads the
convars.

## Verifying

The fastest test: have someone ban a test player from in-game (or
ban yourself by accident from a second account). The webhook
should fire within a couple of seconds.

If no message arrives:

- **Check the SourceMod logs** for the plugin (`addons/sourcemod/logs/`).
  Failed webhook posts are logged there.
- **Confirm both extensions are loaded** with `sm exts list` —
  SteamWorks and SMJansson should both show `Running`.
- **Test the webhook URL with curl** to make sure Discord still
  accepts it:

  ```sh
  curl -X POST -H 'Content-Type: application/json' \
    -d '{"content":"test"}' \
    https://discord.com/api/webhooks/...
  ```
