<h1 align="center">
    <a href="https://sbpp.github.io"><img src="https://raw.githubusercontent.com/sbpp/sourcebans-pp/v1.x/.github/logo.png" height="25%" width="25%"/></a>
    <br/>
    SourceBans++
</h1>

### [![GitHub release](https://img.shields.io/github/release/sbpp/sourcebans-pp.svg?style=flat-square&logo=github&logoColor=white)](https://github.com/sbpp/sourcebans-pp/releases) [![License: CC BY-SA 4.0](https://img.shields.io/badge/License-CC_BY--SA_4.0-blue.svg)](https://github.com/sbpp/sourcebans-pp/blob/php81/LICENSE.md) [![GitHub issues](https://img.shields.io/github/issues/sbpp/sourcebans-pp.svg?style=flat-square&logo=github&logoColor=white)](https://github.com/sbpp/sourcebans-pp/issues) [![GitHub pull requests](https://img.shields.io/github/issues-pr/sbpp/sourcebans-pp.svg?style=flat-square&logo=github&logoColor=white)](https://github.com/sbpp/sourcebans-pp/pulls) [![GitHub All Releases](https://img.shields.io/github/downloads/sbpp/sourcebans-pp/total.svg?style=flat-square&logo=github&logoColor=white)](https://github.com/sbpp/sourcebans-pp/releases) [![Discord](https://img.shields.io/discord/298914017135689728.svg?style=flat-square&logo=discord&label=discord)](https://discord.gg/4Bhj6NU)


Global admin, ban, and communication management system for the Source engine

### Issues
If you have an issue you can report it [here](https://github.com/sbpp/sourcebans-pp/issues/new).
To solve your problems as fast as possible fill out the **issue template** provided
or read how to report issues effectively [here](https://coenjacobs.me/2013/12/06/effective-bug-reports-on-github/).

### Useful Links

* Website: [SourceBans++](https://sbpp.github.io/)
* Install help: [SourceBans++ Docs](https://sbpp.github.io/docs/)
* FAQ: [SourceBans++ FAQ](https://sbpp.github.io/faq/)
* Forum Thread: [SourceBans++ - AlliedModders](https://forums.alliedmods.net/showthread.php?p=2303384)
* Discord Server: [SourceBans++ - Discord](https://discord.gg/4Bhj6NU)

### Requirements

```
* Webserver
  o PHP 8.5 or higher
    * ini setting: memory_limit greater than or equal to 64M
    * GMP extension
  o MySQL 5.6 or MariaDB 10 and higher
* Source Dedicated Server
  o MetaMod: Source
  o SourceMod: Greater Than or Equal To 1.11
```

## How to install a SourceBans++ release version

The easiest way of installing SourceBans++ is to use a [release version](https://github.com/sbpp/sourcebans-pp/releases), since 
those come bundled with all requiered code dependencies and pre-compiled sourcemod plugins.

The [quickstart](https://sbpp.github.io/docs/quickstart/) guide gives you a detailed walktrough of the installation process.

## How to install the current master branch version

The master branch doesn't include the required dependencies or compiled plugins you need to run SourceBans++.
Here is a quick summary of getting the master branch code up and running.

> **Upgrading from 1.x?** v2.0.0 introduces new PHP dependencies and
> resets `config.theme` to `default`. Read [`UPGRADING.md`](UPGRADING.md)
> **before** you `git pull` or unzip a release tarball over an existing
> install — the `composer install` step is required for git-based
> upgrades and is not optional.

### Installing webpanel dependencies
- Follow the [quickstart](https://sbpp.github.io/docs/quickstart/) guide and upload the webpanel files to your web server
- Install [composer](https://getcomposer.org/) - [Installation Guide](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos)
- Go to the root of your SourceBans++ installation (where index.php is located)
- run ```composer install```

After successfully installing all dependencies you can procede with the [quickstart](https://sbpp.github.io/docs/quickstart/) guide.

### Local development with Docker

A one-command Docker stack (PHP 8.5 + Apache, MariaDB, Adminer, Mailpit) is
included for rapid local iteration:

```sh
./sbpp.sh up        # build + start; panel at http://localhost:8080 (admin/admin)
./sbpp.sh logs web  # tail logs
./sbpp.sh phpstan   # run static analysis in the container
./sbpp.sh down      # stop (./sbpp.sh reset to also drop volumes)
```

The DB schema and a default admin are seeded automatically on first boot —
no installer wizard required. Full guide in [`docker/README.md`](docker/README.md).

### Static analysis (PHPStan)

The web panel is checked with [PHPStan](https://phpstan.org/) on every pull request. To run it locally:

```sh
cd web
composer install
includes/vendor/bin/phpstan analyse
```

Existing violations are captured in `web/phpstan-baseline.neon` so CI passes from a clean tree; new code should be free of new errors. If a legitimate change fixes a baseline entry, regenerate the baseline:

```sh
cd web
includes/vendor/bin/phpstan analyse --generate-baseline=phpstan-baseline.neon
```

Configuration lives in `web/phpstan.neon`.

## Privacy & telemetry

SourceBans++ 2.0.0 ships with anonymous opt-out telemetry (#1126). Once
per day per install, the panel sends a small JSON payload to a Cloudflare
Worker so maintainers can see what versions, environments, and feature
toggles are actually in use. The data drives roadmap decisions
("can we drop PHP 8.5?", "is anyone still using `enablefriendsbanning`?"
"is the legacy theme path worth keeping alive?") that would otherwise
be made blind.

The contract this section locks down:

- **Default-on, opt-out.** Opt-in telemetry returns ~1% of installs and
  is statistically useless. The toggle is loud, the docs (this section
  + [`UPGRADING.md`](UPGRADING.md) + the `## Privacy` heading in the
  2.0.0 [`CHANGELOG.md`](CHANGELOG.md)) make the data flow obvious so
  opting out is a real choice, not a buried surprise.
- **Opt-out path.** Admin → Settings → Features → Telemetry. The toggle
  flips `telemetry.enabled` to `0`, **and** clears
  `telemetry.instance_id` so a re-enable mints a fresh per-install ID
  the Worker can't link to the previous one. Enable / disable
  transitions are audit-logged once (`Log::add(LogType::Message,
  'Telemetry', 'Telemetry enabled|disabled by <admin>')`); pings
  themselves are never logged.
- **Anonymous by design.** The payload contains a random
  `instance_id` (`bin2hex(random_bytes(16))`, persisted in
  `:prefix_settings.telemetry.instance_id`) plus the categorical /
  count fields below. The panel **never** sends hostnames, IPs,
  install paths, admin names, ban reasons, dashboard text, SteamIDs,
  server hostnames, server IPs, MOTDs, SMTP credentials, or the Steam
  API key value.
- **Scheduling.** Each request runs a tiny tick at shutdown
  (`register_shutdown_function`); on FPM, `fastcgi_finish_request()`
  closes the user's socket BEFORE the cURL POST so telemetry never
  delays a panel response. The cooldown is 24h ± 1h jitter and the
  `last_ping` slot is reserved atomically (`UPDATE … WHERE
  CAST(value AS UNSIGNED) <= :threshold` with rowCount==1) so two
  concurrent requests can't both fire. cURL has 3s connect / 5s
  total timeouts and any non-2xx is silent — a flapping endpoint
  costs one ping/day, not one ping/request.
- **Self-hosted collector.** The endpoint URL lives in
  `:prefix_settings.telemetry.endpoint` (default
  `https://cf-analytics-telemetry.sbpp.workers.dev/v1/ping`). Operators
  who want to redirect telemetry to their own collector can update that
  row directly (no UI for it on purpose — it's a debug / escape-hatch
  knob); setting it to `''` disables network calls without flipping
  the user-facing toggle. The Worker source lives at
  [sbpp/cf-analytics](https://github.com/sbpp/cf-analytics).

### Field list (schema 1)

The complete payload, sourced from the vendored
`web/includes/Telemetry/schema-1.lock.json`. The
`TelemetryReadmeParityTest` PHPUnit test deep-equals this list against
`Sbpp\Telemetry\Schema1::payloadFieldNames()`, so the docs and the wire
format can never silently drift.

<!-- TELEMETRY-FIELDS-START -->
- `schema` — wire-format version (always `1` for this lock file).
- `instance_id` — random 32-char hex per install. Cleared on opt-out.
- `panel.version` — `SB_VERSION` (release tag, `git describe`, or `'dev'`).
- `panel.git` — short git SHA when available, else empty.
- `panel.dev` — true iff `panel.version` is the `'dev'` sentinel.
- `panel.theme` — `default` for the shipped theme; `custom` for any fork (the fork's actual name is never reported).
- `env.php` — PHP `major.minor` only (e.g. `8.5`). Patch elided.
- `env.db_engine` — `mariadb` or `mysql`, derived from `SELECT VERSION()`.
- `env.db_version` — DB `major.minor` only (e.g. `10.11`).
- `env.web_server` — substring match against `$_SERVER['SERVER_SOFTWARE']`: `apache`, `nginx`, `litespeed`, `iis`, `caddy`, or `other`.
- `env.os_family` — lowercased `PHP_OS_FAMILY`: `linux`, `windows`, `mac`, `bsd`, or `other`.
- `scale.admins` — `SELECT COUNT(*) FROM :prefix_admins` (includes the seeded `CONSOLE` row).
- `scale.servers_enabled` — `SELECT COUNT(*) FROM :prefix_servers WHERE enabled = 1`.
- `scale.bans_active` — `SELECT COUNT(*) FROM :prefix_bans WHERE (ends > UNIX_TIMESTAMP() OR length = 0) AND RemoveType IS NULL`. Mirrors `page.banlist.php`'s active-ban definition.
- `scale.bans_total` — `SELECT COUNT(*) FROM :prefix_bans` (every row, active + expired + removed).
- `scale.comms_active` — `SELECT COUNT(*) FROM :prefix_comms WHERE (ends > UNIX_TIMESTAMP() OR length = 0) AND RemoveType IS NULL`.
- `scale.comms_total` — `SELECT COUNT(*) FROM :prefix_comms`.
- `scale.submissions_30d` — `SELECT COUNT(*) FROM :prefix_submissions WHERE submitted >= UNIX_TIMESTAMP() - 2592000`.
- `scale.protests_30d` — `SELECT COUNT(*) FROM :prefix_protests WHERE datesubmitted >= UNIX_TIMESTAMP() - 2592000`.
- `features.submit` — `Config::getBool('config.enablesubmit')`.
- `features.protest` — `Config::getBool('config.enableprotest')`.
- `features.comms` — `Config::getBool('config.enablecomms')`.
- `features.kickit` — `Config::getBool('config.enablekickit')`.
- `features.exportpublic` — `Config::getBool('config.exportpublic')`.
- `features.publiccomments` — `Config::getBool('config.enablepubliccomments')`.
- `features.steamlogin` — `Config::getBool('config.enablesteamlogin')`.
- `features.normallogin` — `Config::getBool('config.enablenormallogin')`.
- `features.groupbanning` — `Config::getBool('config.enablegroupbanning')`.
- `features.friendsbanning` — `Config::getBool('config.enablefriendsbanning')`.
- `features.adminrehashing` — `Config::getBool('config.enableadminrehashing')`.
- `features.smtp_configured` — true iff `:prefix_settings.smtp.host` is non-empty (the host string itself is never reported).
- `features.steam_api_key_set` — true iff `STEAMAPIKEY` is `define()`d to a non-empty value (the key value is never reported).
- `features.geoip_present` — true iff the Maxmind `data/GeoLite2-Country.mmdb` exists and is readable.
<!-- TELEMETRY-FIELDS-END -->

### On raw `scale.*` counts (privacy trade-off)

Schema 1 ships raw integer counts (e.g. `bans_active: 2847`) rather than
bucketed strings (`"1k-9.9k"`). Raw counts combined with `panel.theme`,
`panel.git`, and `env.*` produce a higher-resolution per-install
fingerprint than buckets would.

The trade-off is acceptable for v2.0.0 because:

1. The data lives only in the Worker's Cloudflare Analytics Engine
   dataset — never in logs, extracts, or row-granularity exports.
2. The Worker strips `CF-Connecting-IP` / `X-Forwarded-For` before any
   logging, so the per-install fingerprint can't be tied back to a
   public IP.
3. Access to the dataset is roadmap-decision-only — there is no public
   stats dashboard, no anonymous extract, no row-level API.

**Any future change that exposes row-level data** (a public stats page,
downloadable extracts, etc.) **reopens this decision and requires a
privacy review before shipping.**

### Schema evolution

There is no auto-update for self-hosted SourceBans++ installs — old
panels keep sending old payloads forever. The Worker is built to handle
that: schema-1 validators are kept indefinitely on the Worker side.
Panel-side rules:

1. **Additive — new optional field within `schema: 1`.** Add the
   extractor to `Telemetry::collect()` once
   `web/includes/Telemetry/schema-1.lock.json` lists the field.
2. **Subtractive / repurposing — rare; bumps the schema number.** The
   panel sends `schema: 2`; a sibling `Sbpp\Telemetry\Schema2` helper
   covers the new shape. The existing `Schema1` helper stays around
   indefinitely for the long tail of un-upgraded installs.

Only `schema` and `instance_id` are required. Every other field is
optional both in `Telemetry::collect()`'s output and in the Worker's
validator.

### Manual schema sync

`web/includes/Telemetry/schema-1.lock.json` is vendored byte-for-byte
from the cf-analytics companion repo. Sync with:

```sh
make sync-telemetry-schema
```

The two parity tests (`TelemetrySchemaParityTest`,
`TelemetryReadmeParityTest`) gate the result — adding a typed slot in
cf-analytics → next sync → the panel build fails until a matching
extractor + README bullet are added.

## Upgrade
*If you ran the installer, this step is unnecessary.*

Upgrading from 1.6/1.7, requires a new [configuration value](/blob/php81/web/config.php.template#L43) to be set. To do this, please run the `upgrade.php` script.
Once done, delete it, as it may output sensitive information.

#### Smarty
#### Updated Smarty version dropped support for the `{php}` tag. 
Custom themes must use the new [`{load_template}`](https://github.com/sbpp/sourcebans-pp/blob/php81/web/includes/SmartyCustomFunctions.php#L54) tag.

#### JWT Update
*If you ran the installer or upgrade file, this step is unecessary.* \
JWT secrets are no longer stored in the database as they are generated using a secret key. 


### Compiling SourceMod plugins
Follow the Guide '[Compiling SourceMod Plugins](https://wiki.alliedmods.net/Compiling_SourceMod_Plugins)' from the official SourceMod Wiki

## Built With

* [SourceMod](http://www.sourcemod.net/) - Scripting platform for the Source Engine - [License](https://raw.githubusercontent.com/sbpp/sourcebans-pp/v1.x/.github/SOURCEMOD-LICENSE.txt) - [GPL v3](https://raw.githubusercontent.com/sbpp/sourcebans-pp/v1.x/.github/GPLv3)
* [SourceBans 1.4.11](https://github.com/GameConnect/sourcebansv1) - Base of this project - [GPL v3](https://raw.githubusercontent.com/sbpp/sourcebans-pp/v1.x/.github/GPLv3) - [CC BY-NC-SA 3.0](https://github.com/sbpp/sourcebans-pp/blob/v1.x/LICENSE)
* [SourceComms](https://github.com/d-ai/SourceComms) - [GPL v3](https://raw.githubusercontent.com/sbpp/sourcebans-pp/v1.x/.github/GPLv3)
* [SourceBans TF2 Theme](https://forums.alliedmods.net/showthread.php?t=252533)

## Contributing

Please read [CONTRIBUTING.md](https://github.com/sbpp/sourcebans-pp/blob/v1.x/CONTRIBUTING.md) for details on our code of conduct, and the process for submitting pull requests to us. Read [SECURITY.md](https://github.com/sbpp/sourcebans-pp/blob/v1.x/SECURITY.md) if you have a Security Bug in SourceBans++

## Authors

* **GameConnect** - *Initial Work / SourceBans* - [GameConnect](https://www.gameconnect.net/)
* **Sarabveer Singh** - *Initial Work on SourceBans++* - [Sarabveer](https://github.com/Sarabveer)
* **Alexander Trost** - *Continuing Development on SourceBans++* - [Galexrt](https://github.com/galexrt)
* **Marvin Lukaschek** - *Continuing Development on SourceBans++* - [Groruk](https://github.com/groruk)

See also the list of [contributors](https://github.com/sbpp/sourcebans-pp/graphs/contributors) who participated in this project.

## License

This SourceMod plugins of this project are licensed under the `GNU GENERAL PUBLIC LICENSE Version 3` (GPLv3) [License](https://raw.githubusercontent.com/sbpp/sourcebans-pp/v1.x/.github/GPLv3).
The Web panel is licensed under the `Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported` (CC BY-NC-SA 3.0) [License](https://github.com/sbpp/sourcebans-pp/blob/v1.x/LICENSE).
