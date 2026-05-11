---
title: Updating SourceBans++
description: How to upgrade an existing SourceBans++ install — web panel and plugin.
sidebar:
  order: 1
---

A simple guide to upgrading SourceBans++. Always **back up your
database** before starting — the updater scripts are idempotent, but a
failed upload halfway through is much easier to recover from when you
have a snapshot to roll back to.

## Downloading

Grab the latest release zip from the
[releases page](https://github.com/sbpp/sourcebans-pp/releases).

## Web panel

1. **Back up your database** in case of corruption.

2. Switch back to the **default theme** if you haven't already — custom
   themes can lag the panel's template / Smarty version and break the
   first paint after upgrade.

3. Upload and overwrite all contents of the `web` folder onto your
   SourceBans++ installation.

4. Delete the `install` directory if it's still around from a previous
   install.

5. Navigate to your panel and append `updater` to the URL — e.g.
   `example.com/updater` or `example.com/sb/updater`.

6. When the updater displays **`Installation up-to-date.`**, delete the
   `updater` directory and you're done.

:::caution
Leaving `install/` or `updater/` accessible after the run is a
foot-gun: anyone who can reach the panel can re-run them. The panel's
`init.php` actively refuses to boot while `install/` exists; the
`updater/` directory has no such guard, so it's on you to remove it.
:::

## Web panel — upgrading from 1.6.x or 1.7.0 to 1.8.x

1.7.0+ requires PHP **>= 8.5** (see
[Prerequisites](/getting-started/prerequisites/)) and adds an
`SB_SECRET_KEY` value in `config.php` used by the JWT-based session
manager. Existing installs need to populate it before logging in.

1. Make sure your host is on PHP >= 8.5 before uploading the new files.

2. Follow the regular [Web panel](#web-panel) steps above.

3. Navigate to `example.com/upgrade.php` in your browser. The script
   will append `SB_SECRET_KEY` to `config.php` and confirm with
   **`config.php updated correctly.`**

4. **Delete `upgrade.php` from your server when done** — it can leak
   sensitive information if left exposed.

5. If you use a custom theme, note that **Smarty 5 dropped the `{php}`
   tag** — switch to the
   [`{load_template}`](https://github.com/sbpp/sourcebans-pp/blob/main/web/includes/SmartyCustomFunctions.php)
   tag instead.

:::tip
A clean re-test after upgrade: log out, log back in (this exercises
the JWT path), open `?p=admin&c=settings`, and walk one of the admin
sub-pages (e.g. `?p=admin&c=admins`). If the chrome paints and the
sidebar lights up, the upgrade landed.
:::

## Plugin

1. Upload and overwrite all contents in `game` to your root game
   directory (`tf`, `cs`, etc).

2. Reconfigure the config files in `addons/sourcemod/configs/sourcebans/`
   if you've changed any defaults.

3. Reload the map / restart the server and tail
   `addons/sourcemod/logs/` to confirm the plugin loaded cleanly.

## Plugin — upgrading from version <= 1.5.4.7

Pre-1.6 plugin installs need a one-off cleanup pass to remove the
legacy `sourcebans.smx` / `sourcecomms.smx` / `sbchecker.smx` /
`sb_admcfg.smx` / `SourceSleuth.smx` files before the new
`sbpp_*.smx` consolidated plugins take over. The full step-by-step
lives in [Legacy → Plugin upgrade from <= 1.5.4.7](/legacy/plugin-pre-1.5.4.7/).

If you're upgrading across multiple major versions, the
[Legacy](/legacy/) section catalogs the older one-off upgrade quirks.
