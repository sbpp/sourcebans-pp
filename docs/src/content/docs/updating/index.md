---
title: Updating SourceBans++
description: Safely upgrade an existing SourceBans++ install — web panel and plugin.
sidebar:
  order: 1
---

Upgrades are routine: drop in the new release, visit the updater
URL, and SourceBans++ migrates any database schema changes for you.
This page covers the safe upgrade path for both halves.

:::tip
Upgrading from **1.8.x to 2.0.x**? Read
[Upgrading from 1.8.x to 2.0.x](/updating/1-8-to-2-0/) first — v2.0
raises the PHP version floor, resets the active theme, and ships
default-on anonymous telemetry.
:::

## Before you start

**Always back up your database** before an upgrade. The updater
scripts are idempotent (re-running them is safe), but a half-completed
upload or a fatal PHP error mid-migration is much easier to recover
from when you have a snapshot.

A simple `mysqldump` or your hosting control panel's "Backup database"
button is enough. Don't skip this step.

## Download

Grab the latest release zip from the
[Releases page](https://github.com/sbpp/sourcebans-pp/releases). You
want the `sourcebans-pp-X.Y.Z.webpanel-only.zip` for the web side and
`sourcebans-pp-X.Y.Z.plugin-only.zip` for the game side.

## Upgrade the web panel

1. **Back up your database.** (Last reminder.)

2. **Switch back to the default theme** if you've been running a
   custom one. Custom themes often lag the panel's template version
   and can break the first paint after upgrade.

   Navigate to **Admin Panel → Settings → Themes** and select
   "default" before uploading.

3. **Upload and overwrite** all contents of the new `web/` folder
   onto your existing SourceBans++ installation.

4. **Delete the `install/` directory** if it's still there from a
   previous install. The panel actively refuses to boot while it
   exists.

5. **Visit the updater URL** in your browser:

   ```
   https://example.com/updater/
   ```

   (or `/sb/updater/` if you installed into a subfolder.)

   The updater walks through each pending database migration. When
   it prints **`Installation up-to-date.`**, the schema is current.

6. **Delete the `updater/` directory** when it's done.

:::caution
Leaving `install/` or `updater/` accessible after an upgrade is a
foot-gun — anyone who can reach the panel can re-run them. The
panel guards against `install/` automatically; the `updater/`
directory has no such guard, so it's on you to remove it.
:::

## Upgrade the plugin

1. **Upload and overwrite** all contents of the new `game/` folder
   to your game server's root.

2. **Review the plugin config files** at
   `addons/sourcemod/configs/sourcebans/` — new versions sometimes
   add new options. Defaults are sensible if you don't touch them.

3. **Reload the map or restart the game server** so SourceMod picks
   up the new plugin versions.

4. Tail `addons/sourcemod/logs/` after the restart and confirm the
   plugins loaded cleanly.

## Version-specific notes

### Upgrading from 1.8.x to 2.0.x

The biggest jump SourceBans++ has shipped — PHP 8.5 requirement,
chrome rewrite, default-on telemetry. The full breakdown lives in
[Upgrading from 1.8.x to 2.0.x](/updating/1-8-to-2-0/).

### Upgrading from 1.6.x or 1.7.0 to 1.8.x

1.7.0+ requires **PHP >= 8.5** and added an `SB_SECRET_KEY` value to
`config.php` used by the JWT-based session manager. Existing installs
need that value generated before they can log in.

1. **Upgrade PHP to >= 8.5** before uploading the new panel files.

2. Follow the regular [web panel upgrade steps](#upgrade-the-web-panel)
   above.

3. **Set the JWT signing secret by hand.** SourceBans++ 1.7 moved the
   panel's session-signing key into `config.php` as `SB_SECRET_KEY`.
   Older releases shipped an `upgrade.php` helper that wrote it for
   you, but it was removed in 2.0
   ([#903](https://github.com/sbpp/sourcebans-pp/issues/903)) because
   of a security issue — set the value manually instead.

   Skip the rest of this step if your existing `config.php` already
   contains a `define('SB_SECRET_KEY', '…')` line (anyone who ran
   `upgrade.php` on a previous upgrade already has one). Otherwise,
   generate a random base64 secret on the host:

   ```sh
   openssl rand -base64 47 | tr -d '\n'
   ```

   And add the result to `web/config.php`, before any closing `?>`:

   ```php
   define('SB_SECRET_KEY', '<paste the secret here>');
   ```

4. If you use a custom theme, note that **Smarty 5 (which 1.7.0+
   uses) dropped the `{php}` tag**. Custom themes that relied on
   `{php}` need to switch to the
   [`{load_template}`](https://github.com/sbpp/sourcebans-pp/blob/main/web/includes/SmartyCustomFunctions.php)
   tag instead.

### Upgrading the plugin from <= 1.5.4.7

Pre-1.6 plugin installs shipped multiple separate `.smx` files;
current releases consolidate them under the `sbpp_*` prefix. If
you're crossing this boundary, after uploading the new `game/`
contents, **delete the legacy plugin files** from
`addons/sourcemod/plugins/`:

- `sourcebans.smx`
- `sourcecomms.smx`
- `sbchecker.smx`
- `sb_admcfg.smx`
- `SourceSleuth.smx`

The new `sbpp_main.smx`, `sbpp_comms.smx`, `sbpp_checker.smx`,
`sbpp_admcfg.smx`, and `sbpp_sleuth.smx` replace them. Restart the
game server and run `sm plugins list` to confirm the new set loaded
cleanly.

### Anything older

If you're on an install older than 1.6.x and the pages above don't
cover your starting point, drop into our
[Discord](https://discord.gg/tzqYqmAtF5) `#help-support` channel —
we'll walk you through it.

## After the upgrade

A quick smoke test once everything's uploaded:

1. **Log out and back in.** This exercises the session path; if
   the JWT secret or session storage is misconfigured you'll catch
   it here.

2. **Visit `?p=admin&c=settings`.** This is one of the heavier admin
   surfaces; if it paints cleanly, the chrome is healthy.

3. **Visit a game server's row.** If the panel can reach it (player
   list, map, online indicator), the cross-component plumbing
   survived.

4. **Apply and lift a test ban.** Confirms the write path through
   to the SourceMod plugin still works.

If anything looks off, the most useful starting points are:

- [Panel not loading](/troubleshooting/panel-not-loading/) — blank
  page or hanging tab after upgrade.
- [Database errors](/troubleshooting/database-errors/) — if the
  panel reports a SQL or table error.
- [Driver not found](/troubleshooting/could-not-find-driver/) — if
  the plugin can't talk to the DB after the plugin upgrade.
