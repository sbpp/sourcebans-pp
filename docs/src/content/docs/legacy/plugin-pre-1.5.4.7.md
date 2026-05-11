---
title: Plugin — upgrading from version <= 1.5.4.7
description: One-off cleanup steps when upgrading the SourceMod plugin half from a pre-1.6 SourceBans++ install.
# Astro 5 strips dots from filename-derived slugs (the canonical path
# would otherwise be `/legacy/plugin-pre-1547/`, which loses the
# version intent). Pin the slug explicitly so the URL reads naturally.
slug: legacy/plugin-pre-1.5.4.7
sidebar:
  order: 2
---

:::caution
This page covers an upgrade pathway for **legacy** SourceBans++ versions
(pre-1.6 plugin). The information may be inaccurate or out of date.
Modern installs follow [Updating → Plugin](/updating/#plugin) instead.
:::

The pre-1.6 plugin shipped multiple separate `.smx` files; current
releases consolidate them under the `sbpp_*` prefix. Clean those up
during the upgrade:

1. Upload and overwrite all contents in `game` to your root game
   directory.

2. Reconfigure the config files in
   `addons/sourcemod/configs/sourcebans/`.

3. **Delete the legacy plugins**:
   - `sourcebans.smx`
   - `sourcecomms.smx`
   - `sbchecker.smx`
   - `sb_admcfg.smx`
   - `SourceSleuth.smx`

4. Restart the server and verify the new `sbpp_*.smx` plugins are
   loaded via `sm plugins list`.

If the plugin doesn't load cleanly after the swap, see
[Could not find driver](/troubleshooting/could-not-find-driver/) and
[Database errors](/troubleshooting/database-errors/) — the same
diagnostic paths apply to legacy and current plugin builds.
