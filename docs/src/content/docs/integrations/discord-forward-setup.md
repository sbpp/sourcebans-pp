---
title: Discord forward setup
description: Forwarding bans, reports, and comm blocks from SourceBans++ into a Discord channel via webhook.
sidebar:
  order: 1
---

The Discord forwarder is a separate SourceMod plugin
([`sbpp/discord-forward`](https://github.com/sbpp/discord-forward))
that posts in-game ban / report / comm-block events to a Discord
webhook. It's distinct from the core SB++ plugin — install it
alongside if you want Discord notifications.

## Prerequisites

- [SteamWorks](http://users.alliedmods.net/~kyles/builds/SteamWorks/)
- [SMJansson](https://forums.alliedmods.net/showthread.php?t=184604)

Both are SourceMod extensions; install them under
`addons/sourcemod/extensions/` before loading the forwarder plugin.

## Installing

1. Grab the latest `sbpp_discord.smx` from the
   [discord-forward releases](https://github.com/sbpp/discord-forward/releases).

2. Upload the plugin to `addons/sourcemod/plugins/`.

## Configuring

After loading the plugin once, SourceMod auto-generates
`cfg/sourcemod/sbpp_discord.cfg` containing every convar below. Edit
that file (or copy the convars to `autoexec.cfg`) and set the webhook
URLs you want to use. Leave any hook empty to disable that channel.

### Webhook endpoints

- `sbpp_discord_banhook` — webhook for ban events
- `sbpp_discord_reporthook` — webhook for in-game reports
- `sbpp_discord_commshook` — webhook for communication blocks (mute / gag)

### Appearance & links

- `sbpp_discord_username` — username shown on the webhook message
  (default: `Sourcebans++`)
- `sbpp_discord_pp_url` — URL to a profile picture used by the webhook
- `sbpp_website_url` — base URL of your SourceBans++ web panel; embeds
  will link admins / players back to it when set
- `sbpp_discord_roleid` — Discord role ID to mention when a report
  comes in. Leave empty to disable the mention

:::note
The forwarder only fires for actions taken **in-game**. It has no
effect on actions taken in the web panel — e.g. a ban applied from
the panel UI won't post to Discord. (See the upstream tracker for
the long-standing feature request to mirror panel actions.)
:::
