---
title: Could not find driver "MySQL"
description: Debugging the missing-MySQL-driver error on the web side and the plugin side.
sidebar:
  order: 2
---

The `Could not find driver "MySQL"` error fires when SB++ — either
the web panel or the SourceMod plugin — can't find a usable MySQL
client library. The fix differs depending on which half of the
install is complaining.

## Web

The panel uses PHP's PDO + `pdo_mysql` extension. Two common reasons
the extension isn't loaded:

- The right `mysql` extension package for your PHP version isn't
  installed. On Debian / Ubuntu the package name embeds the PHP
  version — for PHP 8.5, install `php8.5-mysql`. Replace the version
  number with whichever PHP version the panel runs under.

- The `pdo_mysql` module isn't loaded. Confirm with:

  ```sh
  php -m | grep pdo_mysql
  ```

  If that command prints nothing, the module isn't loaded — check your
  `php.ini` for an `extension=pdo_mysql` line, then restart your
  webserver / PHP-FPM.

For the full PHP-side prerequisites, see
[Prerequisites](/getting-started/prerequisites/).

## Plugin

The game plugin uses SourceMod's `dbi.mysql` extension.

- Make sure `dbi.mysql.ext.so` (or `dbi.mysql.ext.dll` on Windows)
  exists in `addons/sourcemod/extensions/` and is loaded:

  ```
  sm exts list
  ```

  The extension must show up in the list with status `Running`.

- Make sure the file is executable:

  ```sh
  chmod u+x addons/sourcemod/extensions/dbi.mysql.ext.so
  ```

- Install `zlib` — required by the extension on Linux. **You'll need
  SSH access for this.**

  | Distro | Command |
  | ------ | ------- |
  | 32-bit Debian / Ubuntu | `apt-get install zlib1g` |
  | 64-bit Debian / Ubuntu | `apt-get install lib32z1` |
  | 32 / 64-bit Fedora     | `yum install zlib.i686` |
  | 32 / 64-bit Mandriva   | `urpmi zlib1` |
  | 32-bit SUSE            | `zypper install libz1` |
  | 64-bit SUSE            | `zypper install libz1-32bit` |

- If none of the above resolves it, list the extension's missing
  dynamic dependencies:

  ```sh
  ldd -d -r addons/sourcemod/extensions/dbi.mysql.ext.so
  ```

  Anything marked `not found` is what to install next; search for the
  package name your distro uses.
