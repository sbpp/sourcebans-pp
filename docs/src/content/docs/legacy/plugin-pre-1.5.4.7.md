---
title: Plugin upgrade from <= 1.5.4.7
description: One-off cleanup steps when upgrading the SourceMod plugin from a pre-1.6 SourceBans++ install.
slug: legacy/plugin-pre-1.5.4.7
sidebar:
  order: 2
---

:::caution
This page covers an upgrade path for **legacy** SourceBans++
plugins (pre-1.6). Modern installs follow
[Updating → Upgrade the plugin](/updating/#upgrade-the-plugin)
instead.
:::

The pre-1.6 plugin shipped as several separate `.smx` files. Current
releases consolidate them under the `sbpp_*` prefix. If you're
upgrading from a pre-1.6 install, you need to remove the old files
manually so SourceMod doesn't try to load both halves at once.

## Steps

1. **Upload and overwrite** all contents of the new `game/` folder
   onto your game server's root.

2. **Update the plugin config files** in
   `addons/sourcemod/configs/sourcebans/` — the v1.6+ format may
   have new fields. The defaults are sensible if you're not sure.

3. **Delete the old plugin files** from `addons/sourcemod/plugins/`:

   - `sourcebans.smx`
   - `sourcecomms.smx`
   - `sbchecker.smx`
   - `sb_admcfg.smx`
   - `SourceSleuth.smx`

   The new consolidated plugins (`sbpp_main.smx`, `sbpp_comms.smx`,
   `sbpp_checker.smx`, `sbpp_admcfg.smx`, `sbpp_sleuth.smx`)
   replace all of them.

4. **Restart the game server** so SourceMod loads the new plugin
   set cleanly.

5. **Verify** with `sm plugins list` — you should see the new
   `sbpp_*` entries loaded and no errors about the old plugins.

## If something doesn't load

If a plugin fails to load after the swap, the most likely causes
are the same ones the current install can hit:

- [Driver not found](/troubleshooting/could-not-find-driver/) —
  the SourceMod-side MySQL extension isn't available.
- [Database errors](/troubleshooting/database-errors/) — the
  database is reachable but the queries themselves fail.

If neither of those covers it, share the SourceMod log entry from
`addons/sourcemod/logs/` in our
[Discord](https://discord.gg/tzqYqmAtF5) `#help-support` channel.
