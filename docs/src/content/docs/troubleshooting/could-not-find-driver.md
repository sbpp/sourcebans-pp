---
title: Driver not found
description: Fixing the "could not find driver" error on both the panel and plugin sides.
sidebar:
  order: 2
  label: Driver not found
---

`Could not find driver` (or `could not find driver "MySQL"`) means
SourceBans++ can't find a usable MySQL/MariaDB client library. The
fix depends on which half is complaining: the web panel uses PHP's
PDO driver, and the SourceMod plugin uses SourceMod's own MySQL
extension.

## Web panel

The panel uses PHP's `pdo_mysql` extension. Two common reasons it
isn't loaded:

### The PHP MySQL package isn't installed

On Debian / Ubuntu, the package name includes the PHP version:

```sh
sudo apt install php8.5-mysql
```

Adjust `8.5` to whatever PHP version your panel runs under.

On Fedora / RHEL: `sudo dnf install php-mysqlnd`.

After installing, restart your webserver / PHP-FPM:

```sh
sudo systemctl restart php8.5-fpm
# or, for Apache mod_php:
sudo systemctl restart apache2
```

### The extension is installed but not loaded

Confirm with:

```sh
php -m | grep pdo_mysql
```

If that command prints nothing, the module isn't loaded.

Open your `php.ini` and look for an `extension=pdo_mysql` line. If
it's missing or commented out, add it. The `php.ini` location varies
by setup; `php --ini` prints the path it's actually reading.

Restart PHP-FPM / your webserver again after the change.

## SourceMod plugin

The plugin uses SourceMod's `dbi.mysql` extension. The error usually
means the extension itself is missing or fails to load because of an
external dependency.

### Check the extension is loaded

Connect to your game server's RCON console and run:

```
sm exts list
```

Find `MySQL-DBI` in the list. It should show `Running`. If it
shows `Failed to load` or doesn't appear at all, continue below.

### Check the file exists and is executable

Confirm `dbi.mysql.ext.so` (Linux) or `dbi.mysql.ext.dll` (Windows)
exists in `addons/sourcemod/extensions/`. On Linux, make sure
it's executable:

```sh
chmod u+x addons/sourcemod/extensions/dbi.mysql.ext.so
```

### Install `zlib` (Linux only)

The extension depends on `zlib`. Most Linux distros ship it already,
but minimal installs sometimes don't:

| Distro | Command |
| --- | --- |
| 32-bit Debian / Ubuntu | `apt install zlib1g` |
| 64-bit Debian / Ubuntu | `apt install lib32z1` |
| 32 / 64-bit Fedora | `dnf install zlib.i686` |
| 32-bit SUSE | `zypper install libz1` |
| 64-bit SUSE | `zypper install libz1-32bit` |

You'll need SSH access to the game server's host to run these.

### Diagnose missing libraries

If the extension still won't load, ask the linker what it's
missing:

```sh
ldd -d -r addons/sourcemod/extensions/dbi.mysql.ext.so
```

Each line marked `not found` is a missing shared library. Search
your distro's package manager for whichever one is missing and
install it.

## Verifying

After the fix, restart the relevant service:

- **Web side:** restart PHP-FPM / your webserver, then reload the
  panel. The install wizard's System Check should now show
  `pdo_mysql` as available.
- **Plugin side:** reload the map (`changelevel` via RCON) or
  restart the server. `sm exts list` should show MySQL-DBI as
  Running.

If you're still stuck, drop into `#help-support` on our
[Discord](https://discord.gg/tzqYqmAtF5) with the output of
`php -m | grep pdo` (web) or `sm exts list` (plugin) — that
narrows it down quickly.
